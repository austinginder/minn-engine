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
// The strikes are the engine's guarded row: WP-CLI's option delete is refused it, the engine's verb forgets them.
$wp('minn recovery resume-all');
$check(str_contains($wp('minn recovery'), 'nothing paused'), 'nothing is paused to begin with');

[$first] = minn_test_fetch($ENGINE . '/');
$check($first['status'] === 500, 'the fatal answers 500', (string) $first['status']);
// The visitor's request only notes the failure; the engine then asks the site once itself, carrying nothing the
// visitor chose, and that request fails on the plugin too: it is paused (Austin, 2026-10-24).
$status = $wp('minn recovery');
$check(str_contains($status, $plugin), 'a boot failure the engine\'s own request meets too pauses the plugin', $status);
$check(str_contains($wp('option get minn_recovery_strikes'), 'Could not get') || !str_contains($wp('option get minn_recovery_strikes'), 'plugin:' . $plugin), 'a pause clears its strikes');
$check(str_contains($wp('option get minn_recovery_check'), 'Could not get'), 'and spends the check\'s token', $wp('option get minn_recovery_check'));
$check(str_contains($status, 'minn_recovery_probe_no_such_function'), 'the pause records why', $status);

[$second] = minn_test_fetch($ENGINE . '/');
$check($second['status'] === 200, 'the next request recovers', (string) $second['status']);

// WordPress's recovery API reads the engine's own pauses (Austin, D4; probe recovery pins the answers with nothing paused).
$read = json_decode($wp('eval ' . escapeshellarg('echo json_encode(["all" => array_keys(wp_paused_plugins()->get_all()), "by folder" => wp_paused_plugins()->get(' . var_export($slug, true) . ') !== null, "by file" => wp_paused_plugins()->get(' . var_export($plugin, true) . ') !== null, "set" => wp_paused_plugins()->set("zz-other", []), "skipped" => array_values(array_map("plugin_basename", wp_skip_paused_plugins([WP_PLUGIN_DIR . "/" . ' . var_export($plugin, true) . ', WP_PLUGIN_DIR . "/hello.php"]))), "themes" => wp_paused_themes()->get_all()]);')), true);
$check($read === ['all' => [$plugin], 'by folder' => true, 'by file' => true, 'set' => false, 'skipped' => ['hello.php'], 'themes' => []], 'wp_paused_plugins() and wp_skip_paused_plugins() answer from the engine\'s pause; set() is refused', json_encode($read));

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

// A plugin whose hook fails inside engine code is still the plugin's failure: the trace's first plugin frame is
// blamed (the 0.1.0 field test's Kinsta site stayed down when only the throwing file was; Austin, 2026-10-24).
$engineSlug = 'minn-recovery-engine-probe';
$engineDir = $public . '/wp-content/plugins/' . $engineSlug;
$enginePlugin = $engineSlug . '/' . $engineSlug . '.php';
$engineCleanup = static function () use ($engineDir, $wp, $engineSlug): void {
    $wp('plugin deactivate ' . escapeshellarg($engineSlug));
    $wp('minn recovery resume-all');
    array_map('unlink', glob($engineDir . '/*') ?: []);
    @rmdir($engineDir);
};
register_shutdown_function($engineCleanup);
@mkdir($engineDir, 0755, true);
file_put_contents($engineDir . '/' . $engineSlug . '.php', <<<'PLUGIN'
<?php
/**
 * Plugin Name: Minn Recovery Engine Probe
 */
add_action('init', static function (): void {
    if (!defined('WP_CLI') && PHP_SAPI !== 'cli') {
        Minn\Json::decode(['not a string']);
    }
});
PLUGIN);
$wp('plugin activate ' . escapeshellarg($engineSlug));
[$inEngine] = minn_test_fetch($ENGINE . '/');
$status = $wp('minn recovery');
$check($inEngine['status'] === 500 && str_contains($status, $enginePlugin), 'a plugin whose hook fails inside engine code is blamed through its trace, and paused', $inEngine['status'] . ' ' . $status);
$engineCleanup();
[$wellAgain] = minn_test_fetch($ENGINE . '/');
$check($wellAgain['status'] === 200, 'the site is well once that plugin is off', (string) $wellAgain['status']);

// Deleting through WordPress's storage lets the extension load again.
$wp('option update paused_plugins ' . escapeshellarg(json_encode(['zz-paused/zz-paused.php' => ['type' => 1, 'file' => 'x.php', 'line' => 1, 'message' => 'ZZ']])) . ' --format=json');
$deleted = json_decode($wp('eval ' . escapeshellarg('echo json_encode([wp_paused_plugins()->delete("zz-paused"), wp_paused_plugins()->get_all(), wp_paused_plugins()->delete("zz-paused"), wp_paused_plugins()->delete_all()]);')), true);
$check($deleted === [true, [], false, false], 'delete() resumes a pause and says false once there is nothing left, as delete_all() does', json_encode($deleted));
$wp('option delete paused_plugins');

// A failure after boot answers to one request's input and never pauses anything,
// however often it happens: a route that throws is not grounds to take a plugin down.
$restSlug = 'minn-recovery-rest-probe';
$restDir = $public . '/wp-content/plugins/' . $restSlug;
$restCleanup = static function () use ($restDir, $wp, $restSlug): void {
    $wp('plugin deactivate ' . escapeshellarg($restSlug));
    $wp('minn recovery resume-all');
    array_map('unlink', glob($restDir . '/*') ?: []);
    @rmdir($restDir);
};
register_shutdown_function($restCleanup);
@mkdir($restDir, 0755, true);
file_put_contents($restDir . '/' . $restSlug . '.php', <<<'PLUGIN'
<?php
/**
 * Plugin Name: Minn Recovery REST Probe
 */
add_action('rest_api_init', static function (): void {
    register_rest_route('minn-recovery/v1', '/boom', [
        'methods' => 'GET',
        'callback' => static function (): void {
            minn_recovery_rest_probe_no_such_function();
        },
        'permission_callback' => '__return_true',
    ]);
});
add_shortcode('minn_recovery_boom', static function (): string {
    minn_recovery_rest_probe_no_such_function();
});
PLUGIN);
$wp('plugin activate ' . escapeshellarg($restSlug));
$wp('minn recovery resume-all');
[$boom1] = minn_test_fetch($ENGINE . '/wp-json/minn-recovery/v1/boom');
[$boom2] = minn_test_fetch($ENGINE . '/wp-json/minn-recovery/v1/boom');
[$boom3] = minn_test_fetch($ENGINE . '/wp-json/minn-recovery/v1/boom');
$check($boom1['status'] === 500 && $boom3['status'] === 500, 'a route that fatals answers 500 each time', $boom1['status'] . '/' . $boom2['status'] . '/' . $boom3['status']);
$check(str_contains($wp('minn recovery'), 'nothing paused'), 'three route failures pause nothing', $wp('minn recovery'));
$check(str_contains($wp('option get minn_recovery_strikes'), 'Could not get'), 'and record no strike', $wp('option get minn_recovery_strikes'));
[$well] = minn_test_fetch($ENGINE . '/');
$check($well['status'] === 200, 'the site itself stays up', (string) $well['status']);

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
