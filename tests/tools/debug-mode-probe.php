<?php
/**
 * PHP's error settings from the debug constants (probe debug-mode):
 * wp_debug_mode() run from distinct starting values under the site's own
 * WP_DEBUG, WP_DEBUG_LOG and WP_DEBUG_DISPLAY, then with its checks turned
 * off by enable_wp_debug_mode_checks. The test site keeps display off, so
 * the requests that turn it off (ajax, an install, JSON) show nothing here:
 * the unit suite replays the oracle's answers for every combination of
 * constants and requests (contracts/fixtures/debug-mode.json).
 * WP-CLI answers enable_wp_debug_mode_checks with its own debug setup and a
 * false, so the reference's function never runs under it: its callbacks are
 * taken off for the probe and put back after, as are the settings.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$saved = ['error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'), 'log_errors' => ini_get('log_errors'), 'error_log' => ini_get('error_log')];
global $wp_filter;
$cli = isset($wp_filter['enable_wp_debug_mode_checks']) ? clone $wp_filter['enable_wp_debug_mode_checks'] : null;
remove_all_filters('enable_wp_debug_mode_checks');
$state = static fn () => ['error_reporting' => error_reporting(), 'display_errors' => ini_get('display_errors'), 'log_errors' => ini_get('log_errors'), 'error_log' => str_replace(rtrim(ABSPATH, '/'), '{root}', (string) ini_get('error_log'))];
$run = static function (?callable $setup = null, ?callable $undo = null) use ($state): array {
    error_reporting(1);
    ini_set('display_errors', 'stderr');
    ini_set('log_errors', '0');
    ini_set('error_log', '/zz-minn-probe-baseline.log');
    if ($setup !== null) {
        $setup();
    }
    $returned = wp_debug_mode();
    $after = $state();
    if ($undo !== null) {
        $undo();
    }
    return ['returned' => $returned] + $after;
};

$say('the site\'s constants', ['WP_DEBUG' => WP_DEBUG, 'WP_DEBUG_LOG' => WP_DEBUG_LOG, 'WP_DEBUG_DISPLAY' => WP_DEBUG_DISPLAY, 'after' => $run()]);
$off = static fn () => false;
$say('the checks turned off', $run(static fn () => add_filter('enable_wp_debug_mode_checks', $off), static fn () => remove_filter('enable_wp_debug_mode_checks', $off)));
if ($cli !== null) {
    $wp_filter['enable_wp_debug_mode_checks'] = $cli;
}
error_reporting($saved['error_reporting']);
foreach (['display_errors', 'log_errors', 'error_log'] as $name) {
    ini_set($name, (string) $saved[$name]);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
