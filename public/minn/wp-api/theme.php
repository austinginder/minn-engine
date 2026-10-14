<?php
/** Theme lookups and theme mods. */

use Minn\Front\CustomLogo;
use Minn\Runtime\ThemeSupports;
use Minn\Runtime\Runtime;

function wp_get_theme($stylesheet = '', $theme_root = '')
{
    return Minn\Runtime\Themes::theme($stylesheet, $theme_root);
}

function wp_get_themes($args = [])
{
    return Minn\Runtime\Themes::all($args);
}

function get_theme_mods()
{
    return Minn\Runtime\ThemeMods::all();
}

function get_theme_mod($name, $default_value = false)
{
    return Minn\Runtime\ThemeMods::get($name, $default_value);
}

function set_theme_mod($name, $value)
{
    return Minn\Runtime\ThemeMods::set($name, $value);
}

function remove_theme_mod($name)
{
    Minn\Runtime\ThemeMods::remove($name);
}

function remove_theme_mods()
{
    Minn\Runtime\ThemeMods::removeAll();
}

function get_theme_support($feature, ...$args)
{
    return Minn\Runtime\ThemeSupports::get($feature, ...$args);
}

/**
 * Whether the theme supports a feature: never one it did not add; a type
 * asked of a feature that lists its own (formats, thumbnails, html5, the
 * custom header and background) answered from the list; anything else the
 * filter's, handed true (probe hook-inputs-2).
 */
function current_theme_supports($feature, ...$args)
{
    return Minn\Runtime\ThemeSupports::current($feature, ...$args);
}

/** Declares a feature (Runtime\ThemeSupports): bare it is on, otherwise its arguments, merged as WordPress merges each. */
function add_theme_support($feature, ...$args)
{
    return ThemeSupports::add((string) $feature, $args) ? null : false;
}

/** @internal A feature taken back, whichever it is; false for one not supported. */
function _remove_theme_support($feature)
{
    return ThemeSupports::remove((string) $feature);
}

/** A feature taken back; false for one not supported, and for the three the reference will not take back this way (editor styles, widgets, menus). */
function remove_theme_support($feature)
{
    if (in_array($feature, ['editor-style', 'widgets', 'menus'], true)) {
        return false;
    }
    return ThemeSupports::remove((string) $feature);
}

function wp_theme_has_theme_json()
{
    return is_file(get_stylesheet_directory() . '/theme.json') || is_file(get_template_directory() . '/theme.json');
}

/**
 * @internal a WP_Theme_JSON holding data the engine has already worked
 * out (merged, presets under their origins), as the reference's merged
 * objects hold theirs: not built again, so not sanitized twice.
 */
function _minn_theme_json_object(array $data): WP_Theme_JSON
{
    $object = (new ReflectionClass(WP_Theme_JSON::class))->newInstanceWithoutConstructor();
    Closure::bind(function () use ($data): void {
        $this->theme_json = $data;
    }, $object, WP_Theme_JSON::class)();
    return $object;
}

function wp_theme_get_element_class_name($element)
{
    return WP_Theme_JSON::get_element_class_name($element);
}

function get_theme_roots()
{
    return '/themes';
}

/** Adds a folder themes are looked for in (relative to the content folder when it does not exist as given); false when it is not there. */
function register_theme_directory($directory)
{
    return Minn\Runtime\Themes::registerDirectory($directory);
}

/** The themes in the registered theme folders, stylesheet => its style.css and root, in folder order. */
function search_theme_directories($force = false)
{
    // A search answers from the last one unless forced; a fresh one asks whether themes are cached across requests.
    static $last = null;
    if (!$force && $last !== null) {
        return $last;
    }
    apply_filters('wp_cache_themes_persistently', false, 'search_theme_directories');
    $found = [];
    foreach ((array) ($GLOBALS['wp_theme_directories'] ?? []) as $root) {
        foreach (is_dir($root) ? (scandir($root) ?: []) : [] as $entry) {
            if ($entry[0] !== '.' && is_file("{$root}/{$entry}/style.css")) {
                $found[$entry] ??= ['theme_file' => "{$entry}/style.css", 'theme_root' => $root];
            }
        }
    }
    return $last = $found === [] ? false : $found;
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
    Minn\Runtime\ThemeSwitch::switchTo((string) $stylesheet);
}

