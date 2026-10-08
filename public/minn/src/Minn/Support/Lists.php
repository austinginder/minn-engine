<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * List shaping behind the facade's array utilities: arguments over their
 * defaults (wp_parse_args), plucking and filtering (WP_List_Util), the multi-field sort
 * wp_list_sort() promises (loose comparison per field, first difference
 * wins) and the row-shape conversions wpdb hands back for its OBJECT_K /
 * ARRAY_A / ARRAY_N output formats.
 */
final class Lists
{
    /**
     * Arguments as WordPress takes them (wp_parse_args): an object's
     * properties, an array, or a query string, over the defaults.
     *
     * @param array<array-key, mixed> $defaults
     * @return array<array-key, mixed>
     */
    public static function args(mixed $args, mixed $defaults = []): array
    {
        if (is_object($args)) {
            $parsed = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed = $args;
        } else {
            parse_str((string) $args, $parsed);
        }
        return is_array($defaults) && $defaults ? array_merge($defaults, $parsed) : $parsed;
    }

    /**
     * One field of each item (wp_list_pluck), keyed as the list was, or by
     * another field when one is named (items without it are appended);
     * anything not an array or an object is passed over.
     *
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    public static function pluck(array $items, int|string $field, int|string|null $indexKey = null): array
    {
        $out = [];
        foreach ($items as $key => $item) {
            if (!is_object($item) && !is_array($item)) {
                continue;
            }
            $value = is_object($item) ? ($item->{$field} ?? null) : ($item[$field] ?? null);
            $index = $indexKey === null ? $key : (is_object($item) ? ($item->{$indexKey} ?? null) : ($item[$indexKey] ?? null));
            if ($index === null) {
                $out[] = $value;
            } else {
                $out[$index] = $value;
            }
        }
        return $out;
    }

    /**
     * The items matching all the fields given (AND), any of them (OR), or
     * none (NOT), compared loosely, keys kept; nothing for another operator.
     *
     * @param array<array-key, mixed> $items @param array<array-key, mixed> $fields
     * @return array<array-key, mixed>
     */
    public static function filter(array $items, array $fields, string $operator): array
    {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['AND', 'OR', 'NOT'], true)) {
            return [];
        }
        $out = [];
        foreach ($items as $key => $item) {
            $matched = 0;
            foreach ($fields as $name => $wanted) {
                $has = is_object($item) ? isset($item->{$name}) : is_array($item) && array_key_exists($name, $item);
                if ($has && $wanted == (is_object($item) ? $item->{$name} : $item[$name])) {
                    $matched++;
                }
            }
            if (($operator === 'AND' && $matched === count($fields)) || ($operator === 'OR' && $matched > 0) || ($operator === 'NOT' && $matched === 0)) {
                $out[$key] = $item;
            }
        }
        return $out;
    }

    /**
     * A list as arguments give one (wp_parse_list): an array's entries
     * trimmed, the empty ones dropped (keys kept); a string split on commas
     * and whitespace.
     *
     * @return array<int|string, string>
     */
    public static function items(mixed $input): array
    {
        if (!is_array($input)) {
            return preg_split('/[\s,]+/', (string) $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        return array_filter(array_map(static fn ($item): string => trim((string) $item), $input), static fn (string $item): bool => $item !== '');
    }

    /**
     * Ids as arguments give them (wp_parse_id_list): each a whole number made
     * positive, each once, in first-seen order.
     *
     * @return list<int>
     */
    public static function ids(mixed $input): array
    {
        return array_values(array_unique(array_map(static fn (string $id): int => abs((int) $id), self::items($input))));
    }

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
