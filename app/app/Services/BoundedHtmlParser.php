<?php

namespace App\Services;

use App\Exceptions\MarkdownComplexityException;
use Dom\Element;
use Dom\Node;
use Symfony\Component\HtmlSanitizer\Parser\NativeParser;
use Symfony\Component\HtmlSanitizer\Parser\ParserInterface;

class BoundedHtmlParser implements ParserInterface
{
    public const LIMITS = [
        'maxBytes' => 8 * 1024 * 1024,
        'maxTagMarkers' => 10000,
        'maxAttributeMarkers' => 50000,
        'maxDepth' => 100,
        'maxNodes' => 20000,
        'maxAttributesPerElement' => 64,
    ];

    public static function assertInput(string $html): void
    {
        // Bound work before constructing any DOM, including malformed/unclosed HTML.
        // These deliberately conservative counts are not an HTML security filter.
        if (strlen($html) > self::LIMITS['maxBytes']
            || substr_count($html, '<') > self::LIMITS['maxTagMarkers']
            || substr_count($html, '=') > self::LIMITS['maxAttributeMarkers']) {
            throw new MarkdownComplexityException;
        }
    }

    public function parse(string $html, string $context = 'body'): Node|\DOMNode|null
    {
        self::assertInput($html);
        $root = (new NativeParser)->parse($html, $context);
        if ($root === null) {
            return null;
        }

        $pending = [[$root, 0]];
        $nodes = 0;
        while ($pending !== []) {
            [$node, $depth] = array_pop($pending);
            if (++$nodes > self::LIMITS['maxNodes'] || $depth > self::LIMITS['maxDepth']
                || ($node instanceof Element || $node instanceof \DOMElement)
                    && $node->attributes->length > self::LIMITS['maxAttributesPerElement']) {
                throw new MarkdownComplexityException;
            }
            foreach ($node->childNodes as $child) {
                $pending[] = [$child, $depth + 1];
            }
        }

        return $root;
    }
}