function check_theme_switched()
{
    Minn\Runtime\ThemeSwitch::checkSwitched();
}

function _wp_menus_changed()
{
    Minn\Runtime\ThemeSwitch::menusChanged();
}

/** @internal after a switch the widgets are looked over against the arriving theme's sidebars */
function _wp_sidebars_changed()
{
    retrieve_widgets(true);
}

function wp_map_nav_menu_locations($new_nav_menu_locations, $old_nav_menu_locations)
{
    return Minn\Runtime\ThemeSwitch::mapLocations((array) $new_nav_menu_locations, $old_nav_menu_locations);
}

/**
 * Whether the active theme has what it needs (an index template, a child
 * its stylesheet); one that does not gives way to the default theme, and
 * false says so. validate_current_theme returning false skips the check.
 */
function validate_current_theme()
{
    if (wp_installing() || !apply_filters('validate_current_theme', true)) {
        return true;
    }
    $template = get_template_directory();
    $whole = is_file("{$template}/templates/index.html") || is_file("{$template}/block-templates/index.html") || is_file("{$template}/index.php");
    if ($whole && is_file(get_stylesheet_directory() . '/style.css')) {
        return true;
    }
    $fallback = wp_get_theme(WP_DEFAULT_THEME);
    $fallback = $fallback->exists() ? $fallback : WP_Theme::get_core_default_theme();
    if (!$fallback || get_stylesheet() === $fallback->get_stylesheet()) {
        return true;
    }
    switch_theme($fallback->get_stylesheet());
    return false;
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
    return Minn\Runtime\NavMenu::locations();
}

