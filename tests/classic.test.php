<?php
/**
 * Classic-theme suite: the fixture classic PHP theme (tests/fixtures/themes/
 * minn-classic-fixture) renders on both stacks and the bodies diff byte for
 * byte after the theme-suite normalisation (styles, scripts, links stripped),
 * plus a head battery (title tag, robots, feed links, canonical) compared
 * line for line. Live-only: it skips when the reference is down. The suite
 * installs the theme, builds a real nav menu through the reference's wp-cli,
 * lowers posts_per_page so pagination renders, and puts everything back.
 */
require_once __DIR__ . '/lib.php';

$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn-engine.localhost', '/');
$REF = 'http://127.0.0.1:8123';
$ROOT = dirname(__DIR__);
$THEMES = $ROOT . '/wp-reference/wp-content/themes';
$SLUG = 'minn-classic-fixture';

$live = @file_get_contents("$REF/wp-json/", false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])) !== false;
if (!$live) {
    echo "  skip classic suite: reference not running on 8123\n\n0 passed, 0 failed\n";
    exit(0);
}

$wp = static function (string $args) use ($ROOT): string {
    return trim((string) shell_exec('cd ' . escapeshellarg($ROOT . '/wp-reference') . ' && /opt/homebrew/bin/wp ' . $args . ' 2>/dev/null'));
};

// Install the theme into the shared themes directory (public/wp-content/themes symlinks here).
shell_exec('rm -rf ' . escapeshellarg("$THEMES/$SLUG"));
shell_exec('cp -R ' . escapeshellarg($ROOT . '/tests/fixtures/themes/' . $SLUG) . ' ' . escapeshellarg($THEMES . '/'));
minn_test_pin_theme($SLUG);
$savedPerPage = $wp('option get posts_per_page');
$wp('option update posts_per_page 3');

// A real menu through the reference's own management: custom, post, page, term
// items, plus the child page so the current-page-ancestor family renders. The
// custom item points off-host on purpose: the stacks serve different hosts off
// the shared database, so a home-matching URL could only ever match one of
// them; the engine-only battery below pins the home-item behaviour instead.
$menuId = (int) $wp('menu create "Classic Primary" --porcelain');
$homeItem = 0;
if ($menuId > 0) {
    $homeItem = (int) $wp('menu item add-custom ' . $menuId . ' Elsewhere https://example.com/docs/ --porcelain');
    $wp('menu item add-post ' . $menuId . ' 1 --porcelain');
    $wp('menu item add-post ' . $menuId . ' 2 --porcelain');
    $wp('menu item add-post ' . $menuId . ' 6 --porcelain');
    $wp('menu item add-term ' . $menuId . ' category 1 --porcelain');
    $wp('menu location assign ' . $menuId . ' primary');
}

register_shutdown_function(static function () use ($wp, $menuId, $savedPerPage, $THEMES, $SLUG): void {
    if ($menuId > 0) {
        $wp('menu delete ' . $menuId);
    }
    if ($savedPerPage !== '') {
        $wp('option update posts_per_page ' . escapeshellarg($savedPerPage));
    }
    $wp('option delete theme_mods_' . $SLUG);
    shell_exec('rm -rf ' . escapeshellarg("$THEMES/$SLUG"));
});

$fetch = static function (string $base, string $path): string {
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'timeout' => 20]]);
    return (string) @file_get_contents($base . $path, false, $context);
};

/** Both hosts fold to the engine's, in raw and urlencoded form (the oEmbed discovery href embeds its own host urlencoded). */
function classic_hosts(string $text, string $host, string $engine): string
{
    $http = str_replace('https://', 'http://', $engine);
    $text = str_replace([$host, $http], $engine, $text);
    return str_replace([rawurlencode($host), rawurlencode($http)], rawurlencode($engine), $text);
}

function classic_body(string $html, string $host, string $engine): string
{
    if (!preg_match('/<body.*<\/body>/s', $html, $m)) {
        return '';
    }
    $body = $m[0];
    $body = preg_replace('/<style\b[^>]*>.*?<\/style>/s', '', $body);
    $body = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $body);
    $body = preg_replace('/<link\b[^>]*>/', '', $body);
    $body = classic_hosts($body, $host, $engine);
    $lines = array_filter(array_map('rtrim', explode("\n", $body)), static fn (string $l) => trim($l) !== '');
    return implode("\n", $lines);
}

