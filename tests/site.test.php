<?php

declare(strict_types=1);

/**
 * The Minn site theme (own git repo at site/minn-site): the engine's own
 * front page, a block theme rendered by the engine and by the reference
 * alike. The suite pins the site theme (the other suites pin
 * twentytwentyfive), diffs every template the theme ships against the
 * reference on the same database, and checks the marketing page's own
 * invariants. Copy edits belong in the theme repo, not here.
 *
 *   php tests/site.test.php
 */

putenv('MINN_TEST_KEEP_THEME=1');
require __DIR__ . '/lib.php';
if (!is_file(dirname(__DIR__) . '/site/minn-site/style.css')) {
    echo "skip: marketing theme not checked out at site/minn-site (own git repo)\n";
    exit(0);
}
minn_test_pin_theme('minn-site');

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn-engine.localhost', '/');
$REF = 'http://127.0.0.1:8123';

$src = (string) file_get_contents(__DIR__ . '/theme.test.php');
preg_match('/function theme_body.*?\n}\n/s', $src, $m);
eval($m[0]);
preg_match('/function theme_first_diff.*?\n}\n/s', $src, $m);
eval($m[0]);

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   $label\n";
    } else {
        $fail++;
        echo "  FAIL $label" . ($detail === '' ? '' : ": $detail") . "\n";
    }
};

[$h, $home] = minn_test_fetch($ENGINE . '/');
$check($h['status'] === 200, 'front page answers 200');
$check(str_contains($home, 'wp-theme-minn-site'), 'the site theme is active');
$check(str_contains($home, '<title>Minn Engine</title>'), 'document title is the site name');
$check(str_contains($home, 'served by Minn Engine'), 'the footer says who served the page');
$check(str_contains($home, '/wp-content/themes/minn-site/style.css'), 'the theme stylesheet is linked');
$check(!str_contains($home, 'CHECKS_COUNT'), 'no unfilled placeholders on the page');
$check(str_contains($home, 'href="#content">Skip to content'), 'skip link targets the template\'s own main id');
$check(!preg_match('/class="[^"]*has-global-padding/', $home), 'no global padding class without useRootPaddingAwareAlignments');
$check(str_contains($home, '&#8220;Compatible with WordPress&#8221;'), 'template markup is texturized');
foreach (['/wp-content/themes/minn-site/style.css', '/wp-content/themes/minn-site/assets/fonts/hanken-grotesk.woff2', '/wp-content/themes/minn-site/assets/fonts/jetbrains-mono.woff2'] as $asset) {
    [$ah] = minn_test_fetch($ENGINE . $asset);
    $check($ah['status'] === 200, "asset served: $asset");
}

$probe = @file_get_contents($REF . '/', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
if ($probe === false || $probe === '') {
    echo "  skip parity: reference not running at $REF\n";
} else {
    foreach (['/', '/hello-world/', '/sample-page/', '/category/uncategorized/', '/nonexistent/', '/?s=hello', '/page/2/'] as $path) {
        [, $e] = minn_test_fetch($ENGINE . $path);
        [, $r] = minn_test_fetch($REF . $path);
        $eb = theme_body($e, $ENGINE, $ENGINE);
        $rb = theme_body($r, $REF, $ENGINE);
        $check($eb === $rb, "$path matches the reference body", $eb === $rb ? '' : theme_first_diff($eb, $rb));
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