/** Whether a registered location has a menu assigned, through has_nav_menu (probe nav-menu-remaining). */
function has_nav_menu($location)
{
    $has = isset(get_registered_nav_menus()[$location]) && !empty(get_nav_menu_locations()[$location]);
    return (bool) apply_filters('has_nav_menu', $has, $location);
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
    _remove_theme_support('editor-style');
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
    if (is_array($context) && !empty($context['block_name'])) {
        // A block's settings sit under blocks.{name}; a path not given as a list reads them whole.
        $path = array_merge(['blocks', $context['block_name']], is_array($path) ? $path : []);
    }
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
    $runtime = Runtime::current();
    $cached = $runtime->get('minn_theme_styles');
    if (!$cached instanceof Minn\Theme\ThemeStyles) {
        $cached = Minn\Theme\ThemeStyles::forSite(new Minn\Content\Site($runtime->db), Minn\Front\Permalinks::fromDb($runtime->db), ABSPATH . 'wp-content/themes');
        $runtime->set('minn_theme_styles', $cached);
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
    $runtime = Runtime::current();
    $cached = $runtime->get('minn_global_styles');
    if (is_array($cached)) {
        return $cached;
    }
    $site = new Minn\Content\Site($runtime->db);
    $permalinks = Minn\Front\Permalinks::fromDb($runtime->db);
    $theme = Minn\Theme\Theme::forStyles($site, $permalinks, ABSPATH . 'wp-content/themes');
    $styles = [];
    if ($theme !== null) {
        $templates = new Minn\Theme\Templates($runtime->db, new Minn\Content\Posts($runtime->db), $theme);
        $styles = (new Minn\Theme\GlobalStyles($theme, $templates->userStyles()))->resolvedStyles();
    }
    $runtime->set('minn_global_styles', $styles);
    return $styles;
}

/** Forgets the merged theme.json data for the request, so the next read builds it again (with the block styles registered since). */
function wp_clean_theme_json_cache()
{
    Runtime::current()->set('minn_theme_styles', null);
    Runtime::current()->set('minn_global_styles', null);
    Runtime::current()->set('minn_global_styles_custom_css', null);
    WP_Theme_JSON_Resolver::clean_cached_data();
}

/** The global stylesheet by type (variables, styles, presets; all three when none is named), as Theme\GlobalStyles writes it. */
/**
 * @internal the paths of the style nodes a stylesheet prints, as
 * wp_theme_json_get_style_nodes leaves the reference's list for these
 * styles (WP_Theme_JSON::get_style_nodes): "styles", "styles/elements/link",
 * "styles/blocks/core/paragraph" and so on
 */
function _minn_style_node_paths(array $styles): array
{
    $nodes = Closure::bind(static fn (array $json): array => WP_Theme_JSON::get_style_nodes($json), null, WP_Theme_JSON::class)(['styles' => $styles]);
    return array_values(array_unique(array_map(static fn ($node): string => implode('/', (array) ($node['path'] ?? [])), array_filter((array) $nodes, 'is_array'))));
}

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

/** Beside a custom logo whose theme names header text classes, with the header text turned off, a style that hides those classes (probe site-logo). */
function _custom_logo_header_styles()
{
    $classes = get_theme_support('custom-logo', 'header-text');
    if (!$classes || get_theme_mod('header_text', true)) {
        return;
    }
    $selector = '.' . implode(', .', array_map('sanitize_html_class', (array) $classes));
    echo "\t\t<!-- Custom Logo: hide header text -->\n\t\t<style id=\"custom-logo-css\">\n\t\t\t{$selector} {\n\t\t\t\tposition: absolute;\n\t\t\t\tclip-path: inset(50%);\n\t\t\t}\n\t\t</style>\n\t\t";
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

/** The default on delete_attachment: a deleted image stops being the site's logo, and its background when it was that exactly. */
function _delete_attachment_theme_mod($id)
{
    if ((int) get_theme_mod('custom_logo') === (int) $id) {
        remove_theme_mod('custom_logo');
    }
    // The background image goes when it is the attachment's address exactly; the header stays (probe theme-remaining).
    $url = wp_get_attachment_url($id);
    if ($url && get_theme_mod('background_image') === $url) {
        remove_theme_mod('background_image');
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
/** A block theme's default supports, and its core block assets loaded separately and on demand. */
function _add_default_theme_supports()
{
    ThemeSupports::blockThemeDefaults();
    if (wp_is_block_theme()) {
        add_filter('should_load_separate_core_block_assets', '__return_true');
        add_filter('should_load_block_assets_on_demand', '__return_true');
    }
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

/** The theme's stylesheet for the locale ({locale}.css), else for its text direction (rtl.css), through locale_stylesheet_uri. */
function get_locale_stylesheet_uri()
{
    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();
    $locale = get_locale();
    $direction = (string) ($GLOBALS['wp_locale']->text_direction ?? '');
    $stylesheet = file_exists("{$dir}/{$locale}.css") ? "{$uri}/{$locale}.css" : ($direction !== '' && file_exists("{$dir}/{$direction}.css") ? "{$uri}/{$direction}.css" : '');
    return apply_filters('locale_stylesheet_uri', $stylesheet, $uri);
}

/** The folders a block theme keeps its templates and parts in: templates and parts, or the old names when the theme has those; the defaults for a theme that is not there. */
function get_block_theme_folders($theme_stylesheet = null)
{
    $theme = wp_get_theme((string) $theme_stylesheet);
    return $theme->exists() ? $theme->get_block_template_folders() : ['wp_template' => 'templates', 'wp_template_part' => 'parts'];
}

/** @deprecated 3.4.0 The current theme's name. */
function get_current_theme()
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'wp_get_theme()');
    return get_option('current_theme') ?: wp_get_theme()->get('Name');
}

/** @deprecated 3.4.0 The installed themes keyed by name (a repeated name also by name/stylesheet). */
function get_themes()
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'wp_get_themes()');
    $named = [];
    foreach (wp_get_themes() as $theme) {
        $name = (string) $theme->get('Name');
        $named[isset($named[$name]) ? $name . '/' . $theme->get_stylesheet() : $name] = $theme;
    }
    return $named;
}

/** @deprecated 3.4.0 An installed theme by name, or null. */
function get_theme($theme)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'wp_get_theme($stylesheet)');
    $themes = get_themes();
    return array_key_exists($theme, $themes) ? $themes[$theme] : null;
}

