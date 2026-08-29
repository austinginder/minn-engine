<?php
/** Template loading and the small template tags. */

use Minn\Runtime\Runtime;
use Minn\Runtime\Avatar;

function wp_head()
{
    do_action('wp_head');
}

function wp_footer()
{
    do_action('wp_footer');
}

function wp_body_open()
{
    do_action('wp_body_open');
}

function get_language_attributes($doctype = 'html')
{
    $attributes = [];
    if (function_exists('is_rtl') && is_rtl()) {
        $attributes[] = 'dir="rtl"';
    }
    $lang = get_bloginfo('language');
    if ($lang) {
        if (get_option('html_type') === 'text/html' || $doctype === 'html') {
            $attributes[] = 'lang="' . esc_attr($lang) . '"';
        }
        if (get_option('html_type') !== 'text/html' || $doctype === 'xhtml') {
            $attributes[] = 'xml:lang="' . esc_attr($lang) . '"';
        }
    }
    return apply_filters('language_attributes', implode(' ', $attributes), $doctype);
}

function language_attributes($doctype = 'html')
{
    echo get_language_attributes($doctype);
}

function user_trailingslashit($url, $type_of_url = '')
{
    $structure = (string) get_option('permalink_structure');
    $url = str_ends_with($structure, '/') ? trailingslashit($url) : untrailingslashit($url);
    return apply_filters('user_trailingslashit', $url, $type_of_url);
}

function get_year_link($year)
{
    $year = $year === false || $year === '' || $year === null ? current_time('Y') : (int) $year;
    $permalinks = Runtime::current()->get('permalinks');
    $link = $permalinks !== null && $permalinks->isPretty() ? home_url(user_trailingslashit('/' . $year, 'year')) : home_url('?m=' . $year);
    return apply_filters('year_link', $link, $year);
}

function get_month_link($year, $month)
{
    $year = $year === false || $year === '' || $year === null ? current_time('Y') : (int) $year;
    $month = $month === false || $month === '' || $month === null ? current_time('m') : (int) $month;
    $permalinks = Runtime::current()->get('permalinks');
    $link = $permalinks !== null && $permalinks->isPretty() ? home_url(user_trailingslashit('/' . $year . '/' . zeroise($month, 2), 'month')) : home_url('?m=' . $year . zeroise($month, 2));
    return apply_filters('month_link', $link, $year, $month);
}

function get_day_link($year, $month, $day)
{
    $year = $year === false || $year === '' || $year === null ? current_time('Y') : (int) $year;
    $month = $month === false || $month === '' || $month === null ? current_time('m') : (int) $month;
    $day = $day === false || $day === '' || $day === null ? current_time('j') : (int) $day;
    $permalinks = Runtime::current()->get('permalinks');
    $link = $permalinks !== null && $permalinks->isPretty() ? home_url(user_trailingslashit('/' . $year . '/' . zeroise($month, 2) . '/' . zeroise($day, 2), 'day')) : home_url('?m=' . $year . zeroise($month, 2) . zeroise($day, 2));
    return apply_filters('day_link', $link, $year, $month, $day);
}

function locate_template($template_names, $load = false, $load_once = true, $args = [])
{
    $located = '';
    foreach ((array) $template_names as $template_name) {
        if (!$template_name) {
            continue;
        }
        foreach ([get_stylesheet_directory(), get_template_directory(), ABSPATH . WPINC . '/theme-compat'] as $dir) {
            if (file_exists($dir . '/' . $template_name)) {
                $located = $dir . '/' . $template_name;
                break 2;
            }
        }
    }
    if ($load && $located !== '') {
        load_template($located, $load_once, $args);
    }
    return $located;
}

function load_template($_template_file, $load_once = true, $args = [])
{
    global $posts, $post, $wp_did_header, $wp_query, $wp_rewrite, $wpdb, $wp_version, $wp, $id, $comment, $user_ID;
    if (is_array($wp_query->query_vars ?? null)) {
        extract($wp_query->query_vars, EXTR_SKIP);
    }
    if (isset($s)) {
        $s = esc_attr($s);
    }
    do_action('wp_before_load_template', $_template_file, $load_once, $args);
    if ($load_once) {
        require_once $_template_file;
    } else {
        require $_template_file;
    }
    do_action('wp_after_load_template', $_template_file, $load_once, $args);
}

