<?php
/**
 * Rendering and request rules the security sweep of 2026-10-13 settled
 * (probe sweep-rules), each as the reference has it:
 * - a navigation link's and a submenu's class names, and a submenu wired
 *   for hover inside any navigation;
 * - which menu a navigation shows when it names a draft, private,
 *   published or missing one;
 * - a duotone preset only when theme.json registers it, otherwise read as
 *   a custom value;
 * - a string author__in / author__not_in read as ids;
 * - the addresses a safe request refuses.
 * Same protocol as api-probe.php; the menus it makes are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$tags = static function (string $html, string $pattern): array {
    preg_match_all($pattern, $html, $m);
    return array_map(static fn (string $tag) => (string) preg_replace('/\s+/', ' ', $tag), $m[0]);
};

// Class names.
$say('navigation link class names', $tags(do_blocks('<!-- wp:navigation-link {"label":"a","url":"/x","className":"foo bar"} /-->'), '/<li\b[^>]*>/'));
$say('a navigation link class name with a quote in it', $tags(do_blocks('<!-- wp:navigation-link {"label":"a","url":"/x","className":"a\\u0022b"} /-->'), '/<li\b[^>]*>/'));
foreach (['{"openSubmenusOnClick":true}', '{"showSubmenuIcon":true}', '{"showSubmenuIcon":false}'] as $attrs) {
    $html = do_blocks('<!-- wp:navigation ' . $attrs . ' --><!-- wp:navigation-submenu {"label":"s","url":"/s","className":"baz qux"} --><!-- wp:navigation-link {"label":"b","url":"/b","className":"inner"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->');
    $say("submenu in a navigation {$attrs}", [$tags($html, '/<li\b[^>]*>/'), $tags($html, '/<ul\b[^>]*submenu-container[^>]*>/')]);
}

// The menu a navigation shows.
$menus = [];
foreach (['draft', 'private', 'publish'] as $status) {
    $id = wp_insert_post(['post_type' => 'wp_navigation', 'post_title' => "zz sweep menu {$status}", 'post_status' => $status, 'post_content' => '<!-- wp:navigation-link {"label":"zz sweep ' . $status . ' link","url":"https://example.com/' . $status . '"} /-->']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no menu\n");
        exit(1);
    }
    $made[] = $id;
    $menus[$status] = $id;
}
$menus['missing'] = 999999999;
foreach ($menus as $status => $id) {
    $html = do_blocks('<!-- wp:navigation {"ref":' . $id . '} /-->');
    preg_match('/<nav\b[^>]*>/', $html, $nav);
    preg_match_all('/zz sweep (\w+) link/', $html, $links);
    $say("a navigation naming a {$status} menu", ['label' => str_contains($nav[0] ?? '', 'aria-label') ? (string) preg_replace('/.*aria-label="([^"]*)".*/', '$1', $nav[0]) : null, 'links' => $links[1]]);
}

// Duotone presets.
foreach (['grayscale', 'zz-none'] as $slug) {
    $html = do_blocks('<!-- wp:image {"style":{"color":{"duotone":"var:preset|duotone|' . $slug . '"}}} --><figure class="wp-block-image"><img src="/a.png" alt=""/></figure><!-- /wp:image -->');
    preg_match('/<figure[^>]*>/', $html, $figure);
    $say("an image's duotone preset {$slug}", (string) preg_replace('/-\d+(?=["\s])/', '-N', $figure[0] ?? ''));
}

// Author lists given as strings.
foreach (['2,1', '3 1', ' 5', '7'] as $value) {
    foreach (['author__in', 'author__not_in'] as $var) {
        $query = new WP_Query([$var => $value, 'fields' => 'ids', 'no_found_rows' => true, 'post_type' => 'post', 'posts_per_page' => 1]);
        preg_match('/post_author (NOT IN|IN) \([^)]*\)/', $query->request, $m);
        $say("{$var} given as " . json_encode($value), $m[0] ?? null);
    }
}

// Addresses a safe request refuses.
$addresses = [];
foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '100.100.100.200', '100.63.255.255', '100.64.0.1', '100.127.255.255', '100.128.0.1', '198.17.255.255', '198.18.0.1', '198.19.255.255', '198.20.0.1', '192.0.0.192', '192.0.0.9', '192.0.2.1', '192.88.99.1', '198.51.100.1', '203.0.113.1', '223.255.255.255', '224.0.0.1', '239.255.255.255', '240.0.0.1', '255.255.255.255', '172.15.255.255', '172.32.0.1', '8.8.8.8', '1.1.1.1'] as $ip) {
    $addresses[$ip] = wp_http_validate_url("http://{$ip}/x") === false ? 'refused' : 'allowed';
}
$say('addresses a safe request refuses', $addresses);

echo json_encode($log, JSON_UNESCAPED_SLASHES);
