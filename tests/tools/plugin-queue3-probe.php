<?php
/**
 * The third wave of the plugin catalogue's queue (probe plugin-queue3), as
 * the reference answers it: an index dropped and added clean on a table
 * made for a moment; a folder's size, whole and with a part left out;
 * GD's truecolor canvas and the old image loader; typography values and
 * units, and font sizes fixed and fluid; the next widget number; a
 * featured image's border attributes; the elements class; the server
 * variables fixed up; the personal data export folder; a block theme's
 * header and footer parts; EXIF dates and fractions; yes and no; the old
 * editor formatters; an option read past the cache; whether a URL is an
 * attachment here; a caption shortcode around an image; slashes undone;
 * the page template options; and WP_Http_Encoding. Everything made is
 * removed at the end. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$table = $wpdb->prefix . 'zz_queue_index';
$dir = sys_get_temp_dir() . '/zz-queue-size-' . getmypid();
register_shutdown_function(static function () use ($wpdb, $table, $dir): void {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
    foreach (['a/b/c.txt', 'a/b.txt', 'skip/d.txt', 'e.txt'] as $file) {
        @unlink("{$dir}/{$file}");
    }
    foreach (['a/b', 'a', 'skip', ''] as $sub) {
        @rmdir(rtrim("{$dir}/{$sub}", '/'));
    }
    delete_transient('dirsize_cache');
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);
if (!function_exists('add_clean_index')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}
if (!function_exists('image_add_caption')) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
}
if (!function_exists('page_template_dropdown')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}
if (!function_exists('next_widget_id_number')) {
    require_once ABSPATH . 'wp-admin/includes/widgets.php';
}

// Indexes.
$wpdb->query("CREATE TABLE {$table} (id int NOT NULL, name varchar(20) NOT NULL DEFAULT '', KEY name (name))");
$indexes = static fn () => array_values(array_unique(array_map(static fn ($row) => $row->Key_name, (array) $wpdb->get_results("SHOW INDEX FROM {$table}"))));
$say('indexes at first', $indexes());
$say('drop_index', [drop_index($table, 'name'), $indexes()]);
$say('drop_index again', [drop_index($table, 'name'), $indexes()]);
$say('add_clean_index', [add_clean_index($table, 'name'), $indexes()]);
$say('add_clean_index again', [add_clean_index($table, 'name'), $indexes()]);

// A folder's size.
foreach (['a/b', 'skip'] as $sub) {
    mkdir("{$dir}/{$sub}", 0755, true);
}
foreach (['a/b/c.txt' => 10, 'a/b.txt' => 20, 'skip/d.txt' => 40, 'e.txt' => 80] as $file => $bytes) {
    file_put_contents("{$dir}/{$file}", str_repeat('x', $bytes));
}
$say('recurse_dirsize', recurse_dirsize($dir));
$say('recurse_dirsize, one part left out', recurse_dirsize($dir, "{$dir}/skip"));
$say('recurse_dirsize, parts left out', recurse_dirsize($dir, ["{$dir}/skip", "{$dir}/a/b"]));
$say('recurse_dirsize of nothing', recurse_dirsize("{$dir}/nope"));
$cache = [];
recurse_dirsize($dir, null, null, $cache);
$say('recurse_dirsize cache', array_map(static fn ($k) => str_replace($dir, '{dir}', $k), array_keys($cache)));
delete_transient('dirsize_cache');
$say('get_dirsize', get_dirsize($dir));
$say('get_dirsize cached', array_map(static fn ($k) => str_replace($dir, '{dir}', $k), array_keys((array) get_transient('dirsize_cache'))));

// GD.
$canvas = wp_imagecreatetruecolor(30, 20);
$say('wp_imagecreatetruecolor', [is_object($canvas) ? get_class($canvas) : gettype($canvas), imagesx($canvas), imagesy($canvas), imageistruecolor($canvas)]);
$image = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids'])[0] ?? 0);
$loaded = wp_load_image(get_attached_file($image));
$say('wp_load_image', is_object($loaded) ? [get_class($loaded), imagesx($loaded), imagesy($loaded)] : $loaded);
$say('wp_load_image of nothing', str_replace(sys_get_temp_dir(), '{tmp}', (string) wp_load_image(sys_get_temp_dir() . '/zz-nope.png')));
$say('wp_load_image of a non-image', str_replace($dir, '{dir}', (string) wp_load_image("{$dir}/e.txt")));
$say('wp_load_image of a number', wp_load_image($image));

// Typography.
foreach (['12px', '1.5rem', '2em', '100%', '3vw', 'auto', '', 14, '-1px'] as $raw) {
    $say('wp_get_typography_value_and_unit ' . var_export($raw, true), wp_get_typography_value_and_unit($raw));
}
$say('wp_get_typography_value_and_unit coerced', [wp_get_typography_value_and_unit('2rem', ['coerce_to' => 'px']), wp_get_typography_value_and_unit('24px', ['coerce_to' => 'rem']), wp_get_typography_value_and_unit('2em', ['coerce_to' => 'px', 'root_size_value' => 10])]);
foreach ([['size' => '16px'], ['size' => '2rem', 'fluid' => true], ['size' => '40px'], ['size' => '40px', 'fluid' => false], ['size' => '40px', 'fluid' => ['min' => '20px', 'max' => '60px']], ['size' => '1.5em'], ['size' => 'clamp(1rem, 2vw, 3rem)'], ['size' => '']] as $preset) {
    $say('wp_get_typography_font_size_value ' . wp_json_encode($preset), wp_get_typography_font_size_value($preset));
    $say('wp_get_typography_font_size_value fluid ' . wp_json_encode($preset), wp_get_typography_font_size_value($preset, ['fluid' => true]));
}

foreach (['12px', '14px', '15px', '18px', '24px', '64px', '100px', '400px', '1rem', '3rem', '0.5em', '2.25rem'] as $size) {
    $say("wp_get_typography_font_size_value {$size}", wp_get_typography_font_size_value(['size' => $size]));
}
foreach ([
    'a wide size of its own' => ['layout' => ['wideSize' => '1000px']],
    'viewports of its own' => ['typography' => ['fluid' => ['minViewportWidth' => '400px', 'maxViewportWidth' => '1200px']]],
    'a floor of its own' => ['typography' => ['fluid' => ['minFontSize' => '20px']]],
    'fluid off' => ['typography' => ['fluid' => false]],
    'the same viewports' => ['typography' => ['fluid' => ['minViewportWidth' => '800px', 'maxViewportWidth' => '800px']]],
    'a rem wide size' => ['layout' => ['wideSize' => '80rem']],
] as $label => $settings) {
    $say("wp_get_typography_font_size_value 32px, {$label}", wp_get_typography_font_size_value(['size' => '32px'], $settings));
    $say("wp_get_typography_font_size_value 2rem, {$label}", wp_get_typography_font_size_value(['size' => '2rem'], $settings));
}
$say('wp_get_typography_font_size_value with no size', wp_get_typography_font_size_value([]));
$say('wp_get_computed_fluid_typography_value', [
    wp_get_computed_fluid_typography_value(['minimum_viewport_width' => '320px', 'maximum_viewport_width' => '1000px', 'minimum_font_size' => '1rem', 'maximum_font_size' => '2rem', 'scale_factor' => 1]),
    wp_get_computed_fluid_typography_value(['minimum_viewport_width' => '320px', 'maximum_viewport_width' => '1000px', 'minimum_font_size' => '10px', 'maximum_font_size' => '10px', 'scale_factor' => 1]),
    wp_get_computed_fluid_typography_value(['minimum_viewport_width' => '20rem', 'maximum_viewport_width' => '60rem', 'minimum_font_size' => '16px', 'maximum_font_size' => '3rem', 'scale_factor' => 2]),
    wp_get_computed_fluid_typography_value(['minimum_font_size' => '1rem', 'maximum_font_size' => '2rem']),
]);

// Widgets and blocks.
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$say('next_widget_id_number', [next_widget_id_number('search'), next_widget_id_number('zz-none')]);
if (!function_exists('get_block_core_post_featured_image_border_attributes')) {
    require_once ABSPATH . WPINC . '/blocks/post-featured-image.php';
}
$say('get_block_core_post_featured_image_border_attributes', [
    get_block_core_post_featured_image_border_attributes(['style' => ['border' => ['width' => '2px', 'style' => 'dashed', 'radius' => '4px', 'color' => '#f00']]]),
    get_block_core_post_featured_image_border_attributes(['borderColor' => 'contrast', 'style' => ['border' => ['width' => '1px']]]),
    get_block_core_post_featured_image_border_attributes([]),
]);
$block = ['blockName' => 'core/group', 'attrs' => ['style' => ['elements' => ['link' => ['color' => ['text' => 'red']]]]], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
$other = ['attrs' => ['style' => ['elements' => ['link' => ['color' => ['text' => 'blue']]]]]] + $block;
$say('wp_get_elements_class_name', [(string) preg_replace('/[0-9a-f]{32}/', '{md5}', (string) preg_replace('/\d+$/', '{n}', wp_get_elements_class_name($block))), wp_get_elements_class_name($block) === wp_get_elements_class_name($block), wp_get_elements_class_name($block) === wp_get_elements_class_name($other)]);

// The server variables.
$saved = $_SERVER;
$_SERVER = ['SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '', 'PATH_INFO' => '/x', 'HTTP_HOST' => 'zz.example', 'QUERY_STRING' => 'a=1', 'SCRIPT_FILENAME' => '/srv/index.php'];
wp_fix_server_vars();
$say('wp_fix_server_vars', array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'PHP_SELF', 'SCRIPT_FILENAME', 'SERVER_SOFTWARE'])));
$_SERVER = $saved;

$say('wp_privacy_exports', [str_replace(wp_upload_dir()['basedir'], '{uploads}', wp_privacy_exports_dir()), str_replace(wp_upload_dir()['baseurl'], '{uploads}', wp_privacy_exports_url())]);

// A block theme's header and footer.
foreach (['block_header_area', 'block_footer_area'] as $area) {
    ob_start();
    $area();
    $html = (string) ob_get_clean();
    $say($area, [$html !== '', substr_count($html, '<header') + substr_count($html, '<footer'), str_contains($html, 'wp-block-site-title') || str_contains($html, 'wp-block-group')]);
}
ob_start();
block_template_part('zz-no-such-part');
$say('block_template_part of nothing', ob_get_clean());

$say('wp_exif_date2ts', [wp_exif_date2ts('2021:03:04 05:06:07'), wp_exif_date2ts('nope')]);
$say('wp_exif_frac2dec', [wp_exif_frac2dec('1/250'), wp_exif_frac2dec('10/2'), wp_exif_frac2dec('7'), wp_exif_frac2dec('3/0'), wp_exif_frac2dec('a/b'), wp_exif_frac2dec('1.5')]);
$say('bool_from_yn', [bool_from_yn('y'), bool_from_yn('Y'), bool_from_yn('n'), bool_from_yn('yes'), bool_from_yn('')]);
$say('wp_htmledit_pre', [wp_htmledit_pre('<b>a</b> & "b"'), wp_htmledit_pre('')]);
$say('wp_richedit_pre', [wp_richedit_pre("line one\n\nline <b>two</b> & three"), wp_richedit_pre('')]);
$say('__get_option', [__get_option('blogname') === get_option('blogname'), __get_option('zz_no_such_option')]);
$attachmentUrl = wp_get_attachment_url($image);
$say('is_local_attachment', [is_local_attachment(get_permalink($image)), is_local_attachment($attachmentUrl), is_local_attachment(home_url('/?attachment_id=' . $image)), is_local_attachment(home_url('/?p=' . $image)), is_local_attachment('https://elsewhere.example/a.png'), is_local_attachment(home_url('/sample-page/'))]);
$say('image_add_caption', [
    image_add_caption('<a href="x"><img src="y" class="alignleft size-full wp-image-5" /></a>', 5, 'A "caption"', 'Title', 'left', 'x', 'full', 'Alt'),
    image_add_caption('<img src="y" width="300" />', 6, '', 'Title', 'none', '', 'full'),
    image_add_caption('<a href="x"><img src="y" width="300" class="alignleft size-full wp-image-5" /></a>', 5, 'A "caption" with <b>tags</b>', 'Title', 'left', 'x', 'full', 'Alt'),
    image_add_caption('<img src="y" width="200" class="wp-image-6" />', 0, "Two\nlines", 'Title', 'none', '', 'full'),
]);
$say('deslash', [deslash("It\\'s \\\"here\\\" and \\\\'there\\\\'"), deslash('plain')]);
ob_start();
page_template_dropdown('', 'page');
$say('page_template_dropdown', ob_get_clean());

// WP_Http_Encoding.
$text = str_repeat('compress me ', 20);
$say('WP_Http_Encoding', [
    WP_Http_Encoding::is_available(),
    WP_Http_Encoding::decompress(WP_Http_Encoding::compress($text)) === $text,
    WP_Http_Encoding::decompress(gzencode($text)) === $text,
    WP_Http_Encoding::decompress(gzcompress($text)) === $text,
    WP_Http_Encoding::decompress('not compressed'),
    WP_Http_Encoding::compatible_gzinflate(gzencode($text)) === $text,
    WP_Http_Encoding::accept_encoding('https://x.example', []),
    WP_Http_Encoding::accept_encoding('https://x.example', ['stream' => true]),
    WP_Http_Encoding::accept_encoding('https://x.example', ['decompress' => true]),
    WP_Http_Encoding::accept_encoding('https://x.example', ['decompress' => true, 'stream' => true]),
    WP_Http_Encoding::content_encoding(),
    WP_Http_Encoding::should_decode(['content-encoding' => 'gzip']),
    WP_Http_Encoding::should_decode("HTTP/1.1 200 OK\r\nContent-Encoding: deflate\r\n"),
    WP_Http_Encoding::should_decode(['content-type' => 'text/plain']),
]);
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
