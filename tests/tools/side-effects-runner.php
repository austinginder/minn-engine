<?php
/**
 * Runs a probe on the engine as run-api-probe.php does, for
 * tests/tools/side-effects.php: the engine's database connection (the one
 * the facade's wpdb shares) is marked as the probe's first, so the tool's
 * triggers record its writes and no one else's.
 */

ini_set('display_errors', 'stderr');
require __DIR__ . '/engine-runtime.php';
Minn\Db::shared()->connection()->query('SET @zz_side_effects_probe = 1');
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})($argv[1] ?? 'side-effects-baseline-probe.php');
