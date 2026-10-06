<?php
/** Attachments, image sizes, and the media helpers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Blocks\RenderState;
use Minn\Media\Kind;
use Minn\Media\Metadata;
use Minn\Media\PhotoMeta;
use Minn\Media\Sizing;
use Minn\Media\Uploads;
use Minn\Runtime\PostLookup;
use Minn\Runtime\Runtime;

/** @internal the registered sizes: the four from the options, then add_image_size's (the two big ones first) */
function _minn_image_sizes(): array
{
    $sizes = [
        'thumbnail' => ['width' => (int) get_option('thumbnail_size_w'), 'height' => (int) get_option('thumbnail_size_h'), 'crop' => (bool) get_option('thumbnail_crop')],
        'medium' => ['width' => (int) get_option('medium_size_w'), 'height' => (int) get_option('medium_size_h'), 'crop' => false],
        'medium_large' => ['width' => (int) get_option('medium_large_size_w'), 'height' => (int) get_option('medium_large_size_h'), 'crop' => false],
        'large' => ['width' => (int) get_option('large_size_w'), 'height' => (int) get_option('large_size_h'), 'crop' => false],
    ];
    foreach (wp_get_additional_image_sizes() as $name => $size) {
        $sizes[$name] = $size;
    }
    return $sizes;
}

/** The sizes plugins and themes added, kept where they read them: $_wp_additional_image_sizes. */
function wp_get_additional_image_sizes()
{
    if (!isset($GLOBALS['_wp_additional_image_sizes']) || !is_array($GLOBALS['_wp_additional_image_sizes'])) {
        $GLOBALS['_wp_additional_image_sizes'] = [];
    }
    return $GLOBALS['_wp_additional_image_sizes'];
}

/**
 * The two big sizes every site has, registered as a plugin would register
 * them, at plugins_loaded, so plugins that read $_wp_additional_image_sizes
 * (Smush keeps a hash of it) and remove_image_size see them.
 */
function _wp_add_additional_image_sizes()
{
    add_image_size('1536x1536', 1536, 1536);
    add_image_size('2048x2048', 2048, 2048);
}

function add_image_size($name, $width = 0, $height = 0, $crop = false)
{
    wp_get_additional_image_sizes();
    $GLOBALS['_wp_additional_image_sizes'][(string) $name] = ['width' => absint($width), 'height' => absint($height), 'crop' => $crop];
}

/** Whether a size was added with add_image_size (the four from the options are not). */
function has_image_size($name)
{
    return isset(wp_get_additional_image_sizes()[$name]);
}

function remove_image_size($name)
{
    if (!isset(wp_get_additional_image_sizes()[$name])) {
        return false;
    }
    unset($GLOBALS['_wp_additional_image_sizes'][$name]);
    return true;
}

function set_post_thumbnail_size($width = 0, $height = 0, $crop = false)
{
    add_image_size('post-thumbnail', $width, $height, $crop);
}

function get_intermediate_image_sizes()
{
    return apply_filters('intermediate_image_sizes', array_keys(_minn_image_sizes()));
}

function wp_get_registered_image_subsizes()
{
    $out = [];
    foreach (_minn_image_sizes() as $name => $size) {
        $out[$name] = ['width' => (int) $size['width'], 'height' => (int) $size['height'], 'crop' => is_array($size['crop']) ? $size['crop'] : (bool) $size['crop']];
    }
    return apply_filters('wp_get_registered_image_subsizes', $out);
}

function wp_constrain_dimensions($current_width, $current_height, $max_width = 0, $max_height = 0)
{
    $constrained = Sizing::constrain((int) $current_width, (int) $current_height, (int) $max_width, (int) $max_height);
    if (!(int) $max_width && !(int) $max_height) {
        return $constrained;
    }
    return apply_filters('wp_constrain_dimensions', $constrained, (int) $current_width, (int) $current_height, (int) $max_width, (int) $max_height);
}

function image_resize_dimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop = false)
{
    [$orig_w, $orig_h, $dest_w, $dest_h] = [(int) $orig_w, (int) $orig_h, (int) $dest_w, (int) $dest_h];
    if ($orig_w <= 0 || $orig_h <= 0 || ($dest_w <= 0 && $dest_h <= 0)) {
        return false;
    }
    $output = apply_filters('image_resize_dimensions', null, $orig_w, $orig_h, $dest_w, $dest_h, $crop);
    if ($output !== null) {
        return $output;
    }
    return Sizing::resize($orig_w, $orig_h, $dest_w, $dest_h, (bool) $crop, static fn (int $w, int $h, int $mw, int $mh): array => wp_constrain_dimensions($w, $h, $mw, $mh)) ?? false;
}

function image_constrain_size_for_editor($width, $height, $size = 'medium', $context = null)
{
    $width = (int) $width;
    $height = (int) $height;
    if (is_array($size)) {
        $max_width = (int) $size[0];
        $max_height = (int) $size[1];
    } elseif ($size === 'thumb' || $size === 'thumbnail') {
        $max_width = (int) get_option('thumbnail_size_w') ?: 128;
        $max_height = (int) get_option('thumbnail_size_h') ?: 96;
    } elseif ($size === 'full') {
        return [$width, $height];
    } else {
        $sizes = _minn_image_sizes();
        if (!isset($sizes[$size])) {
            return [$width, $height];
        }
        $max_width = (int) $sizes[$size]['width'];
        $max_height = (int) $sizes[$size]['height'];
    }
    return wp_constrain_dimensions($width, $height, $max_width, $max_height);
}

function wp_get_attachment_metadata($attachment_id = 0, $unfiltered = false)
{
    $attachment_id = (int) $attachment_id ?: get_the_ID();
    $post = get_post($attachment_id);
    if ($post === null) {
        return false;
    }
    $data = get_post_meta($post->ID, '_wp_attachment_metadata', true);
    if (!is_array($data)) {
        $blob = _minn_posts()->meta($post->ID, '_wp_attachment_metadata');
        $data = $blob === null ? false : Metadata::parse($blob);
    }
    if ($data === false || $data === '') {
        return false;
    }
    return $unfiltered ? $data : apply_filters('wp_get_attachment_metadata', $data, $post->ID);
}

function wp_update_attachment_metadata($attachment_id, $data)
{
    $post = get_post((int) $attachment_id);
    if ($post === null) {
        return false;
    }
    $data = apply_filters('wp_update_attachment_metadata', $data, $post->ID);
    if ($data) {
        return update_post_meta($post->ID, '_wp_attachment_metadata', $data);
    }
    return delete_post_meta($post->ID, '_wp_attachment_metadata');
}

function wp_get_attachment_url($attachment_id = 0)
{
    $post = get_post((int) $attachment_id);
    if ($post === null || $post->post_type !== 'attachment') {
        return false;
    }
    $file = (string) get_post_meta($post->ID, '_wp_attached_file', true);
    $url = '';
    if ($file !== '') {
        $uploads = wp_get_upload_dir();
        if (str_starts_with($file, $uploads['basedir'])) {
            $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
        } elseif (str_contains($file, 'wp-content/uploads')) {
            $url = trailingslashit($uploads['baseurl'] . '/' . get_current_user_id()) . '../' . ltrim(substr($file, strpos($file, 'wp-content/uploads') + 18), '/');
        } else {
            $url = $uploads['baseurl'] . '/' . ltrim($file, '/');
        }
    }
    if ($url === '') {
        $url = $post->guid;
    }
    $url = apply_filters('wp_get_attachment_url', $url, $post->ID);
    return $url === '' ? false : $url;
}

