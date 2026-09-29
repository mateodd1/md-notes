<?php

namespace App\Services;

use App\Exceptions\MarkdownComplexityException;
use App\Models\SharedNote;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class MarkdownRenderer
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct(private readonly MarkdownMediaUrls $mediaUrls)
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            // The bounded parser rejects excessive complexity without truncating notes.
            ->withMaxInputLength(-1);

        foreach (self::policy()['elements'] as $element => $attributes) {
            $config = $config->allowElement($element, $attributes);
        }

        $this->sanitizer = new HtmlSanitizer($config
            ->forceAttribute('input', 'type', 'checkbox')
            ->forceAttribute('input', 'disabled', 'disabled'), new BoundedHtmlParser);
    }

    /** @return array{elements: array<string, list<string>>, limits: array<string, int>, fallbackMessage: string} */
    public static function policy(): array
    {
        return ['elements' => [
            'p' => [], 'div' => [], 'span' => [], 'br' => [], 'hr' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'blockquote' => [], 'ul' => [], 'ol' => ['start'], 'li' => ['value'],
            'dl' => [], 'dt' => [], 'dd' => [],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'del' => [], 's' => [], 'strike' => [],
            'code' => [], 'pre' => [], 'kbd' => [], 'sub' => [], 'sup' => [],
            'table' => [], 'caption' => [], 'colgroup' => ['span'], 'col' => ['span'],
            'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
            'th' => ['colspan', 'rowspan', 'scope', 'align', 'abbr'],
            'td' => ['colspan', 'rowspan', 'align'],
            'details' => ['open'], 'summary' => [],
            'a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height'],
            'input' => ['type', 'checked', 'disabled'],
        ], 'limits' => BoundedHtmlParser::LIMITS, 'fallbackMessage' => __('ui.markdown_plain_text_fallback')];
    }

    public function render(string $content, ?SharedNote $share = null, bool $softBreaks = true, bool $plainTextFallback = true): string
    {
        $original = $content;
        try {
            BoundedHtmlParser::assertInput($content);
            $content = $share === null
                ? $this->mediaUrls->forAuthenticatedUser($content)
                : ($share->user_id === null ? $content : $this->mediaUrls->forShare($share, $content));

            $html = Str::markdown($content, [
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
                // Let the final sanitizer remove unsupported HTML, rather than display escaped tags.
                'disallowed_raw_html' => ['disallowed_tags' => []],
                'max_nesting_level' => 100,
                'renderer' => ['soft_break' => $softBreaks ? "<br>\n" : "\n"],
            ]);

            return str_replace(['<br />', '<hr />'], ['<br>', '<hr>'], $this->sanitizer->sanitize($html));
        } catch (MarkdownComplexityException $exception) {
            if (! $plainTextFallback) {
                throw $exception;
            }

            return '<p class="markdown-fallback" role="status">'.e(__('ui.markdown_plain_text_fallback')).'</p><pre><code>'.e($original).'</code></pre>';
        }
    }
}
