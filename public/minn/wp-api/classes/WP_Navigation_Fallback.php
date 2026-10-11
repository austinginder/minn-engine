<?php

/**
 * The navigation menu a theme falls back to. Its schema half is real
 * (probe rest-posts-controller): a navigation post's status, raw title and
 * content are readable in the embed context too, so a block can embed the
 * menu it renders. Choosing or creating the fallback menu is still owed.
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

    public static function get_fallback()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_fallback');
        return null;
    }

    private static function get_most_recently_published_navigation()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_most_recently_published_navigation');
        return null;
    }

    private static function create_classic_menu_fallback()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::create_classic_menu_fallback');
        return null;
    }

    private static function get_fallback_classic_menu()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_fallback_classic_menu');
        return null;
    }

    private static function get_most_recently_created_nav_menu($classic_nav_menus)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_most_recently_created_nav_menu');
        return null;
    }

    private static function get_nav_menu_with_primary_slug($classic_nav_menus)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_nav_menu_with_primary_slug');
        return null;
    }

    private static function get_nav_menu_at_primary_location()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_nav_menu_at_primary_location');
        return null;
    }

    private static function create_default_fallback()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::create_default_fallback');
        return null;
    }

    private static function get_default_fallback_blocks()
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_Navigation_Fallback::get_default_fallback_blocks');
        return null;
    }
}
