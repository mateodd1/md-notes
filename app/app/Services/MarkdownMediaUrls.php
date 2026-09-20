<?php

namespace App\Services;

use App\Models\SharedNote;
use Illuminate\Support\Str;

/**
 * Rewrites attachment URLs while Markdown is being displayed.
 *
 * Notes intentionally retain their original text. This keeps old notes and
 * versions portable while ensuring attachments continue to work after a
 * domain or route migration.
 */
class MarkdownMediaUrls
{
    /** @param array<string, string> $filenames */
    public function forAuthenticatedUser(string $content, array $filenames = []): string
    {
        return $this->replace($content, static fn (string $filename): string => route('media.show', [
            'filename' => $filenames[$filename] ?? $filename,
        ]));
    }

    public function forShare(SharedNote $share, string $content): string
    {
        return $this->replace($content, static fn (string $filename): string => route('shares.media', [
            'token' => $share->token,
            'filename' => $filename,
        ]));
    }

    public function isReferenced(string $filename, string $content): bool
    {
        return preg_match($this->pattern($filename), $content) === 1;
    }

    private function replace(string $content, callable $urlFor): string
    {
        return preg_replace_callback($this->pattern(), static function (array $matches) use ($urlFor): string {
            return $matches[1].$urlFor(Str::lower($matches[2])).$matches[3];
        }, $content) ?? $content;
    }

    private function pattern(?string $filename = null): string
    {
        $hosts = array_filter([
            parse_url((string) config('md-notes.canonical_url'), PHP_URL_HOST),
            parse_url((string) config('md-notes.workspace_url'), PHP_URL_HOST),
            ...config('md-notes.legacy_hosts', []),
        ], static fn (mixed $host): bool => is_string($host) && $host !== '');
        $hosts = array_unique(array_map('strtolower', $hosts));
        $hostPattern = implode('|', array_map(static fn (string $host): string => preg_quote($host, '/'), $hosts));
        $absoluteHost = $hostPattern === '' ? '' : '(?:(?:https?:)?\\/\\/(?:'.$hostPattern.'))?';
        $filenamePattern = $filename === null
            ? '[a-z0-9]{24}\\.[a-z0-9]{1,10}'
            : preg_quote(Str::lower($filename), '/');

        $prefix = '(!?\\[[^\\]]*\\]\\([ \\t]*<?|^[ \\t]{0,3}\\[[^\\]\\r\\n]+\\]:[ \\t]*<?)';
        $suffix = '((?:\\?[^\\s)>]*)?(?:#[^\\s)>]*)?>?)(?=[\\s)]|$)';

        return '/'.$prefix.$absoluteHost.'\\/(?:app\\/)?media\\/('.$filenamePattern.')'.$suffix.'/im';
    }
}
