<?php
/** Template loading and the small template tags. */

use Minn\Content\Posts;
use Minn\Front\Calendar;
use Minn\Front\CalendarLabels;
use Minn\Front\Archives;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
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
    return Minn\I18n\SiteLanguage::attributes((string) $doctype);
}

function language_attributes($doctype = 'html')
{
    echo get_language_attributes($doctype);
}

/** A URL with the trailing slash the permalinks use, as the rewrite object read them when it was set up (the option itself otherwise). */
function user_trailingslashit($url, $type_of_url = '')
{
    return SiteUrls::trailing($url, $type_of_url);
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

/** Sets the template path globals: $wp_stylesheet_path and $wp_template_path, the active theme's directories. */
function wp_set_template_globals()
{
    global $wp_stylesheet_path, $wp_template_path;
    $wp_stylesheet_path = get_stylesheet_directory();
    $wp_template_path = get_template_directory();
}

/**
 * Whether a template path stays where it points (probe template-paths): a
 * segment made only of dots ("..", "..."), between slashes or backslashes,
 * would walk out, so it is refused. Nothing else is checked.
 */
function _wp_is_template_path_allowed($path)
{
    return preg_match('#(?:^|[/\\\\])\.{2,}(?:[/\\\\]|$)#', (string) $path) !== 1;
}

/**
 * The first template name found in the child theme, the parent, then the
 * engine's theme-compat. Since 7.1.2 a name with a dot-only segment counts
 * only when the file it reaches is really inside that folder: a page name
 * under a theme's page-* folder could otherwise reach any PHP file (probe
 * patch-security).
 */
function locate_template($template_names, $load = false, $load_once = true, $args = [])
{
    $located = '';
    foreach ((array) $template_names as $template_name) {
        if (!$template_name) {
            continue;
        }
        // The engine's own theme-compat stands where the reference's wp-includes/theme-compat would.
        foreach ([get_stylesheet_directory(), get_template_directory(), MINN_ENGINE_DIR . '/wp-api/theme-compat'] as $dir) {
            $file = $dir . '/' . $template_name;
            if (file_exists($file) && (_wp_is_template_path_allowed($template_name) || str_starts_with((string) realpath($file), realpath($dir) . '/'))) {
                $located = $file;
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
    // As the reference reads it (probe filter-inputs): pre_get_search_form with the arguments as given, then the
    // arguments through search_form_args and the markup's format through search_form_format.
    do_action('pre_get_search_form', $args);
    $args = apply_filters('search_form_args', wp_parse_args($args, ['echo' => true, 'aria_label' => '']));
    $format = apply_filters('search_form_format', current_theme_supports('html5', 'search-form') ? 'html5' : 'xhtml', $args);
    // A theme's own searchform.php wins over the default form, as the reference loads it.
    $template = locate_template(['searchform.php']);
    if ($template !== '') {
        ob_start();
        load_template($template, false, $args);
        $form = (string) ob_get_clean();
    } else {
        $ariaLabel = $args['aria_label'] !== '' ? ' aria-label="' . esc_attr($args['aria_label']) . '"' : '';
        $action = esc_url(home_url('/'));
        $form = $format === 'html5'
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
    return Minn\Runtime\AssetQueue::inlineTag($data, $attributes);
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
    return Minn\Runtime\AssetQueue::tag($attributes);
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
    if ($id_or_email instanceof WP_Comment && !is_avatar_comment_type(get_comment_type($id_or_email))) {
        $args['url'] = false;
        return apply_filters('get_avatar_data', $args, $id_or_email);
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

/** Whether comments of a type have avatars: comments, and the types get_avatar_comment_types adds (pings have none). */
function is_avatar_comment_type($comment_type)
{
    return in_array($comment_type, (array) apply_filters('get_avatar_comment_types', ['comment']), true);
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
    return Minn\Support\Sanitize::hexColor($color);
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

/** The role options, lowest role first, the one given selected; printed. */
function wp_dropdown_roles($selected = '', $editable_roles = null)
{
    $out = '';
    foreach (array_reverse((array) ($editable_roles ?? get_editable_roles())) as $role => $details) {
        $chosen = $selected === $role ? " selected='selected'" : '';
        $out .= "\n\t<option{$chosen} value='" . esc_attr($role) . "'>" . translate_user_role($details['name']) . '</option>';
    }
    echo $out;
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
    // A plugin may answer for the list (probe hook-inputs).
    $pre = apply_filters('pre_get_blogs_of_user', null, (int) $user_id, $all);
    if ($pre !== null) {
        return $pre;
    }
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
    // A block template, when the theme has one for the hierarchy, becomes the canvas and is
    // recorded in $_wp_current_template_id (probe template-getters).
    $template = locate_block_template(locate_template($templates), $type, $templates);
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

/**
 * @internal the reference's wp_head defaults, registered ahead of the
 * theme's own hooks, so a plugin's remove_action() finds them by name: a
 * block theme's head as the reference's block template canvas prints it
 * (the viewport meta, the robots meta, the title, the feed, oEmbed, REST,
 * RSD, generator, canonical and short links, the site icon), a classic
 * theme's as its wp_head() does
 */
function _minn_classic_head_defaults()
{
    if (!Runtime::current()->get('classic_theme')) {
        _minn_block_head_defaults();
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
    add_action('wp_head', 'wp_enqueue_img_auto_sizes_contain_css_fix', 0);
    add_action('wp_head', '_minn_classic_bar_head', 200, 0);
    add_action('wp_footer', '_minn_classic_bar_footer', 200, 0);
}

/**
 * @internal a block theme's wp_head defaults, as the reference registers
 * them for every theme (contracts/front/classic.md "The block head"); the
 * page swaps the title for the block template's own as it renders
 */
function _minn_block_head_defaults(): void
{
    add_action('wp_head', '_wp_render_title_tag', 1, 0);
    add_action('wp_head', 'wp_robots', 1, 0);
    add_action('wp_head', 'wp_preload_resources', 1, 0);
    add_action('wp_head', 'wp_resource_hints', 2, 0);
    add_action('wp_head', 'feed_links', 2, 1);
    add_action('wp_head', 'feed_links_extra', 3, 1);
    // Registered at 4 AND 10 like the reference; the function prints once.
    add_action('wp_head', 'wp_oembed_add_discovery_links', 4, 0);
    add_action('wp_head', 'rest_output_link_wp_head', 10, 0);
    add_action('wp_head', 'rsd_link', 10, 0);
    add_action('wp_head', 'locale_stylesheet', 10, 0);
    add_action('wp_head', 'wp_generator', 10, 0);
    add_action('wp_head', 'rel_canonical', 10, 0);
    add_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    add_action('wp_head', 'wp_oembed_add_discovery_links', 10, 0);
    add_action('wp_head', 'wp_site_icon', 99, 0);
    add_action('wp_head', 'wp_enqueue_img_auto_sizes_contain_css_fix', 0);
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
    $resolution = Runtime::current()->get('head_resolution');
    if ($bar instanceof \Minn\Front\AdminBar && $resolution instanceof \Minn\Front\Resolution) {
        echo $bar->render($resolution);
    }
}

/** The style that sizes auto-sized images before they load, first in the queue, once a request (the first call decides), unless wp_img_tag_add_auto_sizes turns them off. */
function wp_enqueue_img_auto_sizes_contain_css_fix()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!apply_filters('wp_img_tag_add_auto_sizes', true)) {
        return;
    }
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

/** The site's feed and comments feed as head links, each unless its feed_links_show_* filter says no, titled in the caller's words. */
function feed_links($args = [])
{
    if (current_theme_supports('automatic-feed-links')) {
        echo Minn\Theme\HeadLinks::siteFeeds((array) $args);
    }
}

function feed_links_extra($args = [])
{
    $args = apply_filters('feed_links_extra_args', wp_parse_args($args, ['separator' => _x('&raquo;', 'feed link'), 'singletitle' => __('%1$s %2$s %3$s Comments Feed'), 'cattitle' => __('%1$s %2$s %3$s Category Feed'), 'tagtitle' => __('%1$s %2$s %3$s Tag Feed'), 'taxtitle' => __('%1$s %2$s %3$s %4$s Feed'), 'authortitle' => __('%1$s %2$s Posts by %3$s Feed'), 'searchtitle' => __('%1$s %2$s Search Results for &#8220;%3$s&#8221; Feed'), 'posttypetitle' => __('%1$s %2$s %3$s Feed')]));
    [$title, $href] = _minn_extra_feed($args, get_bloginfo('name'), $args['separator']);
    if ($href !== '') {
        echo '<link rel="alternate" type="' . feed_content_type() . '" title="' . esc_attr($title) . '" href="' . esc_url($href) . '" />' . "\n";
    }
}

/**
 * @internal The main query's own feed, as a title and a link (probe
 * general-template-remaining): a singular view's comments feed while
 * comments or pings are open or it has comments (both comments-feed filters
 * can hide it), else the archive's or the search's feed, each behind its
 * feed_links_extra_show_{kind}_feed filter. Nothing for the posts page.
 */
function _minn_extra_feed(array $args, string $site, string $sep): array
{
    $object = get_queried_object();
    $post = is_singular() ? get_post() : null;
    $type = get_query_var('post_type');
    [$kind, $title, $href] = match (true) {
        $post instanceof WP_Post => (comments_open($post) || pings_open($post) || (int) $post->comment_count > 0) && apply_filters('feed_links_show_comments_feed', true)
            ? ['post_comments', sprintf($args['singletitle'], $site, $sep, the_title_attribute(['echo' => false, 'post' => $post])), get_post_comments_feed_link($post->ID)] : ['', '', ''],
        is_post_type_archive() => ['post_type_archive', sprintf($args['posttypetitle'], $site, $sep, post_type_archive_title('', false)), get_post_type_archive_feed_link((string) (is_array($type) ? reset($type) : $type))],
        is_category() && $object instanceof WP_Term => ['category', sprintf($args['cattitle'], $site, $sep, $object->name), get_category_feed_link($object->term_id)],
        is_tag() && $object instanceof WP_Term => ['tag', sprintf($args['tagtitle'], $site, $sep, $object->name), get_tag_feed_link($object->term_id)],
        is_tax() && $object instanceof WP_Term => ['tax', sprintf($args['taxtitle'], $site, $sep, $object->name, get_taxonomy($object->taxonomy)->labels->singular_name ?? ''), get_term_feed_link($object->term_id, $object->taxonomy)],
        is_author() => ['author', sprintf($args['authortitle'], $site, $sep, get_the_author_meta('display_name', (int) get_query_var('author'))), get_author_feed_link((int) get_query_var('author'))],
        is_search() => ['search', sprintf($args['searchtitle'], $site, $sep, get_search_query(false)), get_search_feed_link()],
        default => ['', '', ''],
    };
    return $kind !== '' && apply_filters("feed_links_extra_show_{$kind}_feed", true) ? [$title, (string) $href] : ['', ''];
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

/**
 * The site icon's head tags (probe general-template-remaining): the 32 and
 * 192 pixel icons, the Apple touch icon and the tile image, through
 * site_icon_meta_tags, one per line. Nothing when the site has no icon.
 */
function wp_site_icon()
{
    if (!has_site_icon() && !is_customize_preview()) {
        return;
    }
    $tags = apply_filters('site_icon_meta_tags', [
        sprintf('<link rel="icon" href="%s" sizes="32x32" />', esc_url(get_site_icon_url(32))),
        sprintf('<link rel="icon" href="%s" sizes="192x192" />', esc_url(get_site_icon_url(192))),
        sprintf('<link rel="apple-touch-icon" href="%s" />', esc_url(get_site_icon_url(180))),
        sprintf('<meta name="msapplication-TileImage" content="%s" />', esc_url(get_site_icon_url(270))),
    ]);
    foreach (array_filter((array) $tags) as $tag) {
        echo $tag . "\n";
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
    $output = '<link rel="alternate" title="' . esc_attr_x('oEmbed (JSON)', 'oEmbed resource link name') . '" type="application/json+oembed" href="' . esc_url($base . '?url=' . urlencode($permalink)) . '" />' . "\n";
    $output .= '<link rel="alternate" title="' . esc_attr_x('oEmbed (XML)', 'oEmbed resource link name') . '" type="text/xml+oembed" href="' . esc_url($base . '?url=' . urlencode($permalink) . '&format=xml') . '" />' . "\n";
    echo apply_filters('oembed_discovery_links', $output);
}

/** The Really Simple Discovery link the reference prints from wp_head, to the site URL's xmlrpc.php?rsd. */
function rsd_link()
{
    echo '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="' . esc_url(site_url('xmlrpc.php?rsd', 'rpc')) . '" />' . "\n";
}

function wp_generator()
{
    the_generator(apply_filters('wp_generator_type', 'xhtml'));
}

function the_generator($type)
{
    echo apply_filters('the_generator', get_the_generator($type), $type) . "\n";
}

/** The generator line for a type; with none, the type the feed hook it runs on implies (nothing outside one). */
function get_the_generator($type = '')
{
    if (empty($type)) {
        $type = match (current_filter()) {
            'rss2_head', 'commentsrss2_head' => 'rss2',
            'rss_head', 'opml_head' => 'comment',
            'rdf_header' => 'rdf',
            'atom_head', 'comments_atom_head', 'app_head' => 'atom',
            default => '',
        };
        if ($type === '') {
            return null;
        }
    }
    $version = (string) get_bloginfo('version');
    $gen = match ($type) {
        'html' => '<meta name="generator" content="WordPress ' . esc_attr($version) . '">',
        'atom' => '<generator uri="https://wordpress.org/" version="' . esc_attr(get_bloginfo_rss('version')) . '">WordPress</generator>',
        'rss2' => '<generator>' . sanitize_url('https://wordpress.org/?v=' . get_bloginfo_rss('version')) . '</generator>',
        'rdf' => '<admin:generatorAgent rdf:resource="' . sanitize_url('https://wordpress.org/?v=' . get_bloginfo_rss('version')) . '" />',
        'comment' => '<!-- generator="WordPress/' . esc_attr($version) . '" -->',
        'export' => '<!-- generator="WordPress/' . esc_attr(get_bloginfo_rss('version')) . '" created="' . gmdate('Y-m-d H:i') . '" -->',
        default => '<meta name="generator" content="WordPress ' . esc_attr($version) . '" />',
    };
    return apply_filters("get_the_generator_{$type}", $gen, $type);
}

function get_calendar($args = [])
{
    // The older form, get_calendar($initial, $display), still answers.
    if (!is_array($args)) {
        $legacy = func_get_args();
        $args = ['initial' => (bool) $legacy[0], 'display' => $legacy[1] ?? true];
    }
    $args = apply_filters('get_calendar_args', wp_parse_args($args, ['initial' => true, 'display' => true, 'post_type' => 'post']));
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

/**
 * The page list (probe plugin-queue4): the published pages walked by
 * walk_page_tree under a titled item, the page being viewed current;
 * through wp_list_pages with the pages found.
 */
function wp_list_pages($args = '')
{
    $r = wp_parse_args($args, ['depth' => 0, 'show_date' => '', 'date_format' => get_option('date_format'), 'child_of' => 0, 'exclude' => '', 'title_li' => __('Pages'), 'echo' => 1, 'authors' => '', 'sort_column' => 'menu_order, post_title', 'link_before' => '', 'link_after' => '', 'item_spacing' => 'preserve', 'walker' => '']);
    if (!in_array($r['item_spacing'], ['preserve', 'discard'], true)) {
        $r['item_spacing'] = 'preserve';
    }
    $exclude = preg_replace('/[^0-9,]/', '', (string) $r['exclude']);
    $r['exclude'] = implode(',', (array) apply_filters('wp_list_pages_excludes', $exclude !== '' ? explode(',', $exclude) : []));
    $r['hierarchical'] = 0;
    $pages = get_pages($r);
    $output = '';
    if (!empty($pages)) {
        $output = ($r['title_li'] ? '<li class="pagenav">' . $r['title_li'] . '<ul>' : '') . walk_page_tree($pages, $r['depth'], _minn_list_current_page(), $r) . ($r['title_li'] ? '</ul></li>' : '');
    }
    $html = apply_filters('wp_list_pages', $output, $r, $pages);
    if ($r['echo']) {
        echo $html;
        return null;
    }
    return $html;
}

/** @internal the page a page list marks current: the page, attachment or posts page being viewed, or a hierarchical post */
function _minn_list_current_page(): int
{
    if (is_page() || is_attachment() || !empty(_minn_main_query()->is_posts_page)) {
        return (int) get_queried_object_id();
    }
    $viewed = is_singular() ? get_queried_object() : null;
    return $viewed instanceof WP_Post && is_post_type_hierarchical($viewed->post_type) ? (int) $viewed->ID : 0;
}

/** The page select (probe plugin-queue4): the no-change and none choices, then the pages walked by walk_page_dropdown_tree; nothing without pages. */
function wp_dropdown_pages($args = '')
{
    $r = wp_parse_args($args, ['depth' => 0, 'child_of' => 0, 'selected' => 0, 'echo' => 1, 'name' => 'page_id', 'id' => '', 'class' => '', 'show_option_none' => '', 'show_option_no_change' => '', 'option_none_value' => '', 'value_field' => 'ID']);
    $pages = get_pages($r);
    $output = '';
    if (!empty($pages)) {
        $output = "<select name='" . esc_attr($r['name']) . "'" . ($r['class'] ? " class='" . esc_attr($r['class']) . "'" : '') . " id='" . esc_attr($r['id'] ?: $r['name']) . "'>\n"
            . ($r['show_option_no_change'] ? "\t<option value=\"-1\">" . $r['show_option_no_change'] . "</option>\n" : '')
            . ($r['show_option_none'] ? "\t<option value=\"" . esc_attr($r['option_none_value']) . '">' . $r['show_option_none'] . "</option>\n" : '')
            . walk_page_dropdown_tree($pages, $r['depth'], $r) . "</select>\n";
    }
    $html = apply_filters('wp_dropdown_pages', $output, $r, $pages);
    if ($r['echo']) {
        echo $html;
        return null;
    }
    return $html;
}

function wp_get_archives($args = '')
{
    global $wpdb;
    // As the reference reads them (probe filter-inputs): the arguments through wp_get_archives_args, the limit
    // written as its clause, then the query's WHERE and JOIN through getarchives_where and getarchives_join,
    // which the rows are read with.
    $args = apply_filters('wp_get_archives_args', $args);
    $r = wp_parse_args($args, ['type' => 'monthly', 'limit' => '', 'format' => 'html', 'before' => '', 'after' => '', 'show_post_count' => false, 'echo' => 1, 'order' => 'DESC', 'post_type' => 'post', 'year' => get_query_var('year'), 'monthnum' => get_query_var('monthnum'), 'day' => get_query_var('day'), 'w' => get_query_var('w')]);
    $type = $r['type'] === '' ? 'monthly' : (string) $r['type'];
    $limit = absint($r['limit']);
    $r['limit'] = $limit > 0 ? ' LIMIT ' . $limit : '';
    $where = (string) apply_filters('getarchives_where', $wpdb->prepare("WHERE post_type = %s AND post_status = 'publish'", $r['post_type']), $r);
    $join = (string) apply_filters('getarchives_join', '', $r);
    $output = '';
    foreach (_minn_archive_rows($type, (string) $r['post_type'], (string) $r['order'], $limit, $where, $join) as $row) {
        $after = $r['show_post_count'] && $row['count'] > 0 ? '&nbsp;(' . $row['count'] . ')' : $r['after'];
        $output .= get_archives_link($row['url'], $row['text'], $r['format'], $r['before'], $after, _minn_archive_is_current($type, $row, $r));
    }
    if ($r['echo']) {
        echo $output;
        return null;
    }
    return $output;
}

/** @internal whether an archive row is the period the query is on, or the post being shown */
function _minn_archive_is_current(string $type, array $row, array $r): bool
{
    $period = $row['period'] ?? [];
    return match ($type) {
        'yearly' => (int) $r['year'] === ($period['year'] ?? -1),
        'monthly' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['monthnum'] === ($period['month'] ?? -1),
        'daily' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['monthnum'] === ($period['month'] ?? -1) && (int) $r['day'] === ($period['day'] ?? -1),
        'weekly' => (int) $r['year'] === ($period['year'] ?? -1) && (int) $r['w'] === ($period['week'] ?? -1),
        'postbypost', 'alpha' => get_the_ID() === ($row['id'] ?? null),
        default => false,
    };
}

/** @internal the rows wp_get_archives lists for one type */
function _minn_archive_rows(string $type, string $postType, string $order, int $limit, string $where = '', string $join = ''): array
{
    $archives = new Archives(
        new Posts(Runtime::current()->db),
        static fn (string $format, string $datetime): string => date_i18n($format, (int) strtotime($datetime)),
        static fn (int $y, ?int $m, ?int $d): string => $d !== null ? get_day_link($y, $m, $d) : ($m !== null ? get_month_link($y, $m) : get_year_link($y)),
        static fn (string $datetime): array => get_weekstartend($datetime, get_option('start_of_week')),
    );
    if ($type === 'postbypost' || $type === 'alpha') {
        $title = static fn (int $id, string $title): string => $title !== '' ? strip_tags(apply_filters('the_title', $title, $id)) : (string) $id;
        return $archives->posts($postType, $type === 'alpha' ? 'title' : 'date', $type === 'alpha' ? 'ASC' : $order, $limit, $title, static fn (int $id): string => (string) get_permalink($id), $where, $join);
    }
    $weekLink = static fn (int $year, int $week): string => add_query_arg(['m' => $year, 'w' => $week], home_url('/'));
    return $archives->periods($type, $postType, $order, $limit, $weekLink, $where, $join);
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

/** The page templates as <option>s, by name, the one given selected. */
function page_template_dropdown($default_template = '', $post_type = 'page')
{
    $templates = get_page_templates(null, $post_type);
    ksort($templates);
    foreach ($templates as $name => $file) {
        echo "\n\t<option value='" . esc_attr($file) . "' " . selected($default_template, $file, false) . '>' . esc_html($name) . '</option>';
    }
}

/** The tags and attributes a comment may use ($allowedtags), as the form's hint prints them, escaped for HTML. */
function allowed_tags()
{
    global $allowedtags;
    $allowed = '';
    foreach ((array) $allowedtags as $tag => $attributes) {
        $allowed .= '<' . $tag;
        foreach (array_keys((array) $attributes) as $attribute) {
            $allowed .= ' ' . $attribute . '=""';
        }
        $allowed .= '> ';
    }
    return htmlentities($allowed);
}

/** The mark of a required form field (wp_required_field_indicator filters it). */
function wp_required_field_indicator()
{
    return apply_filters('wp_required_field_indicator', '<span class="required">' . esc_html__('*') . '</span>');
}

/** The note that explains the required-field mark (wp_required_field_message filters it). */
function wp_required_field_message()
{
    return apply_filters('wp_required_field_message', '<span class="required-field-message">' . sprintf(__('Required fields are marked %s'), wp_required_field_indicator()) . '</span>');
}

/** @internal a tooltip's id: the one given, else wp-tooltip-{n}; every tooltip made counts, given an id or not */
function _minn_tooltip_id(array $args): string
{
    static $count = 0;
    $count++;
    return isset($args['id']) && $args['id'] !== '' ? (string) $args['id'] : 'wp-tooltip-' . $count;
}

/**
 * A hover tooltip (probe general-template-more): a help button labelled with
 * the text (tags stripped; its attributes in name order since 7.1.3) and a
 * hint popover holding it, escaped. args:
 * icon (after "dashicons "), class, id. Nothing for no text.
 */
function wp_get_tooltip($content, $args = [])
{
    $content = (string) $content;
    if ($content === '') {
        return '';
    }
    $args = (array) $args;
    $id = _minn_tooltip_id($args);
    return '<span class="wp-tooltip wp-is-tooltip' . (!empty($args['class']) ? ' ' . esc_attr((string) $args['class']) : '') . '">' . "\n\t\t\t\t"
        . '<button aria-label="' . esc_attr(wp_strip_all_tags($content)) . '" class="wp-tooltip__toggle" type="button"><span aria-hidden="true" class="' . esc_attr('dashicons ' . ($args['icon'] ?? 'dashicons-editor-help')) . '"></span></button>' . "\n\t\t\t\t"
        . '<span popover="hint" id="' . esc_attr($id) . '" class="wp-tooltip__bubble" role="tooltip"><span id="' . esc_attr($id . '-text') . '" class="wp-tooltip__text">' . esc_html($content) . '</span></span></span>';
}

/** A help tooltip; the same markup as wp_get_tooltip. */
function wp_get_tooltip_helper($content, $args = [])
{
    return wp_get_tooltip($content, $args);
}

/**
 * A click-to-open toggletip: a button and a dialog popover labelled with
 * args label ("Help" by default), the text escaped, and a close button.
 * args: label, icon, class, id. Nothing for no text.
 */
function wp_get_toggletip($content, $args = [])
{
    $content = (string) $content;
    if ($content === '') {
        return '';
    }
    $args = (array) $args;
    $id = esc_attr(_minn_tooltip_id($args));
    $label = esc_attr((string) ($args['label'] ?? __('Help')));
    return '<span class="wp-tooltip wp-is-toggletip' . (!empty($args['class']) ? ' ' . esc_attr((string) $args['class']) : '') . '">' . "\n\t\t\t\t"
        . '<button aria-haspopup="dialog" aria-label="' . $label . '" class="wp-tooltip__toggle" popovertarget="' . $id . '" type="button"><span aria-hidden="true" class="' . esc_attr('dashicons ' . ($args['icon'] ?? 'dashicons-editor-help')) . '"></span></button>' . "\n\t\t\t\t"
        . '<span popover="auto" id="' . $id . '" class="wp-tooltip__bubble" role="dialog" aria-label="' . $label . '" tabindex="-1" autofocus><span id="' . $id . '-text" class="wp-tooltip__text">' . esc_html($content) . '</span>'
        . '<button type="button" class="wp-tooltip__close" popovertarget="' . $id . '" popovertargetaction="hide" aria-label="' . esc_attr__('Close') . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></span></span>';
}

/** A post type archive's description (get_the_post_type_description filters it; texturized and paragraphed by default). */
function get_the_post_type_description()
{
    $type = get_query_var('post_type');
    $type = is_array($type) ? reset($type) : $type;
    $object = get_post_type_object((string) $type);
    return apply_filters('get_the_post_type_description', $object->description ?? '', $object);
}

/** Prints the current post's date as Y-m-d. */
function the_date_xml()
{
    echo get_post_time('Y-m-d');
}

/** Prints the weekday of the current post's date (the_weekday filters it). */
function the_weekday()
{
    $post = get_post();
    if (!$post) {
        return;
    }
    echo apply_filters('the_weekday', $GLOBALS['wp_locale']->get_weekday(get_post_time('w', false, $post)));
}

/** Prints the current post's weekday once per day in a loop ($previousweekday), between before and after; the_weekday_date filters it. */
function the_weekday_date($before = '', $after = '')
{
    global $previousweekday;
    $post = get_post();
    if (!$post) {
        return;
    }
    $weekday = $GLOBALS['wp_locale']->get_weekday(get_post_time('w', false, $post));
    $out = '';
    if ($previousweekday !== $weekday) {
        $out = $before . $weekday . $after;
        $previousweekday = $weekday;
    }
    echo apply_filters('the_weekday_date', $out, $before, $after);
}

/** The external hosts the queued scripts, then styles, load from, each once, the site's own server left out (for resource hints). */
function wp_dependencies_unique_hosts()
{
    $hosts = [];
    $own = $_SERVER['SERVER_NAME'] ?? '';
    foreach ([wp_scripts(), wp_styles()] as $registry) {
        $registry->sync();
        foreach ((array) $registry->queue as $handle) {
            $host = isset($registry->registered[$handle]) ? (string) wp_parse_url((string) $registry->registered[$handle]->src, PHP_URL_HOST) : '';
            if ($host !== '' && $host !== $own && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }
    }
    return $hosts;
}

/**
 * Prints a <link rel='preload'> for each resource wp_preload_resources asks
 * for: each needs an href or an imagesrcset and is printed once; only href,
 * as, crossorigin, type, media, imagesrcset, imagesizes and fetchpriority are
 * kept, in the order given.
 */
function wp_preload_resources()
{
    $resources = apply_filters('wp_preload_resources', []);
    $unique = [];
    foreach (is_array($resources) ? $resources : [] as $resource) {
        $key = is_array($resource) ? ($resource['href'] ?? $resource['imagesrcset'] ?? null) : null;
        if (is_string($key) && !isset($unique[$key])) {
            $unique[$key] = $resource;
        }
    }
    foreach ($unique as $resource) {
        $html = [];
        foreach ($resource as $name => $value) {
            if (is_scalar($value) && in_array($name, ['href', 'as', 'crossorigin', 'type', 'media', 'imagesrcset', 'imagesizes', 'fetchpriority'], true)) {
                $html[] = $name . "='" . ($name === 'href' ? esc_url((string) $value, ['http', 'https']) : esc_attr((string) $value)) . "'";
            }
        }
        printf("<link rel='preload' %s />\n", implode(' ', $html));
    }
}

// The template enhancement output buffer (probe template-loading). The engine builds its pages as strings, so the
// buffer starts only where wp_before_include_template is fired by a plugin.

/** Whether the template is buffered for enhancement: when anything filters wp_template_enhancement_output_buffer, unless overridden. */
function wp_should_output_buffer_template_for_enhancement()
{
    return (bool) apply_filters('wp_should_output_buffer_template_for_enhancement', has_filter('wp_template_enhancement_output_buffer'));
}

/** The enhancement buffer started (wp_before_include_template 1000), wp_template_enhancement_output_buffer_started told; false when it is not wanted. */
function wp_start_template_enhancement_output_buffer()
{
    if (!wp_should_output_buffer_template_for_enhancement()) {
        return false;
    }
    ob_start('wp_finalize_template_enhancement_output_buffer');
    do_action('wp_template_enhancement_output_buffer_started');
    return true;
}

/** The buffered page through wp_template_enhancement_output_buffer (output, original), then wp_finalized_template_enhancement_output_buffer; a cleaned buffer is left alone. */
function wp_finalize_template_enhancement_output_buffer(string $output, int $phase)
{
    if (($phase & PHP_OUTPUT_HANDLER_CLEAN) !== 0) {
        return $output;
    }
    $filtered = apply_filters('wp_template_enhancement_output_buffer', $output, $output);
    do_action('wp_finalized_template_enhancement_output_buffer', $filtered);
    return $filtered;
}

/** The heartbeat's settings: the admin-ajax address, and the heartbeat nonce for someone signed in (probe placeholder-helpers). */
function wp_heartbeat_settings($settings)
{
    $settings = (array) $settings;
    $settings['ajaxurl'] = admin_url('admin-ajax.php');
    if (is_user_logged_in()) {
        $settings['nonce'] = wp_create_nonce('heartbeat-nonce');
    }
    return $settings;
}

/**
 * The current block template's markup (probe tail-helpers): its embeds, a
 * lone shortcode's paragraph removed, blocks, shortcodes, then texturized,
 * smilies, image tags, and "]]>" kept from closing a CDATA, inside the site
 * blocks wrapper. With no template, nothing, or a notice for someone signed
 * in.
 */
function get_the_block_template_html()
{
    global $_wp_current_template_content;
    if (!$_wp_current_template_content) {
        return is_user_logged_in() ? '<h1>' . esc_html__('No matching template found') . '</h1>' : '';
    }
    $embed = _minn_wp_embed();
    $content = shortcode_unautop($embed->autoembed($embed->run_shortcode((string) $_wp_current_template_content)));
    $content = convert_smilies(wptexturize(do_shortcode(do_blocks($content))));
    $content = str_replace(']]>', ']]&gt;', wp_filter_content_tags($content, 'template'));
    return '<div class="wp-site-blocks">' . $content . '</div>';
}

/**
 * An editor field (an editor dead end answered by real code: Austin,
 * 2026-10-11, D7). The engine ships no TinyMCE or Quicktags, so a form gets a
 * plain textarea with the editor's id, name, rows, class and content, which
 * still submits; Minn's own editor lives in Minn Admin.
 */
function wp_editor($content, $editor_id, $settings = [])
{
    $settings = wp_parse_args($settings, ['textarea_name' => $editor_id, 'textarea_rows' => 20, 'editor_class' => '']);
    printf('<textarea class="%s" rows="%d" cols="40" name="%s" id="%s">%s</textarea>', esc_attr(trim('wp-editor-area ' . $settings['editor_class'])), (int) $settings['textarea_rows'], esc_attr((string) $settings['textarea_name']), esc_attr((string) $editor_id), esc_textarea((string) $content));
}
