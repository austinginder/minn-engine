<?php

/**
 * The navigation menu a theme falls back to. Its schema half is real
 * (probe rest-posts-controller): a navigation post's status, raw title and
 * content are readable in the embed context too, so a block can embed the
 * menu it renders.
 */
class WP_Navigation_Fallback
{

    public static function update_wp_navigation_post_schema($schema)
    {
        // Each of these fields also shows when the post is embedded.
        foreach ([['status'], ['title', 'properties', 'raw'], ['content'], ['content', 'properties', 'raw'], ['content', 'properties', 'rendered'], ['content', 'properties', 'block_version']] as $path) {
            $field = &$schema['properties'];
            foreach ($path as $key) {
                $field = &$field[$key];
            }
            if (is_array($field) && isset($field['context']) && !in_array('embed', (array) $field['context'], true)) {
                $field['context'][] = 'embed';
            }
            unset($field);
        }
        return $schema;
    }

    /**
     * The navigation menu to fall back to (probe tail-helpers): the newest
     * published one; else, unless wp_navigation_should_create_fallback says
     * not, one made from a classic menu or, without one, a default page list,
     * then the newest published again.
     */
    public static function get_fallback()
    {
        $create = apply_filters('wp_navigation_should_create_fallback', true);
        $fallback = static::get_most_recently_published_navigation();
        if ($fallback || !$create) {
            return $fallback;
        }
        if (!block_core_navigation_maybe_use_classic_menu_fallback()) {
            static::create_default_fallback();
        }
        return static::get_most_recently_published_navigation();
    }

    private static function get_most_recently_published_navigation()
    {
        return block_core_navigation_get_most_recently_published_navigation();
    }

    /** A published navigation menu listing the site's pages, titled Navigation. */
    private static function create_default_fallback()
    {
        return wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => _x('Navigation', 'Title of a Navigation menu'), 'post_content' => '<!-- wp:page-list /-->', 'post_status' => 'publish'], true);
    }
}
