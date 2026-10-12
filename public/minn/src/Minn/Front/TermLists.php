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
     * The tag cloud the reference prints, or null for none: the tags sorted
     * and cut to the number asked for (cloudTags), each one's entry
     * (cloudData), and the links (cloudMarkup). Each row brings its "id"
     * (for the tag-link class; its key when it has none), "scaled" (its count
     * through the scale callback), "text" (the count in words, for the
     * label), and its link already escaped.
     *
     * @param array<array-key, array<string, mixed>> $tags
     * @param array<string, mixed> $args wp_generate_tag_cloud arguments
     */
    public static function tagCloud(array $tags, array $args): ?string
    {
        return $tags === [] ? null : self::cloudMarkup(self::cloudData(self::cloudTags($tags, $args), $args), $args);
    }

    /**
     * The tags in the cloud's order (by name, or by count; random shuffles)
     * and cut to the number asked for. Keys survive the sort; taking the
     * first N renumbers the numeric ones before they name the tags.
     *
     * @param array<array-key, array<string, mixed>> $tags
     * @return array<array-key, array<string, mixed>>
     */
    public static function cloudTags(array $tags, array $args): array
    {
        $tags = self::sorted($tags, (string) ($args['orderby'] ?? 'name'), (string) ($args['order'] ?? 'ASC'));
        return (int) ($args['number'] ?? 0) > 0 ? array_slice($tags, 0, (int) $args['number']) : $tags;
    }

    /**
     * Each tag's entry, as wp_generate_tag_cloud_data is handed them (probe
     * filter-inputs): id, url, role, name, the count in words and as a
     * number, the link class, the font size, the label attribute and the
     * shown count. Sizes step from smallest to largest across the scaled
     * counts; a reversed range steps by one unit; an empty one leaves every
     * tag the same size and drops the label, unless the counts are shown.
     *
     * @param array<array-key, array<string, mixed>> $tags in the cloud's order
     * @return list<array<string, mixed>>
     */
    public static function cloudData(array $tags, array $args): array
    {
        if ($tags === []) {
            return [];
        }
        $scaled = array_map(static fn (array $tag): float => (float) $tag['scaled'], $tags);
        $min = min($scaled);
        $spread = max(1, max($scaled) - $min);
        $smallest = (float) ($args['smallest'] ?? 8);
        $range = (float) ($args['largest'] ?? 22) - $smallest;
        $range = $range < 0 ? 1.0 : $range;
        $labelled = $range != 0 || !empty($args['show_count']);
        $data = [];
        foreach ($tags as $key => $tag) {
            $id = $tag['id'] ?? $key;
            $data[] = [
                'id' => $id,
                'url' => $tag['link'],
                'role' => '',
                'name' => (string) $tag['name'],
                'formatted_count' => (string) $tag['text'],
                'slug' => (string) ($tag['slug'] ?? ''),
                'real_count' => (int) ($tag['count'] ?? 0),
                'class' => 'tag-cloud-link tag-link-' . $id,
                'font_size' => $smallest + (($scaled[$key] - $min) * ($range / $spread)),
                'aria_label' => $labelled ? ' aria-label="' . Html::attr($tag['name'] . ' (' . $tag['text'] . ')') . '"' : '',
                'show_count' => !empty($args['show_count']) ? '<span class="tag-link-count"> (' . (int) ($tag['count'] ?? 0) . ')</span>' : '',
            ];
        }
        return $data;
    }

    /**
     * The cloud from its entries (whatever wp_generate_tag_cloud_data made
     * of them), in the format asked for, or null for none.
     *
     * @param list<array<string, mixed>> $data
     */
    public static function cloudMarkup(array $data, array $args): ?string
    {
        if ($data === []) {
            return null;
        }
        $links = [];
        foreach (array_values($data) as $index => $entry) {
            $size = str_replace(',', '.', (string) ($entry['font_size'] ?? '')) . ($args['unit'] ?? 'pt');
            $links[] = '<a href="' . ($entry['url'] ?? '') . '"' . ($entry['role'] ?? '') . ' class="' . Html::attr((string) ($entry['class'] ?? '')) . ' tag-link-position-' . ($index + 1) . '" style="font-size: ' . $size . ';"'
                . ($entry['aria_label'] ?? '') . '>' . Html::esc((string) ($entry['name'] ?? '')) . ($entry['show_count'] ?? '') . '</a>';
        }
        return match ($args['format'] ?? 'flat') {
            'list' => "<ul class='wp-tag-cloud' role='list'>\n\t<li>" . implode("</li>\n\t<li>", $links) . "</li>\n</ul>\n",
            'array' => implode("\n", $links),
            default => implode((string) ($args['separator'] ?? "\n"), $links),
        };
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
