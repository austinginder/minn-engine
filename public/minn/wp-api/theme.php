<?php
/** Theme lookups and theme mods. */

use Minn\Front\CustomLogo;
use Minn\Runtime\ThemeSupports;
use Minn\Runtime\Runtime;

function wp_get_theme($stylesheet = '', $theme_root = '')
{
    $stylesheet = (string) $stylesheet === '' ? get_stylesheet() : (string) $stylesheet;
    return new WP_Theme($stylesheet, $theme_root !== '' ? (string) $theme_root : get_theme_root($stylesheet));
}

function wp_get_themes($args = [])
{
    $out = [];
    foreach (glob(get_theme_root() . '/*/style.css') ?: [] as $file) {
        $slug = basename(dirname($file));
        $out[$slug] = new WP_Theme($slug, get_theme_root());
    }
    return $out;
}

function get_theme_mods()
{
    $mods = get_option('theme_mods_' . get_stylesheet());
    return is_array($mods) ? $mods : [];
}

function get_theme_mod($name, $default_value = false)
{
    $mods = get_theme_mods();
    if (isset($mods[$name])) {
        return apply_filters("theme_mod_{$name}", $mods[$name]);
    }
    if (is_string($default_value)) {
        $default_value = sprintf($default_value, get_template_directory_uri(), get_stylesheet_directory_uri());
    }
    return apply_filters("theme_mod_{$name}", $default_value);
}

function set_theme_mod($name, $value)
{
    $mods = get_theme_mods();
    $old = $mods[$name] ?? false;
    $mods[$name] = apply_filters("pre_set_theme_mod_{$name}", $value, $old);
    return update_option('theme_mods_' . get_stylesheet(), $mods);
}

function remove_theme_mod($name)
{
    $mods = get_theme_mods();
    if (!isset($mods[$name])) {
        return;
    }
    unset($mods[$name]);
    if ($mods === []) {
        remove_theme_mods();
        return;
    }
    update_option('theme_mods_' . get_stylesheet(), $mods);
}

function remove_theme_mods()
{
    delete_option('theme_mods_' . get_stylesheet());
}

function get_theme_support($feature, ...$args)
{
    $supports = ThemeSupports::all();
    if (!array_key_exists($feature, $supports)) {
        return false;
    }
    if ($args === [] || $supports[$feature] === true) {
        return $supports[$feature];
    }
    if (is_array($supports[$feature][0] ?? null) && array_is_list($supports[$feature][0])) {
        return in_array($args[0], $supports[$feature][0], true);
    }
    return $supports[$feature][0][$args[0]] ?? false;
}

function current_theme_supports($feature, ...$args)
{
    return apply_filters("current_theme_supports-{$feature}", get_theme_support($feature, ...$args) !== false, $args, get_theme_support($feature));
}

/** Declares a feature (Runtime\ThemeSupports): bare it is on, otherwise its arguments, merged as WordPress merges each. */
function add_theme_support($feature, ...$args)
{
    return ThemeSupports::add((string) $feature, $args) ? null : false;
}

function remove_theme_support($feature)
{
    return ThemeSupports::remove((string) $feature);
}

function wp_theme_has_theme_json()
{
    return is_file(get_stylesheet_directory() . '/theme.json') || is_file(get_template_directory() . '/theme.json');
}

function wp_theme_get_element_class_name($element)
{
    return in_array($element, ['button', 'caption'], true) ? 'wp-element-' . $element : '';
}

function get_theme_roots()
{
    return '/themes';
}

/** Adds a folder themes are looked for in (relative to the content folder when it does not exist as given); false when it is not there. */
function register_theme_directory($directory)
{
    $directory = (string) $directory;
    if (!file_exists($directory)) {
        $directory = WP_CONTENT_DIR . '/' . $directory;
        if (!file_exists($directory)) {
            return false;
        }
    }
    $directory = untrailingslashit($directory);
    $GLOBALS['wp_theme_directories'] ??= [];
    if (!in_array($directory, $GLOBALS['wp_theme_directories'], true)) {
        $GLOBALS['wp_theme_directories'][] = $directory;
    }
    return true;
}

/** The themes in the registered theme folders, stylesheet => its style.css and root, in folder order. */
function search_theme_directories($force = false)
{
    $found = [];
    foreach ((array) ($GLOBALS['wp_theme_directories'] ?? []) as $root) {
        foreach (is_dir($root) ? (scandir($root) ?: []) : [] as $entry) {
            if ($entry[0] !== '.' && is_file("{$root}/{$entry}/style.css")) {
                $found[$entry] ??= ['theme_file' => "{$entry}/style.css", 'theme_root' => $root];
            }
        }
    }
    return $found === [] ? false : $found;
}

function get_raw_theme_root($stylesheet_or_template, $skip_cache = false)
{
    return '/themes';
}

