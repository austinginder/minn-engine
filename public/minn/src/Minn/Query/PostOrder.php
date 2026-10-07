<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * WP_Query's ORDER BY as the reference writes it (probe wp-query-sql): the
 * keys it takes, what each key becomes (a column, a meta value, a FIELD()
 * list, a seeded RAND()), and how a list or a map of keys joins with its
 * directions. A key it does not know is dropped; none left means post_date.
 */
final class PostOrder
{
    private const KEYS = ['post_name', 'post_author', 'post_date', 'post_title', 'post_modified', 'post_parent', 'post_type', 'name', 'author', 'date', 'title', 'modified', 'parent', 'type', 'ID', 'menu_order', 'comment_count', 'rand', 'post__in', 'post_parent__in', 'post_name__in'];
    private const COLUMNS = ['post_name', 'post_author', 'post_date', 'post_title', 'post_modified', 'post_parent', 'post_type', 'ID', 'menu_order', 'comment_count'];
    private const IN_GIVEN_ORDER = ['post__in', 'post_name__in', 'post_parent__in'];

    /** ASC when asked for, DESC for anything else. */
    public static function direction(mixed $order): string
    {
        if (!is_string($order) || $order === '') {
            return 'DESC';
        }
        return strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * The ORDER BY body for the query's orderby and order, settling both in
     * $q as the reference leaves them (a random or a given-order sort has no
     * direction; a string orderby is url-decoded and slashed).
     *
     * @param array<string, mixed> $q
     * @param array<array-key, array<string, mixed>> $metaClauses the meta query's clauses, by name
     */
    public static function build(array &$q, string $table, array $metaClauses): string
    {
        $rand = isset($q['orderby']) && $q['orderby'] === 'rand';
        $q['order'] = $rand ? '' : (isset($q['order']) ? self::direction($q['order']) : 'DESC');
        if (isset($q['orderby']) && in_array($q['orderby'], self::IN_GIVEN_ORDER, true)) {
            $q['order'] = '';
        }
        if (empty($q['orderby'])) {
            // False or an empty array asks for no order at all; anything else empty, the default.
            return isset($q['orderby']) && (is_array($q['orderby']) || $q['orderby'] === false) ? '' : "{$table}.post_date " . $q['order'];
        }
        if ($q['orderby'] === 'none') {
            return '';
        }
        if (is_array($q['orderby'])) {
            return self::map($q['orderby'], $table, $metaClauses, $q);
        }
        $q['orderby'] = addslashes(urldecode((string) $q['orderby']));
        $parts = array_values(array_filter(array_map(static fn (string $key) => self::clause($key, $table, $metaClauses, $q), explode(' ', $q['orderby']))));
        $orderby = implode(' ' . $q['order'] . ', ', $parts);
        if ($orderby === '') {
            return "{$table}.post_date " . $q['order'];
        }
        return $q['order'] !== '' ? "{$orderby} {$q['order']}" : $orderby;
    }

    /** @param array<array-key, mixed> $orderby key => direction @param array<string, mixed> $q */
    private static function map(array $orderby, string $table, array $metaClauses, array $q): string
    {
        $parts = [];
        foreach ($orderby as $key => $order) {
            $parsed = self::clause(addslashes(urldecode((string) $key)), $table, $metaClauses, $q);
            if ($parsed !== false && $parsed !== '') {
                $parts[] = $parsed . ' ' . self::direction($order);
            }
        }
        return implode(', ', $parts);
    }

    /**
     * One orderby key as SQL, or false when the reference does not take it:
     * a column, RAND() (seeded when asked), the primary meta clause's value
     * (cast when it has a type), a named meta clause's cast value, or the
     * FIELD() list that keeps post__in, post_name__in or post_parent__in in
     * the order given.
     *
     * @param array<array-key, array<string, mixed>> $metaClauses
     * @param array<string, mixed> $q
     */
    public static function clause(string $orderby, string $table, array $metaClauses, array $q): string|false
    {
        $allowed = self::KEYS;
        $primary = [];
        $primaryKey = '';
        if ($metaClauses !== []) {
            $primary = (array) reset($metaClauses);
            $primaryKey = !empty($primary['key']) ? (string) $primary['key'] : '';
            $allowed = [...$allowed, ...array_filter([$primaryKey]), 'meta_value', 'meta_value_num', ...array_map('strval', array_keys($metaClauses))];
        }
        $seeded = false;
        if (preg_match('/RAND\(([0-9]+)\)/i', $orderby, $match) === 1) {
            $orderby = sprintf('RAND(%s)', (int) $match[1]);
            $allowed[] = $orderby;
            $seeded = true;
        }
        if (!in_array($orderby, $allowed, true)) {
            return false;
        }
        return match (true) {
            in_array($orderby, self::COLUMNS, true) => "{$table}.{$orderby}",
            $orderby === 'rand' => 'RAND()',
            $orderby === 'meta_value' || ($primaryKey !== '' && $orderby === $primaryKey) => !empty($primary['type']) ? "CAST({$primary['alias']}.meta_value AS {$primary['cast']})" : "{$primary['alias']}.meta_value",
            $orderby === 'meta_value_num' => "{$primary['alias']}.meta_value+0",
            in_array($orderby, self::IN_GIVEN_ORDER, true) => self::field($orderby, $table, $q),
            array_key_exists($orderby, $metaClauses) => "CAST({$metaClauses[$orderby]['alias']}.meta_value AS {$metaClauses[$orderby]['cast']})",
            $seeded => $orderby,
            default => "{$table}.post_" . $orderby,
        };
    }

    /** A FIELD() list in the order the variable gives, or '' when it gives none. @param array<string, mixed> $q */
    private static function field(string $orderby, string $table, array $q): string
    {
        $given = $q[$orderby] ?? [];
        if (empty($given)) {
            return '';
        }
        $ids = static fn (array $values): array => array_map(static fn ($v) => abs((int) $v), $values);
        return match ($orderby) {
            'post__in' => "FIELD({$table}.ID," . implode(',', $ids((array) $given)) . ')',
            'post_parent__in' => "FIELD( {$table}.post_parent," . implode(', ', $ids((array) $given)) . ' )',
            default => "FIELD( {$table}.post_name,'" . implode("','", array_map('sanitize_title_for_query', (array) $given)) . "' )",
        };
    }
}
