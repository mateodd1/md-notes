<?php

namespace App\Services;

use App\Models\NoteVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class NoteVersionHistory
{
    public const MAX_VERSIONS = 50;

    public const RETENTION_DAYS = 7;

    public function __construct(
        private readonly NoteMedia $media,
        private readonly StorageQuota $quota,
    )
    {
    }

    public function record(User $user, string $path, string $content): void
    {
        $latestContent = NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->where('path', $path)
            ->latest('id')
            ->value('content');

        if ($latestContent !== $content) {
            $this->quota->ensureCanRecordVersion($user, $this->spaceRoot($user), $path, $content);
            NoteVersion::query()->create([
                'user_id' => $user->getKey(),
                'path' => $path,
                'content' => $content,
            ]);
        }

        $this->prune($user, $path);
    }

    /** @return Collection<int, NoteVersion> */
    public function forNote(User $user, string $path): Collection
    {
        $this->prune($user, $path);

        return NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->where('path', $path)
            ->latest('id')
            ->get();
    }

    public function relocate(User $user, string $sourcePath, string $destinationPath): void
    {
        NoteVersion::query()->where('user_id', $user->getKey())->get()->each(function (NoteVersion $version) use ($sourcePath, $destinationPath): void {
            if ($version->path === $sourcePath) {
                $version->update(['path' => $destinationPath]);
            } elseif (Str::startsWith($version->path, $sourcePath.'/')) {
                $version->update(['path' => $destinationPath.Str::after($version->path, $sourcePath)]);
            }
        });
    }

    public function remove(User $user, string $path): void
    {
        NoteVersion::query()->where('user_id', $user->getKey())->get()->each(function (NoteVersion $version) use ($path): void {
            if ($version->path === $path || Str::startsWith($version->path, $path.'/')) {
                $version->delete();
            }
        });
    }

    public function pruneExpired(): void
    {
        NoteVersion::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();

        User::query()->cursor()->each(function (User $user): void {
            $this->media->pruneUnreferenced($user);
        });
    }

    private function prune(User $user, string $path): void
    {
        $versions = NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->where('path', $path);

        (clone $versions)->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();

        $staleIds = (clone $versions)->latest('id')->get(['id'])->slice(self::MAX_VERSIONS)->pluck('id');
        if ($staleIds->isNotEmpty()) {
            NoteVersion::query()->whereKey($staleIds)->delete();
        }
    }

    private function spaceRoot(User $user): string
    {
        return app(NoteSpace::class)->root($user);
    }
}
