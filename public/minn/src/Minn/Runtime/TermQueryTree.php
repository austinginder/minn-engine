<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What a term query does with its rows, as the reference does it (probe
 * wp-term-query-sql): each row a term through get_term (with the object id
 * a relationship query carries); only child_of's descendants; counts padded
 * with the children's posts; an empty term kept only while one of its
 * children has posts (the rest dropped where they stood, keys and all); a
 * tree's page cut once the tree is walked; then the shape the fields ask
 * for.
 */
final class TermQueryTree
{
    /** @param list<string> $taxonomies */
    public function __construct(private readonly array $taxonomies)
    {
    }

    /**
     * The terms the rows name, keyed as the rows were.
     *
     * @param array<array-key, object> $rows
     * @return array<array-key, \WP_Term>
     */
    public static function populate(array $rows): array
    {
        $terms = [];
        foreach ($rows as $key => $row) {
            $term = \get_term(is_object($row) && property_exists($row, 'term_id') ? $row->term_id : $row);
            if (!$term instanceof \WP_Term) {
                continue;
            }
            if (is_object($row) && property_exists($row, 'object_id')) {
                $term->object_id = (int) $row->object_id;
            }
            if (is_object($row) && property_exists($row, 'count')) {
                $term->count = (int) $row->count;
            }
            $terms[$key] = $term;
        }
        return $terms;
    }

    /**
     * The terms after the tree has had its say: descendants only, padded
     * counts, empty branches dropped, the page cut.
     *
     * @param array<array-key, \WP_Term> $terms
     * @param array<string, mixed> $args
     * @return array<array-key, \WP_Term>
     */
    public function settle(array $terms, array $args): array
    {
        $hierarchical = (bool) $args['hierarchical'];
        if ($args['child_of']) {
            foreach ($this->taxonomies as $taxonomy) {
                if (\_get_term_hierarchy($taxonomy) !== []) {
                    $terms = \_get_term_children((int) $args['child_of'], $terms, $taxonomy);
                }
            }
        }
        if ($args['pad_counts'] && $args['fields'] === 'all') {
            foreach ($this->taxonomies as $taxonomy) {
                \_pad_term_counts($terms, $taxonomy);
            }
        }
        if ($hierarchical && $args['hide_empty']) {
            $terms = array_filter($terms, self::hasPosts(...));
        }
        if ($hierarchical && $args['number']) {
            $terms = $args['offset'] >= count($terms) ? [] : array_slice($terms, (int) $args['offset'], (int) $args['number'], true);
        }
        return $terms;
    }

    /**
     * Each term's count raised to the published posts in it or under it, as
     * _pad_term_counts does for a hierarchical taxonomy.
     *
     * @param array<array-key, \WP_Term> $terms
     */
    public static function pad(array &$terms, string $taxonomy): void
    {
        if (!\is_taxonomy_hierarchical($taxonomy) || \_get_term_hierarchy($taxonomy) === [] || $terms === []) {
            return;
        }
        $byId = [];
        $ids = [];
        foreach ($terms as $key => $term) {
            $byId[$term->term_id] = &$terms[$key];
            $ids[$term->term_taxonomy_id] = $term->term_id;
        }
        $db = Runtime::current()->db;
        $types = (array) (\get_taxonomy($taxonomy)->object_type ?? []);
        $rows = $db->rows("SELECT object_id, term_taxonomy_id FROM {$db->table('term_relationships')} INNER JOIN {$db->table('posts')} ON object_id = ID WHERE term_taxonomy_id IN (?) AND post_type IN (?) AND post_status = 'publish'", [array_keys($ids), $types === [] ? [''] : $types]);
        $items = [];
        foreach ($rows as $row) {
            $items[$ids[(int) $row['term_taxonomy_id']]][(int) $row['object_id']] = true;
        }
        // Every post in a term counts once for each ancestor too.
        foreach ($ids as $termId) {
            [$child, $seen] = [$termId, []];
            while (!empty($byId[$child]) && ($parent = $byId[$child]->parent) && !in_array($parent, $seen, true)) {
                $seen[] = $child;
                foreach (array_keys($items[$termId] ?? []) as $object) {
                    $items[$parent][$object] = true;
                }
                $child = $parent;
            }
        }
        foreach ($items as $id => $objects) {
            if (isset($byId[$id])) {
                $byId[$id]->count = count($objects);
            }
        }
    }

    /** Whether a term, or one of its children, has posts. */
    private static function hasPosts(\WP_Term $term): bool
    {
        if ($term->count) {
            return true;
        }
        foreach ((array) \get_term_children($term->term_id, $term->taxonomy) as $child) {
            $child = \get_term($child, $term->taxonomy);
            if ($child instanceof \WP_Term && $child->count) {
                return true;
            }
        }
        return false;
    }

    /**
     * The terms in the shape the fields ask for.
     *
     * @param array<array-key, \WP_Term> $terms
     * @return array<array-key, mixed>
     */
    public static function format(array $terms, string $fields): array
    {
        $out = [];
        foreach ($terms as $key => $term) {
            match ($fields) {
                'id=>parent' => $out[$term->term_id] = $term->parent,
                'id=>name' => $out[$term->term_id] = $term->name,
                'id=>slug' => $out[$term->term_id] = $term->slug,
                'ids' => $out[] = (int) $term->term_id,
                'tt_ids' => $out[] = (int) $term->term_taxonomy_id,
                'names' => $out[] = $term->name,
                'slugs' => $out[] = $term->slug,
                'all', 'all_with_object_id' => $out[$key] = $term,
                default => null,
            };
        }
        return $out;
    }
}