/** A CSS length's number and unit (px, rem, em), converted on request; null for anything else. */
function wp_get_typography_value_and_unit($raw_value, $options = [])
{
    if (!is_string($raw_value) && !is_int($raw_value) && !is_float($raw_value)) {
        _doing_it_wrong(__FUNCTION__, __('Raw size value must be a string, integer, or float.'), '6.1.0');
        return null;
    }
    return Minn\Theme\Typography::valueAndUnit($raw_value, (array) $options);
}

/** A font size preset as CSS: fluid (a clamp()) when the settings, over the global ones, turn fluid typography on. */
function wp_get_typography_font_size_value($preset, $settings = [])
{
    if (is_bool($settings)) {
        _deprecated_argument(__FUNCTION__, '6.6.0', __('$settings must be an array.'));
        $settings = ['typography' => ['fluid' => $settings]];
    }
    return Minn\Theme\Typography::fontSize((array) $preset, wp_parse_args($settings, wp_get_global_settings()));
}

/** The clamp() between two font sizes over two viewport widths, or null. */
function wp_get_computed_fluid_typography_value($args = [])
{
    return Minn\Theme\Typography::clamp($args['minimum_viewport_width'] ?? null, $args['maximum_viewport_width'] ?? null, $args['minimum_font_size'] ?? null, $args['maximum_font_size'] ?? null, $args['scale_factor'] ?? null);
}

/** Whether a path in a theme lies under a folder theme scans pass over: version control, macOS's, node_modules and vendor. */
function wp_is_theme_directory_ignored($path)
{
    foreach (['.DS_Store', '.svn', '.git', '.hg', '.bzr', 'node_modules', 'vendor'] as $directory) {
        if (str_starts_with((string) $path, $directory)) {
            return true;
        }
    }
    return false;
}

/** Deprecated since 3.4: a theme's headers by the old names, from its style.css (wp_get_theme). */
function get_theme_data($theme_file)
{
    _deprecated_function(__FUNCTION__, '3.4.0', 'wp_get_theme()');
    $theme = wp_get_theme(wp_basename(dirname($theme_file)), dirname($theme_file, 2));
    $data = ['Name' => $theme->get('Name'), 'URI' => $theme->display('ThemeURI', true, false), 'Description' => $theme->display('Description', true, false), 'Author' => $theme->display('Author', true, false), 'AuthorURI' => $theme->display('AuthorURI', true, false), 'Version' => $theme->get('Version'), 'Template' => $theme->get('Template'), 'Status' => $theme->get('Status'), 'Tags' => $theme->get('Tags'), 'Title' => $theme->get('Name'), 'AuthorName' => $theme->get('Author')];
    foreach ((array) apply_filters('extra_theme_headers', []) as $header) {
        $data[$header] ??= $theme->get($header);
    }
    return $data;
}

/** The starter content a theme declares, through get_theme_starter_content; none without it. */
function get_theme_starter_content()
{
    $config = get_theme_support('starter-content');
    $config = is_array($config) && isset($config[0]) && is_array($config[0]) ? $config[0] : [];
    return apply_filters('get_theme_starter_content', $config, $config);
}

