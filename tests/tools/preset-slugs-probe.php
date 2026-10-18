<?php
/**
 * Preset slugs in kebab case, as the reference cuts them (probe
 * preset-slugs): _wp_to_kebab_case over camel case, digits, ordinals,
 * separators, accented capitals and symbols; the has-* classes the block
 * supports give a dynamic block for such a slug; and the custom properties
 * and utility classes the global stylesheet prints for theme presets whose
 * slugs are not kebab case already (a classic theme's palette1, h1,
 * brandBlue). Same protocol as api-probe.php; nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$cases = ['h1', 'very-large2', 'café', 'xLarge', 'XXLarge', '2xl', 'Zz Space', "it's", "it\u{2019}s", 'ÉCOLE', 'naïveCase', 'a1b2', 'x2Large', 'size_1', '3D', 'v2.5',
    'x-large', 'size.one', 'size one', 'a--b', '-lead', 'trail-', 'fooBAR', 'FOOBar', 'foo2Bar', 'XMLHttpRequest', 'html5Video', '--x--', 'über', 'ÜberCool',
    'snake_case_word', 'Title Case Words', '  spaced  ', '123', '1st Place', '2ndFloor', '21st', 'A_B_C', 'aB1cD2', 'x.y.z', 'rgba(0,0,0,0.5)', '#fff', '50%',
    'calc(100% - 2rem)', 'theme-palette1', 'palette10', 'Primary Color', 'primaryColor', 'ABC', 'aBC', 'AbC', "émoji\u{1F600}case", 'jp日本語', 'ß-thing', 'ÀB',
    'lineHeight', 'fontSize', 'var(--wp--preset--color--x)', 'WP_Theme', 'x×y', 'a·b', "tab\tsep", 'ninety9Problems', 'vw4', '4vw', '0.5em', 'H2O',
    'iPhone12Pro', "don't", "DON'T", "o'clock", '', '-', "a\u{00A0}b", "a\u{3000}b"];
$say('_wp_to_kebab_case', array_map(static fn (string $value) => [$value, _wp_to_kebab_case($value)], $cases));

// A dynamic block's preset classes: the block supports print them from the slug the block names.
$classes = static function (string $markup): string {
    preg_match('/class="([^"]*)"/', do_blocks($markup), $m);
    return $m[1] ?? '';
};
$rows = [];
foreach (['h1', 'xLarge', 'theme-palette1', 'brandBlue', 'accent_2', '2xl', 'ÜberCool', 'x-large'] as $slug) {
    $rows[$slug] = [];
    foreach (['fontSize', 'textColor', 'backgroundColor', 'fontFamily'] as $attr) {
        $rows[$slug][$attr] = $classes('<!-- wp:site-title ' . wp_json_encode([$attr => $slug]) . ' /-->');
    }
}
$say('site-title preset classes', $rows);

// Theme presets whose slugs are not kebab case, added to the theme's own: their custom properties and classes in the global stylesheet.
$presets = static function ($data) {
    $own = $data->get_data()['settings'] ?? [];
    // A list, or keyed by origin as the theme's data holds it here.
    $list = static fn ($presets): array => array_values(array_filter((array) (is_array($presets) && !array_is_list($presets) ? ($presets['theme'] ?? []) : $presets), 'is_array'));
    return $data->update_with(['version' => 3, 'settings' => [
        'typography' => ['fontSizes' => [...$list($own['typography']['fontSizes'] ?? []), ['slug' => 'h1', 'size' => '3rem', 'name' => 'H1'], ['slug' => 'xLarge2', 'size' => '2rem', 'name' => 'X']]],
        'color' => ['palette' => [...$list($own['color']['palette'] ?? []), ['slug' => 'brandBlue', 'color' => '#00f', 'name' => 'Brand'], ['slug' => 'accent_2', 'color' => '#0f0', 'name' => 'A2'], ['slug' => 'theme-palette1', 'color' => '#f00', 'name' => 'P1']]],
    ]]);
};
add_filter('wp_theme_json_data_theme', $presets);
wp_clean_theme_json_cache();
$css = wp_get_global_stylesheet(['variables', 'presets']);
remove_filter('wp_theme_json_data_theme', $presets);
wp_clean_theme_json_cache();
preg_match_all('/--wp--preset--(?:font-size|color)--[^:;)\s]+/', $css, $vars);
preg_match_all('/\.has-[^{\s,]+-(?:font-size|color|background-color|border-color)(?=[{\s,])/', $css, $utility);
$say('global stylesheet preset names', ['custom properties' => array_values(array_unique($vars[0])), 'classes' => array_values(array_unique($utility[0]))]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