function wp_get_attachment_thumb_url($post_id = 0)
{
    $post_id = (int) $post_id ?: get_the_ID();
    $post = get_post($post_id);
    if ($post === null) {
        return false;
    }
    $url = wp_get_attachment_url($post->ID);
    if (!$url) {
        return false;
    }
    $sized = image_downsize($post->ID, 'thumbnail');
    if ($sized) {
        $url = $sized[0];
    }
    return apply_filters('wp_get_attachment_thumb_url', $url, $post->ID);
}

function wp_attachment_is($type, $post = null)
{
    $post = get_post($post);
    $file = $post === null ? '' : (string) get_attached_file($post->ID);
    if ($file === '') {
        return false;
    }
    $check = wp_check_filetype($file);
    return Kind::matches((string) $type, (string) $post->post_mime_type, (string) ($check['ext'] ?: ''), wp_get_audio_extensions(), wp_get_video_extensions());
}

function wp_attachment_is_image($post = null)
{
    return wp_attachment_is('image', $post);
}

function wp_get_audio_extensions()
{
    return apply_filters('wp_audio_extensions', ['mp3', 'ogg', 'flac', 'm4a', 'wav']);
}

function wp_get_video_extensions()
{
    return apply_filters('wp_video_extensions', ['mp4', 'm4v', 'webm', 'ogv', 'flv']);
}

function image_get_intermediate_size($post_id, $size = 'thumbnail')
{
    $post_id = (int) $post_id;
    $meta = wp_get_attachment_metadata($post_id);
    if (!is_array($meta) || !$size) {
        return false;
    }
    $data = Sizing::intermediate($meta, $size, wp_get_attachment_url($post_id) ?: null, static fn (int $w, int $h, array $box): array => image_constrain_size_for_editor($w, $h, $box));
    return $data === null ? false : apply_filters('image_get_intermediate_size', $data, $post_id, $size);
}

function image_downsize($id, $size = 'medium')
{
    $post = get_post((int) $id);
    if ($post === null || !wp_attachment_is_image($post->ID)) {
        return false;
    }
    $out = apply_filters('image_downsize', false, $post->ID, $size);
    if ($out) {
        return $out;
    }
    $img_url = wp_get_attachment_url($post->ID);
    $meta = wp_get_attachment_metadata($post->ID);
    $width = 0;
    $height = 0;
    $is_intermediate = false;
    $img_url_basename = wp_basename((string) $img_url);
    $intermediate = image_get_intermediate_size($post->ID, $size);
    if ($intermediate) {
        $img_url = str_replace($img_url_basename, $intermediate['file'], (string) $img_url);
        $width = (int) $intermediate['width'];
        $height = (int) $intermediate['height'];
        $is_intermediate = true;
    } elseif ($size === 'thumbnail') {
        $thumb = wp_get_attachment_thumb_url($post->ID);
        if ($thumb && ($info = @getimagesize(get_attached_file($post->ID)))) {
            $img_url = $thumb;
            $width = $info[0];
            $height = $info[1];
            $is_intermediate = true;
        }
    }
    if (!$width && !$height && is_array($meta) && isset($meta['width'], $meta['height'])) {
        $width = (int) $meta['width'];
        $height = (int) $meta['height'];
    }
    if ($img_url) {
        [$width, $height] = image_constrain_size_for_editor($width, $height, $size);
        return [$img_url, $width, $height, $is_intermediate];
    }
    return false;
}

function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail', $icon = false)
{
    $image = image_downsize((int) $attachment_id, $size);
    if (!$image && $icon) {
        $src = wp_mime_type_icon((int) $attachment_id);
        $image = $src ? [$src, 64, 64, false] : false;
    }
    return apply_filters('wp_get_attachment_image_src', $image, $attachment_id, $size, $icon);
}

function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail', $icon = false)
{
    $image = wp_get_attachment_image_src($attachment_id, $size, $icon);
    return $image ? $image[0] : false;
}

function wp_mime_type_icon($mime = 0, $preferred_ext = '.png')
{
    return false;
}

function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '')
{
    $attachment_id = (int) $attachment_id;
    $image = wp_get_attachment_image_src($attachment_id, $size, $icon);
    if (!$image) {
        return '';
    }
    [$src, $width, $height] = $image;
    $size_class = is_array($size) ? implode('x', $size) : $size;
    $attr = wp_parse_args($attr, ['src' => $src, 'class' => "attachment-{$size_class} size-{$size_class}", 'alt' => trim(strip_tags((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)))]);
    $attr = _minn_attachment_image_attributes($attachment_id, $attr, $src, (int) $width, (int) $height);
    $attr = apply_filters('wp_get_attachment_image_attributes', $attr, get_post($attachment_id), $size);
    $attr = array_map('esc_attr', array_filter($attr, static fn ($v) => $v !== false && $v !== null));
    $html = rtrim('<img ' . image_hwstring($width, $height));
    foreach ($attr as $name => $value) {
        $html .= " {$name}=\"{$value}\"";
    }
    return apply_filters('wp_get_attachment_image', $html . ' />', $attachment_id, $size, $icon, $attr);
}

/** @internal the loading attributes and the srcset/sizes pair an attachment image carries unless the caller set them */
function _minn_attachment_image_attributes(int $attachment_id, array $attr, string $src, int $width, int $height): array
{
    $loading = wp_get_loading_optimization_attributes('img', $attr + ['width' => $width, 'height' => $height], 'wp_get_attachment_image');
    foreach (['loading', 'fetchpriority'] as $key) {
        if (array_key_exists($key, $attr) && !$attr[$key]) {
            unset($loading[$key]);
        }
    }
    $attr = array_merge($attr, $loading);
    if (!empty($attr['srcset'])) {
        return $attr;
    }
    $meta = wp_get_attachment_metadata($attachment_id);
    if (!is_array($meta)) {
        return $attr;
    }
    $srcset = wp_calculate_image_srcset([$width, $height], $src, $meta, $attachment_id);
    $sizes = wp_calculate_image_sizes([$width, $height], $src, $meta, $attachment_id);
    if ($srcset && ($sizes || !empty($attr['sizes']))) {
        $attr['srcset'] = $srcset;
        $attr['sizes'] = empty($attr['sizes']) ? $sizes : $attr['sizes'];
    }
    return $attr;
}

function image_hwstring($width, $height)
{
    $out = '';
    if ($width) {
        $out .= 'width="' . (int) $width . '" ';
    }
    if ($height) {
        $out .= 'height="' . (int) $height . '" ';
    }
    return $out;
}

function wp_get_loading_optimization_attributes($tag_name, $attr, $context)
{
    $optimization = [];
    if ($tag_name !== 'img' && $tag_name !== 'iframe') {
        return $optimization;
    }
    if ($tag_name === 'img') {
        $optimization['decoding'] = 'async';
    }
    $explicitLoading = array_key_exists('loading', $attr) && !$attr['loading'];
    $explicitPriority = array_key_exists('fetchpriority', $attr) && $attr['fetchpriority'] === 'high';
    if ($explicitPriority) {
        // A caller that asks for high priority keeps it, and no later image competes.
        $optimization['fetchpriority'] = 'high';
        RenderState::current()->closePriority();
    } elseif ($explicitLoading) {
        if (RenderState::current()->claimPriority()) {
            $optimization['fetchpriority'] = 'high';
        }
    } elseif ($tag_name === 'img' && (RenderState::current()->depth() > 0 || (in_the_loop() && is_main_query())) && !(defined('REST_REQUEST') && REST_REQUEST) && RenderState::current()->nextImage() <= 3) {
        // Inside a page render the plugin's image shares the engine's budget:
        // three eager images, and the first one large enough to be worth the
        // network's attention takes high priority. A thumbnail or an avatar is
        // not, so the flag can fall to a later image.
        $pixels = (int) ($attr['width'] ?? 0) * (int) ($attr['height'] ?? 0);
        if ($pixels >= (int) apply_filters('wp_min_priority_img_pixels', 50000) && RenderState::current()->claimPriority()) {
            $optimization['fetchpriority'] = 'high';
        }
    } elseif (wp_lazy_loading_enabled($tag_name, $context)) {
        $optimization['loading'] = 'lazy';
    }
    return apply_filters('wp_get_loading_optimization_attributes', $optimization, $tag_name, $attr, $context);
}

