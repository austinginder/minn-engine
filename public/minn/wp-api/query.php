<?php
/** The main query, query vars, conditional tags, and the loop. */

use Minn\Front\Canonical;
use Minn\Runtime\Runtime;

/** @internal the main query the page runs on; a CLI boot gets an empty one */
function _minn_main_query(): WP_Query
{
    if (!isset($GLOBALS['wp_the_query']) || !$GLOBALS['wp_the_query'] instanceof WP_Query) {
        $GLOBALS['wp_the_query'] = new WP_Query();
        $GLOBALS['wp_the_query']->posts = [];
        $GLOBALS['wp_the_query']->post_count = 0;
    }
    if (!isset($GLOBALS['wp_query']) || !$GLOBALS['wp_query'] instanceof WP_Query) {
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
    }
    return $GLOBALS['wp_query'];
}

function get_query_var($query_var, $default_value = '')
{
    return _minn_main_query()->get($query_var, $default_value);
}

function set_query_var($query_var, $value)
{
    _minn_main_query()->set($query_var, $value);
}

function get_search_query($escaped = true)
{
    $query = apply_filters('get_search_query', get_query_var('s'));
    return $escaped ? esc_attr($query) : $query;
}

function the_search_query()
{
    echo esc_attr(apply_filters('the_search_query', get_search_query(false)));
}

function get_queried_object()
{
    return _minn_main_query()->get_queried_object();
}

function get_queried_object_id()
{
    return _minn_main_query()->get_queried_object_id();
}

function is_main_query()
{
    return _minn_main_query()->is_main_query();
}

function in_the_loop()
{
    return (bool) _minn_main_query()->in_the_loop;
}

function have_posts()
{
    return _minn_main_query()->have_posts();
}

function the_post()
{
    _minn_main_query()->the_post();
}

function rewind_posts()
{
    _minn_main_query()->rewind_posts();
}

function have_comments()
{
    return _minn_main_query()->have_comments();
}

function the_comment()
{
    _minn_main_query()->the_comment();
}

function is_singular($post_types = '')
{
    return _minn_main_query()->is_singular($post_types);
}

function is_single($post = '')
{
    return _minn_main_query()->is_single($post);
}

function is_page($page = '')
{
    return _minn_main_query()->is_page($page);
}

function is_home()
{
    return _minn_main_query()->is_home();
}

function is_front_page()
{
    return _minn_main_query()->is_front_page();
}

function is_archive()
{
    return _minn_main_query()->is_archive();
}

function is_search()
{
    return _minn_main_query()->is_search();
}

function is_404()
{
    return _minn_main_query()->is_404();
}

function is_author($author = '')
{
    return _minn_main_query()->is_author($author);
}

function is_category($category = '')
{
    return _minn_main_query()->is_category($category);
}

function is_tag($tag = '')
{
    return _minn_main_query()->is_tag($tag);
}

function is_tax($taxonomy = '', $term = '')
{
    return _minn_main_query()->is_tax($taxonomy, $term);
}

function is_date()
{
    return _minn_main_query()->is_date();
}

function is_year()
{
    return _minn_main_query()->is_year();
}

function is_month()
{
    return _minn_main_query()->is_month();
}

function is_day()
{
    return _minn_main_query()->is_day();
}

function is_time()
{
    return _minn_main_query()->is_time();
}

function is_feed($feeds = '')
{
    return _minn_main_query()->is_feed($feeds);
}

function is_comment_feed()
{
    return _minn_main_query()->is_comment_feed();
}

function is_attachment($attachment = '')
{
    return _minn_main_query()->is_attachment($attachment);
}

function is_post_type_archive($post_types = '')
{
    return _minn_main_query()->is_post_type_archive($post_types);
}

function is_paged()
{
    return _minn_main_query()->is_paged();
}

function is_preview()
{
    return _minn_main_query()->is_preview();
}

function is_embed()
{
    return _minn_main_query()->is_embed();
}

function is_privacy_policy()
{
    return _minn_main_query()->is_privacy_policy();
}

function is_trackback()
{
    return _minn_main_query()->is_trackback();
}

function is_robots()
{
    return _minn_main_query()->is_robots();
}

function is_favicon()
{
    return _minn_main_query()->is_favicon();
}

function wp_title($sep = '&raquo;', $display = true, $seplocation = '')
{
    $title = apply_filters('wp_title', '', $sep, $seplocation);
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}

function single_post_title($prefix = '', $display = true)
{
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return null;
    }
    $title = apply_filters('single_post_title', $post->post_title, $post);
    if ($display) {
        echo $prefix . $title;
        return null;
    }
    return $prefix . $title;
}

/** The reference titles archives as a label prefix around the bare name: `Category: <span>Uncategorized</span>`. */
function get_the_archive_title()
{
    [$prefix, $name] = _minn_archive_title_parts();
    if (is_search()) {
        $original = $title = 'Search Results for: ' . get_search_query();
    } else {
        $original = $name;
        $title = $prefix === '' ? $name : $prefix . ' <span>' . $name . '</span>';
    }
    return apply_filters('get_the_archive_title', $title, $original, $prefix === '' ? '' : $prefix);
}

