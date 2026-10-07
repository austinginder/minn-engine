<?php
/**
 * An image's sized address when the size it asks for was never made, as the
 * reference answers it (probe image-downsize): image_downsize,
 * wp_get_attachment_thumb_url, wp_get_attachment_image_src and
 * wp_get_attachment_image_url for an image with a thumbnail size, one with
 * none, one with the old `thumb` metadata (its file there or gone), one
 * with no metadata, and a file that is not an image. Same protocol as
 * api-probe.php; its files and attachments go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$uploads = wp_get_upload_dir();
$dir = $uploads['basedir'] . '/zz-downsize-probe';
wp_mkdir_p($dir);
$png = static function (string $file, int $w, int $h): void {
    $img = imagecreatetruecolor($w, $h);
    imagepng($img, $file);
};
$png("{$dir}/zz-full.png", 300, 200);
$png("{$dir}/zz-full-150x100.png", 150, 100);
$png("{$dir}/zz-old-thumb.png", 120, 80);
file_put_contents("{$dir}/zz-doc.pdf", "%PDF-1.4\n%zz\n");
$made = [];
$attach = static function (string $file, string $mime, $meta) use (&$made): int {
    $id = (int) wp_insert_attachment(['post_title' => 'Zz Downsize ' . basename($file), 'post_mime_type' => $mime, 'post_status' => 'inherit'], false);
    update_post_meta($id, '_wp_attached_file', 'zz-downsize-probe/' . $file);
    if ($meta !== null) {
        update_post_meta($id, '_wp_attachment_metadata', $meta);
    }
    $made[] = $id;
    return $id;
};
$base = ['width' => 300, 'height' => 200, 'file' => 'zz-downsize-probe/zz-full.png', 'filesize' => 100];
$cases = [
    'with a thumbnail size' => $attach('zz-full.png', 'image/png', $base + ['sizes' => ['thumbnail' => ['file' => 'zz-full-150x100.png', 'width' => 150, 'height' => 100, 'mime-type' => 'image/png']]]),
    'no sizes' => $attach('zz-full.png', 'image/png', $base + ['sizes' => []]),
    'old thumb, its file there' => $attach('zz-full.png', 'image/png', $base + ['sizes' => [], 'thumb' => 'zz-old-thumb.png']),
    'old thumb, its file gone' => $attach('zz-full.png', 'image/png', $base + ['sizes' => [], 'thumb' => 'zz-gone.png']),
    'no metadata' => $attach('zz-full.png', 'image/png', null),
    'not an image' => $attach('zz-doc.pdf', 'application/pdf', null),
];
$home = home_url();
$mask = static fn ($value) => json_decode(str_replace([$home, addcslashes($home, '/')], '{home}', (string) json_encode($value, JSON_UNESCAPED_SLASHES)), true);
foreach ($cases as $label => $id) {
    $say($label, $mask([
        'downsize thumbnail' => image_downsize($id, 'thumbnail'),
        'downsize medium' => image_downsize($id, 'medium'),
        'downsize full' => image_downsize($id, 'full'),
        'thumb url' => wp_get_attachment_thumb_url($id),
        'image src thumbnail' => wp_get_attachment_image_src($id, 'thumbnail'),
        'image url thumbnail' => wp_get_attachment_image_url($id, 'thumbnail'),
    ]));
}

foreach (array_reverse($made) as $id) {
    wp_delete_attachment($id, true);
}
foreach (glob("{$dir}/*") ?: [] as $file) {
    @unlink($file);
}
@rmdir($dir);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
