<?php
/**
 * The rules WP_Rewrite::generate_rewrite_rules makes from a structure, as
 * the reference makes them (probe rewrite-generate): for a matrix of
 * permalink structures (a name, dates, an id, a category, a front, no
 * trailing slash, PATHINFO) and of structures handed to it (a post's, a
 * date's, a term's, a page's, a search's, a feed's), each endpoint mask,
 * with and without pages, feeds, comment feeds, walked directories and
 * endpoints; with an endpoint and a rewrite tag of a plugin's. Each case
 * on a fresh WP_Rewrite, so nothing global changes. Read only. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$fresh = static function (string $structure): WP_Rewrite {
    $pinned = static fn () => $structure;
    add_filter('pre_option_permalink_structure', $pinned);
    $rewrite = new WP_Rewrite();
    $rewrite->init();
    remove_filter('pre_option_permalink_structure', $pinned);
    return $rewrite;
};

$structures = ['/%postname%/', '/%year%/%monthnum%/%day%/%postname%/', '/%year%/%monthnum%/%postname%/', '/archives/%post_id%', '/%category%/%postname%/', '/blog/%postname%/', '/%postname%', '/index.php/%postname%/', '/%author%/%postname%/', '/%year%/%post_id%/'];
foreach ($structures as $structure) {
    $rewrite = $fresh($structure);
    $say("{$structure}: the post rules", $rewrite->generate_rewrite_rules($rewrite->permalink_structure, EP_PERMALINK));
    $say("{$structure}: its parts", [$rewrite->front, $rewrite->root, $rewrite->use_trailing_slashes, $rewrite->use_verbose_page_rules, $rewrite->use_verbose_rules, $rewrite->get_date_permastruct(), $rewrite->get_page_permastruct(), $rewrite->get_category_permastruct()]);
}

$rewrite = $fresh('/%postname%/');
$say('dates', $rewrite->generate_rewrite_rules($rewrite->get_date_permastruct(), EP_DATE));
$say('the root', $rewrite->generate_rewrite_rules($rewrite->root . '/', EP_ROOT));
$say('comments', $rewrite->generate_rewrite_rules($rewrite->root . $rewrite->comments_base, EP_COMMENTS, false, true, true, false));
$say('search', $rewrite->generate_rewrite_rules($rewrite->get_search_permastruct(), EP_SEARCH));
$say('author', $rewrite->generate_rewrite_rules($rewrite->get_author_permastruct(), EP_AUTHORS));
$say('pages', $rewrite->generate_rewrite_rules($rewrite->get_page_permastruct(), EP_PAGES, true, true, false, false));
$say('a term permastruct', $rewrite->generate_rewrite_rules('/zz-genre/%zz_genre%', EP_NONE));
$say('a post type permastruct', $rewrite->generate_rewrite_rules('/zz-book/%zz_book%', EP_PERMALINK, true, true, false, true, true));

$flags = ['no pages' => [false, true, false, true, true], 'no feeds' => [true, false, false, true, true], 'comment feeds' => [true, true, true, true, true], 'no walking' => [true, true, false, false, true], 'no endpoints' => [true, true, false, true, false]];
foreach ($flags as $label => [$paged, $feed, $comments, $walk, $endpoints]) {
    $say("dated posts, {$label}", $rewrite->generate_rewrite_rules('/%year%/%monthnum%/%postname%/', EP_PERMALINK, $paged, $feed, $comments, $walk, $endpoints));
}
foreach (['EP_NONE' => EP_NONE, 'EP_ALL' => EP_ALL, 'EP_CATEGORIES' => EP_CATEGORIES, 'EP_ATTACHMENT' => EP_ATTACHMENT] as $label => $mask) {
    $say("a name under {$label}", $rewrite->generate_rewrite_rules('/%postname%/', $mask));
}

$rewrite = $fresh('/%postname%/');
$rewrite->add_endpoint('zz-json', EP_PERMALINK | EP_PAGES, 'zz_json');
$rewrite->add_endpoint('zz-print', EP_ALL, false);
$rewrite->add_endpoint('zz-attach', EP_ATTACHMENT);
$rewrite->add_rewrite_tag('%zz_tag%', '([a-z]+)', 'zz_tag=');
$say('with endpoints: posts', $rewrite->generate_rewrite_rules('/%postname%/', EP_PERMALINK));
$say('with endpoints: the root', $rewrite->generate_rewrite_rules('/', EP_ROOT));
$say('with endpoints: pages', $rewrite->generate_rewrite_rules($rewrite->get_page_permastruct(), EP_PAGES, true, true, false, false));
$say('a plugin tag', $rewrite->generate_rewrite_rules('/zz/%zz_tag%/%postname%/', EP_PERMALINK));
$say('generate_rewrite_rule', [$rewrite->generate_rewrite_rule('/%year%/%postname%/'), $rewrite->generate_rewrite_rule('/%year%/%postname%/', true)]);

$rewrite = $fresh('/%postname%/');
foreach (['EP_COMMENTS' => EP_COMMENTS, 'EP_PAGES' => EP_PAGES, 'EP_ROOT' => EP_ROOT, 'EP_DATE' => EP_DATE, 'EP_PERMALINK | EP_PAGES' => EP_PERMALINK | EP_PAGES] as $label => $mask) {
    $say("a dated name under {$label}", $rewrite->generate_rewrite_rules('/%year%/%postname%/', $mask));
}
$say('comments, paged', $rewrite->generate_rewrite_rules('comments', EP_COMMENTS, true, true, true, false));
$front = static fn () => 'page';
$frontPage = static fn () => 2;
add_filter('pre_option_show_on_front', $front);
add_filter('pre_option_page_on_front', $frontPage);
$say('the root with a page in front', $rewrite->generate_rewrite_rules('/', EP_ROOT));
$say('posts with a page in front', $rewrite->generate_rewrite_rules('/%postname%/', EP_PERMALINK));
remove_filter('pre_option_show_on_front', $front);
remove_filter('pre_option_page_on_front', $frontPage);

register_post_type('zz_book', ['public' => true, 'rewrite' => ['slug' => 'zz-book']]);
register_post_type('zz_chapter', ['public' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'zz-chapter']]);
register_taxonomy('zz_genre', 'zz_book', ['public' => true, 'rewrite' => ['slug' => 'zz-genre']]);
register_taxonomy('zz_shelf', 'zz_book', ['public' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'zz-shelf', 'hierarchical' => true]]);
register_post_type('zz_noqv', ['public' => true, 'query_var' => false]);
register_post_type('zz_noqv_h', ['public' => true, 'hierarchical' => true, 'query_var' => false]);
register_post_type('zz_unwritten', ['public' => true, 'rewrite' => false]);
register_taxonomy('zz_noqv_tax', 'zz_book', ['public' => true, 'query_var' => false]);
global $wp_rewrite;
foreach (['zz_book', 'zz_chapter', 'zz_genre', 'zz_shelf'] as $name) {
    $args = $wp_rewrite->extra_permastructs[$name] ?? null;
    $say("registered {$name}", $args === null ? null : [$args, $wp_rewrite->generate_rewrite_rules($args['struct'], $args['ep_mask'], $args['paged'], $args['feed'], $args['forcomments'], $args['walk_dirs'], $args['endpoints'])]);
}
$say('the rewrite tags', array_values(array_filter(array_map(null, $wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $wp_rewrite->queryreplace), static fn ($tag) => !str_starts_with($tag[0], '%sitemap'))));
$say('the permastructs', array_map(static fn ($args) => $args['struct'], $wp_rewrite->extra_permastructs));
unregister_taxonomy('zz_noqv_tax');
unregister_post_type('zz_noqv');
unregister_post_type('zz_noqv_h');
unregister_post_type('zz_unwritten');
unregister_taxonomy('zz_genre');
unregister_taxonomy('zz_shelf');
unregister_post_type('zz_book');
unregister_post_type('zz_chapter');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
