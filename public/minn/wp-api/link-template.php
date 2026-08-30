<?php
/** URLs of the site and its pieces. */

use Minn\Front\Pagination;
use Minn\Media\Uploads;
use Minn\Runtime\Runtime;

function get_home_url($blog_id = null, $path = '', $scheme = null)
{
    $original = $scheme;
    $url = (string) get_option('home');
    if (!in_array($scheme, ['http', 'https', 'relative'], true)) {
        $scheme = is_ssl() ? 'https' : (parse_url($url, PHP_URL_SCHEME) ?: 'http');
    }
    $url = set_url_scheme($url, $scheme);
    if ($path !== '' && is_string($path) && !str_contains($path, '..')) {
        $url .= '/' . ltrim($path, '/');
    }
    return apply_filters('home_url', $url, $path, $original, $blog_id);
}

function home_url($path = '', $scheme = null)
{
    return get_home_url(null, $path, $scheme);
}

function get_site_url($blog_id = null, $path = '', $scheme = null)
{
    $url = set_url_scheme((string) get_option('siteurl'), $scheme);
    if ($path !== '' && is_string($path) && !str_contains($path, '..')) {
        $url .= '/' . ltrim($path, '/');
    }
    return apply_filters('site_url', $url, $path, $scheme, $blog_id);
}

function site_url($path = '', $scheme = null)
{
    return get_site_url(null, $path, $scheme);
}

function get_admin_url($blog_id = null, $path = '', $scheme = 'admin')
{
    $url = get_site_url($blog_id, 'wp-admin/', $scheme);
    if ($path !== '' && is_string($path)) {
        $url .= ltrim($path, '/');
    }
    return apply_filters('admin_url', $url, $path, $blog_id, $scheme);
}

function admin_url($path = '', $scheme = 'admin')
{
    return get_admin_url(null, $path, $scheme);
}

function self_admin_url($path = '', $scheme = 'admin')
{
    return apply_filters('self_admin_url', admin_url($path, $scheme), $path, $scheme);
}

function network_admin_url($path = '', $scheme = 'admin')
{
    return admin_url($path, $scheme);
}

function user_admin_url($path = '', $scheme = 'admin')
{
    return admin_url($path, $scheme);
}

function network_site_url($path = '', $scheme = null)
{
    return site_url($path, $scheme);
}

function network_home_url($path = '', $scheme = null)
{
    return home_url($path, $scheme);
}

function content_url($path = '')
{
    $url = set_url_scheme(WP_CONTENT_URL);
    if ($path !== '' && is_string($path) && !str_contains($path, '..')) {
        $url .= '/' . ltrim($path, '/');
    }
    return apply_filters('content_url', $url, $path);
}

function includes_url($path = '', $scheme = null)
{
    $url = site_url('/' . WPINC . '/', $scheme);
    if ($path !== '' && is_string($path)) {
        $url .= ltrim($path, '/');
    }
    return apply_filters('includes_url', $url, $path, $scheme);
}

function wp_login_url($redirect = '', $force_reauth = false)
{
    $url = site_url('wp-login.php', 'login');
    if (!empty($redirect)) {
        $url = add_query_arg('redirect_to', urlencode((string) $redirect), $url);
    }
    if ($force_reauth) {
        $url = add_query_arg('reauth', '1', $url);
    }
    return apply_filters('login_url', $url, $redirect, $force_reauth);
}

function wp_logout_url($redirect = '')
{
    $args = [];
    if (!empty($redirect)) {
        $args['redirect_to'] = urlencode((string) $redirect);
    }
    $url = add_query_arg($args + ['action' => 'logout'], site_url('wp-login.php', 'login'));
    $url = wp_nonce_url($url, 'log-out');
    return apply_filters('logout_url', $url, $redirect);
}

function wp_registration_url()
{
    return apply_filters('register_url', site_url('wp-login.php?action=register', 'login'));
}

