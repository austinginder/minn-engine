<?php
/**
 * WP_Theme_JSON's presets and values (probe theme-json-presets): on a
 * settings tree with presets in several origins (colors, gradients,
 * duotone, font sizes with fluid typography, font families, spacing sizes,
 * shadows, aspect ratios) and custom values, the values and slugs by
 * origin, the preset custom properties, the theme's own custom properties,
 * the preset utility classes, keys pruned to a schema, and property values
 * read from styles (plain, presets, refs).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$reach = static fn (string $method, ...$args) => $try(static fn () => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, WP_Theme_JSON::class)(...$args));
$reachOn = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, WP_Theme_JSON::class)(...$args));

$settings = [
    'color' => [
        'palette' => [
            'default' => [['slug' => 'black', 'color' => '#000000', 'name' => 'Black'], ['slug' => 'vivid-red', 'color' => '#cf2e2e', 'name' => 'Vivid red']],
            'theme' => [['slug' => 'base', 'color' => '#ffffff', 'name' => 'Base'], ['slug' => 'Accent One', 'color' => 'var(--zz)', 'name' => 'Accent']],
            'custom' => [['slug' => 'zz-custom', 'color' => 'rgb(1, 2, 3)', 'name' => 'Zz']],
        ],
        'gradients' => ['theme' => [['slug' => 'zz-fade', 'gradient' => 'linear-gradient(135deg,#fff 0%,#000 100%)', 'name' => 'Fade']]],
        'duotone' => ['default' => [['slug' => 'dark-grayscale', 'colors' => ['#000000', '#7f7f7f'], 'name' => 'Dark grayscale']]],
    ],
    'typography' => [
        'fluid' => true,
        'fontSizes' => [
            'default' => [['slug' => 'small', 'size' => '13px', 'name' => 'Small']],
            'theme' => [['slug' => 'medium', 'size' => '1rem', 'name' => 'Medium', 'fluid' => false], ['slug' => 'x-large', 'size' => '2.5rem', 'name' => 'XL'], ['slug' => 'huge', 'size' => '3rem', 'name' => 'Huge', 'fluid' => ['min' => '2rem', 'max' => '4rem']]],
        ],
        'fontFamilies' => ['theme' => [['slug' => 'manrope', 'fontFamily' => 'Manrope, sans-serif', 'name' => 'Manrope']]],
    ],
    'spacing' => ['spacingSizes' => ['theme' => [['slug' => '30', 'size' => '20px', 'name' => 'Small'], ['slug' => '50', 'size' => 'clamp(30px, 5vw, 50px)', 'name' => 'Regular']]]],
    'shadow' => ['presets' => ['default' => [['slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)', 'name' => 'Natural']]]],
    'dimensions' => ['aspectRatios' => ['default' => [['slug' => 'square', 'ratio' => '1', 'name' => 'Square']]]],
    'custom' => ['lineHeight' => ['body' => 1.7, 'tight' => '1.1'], 'zzSpacing' => ['outer' => 'var:preset|spacing|50']],
];
$byPath = static function (array $path) {
    foreach (WP_Theme_JSON::PRESETS_METADATA as $metadata) {
        if ($metadata['path'] === $path) {
            return $metadata;
        }
    }
    return null;
};
$all = ['default', 'theme', 'custom'];

$say('values by slug', [
    'colors, every origin' => $reach('get_settings_values_by_slug', $settings, $byPath(['color', 'palette']), $all),
    'colors, the theme alone' => $reach('get_settings_values_by_slug', $settings, $byPath(['color', 'palette']), ['theme']),
    'font sizes' => $reach('get_settings_values_by_slug', $settings, $byPath(['typography', 'fontSizes']), $all),
    'font families' => $reach('get_settings_values_by_slug', $settings, $byPath(['typography', 'fontFamilies']), $all),
    'duotone' => $reach('get_settings_values_by_slug', $settings, $byPath(['color', 'duotone']), $all),
    'aspect ratios' => $reach('get_settings_values_by_slug', $settings, $byPath(['dimensions', 'aspectRatios']), $all),
]);
$say('slugs', [
    'colors' => $reach('get_settings_slugs', $settings, $byPath(['color', 'palette'])),
    'colors, the theme alone' => $reach('get_settings_slugs', $settings, $byPath(['color', 'palette']), ['theme']),
    'spacing' => $reach('get_settings_slugs', $settings, $byPath(['spacing', 'spacingSizes'])),
]);
$say('preset custom properties', [
    'every origin' => $reach('compute_preset_vars', $settings, $all),
    'the theme alone' => $reach('compute_preset_vars', $settings, ['theme']),
    'nothing' => $reach('compute_preset_vars', [], $all),
]);
$say('theme custom properties', [$reach('compute_theme_vars', $settings), $reach('compute_theme_vars', []), $reach('compute_theme_vars', ['custom' => []])]);
$say('preset classes', [
    'on the body' => $reach('compute_preset_classes', $settings, 'body', $all),
    'on a block' => $reach('compute_preset_classes', $settings, '.wp-block-zz', ['theme']),
    'nothing' => $reach('compute_preset_classes', [], 'body', $all),
]);

$say('keys pruned to a schema', [
    $reach('remove_keys_not_in_schema', ['a' => 1, 'b' => ['c' => 2, 'd' => 3], 'e' => ['f' => 4]], ['a' => null, 'b' => ['c' => null], 'e' => null]),
    $reach('remove_keys_not_in_schema', ['a' => ['x' => 1]], ['a' => ['y' => null]]),
    $reach('remove_keys_not_in_schema', ['a' => 'string'], ['a' => ['y' => null]]),
    $reach('remove_keys_not_in_schema', ['a' => [1, 2]], ['a' => null]),
    $reach('remove_keys_not_in_schema', [], ['a' => null]),
    $reach('remove_keys_not_in_schema', ['fonts' => [['fontFamily' => 'x', 'zz' => 1], ['name' => 'y'], 'loose']], ['fonts' => [['fontFamily' => null, 'name' => null]]]),
    $reach('remove_keys_not_in_schema', ['a' => ['b' => ['c' => 1, 'd' => 2]]], ['a' => ['b' => ['c' => null]]]),
    $reach('remove_keys_not_in_schema', ['a' => null, 'b' => false, 'c' => 0], ['a' => null, 'b' => null, 'c' => ['x' => null]]),
]);

$styles = ['color' => ['text' => 'var:preset|color|black', 'background' => ['ref' => 'styles.elements.link.color.text']], 'spacing' => ['padding' => ['top' => 'var:preset|spacing|50', 'left' => '1rem']], 'typography' => ['fontSize' => '2rem', 'lineHeight' => '1.5'], 'elements' => ['link' => ['color' => ['text' => '#123456']]], 'border' => ['radius' => ['ref' => 'styles.missing.radius']], 'shadow' => 'var:preset|shadow|natural'];
$tree = ['styles' => $styles];
$say('property values', [
    'a preset' => $reach('get_property_value', $styles, ['color', 'text']),
    'a ref' => $reach('get_property_value', $styles, ['color', 'background'], $tree),
    'a ref with no tree' => $reach('get_property_value', $styles, ['color', 'background']),
    'a missing ref' => $reach('get_property_value', $styles, ['border', 'radius'], $tree),
    'a nested preset' => $reach('get_property_value', $styles, ['spacing', 'padding', 'top']),
    'an array' => $reach('get_property_value', $styles, ['spacing', 'padding']),
    'plain' => $reach('get_property_value', $styles, ['typography', 'fontSize']),
    'missing' => $reach('get_property_value', $styles, ['zz', 'none']),
    'a shadow preset' => $reach('get_property_value', $styles, ['shadow']),
    'a ref to an array' => $reach('get_property_value', ['spacing' => ['padding' => ['ref' => 'styles.spacing.margin']]], ['spacing', 'padding'], ['styles' => ['spacing' => ['margin' => ['top' => '1px']]]]),
    'a ref to a ref' => $reach('get_property_value', ['color' => ['text' => ['ref' => 'styles.a']]], ['color', 'text'], ['styles' => ['a' => ['ref' => 'styles.b'], 'b' => '#abc']]),
    'a ref to an empty string' => $reach('get_property_value', ['color' => ['text' => ['ref' => 'styles.a']]], ['color', 'text'], ['styles' => ['a' => '']]),
    'a ref to a number' => $reach('get_property_value', ['typography' => ['lineHeight' => ['ref' => 'styles.a']]], ['typography', 'lineHeight'], ['styles' => ['a' => 1.5]]),
    'zero' => $reach('get_property_value', ['typography' => ['lineHeight' => 0]], ['typography', 'lineHeight']),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
