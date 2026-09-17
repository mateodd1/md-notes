<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class StorageQuota
{
    public const DEFAULT_BYTES = 100 * 1024 * 1024;

    private const METADATA_FILES = [
        '.md-notes-order.json',
        '.md-notes-folder-order.json',
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

    public function limit(User $user): int
    {
        $configured = (int) ($user->storage_quota_bytes ?? 0);

        return $configured > 0 ? $configured : self::DEFAULT_BYTES;
    }

    public function used(User $user, ?string $root = null): int
    {
        $root ??= storage_path('app/private/spaces').'/'.$user->getKey();
        if (! is_dir($root)) {
            return 0;
        }

        $bytes = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->isLink() || in_array($file->getFilename(), self::METADATA_FILES, true)) {
                continue;
            }

            $bytes += $file->getSize();
        }

        return $bytes;
    }

    private function format(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }
}
