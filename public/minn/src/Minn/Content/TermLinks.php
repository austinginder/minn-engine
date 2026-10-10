<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Html;

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
     * a joined run otherwise. Both carry rel="category tag". A row may bring
     * markup to go before its link (its parents) and the space before rel.
     *
     * @param list<array{name: string, url: string, before?: string, space?: string}> $categories
     */
    public static function categories(array $categories, string $separator): string
    {
        $links = array_map(static fn (array $c): string => ($c['before'] ?? '') . self::anchor($c['url'], $c['name'], 'category tag', $c['space'] ?? ' '), $categories);
        if ($separator !== '') {
            return implode($separator, $links);
        }
        return '<ul class="post-categories">' . implode('', array_map(static fn (string $link): string => "\n\t<li>{$link}</li>", $links)) . '</ul>';
    }

    private static function anchor(string $url, string $name, string $rel, string $space = ' '): string
    {
        return '<a href="' . Html::attr($url) . '"' . $space . 'rel="' . $rel . '">' . $name . '</a>';
    }
}
