<?php
/** Template loading and the small template tags. */

use Minn\Content\Posts;
use Minn\Front\Calendar;
use Minn\Front\CalendarLabels;
use Minn\Front\Archives;
use Minn\Front\ListSpacing;
use Minn\Front\PageList;
use Minn\Runtime\Runtime;
use Minn\Runtime\Avatar;
use Minn\Auth\Password;

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
        $ariaLabel = $args['aria_label'] !== '' ? ' aria-label="' . esc_attr($args['aria_label']) . '"' : '';
        $action = esc_url(home_url('/'));
        $form = current_theme_supports('html5', 'search-form')
            ? '<form role="search"' . $ariaLabel . ' method="get" class="search-form" action="' . $action . '">'
                . "\n\t\t\t\t<label>\n\t\t\t\t\t" . '<span class="screen-reader-text">Search for:</span>'
                . "\n\t\t\t\t\t" . '<input type="search" class="search-field" placeholder="Search &hellip;" value="' . get_search_query() . '" name="s" />'
                . "\n\t\t\t\t</label>\n\t\t\t\t" . '<input type="submit" class="search-submit" value="Search" />' . "\n\t\t\t</form>"
            : '<form role="search"' . $ariaLabel . ' method="get" id="searchform" class="searchform" action="' . $action . '">'
                . "\n\t\t\t\t<div>\n\t\t\t\t\t" . '<label class="screen-reader-text" for="s">Search for:</label>'
                . "\n\t\t\t\t\t" . '<input type="text" value="' . get_search_query() . '" name="s" id="s" />'
                . "\n\t\t\t\t\t" . '<input type="submit" id="searchsubmit" value="Search" />' . "\n\t\t\t\t</div>\n\t\t\t</form>";
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

/** Inline code's element: the code trimmed between newlines, wp_inline_script_attributes asked, escaped as Minn\Support\ScriptTag says. */
function wp_get_inline_script_tag($data, $attributes = [])
{
    $data = "\n" . trim((string) $data, "\n\r ") . "\n";
    $attributes = apply_filters('wp_inline_script_attributes', $attributes, $data);
    return Minn\Support\ScriptTag::inline($data, (array) $attributes);
}

function wp_print_inline_script_tag($data, $attributes = [])
{
    echo wp_get_inline_script_tag($data, $attributes);
}

/** Attributes as markup, in their order: true printed bare, false left out, every value through esc_attr. */
function wp_sanitize_script_attributes($attributes)
{
    $out = '';
    foreach ((array) $attributes as $name => $value) {
        if ($value === false) {
            continue;
        }
        $out .= $value === true ? ' ' . esc_attr((string) $name) : sprintf(' %1$s="%2$s"', esc_attr((string) $name), esc_attr((string) $value));
    }
    return $out;
}