/** @internal @return array{string, string} the label prefix (with its trailing colon) and the escaped bare name */
function _minn_archive_title_parts()
{
    if (is_post_type_archive()) {
        $object = get_queried_object();
        return ['Archives:', $object instanceof WP_Post_Type ? esc_html((string) $object->labels->name) : ''];
    }
    $resolution = Runtime::current()->get('classic_resolution');
    if (!$resolution instanceof \Minn\Front\Resolution) {
        return ['', ''];
    }
    [$label, $name] = \Minn\Theme\ArchiveTitle::parts($resolution, (string) get_option('date_format'));
    return [$label === '' || $name === '' ? '' : $label . ':', $name];
}

function get_the_archive_description()
{
    return apply_filters('get_the_archive_description', is_category() || is_tag() || is_tax() ? term_description() : '');
}

function the_archive_title($before = '', $after = '')
{
    $title = (string) get_the_archive_title();
    if ($title !== '') {
        echo $before . $title . $after;
    }
}

function the_archive_description($before = '', $after = '')
{
    $description = (string) get_the_archive_description();
    if ($description !== '') {
        echo $before . $description . $after;
    }
}

/**
 * Seeds the main query from the engine's own resolution of the page: the
 * query variables, the flags they imply, the posts the listing found, and
 * the queried object, before template_redirect fires.
 *
 * @param array<string, mixed> $vars
 * @param list<int> $postIds
 */
/**
 * @internal the main query for a plugin's archive runs through WP_Query, so pre_get_posts can shape it
 * (per page, order, hidden items), and becomes the main query; rows come back for the engine's context
 * @return array{posts: list<array>, total: int, perPage: int}
 */
function _minn_run_main_query(array $vars, int $paged, int $perPage): array
{
    $query = new WP_Query();
    // The main query is the main query while it runs, so is_main_query() holds inside pre_get_posts.
    $GLOBALS['wp_the_query'] = $query;
    $GLOBALS['wp_query'] = $query;
    // No posts_per_page in the vars: the page size is the option's until a plugin's pre_get_posts says otherwise.
    $query->query($vars + ($paged > 1 ? ['paged' => $paged] : []));
    // The columns as the query left them (a the_posts filter may have changed one), not to_array()'s extras.
    $rows = array_map(static fn ($p) => $p instanceof WP_Post ? array_diff_key(get_object_vars($p), ['filter' => true]) : (array) $p, $query->posts);
    return ['posts' => $rows, 'total' => (int) $query->found_posts, 'perPage' => max(1, (int) ($query->query_vars['posts_per_page'] ?? $perPage))];
}

function _minn_seed_main_query(array $vars, array $postIds, int $total, int $perPage, bool $postsPage = false): void
{
    $query = new WP_Query();
    $query->init();
    $query->query = $vars;
    $query->query_vars = $vars;
    $query->parse_query();
    if ($postsPage) {
        $query->is_home = true;
        $query->is_posts_page = true;
        $query->is_page = false;
        $query->is_singular = false;
    }
    $posts = [];
    foreach ($postIds as $id) {
        $post = get_post($id);
        if ($post instanceof WP_Post) {
            $posts[] = $post;
        }
    }
    if ($posts === [] && ($query->is_singular || $query->is_attachment)) {
        // A post named by id, or (a plugin's type) by name and type.
        $single = get_post((int) ($vars['p'] ?? $vars['page_id'] ?? 0));
        if ($single === null && !empty($vars['name']) && !empty($vars['post_type'])) {
            $row = _minn_posts()->findByNameAnyStatus((string) $vars['name'], [(string) $vars['post_type']]);
            $single = $row === null ? null : get_post((int) $row['ID']);
        }
        if ($single instanceof WP_Post) {
            $posts[] = $single;
        }
    }
    $query->posts = $posts;
    $query->post_count = count($posts);
    $query->found_posts = $total;
    $query->max_num_pages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
    $query->post = $posts[0] ?? null;
    $GLOBALS['wp_the_query'] = $query;
    $GLOBALS['wp_query'] = $query;
    if ($query->post !== null && $query->is_singular) {
        $GLOBALS['post'] = $query->post;
    }
    _minn_seed_wp_request($vars);
}

/**
 * The WP global's view of this request. `request` is the path alone, with
 * no leading or trailing slash and no query string, and plugin code builds
 * URLs from it: WooCommerce points its add-to-cart form at
 * home_url( add_query_arg( $_GET, $wp->request ) ), so an empty request
 * posts the form to the site root instead of back to the product.
 *
 * @param array<string, mixed> $vars
 */
function _minn_seed_wp_request(array $vars): void
{
    _minn_rewrite();
    $wp = $GLOBALS['wp'] ?? null;
    if (!$wp instanceof WP) {
        return;
    }
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $wp->request = trim($path, '/');
    $wp->query_vars = $vars;
    $wp->did_permalink = get_option('permalink_structure') !== '';
}

