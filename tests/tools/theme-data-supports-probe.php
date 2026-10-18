<?php
/**
 * WP_Theme_JSON_Resolver::get_theme_data() with and without the theme's
 * supports (probe theme-data-supports): the settings it answers for the
 * active block theme as it is, then with classic theme supports added
 * (palette, font sizes, gradients, line height, units, spacing, link colour,
 * border, appearance tools, the custom-colour switch), each read with the
 * default options and with with_supports false, as raw data and settings. Supports are removed and the
 * resolver's caches cleaned on the way out; same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$read = static function (): array {
    WP_Theme_JSON_Resolver::clean_cached_data();
    $with = WP_Theme_JSON_Resolver::get_theme_data();
    WP_Theme_JSON_Resolver::clean_cached_data();
    $without = WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false]);
    WP_Theme_JSON_Resolver::clean_cached_data();
    $explicit = WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => true]);
    // The theme's styles come through untouched either way: a digest stands for them.
    $raw = static function (WP_Theme_JSON $data): array {
        $raw = $data->get_raw_data();
        $raw['styles'] = md5((string) json_encode($raw['styles'] ?? null));
        return $raw;
    };
    return ['with supports' => $raw($with), 'without' => $raw($without), 'with_supports true is the default' => $explicit->get_raw_data() === $with->get_raw_data()];
};
$say('the block theme as it is', $read());
$supports = [
    'editor-color-palette' => [[['name' => 'Probe Ink', 'slug' => 'probe-ink', 'color' => '#123456'], ['name' => 'Probe Paper', 'slug' => 'probe-paper', 'color' => '#fedcba']]],
    'editor-font-sizes' => [[['name' => 'Probe Small', 'slug' => 'probe-small', 'size' => 11], ['name' => 'Probe Big', 'slug' => 'probe-big', 'size' => 40]]],
    'editor-gradient-presets' => [[['name' => 'Probe Fade', 'slug' => 'probe-fade', 'gradient' => 'linear-gradient(90deg,#000 0%,#fff 100%)']]],
    'custom-line-height' => [],
    'custom-units' => [['px', 'rem']],
    'custom-spacing' => [],
    'link-color' => [],
    'border' => [],
    'appearance-tools' => [],
    'disable-custom-colors' => [],
];
foreach ($supports as $feature => $args) {
    add_theme_support($feature, ...$args);
}
$say('with classic theme supports added', $read());
foreach (array_keys($supports) as $feature) {
    remove_theme_support($feature);
}
WP_Theme_JSON_Resolver::clean_cached_data();
$say('supports removed again', $read()['with supports']);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
