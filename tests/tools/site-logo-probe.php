<?php
/**
 * The site logo and the custom logo kept in step (probe site-logo): the
 * theme mod read through the site_logo option, setting one writing the
 * other, removing one removing the other (and removing the theme mods
 * where the theme supports custom logos), the hooks that do it, and the
 * style that hides the header text beside a logo. The two options are
 * written and put back as they were, byte for byte.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$mods_option = 'theme_mods_' . get_option('stylesheet');
$raw = static fn (string $name) => $wpdb->get_row($wpdb->prepare("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name), ARRAY_A);
$saved = ['site_logo' => $raw('site_logo'), $mods_option => $raw($mods_option)];
register_shutdown_function(static function () use ($saved, $wpdb): void {
    foreach ($saved as $name => $row) {
        if (!is_array($row)) {
            $wpdb->delete($wpdb->options, ['option_name' => $name]);
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name)) !== null) {
            $wpdb->update($wpdb->options, $row, ['option_name' => $name]);
        } else {
            $wpdb->insert($wpdb->options, ['option_name' => $name] + $row);
        }
    }
    wp_cache_delete('alloptions', 'options');
});
$mod = static fn () => get_option($mods_option)['custom_logo'] ?? 'none';

$say('the hooks', array_map(static fn ($hook) => [$hook[1], has_action($hook[0], $hook[1])], [
    ['theme_mod_custom_logo', '_override_custom_logo_theme_mod'],
    ['pre_set_theme_mod_custom_logo', '_sync_custom_logo_to_site_logo'],
    ['delete_option_site_logo', '_delete_custom_logo_on_remove_site_logo'],
    ['setup_theme', '_delete_site_logo_on_remove_custom_logo_on_setup_theme'],
    ["update_option_{$mods_option}", '_delete_site_logo_on_remove_custom_logo'],
    ["delete_option_{$mods_option}", '_delete_site_logo_on_remove_theme_mods'],
    ['wp_head', '_custom_logo_header_styles'],
]));

$say('the theme mod read through the option', [
    [_override_custom_logo_theme_mod(5), _override_custom_logo_theme_mod(false), _override_custom_logo_theme_mod('')],
    [_sync_custom_logo_to_site_logo(123), get_option('site_logo'), get_theme_mod('custom_logo')],
    [_sync_custom_logo_to_site_logo(0), get_option('site_logo', 'none')],
    [_sync_custom_logo_to_site_logo('77'), get_option('site_logo', 'none'), _override_custom_logo_theme_mod(5), get_theme_mod('custom_logo')],
    [_sync_custom_logo_to_site_logo(''), get_option('site_logo', 'none')],
    [_sync_custom_logo_to_site_logo(false), get_option('site_logo', 'none')],
    (static function () {
        update_option('site_logo', '');
        $out = [get_option('site_logo', 'none'), _override_custom_logo_theme_mod(5), _override_custom_logo_theme_mod(false)];
        update_option('site_logo', '0');
        $out[] = _override_custom_logo_theme_mod(5);
        delete_option('site_logo');
        return $out;
    })(),
]);

$say('setting and removing either one', [
    (static function () use ($mod) {
        set_theme_mod('custom_logo', 88);
        return [get_option('site_logo'), get_theme_mod('custom_logo'), $mod()];
    })(),
    (static function () {
        remove_theme_mod('custom_logo');
        return [get_option('site_logo', 'none'), get_theme_mod('custom_logo')];
    })(),
    (static function () use ($mod) {
        update_option('site_logo', 99);
        set_theme_mod('custom_logo', 99);
        delete_option('site_logo');
        return [get_option('site_logo', 'none'), get_theme_mod('custom_logo'), $mod()];
    })(),
    (static function () {
        update_option('site_logo', 66);
        return [get_theme_mod('custom_logo'), get_option('site_logo')];
    })(),
    (static function () use ($mod) {
        set_theme_mod('custom_logo', 70);
        $out = [_delete_custom_logo_on_remove_site_logo(), $mod(), get_option('site_logo', 'none')];
        $out[] = _delete_custom_logo_on_remove_site_logo();
        return $out;
    })(),
]);

$say('removed with the custom logo', [
    (static function () {
        update_option('site_logo', 55);
        return [_delete_site_logo_on_remove_custom_logo(['custom_logo' => 1], ['x' => 1]), get_option('site_logo', 'none')];
    })(),
    (static function () {
        update_option('site_logo', 56);
        return [
            _delete_site_logo_on_remove_custom_logo(['custom_logo' => 1], ['custom_logo' => 2]), get_option('site_logo', 'none'),
            _delete_site_logo_on_remove_custom_logo([], []), get_option('site_logo', 'none'),
            _delete_site_logo_on_remove_custom_logo(['x' => 1], []), get_option('site_logo', 'none'),
            _delete_site_logo_on_remove_custom_logo(['custom_logo' => 0], []), get_option('site_logo', 'none'),
        ];
    })(),
    [_delete_site_logo_on_remove_theme_mods(), get_option('site_logo', 'none'), current_theme_supports('custom-logo')],
    (static function () {
        add_theme_support('custom-logo');
        return [_delete_site_logo_on_remove_theme_mods(), get_option('site_logo', 'none')];
    })(),
    (static function () use ($mods_option) {
        remove_all_actions("update_option_{$mods_option}");
        remove_all_actions("delete_option_{$mods_option}");
        return [_delete_site_logo_on_remove_custom_logo_on_setup_theme(), has_action("update_option_{$mods_option}", '_delete_site_logo_on_remove_custom_logo'), has_action("delete_option_{$mods_option}", '_delete_site_logo_on_remove_theme_mods')];
    })(),
]);

$styles = static function (): string {
    ob_start();
    _custom_logo_header_styles();
    return (string) ob_get_clean();
};
remove_theme_support('custom-logo');
$say('the header text hidden beside a logo', [
    $styles(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['header-text' => ['site-title', 'site-description']]);
        return $styles();
    })(),
    (static function () use ($styles) {
        add_filter('theme_mod_header_text', '__return_false');
        return $styles();
    })(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['header-text' => ['zz-a', 'zz b', 'zz"c']]);
        return $styles();
    })(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['header-text' => 'not-array']);
        return $styles();
    })(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['header-text' => []]);
        return $styles();
    })(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['width' => 100]);
        return $styles();
    })(),
    (static function () use ($styles) {
        add_theme_support('custom-logo', ['header-text' => ['site-title']]);
        remove_theme_support('html5');
        return [current_theme_supports('html5', 'style'), $styles()];
    })(),
    (static function () use ($styles) {
        remove_filter('theme_mod_header_text', '__return_false');
        add_filter('theme_mod_header_text', '__return_zero');
        return $styles();
    })(),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
