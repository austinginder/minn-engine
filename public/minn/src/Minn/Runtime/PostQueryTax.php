<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The taxonomy side of WP_Query as the reference runs it (probe
 * wp-query-sql): the clauses its variables make (tax_query, taxonomy and
 * term, each taxonomy's query var, cat and the category__ lists, tag and the
 * tag__ lists), settled back into the variables; the post types a taxonomy
 * archive with no type searches; and the cat, category_name, tag_id,
 * taxonomy and term variables set from the terms queried.
 */
final class PostQueryTax
{
    /**
     * The tax query a set of variables makes, settling the variables it reads.
     *
     * @param array<string, mixed> $q
     * @return list<array<string, mixed>>|array<string, mixed>
     */
    public static function clauses(\WP_Query $query, array &$q): array
    {
        $singular = (bool) $query->is_singular;
        $clauses = !empty($q['tax_query']) && is_array($q['tax_query']) ? $q['tax_query'] : [];
        if (!empty($q['taxonomy']) && !empty($q['term'])) {
            $clauses[] = ['taxonomy' => $q['taxonomy'], 'terms' => [$q['term']], 'field' => 'slug'];
        }
        foreach (\get_taxonomies([], 'objects') as $name => $taxonomy) {
            if ($name !== 'post_tag' && $taxonomy->query_var && !empty($q[$taxonomy->query_var])) {
                array_push($clauses, ...self::queryVar($q, (string) $name, $taxonomy));
            }
        }
        if (is_array($q['cat'] ?? null)) {
            $q['cat'] = implode(',', $q['cat']);
        }
        if (!empty($q['cat']) && !$singular) {
            array_push($clauses, ...self::cat($q));
        }
        array_push($clauses, ...self::categoryLists($q));
        if (is_array($q['tag'] ?? null)) {
            $q['tag'] = implode(',', $q['tag']);
        }
        if (($q['tag'] ?? '') !== '' && !$singular && $query->query_vars_changed) {
            self::tag($q);
        }
        array_push($clauses, ...self::tagLists($q));
        return $clauses;
    }

    /** @return list<array<string, mixed>> */
    private static function queryVar(array &$q, string $name, object $taxonomy): array
    {
        $defaults = ['taxonomy' => $name, 'field' => 'slug'];
        if (!empty($taxonomy->rewrite['hierarchical'])) {
            $q[$taxonomy->query_var] = \wp_basename($q[$taxonomy->query_var]);
        }
        $term = $q[$taxonomy->query_var];
        $term = is_array($term) ? implode(',', $term) : (string) $term;
        if (str_contains($term, '+')) {
            return array_map(static fn (string $one) => array_merge($defaults, ['terms' => [$one]]), preg_split('/[+]+/', $term) ?: []);
        }
        return [array_merge($defaults, ['terms' => preg_split('/[,]+/', $term) ?: []])];
    }

    /** @return list<array<string, mixed>> */
    private static function cat(array &$q): array
    {
        $ids = array_map('intval', preg_split('/[,\s]+/', urldecode((string) $q['cat'])) ?: []);
        $q['cat'] = implode(',', $ids);
        $in = array_values(array_filter($ids, static fn (int $id) => $id > 0));
        $out = array_values(array_map('abs', array_filter($ids, static fn (int $id) => $id < 0)));
        $clauses = [];
        if ($in !== []) {
            $clauses[] = ['taxonomy' => 'category', 'terms' => $in, 'field' => 'term_id', 'include_children' => true];
        }
        if ($out !== []) {
            $clauses[] = ['taxonomy' => 'category', 'terms' => $out, 'field' => 'term_id', 'operator' => 'NOT IN', 'include_children' => true];
        }
        return $clauses;
    }

    /** @return list<array<string, mixed>> */
    private static function categoryLists(array &$q): array
    {
        if (!empty($q['category__and']) && count((array) $q['category__and']) === 1) {
            $q['category__and'] = (array) $q['category__and'];
            $q['category__in'] ??= [];
            $q['category__in'][] = \absint(reset($q['category__and']));
            unset($q['category__and']);
        }
        $clauses = [];
        foreach (['category__in' => null, 'category__not_in' => 'NOT IN', 'category__and' => 'AND'] as $var => $operator) {
            if (empty($q[$var])) {
                continue;
            }
            $q[$var] = array_map('absint', array_unique((array) $q[$var]));
            sort($q[$var]);
            $clause = ['taxonomy' => 'category', 'terms' => $q[$var]];
            $clause += $var === 'category__not_in' ? [] : ['field' => 'term_id'];
            $clauses[] = $clause + ($operator === null ? [] : ['operator' => $operator]) + ['include_children' => false];
        }
        return $clauses;
    }

