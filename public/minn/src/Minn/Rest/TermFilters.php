<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Runtime\Runtime;

/**
 * A term a REST read answers with, as plugin code filters it on the
 * reference (probe rest-term-filters): through get_term and
 * get_{taxonomy}. What a filter changes (a count, a name) is what the
 * answer shows. Without the runtime, or with nothing hooked, the engine's
 * own row stands. (Lists run get_terms itself.)
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
