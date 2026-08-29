<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Support\Html;

/**
 * The two term listings themes print: the nested category list and the
 * tag cloud, built from term rows the caller already fetched and links the
 * caller resolves.
 */
final class TermLists
{
    /**
     * @param list<array<string, mixed>> $terms rows with term_id, name, slug, count, parent
     * @param array<string, mixed> $args wp_list_categories arguments
     * @param Closure(array): string $link
     */
    public static function categoryList(array $terms, array $args, Closure $link): string
    {
        $flat = ($args['style'] ?? 'list') !== 'list';
        if ($terms === []) {
            $none = (string) ($args['show_option_none'] ?? 'No categories');
            return $flat ? $none : '<li class="cat-item-none">' . $none . '</li>';
        }
        $current = array_map('intval', (array) ($args['current_category'] ?? []));
        $byParent = [];
        foreach ($terms as $term) {
            $byParent[(int) ($term['parent'] ?? 0)][] = $term;
        }
        $hierarchical = !empty($args['hierarchical']) && !$flat;
        $roots = $hierarchical ? ($byParent[0] ?? self::orphans($terms, $byParent)) : $terms;
        return self::items($roots, $byParent, $args, $link, $current, $hierarchical, 0);
    }

    /** @return list<string> the categories block wrapper, before and after the items */
    public static function categoryWrapper(array $args): array
    {
        $title = (string) ($args['title_li'] ?? 'Categories');
        if ($title === '') {
            return ['', ''];
        }
        return ($args['style'] ?? 'list') === 'list' ? ['<li class="categories">' . $title . '<ul>', '</ul></li>'] : [$title, ''];
    }

    /**
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
                . '" style="font-size: ' . $size . $unit . ';" aria-label="' . $label . '">' . Html::esc((string) $tag['name']) . '</a>';
        }
        if (($args['format'] ?? 'flat') === 'list') {
            return "<ul class='wp-tag-cloud' role='list'>\n\t<li>" . implode("</li>\n\t<li>", $links) . "</li>\n</ul>\n";
        }
        if (($args['format'] ?? 'flat') === 'array') {
            return implode("\n", $links);
        }
        return implode((string) ($args['separator'] ?? "\n"), $links);
    }

    /**
     * The option elements of a category dropdown, nested by depth when the
     * caller asked for a hierarchy.
     *
     * @param list<array<string, mixed>> $terms
     */
    public static function dropdownOptions(array $terms, array $args): string
    {
        $byParent = [];
        foreach ($terms as $term) {
            $byParent[!empty($args['hierarchical']) ? (int) ($term['parent'] ?? 0) : 0][] = $term;
        }
        $roots = !empty($args['hierarchical']) ? ($byParent[0] ?? self::orphans($terms, $byParent)) : $terms;
        return self::options($roots, $byParent, $args, 0);
    }

    private static function options(array $terms, array $byParent, array $args, int $depth): string
    {
        $out = '';
        $maxDepth = (int) ($args['depth'] ?? 0);
        $field = (string) ($args['value_field'] ?? 'term_id');
        foreach ($terms as $term) {
            $value = (string) ($term[$field] ?? $term['term_id']);
            $selected = (string) ($args['selected'] ?? '0') === $value ? ' selected="selected"' : '';
            $count = !empty($args['show_count']) ? '&nbsp;&nbsp;(' . (int) $term['count'] . ')' : '';
            $out .= "\t" . '<option class="level-' . $depth . '" value="' . Html::attr($value) . '"' . $selected . '>' . str_repeat('&nbsp;', $depth * 3) . Html::esc((string) $term['name']) . $count . "</option>\n";
            $children = $byParent[(int) $term['term_id']] ?? [];
            if ($children !== [] && ($maxDepth === 0 || $depth + 1 < $maxDepth)) {
                $out .= self::options($children, $byParent, $args, $depth + 1);
            }
        }
        return $out;
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

    /** Terms whose parent is not in the list stand as roots. */
    private static function orphans(array $terms, array $byParent): array
    {
        $ids = array_map(static fn (array $t) => (int) $t['term_id'], $terms);
        return array_values(array_filter($terms, static fn (array $t) => !in_array((int) ($t['parent'] ?? 0), $ids, true)));
    }

    /** @param list<int> $current */
    private static function items(array $terms, array $byParent, array $args, Closure $link, array $current, bool $nested, int $depth): string
    {
        $out = '';
        $maxDepth = (int) ($args['depth'] ?? 0);
        foreach ($terms as $term) {
            $id = (int) $term['term_id'];
            $isCurrent = in_array($id, $current, true);
            $anchor = '<a' . ($isCurrent ? ' aria-current="page"' : '') . ' href="' . Html::attr($link($term)) . '">' . Html::esc((string) $term['name']) . '</a>';
            $count = !empty($args['show_count']) ? ' (' . (int) $term['count'] . ')' : '';
            if (($args['style'] ?? 'list') !== 'list') {
                $out .= "\t" . $anchor . $count . "<br />\n";
                continue;
            }
            $classes = 'cat-item cat-item-' . $id . ($isCurrent ? ' current-cat' : '');
            $children = $nested && ($maxDepth === 0 || $depth + 1 < $maxDepth) ? ($byParent[$id] ?? []) : [];
            if ($children !== [] && $isCurrent === false && array_intersect($current, array_map(static fn (array $c) => (int) $c['term_id'], $children)) !== []) {
                $classes .= ' current-cat-parent';
            }
            $out .= "\t" . '<li class="' . $classes . '">' . $anchor . $count . "\n";
            if ($children !== []) {
                $out .= "<ul class='children'>\n" . self::items($children, $byParent, $args, $link, $current, $nested, $depth + 1) . "</ul>\n";
            }
            $out .= "</li>\n";
        }
        return $out;
    }
}
