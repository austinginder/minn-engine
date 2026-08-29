<?php
/** URLs of the site and its pieces. */

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
    $siteurl = (string) get_option('siteurl');
    $uploadPath = trim((string) get_option('upload_path'));
    if ($uploadPath === '' || $uploadPath === 'wp-content/uploads') {
        $dir = WP_CONTENT_DIR . '/uploads';
    } elseif (!str_starts_with($uploadPath, ABSPATH)) {
        $dir = rtrim(ABSPATH, '/') . '/' . $uploadPath;
    } else {
        $dir = $uploadPath;
    }
    $url = (string) get_option('upload_url_path');
    if ($url === '') {
        $url = $uploadPath === '' || $uploadPath === 'wp-content/uploads' || $uploadPath === $dir ? WP_CONTENT_URL . '/uploads' : trailingslashit($siteurl) . $uploadPath;
    }
    $subdir = '';
    if (get_option('uploads_use_yearmonth_folders')) {
        $time ??= current_time('mysql');
        $subdir = '/' . substr((string) $time, 0, 4) . '/' . substr((string) $time, 5, 2);
    }
    return apply_filters('upload_dir', [
        'path' => $dir . $subdir,
        'url' => $url . $subdir,
        'subdir' => $subdir,
        'basedir' => $dir,
        'baseurl' => $url,
        'error' => false,
    ]);
}

function get_permalink($post = 0, $leavename = false)
{
    $post = get_post($post);
    if ($post === null) {
        return false;
    }
    return apply_filters('post_link', Runtime::current()->get('permalinks')?->forPost($post->to_array()) ?? home_url('/?p=' . $post->ID), $post, $leavename);
}

function get_the_permalink($post = 0, $leavename = false)
{
    return get_permalink($post, $leavename);
}

function the_permalink($post = 0)
{
    echo esc_url(apply_filters('the_permalink', get_permalink($post), $post));
}

function get_edit_post_link($post = 0, $context = 'display')
{
    $post = get_post($post);
    if ($post === null || !current_user_can('edit_post', $post->ID)) {
        return null;
    }
    $sep = $context === 'display' ? '&amp;' : '&';
    return apply_filters('get_edit_post_link', admin_url("post.php?post={$post->ID}{$sep}action=edit"), $post->ID, $context);
}

function wp_get_shortlink($id = 0, $context = 'post', $allow_slugs = true)
{
    $post = get_post($id);
    return $post === null ? '' : home_url('?p=' . $post->ID);
}

function get_avatar_url($id_or_email, $args = null)
{
    return false;
}

function get_avatar($id_or_email, $size = 96, $default_value = '', $alt = '', $args = null)
{
    return false;
}

function get_search_link($query = '')
{
    return apply_filters('search_link', home_url('/?s=' . urlencode((string) $query)), $query);
}

function get_pagenum_link($pagenum = 1, $escape = true)
{
    $url = home_url('/');
    if ((int) $pagenum > 1) {
        $url .= 'page/' . (int) $pagenum . '/';
    }
    return $escape ? esc_url($url) : esc_url_raw($url);
}
