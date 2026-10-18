<?php
/**
 * Core's preset lists beside a theme's own (probe default-presets): the
 * font-size and colour custom properties and utility classes the global
 * stylesheet prints when the theme's own sizes and palette no longer carry
 * core's slugs, or carry one of them, with defaultFontSizes and
 * defaultPalette false and true. The theme's data is changed through
 * wp_theme_json_data_theme only; the filter is removed and the theme.json
 * caches cleaned after each case. Same protocol as api-probe.php; nothing
 * is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$sheet = static function (): array {
    $css = wp_get_global_stylesheet(['variables', 'presets']);
    preg_match_all('/(--wp--preset--font-size--[a-z0-9-]+):\s*([^;}]+)/', $css, $sizes, PREG_SET_ORDER);
    preg_match_all('/\.has-([a-z0-9-]+)-font-size\{font-size:\s*([^}]+)\}/', $css, $sizeClasses, PREG_SET_ORDER);
    preg_match_all('/--wp--preset--color--([a-z0-9-]+):/', $css, $colors);
    preg_match_all('/\.has-([a-z0-9-]+)-color\{/', $css, $colorClasses);
    return [
        'font-size properties' => array_map(static fn (array $m) => [$m[1], trim($m[2])], $sizes),
        'font-size classes' => array_map(static fn (array $m) => [$m[1], trim($m[2])], $sizeClasses),
        'colour properties' => array_values(array_unique($colors[1])),
        'colour classes' => array_values(array_unique($colorClasses[1])),
    ];
};
$say('the theme as it is', $sheet());
$cases = [
    'own sizes and palette only, defaults off' => [false, [['slug' => 'probe-tiny', 'size' => '0.7rem', 'name' => 'Tiny'], ['slug' => 'probe-huge', 'size' => '3rem', 'name' => 'Huge']], [['slug' => 'probe-ink', 'color' => '#123456', 'name' => 'Ink']]],
    'own sizes and palette only, defaults on' => [true, [['slug' => 'probe-tiny', 'size' => '0.7rem', 'name' => 'Tiny'], ['slug' => 'probe-huge', 'size' => '3rem', 'name' => 'Huge']], [['slug' => 'probe-ink', 'color' => '#123456', 'name' => 'Ink']]],
    'one of core\'s slugs kept, defaults off' => [false, [['slug' => 'small', 'size' => '0.8rem', 'name' => 'Small'], ['slug' => 'probe-huge', 'size' => '3rem', 'name' => 'Huge']], [['slug' => 'black', 'color' => '#111111', 'name' => 'Black'], ['slug' => 'probe-ink', 'color' => '#123456', 'name' => 'Ink']]],
    'one of core\'s slugs kept, defaults on' => [true, [['slug' => 'small', 'size' => '0.8rem', 'name' => 'Small'], ['slug' => 'probe-huge', 'size' => '3rem', 'name' => 'Huge']], [['slug' => 'black', 'color' => '#111111', 'name' => 'Black'], ['slug' => 'probe-ink', 'color' => '#123456', 'name' => 'Ink']]],
];
foreach ($cases as $label => [$defaults, $sizes, $palette]) {
    $filter = static fn ($data) => $data->update_with(['version' => 3, 'settings' => [
        'typography' => ['defaultFontSizes' => $defaults, 'fontSizes' => $sizes],
        'color' => ['defaultPalette' => $defaults, 'palette' => $palette],
    ]]);
    add_filter('wp_theme_json_data_theme', $filter);
    wp_clean_theme_json_cache();
    $say($label, $sheet());
    remove_filter('wp_theme_json_data_theme', $filter);
    wp_clean_theme_json_cache();
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
