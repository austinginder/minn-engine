<?php

/**
 * The slice of the reference's theme-JSON resolver that plugin code reaches
 * for: the user global-styles post. The find is the wp_theme term for the
 * active stylesheet; a missing post is created the way the reference
 * creates it (captured shape: publish, title "Custom Styles", name
 * wp-global-styles-{stylesheet}, closed discussion, versioned JSON body)
 * and linked to the theme term so the next call finds it.
 */
final class WP_Theme_JSON_Resolver
{
    public static function get_user_global_styles_post_id(): int
    {
        $stylesheet = get_stylesheet();
        $found = get_posts([
            'post_type' => 'wp_global_styles',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => $stylesheet]],
        ]);
        if ($found !== []) {
            return (int) $found[0]->ID;
        }
        $id = (int) wp_insert_post([
            'post_type' => 'wp_global_styles',
            'post_status' => 'publish',
            'post_title' => 'Custom Styles',
            'post_name' => 'wp-global-styles-' . $stylesheet,
            'post_content' => '{"version": 3, "isGlobalStylesUserThemeJSON": true }',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
        if ($id > 0) {
            wp_set_post_terms($id, [$stylesheet], 'wp_theme');
        }
        return $id;
    }
}
