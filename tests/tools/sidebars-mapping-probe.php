<?php
/**
 * The widget lists fitted to a new theme's sidebars (probe
 * sidebars-mapping): wp_map_sidebars_widgets() handed an old theme's lists
 * with the new theme's sidebars registered for the call. Same ids, the
 * groups of id fragments, one old and one new, what goes inactive, and the
 * theme's stored inactive widgets that come back when nothing else is
 * inactive (the stored theme mod, and one put in its place by a filter).
 * The registered sidebars are put back; nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
global $wp_registered_sidebars;
$saved = $wp_registered_sidebars;
$run = static function (array $registered, array $existing) {
    global $wp_registered_sidebars;
    $wp_registered_sidebars = [];
    foreach ($registered as $id => $name) {
        $wp_registered_sidebars[$id] = ['id' => $id, 'name' => $name];
    }
    return wp_map_sidebars_widgets($existing);
};
$e = static fn (array $sidebars, array $inactive = []) => ['wp_inactive_widgets' => $inactive] + $sidebars;
$cases = [
    'the same id' => [['sidebar-1' => 'Sidebar'], $e(['sidebar-1' => ['a', 'b']])],
    'one old, one new' => [['primary' => 'P'], $e(['sidebar-1' => ['a']])],
    'one old, one new, no names in common' => [['zone' => 'Z'], $e(['widgets' => ['a']])],
    'one old, one new, the old one empty' => [['zone' => 'Z'], $e(['widgets' => []], ['i'])],
    'one old, one new, inactive kept' => [['primary' => 'P'], $e(['sidebar-1' => ['a']], ['x'])],
    'one old and an orphaned list' => [['zone' => 'Z'], $e(['widgets' => ['a'], 'orphaned_widgets_1' => ['o']])],
    'groups' => [['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'sidebar-2' => ['b']])],
    'header and main' => [['top' => 'T', 'main' => 'M'], $e(['header' => ['a'], 'sidebar-1' => ['b']])],
    'second, then footer' => [['bottom' => 'B', 'left' => 'L'], $e(['second-area' => ['s'], 'footer-1' => ['f']])],
    'a fragment inside a word' => [['remainder' => 'R', 'other' => 'O'], $e(['sidebar-1' => ['a'], 'x' => ['b']])],
    'names do not count' => [['zone-1' => 'Primary Sidebar', 'zone-2' => 'Footer'], $e(['sidebar-1' => ['a'], 'x' => ['b']])],
    'case' => [['Primary' => 'P', 'other' => 'O'], $e(['Sidebar-1' => ['a'], 'x' => ['b']])],
    'old order' => [['primary' => 'P', 'main' => 'M'], $e(['right' => ['r'], 'sidebar' => ['s']])],
    'left and right' => [['left' => 'L', 'right' => 'R'], $e(['sidebar-2' => ['b'], 'sidebar-1' => ['a']])],
    'a new sidebar in two groups' => [['sidebar-2' => 'S2', 'primary' => 'P'], $e(['footer' => ['f'], 'sidebar-1' => ['a']])],
    'the same id, then a group' => [['primary' => 'P', 'main' => 'M'], $e(['primary' => ['p'], 'sidebar-1' => ['a']], ['i'])],
    'more new than old' => [['primary' => 'P', 'main' => 'M', 'right' => 'R'], $e(['sidebar-1' => ['a']], ['i'])],
    'more old than new' => [['primary' => 'P'], $e(['sidebar-1' => ['a'], 'main-2' => ['m'], 'right' => ['r']], ['i'])],
    'three old, two new' => [['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'sidebar-2' => ['b'], 'sidebar-3' => ['c']])],
    'no names in common' => [['zone-1' => 'Z1', 'zone-2' => 'Z2'], $e(['widgets-a' => ['a'], 'widgets-b' => ['b']])],
    'an empty old sidebar' => [['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => [], 'sidebar-2' => ['b']])],
    'inactive kept, the rest after it' => [['primary' => 'P'], $e(['sidebar-1' => ['a'], 'zzz' => ['z']], ['x'])],
    'no inactive list' => [['primary' => 'P', 'footer' => 'F'], ['sidebar-1' => ['a'], 'zz' => ['z']]],
    'an inactive string' => [['primary' => 'P', 'footer' => 'F'], ['wp_inactive_widgets' => 'x', 'sidebar-1' => ['a'], 'zz' => ['z']]],
    'a widget twice' => [['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'zz' => ['a']], ['a'])],
    'an orphaned list' => [['primary' => 'P', 'footer' => 'F'], $e(['orphaned_widgets_1' => ['o'], 'sidebar-1' => ['a']])],
    'the array version' => [['primary' => 'P'], $e(['sidebar-1' => ['a'], 'array_version' => 3])],
    'numbered widget keys' => [['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => [3 => 'a', 7 => 'b'], 'zz' => [5 => 'z']], ['i'])],
    'nothing registered' => [[], $e(['sidebar-1' => ['a']], ['i'])],
    'nothing old' => [['primary' => 'P'], []],
    'only inactive' => [['primary' => 'P'], $e([], ['x'])],
];
$mapped = [];
foreach ($cases as $label => [$registered, $existing]) {
    $mapped[$label] = $run($registered, $existing);
}
$say('mapped', $mapped);
$stored = static fn () => ['time' => 1, 'data' => ['wp_inactive_widgets' => ['block-3', 'zz-fake', 'block-6'], 'primary' => ['block-2']]];
add_filter('theme_mod_sidebars_widgets', $stored);
$say('stored inactive widgets come back', [
    'registered ones, keys kept' => $run(['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'sidebar-2' => ['b']])),
    'one already placed' => $run(['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['block-3'], 'sidebar-2' => ['b']])),
    'something else inactive' => $run(['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'sidebar-2' => ['b'], 'sidebar-3' => ['c']])),
    'one old, one new' => $run(['primary' => 'P'], $e(['sidebar-1' => ['a']])),
]);
remove_filter('theme_mod_sidebars_widgets', $stored);
$none = static fn () => false;
add_filter('theme_mod_sidebars_widgets', $none);
$say('no stored widgets', $run(['primary' => 'P', 'footer' => 'F'], $e(['sidebar-1' => ['a'], 'sidebar-2' => ['b']])));
remove_filter('theme_mod_sidebars_widgets', $none);
$wp_registered_sidebars = $saved;

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
