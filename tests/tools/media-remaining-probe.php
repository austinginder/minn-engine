<?php
/**
 * The rest of media.php (probe media-remaining): image size registration,
 * sizes from metadata, width and height strings, ratio matches, srcset and
 * sizes for crafted metadata, srcset added to content images, lazy loading
 * defaults, audio and video extensions, attached media, attachment
 * taxonomies, the adjacent image links in an attachment's gallery, real
 * image files (sizes read, an intermediate size made), editor support and
 * output formats, the upload size limit, and the playlist scripts. The
 * image library differs (the reference picks Imagick, the engine GD), so
 * file sizes are compared by type. Posts and files are removed at the end.
 * wp_enqueue_media is left out: the engine does not run the media modal.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'files' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['files'] as $file) {
        if (is_string($file) && str_contains($file, 'zz-md-') && is_file($file)) {
            unlink($file);
        }
    }
    $dir = wp_get_upload_dir()['basedir'] . '/zz-md-files';
    if (is_dir($dir)) {
        @rmdir($dir);
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$uploads = wp_get_upload_dir();
$mask = static function ($value) use (&$mask, &$ids, $host, $uploads) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = str_replace([$uploads['basedir'], $uploads['baseurl']], ['{updir}', '{uploads}'], $value);
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
wp_set_current_user(1);

$sizes = static fn () => array_intersect_key(wp_get_additional_image_sizes(), array_flip(['1536x1536', '2048x2048', 'post-thumbnail', 'zz-md-size']));
$registration = [$sizes()];
remove_image_size('1536x1536');
remove_image_size('2048x2048');
$registration[] = $sizes();
_wp_add_additional_image_sizes();
$registration[] = $sizes();
set_post_thumbnail_size(320, 180, true);
$registration[] = $sizes();
set_post_thumbnail_size();
$registration[] = $sizes();
set_post_thumbnail_size('640', '0', ['center', 'top']);
$registration[] = $sizes();
remove_image_size('post-thumbnail');
$say('image size registration', $registration);

$meta = [
    'width' => 2000, 'height' => 1000, 'file' => '2026/10/zz-md-wide.jpg',
    'sizes' => [
        'thumbnail' => ['file' => 'zz-md-wide-150x150.jpg', 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg'],
        'medium' => ['file' => 'zz-md-wide-300x150.jpg', 'width' => 300, 'height' => 150, 'mime-type' => 'image/jpeg'],
        'medium_large' => ['file' => 'zz-md-wide-768x384.jpg', 'width' => 768, 'height' => 384, 'mime-type' => 'image/jpeg'],
        'large' => ['file' => 'zz-md-wide-1024x512.jpg', 'width' => 1024, 'height' => 512, 'mime-type' => 'image/jpeg'],
        '1536x1536' => ['file' => 'zz-md-wide-1536x768.jpg', 'width' => 1536, 'height' => 768, 'mime-type' => 'image/jpeg'],
        'odd' => ['file' => 'zz-md-wide-500x400.jpg', 'width' => 500, 'height' => 400, 'mime-type' => 'image/jpeg'],
        'webp' => ['file' => 'zz-md-wide-600x300.webp', 'width' => 600, 'height' => 300, 'mime-type' => 'image/webp'],
    ],
];
$say('sizes from metadata and strings', [
    array_map(static fn ($size) => _wp_get_image_size_from_meta($size, $meta), ['thumbnail', 'large', 'full', 'zz-missing', 'odd']),
    _wp_get_image_size_from_meta('large', []), _wp_get_image_size_from_meta('full', ['width' => 10]),
    array_map(static fn ($pair) => image_hwstring(...$pair), [[100, 50], [0, 50], [100, 0], ['100', '50'], [null, null], [12.5, 3]]),
    array_map(static fn ($four) => wp_image_matches_ratio(...$four), [[2000, 1000, 1024, 512], [2000, 1000, 300, 150], [2000, 1000, 500, 400], [2000, 1000, 1023, 512], [2000, 1000, 1021, 512], [100, 100, 99, 100], [1000, 1, 999, 1], [10, 10, 0, 0]]),
]);

$src = $uploads['baseurl'] . '/2026/10/zz-md-wide-1024x512.jpg';
$say('srcset and sizes', $mask([
    wp_calculate_image_srcset([1024, 512], $src, $meta), wp_calculate_image_srcset([300, 150], $uploads['baseurl'] . '/2026/10/zz-md-wide-300x150.jpg', $meta), wp_calculate_image_srcset([500, 400], $uploads['baseurl'] . '/2026/10/zz-md-wide-500x400.jpg', $meta),
    wp_calculate_image_srcset([1024, 512], 'https://elsewhere.example/zz-md-wide-1024x512.jpg', $meta), wp_calculate_image_srcset([1024, 512], $src, ['width' => 2000, 'height' => 1000, 'file' => '2026/10/zz-md-wide.jpg', 'sizes' => []]),
    wp_calculate_image_srcset([2000, 1000], $uploads['baseurl'] . '/2026/10/zz-md-wide.jpg', $meta), wp_calculate_image_srcset([0, 0], $src, $meta),
    wp_calculate_image_sizes([1024, 512]), wp_calculate_image_sizes('large', $src, $meta), wp_calculate_image_sizes('medium', null, $meta), wp_calculate_image_sizes([300, 150], $src), wp_calculate_image_sizes('zz-missing', null, $meta), wp_calculate_image_sizes('full', null, $meta),
]));

$wide = $made['posts'][] = (int) wp_insert_attachment(['post_title' => 'zz md wide', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], '2026/10/zz-md-wide.jpg', 0);
$ids[$wide] = 'wide';
wp_update_attachment_metadata($wide, $meta);
$say('srcset added to content images', $mask(array_map(static fn ($image) => wp_image_add_srcset_and_sizes($image, $meta, $wide), [
    '<img src="' . $src . '" width="1024" height="512" alt="" class="wp-image-' . $wide . '">',
    '<img src="' . $src . '" alt="">',
    '<img src="' . $src . '" width="1024" height="512" srcset="x.jpg 1x" alt="">',
    '<img src="https://elsewhere.example/zz.jpg" width="1024" height="512" alt="">',
    '<img src="' . $uploads['baseurl'] . '/2026/10/zz-md-wide-300x150.jpg" width="300" height="150" sizes="auto" alt="">',
    '<img src="' . $src . '?ver=2" width="1024" height="512" alt="">',
])));

$say('lazy loading defaults', array_map(static fn ($pair) => wp_lazy_loading_enabled(...$pair), [['img', 'the_content'], ['iframe', 'the_content'], ['video', 'the_content'], ['img', 'wp_get_attachment_image'], ['iframe', 'zz_context'], ['img', 'widget_text_content'], ['IMG', 'the_content']]));
add_filter('wp_lazy_loading_enabled', $off = static fn ($default, $tag, $context) => $context === 'zz_off' ? false : $default, 10, 3);
$say('lazy loading filtered', [wp_lazy_loading_enabled('img', 'zz_off'), wp_lazy_loading_enabled('img', 'the_content')]);
remove_filter('wp_lazy_loading_enabled', $off, 10);
$say('audio and video extensions', [wp_get_audio_extensions(), wp_get_video_extensions()]);

$parent = $made['posts'][] = (int) wp_insert_post(['post_title' => 'zz md gallery', 'post_status' => 'publish', 'post_content' => 'x']);
$ids[$parent] = 'gallery';
$child = static function (string $name, string $mime, int $order) use (&$made, &$ids, $parent): int {
    $made['posts'][] = $id = (int) wp_insert_attachment(['post_title' => "zz md {$name}", 'post_mime_type' => $mime, 'post_status' => 'inherit', 'menu_order' => $order], "2026/10/zz-md-{$name}." . (str_starts_with($mime, 'image') ? 'jpg' : 'mp3'), $parent);
    if (str_starts_with($mime, 'image')) {
        wp_update_attachment_metadata($id, ['width' => 600, 'height' => 400, 'file' => "2026/10/zz-md-{$name}.jpg", 'sizes' => ['thumbnail' => ['file' => "zz-md-{$name}-150x150.jpg", 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg']]]);
    }
    $ids[$id] = $name;
    return $id;
};
$first = $child('first', 'image/jpeg', 1);
$second = $child('second', 'image/jpeg', 2);
$third = $child('third', 'image/png', 3);
$song = $child('song', 'audio/mpeg', 4);
$titles = static fn ($posts) => array_map(static fn ($post) => [$post->ID, $post->post_title, $post->post_mime_type], array_values($posts));
$say('attached media', $mask([
    $titles(get_attached_media('image', $parent)), $titles(get_attached_media('audio', get_post($parent))), $titles(get_attached_media('', $parent)), $titles(get_attached_media('image/png', $parent)), $titles(get_attached_media('video', $parent)), array_keys(get_attached_media('image', $parent)) === [$first, $second, $third] || array_keys(get_attached_media('image', $parent)) === [$third, $second, $first],
]));
$say('attachment taxonomies', [get_taxonomies_for_attachments(), array_map(static fn ($taxonomy) => [$taxonomy->name, $taxonomy->object_type], array_values(get_taxonomies_for_attachments('objects')))]);

$imageLinks = static function (int $id) use ($echoed) {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query(['p' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit']);
    $wp_query->the_post();
    return [
        $echoed(static fn () => previous_image_link()), $echoed(static fn () => next_image_link()), $echoed(static fn () => previous_image_link(false, 'Back')), $echoed(static fn () => next_image_link('medium', 'On')),
        $echoed(static fn () => adjacent_image_link(true, 0, false)), $echoed(static fn () => adjacent_image_link(false, [40, 40], '<b>x</b>')), $echoed(static fn () => next_image_link(0, true)),
    ];
};
$say('adjacent image links', $mask([$imageLinks($first), $imageLinks($second), $imageLinks($third), $imageLinks($song)]));
$heard = [];
foreach (['previous_image_link', 'next_image_link'] as $hook) {
    add_filter($hook, $listeners[$hook] = static function ($output, ...$rest) use ($hook, &$heard) {
        $heard[] = [$hook, ...$rest];
        return $output;
    }, 10, 4);
}
$imageLinks($second);
$imageLinks($song);
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener, 10);
}
$say('adjacent image link filters', $mask($heard));

$dir = $uploads['basedir'] . '/zz-md-files';
wp_mkdir_p($dir);
$canvas = imagecreatetruecolor(300, 200);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 200, 30, 30));
imagepng($canvas, $made['files'][] = "{$dir}/zz-md-red.png");
imagejpeg($canvas, $made['files'][] = "{$dir}/zz-md-red.jpg", 80);
file_put_contents($made['files'][] = "{$dir}/zz-md-text.png", 'not an image');
$info = null;
$read = [wp_getimagesize("{$dir}/zz-md-red.png"), wp_getimagesize("{$dir}/zz-md-red.jpg", $info), is_array($info), wp_getimagesize("{$dir}/zz-md-text.png"), wp_getimagesize("{$dir}/zz-md-missing.png")];
$made['files'][] = "{$dir}/zz-md-red-100x100.png";
$made['files'][] = "{$dir}/zz-md-red-150x100.png";
$made['files'][] = "{$dir}/zz-md-red-120x80.jpg";
$intermediate = static function ($result) {
    if (is_array($result) && array_key_exists('filesize', $result)) {
        $result['filesize'] = is_int($result['filesize']) && $result['filesize'] > 0 ? 'bytes' : $result['filesize'];
    }
    return $result;
};
$say('image files', $mask([
    $read, is_gd_image($canvas), is_gd_image('x'), is_gd_image(null), is_gd_image(imagecreate(1, 1)),
    $intermediate(image_make_intermediate_size("{$dir}/zz-md-red.png", 100, 100, true)), $intermediate(image_make_intermediate_size("{$dir}/zz-md-red.png", 150, 0)), $intermediate(image_make_intermediate_size("{$dir}/zz-md-red.jpg", 120, 120)),
    image_make_intermediate_size("{$dir}/zz-md-red.png", 0, 0), image_make_intermediate_size("{$dir}/zz-md-red.png", 600, 600), $intermediate(image_make_intermediate_size("{$dir}/zz-md-missing.png", 50, 50)),
    array_map(static fn ($file) => is_file("{$dir}/{$file}"), ['zz-md-red-100x100.png', 'zz-md-red-150x100.png', 'zz-md-red-120x80.jpg']),
]));

$say('editor support and output formats', [
    array_map(static fn ($args) => wp_image_editor_supports($args), [[], ['mime_type' => 'image/jpeg'], ['mime_type' => 'image/png', 'methods' => ['resize', 'crop', 'rotate']], ['mime_type' => 'image/zz'], ['mime_type' => 'application/pdf', 'methods' => ['zz_method']], ['methods' => ['zz_method']]]),
    array_map(static fn ($pair) => wp_get_image_editor_output_format(...$pair), [['a.jpg', 'image/jpeg'], ['a.heic', 'image/heic'], ['a.png', ''], ['a.webp', 'image/webp'], ['a', 'image/heif'], ['a.HEIC', null]]),
]);
add_filter('image_editor_output_format', $format = static fn ($formats) => $formats + ['image/png' => 'image/webp']);
$say('output formats filtered', [wp_get_image_editor_output_format('a.png', 'image/png'), wp_get_image_editor_output_format('a.jpg', 'image/jpeg')]);
remove_filter('image_editor_output_format', $format);

$bytes = static fn (string $value) => wp_convert_hr_to_bytes($value);
$heard = [];
add_filter('upload_size_limit', $limit = static function ($size, $upload, $post) use (&$heard) {
    $heard[] = [$size, $upload, $post];
    return $size;
}, 10, 3);
$max = wp_max_upload_size();
remove_filter('upload_size_limit', $limit, 10);
$u = $bytes((string) ini_get('upload_max_filesize'));
$p = $bytes((string) ini_get('post_max_size'));
$say('upload size limit', [$max === min($u, $p), $heard === [[min($u, $p), $u, $p]]]);

$playlist = [wp_script_is('wp-playlist', 'enqueued'), wp_style_is('wp-mediaelement', 'enqueued'), has_action('wp_footer', 'wp_underscore_playlist_templates'), has_action('admin_footer', 'wp_underscore_playlist_templates')];
wp_playlist_scripts('audio');
$playlist[] = [wp_script_is('wp-playlist', 'enqueued'), wp_style_is('wp-mediaelement', 'enqueued'), has_action('wp_footer', 'wp_underscore_playlist_templates'), has_action('admin_footer', 'wp_underscore_playlist_templates')];
$say('playlist scripts', $playlist);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
