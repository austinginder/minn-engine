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
// The marketing site is the one place the suites read rather than drive: it
// serves the Minn site theme permanently, so there is nothing to pin and
// nothing to restore. Its own parked WordPress answers on 8128.
$ENGINE = rtrim(getenv('MINN_SITE_URL') ?: 'https://minn-engine.localhost', '/');
$REF = rtrim(getenv('MINN_SITE_REF') ?: 'http://127.0.0.1:8128', '/');

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
[, $refHome] = minn_test_fetch($REF . '/');
preg_match('#<title>(.*?)</title>#s', $home, $et);
preg_match('#<title>(.*?)</title>#s', $refHome, $rt);
$check(($et[1] ?? '') !== '' && ($et[1] ?? '') === ($rt[1] ?? null), 'document title matches the reference', ($et[1] ?? '') . ' vs ' . ($rt[1] ?? ''));
$check(str_starts_with($et[1] ?? '', 'Minn') && !str_contains($et[1] ?? '', 'Minn Engine'), 'the tab uses the product name', $et[1] ?? '');
$check(str_contains($home, 'served by Minn'), 'the footer says who served the page');
$check(str_contains($home, 'not affiliated with, sponsored by, or endorsed'), 'the footer names independence from the marks');
$check(!str_contains($home, 'Managed WordPress'), 'no Managed WordPress phrase');
$check(!str_contains($home, 'Give WordPress a second engine'), 'the CTA does not use WordPress as a slogan object');
$check(str_contains($home, 'A second engine for WordPress sites'), 'the CTA is nominative: WordPress sites');
$check(str_contains($home, 'class="minn-wordmark">minn</span>'), 'the header wordmark is Minn');
$check(!str_contains($home, 'minn<small>engine</small>'), 'the header does not say engine');
$check(str_contains($home, 'What is Minn, and what is Minn Engine?'), 'the FAQ names the product vs the engine');
$check(str_contains($home, '/wp-content/themes/minn-site/style.css'), 'the theme stylesheet is linked');
$check(str_contains($home, 'rel="icon"') && str_contains($home, '/assets/img/favicon.webp'), 'the homepage links the theme favicon');
$check(str_contains($home, 'rel="preload"') && str_contains($home, '/assets/fonts/hanken-grotesk.woff2'), 'the homepage preloads the body font');
$check(!str_contains($home, 'CHECKS_COUNT'), 'no unfilled placeholders on the page');
$check(str_contains($home, 'href="#content">Skip to content'), 'skip link targets the template\'s own main id');
$check(!preg_match('/class="[^"]*has-global-padding/', $home), 'no global padding class without useRootPaddingAwareAlignments');
[, $texturizePage] = minn_test_fetch($ENGINE . '/texturize-battery-its-quoted-fine/');
$check(str_contains($texturizePage, '&#8220;quoted&#8221;'), 'template markup is texturized');
$check(str_contains($home, 'A modern PHP engine'), 'the hero names what Minn is');
$check(str_contains($home, 'Minn fluently speaks with WordPress sites'), 'the hero names the compatibility');
$check(str_contains($home, 'Built from') && str_contains($home, 'scratch'), 'the hero says built from scratch');
$check(str_contains($home, 'Minn Admin is the only UI'), 'the visual draws the no-wp-admin line');
$check(str_contains($home, 'Audience 3'), 'the three-audience visual is on the page');
$check(str_contains($home, 'href="/lexicon/"'), 'the homepage links to the lexicon');
$check(str_contains($home, 'href="/looks-like/"'), 'the homepage links to looks-like');
$check(str_contains($home, 'href="/php/"'), 'the homepage links to how the PHP reads');
$check(str_contains($home, 'Browse the lexicon'), 'the visual points at the glossary page');
$check(str_contains($home, 'Classic PHP themes run'), 'classic PHP themes are on the working list');
$check(!str_contains($home, 'are out of scope; preflight flags them'), 'classic PHP themes are not on the not-yet list');

[$lh, $lex] = minn_test_fetch($ENGINE . '/lexicon/');
$check($lh['status'] === 200, 'theme /lexicon/ answers 200', (string) $lh['status']);
$check(str_contains($lex, '<title>The lexicon · Minn'), 'lexicon title');
$check(str_contains($lex, '/assets/img/favicon.webp'), 'lexicon links the theme favicon');
$check(str_contains($lex, 'data-lex-filter="speak"'), 'lexicon Speak chip');
$check(str_contains($lex, 'aria-current="page"'), 'lexicon nav is current');
[$eh, $errors] = minn_test_fetch($ENGINE . '/errors/');
$check($eh['status'] === 200, 'theme /errors/ answers 200', (string) $eh['status']);
$check(str_contains($errors, '<title>When something breaks'), 'errors page title');
$check(substr_count($errors, 'class="minn-errframe"') === 3, 'the three failure pages are shown');
$check(str_contains($errors, 'Error establishing a database connection'), 'the database page is one of them');
$check(str_contains($errors, 'wp minn recovery'), 'the page names the recovery verb');
[$emd] = minn_test_fetch($ENGINE . '/errors.md');
$check($emd['status'] === 200, 'theme /errors.md answers 200', (string) $emd['status']);

