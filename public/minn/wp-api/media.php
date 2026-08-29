<?php
/** Attachments, image sizes, and the media helpers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Media\Metadata;
use Minn\Blocks\RenderState;
use Minn\Runtime\Runtime;
use Minn\Runtime\PostLookup;

/** @internal the registered sizes: the four from the options plus the two big ones, plus add_image_size */
function _minn_image_sizes(): array
{
    $sizes = [
        'thumbnail' => ['width' => (int) get_option('thumbnail_size_w'), 'height' => (int) get_option('thumbnail_size_h'), 'crop' => (bool) get_option('thumbnail_crop')],
        'medium' => ['width' => (int) get_option('medium_size_w'), 'height' => (int) get_option('medium_size_h'), 'crop' => false],
        'medium_large' => ['width' => (int) get_option('medium_large_size_w'), 'height' => (int) get_option('medium_large_size_h'), 'crop' => false],
        'large' => ['width' => (int) get_option('large_size_w'), 'height' => (int) get_option('large_size_h'), 'crop' => false],
        '1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false],
        '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false],
    ];
    foreach ((array) Runtime::current()->get('image_sizes', []) as $name => $size) {
        $sizes[$name] = $size;
    }
    return $sizes;
}

function add_image_size($name, $width = 0, $height = 0, $crop = false)
{
    $sizes = Runtime::current()->get('image_sizes', []);
    $sizes[(string) $name] = ['width' => absint($width), 'height' => absint($height), 'crop' => $crop];
    Runtime::current()->set('image_sizes', $sizes);
}

function has_image_size($name)
{
    return isset(_minn_image_sizes()[$name]);
}

function remove_image_size($name)
{
    $sizes = Runtime::current()->get('image_sizes', []);
    if (!isset($sizes[$name])) {
        return false;
    }
    unset($sizes[$name]);
    Runtime::current()->set('image_sizes', $sizes);
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
        $out[$name] = ['width' => (int) $size['width'], 'height' => (int) $size['height'], 'crop' => (bool) $size['crop']];
    }
    return apply_filters('wp_get_registered_image_subsizes', $out);
}

function wp_constrain_dimensions($current_width, $current_height, $max_width = 0, $max_height = 0)
{
    $current_width = (int) $current_width;
    $current_height = (int) $current_height;
    $max_width = (int) $max_width;
    $max_height = (int) $max_height;
    if (!$max_width && !$max_height) {
        return [$current_width, $current_height];
    }
    $width_ratio = 1.0;
    $height_ratio = 1.0;
    $did_width = false;
    $did_height = false;
    if ($max_width > 0 && $current_width > 0 && $current_width > $max_width) {
        $width_ratio = $max_width / $current_width;
        $did_width = true;
    }
    if ($max_height > 0 && $current_height > 0 && $current_height > $max_height) {
        $height_ratio = $max_height / $current_height;
        $did_height = true;
    }
    $smaller_ratio = min($width_ratio, $height_ratio);
    $w = max(1, (int) round($current_width * $smaller_ratio));
    $h = max(1, (int) round($current_height * $smaller_ratio));
    if ($did_width && $w === $max_width - 1) {
        $w = $max_width;
    }
    if ($did_height && $h === $max_height - 1) {
        $h = $max_height;
    }
    return apply_filters('wp_constrain_dimensions', [$w, $h], $current_width, $current_height, $max_width, $max_height);
}

