<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OfflineNotes
{
    public function __construct(private readonly NoteSpace $spaces, private readonly NoteVersionHistory $history) {}

    public static function accountKey(User $user): string
    {
        return hash_hmac('sha256', $user->getKey().'|'.$user->created_at?->toISOString(), config('app.key'));
    }

    /** Compare and save while holding the same account lock as online saves. */
    public function save(User $user, string $path, string $content, string $revision, string $changeId, bool $snapshot): array
    {
        return $this->spaces->synchronized($user, function () use ($user, $path, $content, $revision, $changeId, $snapshot): array {
            $current = $this->readIfPresent($user, $path);
            $conflict = $current === null || (! hash_equals(hash('sha256', $current), $revision) && $current !== $content);
            if ($conflict) {
                // Deterministic name makes a lost response safe to retry. Never restore a deleted path.
                $name = mb_substr(pathinfo(basename($path), PATHINFO_FILENAME), 0, 30);
                $path = $name.' (offline '.str_replace('-', '', $changeId).').md';
                $existing = $this->readIfPresent($user, $path);
                if ($existing !== null && $existing !== $content) {
                    abort(409, __('offline.copy_changed'));
                }
            }

            $this->spaces->withNoteRollback($user, $path, function () use ($user, $path, $content, $snapshot, $conflict): void {
                $this->spaces->write($user, $path, $content, $snapshot || $conflict);
                if ($snapshot || $conflict) {
                    $this->history->record($user, $path, $content);
                }
            });
            app(NoteMedia::class)->pruneUnreferenced($user);

            return ['path' => $path, 'revision' => hash('sha256', $content), 'conflict' => $conflict, 'savedAt' => now()->toISOString()];
        });
    }

    private function readIfPresent(User $user, string $path): ?string
    {
        try {
            return $this->spaces->read($user, $path);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }

            return null;
        }
    }
}
