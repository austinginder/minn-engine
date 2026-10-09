<?php
/**
 * The font library (probe fonts): the fonts a theme declares and its style
 * variations declare (WP_Font_Face_Resolver), their @font-face rules printed
 * (wp_print_font_faces, WP_Font_Face), the font utilities, the fonts
 * directory, font collections registered from local data, refused and
 * unregistered (the reference's default collection lives on wordpress.org
 * and is left out), and a font family's posts and files removed with it.
 * The probe's collection file, font posts and font files are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$uploads = wp_upload_dir(null, false);
$mask = static function ($value) use (&$mask, $host, $uploads) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_wp_error($value)) {
        return ['error', $value->get_error_code(), $value->get_error_message()];
    }
    return is_string($value) ? str_replace([(string) $uploads['basedir'], (string) $uploads['baseurl'], $host, 'http://{host}'], ['{basedir}', '{baseurl}', '{host}', 'https://{host}'], $value) : $value;
};
$printed = static function (Closure $call) use ($mask) {
    ob_start();
    $out = $call();
    return [$mask(ob_get_clean()), $mask($out)];
};
$made = ['posts' => [], 'files' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0 && get_post($id)) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['files'] as $file) {
        if (is_string($file) && is_file($file)) {
            unlink($file);
        }
    }
});

$say('fonts a theme declares', [
    $mask(WP_Font_Face_Resolver::get_fonts_from_theme_json()),
    count(WP_Font_Face_Resolver::get_fonts_from_style_variations()) >= 0,
]);
$fonts = [
    'Zz Sans' => [
        ['font-family' => 'Zz Sans', 'src' => ['https://fonts.zz.example/sans-400.woff2', 'https://fonts.zz.example/sans-400.ttf'], 'font-weight' => '400', 'font-style' => 'normal'],
        ['font-family' => 'Zz Sans', 'src' => 'https://fonts.zz.example/sans-italic.woff', 'font-weight' => '300 700', 'font-style' => 'italic', 'font-display' => 'swap', 'unicode-range' => 'U+0000-00FF', 'font-stretch' => 'condensed', 'ascent-override' => '90%'],
        ['font-family' => 'Zz Sans', 'src' => []],
        ['font-family' => '', 'src' => ['https://fonts.zz.example/x.woff2']],
        ['src' => ['https://fonts.zz.example/y.woff2']],
    ],
    'zz-mono' => [
        ['font-family' => 'zz-mono', 'src' => ['data:font/woff2;base64,AAAA', 'https://fonts.zz.example/mono.otf', 'https://fonts.zz.example/mono.eot', 'https://fonts.zz.example/mono.svg', 'https://fonts.zz.example/mono.bin']],
    ],
];
$say('font faces printed', [
    $printed(static fn () => wp_print_font_faces($fonts)),
    $printed(static fn () => (new WP_Font_Face())->generate_and_print($fonts)),
    $printed(static fn () => (new WP_Font_Face())->generate_and_print([])),
    $printed(static fn () => wp_print_font_faces(['Only' => [['font-family' => 'Only', 'src' => []]]])),
    $printed(static fn () => wp_print_font_faces(['Zz Order' => [['font-family' => 'Zz Order', 'src' => ['https://f.example/a.otf', 'https://f.example/a.ttf', 'https://f.example/a.svg', 'https://f.example/a.woff', 'data:application/font-woff;base64,BBBB', 'https://f.example/a.eot', 'https://f.example/a.woff2', 'https://f.example/a.ttf?v=1', 'https://f.example/noext'], 'font-display' => 'nonsense', 'font-weight' => 700, 'font-variation-settings' => ['wght' => 400, 'wdth' => 100], 'font-feature-settings' => '"liga" 1', 'line-gap-override' => '10%', 'zz-unknown' => 'x']]])),
    $printed(static fn () => wp_print_font_faces(['Quote"d' => [['font-family' => "O'Brien \"Sans\"", 'src' => ['https://f.example/q.woff2']], ['font-family' => 'serif', 'src' => 'https://f.example/s.woff2'], ['font-family' => 'Zz, Two', 'src' => ['https://f.example/t.woff2']]]])),
    $printed(static fn () => wp_print_font_faces_from_style_variations()),
    (static function () use ($printed) {
        add_filter('wp_font_face_display', static fn () => 'block');
        $out = $printed(static fn () => wp_print_font_faces(['Zz Display' => [['font-family' => 'Zz Display', 'src' => ['https://fonts.zz.example/d.woff2']]]]));
        remove_all_filters('wp_font_face_display');
        return $out;
    })(),
]);

$say('font utilities', [
    WP_Font_Utils::sanitize_font_family('Open Sans, Arial, sans-serif'),
    WP_Font_Utils::sanitize_font_family('"Zz Display", serif'),
    WP_Font_Utils::sanitize_font_family('Zz<script>'),
    WP_Font_Utils::sanitize_font_family(''),
    WP_Font_Utils::get_font_face_slug(['fontFamily' => 'Open Sans, sans-serif', 'fontStyle' => 'italic', 'fontWeight' => '400', 'src' => ['https://x.example/a.woff2'], 'unicodeRange' => 'U+0000-00FF']),
    WP_Font_Utils::get_font_face_slug(['fontFamily' => 'Zz', 'fontWeight' => 'bold', 'fontStretch' => '100%', 'src' => 'https://x.example/b.ttf']),
    WP_Font_Utils::get_font_face_slug(['fontFamily' => 'Zz', 'fontWeight' => '300 700']),
    WP_Font_Utils::sanitize_from_schema(['fontFamily' => '<b>Zz</b>', 'slug' => 'Zz Slug!', 'fontFace' => [['src' => 'https://x.example/a b.woff2', 'fontWeight' => '<i>400</i>', 'extra' => 'gone']], 'unknown' => 'gone'], ['fontFamily' => 'sanitize_text_field', 'slug' => 'sanitize_title', 'fontFace' => [['src' => 'sanitize_url', 'fontWeight' => 'sanitize_text_field']]]),
    WP_Font_Utils::sanitize_from_schema(['name' => 'Zz', 'list' => ['a', 'b']], ['name' => 'sanitize_text_field', 'list' => 'sanitize_text_field']),
    WP_Font_Utils::get_allowed_font_mime_types(),
]);

$say('the fonts directory', [
    $mask(wp_font_dir()),
    $mask(wp_font_dir(false)),
    (static function () use ($mask) {
        add_filter('font_dir', static fn ($dir) => ['subdir' => '/zz'] + $dir);
        $out = $mask(wp_font_dir());
        remove_all_filters('font_dir');
        return $out;
    })(),
    $mask(_wp_filter_font_directory(['path' => '/p/2024/01', 'url' => 'https://x.example/u/2024/01', 'subdir' => '/2024/01', 'basedir' => '/p', 'baseurl' => 'https://x.example/u', 'error' => false])),
]);

$data_file = rtrim(get_temp_dir(), '/') . '/zz-fonts-' . getmypid() . '.json';
$made['files'][] = $data_file;
file_put_contents($data_file, wp_json_encode(['font_families' => [['font_family_settings' => ['name' => 'Zz From File', 'slug' => 'zz-from-file', 'fontFamily' => 'Zz From File', 'fontFace' => [['src' => 'https://fonts.zz.example/f.woff2', 'fontWeight' => '400', 'fontStyle' => 'normal', 'fontFamily' => 'Zz From File']]], 'categories' => ['sans-serif']]], 'categories' => [['name' => 'Sans Serif', 'slug' => 'sans-serif']]]));
$library = WP_Font_Library::get_instance();
$families = [['font_family_settings' => ['name' => 'Zz Inline', 'slug' => 'zz-inline', 'fontFamily' => 'Zz Inline, sans-serif', 'fontFace' => [['src' => 'https://fonts.zz.example/i.woff2', 'fontWeight' => '400', 'fontStyle' => 'normal', 'fontFamily' => 'Zz Inline']]], 'categories' => ['zz']]];
$collection = static fn ($value) => $value instanceof WP_Font_Collection ? ['collection', $value->slug, $value->get_data() instanceof WP_Error ? $value->get_data()->get_error_code() : array_keys($value->get_data())] : (is_wp_error($value) ? ['error', $value->get_error_code(), $value->get_error_message()] : $value);
$say('font collections', [
    $library === WP_Font_Library::get_instance(),
    $heard(static fn () => $collection(wp_register_font_collection('zz-inline', ['name' => 'Zz Inline', 'description' => 'Inline fonts.', 'font_families' => $families, 'categories' => [['name' => 'Zz', 'slug' => 'zz']]]))),
    $heard(static fn () => $collection(wp_register_font_collection('zz-inline', ['name' => 'Again', 'font_families' => $families]))),
    $heard(static fn () => $collection(wp_register_font_collection('zz-file', ['name' => 'Zz File', 'font_families' => $data_file]))),
    $heard(static fn () => $collection(wp_register_font_collection('zz-noname', ['font_families' => $families]))),
    $heard(static fn () => $collection(wp_register_font_collection('zz-nofamilies', ['name' => 'No families']))),
    $heard(static fn () => $collection(wp_register_font_collection('Zz Bad Slug', ['name' => 'Bad', 'font_families' => $families]))),
    $heard(static fn () => $collection(wp_register_font_collection('zz-missing-file', ['name' => 'Missing', 'font_families' => $data_file . '.none']))),
    $heard(static fn () => $collection($library->get_font_collection('zz-inline'))),
    $heard(static fn () => $library->get_font_collection('zz-never')),
    $heard(static fn () => array_values(array_filter(array_keys($library->get_font_collections()), static fn ($slug) => str_starts_with($slug, 'zz-')))),
    $heard(static fn () => $library->get_font_collection('zz-inline')->get_data()),
    $heard(static fn () => $library->get_font_collection('zz-file')->get_data()),
    $heard(static function () use ($library) {
        $missing = $library->get_font_collection('zz-missing-file');
        return $missing ? [$missing->get_data()->get_error_code(), $missing->get_data()->get_error_message()] : null;
    }),
    $heard(static fn () => wp_unregister_font_collection('zz-inline')),
    $heard(static fn () => wp_unregister_font_collection('zz-inline')),
    $heard(static fn () => $library->unregister_font_collection('zz-file')),
    $heard(static fn () => $collection($library->register_font_collection('zz-direct', ['name' => 'Direct', 'font_families' => $families]))),
    $heard(static fn () => $collection(new WP_Font_Collection('zz-new', ['name' => 'New', 'font_families' => $families]))),
    $heard(static fn () => $collection(new WP_Font_Collection('zz-new', ['font_families' => $families]))),
    $heard(static fn () => $library->unregister_font_collection('zz-direct')),
]);
foreach (array_keys($library->get_font_collections()) as $slug) {
    if (str_starts_with($slug, 'zz-')) {
        $library->unregister_font_collection($slug);
    }
}

$font_dir = wp_font_dir();
wp_mkdir_p($font_dir['path']);
$face_file = trailingslashit($font_dir['path']) . 'zz-probe-' . getmypid() . '.woff2';
$made['files'][] = $face_file;
file_put_contents($face_file, 'zz');
$made['posts'][] = $family_id = (int) wp_insert_post(['post_type' => 'wp_font_family', 'post_status' => 'publish', 'post_title' => 'Zz Probe Family', 'post_name' => 'zz-probe-family', 'post_content' => wp_json_encode(['fontFamily' => 'Zz Probe Family'])]);
$made['posts'][] = $face_id = (int) wp_insert_post(['post_type' => 'wp_font_face', 'post_status' => 'publish', 'post_parent' => $family_id, 'post_title' => 'zz face', 'post_content' => wp_json_encode(['fontFamily' => 'Zz Probe Family', 'src' => $font_dir['url'] . '/' . basename($face_file)])]);
update_post_meta($face_id, '_wp_font_face_file', basename($face_file));
$say('a font family removed', [
    [has_action('deleted_post', '_wp_after_delete_font_family'), has_action('before_delete_post', '_wp_before_delete_font_face'), has_action('wp_head', 'wp_print_font_faces'), has_action('wp_head', 'wp_print_font_faces_from_style_variations'), has_action('init', '_wp_register_default_font_collections')],
    $family_id > 0 && $face_id > 0,
    is_file($face_file),
    (bool) wp_delete_post($family_id, true),
    get_post($face_id) === null,
    is_file($face_file),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