function wp_high_priority_element_flag($value = null)
{
    // Only a real boolean moves the flag; anything else just reads it.
    if ($value === true) {
        RenderState::current()->reopenPriority();
    } elseif ($value === false) {
        RenderState::current()->closePriority();
    }
    return RenderState::current()->priorityAvailable();
}

function wp_lazy_loading_enabled($tag_name, $context)
{
    return apply_filters('wp_lazy_loading_enabled', $tag_name === 'img' || $tag_name === 'iframe', $tag_name, $context);
}

function wp_calculate_image_srcset($size_array, $image_src, $image_meta, $attachment_id = 0)
{
    $image_meta = apply_filters('wp_calculate_image_srcset_meta', $image_meta, $size_array, $image_src, $attachment_id);
    if (!is_array($image_meta)) {
        return false;
    }
    $sources = Sizing::sources([(int) ($size_array[0] ?? 0), (int) ($size_array[1] ?? 0)], (string) $image_src, $image_meta, static fn (int $sw, int $sh, int $tw, int $th): bool => (bool) wp_image_matches_ratio($sw, $sh, $tw, $th));
    if ($sources === []) {
        return false;
    }
    $sources = apply_filters('wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id);
    return Sizing::srcset(is_array($sources) ? $sources : []) ?? false;
}

function wp_image_matches_ratio($source_width, $source_height, $target_width, $target_height)
{
    if (!$source_width || !$source_height || !$target_width || !$target_height) {
        return false;
    }
    if (max($source_width, $source_height) > max($target_width, $target_height)) {
        $constrained = wp_constrain_dimensions($source_width, $source_height, $target_width, $target_height);
    } else {
        $constrained = wp_constrain_dimensions($target_width, $target_height, $source_width, $source_height);
    }
    $height_error = 1;
    return abs($constrained[0] - ($constrained[0] > $target_width ? $constrained[0] : $target_width)) <= 1 && abs($constrained[1] - $target_height) <= $height_error || (abs($constrained[0] - $source_width) <= 1 && abs($constrained[1] - $source_height) <= $height_error);
}

function wp_calculate_image_sizes($size, $image_src = null, $image_meta = null, $attachment_id = 0)
{
    $width = 0;
    if (is_array($size)) {
        $width = absint($size[0]);
    } elseif (is_string($size)) {
        if (!$image_meta && $attachment_id) {
            $image_meta = wp_get_attachment_metadata($attachment_id);
        }
        if (is_array($image_meta)) {
            $size_array = _wp_get_image_size_from_meta($size, $image_meta);
            if ($size_array) {
                $width = absint($size_array[0]);
            }
        }
    }
    if (!$width) {
        return false;
    }
    $sizes = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width);
    return apply_filters('wp_calculate_image_sizes', $sizes, $size, $image_src, $image_meta, $attachment_id);
}

function _wp_get_image_size_from_meta($size_name, $image_meta)
{
    if ($size_name === 'full') {
        return [absint($image_meta['width']), absint($image_meta['height'])];
    }
    if (!empty($image_meta['sizes'][$size_name])) {
        return [absint($image_meta['sizes'][$size_name]['width']), absint($image_meta['sizes'][$size_name]['height'])];
    }
    return false;
}

function wp_get_attachment_image_srcset($attachment_id, $size = 'medium', $image_meta = null)
{
    $image = wp_get_attachment_image_src((int) $attachment_id, $size);
    if (!$image) {
        return false;
    }
    $image_meta = $image_meta ?? wp_get_attachment_metadata((int) $attachment_id);
    if (!is_array($image_meta)) {
        return false;
    }
    return wp_calculate_image_srcset([absint($image[1]), absint($image[2])], $image[0], $image_meta, (int) $attachment_id);
}

function wp_get_attachment_image_sizes($attachment_id, $size = 'medium', $image_meta = null)
{
    $image = wp_get_attachment_image_src((int) $attachment_id, $size);
    if (!$image) {
        return false;
    }
    $image_meta = $image_meta ?? wp_get_attachment_metadata((int) $attachment_id);
    if (!is_array($image_meta)) {
        return false;
    }
    return wp_calculate_image_sizes([absint($image[1]), absint($image[2])], $image[0], $image_meta, (int) $attachment_id);
}

function wp_image_add_srcset_and_sizes($image, $image_meta, $attachment_id)
{
    return $image;
}

function wp_get_attachment_caption($post_id = 0)
{
    $post = get_post((int) $post_id ?: get_the_ID());
    if ($post === null || $post->post_type !== 'attachment') {
        return false;
    }
    return apply_filters('wp_get_attachment_caption', $post->post_excerpt, $post->ID);
}

function wp_get_attachment_link($post = 0, $size = 'thumbnail', $permalink = false, $icon = false, $text = false, $attr = '')
{
    $post = get_post($post);
    if ($post === null || $post->post_type !== 'attachment') {
        return 'Missing Attachment';
    }
    $url = wp_get_attachment_url($post->ID);
    if ($permalink) {
        $url = get_attachment_link($post->ID);
    }
    if ($text) {
        $link_text = $text;
    } elseif ($size && $size !== 'none') {
        $link_text = wp_get_attachment_image($post->ID, $size, $icon, $attr);
    } else {
        $link_text = '';
    }
    if (trim((string) $link_text) === '') {
        $link_text = $post->post_title;
    }
    if (trim((string) $link_text) === '') {
        $link_text = esc_html(pathinfo(get_attached_file($post->ID), PATHINFO_FILENAME));
    }
    $attributes = '';
    return apply_filters('wp_get_attachment_link', "<a href='" . esc_url($url) . "'{$attributes}>{$link_text}</a>", $post->ID, $size, $permalink, $icon, $text, $attr);
}

function attachment_url_to_postid($url)
{
    $dir = wp_get_upload_dir();
    $path = (string) $url;
    $site_url = parse_url($dir['url']);
    $image_path = parse_url($path);
    if (isset($image_path['scheme']) && isset($site_url['scheme']) && $image_path['scheme'] !== $site_url['scheme']) {
        $path = str_replace($image_path['scheme'], $site_url['scheme'], $path);
    }
    if (str_starts_with($path, $dir['baseurl'] . '/')) {
        $path = substr($path, strlen($dir['baseurl'] . '/'));
    }
    $id = (new PostLookup(Runtime::current()->db))->attachmentIdByFile($path);
    return (int) apply_filters('attachment_url_to_postid', $id ?? 0, $url);
}

function update_attached_file($attachment_id, $file)
{
    $post = get_post((int) $attachment_id);
    if ($post === null) {
        return false;
    }
    $file = apply_filters('update_attached_file', (string) $file, $post->ID);
    $file = _wp_relative_upload_path($file);
    if ($file !== '') {
        return update_post_meta($post->ID, '_wp_attached_file', $file);
    }
    return delete_post_meta($post->ID, '_wp_attached_file');
}

function _wp_relative_upload_path($path)
{
    $new_path = (string) $path;
    $uploads = wp_get_upload_dir();
    if (str_starts_with($new_path, $uploads['basedir'])) {
        $new_path = ltrim(str_replace($uploads['basedir'], '', $new_path), '/');
    }
    return apply_filters('_wp_relative_upload_path', $new_path, $path);
}