function wp_lostpassword_url($redirect = '')
{
    $args = ['action' => 'lostpassword'];
    if (!empty($redirect)) {
        $args['redirect_to'] = urlencode((string) $redirect);
    }
    return apply_filters('lostpassword_url', add_query_arg($args, network_site_url('wp-login.php', 'login')), $redirect);
}

function get_rest_url($blog_id = null, $path = '/', $scheme = 'rest')
{
    $path = (string) $path;
    if ($path === '') {
        $path = '/';
    }
    $path = '/' . ltrim($path, '/');
    $url = trailingslashit(get_home_url($blog_id, rest_get_url_prefix(), $scheme)) . ltrim($path, '/');
    if (is_ssl() && isset($_SERVER['SERVER_NAME'])) {
        // Nothing: the home scheme already matches the request in the engine.
    }
    return apply_filters('rest_url', $url, $path, $blog_id, $scheme);
}

function rest_url($path = '', $scheme = 'rest')
{
    return get_rest_url(null, $path, $scheme);
}

function rest_get_url_prefix()
{
    return apply_filters('rest_url_prefix', 'wp-json');
}

function get_theme_root($stylesheet_or_template = '')
{
    return apply_filters('theme_root', WP_CONTENT_DIR . '/themes');
}

function get_theme_root_uri($stylesheet_or_template = '', $theme_root = '')
{
    return apply_filters('theme_root_uri', content_url('themes'), get_theme_root(), $stylesheet_or_template);
}

function get_stylesheet()
{
    return apply_filters('stylesheet', (string) get_option('stylesheet'));
}

function get_template()
{
    return apply_filters('template', (string) get_option('template'));
}

function get_stylesheet_directory()
{
    $stylesheet = get_stylesheet();
    return apply_filters('stylesheet_directory', get_theme_root($stylesheet) . '/' . $stylesheet, $stylesheet, get_theme_root($stylesheet));
}

function get_stylesheet_directory_uri()
{
    $stylesheet = str_replace('%2F', '/', rawurlencode(get_stylesheet()));
    return apply_filters('stylesheet_directory_uri', get_theme_root_uri($stylesheet) . '/' . $stylesheet, $stylesheet, get_theme_root_uri($stylesheet));
}

function get_stylesheet_uri()
{
    return apply_filters('stylesheet_uri', get_stylesheet_directory_uri() . '/style.css', get_stylesheet_directory_uri());
}

function get_template_directory()
{
    $template = get_template();
    return apply_filters('template_directory', get_theme_root($template) . '/' . $template, $template, get_theme_root($template));
}

function get_template_directory_uri()
{
    $template = str_replace('%2F', '/', rawurlencode(get_template()));
    return apply_filters('template_directory_uri', get_theme_root_uri($template) . '/' . $template, $template, get_theme_root_uri($template));
}

function get_theme_file_uri($file = '')
{
    $file = ltrim((string) $file, '/');
    if ($file === '') {
        $url = get_stylesheet_directory_uri();
    } elseif (is_file(get_stylesheet_directory() . '/' . $file)) {
        $url = get_stylesheet_directory_uri() . '/' . $file;
    } else {
        $url = get_template_directory_uri() . '/' . $file;
    }
    return apply_filters('theme_file_uri', $url, $file);
}

function get_theme_file_path($file = '')
{
    $file = ltrim((string) $file, '/');
    if ($file === '') {
        $path = get_stylesheet_directory();
    } elseif (is_file(get_stylesheet_directory() . '/' . $file)) {
        $path = get_stylesheet_directory() . '/' . $file;
    } else {
        $path = get_template_directory() . '/' . $file;
    }
    return apply_filters('theme_file_path', $path, $file);
}

