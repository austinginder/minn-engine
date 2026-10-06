<?php
/**
 * What making an image's sizes hands plugins and leaves behind: an ordinary
 * JPEG, one past the big-image threshold, a big PNG, a JPEG whose EXIF says
 * it is turned and whose IPTC names a caption, credit and keywords, and a
 * plugin that turns JPEG output into WebP (image_editor_output_format). Each
 * case lists the image filters in order with their arguments, the metadata
 * and the files on disk. Both stacks are held to the GD editor, which is
 * what Minn has. Same protocol as api-probe.php; the images are its own and
 * go at the end.
 */

if (defined('ABSPATH') && !function_exists('wp_generate_attachment_metadata') && is_file(ABSPATH . 'wp-admin/includes/image.php')) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
add_filter('wp_image_editors', static fn () => ['WP_Image_Editor_GD'], 99);
$watch = ['wp_read_image_metadata', 'wp_read_image_metadata_types', 'big_image_size_threshold', 'image_editor_output_format', 'wp_editor_set_quality', 'jpeg_quality', 'image_resize_dimensions', 'wp_image_maybe_exif_rotate', 'wp_update_attachment_metadata', 'intermediate_image_sizes_advanced', 'image_make_intermediate_size', 'wp_generate_attachment_metadata', 'wp_image_resize_identical_dimensions', 'image_save_progressive', 'wp_image_editor_before_change'];
$uploads = wp_get_upload_dir();
$short = static function ($value) use ($uploads, &$short) {
    if (is_string($value)) {
        return str_replace([$uploads['basedir'] . '/', $uploads['basedir']], ['{uploads}/', '{uploads}'], $value);
    }
    if (is_array($value)) {
        return array_map($short, $value);
    }
    return is_object($value) ? 'object:' . get_class($value) : $value;
};
$steps = [];
$recorder = static function (string $hook) use (&$steps, $watch, $short): void {
    if (!in_array($hook, $watch, true)) {
        return;
    }
    $args = array_slice(func_get_args(), 1);
    if (in_array($hook, ['wp_update_attachment_metadata', 'wp_generate_attachment_metadata'], true)) {
        $meta = is_array($args[0]) ? $args[0] : [];
        $args[0] = 'sizes[' . implode(',', array_keys($meta['sizes'] ?? [])) . '] file ' . $short($meta['file'] ?? '');
        $args = array_slice($args, 0, 2) === $args ? $args : $args;
    }
    if ($hook === 'wp_read_image_metadata') {
        $args[4] = is_array($args[4] ?? null) ? array_values(array_diff(array_keys($args[4]), ['FileName', 'FileDateTime', 'FileSize', 'SectionsFound', 'COMPUTED', 'COMMENT', 'MimeType', 'FileType', 'Exif_IFD_Pointer'])) : $args[4] ?? null;
        $args[3] = is_array($args[3] ?? null) ? array_keys($args[3]) : $args[3] ?? null;
    }
    $steps[] = $hook . ' ' . json_encode($short($args), JSON_UNESCAPED_SLASHES);
};

