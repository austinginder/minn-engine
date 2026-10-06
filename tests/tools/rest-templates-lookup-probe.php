<?php
/**
 * Template hierarchies from a slug, as get_template_hierarchy builds them
 * (every family, a prefix, a custom template, a post type and a taxonomy a
 * plugin registers), and wp/v2/templates/lookup and template-parts/lookup
 * answering with the first template a slug would use. Same protocol as
 * api-probe.php; dispatched in process.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_post_type('zz_book', ['public' => true]);
register_taxonomy('zz_genre', 'zz_book', ['public' => true]);
$cases = [
    ['index'], ['home'], ['front-page'], ['front-page-x'], ['home-x'], ['single'], ['single-post'], ['single-post-hello'], ['single-post-a-b'],
    ['single-product-shoe'], ['single-hello'], ['single-page-x'], ['single-attachment-x'], ['single-zz_book'], ['single-zz_book-x'],
    ['page'], ['page-about'], ['page-12'], ['paged'], ['singular'], ['attachment'], ['archive'], ['archive-product'], ['archive-zz_book'],
    ['author'], ['author-admin'], ['author-12'], ['category'], ['category-news'], ['category-a-b'], ['tag'], ['tag-tips'],
    ['taxonomy-genre'], ['taxonomy-genre-rock'], ['taxonomy-zz_genre'], ['taxonomy-zz_genre-rock'], ['taxonomy-category-news'], ['taxonomy-post_format-aside'], ['taxonomy-nope-x'],
    ['date'], ['search'], ['404'], ['embed'], ['privacy-policy'], ['zz-unknown'], ['my-custom'],
    ['my-custom', true], ['single-post', true], ['category-news', false, 'category'], ['category-news', false, 'taxonomy-category'], ['tag-tips', false, 'tag'],
    ['taxonomy-zz_genre-rock', false, 'taxonomy-zz_genre'], ['single-zz_book-x', false, 'single-zz_book'], ['archive-zz_book', false, 'archive'], ['author-admin', false, 'author'], ['page-about', false, 'page'],
];
$hierarchies = [];
foreach ($cases as $case) {
    $hierarchies[] = [$case, get_template_hierarchy(...$case)];
}
$say('hierarchies', $hierarchies);
$send = static function (string $who, string $route, array $query) use ($say): void {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    $data = rest_get_server()->response_to_data($response, false);
    $say($who . ' ' . $route . ' ' . json_encode($query), [$response->get_status(), is_array($data) ? array_intersect_key($data, array_flip(['id', 'slug', 'source', 'type', 'title', 'area', 'has_theme_file', 'is_custom', 'code', 'message'])) : $data]);
};
wp_set_current_user(0);
$send('visitor', '/wp/v2/templates/lookup', ['slug' => 'single-post-hello']);
wp_set_current_user(1);
foreach ([['slug' => 'single-post-hello'], ['slug' => 'page-about'], ['slug' => 'category-news', 'template_prefix' => 'category'], ['slug' => 'index'], ['slug' => 'zz-unknown'], ['slug' => 'my-custom', 'is_custom' => true], ['slug' => 'single-zz_book-x'], []] as $query) {
    $send('admin', '/wp/v2/templates/lookup', $query);
}
$send('admin', '/wp/v2/template-parts/lookup', ['slug' => 'header']);
$send('admin', '/wp/v2/template-parts/lookup', ['slug' => 'zz-nothing']);
wp_set_current_user(0);
unregister_taxonomy('zz_genre');
unregister_post_type('zz_book');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
