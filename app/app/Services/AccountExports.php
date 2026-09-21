<?php

namespace App\Services;

use App\Models\AccountExport;
use App\Models\ApiToken;
use App\Models\NoteVersion;
use App\Models\SharedNote;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class AccountExports
{
    private const LIFETIME_HOURS = 24;

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly ?string $basePath = null,
    )
    {
    }

    /** @return array{0: AccountExport, 1: string}|null */
    public function create(User $user): ?array
    {
        $this->pruneExpired();

        if (AccountExport::query()
            ->where('user_id', $user->getKey())
            ->whereDate('created_at', now()->toDateString())
            ->exists()) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $export = AccountExport::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $token),
            'archive_path' => '',
            'expires_at' => now()->addHours(self::LIFETIME_HOURS),
        ]);
        $archivePath = $this->archivePath($export);

        try {
            $this->buildArchive($user, $archivePath);
            $export->forceFill(['archive_path' => $archivePath])->save();
        } catch (\Throwable $exception) {
            File::delete($archivePath);
            $export->delete();

            throw new RuntimeException(__('ui.account_export_creation_failed'), previous: $exception);
        }

        return [$export, $token];
    }

    public function download(string $token): BinaryFileResponse
    {
        $this->pruneExpired();
        $export = AccountExport::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->firstOrFail();

        abort_unless($export->archive_path !== '' && is_file($export->archive_path), 404);

        return response()->download(
            $export->archive_path,
            'md-notes-'.now()->format('Y-m-d').'.zip',
            ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function delete(AccountExport $export): void
    {
        if ($export->archive_path !== '') {
            File::delete($export->archive_path);
        }

        $export->delete();
    }

    public function purgeForUser(User $user): void
    {
        AccountExport::query()->where('user_id', $user->getKey())->get()->each(
            fn (AccountExport $export) => $this->delete($export),
        );
    }

    private function pruneExpired(): void
    {
        AccountExport::query()->where('expires_at', '<=', now())->get()->each(
            fn (AccountExport $export) => $this->delete($export),
        );
    }

    private function archivePath(AccountExport $export): string
    {
        $directory = $this->basePath ?? storage_path('app/private/account-exports');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('ui.account_export_creation_failed'));
        }

        return $directory.'/'.$export->id.'-'.Str::lower(Str::random(24)).'.zip';
    }

    private function buildArchive(User $user, string $archivePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('ui.account_export_creation_failed'));
        }

        try {
            $zip->addFromString('account.json', $this->json([
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'storage_quota_bytes' => $user->storage_quota_bytes,
            ]));
            $zip->addFromString('shared-links.json', $this->json(SharedNote::query()
                ->where('user_id', $user->getKey())
                ->orderBy('id')
                ->get(['path', 'token', 'expires_at', 'created_at'])
                ->map(fn (SharedNote $share): array => [
                    'path' => $share->path,
                    'url' => app(ShareTokens::class)->publicUrl($share->token),
                    'expires_at' => $share->expires_at?->toIso8601String(),
                    'created_at' => $share->created_at?->toIso8601String(),
                ])->all()));
            $zip->addFromString('version-history.json', $this->json(NoteVersion::query()
                ->where('user_id', $user->getKey())
                ->orderBy('id')
                ->get(['path', 'content', 'created_at', 'updated_at'])
                ->map(fn (NoteVersion $version): array => [
                    'path' => $version->path,
                    'content' => $version->content,
                    'created_at' => $version->created_at?->toIso8601String(),
                    'updated_at' => $version->updated_at?->toIso8601String(),
                ])->all()));
            $zip->addFromString('api-tokens.json', $this->json(ApiToken::query()
                ->where('user_id', $user->getKey())
                ->orderBy('id')
                ->get(['name', 'last_used_at', 'created_at'])
                ->map(fn (ApiToken $token): array => [
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ])->all()));

            $root = $this->spaces->root($user);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }

                $relative = Str::after($file->getPathname(), $root.'/');
                $zip->addFile($file->getPathname(), 'notes/'.$relative);
            }
        } finally {
            $zip->close();
        }
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}
