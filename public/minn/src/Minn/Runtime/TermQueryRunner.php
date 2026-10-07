<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\Sql;
use Minn\Support\Lists;

/**
 * WP_Term_Query::get_terms as the reference runs it (probe
 * wp-term-query-sql): the variables parsed and handed to pre_get_terms,
 * get_terms_args, the WHERE pieces (taxonomies, inclusions, exclusions with
 * excluded trees and childless terms through list_terms_exclusions, names,
 * slugs, term taxonomy ids, likes, objects, parent, counts, search, meta),
 * the order through get_terms_orderby, the fields through get_terms_fields,
 * all of it through terms_clauses, terms_pre_query, and the results shaped
 * by TermQueryTree (children, padded counts, the empty hidden, the page).
 */
final class TermQueryRunner
{
    private \WP_Term_Query $query;
    private object $wpdb;
    /** @var array<string, string> */
    private array $where = [];

    /**
     * Runs a term query object's variables: its terms in the shape the
     * fields ask for, a count, or what a plugin answered first.
     *
     * @param object $wpdb the database object plugins see
     */
    public function run(\WP_Term_Query $query, object $wpdb): mixed
    {
        $this->query = $query;
        $this->wpdb = $wpdb;
        $query->parse_query($query->query_vars);
        $args = &$query->query_vars;
        $query->meta_query = new \WP_Meta_Query();
        $query->meta_query->parse_query_vars($args);
        \do_action_ref_array('pre_get_terms', [&$query]);
        $taxonomies = (array) $args['taxonomy'];
        $args = $this->settle($args, $taxonomies);
        $parent = $args['child_of'] ?: $args['parent'];
        if ($parent && !$this->inHierarchy((int) $parent, $taxonomies)) {
            return $args['fields'] === 'count' ? 0 : ($query->terms = []);
        }
        $args = self::lists($args);
        [$orderby, $order] = $this->order($args);
        $this->clauses($args, $taxonomies);
        $this->select($args, $taxonomies, $orderby, $order);
        $query->terms = \apply_filters_ref_array('terms_pre_query', [null, &$query]);
        if ($query->terms !== null) {
            return $query->terms;
        }
        if ($args['fields'] === 'count') {
            return $this->wpdb->get_var($query->request);
        }
        $rows = $this->wpdb->get_results($query->request);
        if (empty($rows)) {
            return $query->terms = [];
        }
        \_prime_term_caches(array_map(static fn ($row) => (int) $row->term_id, $rows), false);
        $tree = new TermQueryTree($taxonomies);
        $terms = $tree->settle(TermQueryTree::populate($rows), $args);
        return $query->terms = TermQueryTree::format($terms, (string) $args['fields']);
    }

    /**
     * Names, slugs, term taxonomy ids and object ids as lists, as the
     * reference holds them once plugins have had the arguments.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function lists(array $args): array
    {
        foreach (['name', 'slug'] as $key) {
            $args[$key] = $args[$key] === '' || $args[$key] === null ? [] : (array) $args[$key];
        }
        foreach (['term_taxonomy_id', 'object_ids'] as $key) {
            $args[$key] = is_array($args[$key]) ? $args[$key] : Lists::ids((string) $args[$key]);
        }
        return $args;
    }

    /** Whether the results have a tree to walk (a count never does). @param array<string, mixed> $args */
    private static function hierarchical(array $args): bool
    {
        return $args['fields'] !== 'count' && (bool) $args['hierarchical'];
    }

    /**
     * The variables pre_get_terms left, settled: a flat taxonomy needs no
     * tree, a parent overrides child_of, `get=all` asks for every term;
     * then get_terms_args.
     *
     * @param array<string, mixed> $args
     * @param list<string> $taxonomies
     * @return array<string, mixed>
     */
    private function settle(array $args, array $taxonomies): array
    {
        $hierarchical = $taxonomies === [] || array_filter($taxonomies, 'is_taxonomy_hierarchical') !== [];
        if (!$hierarchical) {
            $args['hierarchical'] = false;
            $args['pad_counts'] = false;
        }
        if ((int) $args['parent'] > 0) {
            $args['child_of'] = false;
        }
        if ($args['get'] === 'all') {
            $args = array_replace($args, ['childless' => false, 'child_of' => 0, 'hide_empty' => 0, 'hierarchical' => false, 'pad_counts' => false]);
        }
        return (array) \apply_filters('get_terms_args', $args, $taxonomies);
    }

