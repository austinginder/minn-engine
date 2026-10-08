<?php
/**
 * The custom header and custom background as classic themes read them
 * (probe custom-header): with no theme support, then with the supports a
 * theme declares (default image and text colour, header text, video;
 * default background colour and image), and with the theme mods a site
 * sets (no header text, header removed, a chosen image with its data,
 * random defaults, a header video, background colour, image and layout).
 * Recorded: what each function answers or prints. The active theme's mods
 * and supports are put back at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$option = 'theme_mods_' . get_option('stylesheet');
$savedMods = get_option($option, null);
global $wpdb;
$savedAutoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option));
$savedSupport = ['custom-header' => get_theme_support('custom-header'), 'custom-background' => get_theme_support('custom-background')];
$savedHeaders = $GLOBALS['_wp_default_headers'] ?? null;
// The row is put back as it was, its autoload included (the theme-switch probe reads it).
register_shutdown_function(static function () use ($option, $savedMods, $savedAutoload): void {
    global $wpdb;
    if ($savedMods === null) {
        delete_option($option);
        return;
    }
    update_option($option, $savedMods);
    $wpdb->update($wpdb->options, ['autoload' => $savedAutoload], ['option_name' => $option]);
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete($option, 'options');
});
$printed = static function (callable $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$theme = static fn (string $value): string => str_replace([get_template_directory_uri(), get_stylesheet_directory_uri(), home_url()], ['{template}', '{stylesheet}', '{home}'], $value);
$read = static function (string $label) use ($say, $printed, $theme): void {
    $header = get_custom_header();
    $say($label, [
        'get_header_image' => is_string(get_header_image()) ? $theme(get_header_image()) : get_header_image(),
        'header_image' => $theme($printed('header_image')),
        'has_header_image' => has_header_image(),
        'get_header_textcolor' => get_header_textcolor(),
        'header_textcolor' => $printed('header_textcolor'),
        'display_header_text' => display_header_text(),
        'get_custom_header' => array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, (array) $header),
        'get_header_image_tag' => $theme(get_header_image_tag(['class' => 'zz-header'])),
        'has_custom_header' => has_custom_header(),
        'has_header_video' => has_header_video(),
        'get_header_video_url' => get_header_video_url(),
        'is_header_video_active' => is_header_video_active(),
        'is_random_header_image any' => is_random_header_image(),
        'get_uploaded_header_images' => get_uploaded_header_images(),
        'get_background_image' => $theme(get_background_image()),
        'background_image' => $theme($printed('background_image')),
        'get_background_color' => get_background_color(),
        'background_color' => $printed('background_color'),
    ]);
};

remove_theme_support('custom-header');
remove_theme_support('custom-background');
delete_option($option);
unset($GLOBALS['_wp_default_headers']);
$read('no support, no mods');
$say('no support: markup', $theme(get_custom_header_markup()));
$say('no support: background callback prints', $printed('_custom_background_cb'));

add_theme_support('custom-header', ['default-image' => '%s/images/header.jpg', 'width' => 1200, 'height' => 280, 'flex-height' => true, 'default-text-color' => '333333', 'header-text' => true, 'uploads' => true, 'video' => true]);
add_theme_support('custom-background', ['default-color' => 'f0f0f0', 'default-image' => '%s/images/background.jpg']);
$say('the supports as stored', ['custom-header' => array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, (array) (get_theme_support('custom-header')[0] ?? [])), 'custom-background' => array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, (array) (get_theme_support('custom-background')[0] ?? []))]);
$read('supports, no mods');
$say('supports: markup', $theme(get_custom_header_markup()));
$say('supports: the_custom_header_markup prints', $theme($printed('the_custom_header_markup')));
$say('supports: background callback prints', $theme($printed('_custom_background_cb')));
$say('supports: header video settings', array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, (array) get_header_video_settings()));

set_theme_mod('header_textcolor', 'blank');
$read('header text hidden');
set_theme_mod('header_textcolor', 'ff0000');
set_theme_mod('header_image', 'remove-header');
$read('header removed, text red');
set_theme_mod('header_image', 'http://example.com/chosen-header.jpg');
set_theme_mod('header_image_data', (object) ['attachment_id' => 0, 'url' => 'http://example.com/chosen-header.jpg', 'thumbnail_url' => 'http://example.com/chosen-header-thumb.jpg', 'height' => 300, 'width' => 1500]);
$read('a chosen header image');
$say('a chosen header image: tag with attributes', $theme(get_header_image_tag(['alt' => 'Header <alt>', 'width' => 600, 'loading' => 'lazy', 'sizes' => '100vw'])));
$say('a chosen header image: the_header_image_tag prints', $theme($printed(static fn () => the_header_image_tag(['class' => 'printed']))));
$say('a chosen header image: markup', $theme(get_custom_header_markup()));

register_default_headers(['zz-one' => ['url' => '%s/images/one.jpg', 'thumbnail_url' => '%s/images/one-thumb.jpg', 'description' => 'One']]);
$say('registered default headers', array_map(static fn ($h) => array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, $h), $GLOBALS['_wp_default_headers'] ?? []));
set_theme_mod('header_image', 'random-default-image');
$read('random default image, one registered');
$say('random default image: is_random_header_image', [is_random_header_image('any'), is_random_header_image('default'), is_random_header_image('uploaded')]);
unregister_default_headers(['zz-one']);
$say('after unregistering', $GLOBALS['_wp_default_headers'] ?? null);
remove_theme_mod('header_image');
remove_theme_mod('header_image_data');

set_theme_mod('external_header_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
$read('an external header video');
$say('an external header video: settings', array_map(static fn ($v) => is_string($v) ? $theme($v) : $v, (array) get_header_video_settings()));
$say('an external header video: markup', $theme(get_custom_header_markup()));
remove_theme_mod('external_header_video');

set_theme_mod('background_color', 'abcdef');
$say('background colour set: callback prints', $theme($printed('_custom_background_cb')));
set_theme_mod('background_image', 'http://example.com/bg "image".jpg');
set_theme_mod('background_repeat', 'no-repeat');
set_theme_mod('background_position_x', 'center');
set_theme_mod('background_position_y', 'bottom');
set_theme_mod('background_size', 'cover');
set_theme_mod('background_attachment', 'fixed');
$read('background image and layout set');
$say('background image and layout: callback prints', $theme($printed('_custom_background_cb')));
set_theme_mod('background_color', 'f0f0f0');
remove_theme_mod('background_image');
$say('background back to its defaults: callback prints', $theme($printed('_custom_background_cb')));

remove_theme_support('custom-header');
remove_theme_support('custom-background');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement];
}, 10, 2);
add_filter('deprecated_function_trigger_error', '__return_false');
add_custom_image_header('', '', '');
add_custom_background();
$say('the old functions', ['deprecated' => $deprecated, 'custom-header' => current_theme_supports('custom-header'), 'custom-background' => current_theme_supports('custom-background')]);

remove_theme_support('custom-header');
remove_theme_support('custom-background');
foreach ($savedSupport as $feature => $args) {
    if ($args !== false) {
        add_theme_support($feature, ...(array) $args);
    }
}
$GLOBALS['_wp_default_headers'] = $savedHeaders;
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
