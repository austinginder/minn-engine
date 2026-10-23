<?php

declare(strict_types=1);

/**
 * A query loop that navigates on the client (enhancedPagination), compared
 * with the reference on the same database: a page holding one is made on the
 * reference and removed after, and its first and second pages are fetched
 * from both stacks. The loop's markup must match (the interactive core/query
 * region, the items' keys, the previous, next and numbered links' keys and
 * actions), and so must what it brings to the page: the query block's view
 * module, the router in the import map, the router's loading bar and its
 * animation styles.
 */

require_once __DIR__ . '/lib.php';

$ENGINE = minn_test_url();
$REF = minn_test_reference_url();
$WP = '/opt/homebrew/bin/wp --skip-plugins --skip-themes --path=' . escapeshellarg(minn_test_site_root() . '/wp-reference');

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail === '' ? '' : "\n       " . substr($detail, 0, 600)) . "\n";
    }
};

[$head] = minn_test_fetch("{$REF}/?rest_route=/wp/v2/posts", 3);
if ($head['status'] !== 200) {
    echo "SKIP: reference not running at {$REF}\n";
    exit(0);
}
echo "query-navigation suite: {$ENGINE} (engine) / {$REF} (oracle)\n";

$content = '<!-- wp:query {"queryId":7,"query":{"perPage":2,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":"exclude"},"enhancedPagination":true} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --><!-- wp:query-no-results --><!-- wp:paragraph --><p>None</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query -->';
$id = (int) trim((string) shell_exec($WP . ' post create --post_type=page --post_status=publish --post_title=' . escapeshellarg('ZZ Query Navigation') . ' --post_name=zz-query-navigation --post_content=' . escapeshellarg($content) . ' --porcelain 2>/dev/null'));
register_shutdown_function(static function () use ($WP, $id): void {
    if ($id > 1) {
        shell_exec($WP . ' post delete ' . $id . ' --force 2>/dev/null');
    }
});
$check($id > 1, 'a page with a navigating query loop is made on the reference', (string) $id);

$main = static function (string $html): string {
    $main = preg_match('#<main.*?</main>#s', $html, $m) ? $m[0] : '';
    return (string) preg_replace('/wp-container-core-[a-z-]+-is-layout-[0-9a-f]{8}/', 'LAYOUT', $main);
};
$brings = static fn (string $html): array => [
    'loading bar' => substr_count($html, 'wp-interactivity-router-loading-bar') > 0,
    'animation styles' => str_contains($html, 'wp-interactivity-router-animations'),
    'query view module' => str_contains($html, 'block-library/query/view-js-module'),
    'router in the import map' => str_contains($html, '"@wordpress/interactivity-router"'),
];
foreach (['' => 'the first page', '?query-7-page=2' => 'the second page'] as $query => $label) {
    [, $engine] = minn_test_fetch("{$ENGINE}/zz-query-navigation/{$query}", 20);
    [, $reference] = minn_test_fetch("{$REF}/zz-query-navigation/{$query}", 20);
    $check($main((string) $engine) !== '' && $main((string) $engine) === $main((string) $reference), "{$label}: the loop's markup matches the reference", $main((string) $engine));
    $check(str_contains((string) $reference, 'data-wp-router-region="query-7"'), "{$label}: the reference marks the loop a router region");
    $check($brings((string) $engine) === $brings((string) $reference) && !in_array(false, $brings((string) $reference), true), "{$label}: the view module, router, loading bar and styles come with it, as on the reference", json_encode([$brings((string) $engine), $brings((string) $reference)]));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
