<?php

namespace App\Services;

use App\Models\NoteVersion;
use App\Models\User;
use RuntimeException;

class StorageQuota
{
    public const DEFAULT_BYTES = 100 * 1024 * 1024;

    private const METADATA_FILES = [
        '.md-notes-order.json',
        '.md-notes-folder-order.json',
        '.md-notes-folder-colors.json',
        '.md-notes-folder-collapsed.json',
        '.md-notes-pinned.json',
        'metadata.json',
    ];

    /** @return array{used: int, limit: int, available: int, percentage: int, used_human: string, limit_human: string} */
    public function summary(User $user, ?string $root = null): array
    {
        $used = $this->used($user, $root);
        $limit = $this->limit($user);

        return [
            'used' => $used,
            'limit' => $limit,
            'available' => max(0, $limit - $used),
            'percentage' => min(100, (int) round(($used / max(1, $limit)) * 100)),
            'used_human' => $this->format($used),
            'limit_human' => $this->format($limit),
        ];
    }

    public function ensureCanAdd(User $user, string $root, int $bytes): void
    {
        $bytes = max(0, $bytes);
        $summary = $this->summary($user, $root);

        if ($summary['used'] + $bytes > $summary['limit']) {
            throw new RuntimeException(__('ui.storage_quota_exceeded', [
                'used' => $summary['used_human'],
                'limit' => $summary['limit_human'],
            ]));
        }
    }

    public function ensureCanReplace(User $user, string $root, string $path, int $replacementBytes): void
    {
        $currentBytes = is_file($path) ? (int) filesize($path) : 0;

        $this->ensureCanAdd($user, $root, max(0, $replacementBytes - $currentBytes));
    }

    public function ensureCanSaveNote(User $user, string $root, string $file, string $path, string $content, bool $snapshot): void
    {
        $currentBytes = is_file($file) ? (int) filesize($file) : 0;
        $projected = $this->used($user, $root) + strlen($content) - $currentBytes;

        if ($snapshot) {
            $projected += $this->projectedVersionDelta($user, $path, $content);
        }

        $this->ensureProjectedUsage($user, $root, $projected);
    }

    public function ensureCanRecordVersion(User $user, string $root, string $path, string $content): void
    {
        $projected = $this->used($user, $root) + $this->projectedVersionDelta($user, $path, $content);

        $this->ensureProjectedUsage($user, $root, $projected);
    }

    public function limit(User $user): int
    {
        $configured = (int) ($user->storage_quota_bytes ?? 0);

        return $configured > 0 ? $configured : self::DEFAULT_BYTES;
    }

    public function used(User $user, ?string $root = null): int
    {
        $root ??= storage_path('app/private/spaces').'/'.$user->getKey();
        $bytes = 0;
        if (is_dir($root)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file->isFile() || $file->isLink() || in_array($file->getFilename(), self::METADATA_FILES, true)) {
                    continue;
                }

                $bytes += $file->getSize();
            }
        }

        return $bytes + $this->historyBytes($user);
    }

    private function ensureProjectedUsage(User $user, string $root, int $projected): void
    {
        $current = $this->used($user, $root);
        $limit = $this->limit($user);

        if ($projected > $limit && $projected > $current) {
            throw new RuntimeException(__('ui.storage_quota_exceeded', [
                'used' => $this->format($current),
                'limit' => $this->format($limit),
            ]));
        }
    }

    private function historyBytes(User $user): int
    {
        if (! $user->exists) {
            return 0;
        }

        return (int) NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('COALESCE(SUM(OCTET_LENGTH(content)), 0) AS bytes')
            ->value('bytes');
    }

    private function projectedVersionDelta(User $user, string $path, string $content): int
    {
        $versions = NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->where('path', $path)
            ->orderByDesc('id')
            ->get(['content', 'created_at']);

        $currentBytes = $versions->sum(fn (NoteVersion $version): int => strlen($version->content));
        $latestContent = $versions->first()?->content;
        $remaining = $versions
            ->filter(fn (NoteVersion $version): bool => $version->created_at->gte(now()->subDays(NoteVersionHistory::RETENTION_DAYS)))
            ->pluck('content')
            ->all();

        if ($latestContent !== $content) {
            array_unshift($remaining, $content);
        }

        $remaining = array_slice($remaining, 0, NoteVersionHistory::MAX_VERSIONS);
        $projectedBytes = array_sum(array_map('strlen', $remaining));

        return $projectedBytes - $currentBytes;
    }

    private function format(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }
}