function wp_get_original_image_path($attachment_id, $unfiltered = false)
{
    $attachment_id = (int) $attachment_id;
    if (!wp_attachment_is_image($attachment_id)) {
        return false;
    }
    $image_meta = wp_get_attachment_metadata($attachment_id);
    $image_file = get_attached_file($attachment_id, $unfiltered);
    if (empty($image_meta['original_image'])) {
        $original_image = $image_file;
    } else {
        $original_image = path_join(dirname($image_file), $image_meta['original_image']);
    }
    return apply_filters('wp_get_original_image_path', $original_image, $attachment_id);
}

function wp_get_original_image_url($attachment_id)
{
    $attachment_id = (int) $attachment_id;
    if (!wp_attachment_is_image($attachment_id)) {
        return false;
    }
    $image_url = wp_get_attachment_url($attachment_id);
    $image_meta = wp_get_attachment_metadata($attachment_id);
    if (empty($image_meta['original_image'])) {
        $original_image_url = $image_url;
    } else {
        $original_image_url = path_join(dirname($image_url), $image_meta['original_image']);
    }
    return apply_filters('wp_get_original_image_url', $original_image_url, $attachment_id);
}

function file_is_valid_image($path)
{
    $size = @getimagesize((string) $path);
    return !empty($size);
}

function file_is_displayable_image($path)
{
    $info = @getimagesize((string) $path);
    if (empty($info)) {
        return false;
    }
    return in_array($info[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP, IMAGETYPE_ICO, IMAGETYPE_WEBP], true);
}

/**
 * An editor for the image: the type it will write, from
 * image_editor_output_format, then the first editor wp_image_editors names
 * that can read and write those types (Minn has GD), loaded.
 */
function wp_get_image_editor($path, $args = [])
{
    $args['path'] = $path;
    if (!isset($args['mime_type'])) {
        $args['mime_type'] = wp_check_filetype($path)['type'] ?: (wp_get_image_mime($path) ?: '');
    }
    $output = wp_get_image_editor_output_format($path, $args['mime_type']);
    if (isset($output[$args['mime_type']])) {
        $args['output_mime_type'] = $output[$args['mime_type']];
    }
    $implementation = _wp_image_editor_choose($args);
    if (!$implementation) {
        return new WP_Error('image_no_editor', 'No editor could be selected.');
    }
    $editor = new $implementation($path);
    $loaded = $editor->load();
    return is_wp_error($loaded) ? $loaded : $editor;
}

/** The types images are written in instead of their own: HEIC and HEIF as JPEG, and whatever image_editor_output_format adds. */
function wp_get_image_editor_output_format($filename, $mime_type)
{
    $default = ['image/heic' => 'image/jpeg', 'image/heif' => 'image/jpeg', 'image/heic-sequence' => 'image/jpeg', 'image/heif-sequence' => 'image/jpeg'];
    return apply_filters('image_editor_output_format', $default, $filename, $mime_type);
}

function wp_image_editor_supports($args = [])
{
    return WP_Image_Editor_GD::test($args);
}

function image_make_intermediate_size($file, $width, $height, $crop = false)
{
    if (!$width && !$height) {
        return false;
    }
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return false;
    }
    $resized = $editor->resize($width, $height, $crop);
    if (is_wp_error($resized)) {
        return false;
    }
    $saved = $editor->save();
    if (is_wp_error($saved)) {
        return false;
    }
    unset($saved['path']);
    return $saved;
}

/**
 * An upload's metadata and sizes in the reference's order (probe
 * image-pipeline): its own description (wp_read_image_metadata); an image
 * past big_image_size_threshold scaled down to it and saved "-scaled", one
 * taken turned saved upright "-rotated", one a plugin wants in another type
 * converted (each becomes the attached file, the upload kept as
 * original_image); the metadata stored; then each size, cut from the
 * upload, stored as it lands.
 */
function wp_create_image_subsizes($file, $attachment_id)
{
    $attachment_id = (int) $attachment_id;
    $imagesize = wp_getimagesize($file);
    if (empty($imagesize)) {
        return [];
    }
    $image_meta = ['width' => (int) $imagesize[0], 'height' => (int) $imagesize[1], 'file' => _wp_relative_upload_path($file), 'filesize' => (int) wp_filesize($file), 'sizes' => []];
    $exif_meta = wp_read_image_metadata($file);
    if ($exif_meta) {
        $image_meta['image_meta'] = $exif_meta;
    }
    $threshold = (int) apply_filters('big_image_size_threshold', 2560, $imagesize, $file, $attachment_id);
    if ($threshold && ($image_meta['width'] > $threshold || $image_meta['height'] > $threshold)) {
        $image_meta = _minn_image_scaled($file, $image_meta, $threshold, is_array($exif_meta), $attachment_id);
    } else {
        $image_meta = _minn_image_upright($file, $image_meta, (string) ($imagesize['mime'] ?? ''), $attachment_id);
    }
    wp_update_attachment_metadata($attachment_id, $image_meta);
    $new_sizes = apply_filters('intermediate_image_sizes_advanced', wp_get_registered_image_subsizes(), $image_meta, $attachment_id);
    return _wp_make_subsizes($new_sizes, $file, $image_meta, $attachment_id);
}

/** @internal a big upload scaled to the threshold (turned upright too), saved "-scaled" as the attached file */
function _minn_image_scaled(string $file, array $image_meta, int $threshold, bool $hasExif, int $attachment_id): array
{
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return $image_meta;
    }
    $resized = $editor->resize($threshold, $threshold);
    $rotated = null;
    if (!is_wp_error($resized) && $hasExif) {
        $resized = $rotated = $editor->maybe_exif_rotate();
    }
    if (is_wp_error($resized)) {
        return $image_meta;
    }
    $saved = $editor->save($editor->generate_filename('scaled'));
    if (is_wp_error($saved)) {
        return $image_meta;
    }
    $image_meta = _wp_image_meta_replace_original($saved, $file, $image_meta, $attachment_id);
    if ($rotated === true && !empty($image_meta['image_meta']['orientation'])) {
        $image_meta['image_meta']['orientation'] = 1;
    }
    return $image_meta;
}

/** @internal an upload taken turned saved upright ("-rotated"), or one a plugin wants in another type converted */
function _minn_image_upright(string $file, array $image_meta, string $mime, int $attachment_id): array
{
    $output = wp_get_image_editor_output_format($file, $mime);
    $convert = isset($output[$mime]) && $output[$mime] !== $mime;
    if (!$convert && (int) ($image_meta['image_meta']['orientation'] ?? 1) <= 1) {
        return $image_meta;
    }
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return $image_meta;
    }
    $rotated = $editor->maybe_exif_rotate();
    if ($rotated !== true && !$convert) {
        return $image_meta;
    }
    $saved = $editor->save($rotated === true ? $editor->generate_filename('rotated') : $file);
    if (is_wp_error($saved)) {
        return $image_meta;
    }
    $image_meta = _wp_image_meta_replace_original($saved, $file, $image_meta, $attachment_id);
    if ($rotated === true && !empty($image_meta['image_meta']['orientation'])) {
        $image_meta['image_meta']['orientation'] = 1;
    }
    return $image_meta;
}

/** The saved image as the attached file and the metadata's own, the upload kept by name as original_image. */
function _wp_image_meta_replace_original($saved_data, $original_file, $image_meta, $attachment_id)
{
    $new_file = (string) $saved_data['path'];
    update_attached_file((int) $attachment_id, $new_file);
    $image_meta['width'] = (int) $saved_data['width'];
    $image_meta['height'] = (int) $saved_data['height'];
    $image_meta['file'] = _wp_relative_upload_path($new_file);
    $image_meta['filesize'] = (int) ($saved_data['filesize'] ?? wp_filesize($new_file));
    $image_meta['original_image'] = wp_basename((string) $original_file);
    return $image_meta;
}

