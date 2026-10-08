<?php
/**
 * media.php's tag helpers (probe media-tags): the loading, decoding and
 * fetchpriority attributes an <img> or <iframe> gets by context, the width
 * and height an attachment's image is given, the content media count they
 * share, the sizes="auto" test, the post thumbnail's class and context
 * filters, next and previous image links, and the small pure helpers
 * (dimensions, encode quality, the mediaelement fallback, AVIF and Chromium
 * readers, client-side processing). Uses the block battery's image (607);
 * the posts it makes are named zz-media-tags-* and swept at start and
 * shutdown. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$sweep = static function (): void {
    foreach (get_posts(['post_type' => ['post', 'attachment'], 'post_status' => 'any', 'numberposts' => -1, 's' => 'zz-media-tags', 'fields' => 'ids']) as $id) {
        if (is_int($id) && $id > 0 && str_starts_with((string) get_post_field('post_name', $id), 'zz-media-tags-')) {
            wp_delete_post($id, true);
        }
    }
};
$sweep();
register_shutdown_function($sweep);
$ids = [];
// A probe-made id reads as its name in the output.
$mask = static function ($value) use (&$ids) {
    $text = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES);
    foreach ($ids as $name => $id) {
        $text = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $text);
    }
    return is_string($value) ? $text : json_decode($text, true);
};

// The count starts at nothing, before anything here has drawn an image.
$say('media count at start', [wp_increase_content_media_count(0), wp_increase_content_media_count(), wp_increase_content_media_count(2), wp_increase_content_media_count(0)]);

$say('sizes auto', array_map(static fn ($s) => [$s, wp_sizes_attribute_includes_valid_auto($s)], ['auto', 'auto, 100vw', 'AUTO , 50vw', '100vw, auto', ' auto', 'autocomplete', '', 'auto,', '(max-width: 600px) 100vw, auto']));
$say('expand dimensions', array_map(static fn ($d) => [$d, wp_expand_dimensions(...$d)], [[640, 360, 500, 500], [640, 360, 1000, 1000], [100, 100, 50, 200], [300, 150, 300, 150], [200, 400, 100, 100], [16, 9, 320, 1000]]));
$quality = [];
foreach (['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'text/plain'] as $mime) {
    $quality[$mime] = [wp_get_image_encode_quality($mime), wp_get_image_encode_quality($mime, ['width' => 2000, 'height' => 2000]), wp_get_image_encode_quality($mime, [], 70)];
}
$say('encode quality', $quality);
add_filter('wp_editor_set_quality', $q = static fn ($quality, $mime) => $mime === 'image/webp' ? 61 : $quality, 10, 2);
$say('encode quality filtered', [wp_get_image_encode_quality('image/webp'), wp_get_image_encode_quality('image/jpeg')]);
remove_filter('wp_editor_set_quality', $q, 10);
add_filter('jpeg_quality', $j = static fn ($quality, $context) => $quality - 10, 10, 2);
$say('encode quality, jpeg_quality filtered', [wp_get_image_encode_quality('image/jpeg'), wp_get_image_encode_quality('image/png'), wp_get_image_encode_quality('image/jpeg', [], 50)]);
remove_filter('jpeg_quality', $j, 10);
$say('mediaelement fallback', [wp_mediaelement_fallback('https://minn.localhost/a.mp3'), wp_mediaelement_fallback('https://minn.localhost/a b&c.mp4')]);

$file = get_attached_file(607);
// A small AVIF made here, so the reader has a real one to read.
$avif = sys_get_temp_dir() . '/zz-media-tags-probe.avif';
$canvas = imagecreatetruecolor(24, 16);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 200, 40, 90));
imageavif($canvas, $avif, 60, 9);
$say('avif info', [wp_get_avif_info('/nonexistent/zz.avif'), wp_get_avif_info((string) $file), wp_get_avif_info($avif)]);
@unlink($avif);

$agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$chromium = [];
foreach (['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36', 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.71 Safari/537.36 Edg/120.0.2210.61', 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', '', 'Chromium/88.1'] as $ua) {
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    $chromium[] = [$ua, wp_get_chromium_major_version()];
}
unset($_SERVER['HTTP_USER_AGENT']);
$chromium[] = ['(none)', wp_get_chromium_major_version()];
if ($agent !== null) {
    $_SERVER['HTTP_USER_AGENT'] = $agent;
}
$say('chromium version', $chromium);
$clientSide = [wp_is_client_side_media_processing_enabled()];
add_filter('wp_client_side_media_processing_enabled', '__return_true');
$clientSide[] = wp_is_client_side_media_processing_enabled();
remove_filter('wp_client_side_media_processing_enabled', '__return_true');
$say('client-side processing', $clientSide);

$meta = wp_get_attachment_metadata(607);
$base = basename((string) ($meta['file'] ?? ''));
$sizeFile = (string) (array_values($meta['sizes'] ?? [])[0]['file'] ?? '');
$say('file matches meta', [
    wp_image_file_matches_image_meta($base, $meta, 607),
    wp_image_file_matches_image_meta('https://minn.localhost/wp-content/uploads/x/' . $base, $meta),
    wp_image_file_matches_image_meta($sizeFile, $meta),
    wp_image_file_matches_image_meta('other.jpg', $meta),
    wp_image_file_matches_image_meta($base . '?ver=1', $meta),
    wp_image_file_matches_image_meta('orig.jpg', ['file' => 'x/a.jpg', 'original_image' => 'orig.jpg']),
    wp_image_file_matches_image_meta('a.jpg', []),
    wp_image_file_matches_image_meta('https://minn.localhost/wp-content/uploads/' . $meta['file'], $meta, 607),
    wp_image_file_matches_image_meta($meta['file'], $meta),
    wp_image_file_matches_image_meta('/elsewhere/' . $meta['file'], $meta),
    wp_image_file_matches_image_meta(dirname($meta['file']) . '/' . $sizeFile, $meta),
    wp_image_file_matches_image_meta('https://minn.localhost/wp-content/uploads/' . $meta['file'] . '?x=1', $meta),
    wp_image_file_matches_image_meta('x/orig.jpg', ['file' => 'x/a.jpg', 'original_image' => 'orig.jpg']),
]);
add_filter('wp_image_file_matches_image_meta', $m = static fn ($match, $location) => str_ends_with($location, '.zz') ? true : $match, 10, 2);
$say('file matches meta, filtered', [wp_image_file_matches_image_meta('any.zz', $meta), wp_image_file_matches_image_meta($meta['file'], $meta)]);
remove_filter('wp_image_file_matches_image_meta', $m, 10);

// The tag helpers, each group starting with high priority unclaimed.
$images = [
    '<img src="a.jpg" width="100" height="100">',
    '<img src="big.jpg" width="1200" height="800">',
    '<img src="a.jpg">',
    '<img src="a.jpg" width="100" height="100" loading="eager">',
    '<img src="a.jpg" width="100" height="100" loading="lazy">',
    '<img src="big.jpg" width="1200" height="800" fetchpriority="high">',
    '<img src="a.jpg" width="100" height="100" decoding="sync">',
    '<img width="100" height="100" src="a.jpg" class="x"/>',
];
$tags = [];
foreach (['the_content', 'wp_get_attachment_image', 'widget_text_content', 'template', 'zz_other'] as $context) {
    wp_high_priority_element_flag(true);
    $tags[$context] = array_map(static fn ($tag) => wp_img_tag_add_loading_optimization_attrs($tag, $context), $images);
}
$say('img loading optimization', $tags);
add_filter('wp_img_tag_add_loading_attr', $f = static fn () => false);
wp_high_priority_element_flag(true);
$say('img loading optimization, loading filtered off', [wp_img_tag_add_loading_optimization_attrs($images[0], 'zz_other'), wp_img_tag_add_loading_optimization_attrs($images[1], 'zz_other')]);
remove_filter('wp_img_tag_add_loading_attr', $f);
$say('media count after the tag helpers', [wp_increase_content_media_count(0)]);

$url = wp_get_attachment_url(607);
$say('img width and height', [
    wp_img_tag_add_width_and_height_attr('<img src="' . $url . '" class="wp-image-607">', 'the_content', 607),
    wp_img_tag_add_width_and_height_attr('<img src="' . $url . '" width="10" class="wp-image-607">', 'the_content', 607),
    wp_img_tag_add_width_and_height_attr('<img src="https://minn.localhost/elsewhere.jpg">', 'the_content', 607),
    wp_img_tag_add_width_and_height_attr('<img src="' . $url . '">', 'the_content', 0),
]);
add_filter('wp_img_tag_add_width_and_height_attr', '__return_false');
$say('img width and height, filtered off', [wp_img_tag_add_width_and_height_attr('<img src="' . $url . '" class="wp-image-607">', 'the_content', 607)]);
remove_filter('wp_img_tag_add_width_and_height_attr', '__return_false');

$frames = ['<iframe src="https://x.example/" width="560" height="315"></iframe>', '<iframe src="https://x.example/"></iframe>', '<iframe loading="eager" src="x" width="1" height="1"></iframe>', '<iframe src="x" width="1" height="1" data-wp-loading="x"></iframe>'];
$iframe = [];
foreach (['the_content', 'widget_text_content', 'zz_other'] as $context) {
    $iframe[$context] = array_map(static fn ($tag) => wp_iframe_tag_add_loading_attr($tag, $context), $frames);
}
$say('iframe loading', $iframe);
add_filter('wp_iframe_tag_add_loading_attr', '__return_false');
$say('iframe loading, filtered off', [wp_iframe_tag_add_loading_attr($frames[0], 'the_content')]);
remove_filter('wp_iframe_tag_add_loading_attr', '__return_false');

$priority = [];
foreach ([[[], 'img', ['width' => 1200, 'height' => 800]], [['loading' => 'lazy'], 'img', ['width' => 1200, 'height' => 800]], [[], 'img', ['width' => 10, 'height' => 10]], [[], 'iframe', ['width' => 1200, 'height' => 800]], [[], 'img', ['width' => 1200, 'height' => 800, 'fetchpriority' => 'low']], [[], 'img', ['width' => 1200, 'height' => 800]]] as $i => [$attrs, $tag, $attr]) {
    if ($i === 0) {
        wp_high_priority_element_flag(true);
    }
    $priority[] = [$attrs, $tag, $attr, wp_maybe_add_fetchpriority_high_attr($attrs, $tag, $attr), wp_high_priority_element_flag()];
}
$say('fetchpriority high', $priority);

// The post thumbnail's filters, and the thumbnail they shape.
$say('thumbnail class filter', [_wp_post_thumbnail_class_filter(['class' => 'a']), _wp_post_thumbnail_class_filter(['class' => '']), _wp_post_thumbnail_class_filter(['alt' => 'x']), _wp_post_thumbnail_context_filter('zz')]);
$state = static fn (): array => [has_filter('wp_get_attachment_image_attributes', '_wp_post_thumbnail_class_filter'), has_filter('wp_get_attachment_image_context', '_wp_post_thumbnail_context_filter')];
$before = $state();
_wp_post_thumbnail_class_filter_add([]);
_wp_post_thumbnail_context_filter_add();
$during = $state();
_wp_post_thumbnail_class_filter_remove([]);
_wp_post_thumbnail_context_filter_remove();
$say('thumbnail filters added and removed', [$before, $during, $state()]);
$post = wp_insert_post(['post_title' => 'zz-media-tags-post', 'post_name' => 'zz-media-tags-post', 'post_status' => 'publish', 'post_content' => 'x']);
if (is_int($post) && $post > 0) {
    $ids['post'] = $post;
    update_post_meta($post, '_thumbnail_id', 607);
    $seen = [];
    add_filter('wp_get_attachment_image_context', $c = static function ($context) use (&$seen) { $seen[] = $context; return $context; }, 99);
    wp_high_priority_element_flag(true);
    $say('post thumbnail', $mask([get_the_post_thumbnail($post), get_the_post_thumbnail($post, 'thumbnail', ['class' => 'custom']), $seen, $state()]));
    remove_filter('wp_get_attachment_image_context', $c, 99);
}

// Next and previous image links among one parent's attachments.
$parent = wp_insert_post(['post_title' => 'zz-media-tags-parent', 'post_name' => 'zz-media-tags-parent', 'post_status' => 'publish', 'post_content' => 'x']);
if (is_int($parent) && $parent > 0) {
    $ids['parent'] = $parent;
    foreach (['a', 'b', 'c'] as $order => $letter) {
        $id = wp_insert_attachment(['post_title' => 'zz-media-tags-' . $letter, 'post_name' => 'zz-media-tags-' . $letter, 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'menu_order' => $order], false, $parent);
        if (is_int($id) && $id > 0) {
            $ids[$letter] = $id;
        }
    }
    $GLOBALS['post'] = get_post($ids['b'] ?? 0);
    $say('adjacent image links', $mask([get_next_image_link('thumbnail', 'Next'), get_previous_image_link('thumbnail', 'Prev'), get_next_image_link(), get_previous_image_link('full', false)]));
    $GLOBALS['post'] = get_post($ids['c'] ?? 0);
    $say('adjacent image links at the end', $mask([get_next_image_link('thumbnail', 'Next'), get_previous_image_link('thumbnail', 'Prev')]));
    unset($GLOBALS['post']);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