/** The custom templates theme.json declares: title and post types (pages when it names none). */
function wp_get_theme_data_custom_templates()
{
    $templates = [];
    foreach ((array) (Runtime::current()->get('theme')?->json()['customTemplates'] ?? []) as $item) {
        if (isset($item['name'])) {
            $templates[$item['name']] = ['title' => $item['title'] ?? '', 'postTypes' => $item['postTypes'] ?? ['page']];
        }
    }
    return $templates;
}

/** The template parts theme.json declares: title and area. */
function wp_get_theme_data_template_parts()
{
    $parts = [];
    foreach ((array) (Runtime::current()->get('theme')?->json()['templateParts'] ?? []) as $item) {
        if (isset($item['name'])) {
            $parts[$item['name']] = ['title' => $item['title'] ?? '', 'area' => $item['area'] ?? ''];
        }
    }
    return $parts;
}

/** The theme directory's features by group, as labels; the engine asks wordpress.org for nothing, so always this list. */
function get_theme_feature_list($api = true)
{
    return [
        __('Subject') => ['blog' => __('Blog'), 'e-commerce' => __('E-Commerce'), 'education' => __('Education'), 'entertainment' => __('Entertainment'), 'food-and-drink' => __('Food & Drink'), 'holiday' => __('Holiday'), 'news' => __('News'), 'photography' => __('Photography'), 'portfolio' => __('Portfolio')],
        __('Features') => ['accessibility-ready' => __('Accessibility Ready'), 'block-patterns' => __('Block Editor Patterns'), 'block-styles' => __('Block Editor Styles'), 'custom-background' => __('Custom Background'), 'custom-colors' => __('Custom Colors'), 'custom-header' => __('Custom Header'), 'custom-logo' => __('Custom Logo'), 'editor-style' => __('Editor Style'), 'featured-image-header' => __('Featured Image Header'), 'featured-images' => __('Featured Images'), 'footer-widgets' => __('Footer Widgets'), 'full-site-editing' => __('Site Editor'), 'full-width-template' => __('Full Width Template'), 'post-formats' => __('Post Formats'), 'sticky-post' => __('Sticky Post'), 'style-variations' => __('Style Variations'), 'template-editing' => __('Template Editing'), 'theme-options' => __('Theme Options')],
        __('Layout') => ['grid-layout' => __('Grid Layout'), 'one-column' => __('One Column'), 'two-columns' => __('Two Columns'), 'three-columns' => __('Three Columns'), 'four-columns' => __('Four Columns'), 'left-sidebar' => __('Left Sidebar'), 'right-sidebar' => __('Right Sidebar'), 'wide-blocks' => __('Wide Blocks')],
    ];
}

/** A file required (each time) when the theme supports a feature; whether it was. */
function require_if_theme_supports($feature, $file)
{
    if (!current_theme_supports($feature)) {
        return false;
    }
    require $file;
    return true;
}

/** True when the theme's Requires at least and Requires PHP headers are met (or it is unknown); a WP_Error naming what is not (probe template-loading). */
function validate_theme_requirements($stylesheet)
{
    $theme = wp_get_theme($stylesheet);
    $wp = is_wp_version_compatible((string) $theme->get('RequiresWP'));
    $php = is_php_version_compatible((string) $theme->get('RequiresPHP'));
    $name = $theme->display('Name');
    return match (true) {
        !$wp && !$php => new WP_Error('theme_wp_php_incompatible', sprintf(__('<strong>Error:</strong> Current WordPress and PHP versions do not meet minimum requirements for %s.'), $name)),
        !$php => new WP_Error('theme_php_incompatible', sprintf(__('<strong>Error:</strong> Current PHP version does not meet minimum requirements for %s.'), $name)),
        !$wp => new WP_Error('theme_wp_incompatible', sprintf(__('<strong>Error:</strong> Current WordPress version does not meet minimum requirements for %s.'), $name)),
        // A theme that meets them is the plugins' to judge further (probe hook-inputs).
        default => apply_filters('validate_theme_requirements', true, $stylesheet),
    };
}
