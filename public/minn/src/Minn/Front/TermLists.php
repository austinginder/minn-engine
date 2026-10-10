<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Html;

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
     * The tag cloud the reference prints, or null for none. Each row brings
     * its "id" (for the tag-link class; its key when it has none), "scaled"
     * (its count through the scale callback), "text" (the count in words, for
     * the label), and its link already escaped. Keys survive the sort; taking
     * the first N renumbers the numeric ones before they name the tags.
     *
     * @param array<array-key, array<string, mixed>> $tags
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
        $links = self::cloudLinks($tags, $args);
        return match ($args['format'] ?? 'flat') {
            'list' => "<ul class='wp-tag-cloud' role='list'>\n\t<li>" . implode("</li>\n\t<li>", $links) . "</li>\n</ul>\n",
            'array' => implode("\n", $links),
            default => implode((string) ($args['separator'] ?? "\n"), $links),
        };
    }

    /**
     * Sizes step from smallest to largest across the scaled counts. A
     * reversed range steps by one unit; an empty one leaves every tag the
     * same size and drops the label, unless the counts are shown.
     *
     * @param array<array-key, array<string, mixed>> $tags
     * @return list<string>
     */
    private static function cloudLinks(array $tags, array $args): array
    {
        $scaled = array_map(static fn (array $tag): float => (float) $tag['scaled'], $tags);
        $min = min($scaled);
        $spread = max(1, max($scaled) - $min);
        $smallest = (float) ($args['smallest'] ?? 8);
        $range = (float) ($args['largest'] ?? 22) - $smallest;
        $range = $range < 0 ? 1.0 : $range;
        $labelled = $range != 0 || !empty($args['show_count']);
        $links = [];
        foreach ($tags as $key => $tag) {
            $size = str_replace(',', '.', (string) ($smallest + (($scaled[$key] - $min) * ($range / $spread)))) . ($args['unit'] ?? 'pt');
            $links[] = self::cloudLink(['id' => $tag['id'] ?? $key] + $tag, $size, $labelled ? $tag['name'] . ' (' . $tag['text'] . ')' : null, !empty($args['show_count']) ? (int) $tag['count'] : null, count($links) + 1);
        }
        return $links;
    }

    /** @param array<string, mixed> $tag */
    private static function cloudLink(array $tag, string $size, ?string $label, ?int $count, int $position): string
    {
        return '<a href="' . $tag['link'] . '" class="tag-cloud-link tag-link-' . Html::attr((string) $tag['id']) . ' tag-link-position-' . $position . '" style="font-size: ' . $size . ';"'
            . ($label === null ? '' : ' aria-label="' . Html::attr($label) . '"') . '>' . Html::esc((string) $tag['name'])
            . ($count === null ? '' : '<span class="tag-link-count"> (' . $count . ')</span>') . '</a>';
    }

    /**
     * By name (natural, case-insensitive) only for "name"; every other value
     * sorts by count. Only "DESC", in capitals, reverses.
     *
     * @param array<array-key, array<string, mixed>> $terms
     */
    private static function sorted(array $terms, string $orderby, string $order): array
    {
        if ($orderby === 'random') {
            shuffle($terms);
            return $terms;
        }
        uasort($terms, $orderby === 'name'
            ? static fn (array $a, array $b) => strnatcasecmp((string) $a['name'], (string) $b['name'])
            : static fn (array $a, array $b) => (int) $a['count'] <=> (int) $b['count']);
        return $order === 'DESC' ? array_reverse($terms, true) : $terms;
    }
}
