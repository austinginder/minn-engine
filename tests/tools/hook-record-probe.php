<?php
/**
 * Runs an api probe with every hook it fires recorded (the hook-parity
 * suite's probe scenarios): MINN_HOOK_PROBE names the probe in this
 * directory, MINN_HOOK_RECORD the file the hooks go to. The probe's own
 * output is its business; only the record matters here. MINN_HOOK_LISTEN
 * may name a JSON list of dynamic hooks to listen to as well.
 *
 * Reference: MINN_HOOK_PROBE=x-probe.php MINN_HOOK_RECORD=/tmp/x.json wp eval-file tests/tools/hook-record-probe.php
 * Engine:    MINN_HOOK_PROBE=x-probe.php MINN_HOOK_RECORD=/tmp/x.json php tests/tools/run-api-probe.php hook-record-probe.php
 */

require_once dirname(__DIR__) . '/fixtures/mu-plugins/minn-test-hooks.php';
// WP-CLI hands the reference's probe a site that has run init (and dropped
// kses on it); the engine's runner stops before init. The engine runs it
// here, unrecorded, so both start the probe from the same place.
if (!defined('WP_CLI') && !did_action('init')) {
    do_action('init');
    kses_remove_filters();
}
$listen = getenv('MINN_HOOK_LISTEN') ? json_decode((string) @file_get_contents((string) getenv('MINN_HOOK_LISTEN')), true) : [];
minn_test_hooks_record((string) getenv('MINN_HOOK_RECORD'), is_array($listen) ? array_map('strval', $listen) : []);
(static function (string $probe): void {
    require __DIR__ . '/' . basename($probe);
})((string) getenv('MINN_HOOK_PROBE'));
