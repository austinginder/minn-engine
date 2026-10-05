<?php
/**
 * The Requests library plugins call directly (WpOrg\Requests\*, the
 * deprecated Requests_* names) and WordPress's HTTP API on the same wire:
 * stages tests/fixtures/requests/echo.php in the test site's uploads (the
 * reference's server runs it for both stacks), runs
 * tests/tools/requests-probe.php in the parked WordPress (outside WP-CLI,
 * which ships its own copy of the library) and on the engine's runtime,
 * diffs the transcripts row by row, and removes the staged endpoint.
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
$folder = "{$site}/public/wp-content/uploads/minn-requests-probe";
$endpoint = "{$folder}/echo.php";
$unstage = static function () use ($folder, $endpoint): void {
    if (is_file($endpoint)) {
        unlink($endpoint);
    }
    if (is_dir($folder) && array_diff((array) scandir($folder), ['.', '..']) === []) {
        rmdir($folder);
    }
};
register_shutdown_function($unstage);
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}
copy("{$root}/tests/fixtures/requests/echo.php", $endpoint);
$base = minn_test_reference_url() . '/wp-content/uploads/minn-requests-probe';
[$headers] = minn_test_fetch("{$base}/echo.php");
if (($headers['status'] ?? 0) !== 200) {
    echo "  FAIL the reference does not run the staged endpoint at {$base} (status " . ($headers['status'] ?? 0) . ")\n";
    exit(1);
}

$env = 'MINN_REQUESTS_BASE=' . escapeshellarg($base);
$probe = "{$root}/tests/tools/requests-probe.php";
$out = [
    'reference' => trim((string) shell_exec("{$env} php " . escapeshellarg("{$root}/tests/tools/run-reference-probe.php") . ' ' . escapeshellarg("{$site}/wp-reference") . ' ' . escapeshellarg($probe) . ' 2>/dev/null')),
    'engine' => trim((string) shell_exec("{$env} php " . escapeshellarg("{$root}/tests/tools/run-api-probe.php") . ' requests-probe.php 2>/dev/null')),
];
$unstage();
if (($dump = getenv('MINN_REQUESTS_DUMP')) !== false && is_dir($dump)) {
    file_put_contents("{$dump}/req-ref.json", $out['reference']);
    file_put_contents("{$dump}/req-eng.json", $out['engine']);
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
    $check($label, $have && json_encode($byLabel[$label]) === json_encode($value), $have ? substr(json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) . ' vs ' . substr(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 600) : 'missing');
}
// Under WP-CLI the library is WP-CLI's own copy; the engine adds only the WordPress classes around it.
$cli = trim((string) shell_exec('cd ' . escapeshellarg("{$site}/public") . " && wp eval 'echo get_class(new Requests_Response()), \"|\", get_parent_class(\"Requests\"), \"|\", class_exists(\"WP_HTTP_Requests_Hooks\") ? \"yes\" : \"no\";' 2>/dev/null"));
$check('under WP-CLI the engine defers to WP-CLI\'s copy of the library', $cli === 'WpOrg\\Requests\\Response|WpOrg\\Requests\\Requests|yes', $cli);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
