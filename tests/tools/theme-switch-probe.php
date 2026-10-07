<?php
/**
 * What switching the theme does, as the reference's switch_theme does it
 * (probe theme-switch): the active theme switched to another installed
 * one, then the next load's step (check_theme_switched), then back again;
 * heard are switch_theme and after_switch_theme with what they were
 * handed, and the options each step changed (the theme's own, the
 * switch's bookkeeping, the theme mods, the widgets, the rewrite rules)
 * as before and after; and how menu locations carry across a switch
 * (wp_map_nav_menu_locations) for a matrix of location names. Every option
 * the probe can touch is put back as it was at the end, whatever happens. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$original = (string) get_option('stylesheet');
$other = $original === 'twentytwentyfour' ? 'twentytwentythree' : 'twentytwentyfour';
$names = ['stylesheet', 'template', 'current_theme', 'theme_switched', 'theme_switched_via_customizer', 'theme_switch_menu_locations', 'template_root', 'stylesheet_root', "theme_mods_{$original}", "theme_mods_{$other}", "mods_{$original}", 'sidebars_widgets', 'rewrite_rules', 'recently_activated'];
$read = static function () use ($names): array {
    global $wpdb;
    $out = [];
    foreach ($names as $name) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
        $out[$name] = $row === null ? null : [$row['option_value'], $row['autoload']];
    }
    return $out;
};
// The stored rules are whatever the last flush left (another suite's plugin, another
// state); regenerate them first, so the switch is measured from this site's own.
flush_rewrite_rules(false);
$saved = $read();
register_shutdown_function(static function () use ($saved): void {
    global $wpdb;
    foreach ($saved as $name => $row) {
        if ($row === null) {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
        } else {
            $wpdb->replace($wpdb->options, ['option_name' => $name, 'option_value' => $row[0], 'autoload' => $row[1]]);
        }
        wp_cache_delete($name, 'options');
    }
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
});
/** What changed between two readings: name => [before, after]; a theme's mods by the keys that changed, times and rule lists summarised. */
$changed = static function (array $from, array $to): array {
    $value = static function (?array $row, string $name) {
        if ($row === null) {
            return '(none)';
        }
        if ($name === 'rewrite_rules') {
            return 'rules: ' . count((array) maybe_unserialize($row[0]));
        }
        $value = maybe_unserialize($row[0]);
        if (is_array($value) && isset($value['sidebars_widgets']['time'])) {
            $value['sidebars_widgets']['time'] = '{time}';
        }
        return $value;
    };
    $out = [];
    foreach ($to as $name => $row) {
        if ($row === $from[$name]) {
            continue;
        }
        [$before, $after] = [$value($from[$name], $name), $value($row, $name)];
        if (str_starts_with($name, 'theme_mods_') && is_array($before) && is_array($after)) {
            $keys = [];
            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
                if (($before[$key] ?? '(none)') !== ($after[$key] ?? '(none)')) {
                    $keys[$key] = [$before[$key] ?? '(none)', $after[$key] ?? '(none)'];
                }
            }
            $out[$name] = ['keys' => $keys, 'autoload' => [$from[$name][1], $row[1]]];
            continue;
        }
        $out[$name] = [$before, $from[$name][1] ?? null, $after, $row[1] ?? null];
    }
    return $out;
};
// The leaving theme starts with no menu locations of its own, whatever earlier runs left in its mods.
$mods = get_option("theme_mods_{$original}");
if (is_array($mods) && array_key_exists('nav_menu_locations', $mods)) {
    unset($mods['nav_menu_locations']);
    update_option("theme_mods_{$original}", $mods);
}
$heard = [];
add_action('switch_theme', static function ($name, $new, $old) use (&$heard): void {
    $heard[] = ['switch_theme', $name, $new->get_stylesheet(), $old->get_stylesheet(), get_option('stylesheet'), get_option('theme_switched')];
}, 10, 3);
add_action('after_switch_theme', static function ($name, $old) use (&$heard): void {
    $heard[] = ['after_switch_theme', $name, $old->get_stylesheet(), get_option('stylesheet'), get_option('theme_switched')];
}, 10, 2);

