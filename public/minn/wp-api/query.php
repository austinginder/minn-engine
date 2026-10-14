<?php
/** The main query, query vars, conditional tags, and the loop. */

use Minn\Front\Canonical;
use Minn\Runtime\Runtime;

/** @internal the main query the page runs on; a CLI boot gets an empty one */
function _minn_main_query(): WP_Query
{
    return Minn\Runtime\MainQuery::current();
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
    return Minn\Runtime\MainQuery::searchQuery($escaped);
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

/**
 * The view's title for a classic theme (probe general-template-remaining):
 * its parts (a taxonomy's name then the term, a year then month then day,
 * "Search Results" then the words) joined by the separator, which also
 * leads the title, or trails it with the parts reversed when the separator
 * goes on the right. A year query var alone and the posts page have none.
 * wp_title_parts hears the parts in reading order, even when there are none;
 * the wp_title filter's defaults texturize and escape the result.
 */
function wp_title($sep = '&raquo;', $display = true, $seplocation = '')
{
    $parts = (array) apply_filters('wp_title_parts', _minn_wp_title_parts());
    $title = '';
    if ($parts !== []) {
        $title = $seplocation === 'right' ? implode(" {$sep} ", array_reverse($parts)) . " {$sep} " : " {$sep} " . implode(" {$sep} ", $parts);
    }
    $title = apply_filters('wp_title', $title, $sep, $seplocation);
    if ($display) {
        echo $title;
        return null;
    }
    return $title;
}

/** @internal the parts of the main query's wp_title, empty for a view with none */
function _minn_wp_title_parts(): array
{
    $object = get_queried_object();
    $title = match (true) {
        is_search() => ['Search Results ', ' ' . strip_tags((string) get_search_query(false))],
        is_404() => [__('Page not found')],
        is_home() || is_singular() => [(string) single_post_title('', false)],
        is_category() || is_tag() => [(string) single_term_title('', false)],
        is_tax() && $object instanceof WP_Term => [(string) (get_taxonomy($object->taxonomy)->labels->name ?? ''), (string) single_term_title('', false)],
        is_post_type_archive() => [(string) post_type_archive_title('', false)],
        is_author() && $object instanceof WP_User => [$object->display_name],
        is_archive() => _minn_wp_title_date(),
        default => [],
    };
    return $title === [''] ? [] : $title;
}

/** @internal a date archive's wp_title parts: from m when given (a year alone counts), else from the year and month (a year alone does not) */
function _minn_wp_title_date(): array
{
    $m = (string) get_query_var('m');
    [$year, $month, $day] = $m !== '' ? [substr($m, 0, 4), substr($m, 4, 2), substr($m, 6, 2)] : [(string) get_query_var('year'), (int) get_query_var('monthnum') ? zeroise((int) get_query_var('monthnum'), 2) : '', ''];
    if ($m === '' && $month === '') {
        return [];
    }
    $day = $m === '' && get_query_var('day') ? zeroise((int) get_query_var('day'), 2) : $day;
    return array_values(array_filter([$year, $month === '' ? '' : $GLOBALS['wp_locale']->get_month($month), $day], static fn ($part) => $part !== ''));
}

function single_post_title($prefix = '', $display = true)
{
    return Minn\Runtime\DocumentTitle::post($prefix, $display);
}

/** The reference titles archives as a label prefix around the bare name: `Category: <span>Uncategorized</span>`. */
function get_the_archive_title()
{
    [$prefix, $original] = _minn_archive_title_parts();
    $prefix = $prefix === '' ? '' : (string) apply_filters('get_the_archive_title_prefix', $prefix);
    $title = $prefix === '' ? $original : $prefix . ' <span>' . $original . '</span>';
    return apply_filters('get_the_archive_title', $title, $original, $prefix);
}

/**
 * @internal The archive title's prefix and name for the main query (probe
 * general-template-remaining): "Archives" with no prefix for a view that
 * is not an archive, search included.
 */
function _minn_archive_title_parts(): array
{
    $object = get_queried_object();
    return match (true) {
        is_category() => [_x('Category:', 'category archive title prefix'), (string) single_cat_title('', false)],
        is_tag() => [_x('Tag:', 'tag archive title prefix'), (string) single_tag_title('', false)],
        is_author() && $object instanceof WP_User => [_x('Author:', 'author archive title prefix'), $object->display_name],
        is_year() => [_x('Year:', 'date archive title prefix'), get_the_date(_x('Y', 'yearly archives date format'))],
        is_month() => [_x('Month:', 'date archive title prefix'), get_the_date(_x('F Y', 'monthly archives date format'))],
        is_day() => [_x('Day:', 'date archive title prefix'), get_the_date(_x('F j, Y', 'daily archives date format'))],
        is_post_type_archive() => [_x('Archives:', 'post type archive title prefix'), (string) post_type_archive_title('', false)],
        is_tax() && $object instanceof WP_Term => [(get_taxonomy($object->taxonomy)->labels->singular_name ?? '') . ':', (string) single_term_title('', false)],
        default => ['', __('Archives')],
    };
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

/** The document title from the main query's state, through the reference's title filters; ready to print inside <title>. */
function wp_get_document_title()
{
    return Minn\Runtime\DocumentTitle::get();
}

/** @internal the document title's parts as the reference makes them from the main query's state (probe feed-tags) */
function _minn_query_title_parts()
{
    return Minn\Runtime\DocumentTitle::parts();
}

/**
 * A slug the post no longer has sends its 404 to the post's current link, through old_slug_redirect_url: the move the
 * engine found as it resolved the request, or, called on any other 404, the one the query names (probe query-remaining).
 */
function wp_old_slug_redirect()
{
    if (!is_404()) {
        return;
    }
    // The front's own resolution when it found the move, else the move worked out from the query (probe query-remaining).
    $found = Runtime::current()->get('old_slug_location');
    $link = is_array($found) ? $found[0] : (Runtime::current()->get('old_slug_settled') ? false : _minn_old_slug_link());
    if (!$link) {
        return;
    }
    $link = apply_filters('old_slug_redirect_url', $link);
    if ($link && wp_redirect($link, 301)) {
        throw new Minn\Theme\Printed();
    }
}

/**
 * @internal Where a 404 for a former name should go (probe query-remaining):
 * for one non-hierarchical queried type, the post found by its old date or old
 * name (through old_slug_redirect_post_id), with the page or embed the request
 * asked for.
 */
function _minn_old_slug_link()
{
    if ((string) get_query_var('name') === '') {
        return false;
    }
    $post_type = get_query_var('post_type');
    if (is_array($post_type)) {
        if (count($post_type) > 1) {
            return false;
        }
        $post_type = reset($post_type);
    }
    $post_type = (string) ($post_type ?: 'post');
    // A hierarchical type's addresses keep no former names.
    if (is_post_type_hierarchical($post_type)) {
        return false;
    }
    $id = (int) apply_filters('old_slug_redirect_post_id', _find_post_by_old_date($post_type) ?: _find_post_by_old_slug($post_type));
    if ($id === 0) {
        return false;
    }
    $link = (string) get_permalink($id);
    $paged = (int) get_query_var('paged');
    if ($paged > 1) {
        return user_trailingslashit(trailingslashit($link) . 'page/' . $paged);
    }
    return is_embed() ? user_trailingslashit(trailingslashit($link) . 'embed') : $link;
}

/** The post of a type that used to have the requested name, on the date the request names; 0 when none. Cached until posts change. */
function _find_post_by_old_slug($post_type)
{
    global $wpdb;
    $sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta}, {$wpdb->posts} WHERE ID = post_id AND post_type = %s AND meta_key = '_wp_old_slug' AND meta_value = %s", $post_type, get_query_var('name')) . _minn_query_date_sql('post_date');
    return _minn_cached_post_query('find_post_by_old_slug', $sql, static fn () => (int) $wpdb->get_var($sql));
}

/**
 * The post of a type that had the requested date when the request names
 * one: by its name and an old date, else by an old name and an old date;
 * 0 when none, or when the request names no date. Cached until posts change.
 */
function _find_post_by_old_date($post_type)
{
    global $wpdb;
    $date = _minn_query_date_sql('pm_date.meta_value');
    if ($date === '') {
        return 0;
    }
    $name = get_query_var('name');
    $sql = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} AS pm_date, {$wpdb->posts} WHERE ID = post_id AND post_type = %s AND meta_key = '_wp_old_date' AND post_name = %s", $post_type, $name) . $date;
    $renamed = $wpdb->prepare("SELECT ID FROM {$wpdb->posts}, {$wpdb->postmeta} AS pm_slug, {$wpdb->postmeta} AS pm_date WHERE ID = pm_slug.post_id AND ID = pm_date.post_id AND post_type = %s AND pm_slug.meta_key = '_wp_old_slug' AND pm_slug.meta_value = %s AND pm_date.meta_key = '_wp_old_date'", $post_type, $name) . $date;
    return _minn_cached_post_query('find_post_by_old_date', $sql, static fn () => (int) $wpdb->get_var($sql) ?: (int) $wpdb->get_var($renamed));
}

