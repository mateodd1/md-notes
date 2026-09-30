<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class AttachmentUploads
{
    private const TYPES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
        'txt' => ['text/plain'], 'md' => ['text/plain', 'text/markdown'],
        'csv' => ['text/plain', 'text/csv'],
    ];

    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 16_000_000;

    /** @return array{path: string, extension: string, temporary: bool} */
    public function prepare(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->getPathname();
        $bytes = (int) filesize($path);
        if ($bytes > self::MAX_BYTES) {
            throw new RuntimeException(__('ui.attachment_too_large'));
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! $file->isValid() || ! isset(self::TYPES[$extension]) || ! in_array($mime, self::TYPES[$extension], true)) {
            throw new RuntimeException(__('ui.attachment_type_not_allowed'));
        }

        if ($extension === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException(__('ui.attachment_invalid'));
            }
            $zip->close();
        }

        if (! str_starts_with($mime, 'image/')) {
            return ['path' => $path, 'extension' => $extension, 'temporary' => false];
        }

        $dimensions = @getimagesize($path);
        if (! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
            throw new RuntimeException(__('ui.attachment_image_dimensions'));
        }

        $contents = file_get_contents($path);
        if (($mime === 'image/gif' && $this->gifFrameCount($contents) !== 1)
            || ($mime === 'image/webp' && substr($contents, 12, 4) === 'VP8X' && (ord($contents[20]) & 2))
            || ($mime === 'image/png' && $this->isAnimatedPng($contents))) {
            throw new RuntimeException(__('ui.attachment_animation_not_supported'));
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException(__('ui.attachment_invalid'));
        }

        $temporary = tempnam(sys_get_temp_dir(), 'md-notes-image-');
        if ($temporary === false) {
            throw new RuntimeException(__('ui.cannot_save_attachment'));
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($image, $temporary, 90),
                'image/png' => imagepng($image, $temporary),
                'image/gif' => imagegif($image, $temporary),
                'image/webp' => imagewebp($image, $temporary, 90),
            };
            clearstatcache(true, $temporary);
            if (! $encoded || filesize($temporary) === 0) {
                throw new RuntimeException(__('ui.cannot_save_attachment'));
            }
            if (filesize($temporary) > self::MAX_BYTES) {
                throw new RuntimeException(__('ui.attachment_too_large'));
            }

            return ['path' => $temporary, 'extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'temporary' => true];
        } catch (\Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }
    }

    private function isAnimatedPng(string $contents): bool
    {
        for ($offset = 8, $length = strlen($contents); $offset + 12 <= $length;) {
            $size = unpack('N', substr($contents, $offset, 4))[1];
            if (substr($contents, $offset + 4, 4) === 'acTL') {
                return true;
            }
            $offset += 12 + $size;
        }

        return false;
    }

    private function gifFrameCount(string $contents): int
    {
        $length = strlen($contents);
        if ($length < 13) {
            return 0;
        }
        $flags = ord($contents[10]);
        $offset = 13 + (($flags & 128) ? 3 * (2 << ($flags & 7)) : 0);
        $frames = 0;
        while ($offset < $length) {
            $block = ord($contents[$offset++]);
            if ($block === 0x3B) {
                return $frames;
            }
            if ($block === 0x2C) {
                if ($offset + 9 >= $length || ++$frames > 1) {
                    return 0;
                }
                $flags = ord($contents[$offset + 8]);
                $offset += 10 + (($flags & 128) ? 3 * (2 << ($flags & 7)) : 0);
            } elseif ($block === 0x21) {
                $offset++;
            } else {
                return 0;
            }
            do {
                if ($offset >= $length) {
                    return 0;
                }
                $size = ord($contents[$offset++]);
                $offset += $size;
            } while ($size !== 0);
        }

        return 0;
    }
}
