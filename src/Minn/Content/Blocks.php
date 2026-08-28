<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Renders stored block markup the way the reference renders content.rendered:
 * delimiters removed (their surrounding whitespace stays), paragraph blocks
 * gain the wp-block-paragraph class, quote blocks gain their layout-support
 * classes, and the result is texturized. Classic content rides the
 * paragraph pipeline instead.
 */
final class Blocks
{
    public static function render(string $raw): string
    {
        if (trim($raw) !== '' && !str_contains($raw, '<!-- wp:') && !preg_match('/<(p|div|ul|ol|h\d|blockquote|pre|table|figure)[\s>]/i', $raw)) {
            return self::paragraphs($raw);
        }
        $out = preg_replace_callback(
            '/<!-- wp:paragraph( \{.*?\})? -->(.*?)<!-- \/wp:paragraph -->/s',
            static function (array $m): string {
                $inner = $m[2];
                if (preg_match('/<p\s+[^>]*class="/', $inner)) {
                    return preg_replace('/(<p\s+[^>]*class=")/', '$1wp-block-paragraph ', $inner, 1);
                }
                return preg_replace('/<p(\s|>)/', '<p class="wp-block-paragraph"$1', $inner, 1);
            },
            $raw,
        );
        $out = preg_replace_callback(
            '/<!-- wp:quote( \{.*?\})? -->(.*?)<!-- \/wp:quote -->/s',
            static fn (array $m): string => preg_replace(
                '/(<blockquote\s+[^>]*class="[^"]*)"/',
                '$1 is-layout-flow wp-block-quote-is-layout-flow"',
                $m[2],
                1,
            ),
            $out,
        );
        $out = preg_replace('/<!-- \/?wp:[^>]*?-->/', '', $out);
        return Texturize::html($out);
    }

    /**
     * Plain prose to paragraphs: texturize, split on blank lines, <br /> on
     * single newlines. Shared by classic post content and comment text.
     */
    public static function paragraphs(string $raw): string
    {
        $text = Texturize::html(trim($raw));
        if ($text === '') {
            return '';
        }
        $out = '';
        foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $text)) as $paragraph) {
            $out .= '<p>' . str_replace("\n", "<br />\n", trim($paragraph)) . "</p>\n";
        }
        return $out;
    }
}