function get_template_part($slug, $name = null, $args = [])
{
    do_action("get_template_part_{$slug}", $slug, $name, $args);
    $templates = [];
    if ($name !== null && $name !== '') {
        $templates[] = "{$slug}-{$name}.php";
    }
    $templates[] = "{$slug}.php";
    do_action('get_template_part', $slug, $name, $templates, $args);
    if (!locate_template($templates, true, false, $args)) {
        return false;
    }
}

function get_header($name = null, $args = [])
{
    do_action('get_header', $name, $args);
    $templates = [];
    if ($name !== null && $name !== '') {
        $templates[] = "header-{$name}.php";
    }
    $templates[] = 'header.php';
    if (!locate_template($templates, true, true, $args)) {
        return false;
    }
}

function get_footer($name = null, $args = [])
{
    do_action('get_footer', $name, $args);
    $templates = [];
    if ($name !== null && $name !== '') {
        $templates[] = "footer-{$name}.php";
    }
    $templates[] = 'footer.php';
    if (!locate_template($templates, true, true, $args)) {
        return false;
    }
}

function get_sidebar($name = null, $args = [])
{
    do_action('get_sidebar', $name, $args);
    $templates = [];
    if ($name !== null && $name !== '') {
        $templates[] = "sidebar-{$name}.php";
    }
    $templates[] = 'sidebar.php';
    if (!locate_template($templates, true, true, $args)) {
        return false;
    }
}

function get_search_form($args = [])
{
    $args = wp_parse_args($args, ['echo' => true, 'aria_label' => '']);
    $form = '<form role="search" method="get" class="search-form" action="' . esc_url(home_url('/')) . '"><label><span class="screen-reader-text">Search for:</span><input type="search" class="search-field" placeholder="Search &hellip;" value="' . get_search_query() . '" name="s" /></label><input type="submit" class="search-submit" value="Search" /></form>';
    $form = apply_filters('get_search_form', $form, $args);
    if ($args['echo']) {
        echo $form;
        return null;
    }
    return $form;
}

function get_upload_iframe_src($type = null, $post_id = null, $tab = null)
{
    $post_id = $post_id ?? get_the_ID();
    $upload_iframe_src = add_query_arg('post_id', (int) $post_id, admin_url('media-upload.php'));
    if ($type && $type !== 'media') {
        $upload_iframe_src = add_query_arg('type', $type, $upload_iframe_src);
    }
    if (!empty($tab)) {
        $upload_iframe_src = add_query_arg('tab', $tab, $upload_iframe_src);
    }
    $upload_iframe_src = apply_filters("{$type}_upload_iframe_src", $upload_iframe_src);
    return add_query_arg('TB_iframe', true, $upload_iframe_src);
}

function add_thickbox()
{
    wp_register_script('thickbox', includes_url('js/thickbox/thickbox.js'), ['jquery'], '3.1-20121105');
    wp_register_style('thickbox', includes_url('js/thickbox/thickbox.css'), [], '3.1-20121105');
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');
}

function wp_get_inline_script_tag($data, $attributes = [])
{
    $is_html5 = current_theme_supports('html5', 'script') || is_admin();
    if (!isset($attributes['type']) && !$is_html5) {
        $attributes = ['type' => 'text/javascript'] + $attributes;
    }
    $attributes = apply_filters('wp_inline_script_attributes', $attributes, $data);
    $data = str_replace('</script>', '<\/script>', trim((string) $data, "\n\r "));
    return sprintf("<script%s>\n%s\n</script>\n", wp_sanitize_script_attributes($attributes), $data);
}

function wp_print_inline_script_tag($data, $attributes = [])
{
    echo wp_get_inline_script_tag($data, $attributes);
}

function wp_sanitize_script_attributes($attributes)
{
    $html5 = current_theme_supports('html5', 'script') || is_admin();
    $out = '';
    $booleans = [];
    $others = [];
    foreach ((array) $attributes as $name => $value) {
        if (is_bool($value)) {
            if ($value) {
                $booleans[$name] = $name;
            }
        } else {
            $others[$name] = $value;
        }
    }
    foreach ($booleans as $name) {
        $out .= ' ' . $name;
    }
    foreach ($others as $name => $value) {
        if ($name === 'type' && $html5 && $value === 'text/javascript') {
            continue;
        }
        $out .= sprintf(' %1$s="%2$s"', esc_attr((string) $name), esc_attr((string) $value));
    }
    return $out;
}

