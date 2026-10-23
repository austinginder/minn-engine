<?php

declare(strict_types=1);

/**
 * The file layout tooling reads: the shape files at the webroot, their
 * templates under minn/layout, and the WP-CLI commands that depend on
 * them (core version, config, db) compared with the reference on the
 * same database. Commands that need WordPress itself fail with the
 * engine's own message, not a fatal.
 */

require_once __DIR__ . '/lib.php';
$ROOT = dirname(__DIR__);
$PUBLIC = minn_test_site_root() . '/public';
$REF_DIR = minn_test_site_root() . '/wp-reference';

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail !== '' ? "\n      {$detail}" : '') . "\n";
    }
};

echo "layout suite: $PUBLIC\n";

foreach (['index.php', 'wp-login.php', 'wp-settings.php', 'wp-cli.yml', 'wp-includes/version.php', 'wp-admin/index.php'] as $file) {
    $check("shape file $file present", is_file("$PUBLIC/$file"));
    $check("minn/layout/$file matches the webroot copy", is_file("$PUBLIC/minn/layout/$file") && file_get_contents("$PUBLIC/minn/layout/$file") === file_get_contents("$PUBLIC/$file"));
}
foreach (['wp-load.php', 'wp-blog-header.php', 'xmlrpc.php'] as $file) {
    $check("no $file (nothing here runs WordPress)", !file_exists("$PUBLIC/$file"));
}
$version = (string) file_get_contents("$PUBLIC/wp-includes/version.php");
$check('version.php declares $wp_version the way readers parse it', preg_match("/^\\\$wp_version = '\\d+\\.\\d+(?:\\.\\d+)?';$/m", $version) === 1);
$check('version.php declares $wp_db_version', preg_match('/^\$wp_db_version = \d+;$/m', $version) === 1);

if (!is_file("$REF_DIR/wp-load.php")) {
    echo "\n  (no wp-reference; command comparisons skipped)\n{$pass} passed, {$fail} failed\n";
    exit($fail === 0 ? 0 : 1);
}

// The entry points an install lays (data/entry-files.json) are the reference's own, from its listing.
$listed = array_merge(
    array_map('basename', glob("$REF_DIR/{wp-*,xmlrpc}.php", GLOB_BRACE) ?: []),
    array_map(static fn (string $f): string => 'wp-admin/' . basename($f), glob("$REF_DIR/wp-admin/*.php") ?: []),
);
$laidByName = ['index.php', 'wp-login.php', 'wp-settings.php', 'wp-load.php', 'wp-blog-header.php', 'wp-config.php', 'wp-config-sample.php', 'wp-admin/index.php'];
$expected = array_values(array_diff($listed, $laidByName));
sort($expected);
$shipped = json_decode((string) file_get_contents("$ROOT/public/minn/data/entry-files.json"), true);
$check('data/entry-files.json names the reference\'s entry points (php tests/tools/entry-files.php when it drifts)', $shipped === $expected, json_encode([array_values(array_diff($expected, (array) $shipped)), array_values(array_diff((array) $shipped, $expected))]));
$check('the entry-point template boots like index.php and does nothing mid-request', str_contains((string) file_get_contents("$ROOT/public/minn/layout/entry.php"), "if (defined('MINN_ENGINE_DIR')) {\n    return;\n}") && str_contains((string) file_get_contents("$ROOT/public/minn/layout/entry.php"), "require_once __DIR__ . '/wp-config.php';"));

$run = static function (string $dir, string $command): array {
    $extra = str_contains($dir, 'wp-reference') ? ' --skip-plugins --skip-themes' : '';
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open("wp {$command}{$extra} --no-color", $descriptors, $pipes, $dir);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $out = preg_replace('/^(Notice|Deprecated|Warning): .*$\n?/m', '', (string) $out);
    return [rtrim((string) $out), $code];
};

foreach ([
    'core version',
    'core version --extra',
    'config get DB_NAME',
    'config get table_prefix',
    'db query "SELECT 1"',
    'db export /tmp/minn-layout-probe.sql',
    'db check',
] as $command) {
    [$engineOut, $engineCode] = $run($PUBLIC, $command);
    [$refOut, $refCode] = $run($REF_DIR, $command);
    $check("wp $command matches the reference", $engineOut === $refOut && $engineCode === $refCode, "engine[$engineCode]: " . substr($engineOut, 0, 200) . "\n      ref[$refCode]:    " . substr($refOut, 0, 200));
}

@unlink('/tmp/minn-layout-probe.sql');
// Commands WP-CLI loads WordPress for run on the engine's runtime, which stands in for it.
foreach (['post list --format=count', 'core is-installed', 'db tables --format=csv', 'db size --size_format=b'] as $command) {
    [$engineOut, $engineCode] = $run($PUBLIC, $command);
    [$refOut, $refCode] = $run($REF_DIR, $command);
    $check("wp $command runs on the engine's runtime as on the reference", $engineOut === $refOut && $engineCode === $refCode, "engine[$engineCode]: " . substr($engineOut, 0, 200) . "\n      ref[$refCode]:    " . substr($refOut, 0, 200));
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
