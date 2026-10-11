<?php
/**
 * A theme.json made into a WP_Theme_JSON (probe theme-json-construct): the
 * blocks metadata it validates against (each registered block's selector,
 * selectors, element selectors, duotone, style variations and states, as a
 * hash per block and in full for a few), the style variations each block
 * allows, a block's selectors; then theme.json data built in each origin
 * and read back raw (unknown keys, blocks and elements removed, presets
 * kept under their origin, versions migrated, appearance tools opted in,
 * shared variations spread to the blocks that have them), as data (presets
 * flattened, keys sorted), and merged in origin order.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
// Core and the theme register blocks and block styles on init, which the engine's probe runner does not fire.
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$reach = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, WP_Theme_JSON::class)(...$args));

$meta = $reach('get_blocks_metadata');
$say('blocks metadata', [
    'blocks' => is_array($meta) ? array_keys($meta) : $meta,
    'each, hashed' => is_array($meta) ? array_map(static fn ($m) => md5(json_encode($m)), $meta) : $meta,
    'a few in full' => is_array($meta) ? array_intersect_key($meta, array_flip(['core/button', 'core/image', 'core/paragraph', 'core/navigation-link', 'core/calendar', 'core/tag-cloud', 'core/list'])) : $meta,
]);
$say('the style variations each block allows', [$reach('get_valid_block_style_variations', $meta), $reach('get_valid_block_style_variations')]);
$registry = WP_Block_Type_Registry::get_instance();
$say('a block\'s selectors', [
    'its own' => $reach('get_block_selectors', $registry->get_registered('core/button'), '.wp-block-button .wp-block-button__link'),
    'none of its own' => $reach('get_block_selectors', $registry->get_registered('core/group'), '.wp-block-group'),
    'feature selectors from supports' => $reach('get_block_selectors', $registry->get_registered('core/calendar'), '.wp-block-calendar'),
    'another root' => $reach('get_block_selectors', $registry->get_registered('core/group'), '.zz-root'),
]);

$cases = [
    'unknown keys' => [['version' => 3, 'zz' => 1, 'settings' => ['zz' => 1, 'color' => ['zz' => 1, 'custom' => false]], 'styles' => ['zz' => 1, 'color' => ['zz' => 1, 'text' => 'red']]], 'theme'],
    'presets, root and block' => [['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'a', 'color' => '#a00', 'name' => 'A']]], 'blocks' => ['core/paragraph' => ['color' => ['palette' => [['slug' => 'p', 'color' => '#0a0', 'name' => 'P']]]], 'zz/none' => ['color' => ['custom' => false]]]]], 'theme'],
    'blocks and elements' => [['version' => 3, 'styles' => ['blocks' => ['core/group' => ['color' => ['text' => 'red'], 'elements' => ['link' => ['color' => ['text' => 'blue'], ':hover' => ['color' => ['text' => 'green']]]], 'zzprop' => 1], 'zz/none' => ['color' => ['text' => 'x']]], 'elements' => ['link' => [':hover' => ['color' => ['text' => 'y']], ':zz' => ['color' => ['text' => 'z']]], 'heading' => [':hover' => ['color' => ['text' => 'h']]], 'zz' => ['color' => ['text' => 'q']]]]], 'theme'],
    'block pseudo selectors and states' => [['version' => 3, 'styles' => ['blocks' => ['core/button' => [':hover' => ['color' => ['text' => 'bh']], ':zz' => ['color' => ['text' => 'bz']]], 'core/navigation-link' => ['-current' => ['color' => ['text' => 'cur']], '-zz' => ['color' => ['text' => 'nz']]]]]], 'theme'],
    'variations' => [['version' => 3, 'styles' => ['blocks' => ['core/button' => ['variations' => ['outline' => ['color' => ['text' => 'o'], 'elements' => ['link' => ['color' => ['text' => 'ol']]], 'blocks' => ['core/paragraph' => ['color' => ['text' => 'op']]]], 'zz-unregistered' => ['color' => ['text' => 'u']]]]], 'variations' => ['zz-shared' => ['color' => ['text' => 's']]]]], 'theme'],
    'variations, nested and unknown keys' => [['version' => 3, 'styles' => ['blocks' => ['core/button' => ['variations' => ['outline' => ['color' => ['text' => 'o', 'zz' => 1], 'zzv' => 1, 'css' => 'x{}', 'elements' => ['link' => ['color' => ['text' => 'ol'], 'zze' => 1, ':hover' => ['color' => ['text' => 'oh']]], 'zzel' => ['color' => ['text' => 'q']]], 'blocks' => ['core/paragraph' => ['color' => ['text' => 'op'], 'zzb' => 1, 'elements' => ['link' => ['color' => ['text' => 'opl']]]], 'zz/none' => ['color' => ['text' => 'n']]]]]]]]], 'theme'],
    'shared variations' => [['version' => 3, 'styles' => ['variations' => ['outline' => ['color' => ['text' => 'shared']]], 'blocks' => ['core/button' => ['color' => ['text' => 'btn'], 'variations' => ['outline' => ['color' => ['background' => 'own']]]]]]], 'theme'],
    'top-level keys' => [['version' => 3, 'title' => 'T', 'slug' => 's', 'description' => 'D', 'blockTypes' => ['core/group'], 'patterns' => ['p'], 'customTemplates' => [['name' => 'c']], 'templateParts' => [['name' => 't']], 'isGlobalStylesUserThemeJSON' => true, '$schema' => 'x'], 'theme'],
    'font families' => [['version' => 3, 'settings' => ['typography' => ['fontFamilies' => [['slug' => 'f', 'fontFamily' => 'F', 'name' => 'F', 'zz' => 1, 'fontFace' => [['fontFamily' => 'F', 'src' => ['x'], 'zz' => 2]]]]]]], 'theme'],
    'version 2' => [['version' => 2, 'settings' => ['typography' => ['fontSizes' => [['slug' => 's', 'size' => '1px', 'name' => 'S']]], 'spacing' => ['spacingSizes' => [['slug' => '1', 'size' => '1px', 'name' => 'One']]]]], 'theme'],
    'version 1' => [['version' => 1, 'settings' => ['color' => ['custom' => false]], 'styles' => ['color' => ['text' => 'red']]], 'theme'],
    'appearance tools' => [['version' => 3, 'settings' => ['appearanceTools' => true, 'blocks' => ['core/group' => ['appearanceTools' => true]]]], 'theme'],
    'custom css' => [['version' => 3, 'styles' => ['css' => 'p{x:1}', 'blocks' => ['core/paragraph' => ['css' => '& a{y:2}']]]], 'theme'],
    'default origin' => [['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'd', 'color' => '#ddd', 'name' => 'D']]]]], 'default'],
    'custom origin' => [['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'c', 'color' => '#ccc', 'name' => 'C']]]]], 'custom'],
    'an origin that is not one' => [['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'z', 'color' => '#zzz', 'name' => 'Z']]]]], 'zz'],
    'no version' => [['settings' => ['color' => ['custom' => false]]], 'theme'],
    'presets that are not lists' => [['version' => 3, 'settings' => ['color' => ['palette' => 'zz', 'gradients' => []]]], 'theme'],
    'shadow and spacing' => [['version' => 3, 'settings' => ['shadow' => ['presets' => [['slug' => 's', 'shadow' => 'x', 'name' => 'S']]], 'spacing' => ['spacingScale' => ['steps' => 3], 'spacingSizes' => [['slug' => '1', 'size' => '1px', 'name' => 'One']]]]], 'theme'],
    'layout and background' => [['version' => 3, 'settings' => ['layout' => ['contentSize' => '600px', 'wideSize' => '1200px', 'zz' => 1]], 'styles' => ['background' => ['backgroundImage' => ['url' => 'x.png'], 'zz' => 1], 'dimensions' => ['minHeight' => '10px', 'zz' => 1], 'spacing' => ['blockGap' => '1rem', 'padding' => ['top' => '1px', 'zz' => 2]]]], 'theme'],
];
// Every appearance tool spelled out reads back as the switch (those turned off stay as they are).
$tools = [];
foreach (WP_Theme_JSON::APPEARANCE_TOOLS_OPT_INS as $tool) {
    _wp_array_set($tools, $tool, true);
}
$cases['appearance tools spelled out'] = [['version' => 3, 'settings' => $tools], 'theme'];
$cases['appearance tools spelled out, one off'] = [['version' => 3, 'settings' => array_replace_recursive($tools, ['border' => ['color' => false]])], 'theme'];
$cases['appearance tools spelled out in a block'] = [['version' => 3, 'settings' => ['blocks' => ['core/group' => $tools]]], 'theme'];
$cases['appearance tools all off'] = [['version' => 3, 'settings' => array_map(static fn ($group) => array_map(static fn () => false, $group), $tools)], 'theme'];
$cases['some appearance tools'] = [['version' => 3, 'settings' => ['border' => ['color' => true, 'radius' => true, 'style' => true, 'width' => true], 'color' => ['link' => true]]], 'theme'];
$built = [];
$raw = [];
$data = [];
foreach ($cases as $label => [$json, $origin]) {
    $built[$label] = $try(static fn () => new WP_Theme_JSON($json, $origin));
    $raw[$label] = $built[$label] instanceof WP_Theme_JSON ? $try(static fn () => $built[$label]->get_raw_data()) : $built[$label];
    // Presets that are not lists cannot be flattened (the reference warns), so that one is read raw only.
    if ($label !== 'presets that are not lists') {
        $data[$label] = $built[$label] instanceof WP_Theme_JSON ? $try(static fn () => $built[$label]->get_data()) : $built[$label];
    }
}
$say('built and read back raw', $raw);
$say('read back as data', $data);

$merge = $try(static function () {
    $default = new WP_Theme_JSON(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'd', 'color' => '#d', 'name' => 'D']], 'custom' => true]]], 'default');
    $theme = new WP_Theme_JSON(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'b', 'color' => '#b', 'name' => 'B'], ['slug' => 'a', 'color' => '#a', 'name' => 'A']], 'custom' => false], 'blocks' => ['core/paragraph' => ['color' => ['palette' => [['slug' => 'p', 'color' => '#p', 'name' => 'P']]]]]], 'styles' => ['color' => ['text' => 'red'], 'blocks' => ['core/group' => ['color' => ['text' => 'g']]]], 'title' => 'T'], 'theme');
    $custom = new WP_Theme_JSON(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'a', 'color' => '#ccc', 'name' => 'A custom'], ['slug' => 'z', 'color' => '#z', 'name' => 'Z']]]], 'styles' => ['color' => ['background' => 'blue']]], 'custom');
    $again = new WP_Theme_JSON(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'x', 'color' => '#x', 'name' => 'X']], 'link' => true]]], 'theme');
    $merged = new WP_Theme_JSON(['version' => 3], 'default');
    $merged->merge($default);
    $merged->merge($theme);
    $merged->merge($custom);
    $out = ['raw' => $merged->get_raw_data(), 'data' => $merged->get_data(), 'settings' => $merged->get_settings()];
    $merged->merge($again);
    $out['the theme again'] = $merged->get_raw_data();
    return $out;
});
$say('merged in origin order', $merge);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