function wp_get_script_tag($attributes)
{
    return sprintf("<script%s></script>\n", wp_sanitize_script_attributes($attributes));
}

function wp_print_script_tag($attributes)
{
    echo wp_get_script_tag($attributes);
}

function wp_star_rating($args = [])
{
    $args = wp_parse_args($args, ['rating' => 0, 'type' => 'rating', 'number' => 0, 'echo' => true]);
    if ($args['type'] === 'percent') {
        $rating = round($args['rating'] / 10, 0) / 2;
    } else {
        $rating = round($args['rating'], 1);
    }
    $full = (int) floor($rating);
    $half = (int) ceil($rating - $full);
    $empty = 5 - $full - $half;
    if ($args['number']) {
        $title = sprintf('%1$s rating based on %2$s ratings', number_format_i18n($rating, 1), number_format_i18n($args['number']));
    } else {
        $title = sprintf('%s rating', number_format_i18n($rating, 1));
    }
    $output = '<div class="star-rating">';
    $output .= '<span class="screen-reader-text">' . $title . '</span>';
    $output .= str_repeat('<div class="star star-full" aria-hidden="true"></div>', $full);
    $output .= str_repeat('<div class="star star-half" aria-hidden="true"></div>', $half);
    $output .= str_repeat('<div class="star star-empty" aria-hidden="true"></div>', $empty);
    $output .= '</div>';
    if ($args['echo']) {
        echo $output;
    }
    return $output;
}

function get_avatar_data($id_or_email, $args = null)
{
    $args = Avatar::dataArgs(wp_parse_args($args, ['size' => 96, 'height' => null, 'width' => null, 'default' => get_option('avatar_default', 'mystery'), 'force_default' => false, 'rating' => get_option('avatar_rating', 'G'), 'scheme' => null, 'processed_args' => null, 'extra_attr' => '']));
    $args = apply_filters('pre_get_avatar_data', $args, $id_or_email);
    if (isset($args['url'])) {
        return apply_filters('get_avatar_data', $args, $id_or_email);
    }
    if (is_object($id_or_email) && isset($id_or_email->comment_ID)) {
        $id_or_email = get_comment($id_or_email);
    }
    [$user, $email, $hash] = _minn_avatar_subject($id_or_email);
    if ($user) {
        $email = $user->user_email;
    }
    $hash ??= ($email === '' && !$user) ? '' : Avatar::hash((string) $email);
    $args['found_avatar'] = $hash !== '';
    $url = sprintf('https://secure.gravatar.com/avatar/%s', $hash);
    if ($args['scheme'] !== null) {
        $url = set_url_scheme($url, $args['scheme']);
    }
    $args['url'] = apply_filters('get_avatar_url', add_query_arg(rawurlencode_deep(Avatar::urlArgs($args)), $url), $id_or_email, $args);
    return apply_filters('get_avatar_data', $args, $id_or_email);
}

/** @internal who an avatar is for: the user, the email, or a hash given outright */
function _minn_avatar_subject($id_or_email): array
{
    if (is_numeric($id_or_email)) {
        return [get_user_by('id', absint($id_or_email)), '', null];
    }
    if (is_string($id_or_email)) {
        $hash = Avatar::hashFromAddress($id_or_email);
        return [false, $hash === null ? $id_or_email : '', $hash];
    }
    if ($id_or_email instanceof WP_User) {
        return [$id_or_email, '', null];
    }
    if ($id_or_email instanceof WP_Post) {
        return [get_user_by('id', (int) $id_or_email->post_author), '', null];
    }
    if ($id_or_email instanceof WP_Comment) {
        return [(int) $id_or_email->user_id > 0 ? get_user_by('id', (int) $id_or_email->user_id) : false, (string) $id_or_email->comment_author_email, null];
    }
    return [false, '', null];
}

function get_avatar_url($id_or_email, $args = null)
{
    return get_avatar_data($id_or_email, $args)['url'];
}

