<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * The reference's generated excerpt: disallowed blocks are removed whole (a
 * code block's text never reaches the excerpt), tags become spaces (list
 * items read as separate words), whitespace collapses, 55-word cap,
 * <p>-wrapped, texturized. A hand-written excerpt skips the block filter.
 */
final class Excerpt
{
    private const ALLOWED_BLOCKS = ['paragraph', 'heading', 'list', 'quote', 'preformatted'];

    public static function render(array $post): string
    {
        $source = (string) $post['post_excerpt'];
        if ($source === '') {
            $source = (string) $post['post_content'];
            if (str_contains($source, '<!-- wp:')) {
                $source = preg_replace_callback(
                    '/<!-- wp:([a-z0-9\/-]+)( \{.*?\})? -->(.*?)<!-- \/wp:\1 -->/s',
                    static fn (array $m): string => in_array($m[1], self::ALLOWED_BLOCKS, true) ? $m[0] : '',
                    $source,
                );
                $source = preg_replace('/<!-- wp:[^>]*?\/-->/', '', $source);
            }
        }
        $text = preg_replace('/<!-- \/?wp:[^>]*?-->/', '', $source);
        $text = trim(preg_replace('/<[^>]*>/', ' ', $text));
        if ($text === '') {
            return '';
        }
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $text = count($words) > 55
            ? implode(' ', array_slice($words, 0, 55)) . ' [&hellip;]'
            : implode(' ', $words);
        return Texturize::html('<p>' . $text . "</p>\n");
    }
}