function is_child_theme()
{
    return get_template() !== get_stylesheet();
}

function switch_theme($stylesheet)
{
    $old = wp_get_theme();
    $new = wp_get_theme($stylesheet);
    update_option('template', $new->get_template());
    update_option('stylesheet', $new->get_stylesheet());
    update_option('current_theme', $new->get('Name'));
    do_action('switch_theme', $new->get('Name'), $new, $old);
}

function validate_current_theme()
{
    return true;
}

function get_header_image()
{
    return get_theme_mod('header_image');
}

function has_custom_logo($blog_id = 0)
{
    return (int) get_theme_mod('custom_logo') > 0 || (int) get_option('site_logo') > 0;
}

/** The site logo linked home (Front\CustomLogo), through get_custom_logo. */
function get_custom_logo($blog_id = 0)
{
    return CustomLogo::html((int) $blog_id);
}

function register_nav_menus($locations = [])
{
    $menus = Runtime::current()->get('nav_menus', []);
    Runtime::current()->set('nav_menus', array_merge($menus, $locations));
}

function register_nav_menu($location, $description)
{
    register_nav_menus([$location => $description]);
}

function unregister_nav_menu($location)
{
    $menus = Runtime::current()->get('nav_menus', []);
    unset($menus[$location]);
    Runtime::current()->set('nav_menus', $menus);
    return true;
}

function get_registered_nav_menus()
{
    return Runtime::current()->get('nav_menus', []);
}

function get_nav_menu_locations()
{
    $locations = get_theme_mod('nav_menu_locations');
    return is_array($locations) ? $locations : [];
}

function has_nav_menu($location)
{
    return !empty(get_nav_menu_locations()[$location]);
}

/** Stylesheets the editor shows the theme's content with; the theme then supports editor styles. */
function add_editor_style($stylesheet = 'editor-style.css')
{
    add_theme_support('editor-style');
    $sheets = (array) $stylesheet;
    if (is_rtl() && isset($sheets[0])) {
        $sheets[] = str_replace('.css', '-rtl.css', (string) $sheets[0]);
    }
    $GLOBALS['editor_styles'] = array_merge((array) ($GLOBALS['editor_styles'] ?? []), $sheets);
}

/** Withdraws the theme's support for editor styles (the list empties only in the admin); false when it had none. */
function remove_editor_styles()
{
    if (!current_theme_supports('editor-style')) {
        return false;
    }
    remove_theme_support('editor-style');
    if (is_admin()) {
        $GLOBALS['editor_styles'] = [];
    }
    return true;
}

/** The editor stylesheets' addresses: external ones first, then the theme's files that exist (a parent's before the child's), through editor_stylesheets. */
function get_editor_stylesheets()
{
    $sheets = [];
    $styles = array_unique(array_filter((array) ($GLOBALS['editor_styles'] ?? [])));
    foreach ($styles as $key => $file) {
        if (preg_match('~^(https?:)?//~', (string) $file)) {
            $sheets[] = sanitize_url((string) $file);
            unset($styles[$key]);
        }
    }
    $folders = is_child_theme() ? [[get_template_directory(), get_template_directory_uri()]] : [];
    $folders[] = [get_stylesheet_directory(), get_stylesheet_directory_uri()];
    foreach ($folders as [$dir, $uri]) {
        foreach ($styles as $file) {
            if (file_exists("{$dir}/{$file}")) {
                $sheets[] = "{$uri}/{$file}";
            }
        }
    }
    return apply_filters('editor_stylesheets', $sheets);
}

/**
 * The active theme's merged settings node (theme.json over the engine's
 * defaults, plus the site editor's saved settings unless the context asks
 * for the base origin). A path that names nothing yields the whole tree.
 */
function wp_get_global_settings($path = [], $context = [])
{
    $origin = is_array($context) && ($context['origin'] ?? '') === 'base' ? 'theme' : 'custom';
    $settings = WP_Theme_JSON_Resolver::get_merged_data($origin)->get_settings();
    foreach ((array) $path as $key) {
        if (!is_array($settings) || !array_key_exists($key, $settings)) {
            return WP_Theme_JSON_Resolver::get_merged_data($origin)->get_settings();
        }
        $settings = $settings[$key];
    }
    return $settings;
}

/** @internal the active theme's styles data, resolved once per request */
function _minn_theme_styles(): Minn\Theme\ThemeStyles
{
    static $cached = null;
    if ($cached === null) {
        $runtime = Runtime::current();
        $cached = Minn\Theme\ThemeStyles::forSite(new Minn\Content\Site($runtime->db), Minn\Front\Permalinks::fromDb($runtime->db), ABSPATH . 'wp-content/themes');
    }
    return $cached;
}

