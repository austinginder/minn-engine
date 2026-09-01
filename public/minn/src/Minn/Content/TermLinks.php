<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Support\Html;

/**
 * A post's terms rendered as links, in the two shapes the reference
 * emits: a plain joined run for a taxonomy, and the category list, which
 * becomes a `post-categories` unordered list when the caller names no
 * separator and a joined run when it does.
 *
 * Names arrive already filtered; the caller applies the reference's own
 * filters around the result.
 */
final class TermLinks
{
    /**
     * Anchor tags for these terms joined by a separator, the way the reference prints a term list.
     *
     * @param list<array{name: string, url: string}> $terms
     */
    public static function joined(array $terms, string $rel, string $sep): string
    {
        $links = [];
        foreach ($terms as $term) {
            $links[] = self::anchor($term['url'], $term['name'], $rel);
        }
        return implode($sep, $links);
    }

    /**
     * The category list: an unordered list when the separator is empty,
     * a joined run otherwise. Both carry rel="category tag".
     *
     * @param list<array{name: string, url: string}> $categories
     */
    public static function categories(array $categories, string $separator): string
    {
        if ($separator !== '') {
            return self::joined($categories, 'category tag', $separator);
        }
        $out = '<ul class="post-categories">';
        foreach ($categories as $category) {
            $out .= "\n\t<li>" . self::anchor($category['url'], $category['name'], 'category tag') . '</li>';
        }
        return $out . '</ul>';
    }

    private static function anchor(string $url, string $name, string $rel): string
    {
        return '<a href="' . Html::attr($url) . '" rel="' . $rel . '">' . $name . '</a>';
    }
}
