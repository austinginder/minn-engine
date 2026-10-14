<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Lists;

/**
 * What is registered, looked up the way WordPress's lookups answer: post
 * types, post statuses and taxonomies by name, or listed by their flags (as
 * names or the objects); whether one exists, is hierarchical or viewable;
 * and the features a post type supports. A name that is not text or a
 * number finds nothing.
 */
final class Registered
{
    /** A post type's object (get_post_type_object), or null. */
    public static function postType(mixed $type): ?object
    {
        return is_scalar($type) ? Runtime::registry()->postTypeObject((string) $type) : null;
    }

    /**
     * The post types whose flags match (get_post_types): all of them (and),
     * any (or) or none (not), as names or as the shared objects, so a flag a
     * plugin changed on one is what the list reads.
     *
     * @return array<string, mixed>
     */
    public static function postTypes(mixed $args = [], mixed $output = 'names', mixed $operator = 'and'): array
    {
        return self::listed(Runtime::registry()->postTypeObjects(), $args, $operator, $output);
    }

    /** Whether a post type is registered (post_type_exists). */
    public static function postTypeExists(mixed $type): bool
    {
        return is_scalar($type) && Runtime::registry()->postType((string) $type) !== null;
    }

    /** Whether a post type supports a feature (post_type_supports). */
    public static function supports(mixed $type, mixed $feature): bool
    {
        return isset(Runtime::registry()->supports((string) $type)[$feature]);
    }

    /** Whether a post type is hierarchical (is_post_type_hierarchical). */
    public static function hierarchicalType(mixed $type): bool
    {
        $row = is_scalar($type) ? Runtime::registry()->postType((string) $type) : null;
        return $row !== null && !empty($row['hierarchical']);
    }

    /** Whether a post type, by name or its object, can be viewed on the site (is_post_type_viewable), as the filter leaves it. */
    public static function viewableType(mixed $type): bool
    {
        if (is_scalar($type)) {
            $type = self::postType($type);
        }
        if (!is_object($type)) {
            return false;
        }
        return (bool) Runtime::hooks()->filter('is_post_type_viewable', [$type->publicly_queryable || ($type->_builtin && $type->public), $type]);
    }

    /** A post status's object (get_post_status_object), or null. */
    public static function status(mixed $status): ?object
    {
        $row = is_scalar($status) ? Runtime::registry()->status((string) $status) : null;
        return $row === null ? null : (object) $row;
    }

    /**
     * The post statuses whose flags match (get_post_stati), as names or objects.
     *
     * @return array<string, mixed>
     */
    public static function statuses(mixed $args = [], mixed $output = 'names', mixed $operator = 'and'): array
    {
        $objects = [];
        foreach (Runtime::registry()->statuses() as $name => $row) {
            $objects[$name] = (object) $row;
        }
        return self::listed($objects, $args, $operator, $output);
    }

    /**
     * Whether posts in a status, by name or its object, can be viewed on the
     * site (is_post_status_viewable): never an internal or protected one;
     * then the filter.
     */
    public static function viewableStatus(mixed $status): bool
    {
        if (is_scalar($status)) {
            $status = self::status($status);
        }
        if (!is_object($status) || $status->internal || $status->protected) {
            return false;
        }
        return (bool) Runtime::hooks()->filter('is_post_status_viewable', [$status->publicly_queryable || ($status->_builtin && $status->public), $status]);
    }

    /** A taxonomy's object (get_taxonomy), or false. */
    public static function taxonomy(mixed $taxonomy): object|false
    {
        return (is_scalar($taxonomy) ? Runtime::registry()->taxonomyObject((string) $taxonomy) : null) ?? false;
    }

    /**
     * The taxonomies whose flags match (get_taxonomies), as names or objects.
     *
     * @return array<string, mixed>
     */
    public static function taxonomies(mixed $args = [], mixed $output = 'names', mixed $operator = 'and'): array
    {
        return self::listed(Runtime::registry()->taxonomyObjects(), $args, $operator, $output);
    }

    /** Whether a taxonomy is registered (taxonomy_exists). */
    public static function taxonomyExists(mixed $taxonomy): bool
    {
        return is_scalar($taxonomy) && Runtime::registry()->taxonomy((string) $taxonomy) !== null;
    }

    /** Whether a taxonomy is hierarchical (is_taxonomy_hierarchical). */
    public static function hierarchicalTaxonomy(mixed $taxonomy): bool
    {
        $row = is_scalar($taxonomy) ? Runtime::registry()->taxonomy((string) $taxonomy) : null;
        return $row !== null && !empty($row['hierarchical']);
    }

    /** Whether a taxonomy, by name or its object, can be queried on the site (is_taxonomy_viewable). */
    public static function viewableTaxonomy(mixed $taxonomy): bool
    {
        if (is_scalar($taxonomy)) {
            $taxonomy = self::taxonomy($taxonomy);
        }
        return is_object($taxonomy) && (bool) $taxonomy->publicly_queryable;
    }

    /** Whether a taxonomy is registered for an object type (is_object_in_taxonomy). */
    public static function objectInTaxonomy(mixed $objectType, mixed $taxonomy): bool
    {
        $row = Runtime::registry()->taxonomy((string) $taxonomy);
        return $row !== null && in_array((string) $objectType, (array) $row['object_type'], true);
    }

    /**
     * The taxonomies registered for any of the object types given
     * (get_object_taxonomies): a list of names, or the objects by name.
     *
     * @return array<array-key, mixed>
     */
    public static function objectTaxonomies(mixed $objectType, mixed $output = 'names'): array
    {
        $types = array_map('strval', (array) $objectType);
        $found = [];
        foreach (Runtime::registry()->taxonomies() as $name => $row) {
            if (array_intersect($types, (array) $row['object_type']) !== []) {
                $found[$name] = $output === 'names' ? $name : Runtime::registry()->taxonomyObject((string) $name);
            }
        }
        return $output === 'names' ? array_values($found) : $found;
    }

    /**
     * The taxonomies registered for attachments, of any file type
     * (get_taxonomies_for_attachments): a list of names, or the objects by name.
     *
     * @return array<array-key, mixed>
     */
    public static function forAttachments(mixed $output = 'names'): array
    {
        $found = [];
        foreach (self::taxonomies([], 'objects') as $taxonomy) {
            $forAttachments = array_filter((array) $taxonomy->object_type, static fn (mixed $type): bool => $type === 'attachment' || str_starts_with((string) $type, 'attachment:'));
            if ($forAttachments === []) {
                continue;
            }
            if ($output === 'names') {
                $found[] = $taxonomy->name;
            } else {
                $found[$taxonomy->name] = $taxonomy;
            }
        }
        return $found;
    }

    /**
     * Objects kept when their fields match (all, any or none of them, as the
     * operator says; every one when nothing is asked), as names when the
     * output asked is "names", else the objects.
     *
     * @param array<string, object> $objects
     * @return array<string, mixed>
     */
    private static function listed(array $objects, mixed $args, mixed $operator, mixed $output): array
    {
        $kept = empty($args) ? $objects : Lists::filter($objects, (array) $args, (string) $operator);
        return $output === 'names' ? Lists::pluck($kept, 'name') : $kept;
    }
}
