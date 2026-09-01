<?php

declare(strict_types=1);

/**
 * Front-page settings: a static front page and a posts page, live against
 * the oracle. The suite switches the shared database to show_on_front=page
 * with the Sample Page in front and Docs as the posts page, compares every
 * URL shape the settings touch (status, redirect target, body classes,
 * title, normalised body), and restores the options on shutdown. Also the
 * canonical redirect for doubled slashes, which is unrelated to the
 * settings but lives in the same resolver.
 *
 *   MINN_TEST_URL=https://minn.localhost php tests/front-page.test.php
 */

require __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'http://127.0.0.1:8123';
$WP = '/opt/homebrew/bin/wp';
$PUBLIC = minn_test_site_root() . '/public';

$src = (string) file_get_contents(__DIR__ . '/theme.test.php');
preg_match('/function theme_body.*?\n}\n/s', $src, $m);
eval($m[0]);
preg_match('/function theme_first_diff.*?\n}\n/s', $src, $m);
eval($m[0]);

$probe = @file_get_contents($REF . '/', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
if ($probe === false || $probe === '') {
    echo "front-page suite: reference not running at $REF; skipping\n";
    exit(0);
}

$option = static function (string $name, ?string $value = null) use ($WP, $PUBLIC): string {
    $cmd = $value === null
        ? sprintf('cd %s && %s option get %s 2>/dev/null', escapeshellarg($PUBLIC), $WP, escapeshellarg($name))
        : sprintf('cd %s && %s option update %s %s >/dev/null 2>&1', escapeshellarg($PUBLIC), $WP, escapeshellarg($name), escapeshellarg($value));
    return trim((string) shell_exec($cmd));
};

$saved = ['show_on_front' => $option('show_on_front'), 'page_on_front' => $option('page_on_front'), 'page_for_posts' => $option('page_for_posts')];
register_shutdown_function(static function () use ($option, $saved): void {
    foreach ($saved as $name => $value) {
        $option($name, $value === '' ? '0' : $value);
    }
});

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

/** Status, redirect target, body classes (theme tokens dropped), title, normalised body. */
$shape = static function (string $base, string $path) use ($REF, $ENGINE): array {
    [$headers, $body] = minn_test_fetch($base . $path);
    $location = (string) ($headers['location'] ?? '');
    $location = str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $location);
    preg_match('/<body class="([^"]*)"/', $body, $bm);
    $classes = preg_replace('/\s*wp-theme-\S+|\s*wp-child-theme-\S+/', '', $bm[1] ?? '');
    preg_match('/<title>([^<]*)<\/title>/', $body, $tm);
    // oEmbed discovery links are a known gap: the engine has no oembed endpoint to point at.
    preg_match_all('/<link rel="alternate"(?![^>]*oembed)[^>]*>/', $body, $links);
    return [
        'status' => (int) $headers['status'],
        'location' => $location,
        'classes' => trim((string) $classes),
        'title' => trim($tm[1] ?? ''),
        'alternates' => array_map(static fn (string $l) => str_replace([$REF, str_replace('https://', 'http://', $ENGINE), $ENGINE], '{site}', $l), $links[0]),
        'body' => (int) $headers['status'] === 200 ? theme_body($body, $REF, $ENGINE) : '',
    ];
};

$compare = static function (string $path, string $label) use ($shape, $check, $REF, $ENGINE): void {
    $a = $shape($ENGINE, $path);
    $b = $shape($REF, $path);
    foreach (['status', 'location', 'classes', 'title'] as $key) {
        $check($a[$key] === $b[$key], "$label $path $key", "engine=" . json_encode($a[$key]) . " reference=" . json_encode($b[$key]));
    }
    $check($a['alternates'] === $b['alternates'], "$label $path alternate links", json_encode($a['alternates']) . ' vs ' . json_encode($b['alternates']));
    if ($b['status'] === 200) {
        $verdict = theme_first_diff($a['body'], $b['body']);
        $check($verdict === 'identical', "$label $path body", $verdict);
    }
};

echo "front-page suite: $ENGINE (engine) vs $REF (reference)\n";

echo "-- doubled slashes (posts on front)\n";
foreach (['//', '/hello-world//', '//hello-world/', '/category//uncategorized/'] as $path) {
    $compare($path, 'slashes');
}

echo "-- show_on_front=page, page_on_front=2, page_for_posts=6\n";
$option('show_on_front', 'page');
$option('page_on_front', '2');
$option('page_for_posts', '6');
foreach (['/', '/page/2/', '/sample-page/', '/sample-page/docs/', '/sample-page/docs/page/2/', '/docs/', '/?page_id=6', '/?page_id=2', '/?p=6', '/sample-page/docs/feed/', '/feed/', '/?s=hello'] as $path) {
    $compare($path, 'front+posts');
}

echo "-- show_on_front=page with only a front page\n";
$option('page_for_posts', '0');
foreach (['/', '/sample-page/docs/', '/page/2/'] as $path) {
    $compare($path, 'front only');
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
