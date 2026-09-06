<?php
/** Template loading and the small template tags. */

use Minn\Content\Posts;
use Minn\Front\Calendar;
use Minn\Front\CalendarLabels;
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
        // The engine's own theme-compat stands where the reference's wp-includes/theme-compat would.
        foreach ([get_stylesheet_directory(), get_template_directory(), MINN_ENGINE_DIR . '/wp-api/theme-compat'] as $dir) {
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
    // A theme's own searchform.php wins over the default form, as the reference loads it.
    $template = locate_template(['searchform.php']);
    if ($template !== '') {
        ob_start();
        load_template($template, false, $args);
        $form = (string) ob_get_clean();
    } else {
        $form = '<form role="search" method="get" class="search-form" action="' . esc_url(home_url('/')) . '"><label><span class="screen-reader-text">Search for:</span><input type="search" class="search-field" placeholder="Search &hellip;" value="' . get_search_query() . '" name="s" /></label><input type="submit" class="search-submit" value="Search" /></form>';
    }
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
    $args['loading'] ??= wp_get_loading_optimization_attributes('img', ['width' => (int) $args['size'], 'height' => (int) $args['size']], 'get_avatar')['loading'] ?? null;
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

/** The theme file for a template type (through the hierarchy and type filters); under a block theme the canvas stands in. */
function get_query_template($type, $templates = [])
{
    $type = (string) preg_replace('|[^a-z0-9-]+|', '', (string) $type);
    if ($templates === []) {
        $templates = ["{$type}.php"];
    }
    $templates = apply_filters("{$type}_template_hierarchy", $templates);
    $template = locate_template($templates);
    if ($template === '' && Runtime::current()->get('block_theme', false) && _minn_block_template_exists($templates)) {
        $template = MINN_ENGINE_DIR . '/wp-api/template-canvas.php';
    }
    return apply_filters("{$type}_template", $template, $type, $templates);
}

function get_index_template()
{
    return get_query_template('index');
}

function get_404_template()
{
    return get_query_template('404');
}

function get_search_template()
{
    return get_query_template('search');
}

function get_front_page_template()
{
    return get_query_template('front_page', \Minn\Theme\Hierarchy::frontPage());
}

function get_home_template()
{
    return get_query_template('home', \Minn\Theme\Hierarchy::home());
}

function get_privacy_policy_template()
{
    return get_query_template('privacy_policy', \Minn\Theme\Hierarchy::privacyPolicy());
}

function get_singular_template()
{
    return get_query_template('singular');
}

function get_page_template()
{
    $id = (int) get_queried_object_id();
    $post = get_post($id);
    $slug = (string) ($post->post_name ?? get_query_var('pagename'));
    return get_query_template('page', \Minn\Theme\Hierarchy::page((string) get_page_template_slug($post), $slug, $id));
}

function get_single_template()
{
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return get_query_template('single', ['single.php']);
    }
    return get_query_template('single', \Minn\Theme\Hierarchy::single((string) $post->post_type, (string) $post->post_name, (string) get_page_template_slug($post)));
}

function get_attachment_template()
{
    $post = get_queried_object();
    $mime = $post instanceof WP_Post ? (string) $post->post_mime_type : '';
    return get_query_template('attachment', \Minn\Theme\Hierarchy::attachment($mime));
}

function get_category_template()
{
    $term = get_queried_object();
    $templates = $term instanceof WP_Term ? \Minn\Theme\Hierarchy::term('category', (string) $term->slug, (int) $term->term_id) : ['category.php'];
    return get_query_template('category', $templates);
}

function get_tag_template()
{
    $term = get_queried_object();
    $templates = $term instanceof WP_Term ? \Minn\Theme\Hierarchy::term('post_tag', (string) $term->slug, (int) $term->term_id) : ['tag.php'];
    return get_query_template('tag', $templates);
}

function get_taxonomy_template()
{
    $term = get_queried_object();
    $templates = $term instanceof WP_Term ? \Minn\Theme\Hierarchy::term((string) $term->taxonomy, (string) $term->slug, (int) $term->term_id) : ['taxonomy.php'];
    return get_query_template('taxonomy', $templates);
}