/**
 * The sizes the metadata lacks, cut from the upload (turned upright first
 * when it carries EXIF), medium and large first, the metadata stored after
 * each.
 */
function _wp_make_subsizes($new_sizes, $file, $image_meta, $attachment_id)
{
    if (empty($image_meta) || !is_array($image_meta)) {
        return [];
    }
    $new_sizes = array_diff_key((array) $new_sizes, (array) ($image_meta['sizes'] ?? []));
    $image_meta['sizes'] = (array) ($image_meta['sizes'] ?? []);
    if ($new_sizes === []) {
        return $image_meta;
    }
    $new_sizes = array_intersect_key($new_sizes, ['medium' => 1, 'large' => 1]) + $new_sizes;
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return $image_meta;
    }
    if (!empty($image_meta['image_meta'])) {
        $editor->maybe_exif_rotate();
    }
    foreach ($new_sizes as $new_size_name => $new_size_data) {
        $new_size_meta = $editor->make_subsize($new_size_data);
        if (!is_wp_error($new_size_meta)) {
            $image_meta['sizes'][$new_size_name] = $new_size_meta;
            wp_update_attachment_metadata((int) $attachment_id, $image_meta);
        }
    }
    return $image_meta;
}

function wp_generate_attachment_metadata($attachment_id, $file)
{
    $attachment_id = (int) $attachment_id;
    $attachment = get_post($attachment_id);
    $metadata = [];
    if (preg_match('!^image/!', (string) get_post_mime_type($attachment)) && file_is_displayable_image($file)) {
        $metadata = wp_create_image_subsizes($file, $attachment_id);
    }
    if (empty($metadata)) {
        $metadata = [];
    }
    return apply_filters('wp_generate_attachment_metadata', $metadata, $attachment_id, 'create');
}

/**
 * A photo's own description from its IPTC and EXIF (Media\PhotoMeta), through
 * wp_read_image_metadata_types and wp_read_image_metadata; false when there
 * is no such file.
 */
function wp_read_image_metadata($file)
{
    if (!file_exists((string) $file)) {
        return false;
    }
    $types = (array) apply_filters('wp_read_image_metadata_types', PhotoMeta::EXIF_TYPES);
    $read = PhotoMeta::read((string) $file, $types, static fn (string $text): string => (string) wp_kses_post($text));
    if ($read === null) {
        return false;
    }
    return apply_filters('wp_read_image_metadata', $read['meta'], $file, $read['type'], $read['iptc'], $read['exif']);
}

function wp_getimagesize($filename, &$image_info = null)
{
    $info = @getimagesize((string) $filename, $image_info);
    return $info === false ? false : $info;
}

function wp_insert_attachment($args, $file = false, $parent_post_id = 0, $wp_error = false, $fire_after_hooks = true)
{
    $defaults = ['file' => $file, 'post_parent' => 0];
    $data = wp_parse_args($args, $defaults);
    if (!empty($parent_post_id)) {
        $data['post_parent'] = (int) $parent_post_id;
    }
    $data['post_type'] = 'attachment';
    if (empty($data['post_status']) || $data['post_status'] !== 'trash') {
        $data['post_status'] = 'inherit';
    }
    $id = wp_insert_post($data, $wp_error, $fire_after_hooks);
    if (is_int($id) && $id > 0 && $file) {
        update_attached_file($id, $file);
    }
    return $id;
}

function wp_delete_attachment($post_id, $force_delete = false)
{
    $post = get_post((int) $post_id);
    if ($post === null || $post->post_type !== 'attachment') {
        return $post;
    }
    if (!$force_delete && EMPTY_TRASH_DAYS && MEDIA_TRASH && $post->post_status !== 'trash') {
        return wp_trash_post($post->ID);
    }
    $check = apply_filters('pre_delete_attachment', null, $post, $force_delete);
    if ($check !== null) {
        return $check;
    }
    $meta = wp_get_attachment_metadata($post->ID);
    $backup_sizes = get_post_meta($post->ID, '_wp_attachment_backup_sizes', true);
    $file = get_attached_file($post->ID);
    do_action('delete_attachment', $post->ID, $post);
    // As the reference removes it: its terms, its comments, its meta row by row, then the row between delete_post and deleted_post.
    wp_delete_object_term_relationships($post->ID, get_object_taxonomies($post->post_type));
    foreach (_minn_comments()->idsOf($post->ID) as $comment_id) {
        wp_delete_comment($comment_id, true);
    }
    _minn_post_delete_meta($post->ID);
    do_action('delete_post', $post->ID, $post);
    _minn_post_writer()->destroy($post->ID);
    do_action('deleted_post', $post->ID, $post);
    clean_post_cache($post);
    wp_delete_attachment_files($post->ID, $meta, $backup_sizes, $file);
    return $post;
}

function wp_delete_attachment_files($post_id, $meta, $backup_sizes, $file)
{
    foreach (Uploads::attachmentFiles((string) $file, is_array($meta) ? $meta : [], is_array($backup_sizes) ? $backup_sizes : null) as $path) {
        wp_delete_file($path);
    }
    return true;
}

function wp_delete_file($file)
{
    $delete = apply_filters('wp_delete_file', (string) $file);
    if ($delete !== '' && is_file($delete)) {
        return @unlink($delete);
    }
    return false;
}

function wp_delete_file_from_directory($file, $directory)
{
    $real_file = realpath(wp_normalize_path((string) $file));
    $real_directory = realpath(wp_normalize_path((string) $directory));
    if ($real_file === false || $real_directory === false || !str_starts_with(wp_normalize_path($real_file), trailingslashit(wp_normalize_path($real_directory)))) {
        return false;
    }
    wp_delete_file($file);
    return true;
}

function get_site_icon_url($size = 512, $url = '', $blog_id = 0)
{
    $site_icon_id = (int) get_option('site_icon');
    if ($site_icon_id > 0) {
        $url_data = wp_get_attachment_image_src($site_icon_id, [$size, $size]);
        if ($url_data) {
            $url = $url_data[0];
        }
    }
    return apply_filters('get_site_icon_url', $url, $size, $blog_id);
}

function has_site_icon($blog_id = 0)
{
    return (bool) get_site_icon_url(512, '', $blog_id);
}

function site_icon_url($size = 512, $url = '', $blog_id = 0)
{
    echo esc_url(get_site_icon_url($size, $url, $blog_id));
}

function get_the_post_thumbnail($post = null, $size = 'post-thumbnail', $attr = '')
{
    $post = get_post($post);
    if ($post === null) {
        return '';
    }
    $post_thumbnail_id = get_post_thumbnail_id($post);
    $size = apply_filters('post_thumbnail_size', $size, $post->ID);
    if ($post_thumbnail_id) {
        do_action('begin_fetch_post_thumbnail_html', $post->ID, $post_thumbnail_id, $size);
        $html = wp_get_attachment_image($post_thumbnail_id, $size, false, $attr);
        do_action('end_fetch_post_thumbnail_html', $post->ID, $post_thumbnail_id, $size);
    } else {
        $html = '';
    }
    return apply_filters('post_thumbnail_html', $html, $post->ID, $post_thumbnail_id, $size, $attr);
}

function the_post_thumbnail($size = 'post-thumbnail', $attr = '')
{
    echo get_the_post_thumbnail(null, $size, $attr);
}

