<?php

namespace App\Services;

use App\Models\NoteVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class NoteMedia
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private const FILENAME_PATTERN = '/^[a-z0-9]{24}\.[a-z0-9]{1,10}$/';

    public const PENDING_UPLOAD_SECONDS = 86400;

    public function __construct(
        private readonly NoteSpace $spaces,
        private readonly StorageQuota $quota,
    ) {}

    public function store(User $user, UploadedFile $file): string
    {
        $root = $this->spaces->root($user);
        $this->quota->ensureCanAdd($user, $root, (int) $file->getSize());

        $extension = $this->extensionFor($file);
        $directory = $this->directory($user);
        $filename = Str::lower(Str::random(24)).'.'.$extension;
        $originalName = $this->sanitizeOriginalName($file->getClientOriginalName(), $filename);

        try {
            $this->markPending($directory, $filename);
            $file->move($directory, $filename);
            $this->writeOriginalName($directory, $filename, $originalName);
        } catch (\Throwable $exception) {
            $this->removeCopied($user, [$filename]);
            throw new RuntimeException(__('ui.cannot_save_attachment'), previous: $exception);
        }

        return $filename;
    }

    public function path(User $user, string $filename): string
    {
        if (! preg_match(self::FILENAME_PATTERN, $filename)) {
            abort(404);
        }

        $path = $this->directory($user).'/'.$filename;
        if (! is_file($path)) {
            abort(404);
        }

        return $path;
    }

    public function downloadName(User $user, string $filename): string
    {
        $path = $this->path($user, $filename);
        $metadata = @file_get_contents($this->namePath(dirname($path), $filename));

        return $this->sanitizeOriginalName(is_string($metadata) ? $metadata : '', $filename);
    }

    public function isImagePath(string $path): bool
    {
        return array_key_exists((string) mime_content_type($path), self::EXTENSIONS);
    }

    public function isPdfPath(string $path): bool
    {
        return mime_content_type($path) === 'application/pdf';
    }

    /** @return array{attachments_bytes: int, attachments_count: int} */
    public function attachmentSummary(User $user, string $content): array
    {
        $directory = $this->spaces->root($user).'/.md-notes-media';
        if (! is_dir($directory)) {
            return ['attachments_bytes' => 0, 'attachments_count' => 0];
        }

        $bytes = 0;
        $count = 0;
        foreach (array_unique($this->filenamesIn($content)) as $filename) {
            $path = $directory.'/'.$filename;
            if (! is_file($path) || is_link($path)) {
                continue;
            }

            $bytes += (int) filesize($path);
            $count++;
        }

        return ['attachments_bytes' => $bytes, 'attachments_count' => $count];
    }

    /**
     * @return array<int, array{filename: string, name: string, extension: string, bytes: int, is_pdf: bool}>
     */
    public function attachmentList(User $user, string $content): array
    {
        $directory = $this->spaces->root($user).'/.md-notes-media';
        if (! is_dir($directory)) {
            return [];
        }

        $attachments = [];
        foreach (array_unique($this->filenamesIn($content)) as $filename) {
            $path = $directory.'/'.$filename;
            if (! is_file($path) || is_link($path)) {
                continue;
            }

            $name = $this->downloadName($user, $filename);
            $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

            $attachments[] = [
                'filename' => $filename,
                'name' => $name,
                'extension' => $extension !== '' ? $extension : Str::lower(pathinfo($filename, PATHINFO_EXTENSION)),
                'bytes' => (int) filesize($path),
                'is_pdf' => $this->isPdfPath($path),
            ];
        }

        return $attachments;
    }

    public function pruneUnreferenced(User $user): int
    {
        $root = $this->spaces->root($user);
        $directory = $root.'/.md-notes-media';
        if (! is_dir($directory)) {
            return 0;
        }

        $referenced = $this->referencedFilenames($user, $root);
        $removed = 0;

        foreach (scandir($directory) ?: [] as $filename) {
            if ($filename === '.' || $filename === '..' || ! preg_match(self::FILENAME_PATTERN, $filename)) {
                continue;
            }

            if (isset($referenced[$filename])) {
                $this->clearPending($directory, $filename);

                continue;
            }

            $pending = $directory.'/.pending-'.$filename;
            if (is_file($pending) && (int) filemtime($pending) > now()->timestamp - self::PENDING_UPLOAD_SECONDS) {
                continue;
            }

            if (! unlink($directory.'/'.$filename)) {
                throw new RuntimeException(__('ui.cannot_delete_image'));
            }

            $removed++;
            $this->clearPending($directory, $filename);
        }

        return $removed;
    }

    /**
     * Copies attachments referenced by a note to another user's isolated
     * media directory. The returned map has the original filename as its key
     * and the recipient's filename as its value.
     *
     * @return array<string, string>
     */
    public function copyReferenced(User $source, User $recipient, string $content): array
    {
        $filenames = array_unique($this->filenamesIn($content));
        if ($filenames === []) {
            return [];
        }

        if ($source->is($recipient)) {
            return array_combine($filenames, $filenames) ?: [];
        }

        $sourceDirectory = $this->directory($source);
        $recipientDirectory = $this->directory($recipient);
        $files = [];
        $bytes = 0;

        foreach ($filenames as $filename) {
            $sourcePath = $sourceDirectory.'/'.$filename;
            if (! is_file($sourcePath) || is_link($sourcePath)) {
                continue;
            }

            $recipientFilename = $this->uniqueFilename($recipientDirectory, $filename);
            $files[$filename] = [
                'source' => $sourcePath,
                'destination' => $recipientDirectory.'/'.$recipientFilename,
                'filename' => $recipientFilename,
                'original_name' => $this->downloadName($source, $filename),
            ];
            $bytes += (int) filesize($sourcePath);
        }

        $this->quota->ensureCanAdd($recipient, $this->spaces->root($recipient), $bytes);
        $copied = [];

        try {
            foreach ($files as $filename => $file) {
                $copied[$filename] = $file['filename'];
                $this->markPending($recipientDirectory, $file['filename']);
                if (! copy($file['source'], $file['destination'])) {
                    throw new RuntimeException(__('ui.could_not_copy_shared_note'));
                }
                $this->writeOriginalName($recipientDirectory, $file['filename'], $file['original_name']);
            }
        } catch (\Throwable $exception) {
            $this->removeCopied($recipient, array_values($copied));

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException(__('ui.could_not_copy_shared_note'), previous: $exception);
        }

        return $copied;
    }

    /** @param array<int, string> $filenames */
    public function removeCopied(User $user, array $filenames): void
    {
        $directory = $this->directory($user);

        foreach (array_unique($filenames) as $filename) {
            if (preg_match(self::FILENAME_PATTERN, $filename)) {
                if (is_file($directory.'/'.$filename)) {
                    unlink($directory.'/'.$filename);
                }
                $this->clearPending($directory, $filename);
                $this->clearOriginalName($directory, $filename);
            }
        }
    }

    private function markPending(string $directory, string $filename): void
    {
        if (! touch($directory.'/.pending-'.$filename, now()->timestamp)) {
            throw new RuntimeException(__('ui.cannot_save_attachment'));
        }
    }

    private function clearPending(string $directory, string $filename): void
    {
        $marker = $directory.'/.pending-'.$filename;
        if (is_file($marker)) {
            unlink($marker);
        }
    }

    private function writeOriginalName(string $directory, string $filename, string $originalName): void
    {
        $metadata = $this->namePath($directory, $filename);
        if (file_put_contents($metadata, $this->sanitizeOriginalName($originalName, $filename), LOCK_EX) === false
            || ! chmod($metadata, 0660)) {
            throw new RuntimeException(__('ui.cannot_save_attachment'));
        }
    }

    private function clearOriginalName(string $directory, string $filename): void
    {
        $metadata = $this->namePath($directory, $filename);
        if (is_file($metadata)) {
            unlink($metadata);
        }
    }

    private function namePath(string $directory, string $filename): string
    {
        return $directory.'/.name-'.$filename;
    }

    private function sanitizeOriginalName(?string $name, string $fallback): string
    {
        $candidate = str_replace('\\', '/', trim((string) $name));
        $candidate = basename($candidate);
        $candidate = preg_replace('/[\x00-\x1F\x7F]/u', '', $candidate) ?? '';
        $candidate = trim($candidate);

        return $candidate !== '' && ! in_array($candidate, ['.', '..'], true)
            ? mb_substr($candidate, 0, 180)
            : $fallback;
    }

    /** @return array<string, true> */
    private function referencedFilenames(User $user, string $root): array
    {
        $referenced = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->isLink() || ! Str::endsWith(Str::lower($file->getFilename()), '.md')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach ($this->filenamesIn($content) as $filename) {
                $referenced[Str::lower($filename)] = true;
            }
        }

        NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->cursor()
            ->each(function (NoteVersion $version) use (&$referenced): void {
                foreach ($this->filenamesIn($version->content) as $filename) {
                    $referenced[Str::lower($filename)] = true;
                }
            });

        return $referenced;
    }

    private function directory(User $user): string
    {
        $directory = $this->spaces->root($user).'/.md-notes-media';

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException(__('ui.cannot_prepare_image_space'));
        }

        return $directory;
    }

    private function uniqueFilename(string $directory, string $sourceFilename): string
    {
        $extension = Str::afterLast($sourceFilename, '.');

        do {
            $filename = Str::lower(Str::random(24)).'.'.$extension;
        } while (file_exists($directory.'/'.$filename));

        return $filename;
    }

    /** @return array<int, string> */
    private function filenamesIn(string $content): array
    {
        preg_match_all('/(?<![a-z0-9])[a-z0-9]{24}\.[a-z0-9]{1,10}(?![a-z0-9])/i', $content, $matches);

        return array_map(Str::lower(...), $matches[0]);
    }

    private function extensionFor(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        if (isset(self::EXTENSIONS[$mime])) {
            return self::EXTENSIONS[$mime];
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : 'bin';
    }
}
