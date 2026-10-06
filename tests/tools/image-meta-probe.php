<?php
/**
 * What wp_read_image_metadata makes of a photo's EXIF and IPTC: which tag
 * fills each field when both carry one, when only EXIF does, a long or
 * short description, dates from either, numbers as fractions, a TIFF-only
 * orientation, text in Latin-1 and in UTF-8, and a file with neither. The
 * photos are built here byte by byte in a temporary folder. Same protocol
 * as api-probe.php; nothing is saved.
 */

if (defined('ABSPATH') && !function_exists('wp_read_image_metadata') && is_file(ABSPATH . 'wp-admin/includes/image.php')) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

/** One IFD: entries [tag, type, value] (2 ASCII, 3 SHORT, 4 LONG, 5 RATIONAL [n, d], 7 UNDEFINED), laid out at $offset. */
$ifd = static function (array $entries, int $offset): string {
    $dataAt = $offset + 2 + count($entries) * 12 + 4;
    $head = pack('v', count($entries));
    $data = '';
    foreach ($entries as [$tag, $type, $value]) {
        $bytes = match ($type) {
            2, 7 => $value,
            3 => pack('v', $value),
            4 => pack('V', $value),
            default => pack('VV', $value[0], $value[1]),
        };
        $count = match ($type) {
            2, 7 => strlen($value),
            default => 1,
        };
        if (strlen($bytes) <= 4) {
            $head .= pack('vvV', $tag, $type, $count) . str_pad($bytes, 4, "\0");
        } else {
            $head .= pack('vvVV', $tag, $type, $count, $dataAt + strlen($data));
            $data .= $bytes . (strlen($bytes) % 2 ? "\0" : '');
        }
    }
    return $head . pack('V', 0) . $data;
};
/** A JPEG with the given IFD0 and Exif-IFD entries and IPTC records [dataset, value] (record 2 unless given as [record, dataset, value]). */
$photo = static function (array $ifd0, array $exif, array $iptc) use ($ifd): string {
    $img = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($img);
    $jpeg = (string) ob_get_clean();
    $segments = '';
    if ($ifd0 !== [] || $exif !== []) {
        $ifd0[] = [0x8769, 4, 0];
        $first = $ifd($ifd0, 8);
        $ifd0[count($ifd0) - 1] = [0x8769, 4, 8 + strlen($first)];
        $tiff = "II*\0" . pack('V', 8) . $ifd($ifd0, 8) . $ifd($exif, 8 + strlen($first));
        $app1 = "Exif\0\0" . $tiff;
        $segments .= "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
    }
    if ($iptc !== []) {
        $records = '';
        foreach ($iptc as $entry) {
            [$record, $dataset, $value] = count($entry) === 3 ? $entry : [2, $entry[0], $entry[1]];
            $records .= "\x1C" . chr($record) . chr($dataset) . pack('n', strlen($value)) . $value;
        }
        $resource = '8BIM' . pack('n', 0x0404) . "\0\0" . pack('N', strlen($records)) . $records . (strlen($records) % 2 ? "\0" : '');
        $app13 = "Photoshop 3.0\0" . $resource;
        $segments .= "\xFF\xED" . pack('n', strlen($app13) + 2) . $app13;
    }
    return "\xFF\xD8" . $segments . substr($jpeg, 2);
};
$dir = sys_get_temp_dir() . '/zz-image-meta-probe-' . getmypid();
@mkdir($dir);
$read = static function (string $label, string $bits) use ($dir, $say): void {
    $file = $dir . '/' . preg_replace('/[^a-z0-9]+/', '-', $label) . '.jpg';
    file_put_contents($file, $bits);
    $say($label, wp_read_image_metadata($file));
    unlink($file);
};
$exifFull = [[0x829A, 5, [1, 250]], [0x829D, 5, [28, 10]], [0x8827, 3, 400], [0x9003, 2, "2024:05:06 07:08:09\0"], [0x9004, 2, "2023:01:02 03:04:05\0"], [0x920A, 5, [50, 1]]];
$ifdFull = [[0x010E, 2, "A short description\0"], [0x010F, 2, "Probe Cam\0"], [0x0110, 2, "Model Z\0"], [0x0112, 3, 3], [0x013B, 2, "Probe Artist\0"], [0x8298, 2, "Exif Copyright\0"]];
$iptcFull = [[5, 'Iptc Title'], [120, 'Iptc caption'], [110, 'Iptc credit'], [80, 'Iptc byline'], [116, 'Iptc copyright'], [25, 'one'], [25, 'two'], [55, '20220304'], [60, '101112+0000']];
$read('both, iptc wins', $photo($ifdFull, $exifFull, $iptcFull));
$read('exif alone', $photo($ifdFull, $exifFull, []));
$read('exif with a long description', $photo([[0x010E, 2, str_repeat('A long description that goes on ', 4) . "\0"]], [], []));
$read('exif with a description and a user comment', $photo([[0x010E, 2, "Short one\0"]], [[0x9286, 7, "ASCII\0\0\0A user comment"]], []));
$read('exif digitized date only', $photo([], [[0x9004, 2, "2021:11:12 13:14:15\0"]], []));
$read('iptc byline, no credit', $photo([], [], [[80, 'Only byline']]));
$read('iptc date without time', $photo([], [], [[55, '20200102']]));
$read('fractions', $photo([], [[0x829A, 5, [10, 20]], [0x829D, 5, [0, 0]], [0x920A, 5, [35, 10]]], []));
$read('a long exposure', $photo([], [[0x829A, 5, [5, 2]]], []));
$read('orientation alone', $photo([[0x0112, 3, 8]], [], []));
$read('latin-1 iptc', $photo([], [], [[120, "Caf\xE9 cr\xE8me"]]));
$read('utf-8 iptc with its marker', $photo([], [], [[1, 90, "\x1B%G"], [120, "Café crème"]]));
$read('utf-8 iptc without a marker', $photo([], [], [[120, "Café crème"]]));
$read('markup in a caption', $photo([], [], [[120, 'Hello <b>world</b> & co']]));
$read('a 79-letter caption', $photo([], [], [[120, str_repeat('x', 79)]]));
$read('an 80-letter caption', $photo([], [], [[120, str_repeat('x', 80)]]));
$read('a long description, a short comment', $photo([[0x010E, 2, str_repeat('Long words ', 9) . "\0"]], [[0x9286, 7, "ASCII\0\0\0Short comment"]], []));
$read('thirds', $photo([], [[0x829A, 5, [1, 3]], [0x829D, 5, [7, 3]], [0x920A, 5, [100, 3]]], []));
$read('a thirtieth', $photo([], [[0x829A, 5, [1, 30]]], []));
$read('two iso speeds', $photo([], [[0x8827, 3, 200]], []));
$read('an iptc time with an offset', $photo([], [], [[55, '20220304'], [60, '101112+0200']]));
$read('a script in a caption', $photo([], [], [[120, '  Hi <script>alert(1)</script> there  ']]));
$read('a camera make alone', $photo([[0x010F, 2, "Only Make\0"]], [], []));
$read('a long description and a comment, run on', $photo([[0x010E, 2, str_repeat('d', 80) . "\0"]], [[0x9286, 7, "ASCII\0\0\0Tail"]], []));
$read('an iptc title, a short description and a comment', $photo([[0x010E, 2, "Short d\0"]], [[0x9286, 7, "ASCII\0\0\0Tail"]], [[5, 'T']]));
$read('a comment alone', $photo([], [[0x9286, 7, "ASCII\0\0\0Tail"]], []));
$read('an iptc caption and an exif description', $photo([[0x010E, 2, "Desc\0"]], [], [[120, 'Cap']]));
$read('an iptc headline and object name', $photo([], [], [[105, 'Headline'], [5, 'Object']]));
$read('neither', $photo([], [], []));
@rmdir($dir);
$say('a missing file', wp_read_image_metadata($dir . '/nope.jpg'));
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
