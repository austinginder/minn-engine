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
    /** @internal the JSON files read this request, by path */
    private static $minn_files = [];

    /** @internal the blocks each origin last saw (has_same_registered_blocks) */
    private static $minn_blocks_seen = ['core' => [], 'blocks' => [], 'theme' => [], 'user' => []];

    /** @internal the translation schema, read once */
    private static $minn_i18n_schema = null;

    /** A theme's user styles post as an array, made when asked and missing; [] when there is none. */
    public static function get_user_data_from_wp_global_styles($theme, $create_post = false, $post_status_filter = ['publish'])
    {
        return Minn\Runtime\UserStylesPost::data($theme, $create_post, $post_status_filter);
    }

    public static function get_user_global_styles_post_id(): int
    {
        return Minn\Runtime\UserStylesPost::id();
    }

    /** Forgets what was worked out: the blocks each origin saw; files read stay read (the engine works the filtered layers out per set of filters, so nothing else goes stale). */
    public static function clean_cached_data()
    {
        Runtime::current()->set('block_style_partials_registered', false);
        self::$minn_blocks_seen = ['core' => [], 'blocks' => [], 'theme' => [], 'user' => []];
    }

    /** @deprecated 6.2.0 whether the theme has a theme.json */
    public static function theme_has_support()
    {
        _deprecated_function(__METHOD__, '6.2.0', 'wp_theme_has_theme_json()');
        return wp_theme_has_theme_json();
    }

    /** @deprecated 5.9.0 nothing, now */
    public static function get_fields_to_translate()
    {
        _deprecated_function(__METHOD__, '5.9.0');
        return [];
    }

    /** A file of the active theme (or with $template, of its parent), '' when it cannot be read. */
    protected static function get_file_path_from_theme($file_name, $template = false)
    {
        $candidate = ($template ? get_template_directory() : get_stylesheet_directory()) . '/' . $file_name;
        return is_readable($candidate) ? $candidate : '';
    }

    /** A JSON file as an array, kept for the request by path; [] for a missing file, broken JSON or anything but an object. */
    protected static function read_json_file($file_path)
    {
        if (!$file_path) {
            return [];
        }
        if (!array_key_exists($file_path, self::$minn_files)) {
            $decoded = wp_json_file_decode($file_path, ['associative' => true]);
            self::$minn_files[$file_path] = is_array($decoded) ? $decoded : [];
        }
        return self::$minn_files[$file_path];
    }

    /** A theme.json's translatable names (data/theme-i18n.json, captured) in a domain. */
    protected static function translate($theme_json, $domain = 'default')
    {
        self::$minn_i18n_schema ??= wp_json_file_decode(MINN_ENGINE_DIR . '/data/theme-i18n.json') ?? [];
        return translate_settings_using_i18n_schema(self::$minn_i18n_schema, $theme_json, $domain);
    }

    /**
     * Whether no block is registered that an origin (core, blocks, theme,
     * user) has not seen; when one is, the origin takes in the registry as
     * it is and the answer is false. Any other origin is always false.
     */
    protected static function has_same_registered_blocks($origin)
    {
        if (!isset(self::$minn_blocks_seen[$origin])) {
            return false;
        }
        $registered = WP_Block_Type_Registry::get_instance()->get_all_registered();
        if (array_diff_key($registered, self::$minn_blocks_seen[$origin]) === []) {
            return true;
        }
        self::$minn_blocks_seen[$origin] = $registered;
        return false;
    }

    /**
     * The theme files a theme.json names as background images ("file:./..."),
     * at the top of its styles and in each block's, with the theme URI each
     * resolves to, where it sits and its type. Remote images, elements and
     * variations are left as they are.
     */
    public static function get_resolved_theme_uris($theme_json)
    {
        if (!$theme_json instanceof WP_Theme_JSON) {
            return [];
        }
        $styles = (array) ($theme_json->get_raw_data()['styles'] ?? []);
        $places = ['styles.background.backgroundImage.url' => $styles['background']['backgroundImage']['url'] ?? null];
        foreach ((array) ($styles['blocks'] ?? []) as $block => $node) {
            $places["styles.blocks.{$block}.background.backgroundImage.url"] = $node['background']['backgroundImage']['url'] ?? null;
        }
        $resolved = [];
        foreach ($places as $target => $url) {
            if (is_string($url) && str_starts_with($url, 'file:')) {
                $type = wp_check_filetype($url)['type'];
                $resolved[] = ['name' => $url, 'href' => sanitize_url(get_theme_file_uri(preg_replace('#^file:\./#', '', $url))), 'target' => $target] + ($type ? ['type' => $type] : []);
            }
        }
        return $resolved;
    }

    /** The theme.json with each theme file it names put at its URI; the same object when it names none. */
    public static function resolve_theme_file_uris($theme_json)
    {
        $resolved = self::get_resolved_theme_uris($theme_json);
        if ($resolved === []) {
            return $theme_json;
        }
        $data = $theme_json->get_raw_data();
        foreach ($resolved as $uri) {
            _wp_array_set($data, explode('.', $uri['target']), $uri['href']);
        }
        return new WP_Theme_JSON($data);
    }

    /** Defaults, theme, and (for origin custom) the site editor's saved styles, merged. */
    public static function get_merged_data($origin = 'custom'): WP_Theme_JSON
    {
        // Core's layer as wp_theme_json_data_default leaves it, as the reference's resolver reads it.
        return self::minn_merged(_minn_theme_styles()->withFilteredCore(), $origin);
    }

    /** @internal the layers merged over the core layer the theme's styles carry */
    private static function minn_merged(Minn\Theme\ThemeStyles $theme, string $origin): WP_Theme_JSON
    {
        $settings = $theme->settings();
        $styles = $theme->styles();
        if ($origin === 'custom') {
            $user = self::get_user_data()->get_raw_data();
            $settings = Minn\Theme\Theme::merge($settings, (array) ($user['settings'] ?? []));
            $styles = Minn\Theme\Theme::merge($styles, (array) ($user['styles'] ?? []));
        }
        // The pattern directory slugs a theme.json lists come along when it lists any (probe block-patterns).
        $patterns = $theme->patterns();
        return _minn_theme_json_object(['version' => 3, 'settings' => $settings, 'styles' => $styles] + ($patterns !== [] ? ['patterns' => $patterns] : []));
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
        // The theme's own read never asks core's layer filter (probe theme-json-resolver).
        return self::minn_merged(_minn_theme_styles(), 'theme');
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

    /**
     * The styles the registered blocks bring (probe loose-classes): a
     * block's style default from its supports, and a block gap left unset
     * for a block whose gap support names a default of its own; through
     * wp_theme_json_data_blocks, made afresh on every call.
     */
    public static function get_block_data()
    {
        $blocks = [];
        foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
            $supports = (array) $type->supports;
            if (isset($supports['__experimentalStyle'])) {
                $blocks[$name] = (array) $supports['__experimentalStyle'];
            }
            if (isset($supports['spacing']['blockGap']['__experimentalDefault'])) {
                $blocks[$name]['spacing']['blockGap'] = null;
            }
        }
        $data = apply_filters('wp_theme_json_data_blocks', new WP_Theme_JSON_Data(['version' => 3, 'styles' => ['blocks' => $blocks]], 'blocks'));
        return $data->get_theme_json();
    }

    /** The engine's defaults alone. */
    public static function get_core_data(): WP_Theme_JSON
    {
        $core = (new Minn\Theme\ThemeStyles(null, ''))->withFilteredCore();
        return _minn_theme_json_object(['version' => 3, 'settings' => $core->settings(), 'styles' => $core->styles()]);
    }

    /**
     * The site editor's saved styles for the active theme, normalized the
     * way the REST item reports them, as wp_theme_json_data_user leaves them.
     */
    public static function get_user_data(): WP_Theme_JSON
    {
        $saved = _minn_user_styles();
        $layer = ['settings' => StyleSettings::normalize($saved['settings'], 'custom'), 'styles' => StyleSettings::resolved($saved['styles'])];
        $layer = Minn\Theme\ThemeJsonData::layer('custom', $layer);
        return _minn_theme_json_object(['version' => 3, 'isGlobalStylesUserThemeJSON' => true] + $layer + ['settings' => [], 'styles' => []]);
    }
}