function get_author_template()
{
    $author = get_queried_object();
    $templates = $author instanceof WP_User
        ? \Minn\Theme\Hierarchy::author((string) $author->user_nicename, (int) $author->ID)
        : \Minn\Theme\Hierarchy::author((string) get_query_var('author_name'), (int) get_query_var('author'));
    return get_query_template('author', $templates);
}

function get_date_template()
{
    return get_query_template('date');
}

function get_archive_template()
{
    $types = array_values(array_filter(array_map('strval', (array) get_query_var('post_type'))));
    return get_query_template('archive', \Minn\Theme\Hierarchy::archive($types));
}

function get_post_type_archive_template()
{
    $type = (array) get_query_var('post_type');
    $object = get_post_type_object((string) reset($type));
    if ($object !== null && empty($object->has_archive)) {
        return '';
    }
    return get_archive_template();
}

/** @internal the reference's wp_head defaults, registered ahead of the classic theme's own hooks; a plugin's remove_action() finds them by name */
function _minn_classic_head_defaults()
{
    if (!Runtime::current()->get('classic_theme')) {
        return;
    }
    add_action('wp_head', '_wp_render_title_tag', 1, 0);
    add_action('wp_head', 'wp_robots', 1, 0);
    add_action('wp_head', 'wp_resource_hints', 2, 0);
    add_action('wp_head', 'feed_links', 2, 1);
    add_action('wp_head', 'feed_links_extra', 3, 1);
    // Registered at 4 AND 10 like the reference; the function prints once.
    add_action('wp_head', 'wp_oembed_add_discovery_links', 4, 0);
    add_action('wp_head', 'rest_output_link_wp_head', 10, 0);
    add_action('wp_head', 'rsd_link', 10, 0);
    add_action('wp_head', 'wp_generator', 10, 0);
    add_action('wp_head', 'rel_canonical', 10, 0);
    add_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    add_action('wp_head', 'wp_oembed_add_discovery_links', 10, 0);
    add_action('wp_head', 'wp_site_icon', 99, 0);
    add_action('wp_enqueue_scripts', '_minn_enqueue_auto_sizes_style', 0);
    add_action('wp_head', '_minn_classic_bar_head', 200, 0);
    add_action('wp_footer', '_minn_classic_bar_footer', 200, 0);
}

/** @internal the Minn front bar's head assets on a classic theme's page */
function _minn_classic_bar_head()
{
    $bar = Runtime::current()->get('classic_bar');
    if ($bar instanceof \Minn\Front\AdminBar) {
        echo $bar->head();
    }
}

/** @internal the Minn front bar itself, printed from the theme's wp_footer() */
function _minn_classic_bar_footer()
{
    $bar = Runtime::current()->get('classic_bar');
    $resolution = Runtime::current()->get('classic_resolution');
    if ($bar instanceof \Minn\Front\AdminBar && $resolution instanceof \Minn\Front\Resolution) {
        echo $bar->render($resolution);
    }
}

/** Speculative loading for signed-out visitors under pretty permalinks: conservative prefetch away from the WordPress paths. */
function wp_print_speculation_rules()
{
    if (!Runtime::current()->get('classic_theme') || is_user_logged_in() || !$GLOBALS['wp_rewrite']->using_permalinks()) {
        return;
    }
    $exclude = ['/wp-*.php', '/wp-admin/*', '/wp-content/uploads/*', '/wp-content/*', '/wp-content/plugins/*'];
    foreach (array_unique([get_stylesheet(), get_template()]) as $slug) {
        $exclude[] = '/wp-content/themes/' . $slug . '/*';
    }
    $exclude[] = '/*\\?(.+)';
    $rules = ['prefetch' => [[
        'source' => 'document',
        'where' => ['and' => [
            ['href_matches' => '/*'],
            ['not' => ['href_matches' => $exclude]],
            ['not' => ['selector_matches' => 'a[rel~="nofollow"]']],
            ['not' => ['selector_matches' => '.no-prefetch, .no-prefetch a']],
        ]],
        'eagerness' => 'conservative',
    ]]];
    echo "<script type=\"speculationrules\">\n" . json_encode($rules, JSON_UNESCAPED_SLASHES) . "\n</script>\n";
}

