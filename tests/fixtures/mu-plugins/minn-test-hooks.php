<?php
/**
 * Plugin Name: Minn test hooks
 * Description: Fixture for the hook-parity suite, loaded by the engine and the reference alike. A request that carries X-Minn-Hooks naming a run the suite opened (wp-content/minn-hooks/<run>.open exists) has every hook it fires noted, once each, with its kind and the most arguments it was given, in wp-content/minn-hooks/<run>.json. tests/tools/hook-record-probe.php records a probe the same way.
 * License: MIT
 */

if (!function_exists('minn_test_hooks_record')) {
    /**
     * Notes every hook fired from now on (action or filter, the most
     * arguments seen, how often) and writes them to $file as the process
     * ends, after everything else that runs at shutdown. Every hook the
     * reference's inventory names exactly gets a callback that passes its
     * value on unchanged, so a stack that fires a hook only when someone
     * listens is recorded as a plugin listening would find it.
     */
    function minn_test_hooks_record(string $file, array $listen = []): void
    {
        $inventory = json_decode((string) @file_get_contents(dirname(__FILE__, 4) . '/contracts/api/hooks.json'), true);
        $pass = static fn ($value = null) => $value;
        // The exact names, and the dynamic instances (rest_prepare_post) the caller names: what the reference fired.
        foreach ([...array_keys(is_array($inventory) ? $inventory : []), ...$listen] as $name) {
            if ($name !== '' && !str_contains((string) $name, '{')) {
                add_filter((string) $name, $pass, PHP_INT_MAX);
            }
        }
        $seen = [];
        add_action('all', static function (string $hook) use (&$seen): void {
            // An action is counted before 'all' runs, a filter is not.
            $kind = did_action($hook) > 0 && doing_action($hook) ? 'action' : 'filter';
            $args = func_num_args() - 1;
            $row = $seen[$hook] ?? [$kind, $args, 0];
            $seen[$hook] = [$row[0] === 'action' || $kind === 'action' ? 'action' : 'filter', max($row[1], $args), $row[2] + 1];
        }, PHP_INT_MIN);
        register_shutdown_function(static function () use (&$seen, $file): void {
            // Registered from shutdown, this runs after the handlers registered before it.
            register_shutdown_function(static function () use (&$seen, $file): void {
                // A scenario that redirects is one record across its requests.
                $before = json_decode((string) @file_get_contents($file), true);
                foreach (is_array($before) ? $before : [] as $hook => $row) {
                    $mine = $seen[$hook] ?? null;
                    $seen[$hook] = $mine === null ? $row : [$mine[0] === 'action' || $row[0] === 'action' ? 'action' : 'filter', max($mine[1], $row[1]), $mine[2] + $row[2]];
                }
                ksort($seen);
                file_put_contents($file, json_encode($seen, JSON_UNESCAPED_SLASHES));
            });
        });
    }
}

$minnHooksRun = (string) ($_SERVER['HTTP_X_MINN_HOOKS'] ?? '');
$minnHooksDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-hooks';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnHooksRun) === 1 && is_file("{$minnHooksDir}/{$minnHooksRun}.open")) {
    // The marker may name dynamic hooks to listen to as well (a JSON list).
    $minnHooksListen = json_decode((string) file_get_contents("{$minnHooksDir}/{$minnHooksRun}.open"), true);
    minn_test_hooks_record("{$minnHooksDir}/{$minnHooksRun}.json", is_array($minnHooksListen) ? array_map('strval', $minnHooksListen) : []);
}
