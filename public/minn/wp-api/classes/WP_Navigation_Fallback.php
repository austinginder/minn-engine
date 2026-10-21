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
     * then the newest published again (Runtime\NavigationFallback).
     */
    public static function get_fallback()
    {
        return Minn\Runtime\NavigationFallback::fallback();
    }

    private static function get_most_recently_published_navigation()
    {
        return Minn\Runtime\NavigationFallback::newest();
    }

    /** A published navigation menu listing the site's pages, titled Navigation. */
    private static function create_default_fallback()
    {
        return Minn\Runtime\NavigationFallback::createDefault();
    }
}