/** @internal the reference's auto-sizes containment style, first in the queue */
function _minn_enqueue_auto_sizes_style()
{
    wp_register_style('wp-img-auto-sizes-contain', false, [], false);
    wp_add_inline_style('wp-img-auto-sizes-contain', 'img:is([sizes=auto i],[sizes^="auto," i]){contain-intrinsic-size:3000px 1500px}');
    wp_enqueue_style('wp-img-auto-sizes-contain');
}

/** The reference renders the title tag itself when the theme declares title-tag support. */
function _wp_render_title_tag()
{
    if (!current_theme_supports('title-tag')) {
        return;
    }
    echo '<title>' . wp_get_document_title() . '</title>' . "\n";
}

function feed_links($args = [])
{
    $head = Runtime::current()->get('classic_head');
    if ($head instanceof \Minn\Theme\HeadLinks && current_theme_supports('automatic-feed-links')) {
        echo $head->feedLinks();
    }
}

function feed_links_extra($args = [])
{
    $head = Runtime::current()->get('classic_head');
    $resolution = Runtime::current()->get('classic_resolution');
    if ($head instanceof \Minn\Theme\HeadLinks && $resolution instanceof \Minn\Front\Resolution && current_theme_supports('automatic-feed-links')) {
        echo $head->extraFeedLink($resolution);
    }
}

/**
 * Resource hints: dns-prefetch for the hosts of enqueued assets away from
 * the page's own host, plus what the wp_resource_hints filter adds
 * (preconnect entries print their full URL, the reference's shape).
 */
function wp_resource_hints()
{
    $own = (string) (Runtime::current()->request?->host ?? '');
    $hints = ['dns-prefetch' => array_values(array_unique([..._minn_assets('script')->externalHosts($own), ..._minn_assets('style')->externalHosts($own)])), 'preconnect' => []];
    foreach ($hints as $relation => $urls) {
        $urls = apply_filters('wp_resource_hints', $urls, $relation);
        $unique = [];
        foreach ($urls as $url) {
            $attrs = is_array($url) ? $url : ['href' => $url];
            $href = (string) ($attrs['href'] ?? '');
            if ($href === '' || isset($unique[$href])) {
                continue;
            }
            $unique[$href] = true;
            if ($relation === 'dns-prefetch') {
                $host = (string) (parse_url($href, PHP_URL_HOST) ?: $href);
                echo "<link rel='dns-prefetch' href='//" . esc_attr(ltrim($host, '/')) . "' />\n";
                continue;
            }
            $extra = '';
            foreach ($attrs as $name => $value) {
                if ($name === 'href') {
                    continue;
                }
                $extra .= is_int($name) ? ' ' . esc_attr((string) $value) : ' ' . esc_attr((string) $name) . "='" . esc_attr((string) $value) . "'";
            }
            echo "<link href='" . esc_url($href) . "'" . $extra . " rel='" . esc_attr($relation) . "' />\n";
        }
    }
}

function wp_site_icon()
{
    $head = Runtime::current()->get('classic_head');
    if ($head instanceof \Minn\Theme\HeadLinks) {
        echo $head->icons();
    }
}

/**
 * The reference registers this on wp_head at BOTH 4 and 10 and prints once
 * (the discovery links appear ahead of the styles); the guard keeps the
 * second firing quiet while a plugin's remove_action() at either priority
 * still finds a registration.
 */
function wp_oembed_add_discovery_links()
{
    if (!is_singular() || Runtime::current()->get('oembed_discovery_printed')) {
        return;
    }
    Runtime::current()->set('oembed_discovery_printed', true);
    $permalink = (string) get_permalink();
    $base = home_url('/wp-json/oembed/1.0/embed');
    $output = '<link rel="alternate" title="oEmbed (JSON)" type="application/json+oembed" href="' . esc_url($base . '?url=' . urlencode($permalink)) . '" />' . "\n";
    $output .= '<link rel="alternate" title="oEmbed (XML)" type="text/xml+oembed" href="' . esc_url($base . '?url=' . urlencode($permalink) . '&format=xml') . '" />' . "\n";
    echo apply_filters('oembed_discovery_links', $output);
}

