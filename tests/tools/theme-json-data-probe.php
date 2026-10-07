<?php
/**
 * The theme's theme.json as a plugin changes it (probe theme-json-data):
 * wp_theme_json_data_theme heard with what it hands over, a palette colour
 * and a custom property added through update_with, and what the global
 * settings and the global stylesheet's variables then hold. The
 * resolver's cache is emptied first, as a plugin's late filter would find
 * it. Read only. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
add_filter('wp_theme_json_data_theme', static function ($data) use (&$heard) {
    $raw = $data->get_data();
    $heard[] = [get_class($data), isset($raw['settings']['color']['palette']), isset($raw['styles'])];
    return $data->update_with(['version' => 3, 'settings' => ['color' => ['palette' => [['slug' => 'zz-plugin', 'color' => '#123456', 'name' => 'Zz Plugin']]], 'custom' => ['zz' => ['size' => '7px']]]]);
});
if (method_exists('WP_Theme_JSON_Resolver', 'clean_cached_data')) {
    WP_Theme_JSON_Resolver::clean_cached_data();
}
$palette = wp_get_global_settings(['color', 'palette']);
$say('the palette holds the plugin\'s colour', [is_array($palette), str_contains((string) json_encode($palette), '"zz-plugin"')]);
$say('the custom setting', wp_get_global_settings(['custom', 'zz']));
$css = wp_get_global_stylesheet(['variables']);
$say('the variables', [str_contains($css, '--wp--preset--color--zz-plugin: #123456'), str_contains($css, '--wp--custom--zz--size: 7px')]);
$say('heard', $heard === [] ? [] : $heard[0]);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
