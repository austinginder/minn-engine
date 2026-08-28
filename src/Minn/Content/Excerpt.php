<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Blocks\Block;
use Minn\Blocks\Parser;

/**
 * The reference's generated excerpt, as captured from probe posts:
 *
 * - Only text-bearing blocks contribute. Containers (group, columns,
 *   column, media-text) pass their allowed children through; anything not
 *   on the list vanishes with everything inside it (code, details, buttons,
 *   images, galleries, covers, dynamic blocks, and the nested list-item,
 *   so a modern list contributes nothing while a classic one does).
 * - The text stops at the first <!--more--> (in a feed it runs on).
 * - Block-level tags read as spaces; inline tags (and <br>) read as nothing.
 * - Texturize applies before the tags go (code stays straight-quoted);
 *   whitespace collapses, 55 words, an ellipsis when cut, wrapped in <p>. A hand-written excerpt skips the block filter.
 */
final class Excerpt
{
    private const ALLOWED = [
        'core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/pullquote', 'core/verse',
        'core/preformatted', 'core/table', 'core/group', 'core/columns', 'core/column', 'core/media-text',
        'core/html', 'core/more', 'core/freeform',
    ];

    private const INLINE = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'kbd', 'mark', 'q',
        's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'del', 'ins',
    ];

    /** The more tag ends a listing's excerpt but not a feed's. */
    public static function render(array $post, bool $stopAtMore = true): string
    {
        $source = (string) $post['post_excerpt'];
        if ($source === '') {
            $source = self::allowedMarkup(Parser::parse((string) $post['post_content']));
            $more = strpos($source, '<!--more-->');
            if ($stopAtMore && $more !== false) {
                $source = substr($source, 0, $more);
            }
        }
        // Texturize runs while the tags are still there, so text inside
        // code or pre stays straight-quoted after the tags go.
        $text = preg_replace_callback(
            '/<\/?([a-zA-Z][\w-]*)[^>]*>|<!--.*?-->/s',
            static fn (array $m) => isset($m[1]) && in_array(strtolower($m[1]), self::INLINE, true) ? '' : ' ',
            Texturize::html($source),
        );
        $words = preg_split('/\s+/', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return '';
        }
        $text = count($words) > 55
            ? implode(' ', array_slice($words, 0, 55)) . ' [&hellip;]'
            : implode(' ', $words);
        return '<p>' . $text . "</p>\n";
    }

    /** @param list<Block> $blocks */
    private static function allowedMarkup(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $block) {
            if ($block->name === null) {
                $out .= $block->innerHtml;
                continue;
            }
            if (!in_array($block->name, self::ALLOWED, true)) {
                continue;
            }
            $inner = 0;
            foreach ($block->innerContent as $chunk) {
                $out .= $chunk ?? self::allowedMarkup([$block->innerBlocks[$inner++]]);
            }
        }
        return $out;
    }
}