    /** Whether a parent or child_of term has children in any of the taxonomies (when none, the query is not run). @param list<string> $taxonomies */
    private function inHierarchy(int $parent, array $taxonomies): bool
    {
        foreach ($taxonomies as $taxonomy) {
            if (isset(\_get_term_hierarchy($taxonomy)[$parent])) {
                return true;
            }
        }
        return false;
    }

    /** The ORDER BY clause and direction, term_order needing the relationships. @param array<string, mixed> $args @return array{0: string, 1: string} */
    private function order(array $args): array
    {
        $orderby = $this->query->query_vars['orderby'];
        if ($orderby === 'term_order' && empty($this->query->query_vars['object_ids'])) {
            $orderby = 'term_id';
        }
        $orderby = TermOrder::clause($this->query, (string) $orderby);
        return [$orderby ? "ORDER BY {$orderby}" : '', TermOrder::direction($this->query->query_vars['order'])];
    }

    /** The WHERE pieces, by name, in the reference's order. @param array<string, mixed> $args @param list<string> $taxonomies */
    private function clauses(array &$args, array $taxonomies): void
    {
        $this->where = [];
        if ($taxonomies !== []) {
            $this->where['taxonomy'] = "tt.taxonomy IN ('" . implode("', '", array_map('esc_sql', $taxonomies)) . "')";
        }
        $include = empty($args['include']) ? [] : $args['include'];
        if (!empty($include)) {
            $this->where['inclusions'] = 't.term_id IN ( ' . implode(',', Lists::ids($include)) . ' )';
        }
        $exclusions = $this->exclusions($args, $taxonomies);
        $exclusions = \apply_filters('list_terms_exclusions', $exclusions, $args, $taxonomies);
        if (!empty($exclusions)) {
            $this->where['exclusions'] = (string) preg_replace('/^\s*AND\s*/', '', (string) $exclusions);
        }
        $this->names($args, $taxonomies);
        $this->likes($args);
        if (!empty($args['object_ids'])) {
            $this->where['object_ids'] = 'tr.object_id IN (' . implode(', ', array_map('intval', (array) $args['object_ids'])) . ')';
            $args['hide_empty'] = false;
        }
        if ($args['parent'] !== '') {
            $this->where['parent'] = "tt.parent = '" . (int) $args['parent'] . "'";
        }
    }

    /** The excluded ids (an excluded term's whole tree, childless terms' parents), unless ids are included. @param array<string, mixed> $args @param list<string> $taxonomies */
    private function exclusions(array $args, array $taxonomies): string
    {
        $included = !empty($args['include']);
        $exclusions = [];
        $tree = $included || empty($args['exclude_tree']) ? [] : Lists::ids($args['exclude_tree']);
        foreach ($tree as $trunk) {
            $exclusions = [...$exclusions, ...(array) \get_terms(['taxonomy' => reset($taxonomies), 'child_of' => (int) $trunk, 'fields' => 'ids', 'hide_empty' => 0])];
        }
        $exclusions = [...$tree, ...$exclusions];
        if (!$included && !empty($args['exclude'])) {
            $exclusions = [...Lists::ids($args['exclude']), ...$exclusions];
        }
        if ($args['childless']) {
            foreach ($taxonomies as $taxonomy) {
                $exclusions = [...array_keys(\_get_term_hierarchy($taxonomy)), ...$exclusions];
            }
        }
        return $exclusions === [] ? '' : 't.term_id NOT IN (' . implode(',', array_map('intval', $exclusions)) . ')';
    }

    /** Names (as saving stores them), slugs and term taxonomy ids. @param array<string, mixed> $args @param list<string> $taxonomies */
    private function names(array $args, array $taxonomies): void
    {
        if (!empty($args['name']) || (is_string($args['name']) && $args['name'] !== '')) {
            $names = array_map(static fn ($name) => stripslashes((string) \sanitize_term_field('name', $name, 0, reset($taxonomies), 'db')), (array) $args['name']);
            $this->where['name'] = "t.name IN ('" . implode("', '", array_map('esc_sql', $names)) . "')";
        }
        if (!empty($args['slug']) || (is_string($args['slug']) && $args['slug'] !== '')) {
            $this->where['slug'] = is_array($args['slug'])
                ? "t.slug IN ('" . implode("', '", array_map('sanitize_title', $args['slug'])) . "')"
                : "t.slug = '" . \sanitize_title($args['slug']) . "'";
        }
        if (!empty($args['term_taxonomy_id'])) {
            $this->where['term_taxonomy_id'] = is_array($args['term_taxonomy_id'])
                ? 'tt.term_taxonomy_id IN (' . implode(',', array_map('intval', $args['term_taxonomy_id'])) . ')'
                : 'tt.term_taxonomy_id = ' . (int) $args['term_taxonomy_id'];
        }
    }

