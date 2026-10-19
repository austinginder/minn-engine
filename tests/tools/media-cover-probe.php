<?php
/**
 * What wp_generate_attachment_metadata does with an audio or video file's
 * cover (probe media-cover): the cover becomes an image attachment of its
 * own (named for the file, digested in _cover_hash) and the attachment's
 * thumbnail, a second file with the same cover is given the same image,
 * a file without one is left alone, and without thumbnail support no
 * image is made; the cover's bytes leave the metadata in every case. The
 * attachments and files made are removed again, and the supports put
 * back. Same protocol as api-probe.php.
 */

if (defined('ABSPATH') && !function_exists('wp_generate_attachment_metadata') && is_file(ABSPATH . 'wp-admin/includes/image.php')) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$media = dirname(__DIR__) . '/fixtures/media';
$uploads = wp_upload_dir();
$made = [];
$files = [];
register_shutdown_function(static function () use (&$made, &$files): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_attachment($id, true);
        }
    }
    foreach ($files as $file) {
        @unlink($file);
    }
});
$attach = static function (string $fixture, string $name, string $mime) use ($media, $uploads, &$made, &$files): int {
    $file = $uploads['path'] . '/' . $name;
    copy("{$media}/{$fixture}", $file);
    $files[] = $file;
    $id = wp_insert_attachment(['post_mime_type' => $mime, 'post_title' => 'zz media cover', 'post_status' => 'inherit'], $file);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "media-cover: no attachment for {$fixture}\n");
        exit(1);
    }
    $made[] = $id;
    return $id;
};
$describe = static function (int $id, array $metadata) use ($uploads, &$made): array {
    $thumbnail = (int) get_post_meta($id, '_thumbnail_id', true);
    $out = ['image' => $metadata['image'] ?? null, 'has thumbnail' => $thumbnail > 1];
    if ($thumbnail > 1) {
        if (!in_array($thumbnail, $made, true)) {
            $made[] = $thumbnail;
        }
        $cover = get_post($thumbnail);
        $coverMeta = wp_get_attachment_metadata($thumbnail);
        $out['cover'] = [
            'mime' => $cover->post_mime_type,
            'title' => $cover->post_title,
            'name is its id' => $cover->post_name === (string) $thumbnail,
            'parent' => (int) $cover->post_parent,
            'file' => basename((string) get_post_meta($thumbnail, '_wp_attached_file', true)),
            'hash' => get_post_meta($thumbnail, '_cover_hash', true),
            'metadata' => is_array($coverMeta) ? array_intersect_key($coverMeta, array_flip(['width', 'height', 'filesize', 'sizes'])) : $coverMeta,
        ];
    }
    return $out;
};

$first = $attach('a01-cbr-tags.mp3', 'zz-media-cover-one.mp3', 'audio/mpeg');
$say('MP3 with a JPEG cover', $describe($first, wp_generate_attachment_metadata($first, get_attached_file($first))));
$second = $attach('a01-cbr-tags.mp3', 'zz-media-cover-two.mp3', 'audio/mpeg');
wp_generate_attachment_metadata($second, get_attached_file($second));
$say('a second file with the same cover is given the same image', (int) get_post_meta($second, '_thumbnail_id', true) === (int) get_post_meta($first, '_thumbnail_id', true));
$aac = $attach('a06-aac.m4a', 'zz-media-cover-three.m4a', 'audio/mp4');
$say('M4A with a PNG cover', $describe($aac, wp_generate_attachment_metadata($aac, get_attached_file($aac))));
$plain = $attach('a04-plain.mp3', 'zz-media-cover-four.mp3', 'audio/mpeg');
$say('MP3 without a cover', $describe($plain, wp_generate_attachment_metadata($plain, get_attached_file($plain))));
$hadType = post_type_supports('attachment:audio', 'thumbnail');
$hadTheme = get_theme_support('post-thumbnails');
remove_post_type_support('attachment:audio', 'thumbnail');
remove_theme_support('post-thumbnails');
$unsupported = $attach('a12-flac.flac', 'zz-media-cover-five.flac', 'audio/flac');
$say('without thumbnail support', $describe($unsupported, wp_generate_attachment_metadata($unsupported, get_attached_file($unsupported))));
if ($hadType) {
    add_post_type_support('attachment:audio', 'thumbnail');
}
if ($hadTheme !== false) {
    add_theme_support('post-thumbnails', ...(is_array($hadTheme) ? $hadTheme : []));
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
