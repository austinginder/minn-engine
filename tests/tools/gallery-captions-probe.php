<?php
/**
 * A [gallery] with a captioned image and one without, as it is printed:
 * the captioned image names its caption (aria-describedby, the gallery's
 * number and the image's id, as the caption's own id), whether it is
 * linked to its page, to its file or not at all; an image whose metadata
 * gives no size has no orientation. Same protocol as api-probe.php; the
 * attachments and their file go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$image = imagecreatetruecolor(400, 300);
ob_start();
imagepng($image);
$upload = wp_upload_bits('zz-gallery-captions.png', null, (string) ob_get_clean());
$plain = (int) wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'zz gallery plain', 'post_status' => 'inherit'], $upload['file']);
$captioned = (int) wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'zz gallery captioned', 'post_status' => 'inherit', 'post_excerpt' => 'A "quoted" caption'], $upload['file']);
$sized = (int) wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'zz gallery sized', 'post_status' => 'inherit'], $upload['file']);
wp_update_attachment_metadata($sized, ['width' => 300, 'height' => 400, 'file' => basename($upload['file']), 'sizes' => []]);
$mask = static fn (string $html): string => str_replace([(string) $plain, (string) $captioned, (string) $sized, wp_get_upload_dir()['subdir'], home_url()], ['{plain}', '{captioned}', '{sized}', '{month}', '{home}'], $html);
foreach (['linked to its page' => '', 'linked to its file' => ' link="file"', 'not linked' => ' link="none"'] as $what => $link) {
    $say("a gallery {$what}", $mask(do_shortcode("[gallery ids=\"{$plain},{$captioned},{$sized}\"{$link}]")));
}
foreach ([$plain, $captioned, $sized] as $id) {
    wp_delete_attachment($id, true);
}
@unlink($upload['file']);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