    /** The tag variable as tag_slug__in or tag_slug__and. */
    private static function tag(array &$q): void
    {
        $tag = (string) $q['tag'];
        if (str_contains($tag, ',')) {
            foreach (preg_split('/[,\r\n\t ]+/', $tag) ?: [] as $one) {
                $q['tag_slug__in'][] = \sanitize_term_field('slug', $one, 0, 'post_tag', 'db');
                sort($q['tag_slug__in']);
            }
        } elseif (preg_match('/[+\r\n\t ]+/', $tag) === 1 || !empty($q['cat'])) {
            foreach (preg_split('/[+\r\n\t ]+/', $tag) ?: [] as $one) {
                $q['tag_slug__and'][] = \sanitize_term_field('slug', $one, 0, 'post_tag', 'db');
            }
        } else {
            $q['tag'] = \sanitize_term_field('slug', $tag, 0, 'post_tag', 'db');
            $q['tag_slug__in'][] = $q['tag'];
            sort($q['tag_slug__in']);
        }
    }

    /** @return list<array<string, mixed>> */
    private static function tagLists(array &$q): array
    {
        $clauses = [];
        if (!empty($q['tag_id'])) {
            $q['tag_id'] = \absint($q['tag_id']);
            $clauses[] = ['taxonomy' => 'post_tag', 'terms' => $q['tag_id']];
        }
        foreach (['tag__in' => null, 'tag__not_in' => 'NOT IN', 'tag__and' => 'AND', 'tag_slug__in' => null, 'tag_slug__and' => 'AND'] as $var => $operator) {
            if (empty($q[$var])) {
                continue;
            }
            $q[$var] = array_map(str_starts_with($var, 'tag_slug') ? 'sanitize_title_for_query' : 'absint', array_unique((array) $q[$var]));
            sort($q[$var]);
            $clause = ['taxonomy' => 'post_tag', 'terms' => $q[$var]];
            $clause += str_starts_with($var, 'tag_slug') ? ['field' => 'slug'] : [];
            $clauses[] = $clause + ($operator === null ? [] : ['operator' => $operator]);
        }
        return $clauses;
    }

    /**
     * The post types a taxonomy archive with no type searches: every
     * searchable type the queried taxonomies are registered for, one as a
     * string, several sorted, none as `any`.
     *
     * @param list<string> $taxonomies
     * @return string|list<string>
     */
    public static function postTypes(array $taxonomies): string|array
    {
        $types = [];
        foreach (\get_post_types(['exclude_from_search' => false]) as $type) {
            $objectTaxonomies = $type === 'attachment' ? \get_taxonomies_for_attachments() : \get_object_taxonomies($type);
            if (array_intersect($taxonomies, $objectTaxonomies) !== []) {
                $types[] = $type;
            }
        }
        if ($types === []) {
            return 'any';
        }
        if (count($types) === 1) {
            return $types[0];
        }
        sort($types);
        return $types;
    }

    /**
     * The compatibility variables the queried terms set: taxonomy and term
     * (or term_id) from the first taxonomy other than category and post_tag
     * when none is set, and cat, category_name and tag_id from those two.
     *
     * @param array<string, array{terms?: list<mixed>, field?: string}> $queried
     */
    public static function compat(\WP_Query $query, array &$q, array $queried): void
    {
        if (!isset($q['taxonomy'])) {
            foreach ($queried as $taxonomy => $items) {
                if (empty($items['terms'][0]) || in_array($taxonomy, ['category', 'post_tag'], true)) {
                    continue;
                }
                $q['taxonomy'] = $taxonomy;
                $q[$items['field'] === 'slug' ? 'term' : 'term_id'] = $items['terms'][0];
                break;
            }
        }
        foreach ($queried as $taxonomy => $items) {
            if (empty($items['terms'][0]) || !in_array($taxonomy, ['category', 'post_tag'], true)) {
                continue;
            }
            $term = \get_term_by($items['field'], $items['terms'][0], $taxonomy);
            if ($term && $taxonomy === 'category') {
                $query->set('cat', $term->term_id);
                $query->set('category_name', $term->slug);
            } elseif ($term) {
                $query->set('tag_id', $term->term_id);
            }
        }
    }
}
