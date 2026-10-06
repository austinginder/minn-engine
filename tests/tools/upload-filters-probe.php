<?php
/**
 * What an upload's filters are handed, in order, and what comes of them:
 * wp_handle_sideload for an image, for a type the site does not allow, for
 * a file a plugin's prefilter refuses, for a type a plugin allows
 * (upload_mimes, wp_check_filetype_and_ext), and the file name's clean-up.
 * Same protocol as api-probe.php; the files it writes go at the end and
 * paths read as {uploads}.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
$dir = wp_upload_dir();
$mask = static fn ($v) => is_string($v) ? (string) preg_replace('#^.*/zz(up|ck)\w+$#', '{tmp-file}', str_replace([$dir['basedir'], $dir['baseurl']], ['{uploads}', '{uploads-url}'], $v)) : $v;
$watch = ['wp_handle_sideload_prefilter', 'wp_handle_sideload_overrides', 'upload_mimes', 'wp_check_filetype_and_ext', 'sanitize_file_name', 'wp_unique_filename', 'pre_move_uploaded_file', 'wp_handle_upload', 'upload_dir', 'wp_prevent_unsupported_mime_type_uploads'];
$describe = static function ($value) use ($mask) {
    if (is_array($value)) {
        $keys = array_keys($value);
        sort($keys);
        return count($value) > 12 ? 'array(' . count($value) . ')' : 'array[' . implode(',', $keys) . ']';
    }
    return is_object($value) ? 'object' : $mask($value);
};
$seen = [];
$recorder = static function (string $hook) use (&$seen, $watch, $describe): void {
    if (in_array($hook, $watch, true)) {
        $seen[] = $hook . '(' . implode(' | ', array_map(static fn ($a) => is_scalar($a) || $a === null ? var_export($describe($a), true) : (string) $describe($a), array_slice(func_get_args(), 1))) . ')';
    }
};
$made = [];
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$sideload = static function (string $label, string $name, string $bits) use (&$seen, $recorder, $say, $mask, &$made): void {
    $tmp = tempnam(sys_get_temp_dir(), 'zzup');
    file_put_contents($tmp, $bits);
    $file = ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($bits), 'error' => 0, 'type' => ''];
    $seen = [];
    add_action('all', $recorder);
    $result = wp_handle_sideload($file, ['test_form' => false]);
    remove_action('all', $recorder);
    if (!empty($result['file'])) {
        $made[] = $result['file'];
    }
    @unlink($tmp);
    $say($label, ['result' => array_map($mask, $result), 'filters' => $seen]);
};

$sideload('an image', 'zz probe image.png', $png);
$sideload('a type the site does not allow', 'zz-probe.php', '<?php echo 1;');
$refuse = static function (array $file): array {
    $file['error'] = 'Refused by a plugin.';
    return $file;
};
add_filter('wp_handle_sideload_prefilter', $refuse);
$sideload('a file a plugin refuses', 'zz-probe-refused.png', $png);
remove_filter('wp_handle_sideload_prefilter', $refuse);
$allow = static fn (array $mimes): array => $mimes + ['svg' => 'image/svg+xml'];
$fix = static fn (array $data, $file, $filename) => str_ends_with((string) $filename, '.svg') ? ['ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false] : $data;
add_filter('upload_mimes', $allow);
add_filter('wp_check_filetype_and_ext', $fix, 10, 3);
$sideload('a type a plugin allows', 'zz-probe.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
remove_filter('upload_mimes', $allow);
remove_filter('wp_check_filetype_and_ext', $fix, 10);
$sideload('a name to clean', 'zz Probe ÿ Name (1).PNG', $png);
$sideload('the same name again', 'zz probe image.png', $png);
$sideload('and once more', 'zz probe image.png', $png);

foreach (['plain.png', 'two words.png', 'caf&eacute; & co.png', '../../etc/passwd.png', 'x.php.png', 'noext', '.htaccess', 'trailing.', 'UPPER.JPG', 'png', 'x.jpg.png', 'archive.tar.gz', 'a.b.c.png', 'x.php5.png', 'résumé.pdf', 'Ünïcödé.txt', 'x.PHP.png', 'shell.phtml.jpg', 'data.json.txt'] as $i => $name) {
    $say("sanitize_file_name #{$i}", sanitize_file_name($name));
}
$jpg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
$gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
foreach ([
    'a png named png' => ['a.png', $png], 'a jpeg named png' => ['a.png', $jpg], 'a png named jpg' => ['photo.jpg', $png], 'a gif named jpeg' => ['x.y.jpeg', $gif],
    'text named txt' => ['notes.txt', "plain words\n"], 'csv named csv' => ['rows.csv', "a,b\n1,2\n"], 'text named pdf' => ['doc.pdf', 'not a pdf'],
    'a pdf named pdf' => ['doc.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"], 'html named txt' => ['page.txt', '<html><body>x</body></html>'], 'php named jpg' => ['shell.jpg', '<?php echo 1;'],
    'a png named PNG' => ['UP.PNG', $png],
] as $label => [$name, $bits]) {
    $tmp = tempnam(sys_get_temp_dir(), 'zzck');
    file_put_contents($tmp, $bits);
    $say("wp_check_filetype_and_ext {$label}", wp_check_filetype_and_ext($tmp, $name));
    @unlink($tmp);
}
$say('wp_check_filetype_and_ext text named png', (static function () {
    $tmp = tempnam(sys_get_temp_dir(), 'zzck');
    file_put_contents($tmp, 'not an image');
    $result = wp_check_filetype_and_ext($tmp, 'fake.png');
    @unlink($tmp);
    return $result;
})());

foreach ($made as $path) {
    @unlink($path);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
