<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Runtime\Runtime;

/**
 * Terms a REST read answers with, as plugin code filters them on the
 * reference (probe rest-term-filters): each through get_term and
 * get_{taxonomy}, a list as well through get_terms with its taxonomies and
 * the query's arguments. What a filter changes (a count, a name) is what
 * the answer shows. Without the runtime, or with nothing hooked, the
 * engine's own rows stand.
 */
final class TermFilters
{
    /** A term as get_term hands it back. */
    public static function one(TermRecord $term, string $taxonomy): TermRecord
    {
        if (!Runtime::booted() || (!\has_filter('get_term') && !\has_filter("get_{$taxonomy}"))) {
            return $term;
        }
        $object = \get_term($term->id, $taxonomy);
        return $object instanceof \WP_Term ? self::record($object, $term) : $term;
    }

    /**
     * A page of terms as get_terms hands it back.
     *
     * @param list<TermRecord> $terms
     * @param array<string, mixed> $args the query's arguments, over WP_Term_Query's defaults
     * @return list<TermRecord>
     */
    public static function page(array $terms, string $taxonomy, array $args): array
    {
        if (!Runtime::booted()) {
            return $terms;
        }
        $terms = array_map(static fn (TermRecord $term) => self::one($term, $taxonomy), $terms);
        if (!\has_filter('get_terms')) {
            return $terms;
        }
        $byId = [];
        $objects = [];
        foreach ($terms as $term) {
            $byId[$term->id] = $term;
            $objects[] = new \WP_Term((object) ($term->row() + ['term_id' => $term->id, 'taxonomy' => $taxonomy]));
        }
        $query = new \WP_Term_Query();
        $args = array_replace((array) $query->query_var_defaults, ['taxonomy' => [$taxonomy]], $args);
        $filtered = \apply_filters('get_terms', $objects, [$taxonomy], $args, $query);
        $out = [];
        foreach ((array) $filtered as $object) {
            if ($object instanceof \WP_Term && isset($byId[$object->term_id])) {
                $out[] = self::record($object, $byId[$object->term_id]);
            }
        }
        return $out;
    }

    /** The record with the fields a filter may have changed. */
    private static function record(\WP_Term $object, TermRecord $term): TermRecord
    {
        return TermRecord::fromRow(array_replace($term->row(), [
            'term_id' => $term->id,
            'name' => (string) $object->name,
            'slug' => (string) $object->slug,
            'description' => (string) $object->description,
            'count' => (int) $object->count,
            'parent' => (int) $object->parent,
        ]));
    }
}
