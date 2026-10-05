<?php
/**
 * Reading other sites' feeds: stages the documents in tests/fixtures/feeds
 * into the test site's uploads (the reference's server serves them to both
 * stacks), runs tests/tools/feeds-probe.php through WP-CLI on the reference
 * and on the engine, diffs the two transcripts row by row, and removes the
 * staged folder, also when a check dies halfway.
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
$folder = "{$site}/public/wp-content/uploads/minn-feed-probe";
$base = minn_test_reference_url() . '/wp-content/uploads/minn-feed-probe';
$staged = [];
$unstage = static function () use (&$staged, $folder): void {
    foreach ($staged as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $staged = [];
    if (is_dir($folder) && array_diff((array) scandir($folder), ['.', '..']) === []) {
        rmdir($folder);
    }
};
register_shutdown_function($unstage);
if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
}
foreach (glob("{$root}/tests/fixtures/feeds/*") ?: [] as $file) {
    $staged[] = $to = "{$folder}/" . basename($file);
    copy($file, $to);
}
[$headers] = minn_test_fetch("{$base}/rss2.xml");
if (($headers['status'] ?? 0) !== 200) {
    echo "  FAIL the reference does not serve the staged feeds at {$base} (status " . ($headers['status'] ?? 0) . ")\n";
    exit(1);
}

$probe = escapeshellarg("{$root}/tests/tools/feeds-probe.php");
$env = 'MINN_FEED_BASE=' . escapeshellarg($base);
$out = [];
foreach (['reference' => "{$site}/wp-reference", 'engine' => "{$site}/public"] as $name => $dir) {
    $out[$name] = trim((string) shell_exec('cd ' . escapeshellarg($dir) . " && {$env} wp eval-file {$probe} 2>/dev/null"));
}
$unstage();
if (($dump = getenv('MINN_FEEDS_DUMP')) !== false && is_dir($dump)) {
    file_put_contents("{$dump}/feeds-ref.json", $out['reference']);
    file_put_contents("{$dump}/feeds-eng.json", $out['engine']);
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
$names = implode(',', array_map(static fn (string $file): string => "'_site_transient_feed_" . md5("{$base}/" . basename($file)) . "'", glob("{$root}/tests/fixtures/feeds/*") ?: []));
$left = trim((string) shell_exec('cd ' . escapeshellarg("{$site}/public") . " && wp db query \"SELECT COUNT(*) FROM wp_options WHERE option_name IN ({$names})\" --skip-column-names 2>/dev/null"));
$check('the probe leaves no cached feed behind on either stack', $left === '0', $left);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
