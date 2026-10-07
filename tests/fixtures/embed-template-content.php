<?php
/**
 * The embed-template suite's own content, run with wp eval-file on the
 * reference (the stacks share a database): "make" removes anything an
 * earlier run left (matched by exact title) and makes two images (a wide
 * one, its widest size 2:1, and a square one) with metadata but no files,
 * a post featuring each, and a protected post, printing their ids as
 * JSON; "remove" removes them.
 */

$titles = ['Zz Embed Wide', 'Zz Embed Square', 'Zz Embed Locked', 'Zz Embed Wide Image', 'Zz Embed Square Image'];
global $wpdb;
$ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_title IN ('" . implode("','", array_map('esc_sql', $titles)) . "')");
foreach ($ids as $id) {
    if (in_array((string) get_post_field('post_title', (int) $id, 'raw'), $titles, true)) {
        wp_delete_post((int) $id, true);
    }
}
if (($args[0] ?? '') !== 'make') {
    return;
}
$image = static function (string $title, string $file, int $width, int $height, array $sizes): int {
    $id = (int) wp_insert_attachment(['post_title' => $title, 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_date' => '2026-09-01 10:00:00', 'post_date_gmt' => '2026-09-01 10:00:00'], $file);
    update_post_meta($id, '_wp_attached_file', $file);
    wp_update_attachment_metadata($id, ['width' => $width, 'height' => $height, 'file' => $file, 'sizes' => $sizes, 'image_meta' => []]);
    return $id;
};
$size = static fn (string $file, int $width, int $height): array => ['file' => $file, 'width' => $width, 'height' => $height, 'mime-type' => 'image/jpeg'];
$wide = $image('Zz Embed Wide Image', 'zz-embed-wide.jpg', 2000, 1000, ['thumbnail' => $size('zz-embed-wide-150x150.jpg', 150, 150), 'large' => $size('zz-embed-wide-1024x512.jpg', 1024, 512)]);
$square = $image('Zz Embed Square Image', 'zz-embed-square.jpg', 600, 600, ['thumbnail' => $size('zz-embed-square-150x150.jpg', 150, 150), 'medium' => $size('zz-embed-square-300x300.jpg', 300, 300)]);
$post = static fn (string $title, string $slug, array $extra = []): int => (int) wp_insert_post(array_merge(['post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => "<!-- wp:paragraph -->\n<p>Zz {$title} body.</p>\n<!-- /wp:paragraph -->", 'post_date' => '2026-09-01 10:00:00', 'post_date_gmt' => '2026-09-01 10:00:00', 'comment_status' => 'open'], $extra));
$made = ['wide_image' => $wide, 'square_image' => $square];
$made['wide'] = $post('Zz Embed Wide', 'zz-embed-wide');
set_post_thumbnail($made['wide'], $wide);
$made['square'] = $post('Zz Embed Square', 'zz-embed-square');
set_post_thumbnail($made['square'], $square);
$made['locked'] = $post('Zz Embed Locked', 'zz-embed-locked', ['post_password' => 'zz']);
echo json_encode($made);
