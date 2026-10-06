<?php
/**
 * What a REST upload and a REST media edit hand plugins: the image sent as
 * the request body, as a client sends it, with a title and caption; a
 * plugin changing the attachment through rest_pre_insert_attachment; and
 * an edit of its title, caption, description and alt text. Dispatched in
 * process as an administrator; the save hooks each step runs are listed in
 * order with their arguments. Same protocol as api-probe.php; the uploads
 * are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$watch = ['wp_read_image_metadata', 'rest_pre_insert_attachment', 'wp_insert_attachment_data', 'wp_insert_post_data', 'pre_post_title', 'title_save_pre', 'pre_post_excerpt', 'excerpt_save_pre', 'pre_post_content', 'content_save_pre', 'pre_post_mime_type', 'pre_post_guid', 'wp_unique_post_slug', 'add_attachment', 'edit_attachment', 'attachment_updated', 'wp_insert_post', 'save_post_attachment', 'save_post', 'wp_after_insert_post', 'rest_insert_attachment', 'rest_after_insert_attachment', 'wp_generate_attachment_metadata', 'update_post_metadata', 'add_post_metadata', 'wp_update_attachment_metadata', 'update_attached_file'];
$made = [];
$steps = [];
$base = wp_get_upload_dir()['basedir'];
$recorder = static function (string $hook) use (&$steps, $watch, $base): void {
    if (!in_array($hook, $watch, true)) {
        return;
    }
    $args = array_map(static function ($a) {
        if (is_object($a)) {
            return 'object:' . get_class($a);
        }
        if (is_array($a)) {
            $keys = array_keys($a);
            sort($keys);
            return 'array[' . implode(',', $keys) . ']';
        }
        return is_string($a) ? mb_substr((string) preg_replace('#^.*?/wp-content/uploads#', '{uploads}', $a), 0, 60) : $a;
    }, array_slice(func_get_args(), 1));
    if (in_array($hook, ['update_post_metadata', 'add_post_metadata'], true)) {
        $args = [$args[1] ?? null, $args[2] ?? null];
    }
    $steps[] = $hook . ' ' . json_encode($args, JSON_UNESCAPED_SLASHES);
};
$uploads = wp_get_upload_dir();
$mask = static function ($value) use (&$made, $uploads, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value) && !is_int($value)) {
        return $value;
    }
    $value = is_int($value) ? (string) $value : $value;
    $value = str_replace([$uploads['basedir'], $uploads['baseurl']], ['{uploads}', '{uploads-url}'], $value);
    foreach ($made as $n => $id) {
        $value = (string) preg_replace('/\b' . $id . '\b/', '{media' . $n . '}', $value);
    }
    return (string) preg_replace(['/\b20\d\d-\d\d-\d\d[T ]\d\d:\d\d:\d\d\b/', '#/20\d\d/\d\d/#'], ['{date}', '/{yyyy}/{mm}/'], $value);
};
$image = static function (): string {
    $img = imagecreatetruecolor(400, 300);
    imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 60));
    ob_start();
    imagejpeg($img);
    return (string) ob_get_clean();
};
$send = static function (string $label, WP_REST_Request $request) use (&$steps, $recorder, $say, $mask, &$made): void {
    $steps = [];
    add_action('all', $recorder);
    $response = rest_do_request($request);
    remove_action('all', $recorder);
    $data = rest_get_server()->response_to_data($response, false);
    if ($response->get_status() === 201 && isset($data['id'])) {
        $made[] = (int) $data['id'];
    }
    $answer = $response->is_error() ? ['code' => $data['code'] ?? null, 'message' => $data['message'] ?? null] : ['title' => $data['title']['raw'] ?? null, 'caption' => $data['caption']['raw'] ?? null, 'description' => $data['description']['raw'] ?? null, 'alt_text' => $data['alt_text'] ?? null, 'slug' => $data['slug'] ?? null, 'mime_type' => $data['mime_type'] ?? null];
    $say($label, $mask(['status' => $response->get_status(), 'answer' => $answer, 'steps' => $steps]));
};
$upload = static function (string $filename, array $params = []) use ($image): WP_REST_Request {
    $request = new WP_REST_Request('POST', '/wp/v2/media');
    $request->set_header('Content-Type', 'image/jpeg');
    $request->set_header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    $request->set_body($image());
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
$send('an upload', $upload('zz-rest-media.jpg', ['title' => 'A <b>title</b>', 'caption' => 'A caption']));
$change = static function ($attachment) {
    $attachment->post_title = ($attachment->post_title ?? "") . " (changed)";
    return $attachment;
};
add_filter('rest_pre_insert_attachment', $change);
$send('a plugin changes it', $upload('zz-rest-media-two.jpg'));
remove_filter('rest_pre_insert_attachment', $change);
if ($made !== []) {
    $edit = new WP_REST_Request('POST', '/wp/v2/media/' . $made[0]);
    $edit->set_body_params(['title' => 'New title', 'caption' => 'New caption', 'description' => 'New <em>description</em>', 'alt_text' => 'Alt words']);
    $send('an edit', $edit);
}
foreach ($made as $id) {
    wp_delete_attachment($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
