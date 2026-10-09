<?php
/**
 * The rest of link-template.php (probe link-template-remaining): the site,
 * network, user and dashboard addresses, feed and search links (pretty and
 * plain), attachment, custom type and delete links, comment edit links, the
 * adjacent post and its links, page number links and the paging tags, the
 * posts pagination and post navigation markup, the comment paging links, and
 * short links. Run from the queries a theme runs them in. The probe's posts,
 * comment, terms and post type are removed at the end. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'comments' => []];
$server = $_SERVER;
register_shutdown_function(static function () use (&$made, $server): void {
    $_SERVER = $server;
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    // WP-CLI keeps the reference's own address on https (and makes 'relative' absolute) with priority-0
    // filters the engine's runner does not have, so the scheme of the site's address is masked whole.
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    $value = str_replace($host, '{host}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return (string) preg_replace('/_wpnonce=[0-9a-f]{10}/', '_wpnonce={nonce}', $value);
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
wp_set_current_user(1);

$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$catA = $term('zz ltr a', 'category');
$catB = $term('zz ltr b', 'category');
$term('zz ltr tag', 'post_tag');
$post = static function (string $title, string $date, array $extra = []) use (&$made, &$ids): int {
    $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_date' => $date, 'post_content' => 'x']);
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
register_post_type('zz_ltr_book', ['public' => true, 'rewrite' => ['slug' => 'zz-books'], 'hierarchical' => false]);
register_post_type('zz_ltr_hidden', ['public' => false, 'publicly_queryable' => false, 'rewrite' => false]);
// Long before the site's own posts (2026 on), so nothing else sits between or before them.
$first = $post('zz ltr first', '1989-03-01 10:00:00', ['post_category' => [$catA]]);
$other = $post('zz ltr other', '1989-03-02 10:00:00', ['post_category' => [$catB]]);
$middle = $post('zz ltr middle "q" & co', '1989-03-03 10:00:00', ['post_category' => [$catA, $catB], 'tags_input' => ['zz ltr tag']]);
$last = $post('zz ltr last', '1989-03-04 10:00:00', ['post_category' => [$catA], 'tags_input' => ['zz ltr tag']]);
$draft = $post('zz ltr draft', '1989-03-05 10:00:00', ['post_status' => 'draft']);
$page = $post('zz ltr page', '1989-03-05 14:00:00', ['post_type' => 'page']);
$book = $post('zz ltr book', '1989-03-06 10:00:00', ['post_type' => 'zz_ltr_book']);
$bookDraft = $post('zz ltr book draft', '1989-03-06 11:00:00', ['post_type' => 'zz_ltr_book', 'post_status' => 'draft']);
$hidden = $post('zz ltr hidden', '1989-03-06 12:00:00', ['post_type' => 'zz_ltr_hidden']);
$made['posts'][] = $attached = (int) wp_insert_attachment(['post_title' => 'zz ltr attached', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], false, $middle);
$ids[$attached] = 'attached';
$made['posts'][] = $loose = (int) wp_insert_attachment(['post_title' => 'zz ltr loose', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], false, 0);
$ids[$loose] = 'loose';
$made['comments'][] = $comment = (int) wp_insert_comment(['comment_post_ID' => $middle, 'comment_content' => 'zz ltr comment', 'comment_approved' => 1, 'comment_author' => 'Zz']);
$ids[$comment] = 'comment';

$plain = static function (bool $on): void {
    global $wp_rewrite;
    $on ? add_filter('pre_option_permalink_structure', '__return_empty_string') : remove_filter('pre_option_permalink_structure', '__return_empty_string');
    $wp_rewrite->init();
};
$single = static function (int $id, string $var = 'p'): void {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query([$var => $id, 'post_type' => 'any']);
    $wp_query->the_post();
};

$say('site addresses', $mask([
    get_home_url(), get_home_url(null, '/x/'), get_home_url(null, 'y', 'http'), get_home_url(1, '/a?b=c'),
    get_site_url(), get_site_url(null, '/wp-login.php', 'login'), get_site_url(null, 'x', 'http'),
    network_site_url(), network_site_url('/n', 'http'), network_admin_url(), network_admin_url('users.php'), network_admin_url('/a.php', 'http'),
    user_admin_url(), user_admin_url('profile.php'), user_admin_url('/x', 'http'),
    get_dashboard_url(), get_dashboard_url(1), get_dashboard_url(1, 'edit.php'), get_dashboard_url(999999, 'x.php'), get_dashboard_url(1, '/y', 'http'),
    get_edit_profile_url(), get_edit_profile_url(1), get_edit_profile_url(1, 'http'),
]));
$heard = [];
foreach (['home_url', 'site_url', 'network_site_url', 'network_admin_url', 'user_admin_url', 'user_dashboard_url', 'edit_profile_url'] as $hook) {
    add_filter($hook, $listeners[$hook] = static function ($url, ...$rest) use ($hook, &$heard, $mask) {
        $heard[] = $mask([$hook, $url, ...array_map(static fn ($value) => is_object($value) ? get_class($value) : $value, $rest)]);
        return $url;
    }, 10, 5);
}
get_home_url(null, '/h', 'https');
get_site_url(null, '/s');
network_site_url('/ns');
network_admin_url('na.php');
user_admin_url('ua.php');
get_dashboard_url(1, 'd.php');
get_edit_profile_url(1);
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener, 10);
}
$say('address filters', $heard);

$feeds = static fn (): array => [
    get_feed_link(), get_feed_link('rss2'), get_feed_link('atom'), get_feed_link('rss'), get_feed_link('rdf'), get_feed_link('comments_rss2'), get_feed_link('comments_atom'), get_feed_link('zz'),
    get_search_link('zz term'), get_search_link('a/b c&d'), get_search_link('ünï'), get_search_link(''),
];
$say('feed and search links', $mask($feeds()));
$plain(true);
$say('feed and search links, plain permalinks', $mask($feeds()));
$plain(false);
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['s' => 'zz searched']);
$say('search link from the query', $mask([get_search_link(), get_search_query()]));

$say('attachment and type links', $mask([
    get_attachment_link($attached), get_attachment_link($attached, true), get_attachment_link($loose), get_attachment_link($loose, true),
    get_post_permalink($book), get_post_permalink($book, true), get_post_permalink($bookDraft), get_post_permalink($bookDraft, false, true), get_post_permalink($bookDraft, true, true),
    get_post_permalink($hidden), get_post_permalink($middle), get_post_permalink(999999),
]));
$plain(true);
$say('attachment and type links, plain permalinks', $mask([get_attachment_link($attached), get_attachment_link($loose), get_post_permalink($book), get_post_permalink($bookDraft), get_post_permalink($hidden)]));
$plain(false);

$single($middle);
$say('the permalink', $mask([$echoed(static fn () => the_permalink()), $echoed(static fn () => the_permalink($page)), $echoed(static fn () => the_permalink($book))]));

$say('delete links', $mask([
    get_delete_post_link($middle), get_delete_post_link($middle, '', true), get_delete_post_link($page), get_delete_post_link($book), get_delete_post_link(999999),
]));
wp_set_current_user(0);
$say('delete links, signed out', $mask([get_delete_post_link($middle), get_delete_post_link($middle, '', true)]));
wp_set_current_user(1);

$GLOBALS['comment'] = get_comment($comment);
$say('comment edit links', $mask([
    get_edit_comment_link($comment), get_edit_comment_link($comment, 'url'), get_edit_comment_link(), get_edit_comment_link(999999),
    $echoed(static fn () => edit_comment_link()), $echoed(static fn () => edit_comment_link('Change', '<b>', '</b>')), $echoed(static fn () => edit_comment_link('<i>Edit</i> "this"')),
]));
wp_set_current_user(0);
$say('comment edit links, signed out', $mask([get_edit_comment_link($comment), $echoed(static fn () => edit_comment_link())]));
wp_set_current_user(1);
unset($GLOBALS['comment']);

$single($middle);
$adjacent = static fn ($post) => $post instanceof WP_Post ? $post->ID : $post;
$say('adjacent posts', $mask([
    $adjacent(get_adjacent_post()), $adjacent(get_adjacent_post(false, '', false)), $adjacent(get_adjacent_post(true)), $adjacent(get_adjacent_post(true, '', false)),
    $adjacent(get_adjacent_post(false, (string) $catB)), $adjacent(get_adjacent_post(false, [$catA], false)), $adjacent(get_adjacent_post(true, '', false, 'post_tag')), $adjacent(get_adjacent_post(true, '', true, 'post_tag')),
]));
$say('adjacent post links', $mask([
    get_adjacent_post_link('%link &raquo;', '%title'), get_adjacent_post_link('&laquo; %link', '%title on %date', false, '', false), get_adjacent_post_link('[%link]', 'Same: %title', true, '', false),
    get_adjacent_post_link('%link', '%title', true, '', true, 'post_tag'), get_adjacent_post_link('%link', '%title', false, (string) $catA, false),
    $echoed(static fn () => next_post_link()), $echoed(static fn () => previous_post_link()), $echoed(static fn () => next_post_link('<b>%link</b>', 'Next: %title', true)), $echoed(static fn () => previous_post_link('%link', '%title', false, '', 'post_tag')),
]));
$single($first);
$say('adjacent post links at the first post', $mask([
    $adjacent(get_adjacent_post()), get_adjacent_post_link('%link', '%title'), $echoed(static fn () => previous_post_link()), $echoed(static fn () => next_post_link()),
    $adjacent(get_adjacent_post(true, '', false, 'post_tag')), $adjacent(get_adjacent_post(true, '', false, 'zz_ltr_missing')), $adjacent(get_adjacent_post(true, [$catA], false)),
]));
$single($page, 'page_id');
$say('adjacent posts of a page', $mask([$adjacent(get_adjacent_post()), $adjacent(get_adjacent_post(true)), $adjacent(get_adjacent_post(false, '', false))]));
$GLOBALS['post'] = null;
$say('adjacent post with no post', [get_adjacent_post(), get_adjacent_post_link('%link', '%title')]);

$say('the post navigation', $mask([
    (static function () use ($single, $middle, $echoed) {
        $single($middle);
        return [$echoed(static fn () => the_post_navigation()), $echoed(static fn () => the_post_navigation(['prev_text' => 'Back: %title', 'next_text' => 'On: %title', 'screen_reader_text' => 'Read more', 'aria_label' => 'More posts', 'class' => 'zz-nav', 'in_same_term' => true]))];
    })(),
    _navigation_markup('<a href="#">x</a>'), _navigation_markup('<a>y</a>', 'pagination', 'Posts pages', 'Pages'), _navigation_markup('', 'zz class', '', ''), _navigation_markup('<b>z</b>', 'zz', 'Read <b>this</b>'),
]));

$archive = static function (int $page, string $uri) use ($catA): void {
    global $wp_query, $wp_the_query;
    $_SERVER['REQUEST_URI'] = $uri;
    $wp_the_query = $wp_query = new WP_Query(['post_type' => 'post', 'posts_per_page' => 1, 'paged' => $page, 'date_query' => [['year' => 1989]], 'category__in' => [$catA]]);
    $GLOBALS['paged'] = $page;
};
$archive(2, '/category/zz-ltr-a/page/2/');
$say('paging on page 2', $mask([
    $GLOBALS['wp_query']->max_num_pages, get_pagenum_link(), get_pagenum_link(2), get_pagenum_link(3), get_pagenum_link(1, false), get_pagenum_link(5, false),
    next_posts(0, false), next_posts(2, false), previous_posts(false), $echoed(static fn () => next_posts()), $echoed(static fn () => previous_posts()),
    get_the_posts_pagination(), get_the_posts_pagination(['mid_size' => 0, 'prev_text' => 'Back', 'next_text' => 'On', 'screen_reader_text' => 'Pages', 'aria_label' => 'Paging', 'class' => 'zz-paging']),
    $echoed(static fn () => the_posts_pagination(['show_all' => true])),
]));
$archive(1, '/category/zz-ltr-a/?orderby=title&zz=a%20b');
$say('paging on page 1 with a query string', $mask([
    get_pagenum_link(), get_pagenum_link(2), get_pagenum_link(2, false), next_posts(0, false), previous_posts(false), get_the_posts_pagination(),
]));
$archive(3, '/?cat=' . $catA . '&paged=3');
$say('paging on page 3 of plain links', $mask([get_pagenum_link(), get_pagenum_link(2), get_pagenum_link(4), next_posts(0, false), previous_posts(false)]));
$plain(true);
$archive(2, '/?cat=' . $catA . '&paged=2');
$say('paging with plain permalinks', $mask([get_pagenum_link(), get_pagenum_link(3), next_posts(0, false), previous_posts(false), get_the_posts_pagination()]));
$plain(false);
$pages = static function () {
    $out = [];
    foreach (['/category/zz-ltr-a/page/2/?page=3&x=1', '/zz/page/2', '/zz/page/12/more/', '/?paged=2&s=q+r', '/zz/?paged=4&a[]=1&a[]=2', '/zz/page/2/#frag', '/index.php/zz/page/2/', '', '/?', '/%7Ezz/'] as $uri) {
        $_SERVER['REQUEST_URI'] = $uri;
        $out[] = [$uri, get_pagenum_link(1, false), get_pagenum_link(2, false), get_pagenum_link(3)];
    }
    return $out;
};
$say('page number links from request addresses', $mask($pages()));
$plain(true);
$say('page number links from request addresses, plain permalinks', $mask($pages()));
$plain(false);
add_filter('pre_option_permalink_structure', $bare = static fn () => '/%postname%');
$GLOBALS['wp_rewrite']->init();
$say('page number links from request addresses, no trailing slash', $mask($pages()));
remove_filter('pre_option_permalink_structure', $bare);
$GLOBALS['wp_rewrite']->init();
$archive(1, '/category/zz-ltr-a/');
$GLOBALS['wp_query']->max_num_pages = 1;
$say('paging with one page', $mask([get_the_posts_pagination(), next_posts(0, false), previous_posts(false)]));

$single($middle);
$_SERVER['REQUEST_URI'] = '/zz-ltr-middle/comment-page-2/';
set_query_var('cpage', 2);
$say('comment paging links', $mask([
    $echoed(static fn () => next_comments_link()), $echoed(static fn () => next_comments_link('Newer', 3)), $echoed(static fn () => next_comments_link('Newer', 2)),
    $echoed(static fn () => previous_comments_link()), $echoed(static fn () => previous_comments_link('<b>Older</b>')),
]));
set_query_var('cpage', 1);
$say('comment paging links on the first page', $mask([$echoed(static fn () => next_comments_link('', 3)), $echoed(static fn () => previous_comments_link())]));
set_query_var('cpage', 0);

$single($middle);
$say('short links', $mask([
    wp_get_shortlink(), wp_get_shortlink($middle), wp_get_shortlink($page), wp_get_shortlink($book), wp_get_shortlink($draft), wp_get_shortlink(0, 'query'), wp_get_shortlink($middle, 'post', false), wp_get_shortlink(999999),
    $echoed(static fn () => wp_shortlink_wp_head()),
]));
$heard = [];
add_filter('pre_get_shortlink', $pre = static function ($short, $id, $context, $slugs) use (&$heard) {
    $heard[] = ['pre_get_shortlink', $short, $id, $context, $slugs];
    return $short;
}, 10, 4);
add_filter('get_shortlink', $after = static function ($short, $id, $context, $slugs) use (&$heard) {
    $heard[] = ['get_shortlink', $short, $id, $context, $slugs];
    return $short;
}, 10, 4);
wp_shortlink_header();
$echoed(static fn () => wp_shortlink_wp_head());
remove_filter('pre_get_shortlink', $pre, 10);
remove_filter('get_shortlink', $after, 10);
$say('short link filters from the head and the header', $mask($heard));
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 1]);
$say('short links off a single post', $mask([wp_get_shortlink(0, 'query'), $echoed(static fn () => wp_shortlink_wp_head())]));

unregister_post_type('zz_ltr_book');
unregister_post_type('zz_ltr_hidden');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
