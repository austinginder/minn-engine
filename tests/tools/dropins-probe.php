<?php
/**
 * Drop-ins, read from inside a request that loaded them: which object cache
 * answers and which of its functions the runtime filled in, the groups it was
 * told about, the database class, and what existed when advanced-cache.php
 * loaded. tests/dropins.test.php stages the files from tests/fixtures/dropins
 * and runs this through `wp eval-file` on both stacks.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$cache = $GLOBALS['wp_object_cache'] ?? null;
$say('object cache', [
    defined('MINN_PROBE_OBJECT_CACHE'),
    wp_using_ext_object_cache(),
    is_object($cache) ? get_class($cache) : gettype($cache),
    wp_cache_set('minn-probe', 'stored', 'minn'),
    wp_cache_get('minn-probe', 'minn'),
    wp_cache_get_multiple(['minn-probe', 'nope'], 'minn'),
    wp_cache_set_multiple(['a' => 1, 'b' => 2], 'minn'),
    wp_cache_get('b', 'minn'),
    wp_cache_supports('get_multiple'),
    function_exists('wp_cache_flush_runtime') ? wp_cache_flush_runtime() : 'missing',
    is_object($cache) && property_exists($cache, 'calls') ? $cache->calls > 0 : null,
]);
$groups = $GLOBALS['minn_probe_cache_groups'] ?? [];
foreach ($groups as $kind => $calls) {
    $flat = array_merge(...$calls);
    sort($flat);
    $groups[$kind] = $flat;
}
ksort($groups);
$say('cache groups', $groups);
$say('database class', [get_class($GLOBALS['wpdb']), $GLOBALS['wpdb']->get_var('SELECT 1'), property_exists($GLOBALS['wpdb'], 'probe_queries') ? $GLOBALS['wpdb']->probe_queries > 0 : null]);
$say('advanced cache', $GLOBALS['minn_probe_advanced_cache'] ?? 'not loaded');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