function get_bloginfo($show = '', $filter = 'raw')
{
    $output = match ($show) {
        'url', 'home', 'siteurl' => home_url(),
        'wpurl' => site_url(),
        'description' => (string) get_option('blogdescription'),
        'rdf_url' => get_feed_link('rdf'),
        'rss_url' => get_feed_link('rss'),
        'rss2_url' => get_feed_link('rss2'),
        'atom_url' => get_feed_link('atom'),
        'comments_atom_url' => get_feed_link('comments_atom'),
        'comments_rss2_url' => get_feed_link('comments_rss2'),
        'pingback_url' => site_url('xmlrpc.php'),
        'stylesheet_url' => get_stylesheet_uri(),
        'stylesheet_directory' => get_stylesheet_directory_uri(),
        'template_directory', 'template_url' => get_template_directory_uri(),
        'admin_email' => (string) get_option('admin_email'),
        'charset' => (string) (get_option('blog_charset') ?: 'UTF-8'),
        'html_type' => (string) (get_option('html_type') ?: 'text/html'),
        'version' => $GLOBALS['wp_version'],
        'language' => str_replace('_', '-', _minn_bloginfo_locale()),
        'text_direction' => is_rtl() ? 'rtl' : 'ltr',
        default => (string) get_option('blogname'),
    };
    if ($filter === 'display') {
        if (str_contains($show, 'url') || str_contains($show, 'directory') || str_contains($show, 'home')) {
            $output = apply_filters('bloginfo_url', $output, $show);
        } else {
            $output = apply_filters('bloginfo', $output, $show);
        }
    }
    return $output;
}

/** @internal the locale as language tags want it: ll_CC without the formal variants */
function _minn_bloginfo_locale(): string
{
    $locale = get_locale();
    return $locale === 'en' ? 'en_US' : $locale;
}

function bloginfo($show = '')
{
    echo get_bloginfo($show, 'display');
}

function get_feed_link($feed = '')
{
    $home = home_url();
    $feed = (string) $feed;
    $default = get_default_feed();
    if (str_starts_with($feed, 'comments_')) {
        $feed = substr($feed, 9);
        $suffix = $feed === $default ? '' : '/' . $feed;
        return apply_filters('feed_link', $home . '/comments/feed' . $suffix . '/', $feed);
    }
    $suffix = $feed === '' || $feed === $default ? '' : '/' . $feed;
    return apply_filters('feed_link', $home . '/feed' . $suffix . '/', $feed);
}

function get_default_feed()
{
    $feed = apply_filters('default_feed', 'rss2');
    return $feed === 'rss' ? 'rss2' : $feed;
}

function wp_customize_url($stylesheet = '')
{
    $url = admin_url('customize.php');
    if ($stylesheet !== '') {
        $url .= '?theme=' . urlencode((string) $stylesheet);
    }
    return esc_url($url);
}

function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false)
{
    $dir = wp_get_upload_dir();
    if ($create_dir && $dir['error'] === false) {
        wp_mkdir_p($dir['path']);
    }
    return $dir;
}

function wp_get_upload_dir()
{
    return _wp_upload_dir();
}

function _wp_upload_dir($time = null)
{
    $layout = Uploads::layout((string) get_option('upload_path'), (string) get_option('upload_url_path'), (string) get_option('siteurl'), WP_CONTENT_DIR, WP_CONTENT_URL, ABSPATH, (bool) get_option('uploads_use_yearmonth_folders'), (string) ($time ?? current_time('mysql')));
    return apply_filters('upload_dir', $layout);
}








function get_search_link($query = '')
{
    $search = $query === '' ? get_search_query(false) : stripslashes((string) $query);
    if (get_option('permalink_structure') === '') {
        $link = home_url('?s=' . urlencode($search));
    } else {
        $search = str_replace('/', '%2F', urlencode($search));
        $link = home_url(user_trailingslashit('/search/' . $search, 'search'));
    }
    return apply_filters('search_link', $link, $search);
}

function get_pagenum_link($pagenum = 1, $escape = true)
{
    $url = home_url('/');
    if ((int) $pagenum > 1) {
        $url .= 'page/' . (int) $pagenum . '/';
    }
    return $escape ? esc_url($url) : esc_url_raw($url);
}

