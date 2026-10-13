<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Runtime\Registered;
use Minn\Runtime\Runtime;

/**
 * The capability names a plugin's post type or taxonomy registered, read
 * from the runtime's registry once plugins are loaded (probe
 * rest-plugin-caps): a type's cap object (create_posts, edit_posts,
 * publish_posts...) and whether it maps meta capabilities, a taxonomy's
 * term capabilities. A built-in type's too (patterns, menu items,
 * templates), all but posts and pages, whose families the engine knows;
 * null for those and when no runtime is up.
 */
final class RegisteredCaps
{
    /** A post type's registered object (any but post and page), or null. */
    public static function ofType(string $type): ?\WP_Post_Type
    {
        if (!Runtime::booted()) {
            return null;
        }
        // Posts and pages own the families everything else is named after; every other type, a built-in one (patterns,
        // menu items, templates) as much as a plugin's, answers with the names it registered, as on the reference.
        $object = Registered::postType($type);
        return $object instanceof \WP_Post_Type && !in_array($type, ['post', 'page'], true) ? $object : null;
    }

    /** A plugin's taxonomy, or null. */
    public static function ofTaxonomy(string $taxonomy): ?\WP_Taxonomy
    {
        if (!Runtime::booted()) {
            return null;
        }
        $object = Registered::taxonomy($taxonomy);
        return $object instanceof \WP_Taxonomy && empty($object->_builtin) ? $object : null;
    }
}