function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
{
    $post_thumbnail_id = get_post_thumbnail_id($post);
    if (!$post_thumbnail_id) {
        return false;
    }
    $url = wp_get_attachment_image_url($post_thumbnail_id, $size);
    return apply_filters('post_thumbnail_url', $url, $post, $size);
}

function the_post_thumbnail_url($size = 'post-thumbnail')
{
    $url = get_the_post_thumbnail_url(null, $size);
    if ($url) {
        echo esc_url($url);
    }
}

function set_post_thumbnail($post, $thumbnail_id)
{
    $post = get_post($post);
    $thumbnail_id = absint($thumbnail_id);
    if ($post && $thumbnail_id && get_post($thumbnail_id)) {
        if (wp_get_attachment_image($thumbnail_id, 'thumbnail')) {
            return update_post_meta($post->ID, '_thumbnail_id', $thumbnail_id);
        }
        return delete_post_meta($post->ID, '_thumbnail_id');
    }
    return false;
}

function delete_post_thumbnail($post)
{
    $post = get_post($post);
    return $post ? delete_post_meta($post->ID, '_thumbnail_id') : false;
}

function wp_prepare_attachment_for_js($attachment)
{
    $attachment = get_post($attachment);
    if ($attachment === null || $attachment->post_type !== 'attachment') {
        return null;
    }
    $meta = wp_get_attachment_metadata($attachment->ID);
    [$type, $subtype] = str_contains($attachment->post_mime_type, '/') ? explode('/', $attachment->post_mime_type, 2) : [$attachment->post_mime_type, ''];
    $attachment_url = (string) wp_get_attachment_url($attachment->ID);
    $response = _minn_attachment_js_fields($attachment, $type, $subtype, $attachment_url);
    $author = get_userdata((int) $attachment->post_author);
    $response['authorName'] = $author ? $author->display_name : '(no author)';
    $file = get_attached_file($attachment->ID);
    $response['filesizeInBytes'] = is_array($meta) && isset($meta['filesize']) ? $meta['filesize'] : (is_file($file) ? filesize($file) : 0);
    $response['filesizeHumanReadable'] = size_format($response['filesizeInBytes']);
    $response['context'] = '';
    if (is_array($meta) && isset($meta['width'], $meta['height'])) {
        $response['height'] = $meta['height'];
        $response['width'] = $meta['width'];
        $response['orientation'] = $meta['height'] > $meta['width'] ? 'portrait' : 'landscape';
    }
    if ($type === 'image') {
        $names = apply_filters('image_size_names_choose', ['thumbnail' => 'Thumbnail', 'medium' => 'Medium', 'large' => 'Large', 'full' => 'Full Size']);
        $response['sizes'] = Sizing::editorSizes(is_array($meta) ? $meta : [], str_replace(wp_basename($attachment_url), '', $attachment_url), $attachment_url, $names, static fn (string $size) => image_downsize($attachment->ID, $size));
    }
    $response['compat'] = ['item' => '', 'meta' => ''];
    return apply_filters('wp_prepare_attachment_for_js', $response, $attachment, $meta);
}

/** @internal the plain fields of the media modal's attachment model */
function _minn_attachment_js_fields(WP_Post $attachment, string $type, string $subtype, string $url): array
{
    return [
        'id' => $attachment->ID,
        'title' => $attachment->post_title,
        'filename' => wp_basename((string) get_attached_file($attachment->ID)),
        'url' => $url,
        'link' => get_attachment_link($attachment->ID),
        'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
        'author' => $attachment->post_author,
        'description' => $attachment->post_content,
        'caption' => $attachment->post_excerpt,
        'name' => $attachment->post_name,
        'status' => $attachment->post_status,
        'uploadedTo' => $attachment->post_parent,
        'date' => strtotime($attachment->post_date_gmt) * 1000,
        'modified' => strtotime($attachment->post_modified_gmt) * 1000,
        'menuOrder' => $attachment->menu_order,
        'mime' => $attachment->post_mime_type,
        'type' => $type,
        'subtype' => $subtype,
        'icon' => wp_mime_type_icon($attachment->ID),
        'dateFormatted' => mysql2date(get_option('date_format'), $attachment->post_date),
        'nonces' => ['update' => false, 'delete' => false, 'edit' => false],
        'editLink' => false,
        'meta' => false,
    ];
}

/** A form's uploaded file, taken as the reference takes one (Minn\Runtime\FileUpload): the stored file, url and type, or an error. */
function wp_handle_upload(&$file, $overrides = false, $time = null)
{
    $file = (array) $file;
    return \Minn\Runtime\FileUpload::handle($file, is_array($overrides) ? $overrides : false, $time === null ? null : (string) $time, 'wp_handle_upload');
}

/** A file from elsewhere (a download, a request body), taken as the reference takes one (Minn\Runtime\FileUpload). */
function wp_handle_sideload(&$file, $overrides = false, $time = null)
{
    $file = (array) $file;
    return \Minn\Runtime\FileUpload::handle($file, is_array($overrides) ? $overrides : false, $time === null ? null : (string) $time, 'wp_handle_sideload');
}

function media_handle_sideload($file_array, $post_id = 0, $desc = null, $post_data = [])
{
    $file = wp_handle_sideload($file_array);
    if (isset($file['error'])) {
        return new WP_Error('upload_error', $file['error']);
    }
    $title = $desc ?? preg_replace('/\.[^.]+$/', '', wp_basename($file['file']));
    $attachment = array_merge(['post_mime_type' => $file['type'], 'guid' => $file['url'], 'post_parent' => $post_id, 'post_title' => $title, 'post_content' => ''], (array) $post_data);
    $id = wp_insert_attachment($attachment, $file['file'], $post_id, true);
    if (!is_wp_error($id)) {
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file['file']));
    }
    return $id;
}

function media_handle_upload($file_id, $post_id, $post_data = [], $overrides = ['test_form' => false])
{
    $files = Runtime::current()->request?->files[$file_id] ?? null;
    if ($files === null) {
        return new WP_Error('upload_error', 'No file was uploaded.');
    }
    return media_handle_sideload($files, $post_id, null, $post_data);
}

function media_sideload_image($file, $post_id = 0, $desc = null, $return_type = 'html')
{
    $download = download_url((string) $file);
    if (is_wp_error($download)) {
        return $download;
    }
    $file_array = ['name' => wp_basename((string) parse_url((string) $file, PHP_URL_PATH)), 'tmp_name' => $download];
    $id = media_handle_sideload($file_array, $post_id, $desc);
    if (is_wp_error($id)) {
        @unlink($download);
        return $id;
    }
    if ($return_type === 'id') {
        return $id;
    }
    $src = wp_get_attachment_url($id);
    if ($return_type === 'src') {
        return $src;
    }
    return '<img src="' . esc_url($src) . '" alt="' . esc_attr((string) $desc) . '" />';
}

function wp_get_attachment_id3_keys($attachment, $context = 'display')
{
    return [];
}

function wp_get_media_creation_timestamp($metadata)
{
    return false;
}

function _wp_get_attachment_relative_path($file)
{
    $dirname = dirname((string) $file);
    if ($dirname === '.') {
        return '';
    }
    if (str_contains($dirname, 'wp-content/uploads')) {
        $dirname = ltrim(substr($dirname, strpos($dirname, 'wp-content/uploads') + 18), '/');
    }
    return $dirname;
}

