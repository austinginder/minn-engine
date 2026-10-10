<?php
/**
 * robots-template.php (probe robots-template): the robots meta tag and each
 * of its filters, on a public and a private site, for a post, a search and
 * an embed; directives given as true, false, strings, numbers and lists;
 * index and noindex, follow and nofollow given together. blog_public is put
 * back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$public = get_option('blog_public', null);
$made = [];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use ($public, &$made, $pings): void {
    $public === null ? delete_option('blog_public') : update_option('blog_public', $public);
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$query = static function (array $args) {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query();
    $wp_query->query($args);
};
$made[] = $post = (int) wp_insert_post(['post_title' => 'zz rt post', 'post_status' => 'publish', 'post_content' => 'x']);

$inputs = [[], ['noindex' => true], ['follow' => true, 'zz' => 'x'], ['noindex' => false, 'max-snippet' => '-1'], ['nofollow' => true]];
$filters = static fn (array $robots) => [
    'max image preview large' => wp_robots_max_image_preview_large($robots), 'no robots' => wp_robots_no_robots($robots), 'noindex' => wp_robots_noindex($robots),
    'noindex embeds' => wp_robots_noindex_embeds($robots), 'noindex search' => wp_robots_noindex_search($robots), 'sensitive page' => wp_robots_sensitive_page($robots),
];
$sites = [];
foreach (['1' => 'public', '0' => 'private'] as $value => $label) {
    update_option('blog_public', $value);
    foreach (['a post' => ['p' => $post], 'search' => ['s' => 'zz rt'], 'an embed' => ['p' => $post, 'embed' => true]] as $view => $args) {
        $query($args);
        $sites["{$label}, {$view}"] = [array_map($filters, $inputs), $echoed(static fn () => wp_robots())];
    }
}
$say('each filter by site and view', $sites);

update_option('blog_public', '1');
$query(['p' => $post]);
$tags = [];
foreach ([
    'true and false' => ['zz-true' => true, 'zz-false' => false], 'strings' => ['max-snippet' => '-1', 'max-video-preview' => '', 'unavailable_after' => '2030-01-01'],
    'numbers' => ['zz-int' => 5, 'zz-zero' => 0, 'zz-float' => 1.5], 'a list' => ['zz-list' => ['a', 'b']], 'index with noindex' => ['index' => true, 'noindex' => true],
    'follow with nofollow' => ['follow' => true, 'nofollow' => true], 'quotes' => ['zz"q' => true, 'zz-v' => 'a\'b"c<d>'], 'upper case' => ['NoIndex' => true],
    'nothing' => null,
] as $label => $add) {
    add_filter('wp_robots', $filter = static fn ($robots) => $add === null ? [] : array_merge($robots, $add), 99);
    $tags[$label] = $echoed(static fn () => wp_robots());
    remove_filter('wp_robots', $filter, 99);
}
add_filter('wp_robots', $heard = static function ($robots) use (&$tags) {
    $tags['heard'] = $robots;
    return $robots;
}, 99);
$echoed(static fn () => wp_robots());
remove_filter('wp_robots', $heard, 99);
$say('the tag from given directives', $tags);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