[$mdh] = minn_test_fetch($ENGINE . '/lexicon.md');
$check($mdh['status'] === 200, 'theme /lexicon.md answers 200', (string) $mdh['status']);
[$csh, $cs] = minn_test_fetch($ENGINE . '/code-size/');
$check($csh['status'] === 200, 'theme /code-size/ answers 200', (string) $csh['status']);
$check(str_contains($cs, '<title>Code size · Minn</title>'), 'code-size title');
[$jsh] = minn_test_fetch($ENGINE . '/code-size.json');
$check($jsh['status'] === 200, 'theme /code-size.json answers 200', (string) $jsh['status']);
[$cvh, $cv] = minn_test_fetch($ENGINE . '/coverage/');
$check($cvh['status'] === 200, 'theme /coverage/ answers 200', (string) $cvh['status']);
$check(str_contains($cv, '<title>Coverage · Minn</title>') && str_contains($cv, 'id="cov-chart-data"'), 'coverage title and chart data');
[$cfh, $cf] = minn_test_fetch($ENGINE . '/coverage/files/');
$check($cfh['status'] === 200 && str_contains($cf, 'id="cov-files"'), 'theme /coverage/files/ answers 200 with the file table', (string) $cfh['status']);
[$cjh] = minn_test_fetch($ENGINE . '/coverage.json');
$check($cjh['status'] === 200, 'theme /coverage.json answers 200', (string) $cjh['status']);
[$llh, $ll] = minn_test_fetch($ENGINE . '/looks-like/');
$check($llh['status'] === 200, 'theme /looks-like/ answers 200', (string) $llh['status']);
$check(str_contains($ll, '<title>What a host inspects · Minn'), 'looks-like title');
$check(str_contains($ll, 'href="/looks-like/" aria-current="page"'), 'looks-like nav is current');
[$ah, $api] = minn_test_fetch($ENGINE . '/api/');
$check($ah['status'] === 200, 'theme /api/ answers 200', (string) $ah['status']);
$check(str_contains($api, '<title>API · Minn'), 'api page title');
$check(substr_count($api, 'minn-api-namespace"') >= 20, 'api index lists every namespace');
$check(str_contains($api, '/api/content/postrecord/"'), 'api index links a class to its own page');
[$nh, $ns] = minn_test_fetch($ENGINE . '/api/content/');
$check($nh['status'] === 200 && str_contains($ns, '>PostRecord</a> <span'), 'api namespace page lists PostRecord');
$check(str_contains($ns, 'All 21 namespaces') || preg_match('/All \d+ namespaces/', $ns) === 1, 'api namespace page carries the namespace strip');
[$ch, $cls] = minn_test_fetch($ENGINE . '/api/rest/postscontroller/');
$check($ch['status'] === 200 && str_contains($cls, '<title>Minn\\Rest\\PostsController'), 'api class page answers');
$check(str_contains($cls, 'minn-api-badge-private">private') && str_contains($cls, 'id="visibleStatuses"'), 'api class page lists the internals');
$check(str_contains($cls, 'Used by') && str_contains($cls, '/api/rest/api/"'), 'api class page links the classes that use it');
$check(str_contains($cls, '<summary>Source · lines'), 'api class page carries the source of each method');
[$bad] = minn_test_fetch($ENGINE . '/api/rest/nosuchclass/');
$check($bad['status'] === 404, 'api class page 404s for an unknown class', (string) $bad['status']);
[$rh, $rest] = minn_test_fetch($ENGINE . '/api/rest/');
$check(str_contains($rest, 'GET /wp/v2/templates'), 'api namespace page shows a controller route');
[$jh] = minn_test_fetch($ENGINE . '/api.json');
$check($jh['status'] === 200 && str_starts_with((string) ($jh['content-type'] ?? ''), 'application/json'), 'theme /api.json answers 200 as JSON');
[$ph, $php] = minn_test_fetch($ENGINE . '/php/');
$check($ph['status'] === 200, 'theme /php/ answers 200', (string) $ph['status']);
$check(str_contains($php, '<title>How the PHP reads · Minn'), 'php page title');
$check(str_contains($php, 'href="/php/" aria-current="page"'), 'php nav is current');
$check(substr_count($php, 'class="minn-php-pair"') === 6, 'six WordPress / Minn comparisons');
$check(substr_count($php, 'class="minn-php-caption"') === 6, 'each comparison carries its caption');
$check(substr_count($php, 'class="minn-php-copy"') === 12, 'a copy button on every pane');
$check(str_contains($php, 'minn-php-pane-count">') && str_contains($php, 'fewer lines</div>'), 'panes count their lines and the Minn pane says the difference');
$check(str_contains($php, '/api/minn/http/"'), 'the Minn pane links a class to its API page');
$check(str_contains($php, '<code>PostFilter</code>') && str_contains($php, '<code>Minn\\Http</code>'), 'php page names the post filter and HTTP client');
[$pmd] = minn_test_fetch($ENGINE . '/php.md');
$check($pmd['status'] === 200, 'theme /php.md answers 200', (string) $pmd['status']);
foreach (['/wp-content/themes/minn-site/style.css', '/wp-content/themes/minn-site/assets/fonts/hanken-grotesk.woff2', '/wp-content/themes/minn-site/assets/fonts/jetbrains-mono.woff2', '/wp-content/themes/minn-site/assets/img/favicon.webp'] as $asset) {
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