/** The width and height a source URL has in the attachment metadata, matched by file name. */
function wp_image_src_get_dimensions($image_src, $image_meta, $attachment_id = 0)
{
    $dimensions = false;
    if (!is_array($image_meta) || !isset($image_meta['file']) || strlen((string) $image_meta['file']) < 4) {
        return apply_filters('wp_image_src_get_dimensions', $dimensions, $image_src, $image_meta, $attachment_id);
    }
    $image_src = str_replace('https://', 'http://', (string) $image_src);
    $image_basename = wp_basename($image_src);
    if (wp_basename($image_meta['file']) === $image_basename) {
        $dimensions = [(int) $image_meta['width'], (int) $image_meta['height']];
    } elseif (!empty($image_meta['sizes'])) {
        foreach ($image_meta['sizes'] as $image_size_data) {
            if ($image_basename === ($image_size_data['file'] ?? null)) {
                $dimensions = [(int) $image_size_data['width'], (int) $image_size_data['height']];
                break;
            }
        }
    }
    return apply_filters('wp_image_src_get_dimensions', $dimensions, $image_src, $image_meta, $attachment_id);
}

function _wp_image_editor_choose($args = [])
{
    $implementations = apply_filters('wp_image_editors', ['WP_Image_Editor_Imagick', 'WP_Image_Editor_GD']);
    $editors = wp_cache_get('wp_image_editor_choose', 'image_editor');
    if (!is_array($editors)) {
        $editors = [];
    }
    $cache_key = md5(serialize($implementations));
    foreach ($implementations as $implementation) {
        if (!class_exists($implementation) || !call_user_func([$implementation, 'test'], $args)) {
            continue;
        }
        if (isset($args['mime_type']) && !call_user_func([$implementation, 'supports_mime_type'], $args['mime_type'])) {
            continue;
        }
        if (isset($args['methods']) && array_diff($args['methods'], get_class_methods($implementation))) {
            continue;
        }
        return $implementation;
    }
    return false;
}

/** How many images print eagerly before lazy loading starts: the reference's three, or a filter's number. */
function wp_omit_loading_attr_threshold($force = false)
{
    static $threshold = null;
    if ($threshold === null || $force) {
        $threshold = (int) apply_filters('wp_omit_loading_attr_threshold', 3);
    }
    return $threshold;
}

function wp_match_mime_types($wildcard_mime_types, $real_mime_types)
{
    $patterns = is_array($wildcard_mime_types) ? $wildcard_mime_types : array_map('trim', explode(',', (string) $wildcard_mime_types));
    return \Minn\Media\Kind::matchWildcards(array_map('strval', $patterns), array_map('strval', (array) $real_mime_types));
}

/** Streams an image editor's current image at the requested type; anything else has nothing to stream. */
function wp_stream_image($image, $mime_type, $attachment_id)
{
    if ($image instanceof WP_Image_Editor) {
        $image = apply_filters('image_editor_save_pre', $image, $attachment_id);
        return !is_wp_error($image->stream($mime_type));
    }
    return false;
}

/** The media-modal compat slot: both members are strings, empty for an ordinary attachment (probed). */
function get_compat_media_markup($attachment_id, $args = null)
{
    $args = wp_parse_args((array) $args, ['in_modal' => false]);
    $form_fields = apply_filters('attachment_fields_to_edit', [], get_post((int) $attachment_id));
    $item = '';
    foreach ((array) $form_fields as $field) {
        $item .= is_array($field) ? (string) ($field['tr'] ?? '') : '';
    }
    return ['item' => $item, 'meta' => ''];
}

/** @internal shared per-request instance counter for the av shortcodes */
function _minn_av_instance(string $kind): int
{
    $key = 'av_instance_' . $kind;
    $n = (int) (Runtime::current()->get($key) ?? 0) + 1;
    Runtime::current()->set($key, $n);
    return $n;
}

/** @internal a truthy shortcode flag (on, true, 1, or a real true) prints as a bare boolean attribute */
function _minn_av_flags(array $attr, array $names): string
{
    $out = '';
    foreach ($names as $name) {
        $value = $attr[$name] ?? '';
        if ($value === true || in_array(strtolower((string) $value), ['on', 'true', '1'], true)) {
            $out .= ' ' . $name;
        }
    }
    return $out;
}

/** @internal the post an av player belongs to, 0 outside a post */
function _minn_av_post_id(): int
{
    $post = get_post();
    return $post instanceof WP_Post ? (int) $post->ID : 0;
}

/** @internal the source type of an av URL: a hosted video's provider, else its file type */
function _minn_av_source_type(string $src, string $fallback): string
{
    if (preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch|youtu\.be/)#', $src)) {
        return 'video/youtube';
    }
    if (preg_match('#^https?://(.+\.)?vimeo\.com/#', $src)) {
        return 'video/vimeo';
    }
    return wp_check_filetype($src)['type'] ?: $fallback;
}

/** The captured player markup: source with a cache-busting query, the bare link as fallback. */
function wp_audio_shortcode($attr, $content = '')
{
    $attr = shortcode_atts(['src' => '', 'loop' => '', 'autoplay' => '', 'preload' => 'none', 'class' => 'wp-audio-shortcode', 'style' => 'width: 100%;'], (array) $attr, 'audio');
    $src = (string) $attr['src'];
    if ($src === '') {
        return null;
    }
    $n = _minn_av_instance('audio');
    $html = '<audio class="' . esc_attr($attr['class']) . '" id="audio-' . _minn_av_post_id() . '-' . $n . '"' . _minn_av_flags($attr, ['loop', 'autoplay']) . ' preload="' . esc_attr($attr['preload']) . '" style="' . esc_attr($attr['style']) . '" controls="controls">'
        . '<source type="' . esc_attr(_minn_av_source_type($src, 'audio/mpeg')) . '" src="' . esc_url(add_query_arg('_', $n, $src)) . '" />'
        . '<a href="' . esc_url($src) . '">' . esc_html($src) . '</a></audio>';
    return apply_filters('wp_audio_shortcode', $html, $attr, '', $n, '');
}

/** The captured video markup: sized wrapper div, source with the cache buster, link fallback. */
function wp_video_shortcode($attr, $content = '')
{
    $attr = shortcode_atts(['src' => '', 'poster' => '', 'width' => 640, 'height' => 360, 'loop' => '', 'autoplay' => '', 'muted' => '', 'preload' => 'metadata', 'class' => 'wp-video-shortcode'], (array) $attr, 'video');
    $src = (string) $attr['src'];
    if ($src === '') {
        return null;
    }
    $n = _minn_av_instance('video');
    $poster = $attr['poster'] !== '' ? ' poster="' . esc_url((string) $attr['poster']) . '"' : '';
    $html = '<div style="width: ' . (int) $attr['width'] . 'px;" class="wp-video">'
        . '<video class="' . esc_attr($attr['class']) . '" id="video-' . _minn_av_post_id() . '-' . $n . '" width="' . (int) $attr['width'] . '" height="' . (int) $attr['height'] . '"' . $poster . _minn_av_flags($attr, ['loop', 'autoplay', 'muted']) . ' preload="' . esc_attr($attr['preload']) . '" controls="controls">'
        . '<source type="' . esc_attr(_minn_av_source_type($src, 'video/mp4')) . '" src="' . esc_url(add_query_arg('_', $n, $src)) . '" />'
        . '<a href="' . esc_url($src) . '">' . esc_html($src) . '</a></video></div>';
    return apply_filters('wp_video_shortcode', $html, $attr, '', $n, '');
}

/** Registered sizes the attachment's metadata lacks and its dimensions can fit (empty for the battery attachment, probed). */
function wp_get_missing_image_subsizes($attachment_id)
{
    $meta = wp_get_attachment_metadata((int) $attachment_id);
    if (!is_array($meta) || empty($meta['width']) || empty($meta['height'])) {
        return [];
    }
    $have = array_keys((array) ($meta['sizes'] ?? []));
    $missing = [];
    foreach (_minn_image_sizes() as $name => $size) {
        $fits = (int) $size['width'] < (int) $meta['width'] || (int) $size['height'] < (int) $meta['height'];
        if (!in_array($name, $have, true) && $fits && (int) $size['width'] > 0) {
            $missing[$name] = $size;
        }
    }
    return apply_filters('wp_get_missing_image_subsizes', $missing, $meta, (int) $attachment_id);
}