$at = $start = $read();
switch_theme($other);
$say('switching: heard', $heard);
$say('switching: changed', $changed($at, $at = $read()));
$say('the active theme', [get_stylesheet(), get_template(), wp_get_theme()->get('Name')]);
$heard = [];
check_theme_switched();
$say('the next load: heard', $heard);
$say('the next load: changed', $changed($at, $at = $read()));
$heard = [];
check_theme_switched();
$say('a load after that: heard', [$heard, $changed($at, $at = $read())]);
$heard = [];
switch_theme($original);
check_theme_switched();
$say('switching back: heard', $heard);
$say('switching back: changed from the start', $changed($start, $read()));

// How menu locations carry across a switch: the arriving theme's locations (registered), the leaving theme's (with their menus), any already set.
$cases = [
    'same names' => [['primary' => 'P', 'footer' => 'F'], ['primary' => 5, 'footer' => 6], []],
    'one each' => [['main-nav' => 'M'], ['top-bar' => 7], []],
    'one each, already set' => [['zz-a' => 'A'], ['zz-b' => 7], ['zz-a' => 3]],
    'one new, two old' => [['main-nav' => 'M'], ['top-bar' => 7, 'other' => 8], []],
    'one new, two old, plain names' => [['zz-a' => 'A'], ['zz-b' => 7, 'zz-c' => 8], []],
    'two each, plain names' => [['zz-a' => 'A', 'zz-d' => 'D'], ['zz-b' => 7, 'zz-c' => 8], []],
    'by role' => [['menu-1' => 'A', 'menu-2' => 'B', 'social' => 'S'], ['primary' => 5, 'footer' => 6, 'social' => 9], []],
    'by part of the name' => [['header-menu' => 'H', 'footer-links' => 'F'], ['main' => 5, 'bottom' => 6], []],
    'two old of one role' => [['menu-1' => 'A'], ['header' => 5, 'primary' => 6], []],
    'two new of one role' => [['header' => 'H', 'primary' => 'P'], ['main' => 5], []],
    'role order' => [['top' => 'T', 'bottom' => 'B'], ['footer' => 6, 'navigation' => 5], []],
    'already set, kept and overwritten' => [['primary' => 'P', 'footer' => 'F'], ['primary' => 5, 'secondary' => 6], ['footer' => 3]],
    'already set, no match' => [['primary' => 'P', 'zz' => 'Z'], ['primary' => 5], ['zz' => 3]],
    'same name, then by role' => [['primary' => 'P', 'zz' => 'Z'], ['primary' => 5, 'header' => 7], []],
    'a role location with no menu' => [['menu-1' => 'M', 'zz' => 'Z'], ['header' => 0, 'main' => 4], []],
    'one location, two role names' => [['main-header' => 'M', 'zz' => 'Z'], ['top' => 5, 'navigation' => 6], []],
    'the name inside the role name' => [['nav' => 'N', 'zz' => 'Z'], ['navigation' => 4, 'qq' => 1], []],
    'letter case' => [['Primary' => 'P', 'zz' => 'Z'], ['PRIMARY' => 5, 'qq' => 1], []],
    'nothing old' => [['primary' => 'P'], [], []],
    'old not a list' => [['primary' => 'P'], false, ['primary' => 2]],
];
$kept = get_registered_nav_menus();
$mapped = [];
foreach ($cases as $label => [$registered, $old, $new]) {
    foreach (array_keys(get_registered_nav_menus()) as $location) {
        unregister_nav_menu($location);
    }
    register_nav_menus($registered);
    $mapped[$label] = wp_map_nav_menu_locations($new, $old);
}
foreach (array_keys(get_registered_nav_menus()) as $location) {
    unregister_nav_menu($location);
}
register_nav_menus($kept);
$say('menu locations carried across', $mapped);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
