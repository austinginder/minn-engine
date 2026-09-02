<?php

use Minn\Theme\StyleSettings;
use Minn\Theme\UserStyles;

/**
 * The slice of the reference's theme-JSON resolver that plugin code reaches
 * for: the user global-styles post, and the merged data (the engine's
 * defaults, the theme, the site editor's saved styles) as a theme.json
 * object. The find is the wp_theme term for the active stylesheet; a
 * missing post is created the way the reference creates it (captured
 * shape: publish, title "Custom Styles", name wp-global-styles-{stylesheet},
 * closed discussion, versioned JSON body) and linked to the theme term so
 * the next call finds it.
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

    /** Defaults, theme, and (for origin custom) the site editor's saved styles, merged. */
    public static function get_merged_data($origin = 'custom'): WP_Theme_JSON
    {
        $theme = _minn_theme_styles();
        $settings = $theme->settings();
        $styles = $theme->styles();
        if ($origin === 'custom') {
            $user = self::get_user_data()->get_raw_data();
            $settings = Minn\Theme\Theme::merge($settings, (array) ($user['settings'] ?? []));
            $styles = Minn\Theme\Theme::merge($styles, (array) ($user['styles'] ?? []));
        }
        return new WP_Theme_JSON(['version' => 3, 'settings' => $settings, 'styles' => $styles]);
    }

    /** The active theme over the defaults, the site editor's styles aside. */
    public static function get_theme_data(): WP_Theme_JSON
    {
        return self::get_merged_data('theme');
    }

    /** The engine's defaults alone. */
    public static function get_core_data(): WP_Theme_JSON
    {
        $core = new Minn\Theme\ThemeStyles(null, '');
        return new WP_Theme_JSON(['version' => 3, 'settings' => $core->settings(), 'styles' => $core->styles()]);
    }

    /** The site editor's saved styles for the active theme, normalized the way the REST item reports them. */
    public static function get_user_data(): WP_Theme_JSON
    {
        $saved = _minn_user_styles();
        return new WP_Theme_JSON([
            'version' => 3,
            'isGlobalStylesUserThemeJSON' => true,
            'settings' => StyleSettings::normalize($saved['settings'], 'custom'),
            'styles' => StyleSettings::resolved($saved['styles']),
        ]);
    }
}