/** The Really Simple Discovery link the reference prints from wp_head. */
function rsd_link()
{
    $head = Runtime::current()->get('classic_head');
    if ($head instanceof \Minn\Theme\HeadLinks) {
        echo $head->rsdLink();
    }
}

function wp_generator()
{
    the_generator('xhtml');
}

function the_generator($type)
{
    echo apply_filters('the_generator', get_the_generator($type), $type) . "\n";
}

function get_the_generator($type = '')
{
    $version = (string) ($GLOBALS['wp_version'] ?? '');
    $gen = match ($type) {
        'atom' => '<generator uri="https://wordpress.org/" version="' . esc_attr($version) . '">WordPress</generator>',
        'rss2' => '<generator>' . esc_url_raw('https://wordpress.org/?v=' . $version) . '</generator>',
        'comment' => '<!-- generator="WordPress/' . esc_attr($version) . '" -->',
        default => '<meta name="generator" content="WordPress ' . esc_attr($version) . '" />',
    };
    return apply_filters("get_the_generator_{$type}", $gen, $type);
}

/** @internal whether the theme (or its parent) ships a block template for any of the PHP names given */
function _minn_block_template_exists(array $templates): bool
{
    foreach ($templates as $name) {
        $slug = basename((string) $name, '.php');
        foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $dir) {
            if (is_file("{$dir}/templates/{$slug}.html")) {
                return true;
            }
        }
    }
    return false;
}

function get_calendar($args = [])
{
    // The older form, get_calendar($initial, $display), still answers.
    if (!is_array($args)) {
        $legacy = func_get_args();
        $args = ['initial' => (bool) $legacy[0], 'display' => $legacy[1] ?? true];
    }
    $args = wp_parse_args($args, ['initial' => true, 'display' => true, 'post_type' => 'post']);
    [$year, $month] = _minn_calendar_month();
    $output = apply_filters('get_calendar', _minn_calendar($year, $month, (bool) $args['initial'], (string) $args['post_type']), $args);
    if ($args['display']) {
        echo $output;
        return null;
    }
    return $output;
}

/** @internal the month the calendar shows: the query's, else the site's current one */
function _minn_calendar_month(): array
{
    $query = $GLOBALS['wp_query'] ?? null;
    $m = (string) ($GLOBALS['m'] ?? ($query instanceof WP_Query ? $query->get('m') : ''));
    $year = (int) ($GLOBALS['year'] ?? ($query instanceof WP_Query ? $query->get('year') : 0));
    $month = (int) ($GLOBALS['monthnum'] ?? ($query instanceof WP_Query ? $query->get('monthnum') : 0));
    if (strlen($m) >= 6) {
        return [(int) substr($m, 0, 4), (int) substr($m, 4, 2)];
    }
    if ($year > 0 && $month > 0) {
        return [$year, $month];
    }
    if ($year > 0) {
        return [$year, (int) current_time('m')];
    }
    return [(int) current_time('Y'), (int) current_time('m')];
}

/** @internal the rendered month, from the site's posts and locale */
function _minn_calendar(int $year, int $month, bool $initial, string $type): string
{
    $locale = $GLOBALS['wp_locale'] ?? new WP_Locale();
    $short = $initial ? $locale->weekday_initial : $locale->weekday_abbrev;
    $months = [];
    foreach ($locale->month as $number => $name) {
        $months[(int) $number] = $name;
    }
    $labels = new CalendarLabels($locale->weekday, $short, $months, $locale->month_abbrev);
    $link = static fn (int $y, int $m, ?int $d): string => $d === null ? get_month_link($y, $m) : get_day_link($y, $m, $d);
    $posts = new Posts(Runtime::current()->db);
    $today = explode('-', current_time('Y-m-d'));
    return (new Calendar($year, $month, (int) get_option('start_of_week'), $labels, $link))->render(
        $posts->daysWithPosts($year, $month, $type),
        $posts->monthBefore($year, $month, $type),
        $posts->monthAfter($year, $month, $type),
        [(int) $today[0], (int) $today[1], (int) $today[2]],
    );
}