function get_avatar($id_or_email, $size = 96, $default_value = '', $alt = '', $args = null)
{
    $args = wp_parse_args($args, []);
    $args += array_filter(['size' => $size, 'default' => $default_value, 'alt' => $alt], static fn ($v) => !empty($v));
    $args = wp_parse_args($args, ['size' => 96, 'height' => null, 'width' => null, 'default' => get_option('avatar_default', 'mystery'), 'force_default' => false, 'rating' => get_option('avatar_rating', 'G'), 'scheme' => null, 'alt' => '', 'class' => null, 'force_display' => false, 'loading' => null, 'fetchpriority' => null, 'decoding' => null, 'extra_attr' => '']);
    if (empty($args['default'])) {
        $args['default'] = get_option('avatar_default', 'mystery');
    }
    $args['loading'] ??= wp_lazy_loading_enabled('img', 'get_avatar') ? 'lazy' : null;
    $args['decoding'] ??= 'async';
    $args['height'] = $args['height'] ?: $args['size'];
    $args['width'] = $args['width'] ?: $args['size'];
    $avatar = apply_filters('pre_get_avatar', null, $id_or_email, $args);
    if ($avatar !== null) {
        return apply_filters('get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args);
    }
    if (!$args['force_display'] && !get_option('show_avatars')) {
        return false;
    }
    $data = get_avatar_data($id_or_email, $args + ['size' => $args['size']]);
    $url2x = get_avatar_url($id_or_email, array_merge($args, ['size' => $args['size'] * 2]));
    if (empty($data['url']) || is_wp_error($data['url'])) {
        return false;
    }
    $class = Avatar::classes((int) $args['size'], !$data['found_avatar'] || $args['force_default'], $args['class']);
    $extra = Avatar::extraAttributes((string) $args['extra_attr'], $args['loading'], $args['decoding']);
    $avatar = sprintf("<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>", esc_attr($args['alt']), esc_url($data['url']), esc_url($url2x) . ' 2x', esc_attr(implode(' ', $class)), (int) $args['height'], (int) $args['width'], $extra);
    return apply_filters('get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args);
}

function wp_make_link_relative($link)
{
    return preg_replace('|^(https?:)?//[^/]+(/?.*)|i', '$2', (string) $link);
}

function esc_xml($text)
{
    $safe = wp_check_invalid_utf8((string) $text);
    $safe = preg_replace_callback('/&([A-Za-z][A-Za-z0-9]*);/', static function (array $m): string {
        $name = $m[1];
        if (in_array($name, ['amp', 'lt', 'gt', 'quot', 'apos'], true)) {
            return '&' . $name . ';';
        }
        $decoded = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $decoded === $m[0] ? '&amp;' . $name . ';' : $decoded;
    }, $safe);
    $safe = preg_replace('/&(?!(?:amp|lt|gt|quot|apos|#\d+|#[xX][0-9a-fA-F]+);)/', '&amp;', $safe);
    $safe = str_replace(['<', '>', '"', "'"], ['&lt;', '&gt;', '&quot;', '&apos;'], $safe);
    return apply_filters('esc_xml', $safe, $text);
}

function sanitize_hex_color($color)
{
    if ($color === '' || $color === null) {
        return '';
    }
    if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color)) {
        return $color;
    }
    return null;
}

function sanitize_hex_color_no_hash($color)
{
    $color = ltrim((string) $color, '#');
    if ($color === '') {
        return '';
    }
    return sanitize_hex_color('#' . $color) ? $color : null;
}

function maybe_hash_hex_color($color)
{
    $unhashed = sanitize_hex_color_no_hash($color);
    return $unhashed ? '#' . $unhashed : $color;
}

function translate_user_role($name, $domain = 'default')
{
    return translate_with_gettext_context($name, 'User role', $domain);
}

function get_super_admins()
{
    return array_values(array_map(static fn (WP_User $u) => $u->user_login, get_users(['role' => 'administrator'])));
}

function get_blogs_of_user($user_id, $all = false)
{
    $user = get_userdata((int) $user_id);
    if (!$user) {
        return [];
    }
    $blogs = [1 => (object) ['userblog_id' => 1, 'blogname' => get_option('blogname'), 'domain' => '', 'path' => '', 'site_id' => 1, 'siteurl' => get_option('siteurl'), 'archived' => 0, 'spam' => 0, 'deleted' => 0]];
    return apply_filters('get_blogs_of_user', $blogs, (int) $user_id, $all);
}

function _wp_to_kebab_case($input_string)
{
    $string = (string) $input_string;
    $string = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $string);
    $string = preg_replace('/([A-Z]+)([A-Z][a-z\d]+)/', '$1-$2', $string);
    $string = preg_replace('/([a-zA-Z])(\d)/', '$1-$2', $string);
    $string = preg_replace('/(\d)([a-zA-Z])/', '$1-$2', $string);
    $string = preg_replace('/[\s_]+/', '-', $string);
    return strtolower($string);
}