function image_resize_dimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop = false)
{
    $orig_w = (int) $orig_w;
    $orig_h = (int) $orig_h;
    $dest_w = (int) $dest_w;
    $dest_h = (int) $dest_h;
    if ($orig_w <= 0 || $orig_h <= 0 || ($dest_w <= 0 && $dest_h <= 0)) {
        return false;
    }
    $output = apply_filters('image_resize_dimensions', null, $orig_w, $orig_h, $dest_w, $dest_h, $crop);
    if ($output !== null) {
        return $output;
    }
    if ($orig_w < $dest_w && $orig_h < $dest_h) {
        return false;
    }
    if ($crop) {
        $aspect_ratio = $orig_w / $orig_h;
        $new_w = min($dest_w, $orig_w);
        $new_h = min($dest_h, $orig_h);
        if (!$new_w) {
            $new_w = (int) round($new_h * $aspect_ratio);
        }
        if (!$new_h) {
            $new_h = (int) round($new_w / $aspect_ratio);
        }
        $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);
        $crop_w = (int) round($new_w / $size_ratio);
        $crop_h = (int) round($new_h / $size_ratio);
        $s_x = (int) floor(($orig_w - $crop_w) / 2);
        $s_y = (int) floor(($orig_h - $crop_h) / 2);
    } else {
        $crop_w = $orig_w;
        $crop_h = $orig_h;
        $s_x = 0;
        $s_y = 0;
        [$new_w, $new_h] = wp_constrain_dimensions($orig_w, $orig_h, $dest_w, $dest_h);
    }
    if ($new_w >= $orig_w && $new_h >= $orig_h && $dest_w !== $orig_w && $dest_h !== $orig_h) {
        return false;
    }
    if ($new_w === $orig_w && $new_h === $orig_h) {
        return false;
    }
    return [0, 0, $s_x, $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h];
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
    if ($post === null) {
        return false;
    }
    $file = get_attached_file($post->ID);
    if (!$file) {
        return false;
    }
    if (str_starts_with((string) $post->post_mime_type, $type . '/')) {
        return true;
    }
    $check = wp_check_filetype($file);
    if (empty($check['ext'])) {
        return false;
    }
    $ext = strtolower((string) $check['ext']);
    if ($post->post_mime_type === 'import' || $post->post_mime_type === '') {
        // fall through to the extension check
    }
    return match ($type) {
        'image' => in_array($ext, ['jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp', 'avif', 'heic'], true),
        'audio' => in_array($ext, wp_get_audio_extensions(), true),
        'video' => in_array($ext, wp_get_video_extensions(), true),
        default => $type === $ext,
    };
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
    if (!is_array($meta) || empty($meta['sizes']) || !$size) {
        return false;
    }
    $data = [];
    if (is_array($size)) {
        $candidates = [];
        if (!isset($meta['file']) && isset($meta['sizes']['full'])) {
            $meta['height'] = $meta['sizes']['full']['height'];
            $meta['width'] = $meta['sizes']['full']['width'];
        }
        foreach ($meta['sizes'] as $name => $row) {
            if (!empty($meta['width']) && !empty($meta['height']) && (int) $row['width'] === (int) $meta['width'] && (int) $row['height'] === (int) $meta['height']) {
                continue;
            }
            if ($row['width'] >= $size[0] && $row['height'] >= $size[1]) {
                $candidates[$row['width'] * $row['height']] = $row;
                if ((int) $row['width'] === (int) $size[0] && (int) $row['height'] === (int) $size[1]) {
                    break;
                }
            }
        }
        if ($candidates !== []) {
            ksort($candidates);
            $data = array_shift($candidates);
        } elseif (!empty($meta['sizes']['thumbnail']) && $meta['sizes']['thumbnail']['width'] >= $size[0] && $meta['sizes']['thumbnail']['width'] >= $size[1]) {
            $data = $meta['sizes']['thumbnail'];
        } else {
            return false;
        }
        [$data['width'], $data['height']] = image_constrain_size_for_editor($data['width'], $data['height'], $size);
    } elseif (!empty($meta['sizes'][$size])) {
        $data = $meta['sizes'][$size];
    }
    if (empty($data)) {
        return false;
    }
    if (!isset($data['path']) && !empty($data['file']) && !empty($meta['file'])) {
        $file_url = wp_get_attachment_url($post_id);
        $data['path'] = path_join(dirname($meta['file']), $data['file']);
        $data['url'] = path_join(dirname($file_url), $data['file']);
    }
    return apply_filters('image_get_intermediate_size', $data, $post_id, $size);
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
    $attachment = get_post($attachment_id);
    $hwstring = image_hwstring($width, $height);
    $size_class = is_array($size) ? implode('x', $size) : $size;
    $default_attr = ['src' => $src, 'class' => "attachment-{$size_class} size-{$size_class}", 'alt' => trim(strip_tags((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true)))];
    $attr = wp_parse_args($attr, $default_attr);
    $loading_attr = $attr;
    $loading_attr['width'] = $width;
    $loading_attr['height'] = $height;
    $loading_optimization = wp_get_loading_optimization_attributes('img', $loading_attr, 'wp_get_attachment_image');
    if (array_key_exists('loading', $attr) && !$attr['loading']) {
        unset($loading_optimization['loading']);
    }
    if (array_key_exists('fetchpriority', $attr) && !$attr['fetchpriority']) {
        unset($loading_optimization['fetchpriority']);
    }
    $attr = array_merge($attr, $loading_optimization);
    if (empty($attr['srcset'])) {
        $image_meta = wp_get_attachment_metadata($attachment_id);
        if (is_array($image_meta)) {
            $size_array = [absint($width), absint($height)];
            $srcset = wp_calculate_image_srcset($size_array, $src, $image_meta, $attachment_id);
            $sizes = wp_calculate_image_sizes($size_array, $src, $image_meta, $attachment_id);
            if ($srcset && ($sizes || !empty($attr['sizes']))) {
                $attr['srcset'] = $srcset;
                if (empty($attr['sizes'])) {
                    $attr['sizes'] = $sizes;
                }
            }
        }
    }
    $attr = apply_filters('wp_get_attachment_image_attributes', $attr, $attachment, $size);
    $attr = array_map('esc_attr', array_filter($attr, static fn ($v) => $v !== false && $v !== null));
    $html = rtrim("<img {$hwstring}");
    foreach ($attr as $name => $value) {
        $html .= " {$name}=\"{$value}\"";
    }
    $html .= ' />';
    return apply_filters('wp_get_attachment_image', $html, $attachment_id, $size, $icon, $attr);
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
    $runtime = Runtime::current();
    if ($tag_name === 'img') {
        $optimization['decoding'] = 'async';
    }
    $explicitLoading = array_key_exists('loading', $attr) && !$attr['loading'];
    $explicitPriority = array_key_exists('fetchpriority', $attr) && $attr['fetchpriority'] === 'high';
    if ($explicitLoading || $explicitPriority) {
        if (!$runtime->get('high_priority_used', false)) {
            $optimization['fetchpriority'] = 'high';
            $runtime->set('high_priority_used', true);
        }
    } elseif ($tag_name === 'img' && RenderState::depth() > 0 && !(defined('REST_REQUEST') && REST_REQUEST) && RenderState::nextImage() <= 3) {
        // Inside a page render the plugin's image shares the engine's budget: three eager images, the first with high priority.
        if (RenderState::claimPriority()) {
            $optimization = ['fetchpriority' => 'high'] + $optimization;
            $runtime->set('high_priority_used', true);
        }
    } elseif (wp_lazy_loading_enabled($tag_name, $context)) {
        $optimization['loading'] = 'lazy';
    }
    return apply_filters('wp_get_loading_optimization_attributes', $optimization, $tag_name, $attr, $context);
}