/** The reference's title pipeline over the engine's parts; the result is ready to print inside <title>. */
function _minn_document_title(array $parts): string
{
    $title = apply_filters('pre_get_document_title', '');
    if (!empty($title)) {
        return (string) $title;
    }
    $sep = apply_filters('document_title_separator', '-');
    $parts = apply_filters('document_title_parts', $parts);
    $title = implode(" $sep ", array_filter((array) $parts));
    $title = wptexturize($title);
    $title = convert_chars($title);
    $title = esc_html($title);
    $title = capital_P_dangit($title);
    return (string) apply_filters('document_title', $title);
}

/** The document title: the parts the page renderer stood, or, for anything else (a feed, code of its own), the parts the main query makes. */
function wp_get_document_title()
{
    $parts = Runtime::current()->get('document_title_parts');
    return _minn_document_title(is_array($parts) ? $parts : _minn_query_title_parts());
}

/** @internal the document title's parts as the reference makes them from the main query's state (probe feed-tags) */
function _minn_query_title_parts(): array
{
    $title = match (true) {
        is_404() => __('Page not found'),
        is_search() => sprintf(__('Search Results for &#8220;%s&#8221;'), get_search_query()),
        is_front_page() => get_bloginfo('name', 'display'),
        is_post_type_archive() => post_type_archive_title('', false),
        is_tax() => single_term_title('', false),
        is_home() || is_singular() => single_post_title('', false),
        is_category() || is_tag() => single_term_title('', false),
        is_author() && get_queried_object() => get_queried_object()->display_name,
        is_year() => get_the_date(_x('Y', 'yearly archives date format')),
        is_month() => get_the_date(_x('F Y', 'monthly archives date format')),
        is_day() => get_the_date(),
        default => '',
    };
    $parts = ['title' => $title];
    $paged = max((int) ($GLOBALS['paged'] ?? 0), (int) ($GLOBALS['page'] ?? 0));
    if ($paged >= 2 && !is_404()) {
        $parts['page'] = sprintf(__('Page %s'), $paged);
    }
    return $parts + (is_front_page() ? ['tagline' => get_bloginfo('description', 'display')] : ['site' => get_bloginfo('name', 'display')]);
}

/**
 * A slug the post no longer has sends its 404 to the post's current link, through old_slug_redirect_url. The engine
 * found the post as it resolved the request (by the type the address names and its date); this makes the move.
 */
function wp_old_slug_redirect()
{
    $found = Runtime::current()->get('old_slug_location');
    if (!is_404() || !is_array($found)) {
        return;
    }
    $link = apply_filters('old_slug_redirect_url', $found[0]);
    if ($link && wp_redirect($link, 301)) {
        throw new Minn\Theme\Printed();
    }
}

/**
 * The canonical form of a URL by the engine's resolution, through the redirect_canonical filter: moves there (ending
 * the request), or hands it back when told not to. At template_redirect it is the move the engine found for this request.
 */
function redirect_canonical($requested_url = null, $do_redirect = true)
{
    $runtime = Runtime::current();
    $request = $runtime->request;
    if ($request === null) {
        return null;
    }
    // Hooked to template_redirect it is handed the action's empty argument: that is this request.
    $requested_url = empty($requested_url) ? null : (string) $requested_url;
    $found = $requested_url === null ? $runtime->get('canonical_location') : null;
    if ($requested_url === null && !is_array($found)) {
        return null;
    }
    $location = is_array($found) ? $found[0] : Canonical::location($runtime->db, $request, (string) $requested_url);
    $requested = $requested_url ?? (is_ssl() ? 'https://' : 'http://') . $request->host . $request->path . $request->queryStringWithout();
    $location = apply_filters('redirect_canonical', $location, $requested);
    if (!$location || $location === $requested) {
        return null;
    }
    if (!$do_redirect) {
        return $location;
    }
    if (wp_redirect($location, is_array($found) ? (int) $found[1] : 301)) {
        throw new Minn\Theme\Printed();
    }
    return null;
}

/** On a month archive, prefix, month name, prefix, year (from m or year and monthnum); false elsewhere. */
function single_month_title($prefix = '', $display = true)
{
    $m = (string) get_query_var('m');
    [$year, $month] = $m !== '' ? [(int) substr($m, 0, 4), (int) substr($m, 4, 2)] : [(int) get_query_var('year'), (int) get_query_var('monthnum')];
    if ($year === 0 || $month === 0) {
        return false;
    }
    $title = $prefix . $GLOBALS['wp_locale']->get_month($month) . $prefix . $year;
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}

/** On a post type archive, the type's plural name (post_type_archive_title) after a prefix; null elsewhere. */
function post_type_archive_title($prefix = '', $display = true)
{
    if (!is_post_type_archive()) {
        return null;
    }
    $type = get_query_var('post_type');
    $type = is_array($type) ? (string) reset($type) : (string) $type;
    $object = get_post_type_object($type);
    if ($object === null) {
        return null;
    }
    $title = $prefix . apply_filters('post_type_archive_title', $object->labels->name, $type);
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}