/** A JPEG with an EXIF block (orientation, camera, time, exposure) and an IPTC block (caption, credit, keywords). */
$withMetadata = static function (string $jpeg): string {
    $entries = [[0x010F, 2, "Probe Cam\0"], [0x0110, 2, "Model Z\0"], [0x0112, 3, 6], [0x8298, 2, "Probe Co\0"]];
    $exifEntries = [[0x829A, 5, [1, 250]], [0x829D, 5, [28, 10]], [0x8827, 3, 400], [0x9003, 2, "2024:05:06 07:08:09\0"], [0x920A, 5, [50, 1]]];
    $ifd = static function (array $entries, int $offset, int $next) use (&$ifd): array {
        $count = count($entries);
        $dataAt = $offset + 2 + $count * 12 + 4;
        $head = pack('v', $count);
        $data = '';
        foreach ($entries as [$tag, $type, $value]) {
            if ($type === 2) {
                $bytes = $value;
                $n = strlen($bytes);
            } elseif ($type === 3) {
                $bytes = pack('vv', $value, 0);
                $n = 1;
            } elseif ($type === 4) {
                $bytes = pack('V', $value);
                $n = 1;
            } else {
                $bytes = pack('VV', $value[0], $value[1]);
                $n = 1;
            }
            if (strlen($bytes) <= 4 && $type !== 5) {
                $head .= pack('vvV', $tag, $type, $n) . str_pad($bytes, 4, "\0");
            } else {
                $head .= pack('vvVV', $tag, $type, $n, $dataAt + strlen($data));
                $data .= $bytes . (strlen($bytes) % 2 ? "\0" : '');
            }
        }
        return [$head . pack('V', $next), $data];
    };
    // IFD0 at 8, with an Exif pointer; the Exif IFD after it.
    $entries[] = [0x8769, 4, 0];
    $first = $ifd($entries, 8, 0);
    $exifAt = 8 + strlen($first[0]) + strlen($first[1]);
    $entries[count($entries) - 1] = [0x8769, 4, $exifAt];
    $first = $ifd($entries, 8, 0);
    $second = $ifd($exifEntries, $exifAt, 0);
    $tiff = "II*\0" . pack('V', 8) . $first[0] . $first[1] . $second[0] . $second[1];
    $app1 = "Exif\0\0" . $tiff;
    $iptc = '';
    foreach ([[120, 'A probe caption'], [110, 'Probe Credit'], [5, 'Probe Title'], [25, 'one'], [25, 'two'], [116, 'Probe Copyright']] as [$tag, $value]) {
        $iptc .= "\x1C\x02" . chr($tag) . pack('n', strlen($value)) . $value;
    }
    $resource = '8BIM' . pack('n', 0x0404) . "\0\0" . pack('N', strlen($iptc)) . $iptc . (strlen($iptc) % 2 ? "\0" : '');
    $app13 = "Photoshop 3.0\0" . $resource;
    return "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . "\xFF\xED" . pack('n', strlen($app13) + 2) . $app13 . substr($jpeg, 2);
};
$image = static function (int $width, int $height, string $type): string {
    $img = imagecreatetruecolor($width, $height);
    imagefill($img, 0, 0, imagecolorallocate($img, 30, 90, 200));
    imagefilledrectangle($img, 0, 0, (int) ($width / 3), (int) ($height / 4), imagecolorallocate($img, 220, 200, 40));
    ob_start();
    $type === 'png' ? imagepng($img) : imagejpeg($img, null, 90);
    return (string) ob_get_clean();
};
$made = [];
$case = static function (string $label, string $name, string $bits, string $mime) use (&$steps, $recorder, $say, $short, &$made, $uploads): void {
    $upload = wp_upload_bits($name, null, $bits);
    $id = wp_insert_attachment(['post_mime_type' => $mime, 'post_title' => $label, 'post_status' => 'inherit'], $upload['file']);
    $made[] = $id;
    $steps = [];
    add_action('all', $recorder);
    $meta = wp_generate_attachment_metadata($id, $upload['file']);
    remove_action('all', $recorder);
    wp_update_attachment_metadata($id, $meta);
    $base = pathinfo($upload['file'], PATHINFO_FILENAME);
    $files = array_map('basename', glob(dirname($upload['file']) . '/' . $base . '*') ?: []);
    sort($files);
    $sizes = [];
    foreach ($meta['sizes'] ?? [] as $size => $data) {
        $sizes[$size] = [$data['file'], $data['width'], $data['height'], $data['mime-type'], isset($data['filesize'])];
    }
    $say($label, ['steps' => array_map(static fn ($s) => str_replace((string) $id, '{id}', $s), $steps), 'meta' => ['file' => $short($meta['file'] ?? null), 'width' => $meta['width'] ?? null, 'height' => $meta['height'] ?? null, 'original_image' => $meta['original_image'] ?? null, 'keys' => array_keys($meta), 'sizes' => $sizes, 'image_meta' => $meta['image_meta'] ?? null], 'attached' => $short(get_attached_file($id)), 'original path' => $short(wp_get_original_image_path($id)), 'files' => $files]);
};
$case('an ordinary jpeg', 'zz pipeline small.jpg', $image(1600, 1000, 'jpg'), 'image/jpeg');
$case('a big jpeg', 'zz pipeline big.jpg', $image(3000, 2000, 'jpg'), 'image/jpeg');
$case('a big png', 'zz pipeline wide.png', $image(2700, 1800, 'png'), 'image/png');
$case('a turned photo with its story', 'zz pipeline photo.jpg', $withMetadata($image(1200, 800, 'jpg')), 'image/jpeg');
$webp = static fn (array $formats): array => ['image/jpeg' => 'image/webp'] + $formats;
add_filter('image_editor_output_format', $webp);
$case('a plugin makes it webp', 'zz pipeline webp.jpg', $image(1600, 1000, 'jpg'), 'image/jpeg');
remove_filter('image_editor_output_format', $webp);
foreach ($made as $id) {
    wp_delete_attachment($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
