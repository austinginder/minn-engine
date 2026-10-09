<?php
/**
 * The object cache class (probe object-cache): WP_Object_Cache as plugins
 * reach it through $wp_object_cache. Values are added, set, replaced,
 * fetched (found or not, objects copied), counted up and down, and deleted,
 * one at a time and several at once. Keys are checked, groups emptied,
 * hits and misses counted, and the stats printed. Only the probe's own
 * groups are touched. Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wp_object_cache;
$cache = $wp_object_cache;
// A bad key is reported through _doing_it_wrong; what it says is kept, the notice is not raised.
$wrong = [];
$listen = static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
};
add_action('doing_it_wrong_run', $listen, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$say('the class', [get_class($cache) === WP_Object_Cache::class, $cache instanceof WP_Object_Cache, is_int($cache->cache_hits), is_int($cache->cache_misses)]);

$found = static function (Closure $call) {
    $found = null;
    $value = $call($found);
    return [$value, $found];
};
$g = 'zz_oc';
$say('add, set, replace', [
    $cache->add('a', 'one', $g),
    $cache->add('a', 'again', $g),
    $cache->get('a', $g),
    $cache->replace('a', 'replaced', $g),
    $cache->replace('missing', 'x', $g),
    $cache->get('missing', $g),
    $cache->set('b', ['x' => 1], $g),
    $cache->set('b', ['x' => 2], $g),
    $cache->get('b', $g),
    $cache->set('', 'empty key', $g),
    $cache->add(false, 'false key', $g),
    $cache->set(12, 'int key', $g),
    $cache->get('12', $g),
    $cache->set('c', 'default group'),
    $cache->get('c'),
    $cache->get('c', ''),
    $cache->get('c', 'default'),
]);
$say('found', [
    $found(static fn (&$f) => $cache->get('a', $g, false, $f)),
    $found(static fn (&$f) => $cache->get('nope', $g, false, $f)),
    $found(static fn (&$f) => $cache->get('a', $g, true, $f)),
    (static function () use ($cache, $g) {
        $cache->set('falsy', false, $g);
        $f = null;
        return [$cache->get('falsy', $g, false, $f), $f];
    })(),
]);
$object = new stdClass();
$object->n = 1;
$cache->set('obj', $object, $g);
$object->n = 2;
$copy = $cache->get('obj', $g);
$copy->n = 3;
$say('objects copied', [$cache->get('obj', $g)->n, $object->n]);

$say('incr and decr', [
    $cache->set('n', 5, $g),
    $cache->incr('n', 1, $g),
    $cache->incr('n', 10, $g),
    $cache->decr('n', 3, $g),
    $cache->decr('n', 100, $g),
    $cache->incr('missing-n', 1, $g),
    (static function () use ($cache, $g) {
        $cache->set('s', 'abc', $g);
        return [$cache->incr('s', 2, $g), $cache->get('s', $g)];
    })(),
    (static function () use ($cache, $g) {
        $cache->set('f', '7.5', $g);
        return [$cache->incr('f', 1, $g), $cache->decr('f', '2', $g)];
    })(),
]);
$say('several at once', [
    $cache->add_multiple(['m1' => 1, 'm2' => 2, 'a' => 'not again'], $g),
    $cache->set_multiple(['m2' => 'two', 'm3' => 3], $g),
    $cache->get_multiple(['m1', 'm2', 'm3', 'm4', 'a'], $g),
    $cache->delete_multiple(['m1', 'm4'], $g),
    $cache->get_multiple(['m1', 'm2'], $g),
    $cache->get_multiple([], $g),
]);
$say('delete', [$cache->delete('a', $g), $cache->delete('a', $g), $cache->get('a', $g), $cache->delete('', $g)]);
$say('bad keys reported', [$wrong, (static function () use ($cache, $g, &$wrong) {
    $wrong = [];
    return [$cache->get('', $g), $cache->get(null, $g), $cache->get(1.5, $g), $cache->get([], $g), $cache->incr('', 1, $g), $cache->replace(true, 'x', $g), $wrong];
})()]);
remove_action('doing_it_wrong_run', $listen, 10);
remove_filter('doing_it_wrong_trigger_error', '__return_false');

$cache->set('x', 1, 'zz_oc_two');
$cache->set('y', 2, 'zz_oc_two');
$cache->set('keep', 3, $g);
$say('groups', [
    $cache->flush_group('zz_oc_two'),
    $cache->get('x', 'zz_oc_two'),
    $cache->get('keep', $g),
    $cache->flush_group('zz_oc_never'),
    (static function () use ($cache) {
        $cache->add_global_groups(['zz_oc_global', 'zz_oc_global']);
        $cache->add_global_groups('zz_oc_global_two');
        $cache->set('k', 'v', 'zz_oc_global');
        $cache->switch_to_blog(1);
        return $cache->get('k', 'zz_oc_global');
    })(),
]);

$hits = $cache->cache_hits;
$misses = $cache->cache_misses;
$cache->get('keep', $g);
$cache->get('zz never set', $g);
$found = null;
$cache->get('zz never set', $g, false, $found);
$say('hits and misses', [$cache->cache_hits - $hits, $cache->cache_misses - $misses]);

$say('magic properties', [isset($cache->cache_hits), isset($cache->zz_nothing), (static function () use ($cache) {
    $cache->zz_dynamic = 'set';
    $out = [isset($cache->zz_dynamic), $cache->zz_dynamic];
    unset($cache->zz_dynamic);
    $out[] = isset($cache->zz_dynamic);
    return $out;
})()]);

ob_start();
$cache->stats();
$stats = (string) ob_get_clean();
preg_match_all('#<li><strong>Group:</strong> (zz_oc\w*) - \( ([^)]*) \)</li>#', $stats, $groups, PREG_SET_ORDER);
$say('stats printed', [(bool) preg_match('#^<p><strong>Cache Hits:</strong> \d+<br /><strong>Cache Misses:</strong> \d+<br /></p><ul>.*</ul>$#s', $stats), array_map(static fn ($m) => [$m[1], $m[2]], $groups)]);

foreach (['zz_oc', 'zz_oc_two', 'zz_oc_global', 'zz_oc_global_two'] as $group) {
    $cache->flush_group($group);
}
$cache->delete('c');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
