<?php
/**
 * The rest of theme.php (probe theme-remaining): theme mods read and removed,
 * the custom header and background tags and callbacks (the header image,
 * text colour, random default headers, video address, header markup, and
 * the background style), the theme feature registry and default supports,
 * theme roots and directories, stylesheet addresses, child theme and
 * current theme checks, a deleted attachment leaving the theme mods, and the
 * custom CSS post. The theme's mods are put back and the probe's posts are
 * removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$stylesheet = get_stylesheet();
$modsOption = "theme_mods_{$stylesheet}";
$mods = get_option($modsOption, null);
// remove_theme_mods() deletes the option, so its autoload is put back too, not only its value.
$autoload = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $modsOption));
$made = [];
register_shutdown_function(static function () use (&$made, $modsOption, $mods, $autoload): void {
    $mods === null ? delete_option($modsOption) : update_option($modsOption, $mods);
    if ($mods !== null && is_string($autoload)) {
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->options, ['autoload' => $autoload], ['option_name' => $modsOption]);
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete($modsOption, 'options');
    }
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = str_replace([WP_CONTENT_DIR, rtrim(ABSPATH, '/')], ['{content}', '{abspath}'], $value);
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$attachment = static function (string $name) use (&$made, &$ids): int {
    $made[] = $id = (int) wp_insert_attachment(['post_title' => "zz th {$name}", 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], "2026/10/zz-th-{$name}.jpg", 0);
    wp_update_attachment_metadata($id, ['width' => 1200, 'height' => 280, 'file' => "2026/10/zz-th-{$name}.jpg", 'sizes' => []]);
    $ids[$id] = $name;
    return $id;
};
$custom = static fn ($all) => is_array($all) ? array_filter($all, static fn ($key) => str_starts_with((string) $key, 'zz_') || in_array($key, ['header_textcolor', 'header_image', 'header_image_data', 'background_color', 'background_image', 'custom_logo', 'external_header_video', 'header_video', 'background_repeat', 'background_position_x', 'background_attachment'], true), ARRAY_FILTER_USE_KEY) : $all;

set_theme_mod('zz_a', 'one');
set_theme_mod('zz_b', ['x' => 1]);
$say('theme mods', [$custom(get_theme_mods()), (static function () use ($modsOption) {
    remove_theme_mods();
    return [get_theme_mods(), get_option($modsOption, 'gone'), get_theme_mod('zz_a', 'default')];
})()]);
update_option($modsOption, $mods);

add_theme_support('custom-header', ['default-image' => '', 'width' => 1200, 'height' => 280, 'flex-height' => true, 'video' => true, 'header-text' => true, 'default-text-color' => '333333', 'uploads' => true]);
add_theme_support('custom-background', ['default-color' => 'ffffff', 'default-image' => '']);
$tags = static fn () => [
    $echoed(static fn () => header_textcolor()), $echoed(static fn () => header_image()), $echoed(static fn () => background_color()), $echoed(static fn () => background_image()),
    $echoed(static fn () => the_header_video_url()), $echoed(static fn () => the_custom_header_markup()), $echoed(static fn () => _custom_background_cb()),
];
$say('header and background tags, unset', $mask($tags()));
$header = $attachment('header');
set_theme_mod('header_textcolor', 'ff0000');
set_theme_mod('header_image', wp_get_attachment_url($header));
set_theme_mod('header_image_data', (object) ['attachment_id' => $header, 'url' => wp_get_attachment_url($header), 'thumbnail_url' => wp_get_attachment_url($header), 'width' => 1200, 'height' => 280]);
set_theme_mod('background_color', 'abcdef');
set_theme_mod('background_image', 'https://zz.example/bg "one".png');
set_theme_mod('background_repeat', 'no-repeat');
set_theme_mod('background_position_x', 'center');
set_theme_mod('background_attachment', 'fixed');
set_theme_mod('external_header_video', 'https://www.youtube.com/watch?v=zzzzzzzzzzz');
$say('header and background tags, set', $mask($tags()));
set_theme_mod('header_textcolor', 'blank');
set_theme_mod('header_image', 'remove-header');
set_theme_mod('background_image', '');
set_theme_mod('background_color', '');
remove_theme_mod('external_header_video');
$say('header and background tags, removed', $mask($tags()));

register_default_headers(['zz-one' => ['url' => '%s/zz-one.jpg', 'thumbnail_url' => '%s/zz-one-thumb.jpg', 'description' => 'Zz one']]);
set_theme_mod('header_image', 'random-default-image');
$say('random default header', $mask([get_random_header_image(), _get_random_header_data(), $echoed(static fn () => header_image()), is_random_header_image(), is_random_header_image('default'), is_random_header_image('uploaded')]));
unregister_default_headers('zz-one');
set_theme_mod('header_image', 'random-uploaded-image');
$say('random uploaded header with none uploaded', $mask([get_random_header_image(), _get_random_header_data()]));

$wpHead = static fn () => [has_action('wp_head', '_custom_background_cb'), has_action('wp_head', '_custom_logo_header_styles')];
$justInTime = [$wpHead()];
_custom_header_background_just_in_time();
$justInTime[] = $wpHead();
$justInTime[] = get_theme_support('custom-background');
$say('custom header and background just in time', $mask($justInTime));

$features = get_registered_theme_features();
$say('theme feature registry', [
    array_keys($features), get_registered_theme_feature('title-tag'), get_registered_theme_feature('editor-color-palette'), get_registered_theme_feature('zz_missing'),
    (static function () use ($features) {
        create_initial_theme_features();
        return array_keys(get_registered_theme_features()) === array_keys($features);
    })(),
]);
add_theme_support('zz-feature', 'a');
$supports = static fn () => array_map(static fn ($feature) => current_theme_supports($feature), ['zz-feature', 'post-thumbnails', 'responsive-embeds', 'editor-styles', 'html5', 'automatic-feed-links', 'wp-block-styles', 'block-template-parts', 'align-wide', 'title-tag', 'link-color', 'border', 'appearance-tools']);
$removed = [_remove_theme_support('zz-feature'), _remove_theme_support('zz-feature'), _remove_theme_support('zz-never')];
$before = $supports();
foreach (['post-thumbnails', 'responsive-embeds', 'editor-styles', 'html5', 'automatic-feed-links', 'align-wide'] as $feature) {
    remove_theme_support($feature);
}
$stripped = $supports();
_add_default_theme_supports();
$say('default theme supports', [$removed, $before, $stripped, $supports(), get_theme_support('html5')]);

$roots = [get_theme_roots(), get_raw_theme_root($stylesheet), get_raw_theme_root($stylesheet, true), get_raw_theme_root('zz-missing-theme'), get_raw_theme_root('zz-missing-theme', true)];
$roots[] = register_theme_directory(WP_CONTENT_DIR . '/zz-missing-directory');
$roots[] = register_theme_directory(get_theme_root());
$roots[] = register_theme_directory('themes');
$roots[] = array_map(static fn ($directory) => $directory, $GLOBALS['wp_theme_directories']);
$say('theme roots and directories', $mask($roots));

$say('stylesheet addresses and checks', $mask([
    get_stylesheet_uri(), get_locale_stylesheet_uri(), $echoed(static fn () => locale_stylesheet()), is_child_theme(), validate_current_theme(),
]));

$logo = $attachment('logo');
$backgroundImage = $attachment('background');
set_theme_mod('custom_logo', $logo);
set_theme_mod('header_image', wp_get_attachment_url($header));
set_theme_mod('header_image_data', (object) ['attachment_id' => $header, 'url' => wp_get_attachment_url($header)]);
set_theme_mod('background_image', wp_get_attachment_url($backgroundImage));
set_theme_mod('background_image_thumb', wp_get_attachment_url($backgroundImage));
$themeMods = static fn () => $mask(array_map(static fn ($key) => get_theme_mod($key, '(none)'), ['custom_logo', 'header_image', 'header_image_data', 'background_image', 'background_image_thumb']));
$deleted = [$themeMods()];
_delete_attachment_theme_mod($logo);
$deleted[] = $themeMods();
_delete_attachment_theme_mod($header);
$deleted[] = $themeMods();
_delete_attachment_theme_mod($backgroundImage);
$deleted[] = $themeMods();
_delete_attachment_theme_mod(999999);
$deleted[] = $themeMods();
$say('a deleted attachment leaves the theme mods', $deleted);

$made[] = $css = (int) wp_insert_post(['post_type' => 'custom_css', 'post_name' => 'zz-th-theme', 'post_title' => 'zz-th-theme', 'post_status' => 'publish', 'post_content' => 'body { color: red; }']);
$ids[$css] = 'css';
$cssPost = static fn ($post) => $post instanceof WP_Post ? [$post->ID, $post->post_type, $post->post_name] : $post;
$say('custom CSS post', $mask([
    $cssPost(wp_get_custom_css_post('zz-th-theme')), $cssPost(wp_get_custom_css_post('zz-th-missing')), $cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)'), wp_get_custom_css('zz-th-theme'),
]));

// The active theme's own CSS post: what the lookup finds, and what it records in the theme mods.
remove_theme_mod('custom_css_post_id');
$lookups = [[$cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)')]];
$made[] = $own = (int) wp_insert_post(['post_type' => 'custom_css', 'post_name' => $stylesheet, 'post_title' => $stylesheet, 'post_status' => 'publish', 'post_content' => 'p { margin: 0; }']);
$ids[$own] = 'own-css';
$lookups[] = [$cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)')];
remove_theme_mod('custom_css_post_id');
$lookups[] = [$cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)'), wp_get_custom_css()];
set_theme_mod('custom_css_post_id', $css);
$lookups[] = [$cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)')];
wp_delete_post($own, true);
$lookups[] = [$cssPost(wp_get_custom_css_post()), get_theme_mod('custom_css_post_id', '(none)')];
$say('the active theme\'s CSS post', $mask($lookups));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
