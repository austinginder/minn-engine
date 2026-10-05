<?php
/**
 * Translations that need language files in place before a request starts
 * (the reference reads the languages folder once per request): stages them,
 * runs tests/tools/l10n-staged-probe.php on the reference and on the engine,
 * diffs the two transcripts row by row, and removes what it staged.
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
$fixture = "{$root}/tests/fixtures/languages/minn-probe-pl_PL.mo";
$languages = "{$site}/public/wp-content/languages";
$staged = [
    "{$languages}/plugins/minn-probe-jit-pl_PL.mo",
    "{$languages}/plugins/minn-probe-lpt-pl_PL.mo",
    "{$languages}/themes/minn-probe-theme-pl_PL.mo",
    "{$languages}/plugins/minn-probe-close-pl_PL.mo",
    "{$languages}/pl_PL.mo",
    "{$site}/public/wp-content/themes/minn-probe-l10n-theme/pl_PL.mo",
];
foreach (["{$site}/public/wp-content/plugins", "{$site}/wp-reference/wp-content/plugins"] as $plugins) {
    $staged[] = "{$plugins}/minn-probe-l10n-theme/pl_PL.mo";
}
$unstage = static function () use ($staged): void {
    foreach ($staged as $file) {
        if (is_file($file)) {
            unlink($file);
        }
        $dir = dirname($file);
        if (str_ends_with($dir, 'minn-probe-l10n-theme') || (str_ends_with($dir, 'languages/themes') && is_dir($dir) && count(scandir($dir)) === 2)) {
            @rmdir($dir);
        }
    }
};
$unstage();
foreach ($staged as $file) {
    @mkdir(dirname($file), 0777, true);
    copy($fixture, $file);
}
register_shutdown_function($unstage);

$reference = (string) shell_exec('cd ' . escapeshellarg("{$site}/wp-reference") . ' && wp eval-file ' . escapeshellarg("{$root}/tests/tools/l10n-staged-probe.php") . ' 2>/dev/null');
$engine = (string) shell_exec('MINN_SITE_ROOT=' . escapeshellarg($site) . ' php ' . escapeshellarg("{$root}/tests/tools/run-api-probe.php") . ' l10n-staged-probe.php 2>/dev/null');
$unstage();
if (($dump = getenv('MINN_L10N_DUMP')) !== false && is_dir($dump)) {
    file_put_contents("{$dump}/l10n-staged-ref.json", $reference);
    file_put_contents("{$dump}/l10n-staged-eng.json", $engine);
}

$expected = json_decode($reference, true);
$actual = json_decode($engine, true);
if (!is_array($expected) || !is_array($actual)) {
    echo "  FAIL the probe did not produce JSON on " . (!is_array($expected) ? 'the reference' : 'the engine') . ":\n" . substr(!is_array($expected) ? $reference : $engine, 0, 2000) . "\n";
    exit(1);
}
$check('row count', count($actual) === count($expected), count($actual) . ' vs ' . count($expected));
$byLabel = [];
foreach ($actual as [$label, $value]) {
    $byLabel[$label] = $value;
}
foreach ($expected as [$label, $value]) {
    $have = array_key_exists($label, $byLabel);
    $check($label, $have && json_encode($byLabel[$label]) === json_encode($value), ($have ? json_encode($byLabel[$label], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'missing') . ' vs ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
