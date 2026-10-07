<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A term query's ORDER BY as the reference writes it (probe
 * wp-term-query-sql): term and taxonomy columns, the relationship's order,
 * the order include or slug lists give, none, or the name; then
 * get_terms_orderby, and after it a meta key or meta_value[_num] when the
 * query has meta clauses.
 */
final class TermOrder
{
    private const TERM_COLUMNS = ['term_id', 'name', 'slug', 'term_group'];
    private const TAXONOMY_COLUMNS = ['count', 'parent', 'taxonomy', 'term_taxonomy_id', 'description'];

    /** ASC when asked for, DESC for anything else. */
    public static function direction(mixed $order): string
    {
        if (!is_string($order) || $order === '') {
            return 'DESC';
        }
        return strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /** The ORDER BY body for an orderby value, filtered as the reference filters it. */
    public static function clause(\WP_Term_Query $query, string $raw): string
    {
        $orderby = strtolower($raw);
        $vars = $query->query_vars;
        $meta = false;
        $clause = match (true) {
            in_array($orderby, self::TERM_COLUMNS, true) => "t.{$orderby}",
            in_array($orderby, self::TAXONOMY_COLUMNS, true) => "tt.{$orderby}",
            $orderby === 'term_order' => 'tr.term_order',
            $orderby === 'include' && !empty($vars['include']) => 'FIELD( t.term_id, ' . implode(',', \wp_parse_id_list($vars['include'])) . ' )',
            $orderby === 'slug__in' && !empty($vars['slug']) && is_array($vars['slug']) => "FIELD( t.slug, '" . implode("', '", array_map('sanitize_title_for_query', $vars['slug'])) . "')",
            $orderby === 'none' => '',
            $orderby === '' || $orderby === 'id' => 't.term_id',
            default => null,
        };
        if ($clause === null) {
            [$clause, $meta] = ['t.name', true];
        }
        $clause = (string) \apply_filters('get_terms_orderby', $clause, $vars, $vars['taxonomy']);
        // The meta reading comes after the filter, as the reference keeps it for older filters.
        $byMeta = $meta ? self::meta($query, $orderby) : '';
        return $byMeta !== '' ? $byMeta : $clause;
    }

    /** A meta key, meta_value[_num] or a named meta clause as SQL, or '' when the query has no such clause. */
    private static function meta(\WP_Term_Query $query, string $orderby): string
    {
        $query->meta_query->get_sql('term', 't', 'term_id');
        $clauses = (array) $query->meta_query->get_clauses();
        if ($clauses === [] || $orderby === '') {
            return '';
        }
        $primary = (array) reset($clauses);
        $key = !empty($primary['key']) ? (string) $primary['key'] : null;
        $allowed = [...array_filter([$key]), 'meta_value', 'meta_value_num', ...array_map('strval', array_keys($clauses))];
        if (!in_array($orderby, $allowed, true)) {
            return '';
        }
        return match (true) {
            $orderby === $key || $orderby === 'meta_value' => !empty($primary['type']) ? "CAST({$primary['alias']}.meta_value AS {$primary['cast']})" : "{$primary['alias']}.meta_value",
            $orderby === 'meta_value_num' => "{$primary['alias']}.meta_value+0",
            array_key_exists($orderby, $clauses) => "CAST({$clauses[$orderby]['alias']}.meta_value AS {$clauses[$orderby]['cast']})",
            default => '',
        };
    }
}
