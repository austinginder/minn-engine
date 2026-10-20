<?php
/**
 * Runs a probe on the engine as run-api-probe.php does, for
 * tests/tools/side-effects.php: in the server variables WP-CLI gives a run
 * on the reference, the engine's database connection (the one the facade's
 * wpdb shares) marked as the probe's first, so the tool's triggers record
 * its writes and no one else's.
 */

ini_set('display_errors', 'stderr');
// The server variables WP-CLI sets for a run on the reference, so what a probe writes from them (an address, an agent) compares.
$_SERVER += ['REQUEST_URI' => '', 'SERVER_PROTOCOL' => 'HTTP/1.0', 'HTTP_USER_AGENT' => 'WP CLI 2.12.0', 'REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '127.0.0.1'];
require __DIR__ . '/engine-runtime.php';
Minn\Db::shared()->connection()->query('SET @zz_side_effects_probe = 1');
// The process the probe runs in, which the tool masks where a probe names something after it.
try {
    Minn\Db::shared()->connection()->query("INSERT INTO zz_side_effects (tbl, op, conn, new_row) VALUES ('', 'p', CONNECTION_ID(), '" . getmypid() . "')");
} catch (mysqli_sql_exception) {
    // Run by hand, outside a pass: there is no audit table to mark.
}
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})($argv[1] ?? 'side-effects-baseline-probe.php');
