<?php
/**
 * Behaviour probe for the classic template tags the popular themes stand on:
 * parent-theme file lookup, posts and comments navigation, the comment and
 * author links, the custom logo, nav-menu names, thumbnail captions. Same
 * protocol as api-probe.php. These are the symbols the catalogue scan named
 * as the top of the theme work queue.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
// The two stacks disagree on the scheme they serve under, so both forms of
// the host fold to the same token.
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$rel = static fn ($v) => is_string($v)
    ? str_replace(['https://' . $bare, 'http://' . $bare, rtrim(ABSPATH, '/')], ['{home}', '{home}', '{abspath}'], $v)
    : $v;
$deep = static function ($v) use (&$deep, $rel) {
    return is_array($v) ? array_map($deep, $v) : $rel($v);
};
$capture = static function (callable $fn) use ($deep): string {
    ob_start();
    $fn();
    return (string) $deep(ob_get_clean());
};
wp_set_current_user(1);

// --- Parent theme files. The child/parent split is what the tags exist for,
// and the dev site carries no child theme, so the parent's directory is moved
// with the filters the reference resolves it through.
$parent = dirname(get_template_directory()) . '/twentytwentyfour';
add_filter('template_directory', static fn () => $parent);
add_filter('template_directory_uri', static fn () => 'https://example.test/parent');
$say('split', $deep([get_stylesheet_directory() !== get_template_directory(), basename(get_template_directory())]));
$say('theme file paths', $deep([
    get_parent_theme_file_path(),
    get_parent_theme_file_path('style.css'),
    get_parent_theme_file_path('/style.css'),
    get_parent_theme_file_path('does-not-exist.php'),
    get_theme_file_path('style.css'),
]));
$say('theme file uris', $deep([
    get_parent_theme_file_uri(),
    get_parent_theme_file_uri('style.css'),
    get_parent_theme_file_uri('/assets/x.js'),
    get_theme_file_uri('style.css'),
]));
add_filter('parent_theme_file_path', static fn ($path, $file) => $file === 'style.css' ? '/filtered/path' : $path, 10, 2);
add_filter('parent_theme_file_uri', static fn ($uri, $file) => $file === 'style.css' ? 'https://filtered/uri' : $uri, 10, 2);
$say('theme file filters', [get_parent_theme_file_path('style.css'), get_parent_theme_file_uri('style.css')]);
remove_all_filters('parent_theme_file_path');
remove_all_filters('parent_theme_file_uri');
remove_all_filters('template_directory');
remove_all_filters('template_directory_uri');

// --- Posts navigation. A three-page listing so both directions exist.
$GLOBALS['wp_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 2, 'paged' => 2]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['paged'] = 2;
$say('posts page links', $deep([
    get_previous_posts_page_link(),
    get_next_posts_page_link(),
    get_next_posts_page_link(5),
]));
$say('get_next_posts_link', $deep([
    get_next_posts_link(),
    get_next_posts_link('Older'),
    get_next_posts_link('Older', 3),
    get_next_posts_link('Older', 2),
]));
$say('get_previous_posts_link', $deep([
    get_previous_posts_link(),
    get_previous_posts_link('Newer &raquo;'),
]));
$say('next_posts_link echo', $capture(static fn () => next_posts_link('Older posts')));
$say('previous_posts_link echo', $capture(static fn () => previous_posts_link()));
$say('get_the_posts_navigation', $deep([
    get_the_posts_navigation(),
    get_the_posts_navigation(['prev_text' => 'P', 'next_text' => 'N', 'screen_reader_text' => 'SR']),
    get_the_posts_navigation(['aria_label' => 'AL', 'class' => 'my-nav']),
]));
$say('the_posts_navigation echo', $capture(static fn () => the_posts_navigation()));
add_filter('next_posts_link_attributes', static fn () => 'class="next-attr"');
add_filter('previous_posts_link_attributes', static fn () => 'class="prev-attr"');
$say('posts link attributes', $deep([get_next_posts_link('N'), get_previous_posts_link('P')]));
remove_all_filters('next_posts_link_attributes');
remove_all_filters('previous_posts_link_attributes');

// On page one there is no previous link, and past the end no next link.
$GLOBALS['wp_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 2, 'paged' => 1]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['paged'] = 1;
$say('page one links', $deep([get_previous_posts_link('P'), get_next_posts_link('N'), get_previous_posts_page_link()]));
$say('page one navigation', $deep(get_the_posts_navigation()));

// A single-page listing prints no navigation at all.
$GLOBALS['wp_query'] = new WP_Query(['post_type' => 'post', 'posts_per_page' => 100]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$say('single page navigation', $deep([get_the_posts_navigation(), get_next_posts_link('N'), get_previous_posts_link('P')]));

// --- Comments. Post 1 carries the seeded comment.
$GLOBALS['wp_query'] = new WP_Query(['p' => 1]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
$say('comments_link echo', $capture(static fn () => comments_link()));
$say('comments_popup_link default', $capture(static fn () => comments_popup_link()));
$say('comments_popup_link texts', $capture(static fn () => comments_popup_link('None', 'One', '% many', 'cls', 'Closed')));
$say('the_post_thumbnail_caption', $deep([get_the_post_thumbnail_caption(), get_the_post_thumbnail_caption(1), $capture(static fn () => the_post_thumbnail_caption())]));

$comments = get_comments(['post_id' => 1, 'number' => 1]);
$comment = $comments[0] ?? null;
if ($comment !== null) {
    $GLOBALS['comment'] = $comment;
    $say('comment_author_link echo', $capture(static fn () => comment_author_link()));
    $say('comment_author_link id', $deep(get_comment_author_link($comment)));
    // An empty id falls back to the comment being read, or it does not; the
    // date tags stand on the answer.
    $say('get_comment empty id', [
        get_comment(0)?->comment_ID === $comment->comment_ID,
        get_comment(null)?->comment_ID === $comment->comment_ID,
        get_comment('')?->comment_ID === $comment->comment_ID,
    ]);
    $say('comment_date echo', $capture(static fn () => comment_date()));
    $say('comment_date format', $capture(static fn () => comment_date('Y-m-d')));
    $say('comment_time echo', $capture(static fn () => comment_time()));
    $say('comment_time format', $capture(static fn () => comment_time('H:i')));
}
$say('get_the_comments_navigation', $deep([
    get_the_comments_navigation(),
    get_the_comments_navigation(['screen_reader_text' => 'CN', 'class' => 'c-nav']),
]));
$say('get_the_comments_pagination', $deep([
    get_the_comments_pagination(),
    get_the_comments_pagination(['screen_reader_text' => 'CP']),
]));
// Which page of a thread the bare permalink stands for, across both the
// paging switch and both reading orders.
$pagenum = [];
foreach ([true, false] as $paging) {
    foreach (['oldest', 'newest'] as $order) {
        add_filter('option_page_comments', $paging ? '__return_true' : '__return_false');
        add_filter('option_default_comments_page', static fn () => $order);
        foreach ([0, 1, 3] as $max) {
            $key = ($paging ? 'paged' : 'unpaged') . ' ' . $order . ' max=' . $max;
            $pagenum[$key] = $deep([get_comments_pagenum_link(1, $max), get_comments_pagenum_link(2, $max), get_comments_pagenum_link(3, $max)]);
        }
        $pagenum[($paging ? 'paged' : 'unpaged') . ' ' . $order . ' context'] = [
            (string) get_option('default_comments_page'),
            (int) get_comment_pages_count(),
            (int) ($GLOBALS['wp_query']->max_num_comment_pages ?? 0),
        ];
        remove_all_filters('option_page_comments');
        remove_all_filters('option_default_comments_page');
    }
}
$say('comments pagenum link matrix', $pagenum);

// Standing more than one comment page on the query object exercises the
// navigation markup without writing comments the read fixtures would notice.
$GLOBALS['wp_query']->max_num_comment_pages = 3;
$GLOBALS['wp_query']->set('cpage', 2);
$GLOBALS['cpage'] = 2;
add_filter('option_page_comments', '__return_true');
add_filter('option_comments_per_page', static fn () => 1);
add_filter('option_default_comments_page', static fn () => 'newest');
$say('comment page links', $deep([
    get_previous_comments_link(),
    get_previous_comments_link('Older comments'),
    get_next_comments_link(),
    get_next_comments_link('Newer comments'),
    get_next_comments_link('Newer', 3),
]));
$say('paged comments navigation', $deep([
    get_the_comments_navigation(),
    get_the_comments_navigation(['prev_text' => 'CP', 'next_text' => 'CN', 'screen_reader_text' => 'SRC']),
]));
$say('paged comments pagination', $deep([
    get_the_comments_pagination(),
    get_the_comments_pagination(['screen_reader_text' => 'CP2', 'class' => 'c-pag']),
]));
$say('paged comments echo', [
    $capture(static fn () => the_comments_navigation()),
    $capture(static fn () => the_comments_pagination()),
]);
// The default-comments-page setting inverts which direction each link points,
// so both settings are walked across every page of a three-page thread.
remove_all_filters('option_default_comments_page');
$matrix = [];
foreach (['newest', 'oldest'] as $default) {
    add_filter('option_default_comments_page', static fn () => $default);
    foreach ([1, 2, 3] as $page) {
        $GLOBALS['wp_query']->set('cpage', $page);
        $GLOBALS['cpage'] = $page;
        $matrix["{$default} page {$page}"] = $deep([get_previous_comments_link('P'), get_next_comments_link('N')]);
    }
    remove_all_filters('option_default_comments_page');
}
$say('comment page matrix', $matrix);
$GLOBALS['wp_query']->set('cpage', 0);
$GLOBALS['cpage'] = 1;
remove_all_filters('option_page_comments');
remove_all_filters('option_comments_per_page');
remove_all_filters('option_default_comments_page');
$GLOBALS['wp_query']->max_num_comment_pages = 0;

$say('comments navigation echo', [
    $capture(static fn () => the_comments_navigation()),
    $capture(static fn () => the_comments_pagination()),
]);

// --- The custom logo and the author link, both echo wrappers with filters.
$say('the_custom_logo', $deep([$capture(static fn () => the_custom_logo()), get_custom_logo()]));
$say('the_author_posts_link', $deep([
    $capture(static fn () => the_author_posts_link()),
    get_the_author_posts_link(),
]));

// --- Nav menu names. A crashed run leaves the menu behind, so sweep by name
// before creating it.
$stale = wp_get_nav_menu_object('Minn Probe Menu');
if ($stale) {
    wp_delete_nav_menu($stale->term_id);
}
$menu = wp_create_nav_menu('Minn Probe Menu');
$say('wp_create_nav_menu', [
    is_wp_error($menu) ? 'error' : (is_int($menu) && $menu > 0 ? 'int id' : gettype($menu)),
    is_wp_error($menu) ? null : wp_get_nav_menu_object($menu)?->name,
]);
$duplicate = wp_create_nav_menu('Minn Probe Menu');
$say('wp_create_nav_menu duplicate', is_wp_error($duplicate) ? [$duplicate->get_error_code(), $duplicate->get_error_message()] : 'no error');
$empty = wp_create_nav_menu('');
$say('wp_create_nav_menu empty', is_wp_error($empty) ? [$empty->get_error_code(), $empty->get_error_message()] : 'no error');
$say('wp_get_nav_menu_name', $deep([
    wp_get_nav_menu_name('nowhere'),
    is_wp_error($menu) ? 'error' : 'created',
]));
register_nav_menus(['minn_probe_location' => 'Minn Probe Location']);
if (!is_wp_error($menu)) {
    set_theme_mod('nav_menu_locations', ['minn_probe_location' => $menu]);
    $say('wp_get_nav_menu_name located', wp_get_nav_menu_name('minn_probe_location'));
    $say('wp_update_nav_menu_object rename', [
        is_wp_error(wp_update_nav_menu_object($menu, ['menu-name' => 'Minn Probe Renamed'])) ? 'error' : 'ok',
        wp_get_nav_menu_object($menu)?->name,
    ]);
    $say('wp_delete_nav_menu', [wp_delete_nav_menu($menu), wp_get_nav_menu_object($menu) ? 'still there' : 'gone']);
    $say('wp_delete_nav_menu again', is_wp_error(wp_delete_nav_menu($menu)) ? 'error' : 'no error');
}
remove_theme_mod('nav_menu_locations');
$say('wp_get_nav_menu_name gone', $deep(wp_get_nav_menu_name('minn_probe_location')));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