/** @internal a lookup's answer from the post-queries cache, salted with posts' last_changed, keyed by its first query */
function _minn_cached_post_query(string $prefix, string $sql, Closure $find): int
{
    $key = $prefix . ':' . md5($sql);
    $salt = wp_cache_get_last_changed('posts');
    $cached = wp_cache_get_salted($key, 'post-queries', $salt);
    if ($cached !== false) {
        return (int) $cached;
    }
    $id = $find();
    wp_cache_set_salted($key, $id, 'post-queries', $salt);
    return $id;
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
    // On a 404 the reference only guesses: a move for a page that was found as typed stands down when the main query found it empty.
    if ($requested_url === null && (!is_array($found) || (($found[2] ?? false) && is_404()))) {
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
    return Minn\Runtime\DocumentTitle::postTypeArchive($prefix, $display);
}

/**
 * A date archive's query vars read as the post they name (probe
 * plugin-queue). Under a structure with %postname% first, second or third,
 * the year, month or day in that place is a slug; when a post has it, was
 * dated in the year (and month) the URL gives, and what follows the slug
 * is one of its pages, the vars become that post's name and page (0 for
 * none). Anything else is left as a date archive.
 */
function wp_resolve_numeric_slug_conflicts($query_vars = [])
{
    if (!isset($query_vars['year']) && !isset($query_vars['monthnum']) && !isset($query_vars['day'])) {
        return $query_vars;
    }
    $index = array_search('%postname%', array_values(array_filter(explode('/', (string) get_option('permalink_structure')))), true);
    $slot = is_int($index) ? (['year', 'monthnum', 'day'][$index] ?? null) : null;
    $post = $slot !== null && isset($query_vars[$slot]) ? get_page_by_path((string) $query_vars[$slot], OBJECT, 'post') : null;
    if (!$post instanceof WP_Post) {
        return $query_vars;
    }
    $dated = $slot === 'year' || !isset($query_vars['year']) || ((int) $query_vars['year'] === (int) substr($post->post_date, 0, 4) && ($slot !== 'day' || !isset($query_vars['monthnum']) || (int) $query_vars['monthnum'] === (int) substr($post->post_date, 5, 2)));
    $next = ['year' => 'monthnum', 'monthnum' => 'day'][$slot] ?? null;
    $page = $next !== null ? (int) trim((string) ($query_vars[$next] ?? ''), '/') : 0;
    $pages = substr_count($post->post_content, '<!--nextpage-->') + 1;
    if (!$dated || ($page && ($pages === 1 || $page > $pages))) {
        return $query_vars;
    }
    $query_vars['page'] = $page;
    unset($query_vars['year'], $query_vars['monthnum'], $query_vars['day']);
    $query_vars['name'] = $post->post_name;
    return $query_vars;
}

/** Whether the request is for a sitemap. */
function is_sitemap()
{
    return (bool) get_query_var('sitemap');
}
