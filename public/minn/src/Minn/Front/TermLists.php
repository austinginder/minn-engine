<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Support\Html;

/**
 * A term's parent chain and the tag cloud, built from term rows the caller
 * already fetched and links the caller resolves. The category list and
 * select are the walkers' (Walker_Category, Walker_CategoryDropdown).
 */
final class TermLists
{
    /**
     * The chain of names from the outermost ancestor down to the term itself,
     * each one separated and optionally wrapped in its own link. The separator
     * trails the last name too, so a breadcrumb reads "Root/Mid/Leaf/".
     *
     * @param list<array{name: string, slug: string, link: string}> $line outermost first, the term itself last
     */
    public static function parentChain(array $line, bool $link, string $separator, bool $bySlug): string
    {
        $out = '';
        foreach ($line as $term) {
            $name = $bySlug ? $term['slug'] : $term['name'];
            $out .= ($link ? '<a href="' . Html::attr($term['link']) . '">' . $name . '</a>' : $name) . $separator;
        }
        return $out;
    }

    /**
     * The tag cloud the reference prints, or null for none.
     *
     * @param list<array<string, mixed>> $tags rows with term_id, name, count, plus "link"
     * @param array<string, mixed> $args wp_generate_tag_cloud arguments
     */
    public static function tagCloud(array $tags, array $args): ?string
    {
        if ($tags === []) {
            return null;
        }
        $tags = self::sorted($tags, (string) ($args['orderby'] ?? 'name'), (string) ($args['order'] ?? 'ASC'));
        if ((int) ($args['number'] ?? 0) > 0) {
            $tags = array_slice($tags, 0, (int) $args['number']);
        }
        $counts = array_map(static fn (array $t) => (int) $t['count'], $tags);
        $min = min($counts);
        $spread = max(1, max($counts) - $min);
        $smallest = (float) ($args['smallest'] ?? 8);
        $step = max(0.0, (float) ($args['largest'] ?? 22) - $smallest) / $spread;
        $unit = (string) ($args['unit'] ?? 'pt');
        $links = [];
        foreach (array_values($tags) as $i => $tag) {
            $count = (int) $tag['count'];
            $size = str_replace(',', '.', (string) ($smallest + (($count - $min) * $step)));
            $label = Html::attr((string) $tag['name']) . ' (' . $count . ' item' . ($count === 1 ? '' : 's') . ')';
            $links[] = '<a href="' . Html::attr((string) $tag['link']) . '" class="tag-cloud-link tag-link-' . (int) $tag['term_id'] . ' tag-link-position-' . ($i + 1)
                . '" style="font-size: ' . $size . $unit . ';" aria-label="' . $label . '">' . Html::esc((string) $tag['name'])
                . (!empty($args['show_count']) ? '<span class="tag-link-count"> (' . $count . ')</span>' : '') . '</a>';
        }
        if (($args['format'] ?? 'flat') === 'list') {
            return "<ul class='wp-tag-cloud' role='list'>\n\t<li>" . implode("</li>\n\t<li>", $links) . "</li>\n</ul>\n";
        }
        if (($args['format'] ?? 'flat') === 'array') {
            return implode("\n", $links);
        }
        return implode((string) ($args['separator'] ?? "\n"), $links);
    }

    /** @param list<array<string, mixed>> $terms */
    private static function sorted(array $terms, string $orderby, string $order): array
    {
        if ($orderby === 'random') {
            shuffle($terms);
            return $terms;
        }
        usort($terms, $orderby === 'count'
            ? static fn (array $a, array $b) => (int) $a['count'] <=> (int) $b['count']
            : static fn (array $a, array $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));
        return strtoupper($order) === 'DESC' ? array_reverse($terms) : $terms;
    }
}