/** The head lines the classic defaults own, in print order. */
function classic_head(string $html, string $host, string $engine): string
{
    $head = explode('</head>', $html, 2)[0];
    $head = classic_hosts($head, $host, $engine);
    $keep = [];
    foreach (explode("\n", $head) as $line) {
        $line = trim($line);
        if (preg_match('/^<title>|^<meta name=.robots.|rel="alternate" type="application\/rss|rel="canonical"|rel="https:\/\/api\.w\.org\/"|rel=.shortlink.|oembed|name="generator"/', $line)) {
            $keep[] = $line;
        }
    }
    return implode("\n", $keep);
}

function classic_first_diff(string $got, string $want): string
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

$pages = ['/', '/page/2/', '/hello-world/', '/sample-page/', '/sample-page/docs/', '/category/uncategorized/', '/?s=welcome', '/zz-classic-nope/'];
$pass = 0;
$fail = 0;
foreach ($pages as $path) {
    $engineHtml = $fetch($ENGINE, $path);
    $refHtml = $fetch($REF, $path);
    $got = classic_body($engineHtml, $REF, $ENGINE);
    $want = classic_body($refHtml, $REF, $ENGINE);
    if ($got !== '' && $got === $want) {
        $pass++;
        echo "  ok   $path body matches reference\n";
    } else {
        $fail++;
        echo "  FAIL $path body: " . classic_first_diff($got, $want) . "\n";
    }
    $gotHead = classic_head($engineHtml, $REF, $ENGINE);
    $wantHead = classic_head($refHtml, $REF, $ENGINE);
    if ($gotHead === $wantHead) {
        $pass++;
        echo "  ok   $path head battery matches reference\n";
    } else {
        $fail++;
        echo "  FAIL $path head battery: " . classic_first_diff($gotHead, $wantHead) . "\n";
    }
}

// The nav renders through the default walker with the reference's item classes.
$home = classic_body($fetch($ENGINE, '/'), $REF, $ENGINE);
foreach (['menu-item-type-custom', 'menu-item-type-post_type', 'menu-item-type-taxonomy', 'id="menu-classic-primary"'] as $needle) {
    if (str_contains($home, $needle)) {
        $pass++;
        echo "  ok   nav carries $needle\n";
    } else {
        $fail++;
        echo "  FAIL nav missing $needle\n";
    }
}

// The home-item battery, engine only: a custom item whose URL is home can
// match at most one stack off the shared database, so the reference cannot
// take part. The expected tokens were captured from the reference with the
// item pointed at ITS host (2026-08-30): current + current_page_item +
// menu-item-home on the front, menu-item-home alone everywhere else.
if ($homeItem > 0) {
    $engineHome = rtrim((string) shell_exec('cd ' . escapeshellarg($ROOT . '/public') . ' && /opt/homebrew/bin/wp option get home 2>/dev/null'));
    $wp('post meta update ' . $homeItem . ' _menu_item_url ' . escapeshellarg(trim($engineHome) . '/'));
    $homeChecks = [
        '/' => 'menu-item-object-custom current-menu-item current_page_item menu-item-home menu-item-' . $homeItem,
        '/page/2/' => 'menu-item-object-custom menu-item-home menu-item-' . $homeItem,
        '/hello-world/' => 'menu-item-object-custom menu-item-home menu-item-' . $homeItem,
    ];
    foreach ($homeChecks as $path => $needle) {
        $html = $fetch($ENGINE, $path);
        if (str_contains($html, $needle)) {
            $pass++;
            echo "  ok   home item on $path carries \"$needle\"\n";
        } else {
            $fail++;
            preg_match('/<li id="menu-item-' . $homeItem . '[^>]*>/', $html, $m);
            echo "  FAIL home item on $path: want \"$needle\" got " . ($m[0] ?? '(no item)') . "\n";
        }
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
