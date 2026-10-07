<?php
/**
 * Theme suite: the public pages the block theme renders, diffed against
 * the reference's <body> after both are normalised (styles, scripts, and
 * link tags stripped; hosts, container suffixes, and per-request ids
 * neutralised). Pinned copies live in contracts/fixtures/theme/.
 *
 * Re-capture with:  php tests/theme.test.php --capture   (oracle up)
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$REF = 'http://127.0.0.1:8123';
$DIR = dirname(__DIR__) . '/contracts/fixtures/theme';
$pages = ['/', '/hello-world/', '/building-in-the-open/', '/sample-page/', '/sample-page/docs/', '/category/uncategorized/', '/tag/engine/',
    '/author/admin/', '/2026/08/', '/?s=hello', '/nonexistent/', '/zz-block-battery-media/', '/zz-block-battery-layout/', '/page/2/', '/battery-image/'];

function theme_body(string $html, string $host, string $engine): string
{
    if (!preg_match('/<body.*<\/body>/s', $html, $m)) {
        return '';
    }
    $body = $m[0];
    $body = preg_replace('/<style\b[^>]*>.*?<\/style>/s', '', $body);
    $body = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $body);
    $body = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/s', '', $body);
    $body = preg_replace('/<link\b[^>]*>/', '', $body);
    $body = str_replace([$host, str_replace('https://', 'http://', $engine)], $engine, $body);
    $body = preg_replace('/(wp-container-core-[a-z-]+-is-layout-)[0-9a-f]{8}/', '$1HASH', $body);
    $body = preg_replace('/wp-block-search__input-\d+/', 'wp-block-search__input-N', $body);
    $body = preg_replace('/modal-\d+/', 'modal-N', $body);
    $body = preg_replace('/aria-label=" \d+"/', 'aria-label=" N"', $body);
    $body = preg_replace('/(wp-block-gallery-|is-style-[a-z-]+--)\d+/', '$1N', $body);
    // Removed scripts and styles leave blank lines behind; compare content lines only.
    $lines = array_filter(array_map('rtrim', explode("\n", $body)), static fn (string $l) => trim($l) !== '');
    return implode("\n", $lines);
}

function theme_first_diff(string $got, string $want): string
{
    $g = explode("\n", $got);
    $w = explode("\n", $want);
    foreach ($w as $i => $line) {
        if (($g[$i] ?? null) !== $line) {
            return 'line ' . ($i + 1) . "\n      want: " . substr($line, 0, 200) . "\n      got:  " . substr($g[$i] ?? '(missing)', 0, 200);
        }
    }
    return count($g) > count($w) ? 'engine output is longer' : 'identical';
}

$fetch = static function (string $base, string $path): string {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 20]]);
    return (string) @file_get_contents($base . $path, false, $context);
};
$slug = static fn (string $path) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($path)), '-') ?: 'home';

if (in_array('--capture', $argv, true)) {
    @mkdir($DIR, 0755, true);
    foreach ($pages as $path) {
        file_put_contents("$DIR/{$slug($path)}.html", theme_body($fetch($REF, $path), $REF, $ENGINE));
    }
    echo 'Captured ' . count($pages) . " pages to $DIR\n";
    exit(0);
}

$live = @file_get_contents("$REF/wp-json/", false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])) !== false;
$pass = 0;
$fail = 0;
foreach ($pages as $path) {
    $engine = theme_body($fetch($ENGINE, $path), $REF, $ENGINE);
    $fixture = @file_get_contents("$DIR/{$slug($path)}.html");
    if ($fixture !== false) {
        if ($engine === $fixture) { $pass++; echo "  ok   $path matches fixture\n"; } else { $fail++; echo "  FAIL $path vs fixture: " . theme_first_diff($engine, $fixture) . "\n"; }
    }
    if ($live) {
        $reference = theme_body($fetch($REF, $path), $REF, $ENGINE);
        if ($engine === $reference) { $pass++; echo "  ok   $path matches reference\n"; } else { $fail++; echo "  FAIL $path vs reference: " . theme_first_diff($engine, $reference) . "\n"; }
    }
}
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
