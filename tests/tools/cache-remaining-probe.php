<?php
/**
 * The rest of cache.php (probe cache-remaining): counters raised and lowered
 * (missing keys, numeric strings, words, below zero, an offset given as a
 * string), values replaced only when present, several keys deleted at once,
 * one group flushed while another keeps its values, groups kept out of a
 * persistent cache, switching blogs on a single site, closing, and the cache
 * made fresh. Everything lives in the probe's own groups and the run's
 * memory.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$group = 'zz_cache_probe';

wp_cache_set('count', 5, $group);
wp_cache_set('text', '7', $group);
wp_cache_set('word', 'apples', $group);
wp_cache_set('low', 2, $group);
$say('counters', [
    wp_cache_incr('count', 1, $group), wp_cache_incr('count', 10, $group), wp_cache_incr('count', -3, $group), wp_cache_get('count', $group),
    wp_cache_incr('text', 1, $group), wp_cache_get('text', $group), wp_cache_incr('word', 2, $group), wp_cache_get('word', $group),
    wp_cache_decr('low', 1, $group), wp_cache_decr('low', 5, $group), wp_cache_get('low', $group), wp_cache_decr('low', -4, $group),
    wp_cache_incr('missing', 1, $group), wp_cache_decr('missing', 1, $group), wp_cache_incr('count', '2', $group), wp_cache_incr('count', 1), wp_cache_decr('count'),
]);

wp_cache_set('present', 'old', $group);
$say('replaced only when present', [
    wp_cache_replace('present', 'new', $group), wp_cache_get('present', $group), wp_cache_replace('absent', 'value', $group), wp_cache_get('absent', $group, false, $found), $found,
    wp_cache_replace('present', ['a' => 1], $group, 30), wp_cache_get('present', $group), wp_cache_replace('present', null, $group), wp_cache_get('present', $group, false, $found), $found,
]);

wp_cache_set_multiple(['one' => 1, 'two' => 2, 'three' => 3], $group);
$say('several keys deleted at once', [wp_cache_delete_multiple(['one', 'three', 'nine'], $group), wp_cache_get_multiple(['one', 'two', 'three'], $group), wp_cache_delete_multiple([], $group), wp_cache_delete_multiple(['two'], 'zz_other_group')]);

wp_cache_set('kept', 'stays', 'zz_cache_keep');
wp_cache_set('gone', 'goes', $group);
$say('one group flushed', [wp_cache_supports('flush_group'), wp_cache_flush_group($group), wp_cache_get('gone', $group, false, $found), $found, wp_cache_get('kept', 'zz_cache_keep'), wp_cache_flush_group('zz_cache_never_used')]);

wp_cache_add_non_persistent_groups(['zz_cache_local', 'zz_cache_local_two']);
wp_cache_add_non_persistent_groups('zz_cache_single');
wp_cache_set('here', 'local value', 'zz_cache_local');
wp_cache_set('here', 'single value', 'zz_cache_single');
$say('groups kept out of a persistent cache', [wp_cache_get('here', 'zz_cache_local'), wp_cache_get('here', 'zz_cache_single')]);

wp_cache_set('blogged', 'before', $group);
$switched = [wp_cache_switch_to_blog(get_current_blog_id()), wp_cache_get('blogged', $group), wp_cache_switch_to_blog(7), wp_cache_get('blogged', $group)];
wp_cache_switch_to_blog(get_current_blog_id());
$switched[] = wp_cache_get('blogged', $group);
$say('switching blogs on a single site', $switched);

wp_cache_set('survivor', 'before init', $group);
$say('closing and a fresh cache', [wp_cache_close(), wp_cache_get('survivor', $group), wp_cache_init(), wp_cache_get('survivor', $group, false, $found), $found, $GLOBALS['wp_object_cache'] instanceof WP_Object_Cache]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
