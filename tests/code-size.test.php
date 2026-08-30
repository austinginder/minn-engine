<?php

declare(strict_types=1);

/**
 * The code-size report tests/tools/code-size.php writes to
 * contracts/code-size.json. The marketing page that renders it lives in
 * the minn-site theme, not here.
 *
 *   php tests/code-size.test.php
 */

$ROOT = dirname(__DIR__);
$JSON = $ROOT . '/contracts/code-size.json';

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
$report = json_decode((string) file_get_contents($JSON), true);
$check(is_array($report) && isset($report['stacks']) && count($report['stacks']) >= 2, 'report has two stacks');
$check(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($report['measured'] ?? '')) === 1, 'measured is a date', (string) ($report['measured'] ?? ''));

$byId = [];
foreach ($report['stacks'] as $stack) {
    $byId[$stack['id']] = $stack;
}
$wordpress = $byId['wordpress'] ?? null;
$minn = $byId['minn'] ?? null;
$check($wordpress !== null && $minn !== null, 'wordpress and minn stacks are present');
$check(preg_match('/^\d+\.\d+/', (string) ($wordpress['version'] ?? '')) === 1, 'WordPress version is a release number', (string) ($wordpress['version'] ?? ''));
$check(str_contains((string) ($wordpress['source'] ?? ''), 'wordpress.org') || str_contains((string) ($wordpress['source'] ?? ''), 'local tree'), 'WordPress was measured from a release zip or a named tree', (string) ($wordpress['source'] ?? ''));
$check(isset($minn['adminVersion']) && preg_match('/^\d+\.\d+\.\d+$/', (string) $minn['adminVersion']) === 1, 'Minn Admin version recorded', (string) ($minn['adminVersion'] ?? ''));

foreach ([$wordpress, $minn] as $stack) {
    if ($stack === null) {
        continue;
    }
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
$ids = array_column($wordpress['components'] ?? [], 'id');
foreach (['wp-admin', 'wp-includes', 'editor-packages', 'libraries', 'root'] as $id) {
    $check(in_array($id, $ids, true), "WordPress has the $id row");
}
$ids = array_column($minn['components'] ?? [], 'id');
foreach (['engine', 'facade', 'admin-php', 'admin-app'] as $id) {
    $check(in_array($id, $ids, true), "Minn has the $id row");
}
$check(($wordpress['totals']['lines'] ?? 0) > 5 * ($minn['totals']['lines'] ?? 1), 'WordPress is several times the size of Minn', number_format((int) ($wordpress['totals']['lines'] ?? 0)) . ' vs ' . number_format((int) ($minn['totals']['lines'] ?? 0)));
$ratio = static fn (int $a, int $b): float => $b === 0 ? 0.0 : round($a / $b, 1);
$check($ratio(1500, 1000) === 1.5 && $ratio(1, 0) === 0.0, 'ratio rounds to one decimal and survives zero');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
