<?php
/** Theme lookups and theme mods. */

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
    $supports = Runtime::current()->get('theme_supports', []);
    if (!array_key_exists($feature, $supports) && wp_is_block_theme()) {
        // A block theme carries the reference's implied supports.
        $implied = ['html5' => [['comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script']], 'post-thumbnails' => true, 'responsive-embeds' => true, 'editor-styles' => true, 'automatic-feed-links' => true, 'title-tag' => true, 'wp-block-styles' => true, 'align-wide' => true];
        if (isset($implied[$feature])) {
            $supports[$feature] = $implied[$feature];
        }
    }
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

function add_theme_support($feature, ...$args)
{
    $supports = Runtime::current()->get('theme_supports', []);
    $supports[$feature] = $args === [] ? true : $args;
    Runtime::current()->set('theme_supports', $supports);
}

function remove_theme_support($feature)
{
    $supports = Runtime::current()->get('theme_supports', []);
    unset($supports[$feature]);
    Runtime::current()->set('theme_supports', $supports);
    return true;
}

function wp_theme_has_theme_json()
{
    return is_file(get_stylesheet_directory() . '/theme.json') || is_file(get_template_directory() . '/theme.json');
}

function get_theme_roots()
{
    return '/themes';
}

function register_theme_directory($directory)
{
    return true;
}

function search_theme_directories($force = false)
{
    return false;
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

function get_custom_logo($blog_id = 0)
{
    return '';
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

function add_editor_style($stylesheet = 'editor-style.css')
{
}

function remove_editor_styles()
{
    return true;
}

function get_editor_stylesheets()
{
    return [];
}

function wp_get_global_settings($path = [], $context = [])
{
    return [];
}

function wp_get_global_styles($path = [], $context = [])
{
    return [];
}

function wp_get_global_stylesheet($types = [])
{
    return '';
}
