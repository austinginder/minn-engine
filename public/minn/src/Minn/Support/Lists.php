<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * List shaping behind the facade's array utilities: the multi-field sort
 * wp_list_sort() promises (loose comparison per field, first difference
 * wins) and the row-shape conversions wpdb hands back for its OBJECT_K /
 * ARRAY_A / ARRAY_N output formats.
 */
final class Lists
{
    /**
     * Items sorted by several fields, each ascending or descending.
     *
     * @param array<int|string, mixed> $items
     * @param array<string, string> $orderby field => ASC|DESC
     * @return array<int|string, mixed>
     */
    public static function sort(array $items, array $orderby, bool $preserveKeys): array
    {
        $compare = static function ($a, $b) use ($orderby): int {
            foreach ($orderby as $field => $direction) {
                $av = is_object($a) ? ($a->{$field} ?? null) : ($a[$field] ?? null);
                $bv = is_object($b) ? ($b->{$field} ?? null) : ($b[$field] ?? null);
                if ($av == $bv) {
                    continue;
                }
                $result = $av < $bv ? -1 : 1;
                return strtoupper((string) $direction) === 'DESC' ? -$result : $result;
            }
            return 0;
        };
        if ($preserveKeys) {
            uasort($items, $compare);
        } else {
            usort($items, $compare);
        }
        return $items;
    }

    /**
     * Rows of stdClass shaped for a wpdb output format: keyed by their first
     * column (first row wins a duplicate key), associative, or numeric.
     *
     * @param list<object> $rows
     * @return array<int|string, mixed>
     */
    public static function shapeRows(array $rows, string $output): array
    {
        return match ($output) {
            'OBJECT_K' => self::keyedByFirstColumn($rows),
            'ARRAY_A' => array_map(static fn ($r) => get_object_vars($r), $rows),
            'ARRAY_N' => array_map(static fn ($r) => array_values(get_object_vars($r)), $rows),
            default => $rows,
        };
    }

    /**
     * Every descendant of one root in a flat parent-linked list, preorder
     * (a child ahead of its own children), the shape _get_term_children and
     * get_page_children walk. Visited ids guard against a parent cycle.
     *
     * @param list<mixed> $items
     * @param callable(mixed): int $id
     * @param callable(mixed): int $parent
     * @param list<int> $visited
     * @return list<mixed>
     */
    public static function descendants(array $items, int $rootId, callable $id, callable $parent, array $visited = []): array
    {
        $out = [];
        foreach ($items as $item) {
            $itemId = $id($item);
            if ($parent($item) !== $rootId || in_array($itemId, $visited, true)) {
                continue;
            }
            $out[] = $item;
            $out = [...$out, ...self::descendants($items, $itemId, $id, $parent, [...$visited, $rootId, $itemId])];
        }
        return $out;
    }

    /**
     * @param list<object> $rows
     * @return array<string, object>
     */
    private static function keyedByFirstColumn(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $values = array_values(get_object_vars($row));
            $key = (string) ($values[0] ?? '');
            if (!isset($out[$key])) {
                $out[$key] = $row;
            }
        }
        return $out;
    }
}
