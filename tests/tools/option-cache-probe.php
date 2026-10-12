<?php
/**
 * The option cache (probe option-cache): the filters around the autoloaded
 * list (pre_wp_load_alloptions and alloptions) heard read by read, in order
 * with the option's own filters; the list rewritten by the one and answered
 * by the other; what reads leave in wp_cache's options group (alloptions,
 * notoptions, one key per option not autoloaded); the group written by hand;
 * a direct database write seen once its cache key is dropped; and the group
 * kept in step by add, update, delete and the autoload flag. The probe's
 * options are removed and the group's keys dropped at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$names = ['zz_oc_auto', 'zz_oc_off', 'zz_oc_missing', 'zz_oc_new', 'zz_oc_injected', 'zz_oc_gone'];
$clear = static function () use ($names, $wpdb): void {
    foreach ($names as $name) {
        $wpdb->delete($wpdb->options, ['option_name' => $name]);
        wp_cache_delete($name, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
};
register_shutdown_function($clear);
$clear();
$wpdb->insert($wpdb->options, ['option_name' => 'zz_oc_auto', 'option_value' => 'auto value', 'autoload' => 'on']);
$wpdb->insert($wpdb->options, ['option_name' => 'zz_oc_off', 'option_value' => 'off value', 'autoload' => 'off']);

// Every hook heard, in order, with what it was handed.
$heard = [];
add_filter('pre_wp_load_alloptions', static function (...$args) use (&$heard) {
    $heard[] = ['pre_wp_load_alloptions', count($args), is_array($args[0]) ? 'a list' : $args[0], $args[1] ?? 'no second'];
    return $args[0];
}, 10, 5);
add_filter('alloptions', static function (...$args) use (&$heard) {
    $all = $args[0];
    $heard[] = ['alloptions', count($args), is_array($all) ? ['auto' => $all['zz_oc_auto'] ?? 'absent', 'off' => $all['zz_oc_off'] ?? 'absent', 'blogname' => isset($all['blogname'])] : gettype($all)];
    return $all;
}, 10, 5);
foreach (['zz_oc_auto', 'zz_oc_off', 'zz_oc_missing'] as $name) {
    add_filter("pre_option_{$name}", static function ($pre) use (&$heard, $name) {
        $heard[] = ["pre_option_{$name}"];
        return $pre;
    });
    add_filter("option_{$name}", static function ($value) use (&$heard, $name) {
        $heard[] = ["option_{$name}", $value];
        return $value;
    });
    add_filter("default_option_{$name}", static function ($value) use (&$heard, $name) {
        $heard[] = ["default_option_{$name}", $value];
        return $value;
    });
}
$step = static function (callable $run) use (&$heard): array {
    $heard = [];
    $returned = $run();
    if (is_array($returned) && !array_is_list($returned)) {
        $returned = ['a list holding the probe option' => $returned['zz_oc_auto'] ?? 'absent', 'and the one not autoloaded' => $returned['zz_oc_off'] ?? 'absent'];
    }
    return ['returned' => $returned, 'heard' => $heard];
};
$say('the filters heard, read by read', [
    'an autoloaded option' => $step(static fn () => get_option('zz_oc_auto')),
    'the same again' => $step(static fn () => get_option('zz_oc_auto')),
    'one not autoloaded' => $step(static fn () => get_option('zz_oc_off')),
    'the same again' . ' (not autoloaded)' => $step(static fn () => get_option('zz_oc_off')),
    'one not there' => $step(static fn () => get_option('zz_oc_missing', 'fallback')),
    'the same again' . ' (not there)' => $step(static fn () => get_option('zz_oc_missing')),
    'the list' => $step(static fn () => wp_load_alloptions()),
    'the list, forcing the cache' => $step(static fn () => wp_load_alloptions(true)),
    'an empty name' => $step(static fn () => get_option('')),
]);
add_filter('pre_option_zz_oc_auto', $early = static fn () => 'early');
$say('a pre filter answering first', $step(static fn () => get_option('zz_oc_auto')));
remove_filter('pre_option_zz_oc_auto', $early);

// The list rewritten on its way out.
$rewrite = static function ($all) {
    $all['zz_oc_injected'] = 'injected';
    $all['zz_oc_auto'] = 'rewritten';
    $all['zz_oc_off'] = 'put in the list';
    $all['zz_oc_missing'] = serialize(['a' => 1]);
    return $all;
};
add_filter('alloptions', $rewrite);
$say('the alloptions filter rewriting the list', [
    'a key it added' => get_option('zz_oc_injected'),
    'a value it changed' => get_option('zz_oc_auto'),
    'an option not autoloaded' => get_option('zz_oc_off'),
    'a serialized value' => get_option('zz_oc_missing'),
    'the list' => array_intersect_key(wp_load_alloptions(), array_flip($names)),
    'kept in the cache' => array_intersect_key((array) wp_cache_get('alloptions', 'options'), array_flip($names)),
]);
remove_filter('alloptions', $rewrite);

// The list answered before it is read.
$answer = static fn () => ['zz_oc_auto' => 'answered', 'zz_oc_injected' => 'answered too'];
add_filter('pre_wp_load_alloptions', $answer, 20);
$say('pre_wp_load_alloptions answering', [
    'from the answer' => $step(static fn () => get_option('zz_oc_auto')),
    'a key only the answer has' => get_option('zz_oc_injected'),
    'one not autoloaded' => get_option('zz_oc_off'),
    'an autoloaded option the answer left out' => get_option('blogname') === $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'blogname'"),
    'the list' => $step(static fn () => wp_load_alloptions()),
]);
remove_filter('pre_wp_load_alloptions', $answer, 20);
$heard = [];

// What the reads left in the group.
$group = static function () use ($names): array {
    $all = wp_cache_get('alloptions', 'options');
    $not = wp_cache_get('notoptions', 'options');
    $keys = [];
    foreach ($names as $name) {
        $keys[$name] = wp_cache_get($name, 'options', false, $found);
        $keys[$name] = $found ? $keys[$name] : 'not cached';
    }
    return [
        'alloptions' => is_array($all) ? array_intersect_key($all, array_flip($names)) + ['blogname' => isset($all['blogname'])] : $all,
        'notoptions' => is_array($not) ? array_intersect_key($not, array_flip($names)) : $not,
        'keys' => $keys,
    ];
};
$say('what the reads left in the group', $group());

// The group written by hand.
$hand = [];
wp_cache_set('zz_oc_off', 'from the cache', 'options');
$hand['a key set'] = get_option('zz_oc_off');
$all = (array) wp_cache_get('alloptions', 'options');
$all['zz_oc_auto'] = 'from the cache';
wp_cache_set('alloptions', $all, 'options');
$hand['the list set'] = get_option('zz_oc_auto');
$not = (array) wp_cache_get('notoptions', 'options');
$not['zz_oc_off'] = true;
wp_cache_set('notoptions', $not, 'options');
wp_cache_delete('zz_oc_off', 'options');
$hand['marked missing'] = get_option('zz_oc_off', 'fallback');
wp_cache_delete('alloptions', 'options');
wp_cache_delete('notoptions', 'options');
$hand['the list dropped'] = get_option('zz_oc_auto');
$hand['notoptions dropped'] = get_option('zz_oc_off');
$say('the group written by hand', $hand);

// A write behind the cache's back.
$direct = [];
$wpdb->update($wpdb->options, ['option_value' => 'written directly'], ['option_name' => 'zz_oc_auto']);
$direct['autoloaded, before'] = get_option('zz_oc_auto');
wp_cache_delete('alloptions', 'options');
$direct['autoloaded, the list dropped'] = get_option('zz_oc_auto');
$wpdb->update($wpdb->options, ['option_value' => 'written directly'], ['option_name' => 'zz_oc_off']);
$direct['not autoloaded, before'] = get_option('zz_oc_off');
wp_cache_delete('zz_oc_off', 'options');
$direct['not autoloaded, its key dropped'] = get_option('zz_oc_off');
$wpdb->insert($wpdb->options, ['option_name' => 'zz_oc_missing', 'option_value' => 'now here', 'autoload' => 'off']);
$direct['added, before'] = get_option('zz_oc_missing', 'fallback');
wp_cache_delete('notoptions', 'options');
$direct['added, notoptions dropped'] = get_option('zz_oc_missing', 'fallback');
$say('a direct write, then its cache key dropped', $direct);

// The group kept in step by the writes.
$writes = [];
$writes['updated'] = [update_option('zz_oc_auto', 'v2'), $group()];
$writes['updated to a list'] = [update_option('zz_oc_auto', ['x' => 1]), $group()];
$writes['updated to true'] = [update_option('zz_oc_auto', true), $group()];
$writes['not autoloaded, updated'] = [update_option('zz_oc_off', 'n2'), $group()];
$writes['deleted'] = [delete_option('zz_oc_off'), $group()];
$writes['added back, not autoloaded'] = [add_option('zz_oc_off', 'back', '', false), $group()];
$writes['added, autoloaded'] = [add_option('zz_oc_new', 'new', '', true), $group()];
$writes['that one deleted'] = [delete_option('zz_oc_new'), $group()];
$writes['autoload turned on'] = [wp_set_option_autoload('zz_oc_off', true), $group(), get_option('zz_oc_off')];
$writes['autoload turned off'] = [wp_set_option_autoload('zz_oc_auto', false), $group(), get_option('zz_oc_auto')];
$say('the group kept in step by the writes', $writes);

// While installing, reads go past the group.
$installing = [];
wp_installing(true);
$installing['an autoloaded option, heard'] = $step(static fn () => get_option('zz_oc_off'));
wp_cache_set('zz_oc_missing', 'from the cache', 'options');
$installing['a key set by hand'] = get_option('zz_oc_missing');
$all = (array) wp_cache_get('alloptions', 'options');
$all['zz_oc_off'] = 'from the cache';
wp_cache_set('alloptions', $all, 'options');
$installing['the list set by hand'] = get_option('zz_oc_off');
$wpdb->insert($wpdb->options, ['option_name' => 'zz_oc_new', 'option_value' => 'written while installing', 'autoload' => 'off']);
$installing['one known unset, written directly'] = get_option('zz_oc_new', 'fallback');
$installing['one not there'] = $step(static fn () => get_option('zz_oc_gone', 'fallback'));
$installing['the list'] = $step(static fn () => wp_load_alloptions());
$installing['updated'] = [update_option('zz_oc_missing', 'updated while installing'), $group()];
$installing['added'] = [add_option('zz_oc_gone', 'added while installing'), $group()];
$installing['deleted'] = [delete_option('zz_oc_auto'), $group()];
wp_installing(false);
$installing['afterwards'] = [get_option('zz_oc_missing'), get_option('zz_oc_off'), get_option('zz_oc_new', 'fallback'), get_option('zz_oc_gone', 'fallback'), get_option('zz_oc_auto', 'fallback'), $group()];
$say('while installing', $installing);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
