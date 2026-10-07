<?php

use Minn\Runtime\Runtime;
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
#[AllowDynamicProperties]
final class WP_Theme_JSON_Resolver
{
    /** A theme's user styles post as an array, made when asked and missing; [] when there is none. */
    public static function get_user_data_from_wp_global_styles($theme, $create_post = false, $post_status_filter = ['publish'])
    {
        $stylesheet = $theme instanceof WP_Theme ? $theme->get_stylesheet() : (string) $theme;
        $found = get_posts([
            'post_type' => 'wp_global_styles',
            'post_status' => $post_status_filter,
            'numberposts' => 1,
            'orderby' => 'ID',
            'order' => 'DESC',
            'tax_query' => [['taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => $stylesheet]],
        ]);
        if ($found !== []) {
            return get_object_vars($found[0]);
        }
        if (!$create_post || $stylesheet !== get_stylesheet()) {
            return [];
        }
        $id = self::get_user_global_styles_post_id();
        return $id > 0 ? get_object_vars(get_post($id)) : [];
    }

    public static function get_user_global_styles_post_id(): int
    {
        $stylesheet = get_stylesheet();
        $found = self::get_user_data_from_wp_global_styles($stylesheet);
        if ($found !== []) {
            return (int) $found['ID'];
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

    /** Forgets what was worked out (the engine works the filtered layers out per set of filters, so nothing goes stale). */
    public static function clean_cached_data()
    {
        Runtime::current()->set('block_style_partials_registered', false);
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

    /**
     * The active theme over the defaults, the site editor's styles aside.
     * Reading it registers the theme's block style partials as block
     * styles, once a request, as the reference's read does.
     */
    public static function get_theme_data(): WP_Theme_JSON
    {
        if (!Runtime::current()->get('block_style_partials_registered', false)) {
            Runtime::current()->set('block_style_partials_registered', true);
            foreach (self::get_style_variations('block') as $variation) {
                foreach ((array) ($variation['blockTypes'] ?? []) as $block) {
                    register_block_style((string) $block, ['name' => (string) ($variation['slug'] ?? ''), 'label' => (string) ($variation['title'] ?? '')]);
                }
            }
        }
        return self::get_merged_data('theme');
    }

    /**
     * The theme's style variations, in path order (a child theme's folder
     * before its parent's): for 'block', the partials under styles/ that
     * name the blocks they style; otherwise the rest (whole-site styles,
     * colour and typography sets).
     */
    public static function get_style_variations($scope = 'theme')
    {
        $out = [];
        foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $dir) {
            $files = is_dir("{$dir}/styles") ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$dir}/styles", FilesystemIterator::SKIP_DOTS))) : [];
            ksort($files);
            foreach (array_keys($files) as $file) {
                $data = str_ends_with($file, '.json') ? json_decode((string) file_get_contents($file), true) : null;
                if (is_array($data) && ($scope === 'block') === isset($data['blockTypes'])) {
                    unset($data['$schema']);
                    $out[] = $data;
                }
            }
        }
        return $out;
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
