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

/** A page number's link on the CURRENT request: the request path with its page/N segment swapped, the query string kept. */
function get_pagenum_link($pagenum = 1, $escape = true)
{
    $pagenum = max(1, (int) $pagenum);
    $request = Runtime::booted() ? Runtime::current()->request : null;
    $path = (string) ($request?->path ?? '/');
    $path = (string) preg_replace('#/page/\d+/?$#', '/', $path);
    if ($path === '' || $path[0] !== '/') {
        $path = '/';
    }
    if ($pagenum > 1) {
        $path = trailingslashit($path) . user_trailingslashit('page/' . $pagenum, 'paged');
    }
    $query = array_diff_key($request?->query ?? [], ['paged' => 1, 'page' => 1]);
    $url = home_url($path) . ($query === [] ? '' : '?' . http_build_query($query));
    $result = apply_filters('get_pagenum_link', $url, $pagenum);
    return $escape ? esc_url($result) : esc_url_raw($result);
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
    $out = Pagination::format($links, (string) $args['type']);
    // WooCommerce decorates page numbers through this filter; array output skips it like the reference.
    return is_string($out) ? apply_filters('paginate_links_output', $out, $args) : $out;
}

/** The reference's navigation wrapper: nav, screen-reader heading, the links. */
function _navigation_markup($links, $css_class = 'posts-navigation', $screen_reader_text = '', $aria_label = '')
{
    if ($screen_reader_text === '') {
        $screen_reader_text = 'Posts navigation';
    }
    if ($aria_label === '') {
        $aria_label = $screen_reader_text;
    }
    $template = '
	<nav class="navigation %1$s" aria-label="%4$s">
		<h2 class="screen-reader-text">%2$s</h2>
		<div class="nav-links">%3$s</div>
	</nav>';
    $template = apply_filters('navigation_markup_template', $template, $css_class);
    return sprintf($template, sanitize_html_class($css_class), esc_html($screen_reader_text), $links, esc_attr($aria_label));
}

function get_the_posts_pagination($args = [])
{
    if ((int) ($GLOBALS['wp_query']->max_num_pages ?? 0) <= 1) {
        return '';
    }
    // A caller's screen_reader_text names the nav when no aria_label is given.
    if (isset($args['screen_reader_text']) && !isset($args['aria_label'])) {
        $args['aria_label'] = $args['screen_reader_text'];
    }
    $args = wp_parse_args($args, ['mid_size' => 1, 'prev_text' => 'Previous', 'next_text' => 'Next', 'screen_reader_text' => 'Posts pagination', 'aria_label' => 'Posts pagination', 'class' => 'pagination']);
    if (isset($args['type']) && $args['type'] === 'array') {
        $args['type'] = 'plain';
    }
    $links = paginate_links($args);
    if (!$links) {
        return '';
    }
    return _navigation_markup($links, $args['class'], $args['screen_reader_text'], $args['aria_label']);
}

function the_posts_pagination($args = [])
{
    echo get_the_posts_pagination($args);
}

/** The canonical link on singular views, at the queried object's own permalink. */
function rel_canonical()
{
    if (!is_singular()) {
        return;
    }
    $id = (int) get_queried_object_id();
    if ($id < 1) {
        return;
    }
    $url = (string) get_permalink($id);
    if ($url === '') {
        return;
    }
    $page = (int) get_query_var('page');
    if ($page >= 2) {
        $url = trailingslashit($url) . user_trailingslashit((string) $page, 'single_paged');
    }
    echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
}

/** The shortlink head tag on singular views, after the canonical in the reference's order. */
function wp_shortlink_wp_head()
{
    $head = Runtime::current()->get('classic_head');
    $resolution = Runtime::current()->get('classic_resolution');
    if ($head instanceof \Minn\Theme\HeadLinks && $resolution instanceof \Minn\Front\Resolution) {
        echo $head->shortlink($resolution);
    }
}

/**
 * The link to the post before or after this one, in the reference's
 * captured shape: the format wraps the link, `%link` is the anchor and
 * `%title` the post title, and the anchor carries rel="prev"/"next".
 * Empty when there is no such post.
 */
function get_adjacent_post_link($format, $link, $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category')
{
    $post = get_adjacent_post($in_same_term, $excluded_terms, $previous, $taxonomy);
    $adjacent = $previous ? 'previous' : 'next';
    if (!$post instanceof WP_Post) {
        $output = '';
    } else {
        $title = get_the_title($post);
        if ($title === '') {
            $title = $previous ? 'Previous Post' : 'Next Post';
        }
        $title = apply_filters('the_title', $title, $post->ID);
        $rel = $previous ? 'prev' : 'next';
        $anchor = '<a href="' . esc_url((string) get_permalink($post)) . '" rel="' . $rel . '">' . str_replace('%title', $title, $link) . '</a>';
        $output = str_replace('%link', $anchor, $format);
    }
    return apply_filters("{$adjacent}_post_link", $output, $format, $link, $post, $adjacent);
}

function get_previous_post_link($format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    return get_adjacent_post_link($format, $link, $in_same_term, $excluded_terms, true, $taxonomy);
}

function get_next_post_link($format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    return get_adjacent_post_link($format, $link, $in_same_term, $excluded_terms, false, $taxonomy);
}

function previous_post_link($format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    echo get_previous_post_link($format, $link, $in_same_term, $excluded_terms, $taxonomy);
}

function next_post_link($format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded_terms = '', $taxonomy = 'category')
{
    echo get_next_post_link($format, $link, $in_same_term, $excluded_terms, $taxonomy);
}

/** The nav block wrapping both adjacent links; empty when neither exists. */
function get_the_post_navigation($args = [])
{
    // Captured precedence: an explicit aria_label wins; otherwise a
    // caller-supplied screen_reader_text drives the label, and only with
    // neither does the default stand.
    if (is_array($args) && !empty($args['screen_reader_text']) && empty($args['aria_label'])) {
        $args['aria_label'] = $args['screen_reader_text'];
    }
    $args = wp_parse_args($args, [
        'prev_text' => '%title',
        'next_text' => '%title',
        'in_same_term' => false,
        'excluded_terms' => '',
        'taxonomy' => 'category',
        'screen_reader_text' => 'Post navigation',
        'aria_label' => 'Posts',
        'class' => 'post-navigation',
    ]);
    $previous = get_previous_post_link('<div class="nav-previous">%link</div>', $args['prev_text'], $args['in_same_term'], $args['excluded_terms'], $args['taxonomy']);
    $next = get_next_post_link('<div class="nav-next">%link</div>', $args['next_text'], $args['in_same_term'], $args['excluded_terms'], $args['taxonomy']);
    if ($previous === '' && $next === '') {
        return '';
    }
    return _navigation_markup($previous . $next, (string) $args['class'], (string) $args['screen_reader_text'], (string) $args['aria_label']);
}

function the_post_navigation($args = [])
{
    echo get_the_post_navigation($args);
}
