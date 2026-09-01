<?php

/**
 * Recovery: an extension that kills a request is paused, the next request
 * comes back without it, and nothing loads it again until it is resumed.
 * The paused list is stored where WordPress stores it, so a site that
 * ejects finds the pause it left with.
 *
 *   php tests/recovery.test.php
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

$root = dirname(__DIR__);
$ENGINE = rtrim(getenv('MINN_TEST_URL') ?: 'https://minn.localhost', '/');
$public = minn_test_site_root() . '/public';
$wp = static fn (string $args): string => trim((string) shell_exec('cd ' . escapeshellarg($public) . ' && /opt/homebrew/bin/wp --require=minn/cli.php ' . $args . ' 2>&1'));

$pass = 0;
$fail = 0;
$check = static function (bool $ok, string $label, string $detail = '') use (&$pass, &$fail): void {
    if ($ok) {
        $pass++;
        echo "  ok   {$label}\n";
    } else {
        $fail++;
        echo "  FAIL {$label}" . ($detail === '' ? '' : ": {$detail}") . "\n";
    }
};

$slug = 'minn-recovery-probe';
$dir = $public . '/wp-content/plugins/' . $slug;
$plugin = $slug . '/' . $slug . '.php';

$cleanup = static function () use ($dir, $wp, $plugin, $slug): void {
    $wp('plugin deactivate ' . escapeshellarg($slug));
    $wp('minn recovery resume ' . escapeshellarg($plugin));
    array_map('unlink', glob($dir . '/*') ?: []);
    @rmdir($dir);
};
register_shutdown_function($cleanup);
$cleanup();

@mkdir($dir, 0755, true);
file_put_contents($dir . '/' . $slug . '.php', <<<'PLUGIN'
<?php
/**
 * Plugin Name: Minn Recovery Probe
 */
add_action('init', static function (): void {
    if (!defined('WP_CLI') && PHP_SAPI !== 'cli') {
        minn_recovery_probe_no_such_function();
    }
});
PLUGIN);

$wp('plugin activate ' . escapeshellarg($slug));
$check(str_contains($wp('minn recovery'), 'nothing paused'), 'nothing is paused to begin with');

[$first] = minn_test_fetch($ENGINE . '/');
$check($first['status'] === 500, 'the fatal answers 500', (string) $first['status']);

$status = $wp('minn recovery');
$check(str_contains($status, $plugin), 'the plugin is paused', $status);
$check(str_contains($status, 'minn_recovery_probe_no_such_function'), 'the pause records why', $status);

[$second] = minn_test_fetch($ENGINE . '/');
$check($second['status'] === 200, 'the next request recovers', (string) $second['status']);

// The list is stored the way WordPress stores it.
$option = json_decode($wp('option get paused_plugins --format=json'), true);
$entry = is_array($option) ? ($option[$plugin] ?? null) : null;
$check(
    is_array($entry) && array_keys($entry) === ['type', 'file', 'line', 'message'],
    'paused_plugins holds the WordPress shape',
    json_encode($entry)
);

$check(str_contains($wp('minn recovery resume ' . escapeshellarg($plugin)), 'resumed'), 'resume reports the plugin');
[$third] = minn_test_fetch($ENGINE . '/');
$check($third['status'] === 500, 'a resumed plugin loads again, and fatals again', (string) $third['status']);

$wp('plugin deactivate ' . escapeshellarg($slug));
$wp('minn recovery resume-all');
[$fourth] = minn_test_fetch($ENGINE . '/');
$check($fourth['status'] === 200, 'the site is well once the plugin is off', (string) $fourth['status']);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
