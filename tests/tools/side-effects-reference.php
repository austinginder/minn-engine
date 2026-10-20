<?php
/**
 * Runs a probe on the reference for tests/tools/side-effects.php (wp
 * eval-file, the probe named in SIDE_EFFECTS_PROBE): marks the run's
 * database connection as the probe's, so the tool's triggers record its
 * writes and no one else's; takes off every hooked callback defined under
 * wp-admin/, which WP-CLI loads and a front-end or REST request never has
 * (update_home_siteurl flushing the rewrite rules on a front page change,
 * WP_Internal_Pointers giving a new user dismissed_wp_pointers; the
 * hook-trace suite shows a REST request without them); then runs the probe
 * in a scope of its own, as eval-file gives one.
 */

$GLOBALS['wpdb']->query('SET @zz_side_effects_probe = 1');
$admin = DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR;
foreach ($GLOBALS['wp_filter'] as $hook => $registered) {
    foreach ($registered->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $function = $callback['function'];
            try {
                $reflection = match (true) {
                    is_string($function) && str_contains($function, '::') => new ReflectionMethod($function),
                    is_string($function) => new ReflectionFunction($function),
                    is_array($function) => new ReflectionMethod($function[0], (string) $function[1]),
                    default => null,
                };
            } catch (ReflectionException) {
                continue;
            }
            if ($reflection !== null && str_contains((string) $reflection->getFileName(), $admin)) {
                remove_filter($hook, $function, $priority);
            }
        }
    }
}
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})((string) getenv('SIDE_EFFECTS_PROBE'));
