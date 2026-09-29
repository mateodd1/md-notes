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
        if (preg_match($this->pattern($filename), $content) === 1) {
            return true;
        }
        if (! str_contains($content, '<')) {
            return false;
        }

        // Use exactly the same URLs that rendering rewrites, including top-level tags.
        // Do not build an unbounded DOM just to authorize an attachment request.
        $found = false;
        $this->replaceHtml($content, static function (string $reference) use ($filename, &$found): string {
            $found = $found || $reference === Str::lower($filename);

            return $reference;
        });

        return $found;
    }

    private function replace(string $content, callable $urlFor): string
    {
        $content = preg_replace_callback($this->pattern(), static function (array $matches) use ($urlFor): string {
            return $matches[1].$urlFor(Str::lower($matches[2])).$matches[3];
        }, $content) ?? $content;

        return $this->replaceHtml($content, $urlFor);
    }

    private function replaceHtml(string $content, callable $urlFor): string
    {
        // Rewrite only complete HTML attributes, preserving the source Markdown.
        // This is URL migration, not sanitization: MarkdownRenderer filters the final HTML.
        return preg_replace_callback('/<(img|a)\b((?:[^"\'<>]|"[^"]*"|\'[^\']*\')*)>/i', function (array $tag) use ($urlFor): string {
            $attribute = strtolower($tag[1]) === 'img' ? 'src' : 'href';
            $attributes = preg_replace_callback('/([^\s\/=<>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?/', function (array $matches) use ($attribute, $urlFor): string {
                if (strtolower($matches[1]) !== $attribute || ! isset($matches[2])) {
                    return $matches[0];
                }

                $value = $matches[2];
                if ($value[0] === '"' || $value[0] === "'") {
                    $value = substr($value, 1, -1);
                }
                $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $filename = $this->htmlMediaFilename($value);
                if ($filename === null) {
                    return $matches[0];
                }

                $suffix = (parse_url($value, PHP_URL_QUERY) !== null ? '?'.parse_url($value, PHP_URL_QUERY) : '')
                    .(parse_url($value, PHP_URL_FRAGMENT) !== null ? '#'.parse_url($value, PHP_URL_FRAGMENT) : '');

                return $matches[1].'="'.htmlspecialchars($urlFor($filename).$suffix, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            }, $tag[2]);

            return '<'.$tag[1].$attributes.'>';
        }, $content) ?? $content;
    }

    private function htmlMediaFilename(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['host'])) {
            $hosts = [
                parse_url((string) config('md-notes.canonical_url'), PHP_URL_HOST),
                parse_url((string) config('md-notes.workspace_url'), PHP_URL_HOST),
                ...config('md-notes.legacy_hosts', []),
            ];
            $hosts = array_map(static fn (mixed $host): string => strtolower((string) $host), $hosts);
            if (! in_array(strtolower($parts['host']), $hosts, true)) {
                return null;
            }
        }

        return preg_match('~^/(?:app/)?media/([a-z0-9]{24}\.[a-z0-9]{1,10})$~i', $parts['path'] ?? '', $matches) === 1
            ? strtolower($matches[1]) : null;
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
