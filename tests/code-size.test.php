<?php

declare(strict_types=1);

/**
 * The code-size comparison: contracts/code-size.json is the report
 * (tests/tools/code-size.php writes it), /code-size/ the page, /code-size.json
 * the report raw. Engine-only; the reference 404s the path.
 *
 *   php tests/code-size.test.php
 */

putenv('MINN_TEST_KEEP_THEME=1');
require __DIR__ . '/lib.php';

$ROOT = dirname(__DIR__);
$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn-engine.localhost', '/');
$JSON = $ROOT . '/contracts/code-size.json';

require_once "$ROOT/public/minn/src/Minn/Autoloader.php";
Minn\Autoloader::register();

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

$check(is_file($JSON), 'contracts/code-size.json is on disk');
$check(Minn\Front\CodeSize::locate("$ROOT/public/minn") === $JSON, 'locate() finds the site-root contracts file');
$check(Minn\Front\CodeSize::locate(sys_get_temp_dir() . '/minn-no-report/x') === null, 'locate() is null when the report is absent');

$report = Minn\Front\CodeSize::fromFile($JSON);
$check(preg_match('/^\d{4}-\d{2}-\d{2}$/', $report->measured) === 1, 'measured is a date', $report->measured);
$wordpress = $report->stack('wordpress');
$minn = $report->stack('minn');
$check(preg_match('/^\d+\.\d+/', (string) $wordpress['version']) === 1, 'WordPress version is a release number', (string) $wordpress['version']);
$check(str_contains((string) $wordpress['source'], 'wordpress.org') || str_contains((string) $wordpress['source'], 'local tree'), 'WordPress was measured from a release zip or a named tree', (string) $wordpress['source']);
$check(isset($minn['adminVersion']) && preg_match('/^\d+\.\d+\.\d+$/', (string) $minn['adminVersion']) === 1, 'Minn Admin version recorded', (string) ($minn['adminVersion'] ?? ''));

foreach ([$wordpress, $minn] as $stack) {
    $sum = ['files' => 0, 'lines' => 0, 'bytes' => 0];
    $own = 0;
    $third = 0;
    foreach ($stack['components'] as $row) {
        foreach ($sum as $key => $_) {
            $sum[$key] += $row[$key];
        }
        $row['thirdParty'] ? $third += $row['lines'] : $own += $row['lines'];
        $languages = array_sum(array_map(static fn (array $language) => $language['lines'], $row['languages']));
        $check($languages === $row['lines'], "{$stack['id']}: {$row['id']} language lines add up", "$languages vs {$row['lines']}");
    }
    $check($sum === array_intersect_key($stack['totals'], $sum), "{$stack['id']}: totals equal the component sum", json_encode($sum));
    $check($own === $stack['own']['lines'] && $third === $stack['thirdParty']['lines'], "{$stack['id']}: own and third-party split", "$own / $third");
    $check($stack['totals']['lines'] > 0 && $stack['totals']['bytes'] > $stack['totals']['lines'], "{$stack['id']}: counts are plausible");
}
$ids = array_column($wordpress['components'], 'id');
foreach (['wp-admin', 'wp-includes', 'editor-packages', 'libraries', 'root'] as $id) {
    $check(in_array($id, $ids, true), "WordPress has the $id row");
}
$ids = array_column($minn['components'], 'id');
foreach (['engine', 'facade', 'admin-php', 'admin-app'] as $id) {
    $check(in_array($id, $ids, true), "Minn has the $id row");
}
$check($wordpress['totals']['lines'] > 5 * $minn['totals']['lines'], 'WordPress is several times the size of Minn', number_format($wordpress['totals']['lines']) . ' vs ' . number_format($minn['totals']['lines']));
$check(Minn\Front\CodeSize::ratio(1500, 1000) === 1.5 && Minn\Front\CodeSize::ratio(1, 0) === 0.0, 'ratio() rounds to one decimal and survives zero');

[$headers, $body] = minn_test_fetch("$ENGINE/code-size/");
$check($headers['status'] === 200, 'GET /code-size/ is 200', (string) $headers['status']);
$check(str_contains($body, '<title>Code size · Minn</title>'), 'title is Code size · Minn');
$check(str_contains($body, '<h1>' . $wordpress['label'] . ' ' . $wordpress['version'] . ' and Minn ' . $minn['version'] . '</h1>'), 'h1 names both versions');
$check(str_contains($body, 'minn-size-big">' . number_format($wordpress['totals']['lines']) . '<'), 'WordPress total is on the page');
$check(str_contains($body, 'minn-size-big">' . number_format($minn['totals']['lines']) . '<'), 'Minn total is on the page');
$check(str_contains($body, 'Minn Admin ' . $minn['adminVersion']), 'Minn Admin version is on the page');
$check(str_contains($body, 'href="/code-size/" aria-current="page"'), 'the nav marks Code size current');
$check(str_contains($body, 'href="/lexicon/"') && !str_contains($body, 'href="/lexicon/" aria-current'), 'the lexicon link is present and not current');
$check(str_contains($body, 'id="languages"') && str_contains($body, 'id="method"') && str_contains($body, 'id="wordpress"') && str_contains($body, 'id="minn"'), 'the four sections are on the page');
$check(str_contains($body, '/code-size.json"'), 'links to the raw report');
$check(substr_count($body, '<table>') === 3, 'three tables: languages and one per stack', (string) substr_count($body, '<table>'));
$check(!preg_match('/[^-]—[^-]/', strip_tags($body)), 'no em dash inside prose');
$check(str_contains($body, 'served by'), 'footer chrome is present');

$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['ignore_errors' => true, 'follow_location' => 0]]);
@file_get_contents("$ENGINE/code-size", false, $ctx);
$redirect = implode("\n", $http_response_header ?? []);
$check(str_contains($redirect, ' 301') && str_contains($redirect, 'Location: ') && str_ends_with(trim((string) preg_replace('/.*Location: (\S+).*/s', '$1', $redirect)), '/code-size/'), '/code-size redirects 301 to /code-size/', $redirect);
[$rawHeaders, $raw] = minn_test_fetch("$ENGINE/code-size.json");
$check($rawHeaders['status'] === 200 && str_starts_with((string) ($rawHeaders['content-type'] ?? ''), 'application/json'), '/code-size.json is served as JSON');
$check(json_decode($raw, true) === json_decode((string) file_get_contents($JSON), true), '/code-size.json is the report');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
