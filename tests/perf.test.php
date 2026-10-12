<?php

declare(strict_types=1);

/**
 * SQL per page against the reference (audit 2026-10-12, goal item 2): the
 * benchmark pages tests/tools/query-count.php fetches, each counted on the
 * database server over both stacks, the fewest of five runs kept. The
 * engine runs no more SELECTs than the reference on any of them, and each
 * page's count stays at or under its ceiling here. A ceiling only falls:
 * lower it when a page needs fewer, never raise one.
 *
 *   php tests/perf.test.php
 */

require_once __DIR__ . '/lib.php';

// Each page's count when this suite was last tightened, plus two. The figures come from the server's
// general log, counted for the test site's database alone (tests/tools/query-count.php), so another
// database's traffic no longer lands in a run's window; the two cover a request of this site's own that
// does. A ceiling only falls.
$selectCeilings = [
    '/' => 28,
    '/hello-world/' => 41,
    '/category/uncategorized/' => 33,
    '/wp-json/wp/v2/posts' => 27,
    '/?s=hello' => 29,
];

[$probe] = minn_test_fetch(minn_test_reference_url() . '/?rest_route=/wp/v2/posts', 3);
if (($probe['status'] ?? 0) !== 200) {
    echo 'SKIP: reference not running at ' . minn_test_reference_url() . "\n";
    exit(0);
}

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

$output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/tools/query-count.php') . ' --runs=5 --json');
$figures = json_decode((string) $output, true);
$check(is_array($figures), 'query-count measured both stacks', substr((string) $output, 0, 200));
foreach ($selectCeilings as $page => $ceiling) {
    $engine = $figures[$page]['engine']['Com_select'] ?? null;
    $reference = $figures[$page]['reference']['Com_select'] ?? null;
    $check(is_int($engine) && is_int($reference) && $reference > 0, "{$page}: both stacks were counted");
    $check(is_int($engine) && is_int($reference) && $engine <= $reference, "{$page}: the engine runs no more SELECTs than the reference", "{$engine} / {$reference}");
    $check(is_int($engine) && $engine <= $ceiling, "{$page}: SELECTs stay at or under {$ceiling}", (string) $engine);
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
