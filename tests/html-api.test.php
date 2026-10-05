<?php
/**
 * The HTML API as plugins and core blocks drive it: WP_HTML_Processor (tree
 * construction over the tag processor: breadcrumbs, depth, implied tokens,
 * foreign content, where the reference stops as unsupported, normalize and
 * serialize, queries, bookmarks and edits), its helper classes, and the tag
 * processor underneath. Runs tests/tools/html-processor-probe.php in the
 * parked WordPress and on the engine's runtime and diffs the transcripts
 * row by row.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require __DIR__ . '/lib.php';
$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
};

$site = minn_test_site_root();
$probe = "{$root}/tests/tools/html-processor-probe.php";
$out = [
    'reference' => trim((string) shell_exec('php ' . escapeshellarg("{$root}/tests/tools/run-reference-probe.php") . ' ' . escapeshellarg("{$site}/wp-reference") . ' ' . escapeshellarg($probe) . ' 2>/dev/null')),
    'engine' => trim((string) shell_exec('php ' . escapeshellarg("{$root}/tests/tools/run-api-probe.php") . ' html-processor-probe.php 2>/dev/null')),
];
if (($dump = getenv('MINN_HTML_DUMP')) !== false && is_dir($dump)) {
    file_put_contents("{$dump}/html-ref.json", $out['reference']);
    file_put_contents("{$dump}/html-eng.json", $out['engine']);
}
$expected = json_decode($out['reference'], true);
$actual = json_decode($out['engine'], true);
if (!is_array($expected) || !is_array($actual)) {
    echo '  FAIL the probe did not produce JSON on ' . (!is_array($expected) ? 'the reference' : 'the engine') . ":\n" . substr(!is_array($expected) ? $out['reference'] : $out['engine'], 0, 2000) . "\n";
    exit(1);
}
$byLabel = [];
foreach ($actual as [$label, $value]) {
    $byLabel[$label] = $value;
}
foreach ($expected as [$label, $value]) {
    $have = array_key_exists($label, $byLabel);
    // Long rows (the corpora) report their first differing case, not the whole row.
    $detail = 'missing';
    if ($have && is_array($value) && array_is_list($value)) {
        foreach ($value as $i => $case) {
            if (json_encode($case) !== json_encode($byLabel[$label][$i] ?? null)) {
                $detail = 'case ' . $i . ': ' . substr(json_encode($byLabel[$label][$i] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) . ' vs ' . substr(json_encode($case, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500);
                break;
            }
        }
    } elseif ($have) {
        $detail = substr(json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500) . ' vs ' . substr(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 500);
    }
    $check($label, $have && json_encode($byLabel[$label]) === json_encode($value), $detail);
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
