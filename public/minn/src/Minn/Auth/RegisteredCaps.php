<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Runtime\Runtime;

/**
 * The capability names a plugin's post type or taxonomy registered, read
 * from the runtime's registry once plugins are loaded (probe
 * rest-plugin-caps): a type's cap object (create_posts, edit_posts,
 * publish_posts...) and whether it maps meta capabilities, a taxonomy's
 * term capabilities. Null for the built-in ones, whose names the engine
 * knows, and when no runtime is up.
 */
final class RegisteredCaps
{
    /** A plugin's post type, or null. */
    public static function ofType(string $type): ?\WP_Post_Type
    {
        if (!Runtime::booted()) {
            return null;
        }
        $object = \get_post_type_object($type);
        return $object instanceof \WP_Post_Type && empty($object->_builtin) ? $object : null;
    }

    /** A plugin's taxonomy, or null. */
    public static function ofTaxonomy(string $taxonomy): ?\WP_Taxonomy
    {
        if (!Runtime::booted()) {
            return null;
        }
        $object = \get_taxonomy($taxonomy);
        return $object instanceof \WP_Taxonomy && empty($object->_builtin) ? $object : null;
    }
}
