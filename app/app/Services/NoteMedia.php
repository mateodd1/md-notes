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

    public function __construct(private readonly NoteSpace $spaces)
    {
    }

    public function store(User $user, UploadedFile $image): string
    {
        $extension = self::EXTENSIONS[$image->getMimeType()];
        $directory = $this->directory($user);
        $filename = Str::lower(Str::random(24)).'.'.$extension;

        try {
            $image->move($directory, $filename);
        } catch (\Throwable $exception) {
            throw new RuntimeException(__('ui.cannot_save_image'), previous: $exception);
        }

        return $filename;
    }

    public function path(User $user, string $filename): string
    {
        if (! preg_match('/^[a-z0-9]{24}\.(?:jpg|png|gif|webp)$/', $filename)) {
            abort(404);
        }

        $path = $this->directory($user).'/'.$filename;
        if (! is_file($path)) {
            abort(404);
        }

        return $path;
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
            if ($filename === '.' || $filename === '..' || ! preg_match('/^[a-z0-9]{24}\.(?:jpg|png|gif|webp)$/', $filename)) {
                continue;
            }

            if (isset($referenced[$filename])) {
                continue;
            }

            if (! unlink($directory.'/'.$filename)) {
                throw new RuntimeException(__('ui.cannot_delete_image'));
            }

            $removed++;
        }

        return $removed;
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

            preg_match_all('/(?<![a-z0-9])[a-z0-9]{24}\.(?:jpg|png|gif|webp)(?![a-z0-9])/i', $content, $matches);
            foreach ($matches[0] as $filename) {
                $referenced[Str::lower($filename)] = true;
            }
        }

        NoteVersion::query()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->cursor()
            ->each(function (NoteVersion $version) use (&$referenced): void {
                preg_match_all('/(?<![a-z0-9])[a-z0-9]{24}\.(?:jpg|png|gif|webp)(?![a-z0-9])/i', $version->content, $matches);
                foreach ($matches[0] as $filename) {
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
}
