<?php
/**
 * The media action routes (probe rest-media-actions), dispatched with
 * rest_do_request(): post-process making an image's sizes; edit rotating
 * one into a new attachment; sideload and finalize, which the reference
 * registers only where images are processed in the browser (not under
 * WP-CLI); each route's argument checks, a missing attachment, and the
 * routes refused signed out. GD edits on both stacks; ids, dates and upload
 * paths are masked; the attachments and files made here go on the way out.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('wp_image_editors', static fn () => ['WP_Image_Editor_GD'], 99);
wp_set_current_user(1);
if (!did_action('init')) {
    do_action('init');
}
require_once ABSPATH . 'wp-admin/includes/image.php';
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_attachment($id, true);
        }
    }
});
$ids = [];
$name = static function ($id) use (&$ids): void {
    $ids[(int) $id] ??= '{id' . (count($ids) + 1) . '}';
};
$uploads = wp_upload_dir();
$mask = static function ($value, $key = '') use (&$mask, &$ids, $uploads) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $item) {
            $out[$k] = $mask($item, (string) $k);
        }
        return $out;
    }
    if (in_array($key, ['date', 'date_gmt', 'modified', 'modified_gmt'], true)) {
        return '{date}';
    }
    if (in_array($key, ['filesize'], true)) {
        return is_int($value) && $value > 0 ? '{bytes}' : $value;
    }
    if ((is_int($value) || (is_string($value) && ctype_digit($value))) && in_array($key, ['id', 'parent', 'post', 'attachment_id'], true)) {
        return $ids[(int) $value] ?? $value;
    }
    if (is_string($value)) {
        $value = str_replace([$uploads['baseurl'], $uploads['basedir'], $uploads['subdir']], ['{uploads}', '{uploads}', '{month}'], $value);
        $value = (string) preg_replace('#https?://#', '{scheme}://', $value);
        $value = (string) preg_replace_callback('/(?<=[=\/])\d+\b/', static fn ($m) => $ids[(int) $m[0]] ?? $m[0], $value);
    }
    return $value;
};
$keep = static fn ($data) => is_array($data) && isset($data['media_details']) ? array_intersect_key($data, array_flip(['id', 'status', 'type', 'title', 'media_type', 'mime_type', 'source_url', 'media_details', 'post'])) : $data;
$do = static function (string $route, array $params = []) use ($keep) {
    $request = new WP_REST_Request('POST', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    return ['status' => $response->get_status(), 'data' => $keep($response->get_data())];
};

// A 400 by 300 JPEG with no sizes made yet.
$image = imagecreatetruecolor(400, 300);
imagefilledrectangle($image, 0, 0, 199, 299, imagecolorallocate($image, 200, 40, 40));
imagefilledrectangle($image, 200, 0, 399, 299, imagecolorallocate($image, 40, 40, 200));
$path = $uploads['path'] . '/zz-rma-photo.jpg';
imagejpeg($image, $path, 90);
$id = wp_insert_attachment(['post_title' => 'ZZ rma photo', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], $path);
if (!is_int($id) || $id <= 1) {
    fwrite(STDERR, "no attachment\n");
    exit(1);
}
$made[] = $id;
$name($id);
update_post_meta($id, '_wp_attachment_metadata', ['width' => 400, 'height' => 300, 'file' => _wp_relative_upload_path($path), 'sizes' => []]);

$say('post-process', [
    'no action' => $do("/wp/v2/media/$id/post-process"),
    'another action' => $do("/wp/v2/media/$id/post-process", ['action' => 'zz']),
    'sizes made' => $do("/wp/v2/media/$id/post-process", ['action' => 'create-image-subsizes']),
    'no such attachment' => $do('/wp/v2/media/99999999/post-process', ['action' => 'create-image-subsizes']),
]);

$source = wp_get_attachment_url($id);
$edited = $do("/wp/v2/media/$id/edit", ['src' => $source, 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]]]]);
$newId = is_array($edited['data']) ? (int) ($edited['data']['id'] ?? 0) : 0;
if ($newId > 1) {
    $made[] = $newId;
    $name($newId);
}
$say('edit', [
    'no source' => $do("/wp/v2/media/$id/edit", ['modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]]]]),
    'another image\'s source' => $do("/wp/v2/media/$id/edit", ['src' => $uploads['baseurl'] . '/zz-none.jpg', 'modifiers' => [['type' => 'rotate', 'args' => ['angle' => 90]]]]),
    'no change asked' => $do("/wp/v2/media/$id/edit", ['src' => $source]),
    'rotated' => $edited,
    'its parent image' => $newId > 1 ? (wp_get_attachment_metadata($newId)['parent_image'] ?? null) : null,
]);

$say('sideload and finalize', [
    'sideload with no size' => $do("/wp/v2/media/$id/sideload"),
    'sideload with an unknown size' => $do("/wp/v2/media/$id/sideload", ['image_size' => 'zz-size']),
    'finalize with a bad sub-size' => $do("/wp/v2/media/$id/finalize", ['sub_sizes' => [['width' => 10]]]),
    'finalize, no such attachment' => $do('/wp/v2/media/99999999/finalize', ['sub_sizes' => []]),
]);

wp_set_current_user(0);
$say('signed out', array_map(static fn ($action) => $do("/wp/v2/media/$id/$action", ['action' => 'create-image-subsizes', 'src' => $source, 'image_size' => 'thumbnail']), ['post-process' => 'post-process', 'edit' => 'edit', 'sideload' => 'sideload', 'finalize' => 'finalize']));

// Masked once every id made here has its name.
echo json_encode($mask($log), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