function get_edit_term_link($term, $taxonomy = '', $object_type = '')
{
    $term = get_term($term, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return null;
    }
    $tax = get_taxonomy($term->taxonomy);
    if (!$tax || !current_user_can('edit_term', $term->term_id)) {
        return null;
    }
    $args = ['taxonomy' => $tax->name, 'tag_ID' => $term->term_id];
    if ($object_type) {
        $args['post_type'] = $object_type;
    } elseif (!empty($tax->object_type)) {
        $args['post_type'] = reset($tax->object_type);
    }
    $location = add_query_arg($args, admin_url('term.php'));
    return apply_filters('get_edit_term_link', $location, $term->term_id, $tax->name, $object_type);
}

function get_edit_user_link($user_id = null)
{
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    if (empty($user_id) || !current_user_can('edit_user', $user_id)) {
        return '';
    }
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }
    if (get_current_user_id() === $user->ID) {
        $link = get_edit_profile_url($user->ID);
    } else {
        $link = add_query_arg('user_id', $user->ID, self_admin_url('user-edit.php'));
    }
    return apply_filters('get_edit_user_link', $link, $user->ID);
}

function get_edit_profile_url($user_id = 0, $scheme = 'admin')
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (get_current_user_id() === $user_id) {
        $url = get_dashboard_url($user_id, 'profile.php', $scheme);
    } else {
        $url = add_query_arg('user_id', $user_id, get_dashboard_url($user_id, 'user-edit.php', $scheme));
    }
    return apply_filters('edit_profile_url', $url, $user_id, $scheme);
}

function get_dashboard_url($user_id = 0, $path = '', $scheme = 'admin')
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $url = admin_url($path ? $path : '', $scheme);
    return apply_filters('user_dashboard_url', $url, $user_id, $path, $scheme);
}

/** Numbered page links; base and format come from the main query's page link unless given. */
function paginate_links($args = '')
{
    $pagenum_link = html_entity_decode(get_pagenum_link());
    $url_parts = explode('?', $pagenum_link, 2);
    $pretty = $GLOBALS['wp_rewrite']->using_permalinks();
    $defaults = ['base' => trailingslashit($url_parts[0]) . '%_%', 'format' => $pretty ? user_trailingslashit('page/%#%', 'paged') : '?paged=%#%', 'total' => (int) ($GLOBALS['wp_query']->max_num_pages ?? 1), 'current' => max(1, (int) get_query_var('paged')), 'aria_current' => 'page', 'show_all' => false, 'prev_next' => true, 'prev_text' => '&laquo; Previous', 'next_text' => 'Next &raquo;', 'end_size' => 1, 'mid_size' => 2, 'type' => 'plain', 'add_args' => [], 'add_fragment' => '', 'before_page_number' => '', 'after_page_number' => ''];
    $args = wp_parse_args($args, $defaults);
    if (!is_array($args['add_args'])) {
        $args['add_args'] = [];
    }
    if (isset($url_parts[1]) && !isset($GLOBALS['minn_paginate_base_given'])) {
        $format_query = parse_url(str_replace('%_%', $args['format'], $args['base']), PHP_URL_QUERY);
        wp_parse_str((string) $format_query, $format_args);
        wp_parse_str($url_parts[1], $url_query_args);
        foreach (array_keys($format_args) as $key) {
            unset($url_query_args[$key]);
        }
        $args['add_args'] = array_merge($args['add_args'], urlencode_deep($url_query_args));
    }
    $link = static function (int $n) use ($args): string {
        $url = str_replace('%_%', $n === 1 ? '' : $args['format'], $args['base']);
        $url = str_replace('%#%', (string) $n, $url);
        if ($args['add_args'] !== []) {
            $url = add_query_arg($args['add_args'], $url);
        }
        return esc_url(apply_filters('paginate_links', $url . $args['add_fragment']));
    };
    $links = Pagination::links($args, $link);
    if ($links === null) {
        return null;
    }
    return Pagination::format($links, (string) $args['type']);
}
