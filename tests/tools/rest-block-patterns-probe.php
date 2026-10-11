<?php
/**
 * The block-pattern routes and a legacy widget's preview (probe
 * rest-block-patterns): GET wp/v2/block-patterns/categories and
 * /patterns as an administrator, remote patterns off (they are
 * wordpress.org's), each pattern compared by a hash of its fields once
 * the scheme WP-CLI forces on theme asset links is masked; a nested
 * pattern written out by prepare_item_for_response(); how
 * resolve_pattern_blocks() writes out a pattern (its markup trimmed, the
 * one block it makes named by the pattern's slug, title, description and
 * categories when it has them); and POST widget-types/search/render, its
 * page skeleton with wp_head and wp_footer emptied for the call. The
 * patterns registered here are unregistered again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? (string) parse_url(home_url(), PHP_URL_HOST);
if (!did_action('init')) {
    do_action('init');
}
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
add_filter('should_load_remote_block_patterns', '__return_false');
$scheme = static fn (string $json) => (string) preg_replace('#https?:(\\\\?/\\\\?/)#', '{scheme}:$1', $json);

$categories = rest_do_request(new WP_REST_Request('GET', '/wp/v2/block-patterns/categories'));
$say('the pattern categories', ['status' => $categories->get_status(), 'categories' => $categories->get_data()]);
$patterns = rest_do_request(new WP_REST_Request('GET', '/wp/v2/block-patterns/patterns'));
$say('the patterns', ['status' => $patterns->get_status(), 'patterns' => array_map(static fn ($pattern) => [$pattern['name'] ?? '', array_keys($pattern), md5($scheme(json_encode($pattern)))], (array) $patterns->get_data())]);

$controller = new WP_REST_Block_Patterns_Controller();
$say('a nested pattern written out', $controller->prepare_item_for_response(['name' => 'zz/outer', 'title' => 'Outer', 'content' => "\n<!-- wp:pattern {\"slug\":\"twentytwentyfive/hidden-search\"} /-->\n"], new WP_REST_Request('GET', '/wp/v2/block-patterns/patterns'))->get_data());

$cases = [
    'zz/many' => ['title' => 'Many', 'content' => "\n<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->\n\n<!-- wp:separator /-->\n"],
    'zz/loose' => ['title' => 'Loose', 'content' => "\nloose text\n<!-- wp:separator /-->\n"],
    'zz/nested' => ['title' => 'Nested', 'content' => "\n<!-- wp:group --><div class=\"wp-block-group\">\n<!-- wp:separator /-->\n</div><!-- /wp:group -->\n"],
    'zz/spaces' => ['title' => 'Spaces', 'content' => "<!-- wp:separator /-->  \t\n "],
    'zz/full' => ['title' => 'Full', 'description' => 'Desc', 'categories' => ['text', 'banner'], 'keywords' => ['k'], 'blockTypes' => ['core/post-content'], 'viewportWidth' => 900, 'content' => '<!-- wp:separator /-->'],
    'zz/empty' => ['title' => 'Empty', 'description' => '', 'categories' => [], 'content' => '<!-- wp:separator /-->'],
    'zz/zero' => ['title' => 'Zero', 'description' => '0', 'content' => '<!-- wp:separator /-->'],
    'zz/named' => ['title' => 'Named', 'description' => 'D', 'content' => '<!-- wp:separator {"metadata":{"name":"Mine","bindings":{"x":1}}} /-->'],
];
$resolved = [];
foreach ($cases as $slug => $args) {
    register_block_pattern($slug, $args);
    $resolved[$slug] = resolve_pattern_blocks(parse_blocks('<!-- wp:pattern {"slug":"' . $slug . '"} /-->'));
    unregister_block_pattern($slug);
}
$say('patterns written out', $resolved);

$head = $GLOBALS['wp_filter']['wp_head'] ?? null;
$footer = $GLOBALS['wp_filter']['wp_footer'] ?? null;
remove_all_actions('wp_head');
remove_all_actions('wp_footer');
$classes = static fn () => ['zz-class'];
add_filter('body_class', $classes, PHP_INT_MAX);
$render = new WP_REST_Request('POST', '/wp/v2/widget-types/search/render');
$render->set_param('instance', []);
$preview = rest_do_request($render);
remove_filter('body_class', $classes, PHP_INT_MAX);
if ($head !== null) {
    $GLOBALS['wp_filter']['wp_head'] = $head;
}
if ($footer !== null) {
    $GLOBALS['wp_filter']['wp_footer'] = $footer;
}
$say('a legacy widget\'s preview page', ['status' => $preview->get_status(), 'data' => $preview->get_data()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