function wp_lazy_loading_enabled($tag_name, $context)
{
    return apply_filters('wp_lazy_loading_enabled', $tag_name === 'img' || $tag_name === 'iframe', $tag_name, $context);
}

function wp_calculate_image_srcset($size_array, $image_src, $image_meta, $attachment_id = 0)
{
    $image_meta = apply_filters('wp_calculate_image_srcset_meta', $image_meta, $size_array, $image_src, $attachment_id);
    if (empty($image_meta['sizes']) || !isset($image_meta['file']) || !str_contains($image_meta['file'], '.')) {
        return false;
    }
    $image_sizes = $image_meta['sizes'];
    $image_sizes['full'] = ['width' => $image_meta['width'], 'height' => $image_meta['height'], 'file' => wp_basename($image_meta['file'])];
    $image_basename = wp_basename($image_meta['file']);
    $image_baseurl = str_replace(wp_basename($image_src), '', $image_src);
    $image_edited = preg_match('/-e[0-9]{13}/', $image_basename, $edit_hash);
    $image_width = (int) $size_array[0];
    $image_height = (int) $size_array[1];
    if (!$image_width || !$image_height) {
        return false;
    }
    $sources = [];
    foreach ($image_sizes as $image) {
        $is_src = false;
        if (!is_array($image) || !isset($image['file'], $image['width'], $image['height'])) {
            continue;
        }
        if (str_contains($image['file'], '.') && ($image_edited && !str_contains($image['file'], $edit_hash[0]))) {
            continue;
        }
        if (wp_basename($image_src) === $image['file']) {
            $is_src = true;
        }
        if (!wp_image_matches_ratio($image_width, $image_height, $image['width'], $image['height'])) {
            continue;
        }
        if (isset($sources[$image['width']]) && !$is_src) {
            continue;
        }
        $sources[$image['width']] = ['url' => $image_baseurl . $image['file'], 'descriptor' => 'w', 'value' => $image['width']];
    }
    $sources = apply_filters('wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id);
    if (count($sources) < 2) {
        return false;
    }
    $srcset = '';
    foreach ($sources as $source) {
        $srcset .= str_replace(' ', '%20', $source['url']) . ' ' . $source['value'] . $source['descriptor'] . ', ';
    }
    return rtrim($srcset, ', ');
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

function wp_get_image_editor($path, $args = [])
{
    $args['path'] = $path;
    if (!isset($args['mime_type'])) {
        $args['mime_type'] = wp_get_image_mime($path) ?: (wp_check_filetype($path)['type'] ?: '');
    }
    if (!WP_Image_Editor_GD::test($args)) {
        return new WP_Error('image_no_editor', 'No editor could be selected.');
    }
    $editor = new WP_Image_Editor_GD($path);
    $loaded = $editor->load();
    if (is_wp_error($loaded)) {
        return $loaded;
    }
    return $editor;
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

function wp_create_image_subsizes($file, $attachment_id)
{
    $attachment_id = (int) $attachment_id;
    $imagesize = wp_getimagesize($file);
    if (empty($imagesize)) {
        return [];
    }
    $image_meta = [
        'width' => $imagesize[0],
        'height' => $imagesize[1],
        'file' => _wp_relative_upload_path($file),
        'filesize' => (int) filesize($file),
        'sizes' => [],
    ];
    $image_meta['image_meta'] = wp_read_image_metadata($file);
    wp_update_attachment_metadata($attachment_id, $image_meta);
    $new_sizes = wp_get_registered_image_subsizes();
    $new_sizes = apply_filters('intermediate_image_sizes_advanced', $new_sizes, $image_meta, $attachment_id);
    // The reference makes medium and large first, then the rest in registration order.
    $ordered = [];
    foreach (['medium', 'large'] as $first) {
        if (isset($new_sizes[$first])) {
            $ordered[$first] = $new_sizes[$first];
        }
    }
    return _wp_make_subsizes($ordered + $new_sizes, $file, $image_meta, $attachment_id);
}

function _wp_make_subsizes($new_sizes, $file, $image_meta, $attachment_id)
{
    if (empty($image_meta) || !is_array($image_meta)) {
        return [];
    }
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
        return $image_meta;
    }
    foreach ($new_sizes as $new_size_name => $new_size_data) {
        if (isset($image_meta['sizes'][$new_size_name])) {
            continue;
        }
        if (!empty($new_size_data['crop'])) {
            $new_size_meta = $editor->resize($new_size_data['width'], $new_size_data['height'], true);
        } else {
            $new_size_meta = $editor->resize($new_size_data['width'], $new_size_data['height'], false);
        }
        if (is_wp_error($new_size_meta)) {
            continue;
        }
        $size = $editor->get_size();
        if ((int) $size['width'] === (int) $image_meta['width'] && (int) $size['height'] === (int) $image_meta['height']) {
            $editor = wp_get_image_editor($file);
            continue;
        }
        $saved = $editor->save();
        $editor = wp_get_image_editor($file);
        if (is_wp_error($saved)) {
            continue;
        }
        unset($saved['path']);
        $image_meta['sizes'][$new_size_name] = $saved;
        wp_update_attachment_metadata($attachment_id, $image_meta);
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

function wp_read_image_metadata($file)
{
    return Metadata::blankImageMeta();
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
    $taxonomies = _minn_post_writer()->taxonomiesOf($post->ID);
    _minn_post_writer()->destroy($post->ID);
    foreach ($taxonomies as $taxonomy) {
        _minn_post_writer()->recount($taxonomy);
    }
    wp_delete_attachment_files($post->ID, $meta, $backup_sizes, $file);
    wp_cache_delete($post->ID, 'posts');
    wp_cache_delete($post->ID, 'post_meta');
    do_action('deleted_post', $post->ID, $post);
    return $post;
}

function wp_delete_attachment_files($post_id, $meta, $backup_sizes, $file)
{
    $uploadpath = wp_get_upload_dir();
    $deleted = true;
    if (!empty($meta['thumb'])) {
        $thumbfile = str_replace(wp_basename((string) $file), $meta['thumb'], (string) $file);
        wp_delete_file($thumbfile);
    }
    if (isset($meta['sizes']) && is_array($meta['sizes'])) {
        $intermediate_dir = path_join($uploadpath['basedir'], dirname((string) $file));
        foreach ($meta['sizes'] as $size => $sizeinfo) {
            $intermediate_file = str_replace(wp_basename((string) $file), $sizeinfo['file'], (string) $file);
            wp_delete_file(path_join(dirname((string) $file), $sizeinfo['file']));
        }
    }
    if (!empty($meta['original_image'])) {
        wp_delete_file(path_join(dirname((string) $file), $meta['original_image']));
    }
    if (is_array($backup_sizes)) {
        foreach ($backup_sizes as $size => $sizeinfo) {
            wp_delete_file(path_join(dirname((string) $file), $sizeinfo['file']));
        }
    }
    if ($file && is_file($file)) {
        wp_delete_file($file);
    }
    return $deleted;
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
    $attachment_url = wp_get_attachment_url($attachment->ID);
    $base_url = str_replace(wp_basename((string) $attachment_url), '', (string) $attachment_url);
    $response = [
        'id' => $attachment->ID,
        'title' => $attachment->post_title,
        'filename' => wp_basename((string) get_attached_file($attachment->ID)),
        'url' => $attachment_url,
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
    $author = get_userdata((int) $attachment->post_author);
    $response['authorName'] = $author ? $author->display_name : '(no author)';
    $response['filesizeInBytes'] = is_array($meta) && isset($meta['filesize']) ? $meta['filesize'] : (is_file(get_attached_file($attachment->ID)) ? filesize(get_attached_file($attachment->ID)) : 0);
    $response['filesizeHumanReadable'] = size_format($response['filesizeInBytes']);
    $response['context'] = '';
    if (is_array($meta) && isset($meta['width'], $meta['height'])) {
        $response['height'] = $meta['height'];
        $response['width'] = $meta['width'];
        $response['orientation'] = $meta['height'] > $meta['width'] ? 'portrait' : 'landscape';
    }
    if ($type === 'image') {
        $sizes = [];
        $possible = apply_filters('image_size_names_choose', ['thumbnail' => 'Thumbnail', 'medium' => 'Medium', 'large' => 'Large', 'full' => 'Full Size']);
        foreach ($possible as $size => $label) {
            if ($size === 'full') {
                continue;
            }
            $downsize = image_downsize($attachment->ID, $size);
            if ($downsize && $downsize[3]) {
                $sizes[$size] = ['height' => $downsize[2], 'width' => $downsize[1], 'url' => $downsize[0], 'orientation' => $downsize[2] > $downsize[1] ? 'portrait' : 'landscape'];
            } elseif (is_array($meta) && isset($meta['sizes'][$size])) {
                $info = $meta['sizes'][$size];
                $sizes[$size] = ['height' => $info['height'], 'width' => $info['width'], 'url' => $base_url . $info['file'], 'orientation' => $info['height'] > $info['width'] ? 'portrait' : 'landscape'];
            }
        }
        if (is_array($meta) && isset($meta['width'])) {
            $sizes['full'] = ['url' => $attachment_url, 'height' => $meta['height'], 'width' => $meta['width'], 'orientation' => $meta['height'] > $meta['width'] ? 'portrait' : 'landscape'];
        }
        $response['sizes'] = $sizes;
    }
    $response['compat'] = ['item' => '', 'meta' => ''];
    return apply_filters('wp_prepare_attachment_for_js', $response, $attachment, $meta);
}

function wp_handle_upload(&$file, $overrides = false, $time = null)
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
        return ['error' => 'No file was uploaded.'];
    }
    $upload = wp_upload_bits((string) $file['name'], null, (string) file_get_contents($file['tmp_name']), $time);
    if ($upload['error']) {
        return ['error' => $upload['error']];
    }
    @unlink($file['tmp_name']);
    return apply_filters('wp_handle_upload', ['file' => $upload['file'], 'url' => $upload['url'], 'type' => $upload['type']], 'upload');
}

function wp_handle_sideload(&$file, $overrides = false, $time = null)
{
    return wp_handle_upload($file, $overrides, $time);
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