/** @internal the site editor's saved settings and styles for the active theme, as stored */
function _minn_user_styles(): array
{
    $runtime = Runtime::current();
    $site = new Minn\Content\Site($runtime->db);
    $theme = Minn\Theme\Theme::forStyles($site, Minn\Front\Permalinks::fromDb($runtime->db), ABSPATH . 'wp-content/themes');
    $saved = $theme === null ? null : (new Minn\Theme\Templates($runtime->db, new Minn\Content\Posts($runtime->db), $theme))->userStyles();
    return Minn\Theme\UserStyles::decode($saved === null ? '' : (string) json_encode($saved));
}

/**
 * The active theme's merged styles node (theme.json under the engine's
 * defaults, plus the site editor's saved global styles). A path that names
 * nothing yields the whole tree, as the reference's array read does.
 */
function wp_get_global_styles($path = [], $context = [])
{
    $styles = _minn_global_styles();
    foreach ((array) $path as $key) {
        if (!is_array($styles) || !array_key_exists($key, $styles)) {
            return _minn_global_styles();
        }
        $styles = $styles[$key];
    }
    return $styles;
}

/** @internal the merged styles node for the active theme, or [] without one */
function _minn_global_styles(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $runtime = Runtime::current();
    $site = new Minn\Content\Site($runtime->db);
    $permalinks = Minn\Front\Permalinks::fromDb($runtime->db);
    $theme = Minn\Theme\Theme::forStyles($site, $permalinks, ABSPATH . 'wp-content/themes');
    if ($theme === null) {
        return $cached = [];
    }
    $templates = new Minn\Theme\Templates($runtime->db, new Minn\Content\Posts($runtime->db), $theme);
    return $cached = (new Minn\Theme\GlobalStyles($theme, $templates->userStyles()))->resolvedStyles();
}

/** The global stylesheet by type (variables, styles, presets; all three when none is named), as Theme\GlobalStyles writes it. */
function wp_get_global_stylesheet($types = [])
{
    $types = (array) $types === [] ? ['variables', 'styles', 'presets'] : array_map('strval', (array) $types);
    $runtime = Runtime::current();
    $theme = Minn\Theme\Theme::forStyles(new Minn\Content\Site($runtime->db), Minn\Front\Permalinks::fromDb($runtime->db), ABSPATH . 'wp-content/themes');
    if ($theme === null) {
        return '';
    }
    $templates = new Minn\Theme\Templates($runtime->db, new Minn\Content\Posts($runtime->db), $theme);
    return (new Minn\Theme\GlobalStyles($theme, $templates->userStyles()))->stylesheet($types);
}

function the_custom_logo($blog_id = 0)
{
    echo get_custom_logo($blog_id);
}

/**
 * The menu assigned to a theme location, by name. An unassigned location and a
 * location the theme never registered both read as an empty string.
 */
function wp_get_nav_menu_name($location)
{
    $locations = get_nav_menu_locations();
    $menu = isset($locations[$location]) ? wp_get_nav_menu_object($locations[$location]) : false;
    $name = $menu && !is_wp_error($menu) ? (string) $menu->name : '';
    return apply_filters('wp_get_nav_menu_name', $name, $location);
}

function get_theme_updates()
{
    $offers = (array) (get_site_transient('update_themes')->response ?? []);
    $out = [];
    foreach ($offers as $stylesheet => $offer) {
        $theme = wp_get_theme((string) $stylesheet);
        if ($theme->exists()) {
            $theme->update = $offer;
            $out[$stylesheet] = $theme;
        }
    }
    return $out;
}

/** The default on delete_attachment: a deleted image stops being the site's logo. */
function _delete_attachment_theme_mod($id)
{
    if ((int) get_theme_mod('custom_logo') === (int) $id) {
        remove_theme_mod('custom_logo');
    }
}

/** Registers a theme feature and how REST shows it (Runtime\ThemeSupports); a WP_Error when it cannot be. */
function register_theme_feature($feature, $args = [])
{
    return ThemeSupports::register((string) $feature, (array) $args);
}

/** Core's theme features, then those registered since. */
function get_registered_theme_features()
{
    return ThemeSupports::features();
}

function get_registered_theme_feature($feature)
{
    return ThemeSupports::features()[$feature] ?? null;
}

/** Core's features are data (data/theme-features.json); nothing to register. */
function create_initial_theme_features()
{
}

/** What every block theme supports before its own setup runs. */
function _add_default_theme_supports()
{
    ThemeSupports::blockThemeDefaults();
}

/** The custom header's and background's defaults, filled in once WordPress has loaded. */
function _custom_header_background_just_in_time()
{
    ThemeSupports::justInTime();
}

/** A block theme's templates are its own. */
function wp_enable_block_templates()
{
    ThemeSupports::editorDefaults('block-templates');
}

/** Widgets are edited as blocks unless a theme or plugin takes the support away. */
function wp_setup_widgets_block_editor()
{
    ThemeSupports::editorDefaults('widgets-block-editor');
}