    /** @param array<string, mixed> $args */
    private function likes(array $args): void
    {
        if (!empty($args['name__like'])) {
            $this->where['name__like'] = 't.name LIKE ' . Sql::quote('%' . Sql::like((string) $args['name__like']) . '%');
        }
        if (!empty($args['description__like'])) {
            $this->where['description__like'] = 'tt.description LIKE ' . Sql::quote('%' . Sql::like((string) $args['description__like']) . '%');
        }
    }

    /**
     * The rest of the WHERE (counts, search, meta), the fields, the joins,
     * terms_clauses, and the request written from what it returned.
     *
     * @param array<string, mixed> $args
     * @param list<string> $taxonomies
     */
    private function select(array $args, array $taxonomies, string $orderby, string $order): void
    {
        if ($args['hide_empty'] && !self::hierarchical($args)) {
            $this->where['count'] = 'tt.count > 0';
        }
        $limits = self::limits($args);
        if (!empty($args['search'])) {
            $like = Sql::quote('%' . Sql::like((string) $args['search']) . '%');
            $this->where['search'] = "((t.name LIKE {$like}) OR (t.slug LIKE {$like}))";
        }
        [$join, $distinct] = $this->meta();
        $selects = $args['fields'] === 'count' ? ['COUNT(*)'] : ['t.term_id', ...($args['fields'] === 'all_with_object_id' && !empty($args['object_ids']) ? ['tr.object_id'] : [])];
        [$orderby, $order] = $args['fields'] === 'count' ? ['', ''] : [$orderby, $order];
        $fields = implode(', ', (array) \apply_filters('get_terms_fields', $selects, $args, $taxonomies));
        $join .= " INNER JOIN {$this->wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id";
        if (!empty($this->query->query_vars['object_ids'])) {
            $join .= " INNER JOIN {$this->wpdb->term_relationships} AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $distinct = 'DISTINCT';
        }
        $where = implode(' AND ', $this->where);
        $clauses = (array) \apply_filters('terms_clauses', compact('fields', 'join', 'where', 'distinct', 'orderby', 'order', 'limits'), $taxonomies, $args);
        $c = array_map(static fn ($piece) => $piece ?? '', $clauses + array_fill_keys(['fields', 'join', 'where', 'distinct', 'orderby', 'order', 'limits'], ''));
        $where = $c['where'] ? "WHERE {$c['where']}" : '';
        $sortby = $c['orderby'] ? "{$c['orderby']} {$c['order']}" : '';
        $break = "\n\t\t\t ";
        $this->query->request = "SELECT {$c['distinct']} {$c['fields']}{$break}FROM {$this->wpdb->terms} AS t {$c['join']}{$break}{$where}{$break}{$sortby}{$break}{$c['limits']}";
    }

    /** A LIMIT only when no tree has to be walked first (the tree's page is cut afterwards). @param array<string, mixed> $args */
    private static function limits(array $args): string
    {
        $number = $args['number'];
        if (!$number || self::hierarchical($args) || $args['child_of'] || $args['parent'] !== '') {
            return '';
        }
        return $args['offset'] ? "LIMIT {$args['offset']},{$number}" : "LIMIT {$number}";
    }

    /** The meta query's join and WHERE piece, read again in case pre_get_terms changed its variables. @return array{0: string, 1: string} */
    private function meta(): array
    {
        $this->query->meta_query->parse_query_vars($this->query->query_vars);
        $sql = $this->query->meta_query->get_sql('term', 't', 'term_id');
        if (empty($this->query->meta_query->get_clauses())) {
            return ['', ''];
        }
        $this->where['meta_query'] = (string) preg_replace('/^\s*AND\s*/', '', (string) ($sql['where'] ?? ''));
        return [(string) ($sql['join'] ?? ''), 'DISTINCT'];
    }
}