function get_the_post_thumbnail_caption($post = null)
{
    $id = get_post_thumbnail_id($post);
    $caption = $id ? (string) wp_get_attachment_caption($id) : '';
    return $caption;
}

function the_post_thumbnail_caption($post = null)
{
    echo apply_filters('the_post_thumbnail_caption', get_the_post_thumbnail_caption($post));
}

function gallery_shortcode($attr)
{
    $post = get_post();
    $postId = $post ? (int) $post->ID : 0;
    $instance = _minn_av_instance('gallery');
    // A caller's ids ARE the include list, and they set the order unless the
    // caller asked for another one.
    $attr = (array) $attr;
    if (!empty($attr['ids'])) {
        if (empty($attr['orderby'])) {
            $attr['orderby'] = 'post__in';
        }
        $attr['include'] = $attr['ids'];
    }
    $atts = shortcode_atts([
        'order' => 'ASC',
        'orderby' => 'menu_order ID',
        'id' => $postId,
        'itemtag' => 'figure',
        'icontag' => 'div',
        'captiontag' => 'figcaption',
        'columns' => 3,
        'size' => 'thumbnail',
        'include' => '',
        'exclude' => '',
        'link' => '',
    ], $attr, 'gallery');
    $short = apply_filters('post_gallery', '', $attr, $instance);
    if ($short !== '') {
        return $short;
    }
    $items = [];
    foreach (_minn_gallery_attachments($atts) as $attachment) {
        $items[] = _minn_gallery_item($attachment, $atts);
    }
    return Minn\Media\Gallery::render($items, [
        'itemtag' => (string) $atts['itemtag'],
        'icontag' => (string) $atts['icontag'],
        'captiontag' => (string) $atts['captiontag'],
        'columns' => (int) $atts['columns'],
        'size' => (string) $atts['size'],
    ], $instance, (int) $atts['id']);
}

/** @internal the attachments a gallery names, in the order it asked for */
function _minn_gallery_attachments(array $atts): array
{
    $shared = ['post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $atts['order'], 'orderby' => $atts['orderby']];
    $include = is_array($atts['include'] ?? '') ? implode(',', $atts['include']) : (string) ($atts['include'] ?? '');
    if ($include !== '') {
        return get_posts(['include' => $include] + $shared);
    }
    $query = ['post_parent' => (int) $atts['id']] + $shared;
    if ((string) ($atts['exclude'] ?? '') !== '') {
        $query['exclude'] = $atts['exclude'];
    }
    return get_children($query) ?: [];
}

/** @internal one gallery cell: the image, how it is linked, and its caption */
function _minn_gallery_item($attachment, array $atts): array
{
    $id = (int) $attachment->ID;
    $size = (string) $atts['size'];
    $link = (string) $atts['link'];
    $icon = match ($link) {
        'none' => wp_get_attachment_image($id, $size, false, ['aria-describedby' => null]),
        'file' => wp_get_attachment_link($id, $size, false, false),
        default => wp_get_attachment_link($id, $size, true, false),
    };
    $meta = wp_get_attachment_metadata($id);
    $width = (int) ($meta['width'] ?? 0);
    $height = (int) ($meta['height'] ?? 0);
    $caption = trim((string) $attachment->post_excerpt);
    return [
        'icon' => $icon,
        'orientation' => $height > $width ? 'portrait' : 'landscape',
        'caption' => $caption === '' ? '' : wptexturize($caption),
        'caption_id' => 'gallery-' . _minn_av_instance('gallery_caption') . '-' . $id,
    ];
}

function get_post_gallery($post = 0, $html = true)
{
    // An empty result stays false: the reference hands that back untouched.
    $galleries = (array) get_post_galleries($post, $html);
    return apply_filters('get_post_gallery', reset($galleries), $post, $galleries);
}

function get_adjacent_image_link($prev = true, $size = 'thumbnail', $text = false)
{
    $post = get_post();
    $attachments = $post ? array_values((array) get_children(['post_parent' => (int) $post->post_parent, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'orderby' => 'menu_order ID'])) : [];
    $at = null;
    foreach ($attachments as $index => $attachment) {
        if ((int) $attachment->ID === (int) ($post->ID ?? 0)) {
            $at = $index;
            break;
        }
    }
    $neighbour = $at === null ? null : ($attachments[$prev ? $at - 1 : $at + 1] ?? null);
    $output = $neighbour === null
        ? ''
        : wp_get_attachment_link((int) $neighbour->ID, $size, true, false, $text);
    return apply_filters($prev ? 'previous_image_link' : 'next_image_link', $output);
}

function previous_image_link($size = 'thumbnail', $text = false)
{
    echo get_adjacent_image_link(true, $size, $text);
}

function next_image_link($size = 'thumbnail', $text = false)
{
    echo get_adjacent_image_link(false, $size, $text);
}

function adjacent_image_link($prev = true, $size = 'thumbnail', $text = false)
{
    echo get_adjacent_image_link($prev, $size, $text);
}

function img_caption_shortcode($attr, $content = '')
{
    if (!isset($attr['caption']) && preg_match('#((?:<a [^>]+>\s*)?<img [^>]+>(?:\s*</a>)?)(.*)#is', (string) $content, $m)) {
        $content = $m[1];
        $attr['caption'] = trim($m[2]);
    }
    $output = apply_filters('img_caption_shortcode', '', $attr, $content);
    if ($output !== '') {
        return $output;
    }
    $atts = shortcode_atts(['id' => '', 'caption_id' => '', 'align' => 'alignnone', 'width' => '', 'caption' => '', 'class' => ''], $attr, 'caption');
    $atts['width'] = (int) $atts['width'];
    if ($atts['width'] < 1 || trim((string) $atts['caption']) === '') {
        return $content;
    }
    $captionId = $atts['caption_id'] !== '' ? $atts['caption_id'] : ($atts['id'] !== '' ? 'caption-' . str_replace('_', '-', (string) $atts['id']) : '');
    $id = $atts['id'] !== '' ? 'id="' . esc_attr(sanitize_html_class((string) $atts['id'])) . '" ' : '';
    $describedBy = $captionId !== '' ? 'aria-describedby="' . esc_attr($captionId) . '" ' : '';
    $captionIdAttr = $captionId !== '' ? 'id="' . esc_attr($captionId) . '" ' : '';
    $class = trim('wp-caption ' . $atts['align'] . ' ' . $atts['class']);
    $html5 = current_theme_supports('html5', 'caption');
    $width = (int) apply_filters('img_caption_shortcode_width', $html5 ? $atts['width'] : 10 + $atts['width'], $atts, $content);
    $style = $width > 0 ? 'style="width: ' . $width . 'px" ' : '';
    if ($html5) {
        return '<figure ' . $id . $describedBy . $style . 'class="' . esc_attr($class) . '">' . do_shortcode($content) . '<figcaption ' . $captionIdAttr . 'class="wp-caption-text">' . $atts['caption'] . '</figcaption></figure>';
    }
    return '<div ' . $id . $style . 'class="' . esc_attr($class) . '">' . do_shortcode($content) . '<p ' . $captionIdAttr . 'class="wp-caption-text">' . $atts['caption'] . '</p></div>';
}

/** Every image and iframe of content as the reference fits them out: sizes, srcset, decoding, and loading by the page's budget. */
function wp_filter_content_tags($content, $context = null)
{
    return Minn\Content\Blocks::renderer()->images()->content((string) $content);
}
