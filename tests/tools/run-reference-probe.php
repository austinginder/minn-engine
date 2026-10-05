<?php
/**
 * Runs a probe inside the parked WordPress without WP-CLI, for the probes
 * whose subject WP-CLI itself ships a copy of (the Requests library: under
 * WP-CLI its copy answers instead of WordPress's). Usage:
 *
 *   php tests/tools/run-reference-probe.php <wordpress dir> <probe file>
 */

[, $dir, $probe] = $argv + [null, null, null];
if (!is_string($dir) || !is_file("{$dir}/wp-load.php") || !is_string($probe) || !is_file($probe)) {
    fwrite(STDERR, "usage: php run-reference-probe.php <wordpress dir> <probe file>\n");
    exit(2);
}
$probe = (string) realpath($probe);
ini_set('display_errors', 'stderr');
$_SERVER += ['HTTP_HOST' => 'minn.localhost', 'SERVER_NAME' => 'minn.localhost', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET', 'SERVER_PROTOCOL' => 'HTTP/1.1'];
define('WP_USE_THEMES', false);
chdir($dir);
require "{$dir}/wp-load.php";
// The probe runs in its own scope, as it does under wp eval-file.
(static function (string $probe): void {
    require $probe;
})($probe);
