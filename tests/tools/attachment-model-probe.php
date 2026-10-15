<?php
/**
 * Behaviour probe for the attachment model the media modal reads
 * (wp_prepare_attachment_for_js), for each kind of visitor: nobody, an
 * administrator, an editor, an author. What changes with who is asking: the
 * nonces the modal saves, deletes and edits with, the links to edit the
 * attachment, its author and the post it was uploaded to, and that post's
 * title. Same protocol as api-probe.php; cleans up on shutdown.
 */

// Admin-side files the reference loads on demand; the engine's facade has these at boot.
if (defined('ABSPATH') && !function_exists('wp_generate_attachment_metadata') && is_file(ABSPATH . 'wp-admin/includes/image.php')) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$month = ltrim((string) wp_get_upload_dir()['subdir'], '/');

$made = ['posts' => [], 'attachments' => []];
$cleanup = static function () use (&$made): void {
    foreach (get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 's' => 'Probe model']) as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_attachment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
};
$cleanup();
register_shutdown_function($cleanup);

$post = static function (array $fields) use (&$made): int {
    $id = wp_insert_post($fields + ['post_status' => 'draft', 'post_type' => 'post', 'post_content' => ''], true);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "attachment-model-probe: a parent post could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    return $id;
};
$attach = static function (string $name, string $bits, string $mime, int $author, int $parent) use (&$made): int {
    $upload = wp_upload_bits($name, null, $bits);
    if (!empty($upload['error'])) {
        fwrite(STDERR, "attachment-model-probe: {$upload['error']}\n");
        exit(1);
    }
    $id = wp_insert_attachment(['post_mime_type' => $mime, 'post_title' => 'Probe model ' . pathinfo($name, PATHINFO_FILENAME), 'post_content' => '', 'post_status' => 'inherit', 'post_author' => $author], $upload['file'], $parent);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "attachment-model-probe: an attachment could not be made\n");
        exit(1);
    }
    $made['attachments'][] = $id;
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
    return $id;
};

$parent = $post(['post_title' => 'Probe model parent "quoted" & <b>bold</b>', 'post_author' => 1]);
$untitled = $post(['post_title' => '', 'post_content' => 'Probe model', 'post_author' => 1]);
$editors = $post(['post_title' => 'Probe model parent of the editor', 'post_author' => 2]);
// A published page: anyone reads it, only an editor or the admin edits it (a page, so no pings are scheduled).
$page = $post(['post_title' => 'Probe model published page', 'post_author' => 1, 'post_type' => 'page', 'post_status' => 'publish']);
$img = imagecreatetruecolor(320, 200);
imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 200));
ob_start();
imagejpeg($img, null, 80);
$jpeg = (string) ob_get_clean();
$attachments = [
    'image by the author, on the admin\'s post' => $attach('probe-model-photo.jpg', $jpeg, 'image/jpeg', 3, $parent),
    'text by the admin, unattached' => $attach('probe-model-note.txt', "probe\n", 'text/plain', 1, 0),
    'image by the author, on an untitled post' => $attach('probe-model-untitled.jpg', $jpeg, 'image/jpeg', 3, $untitled),
    'text by the author, on the editor\'s post' => $attach('probe-model-editor.txt', "probe\n", 'text/plain', 3, $editors),
    'image by the author, on a published page' => $attach('probe-model-page.jpg', $jpeg, 'image/jpeg', 3, $page),
    'text by someone no longer a user, made for a header' => $attach('probe-model-gone.txt', "probe\n", 'text/plain', 987654321, 0),
];
// What the Customizer uploads it for, kept with the attachment.
update_post_meta($attachments['text by someone no longer a user, made for a header'], '_wp_attachment_context', 'custom-header');

// Ids, the host, the upload month, today's dates and the nonces (named by the action
// they were made for, on the stack that made them) are masked.
$ids = array_merge(array_values($attachments), [$parent, $untitled, $editors, $page]);
$names = array_merge(array_keys($attachments), ['parent', 'untitled', 'editors', 'page']);
$mask = static function ($value, int $id) use (&$mask, $ids, $names, $home, $month) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            if ($key === 'date' || $key === 'modified') {
                $out[$key] = is_int($item) && abs($item / 1000 - time()) < 86400 ? '{now}' : $item;
            } elseif ($key === 'dateFormatted') {
                $out[$key] = $item === mysql2date(get_option('date_format'), get_post($id)->post_date) ? '{date}' : $item;
            } elseif (in_array($key, ['update', 'delete', 'edit'], true) && is_string($item)) {
                $action = ['update' => 'update-post_', 'delete' => 'delete-post_', 'edit' => 'image_editor-'][$key];
                $out[$key] = $item === wp_create_nonce($action . $id) ? '{nonce ' . $action . '{id}}' : '{nonce}';
            } else {
                $out[$key] = $mask($item, $id);
            }
        }
        return $out;
    }
    if (is_int($value) && in_array($value, $ids, true)) {
        return '{' . $names[array_search($value, $ids, true)] . '}';
    }
    if (is_string($value)) {
        $value = str_replace([$home, $month . '/'], ['{home}', '{month}/'], $value);
        foreach ($ids as $i => $known) {
            $value = preg_replace('/(?<!\d)' . $known . '(?!\d)/', '{' . $names[$i] . '}', $value);
        }
        return preg_replace('/probe-model-(photo|note|untitled|editor|page|gone)(-\d+)?/', 'probe-model-$1', $value);
    }
    return $value;
};

foreach ([0 => 'nobody', 1 => 'the admin', 2 => 'the editor', 3 => 'the author'] as $user => $who) {
    wp_set_current_user($user);
    foreach ($attachments as $label => $id) {
        $say("as {$who}: {$label}", $mask(wp_prepare_attachment_for_js($id), $id));
    }
}
wp_set_current_user(0);
$say('a post that is no attachment', wp_prepare_attachment_for_js($parent));
$say('nothing at all', wp_prepare_attachment_for_js(0));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