/** A script file's element, its attributes through wp_script_attributes first. */
function wp_get_script_tag($attributes)
{
    return Minn\Support\ScriptTag::element((array) apply_filters('wp_script_attributes', $attributes));
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
    wp_print_inline_script_tag((string) json_encode($rules, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), ['type' => 'speculationrules']);
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

function wp_list_pages($args = '')
{
    $r = wp_parse_args($args, ['depth' => 0, 'show_date' => '', 'date_format' => get_option('date_format'), 'child_of' => 0, 'exclude' => '', 'title_li' => 'Pages', 'echo' => 1, 'authors' => '', 'sort_column' => 'menu_order, post_title', 'sort_order' => 'ASC', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'preserve', 'walker' => '', 'include' => '', 'post_type' => 'page', 'post_status' => 'publish']);
    $r['exclude'] = implode(',', apply_filters('wp_list_pages_excludes', wp_parse_id_list($r['exclude'])));
    $spacing = $r['item_spacing'] === 'discard'
        ? ListSpacing::discarded((string) $r['link_before'], (string) $r['link_after'])
        : ListSpacing::preserved((string) $r['link_before'], (string) $r['link_after']);
    $items = _minn_page_list($r)->items((int) $r['child_of'], (int) $r['depth'], $spacing);
    $output = '';
    if ($items !== '') {
        $output = $r['title_li'] ? '<li class="pagenav">' . $r['title_li'] . '<ul>' . $items . '</ul></li>' : $items;
    }
    $html = apply_filters('wp_list_pages', $output, $r, []);
    if ($r['echo']) {
        echo $html;
        return null;
    }
    return $html;
}

function wp_dropdown_pages($args = '')
{
    $r = wp_parse_args($args, ['depth' => 0, 'child_of' => 0, 'selected' => 0, 'echo' => 1, 'name' => 'page_id', 'id' => '', 'class' => '', 'show_option_none' => '', 'show_option_no_change' => '', 'option_none_value' => '', 'value_field' => 'ID', 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'exclude' => '', 'include' => '']);
    $field = (string) $r['value_field'];
    $value = static fn (array $page): string => $field === 'post_name' ? esc_attr($page['name']) : (string) $page['id'];
    $options = _minn_page_list($r)->options((int) $r['child_of'], (int) $r['depth'], (int) $r['selected'], $value);
    $output = '';
    if ($options !== '') {
        $class = $r['class'] !== '' ? " class='" . esc_attr($r['class']) . "'" : '';
        $output = "<select name='" . esc_attr($r['name']) . "'" . $class . " id='" . esc_attr($r['id'] !== '' ? $r['id'] : $r['name']) . "'>\n";
        if ($r['show_option_no_change']) {
            $output .= "\t<option value=\"-1\">" . $r['show_option_no_change'] . "</option>\n";
        }
        if ($r['show_option_none']) {
            $output .= "\t<option value=\"" . esc_attr($r['option_none_value']) . '">' . $r['show_option_none'] . "</option>\n";
        }
        $output .= $options . "</select>\n";
    }
    $html = apply_filters('wp_dropdown_pages', $output, $r, []);
    if ($r['echo']) {
        echo $html;
        return null;
    }
    return $html;
}

/** @internal the published pages a list or dropdown shows, nested by parent */
function _minn_page_list(array $r): PageList
{
    $include = wp_parse_id_list($r['include'] ?? '');
    $exclude = wp_parse_id_list($r['exclude'] ?? '');
    $rows = [];
    foreach ((new Posts(Runtime::current()->db))->pages((string) $r['sort_column'], (string) $r['sort_order']) as $page) {
        if ($include !== [] ? !in_array($page['id'], $include, true) : in_array($page['id'], $exclude, true)) {
            continue;
        }
        $title = apply_filters('the_title', $page['title'], $page['id']);
        $rows[] = ['id' => $page['id'], 'parent' => $page['parent'], 'name' => $page['name'], 'title' => $title === '' ? '#' . $page['id'] : $title, 'link' => get_permalink($page['id'])];
    }
    return new PageList($rows, _minn_current_page_trail());
}

/** @internal the queried page and its ancestors, the page first; empty off a page */
function _minn_current_page_trail(): array
{
    $queried = get_queried_object();
    if (!$queried instanceof WP_Post || $queried->post_type !== 'page') {
        return [];
    }
    return array_merge([$queried->ID], array_map('intval', get_post_ancestors($queried)));
}

function wp_get_archives($args = '')
{
    $r = wp_parse_args($args, ['type' => 'monthly', 'limit' => '', 'format' => 'html', 'before' => '', 'after' => '', 'show_post_count' => false, 'echo' => 1, 'order' => 'DESC', 'post_type' => 'post', 'year' => get_query_var('year'), 'monthnum' => get_query_var('monthnum'), 'day' => get_query_var('day'), 'w' => get_query_var('w')]);
    $type = $r['type'] === '' ? 'monthly' : (string) $r['type'];
    $output = '';
    foreach (_minn_archive_rows($type, (string) $r['post_type'], (string) $r['order'], (int) $r['limit']) as $row) {
        $after = $r['show_post_count'] && $row['count'] > 0 ? '&nbsp;(' . $row['count'] . ')' : $r['after'];
        $output .= get_archives_link($row['url'], $row['text'], $r['format'], $r['before'], $after, _minn_archive_is_current($type, $row, $r));
    }
    if ($r['echo']) {
        echo $output;
        return null;
    }
    return $output;
}

/** @internal whether an archive row is the period the query is on */
function _minn_archive_is_current(string $type, array $row, array $r): bool
{
    $period = $row['period'] ?? [];
    return match ($type) {
        'yearly' => (int) $r['year'] === ($period['year'] ?? -1),
        'monthly' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['monthnum'] === ($period['month'] ?? -1),
        'daily' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['monthnum'] === ($period['month'] ?? -1) && (int) $r['day'] === ($period['day'] ?? -1),
        'weekly' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['w'] === ($period['week'] ?? -1),
        default => false,
    };
}

/** @internal the rows wp_get_archives lists for one type */
function _minn_archive_rows(string $type, string $postType, string $order, int $limit): array
{
    $archives = new Archives(
        new Posts(Runtime::current()->db),
        static fn (string $format, string $datetime): string => date_i18n($format, (int) strtotime($datetime)),
        static fn (int $y, ?int $m, ?int $d): string => $d !== null ? get_day_link($y, $m, $d) : ($m !== null ? get_month_link($y, $m) : get_year_link($y)),
        static fn (string $datetime): array => get_weekstartend($datetime, get_option('start_of_week')),
    );
    if ($type === 'postbypost' || $type === 'alpha') {
        $title = static fn (int $id, string $title): string => $title !== '' ? strip_tags(apply_filters('the_title', $title, $id)) : (string) $id;
        return $archives->posts($postType, $type === 'alpha' ? 'title' : 'date', $type === 'alpha' ? 'ASC' : $order, $limit, $title, static fn (int $id): string => (string) get_permalink($id));
    }
    $weekLink = static fn (int $year, int $week): string => add_query_arg(['m' => $year, 'w' => $week], home_url('/'));
    return $archives->periods($type, $postType, $order, $limit, $weekLink);
}

function wp_login_form($args = [])
{
    $defaults = [
        'echo' => true,
        'redirect' => (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''),
        'form_id' => 'loginform',
        'label_username' => 'Username or Email Address',
        'label_password' => 'Password',
        'label_remember' => 'Remember Me',
        'label_log_in' => 'Log In',
        'id_username' => 'user_login',
        'id_password' => 'user_pass',
        'id_remember' => 'rememberme',
        'id_submit' => 'wp-submit',
        'remember' => true,
        'value_username' => '',
        'value_remember' => false,
    ];
    $args = wp_parse_args($args, apply_filters('login_form_defaults', $defaults));
    $args['action'] = wp_login_url();
    $form = Minn\Login\LoginForm::embedded(
        $args,
        (string) apply_filters('login_form_top', '', $args),
        (string) apply_filters('login_form_middle', '', $args),
        (string) apply_filters('login_form_bottom', '', $args),
    );
    if ($args['echo']) {
        echo $form;
        return;
    }
    return $form;
}

function wp_loginout($redirect = '', $display = true)
{
    $link = is_user_logged_in()
        ? '<a href="' . esc_url(wp_logout_url($redirect)) . '">Log out</a>'
        : '<a href="' . esc_url(wp_login_url($redirect)) . '">Log in</a>';
    $link = apply_filters('loginout', $link);
    if (!$display) {
        return $link;
    }
    echo $link;
}

function wp_register($before = '<li>', $after = '</li>', $display = true)
{
    if (!is_user_logged_in()) {
        $link = get_option('users_can_register')
            ? $before . '<a href="' . esc_url(wp_registration_url()) . '">Register</a>' . $after
            : '';
    } else {
        $link = $before . '<a href="' . esc_url(admin_url()) . '">Site Admin</a>' . $after;
    }
    $link = apply_filters('register', $link);
    if (!$display) {
        return $link;
    }
    echo $link;
}

function wp_meta()
{
    do_action('wp_meta');
}
