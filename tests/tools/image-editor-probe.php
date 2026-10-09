<?php
/**
 * The image editors (probe image-editor): WP_Image_Editor_GD on a PNG and a
 * JPEG the probe draws. Support tests, sizes and qualities, the output format
 * and file names, resize (scaled and cropped), crop, rotate, flip, sub-sizes
 * one at a time and together, saving (to a path, a format, the editor's own
 * name), and streaming. Files are compared by name and measured size, never
 * by bytes. The probe's folder is removed. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = rtrim(get_temp_dir(), '/') . '/zz-image-editor-probe-' . getmypid();
register_shutdown_function(static function () use ($dir): void {
    foreach (glob($dir . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($dir)) {
        rmdir($dir);
    }
});
wp_mkdir_p($dir);
$mask = static function ($value) use (&$mask, $dir) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['error', $value->get_error_code(), $value->get_error_message()];
    }
    return is_string($value) ? str_replace($dir, '{dir}', $value) : $value;
};
$draw = static function (string $file, int $width, int $height, string $type) {
    $image = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x += 10) {
        imagefilledrectangle($image, $x, 0, $x + 9, $height - 1, imagecolorallocate($image, $x % 256, 80, 160));
    }
    $type === 'png' ? imagepng($image, $file) : imagejpeg($image, $file, 90);
    imagedestroy($image);
};
$png = "$dir/zz-photo.png";
$jpg = "$dir/zz-photo.jpg";
$draw($png, 400, 300, 'png');
$draw($jpg, 640, 480, 'jpg');
$measure = static fn (?string $file) => $file !== null && is_file($file) ? array_slice((array) getimagesize($file), 0, 2) + ['mime' => image_type_to_mime_type((int) getimagesize($file)[2])] : null;

// The GD editor loads with the first wp_get_image_editor() (lazily on the reference), so the subclass comes after it.
wp_get_image_editor($png);
class Zz_Probe_Image_Editor extends WP_Image_Editor_GD
{
    /** The protected methods, called from inside the class. */
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
}

// Which editor wp_get_image_editor() picks is left out: the reference prefers Imagick when the extension is there.
$say('the classes', [
    WP_Image_Editor_GD::test(),
    WP_Image_Editor_GD::test(['methods' => ['resize', 'rotate']]),
    WP_Image_Editor_GD::test(['methods' => ['resize', 'zz_nope']]),
    array_map([WP_Image_Editor_GD::class, 'supports_mime_type'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain', '']),
    is_subclass_of(WP_Image_Editor_GD::class, WP_Image_Editor::class),
    WP_Image_Editor::test(), WP_Image_Editor::supports_mime_type('image/png'),
]);

$editor = new Zz_Probe_Image_Editor($png);
$missing = new Zz_Probe_Image_Editor("$dir/zz-none.png");
$say('loaded', $mask([
    $editor->load(), $editor->get_size(), $editor->call('get_mime_type'), $editor->call('get_mime_type', 'jpg'), $editor->call('get_mime_type', 'nope'),
    $editor->call('get_extension'), $editor->call('get_extension', 'image/webp'), $editor->call('get_extension', 'text/zz'),
    $missing->load(),
    (new Zz_Probe_Image_Editor(__FILE__))->load(),
]));
$say('quality', [
    $editor->get_quality(),
    $editor->call('get_default_quality', 'image/jpeg'), $editor->call('get_default_quality', 'image/webp'), $editor->call('get_default_quality', 'image/png'),
    $mask($editor->set_quality(0)), $editor->get_quality(), $mask($editor->set_quality(150)), $mask($editor->set_quality(55)), $editor->get_quality(),
    (static function () use ($png) {
        $filter = static fn ($quality, $mime) => $mime === 'image/png' ? 42 : $quality;
        add_filter('wp_editor_set_quality', $filter, 10, 2);
        $e = new Zz_Probe_Image_Editor($png);
        $e->load();
        $q = $e->get_quality();
        remove_filter('wp_editor_set_quality', $filter, 10);
        return $q;
    })(),
]);
$say('output format and names', $mask([
    $editor->call('get_output_format'),
    $editor->call('get_output_format', "$dir/x.jpg"),
    $editor->call('get_output_format', null, 'image/gif'),
    $editor->call('get_output_format', "$dir/x.webp", 'image/png'),
    (static function () use ($editor) {
        $map = static fn ($formats) => $formats + ['image/png' => 'image/webp'];
        add_filter('image_editor_output_format', $map);
        $out = $editor->call('get_output_format', null, 'image/png');
        remove_filter('image_editor_output_format', $map);
        return $out;
    })(),
    $editor->get_suffix(),
    $editor->generate_filename(),
    $editor->generate_filename('small'),
    $editor->generate_filename('small', '/elsewhere'),
    $editor->generate_filename('small', null, 'jpg'),
    $editor->generate_filename(null, null, 'gif'),
]));

$steps = [];
$e = new Zz_Probe_Image_Editor($png);
$e->load();
$steps['resize'] = [$mask($e->resize(200, 100)), $e->get_size()];
$steps['resize bigger'] = [$mask($e->resize(800, 800)), $e->get_size()];
$e = new Zz_Probe_Image_Editor($png);
$e->load();
$steps['resize cropped'] = [$mask($e->resize(100, 100, true)), $e->get_size()];
$steps['resize cropped top left'] = (static function () use ($png, $mask) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    return [$mask($e->resize(120, 60, ['left', 'top'])), $e->get_size()];
})();
$steps['crop'] = (static function () use ($png, $mask) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    return [$mask($e->crop(10, 20, 100, 50)), $e->get_size(), $mask($e->crop(0, 0, 40, 40, 20, 10)), $e->get_size(), $mask($e->crop(0, 0, 0, 0))];
})();
$steps['rotate'] = (static function () use ($png, $mask) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    return [$mask($e->rotate(90)), $e->get_size(), $mask($e->rotate(45)), $e->get_size()];
})();
$steps['flip'] = (static function () use ($png, $mask) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    return [$mask($e->flip(true, false)), $mask($e->flip(false, true)), $mask($e->flip(true, true)), $e->get_size()];
})();
$steps['exif rotate'] = (static function () use ($jpg, $png) {
    $j = new Zz_Probe_Image_Editor($jpg);
    $j->load();
    $p = new Zz_Probe_Image_Editor($png);
    $p->load();
    return [$j->maybe_exif_rotate(), $p->maybe_exif_rotate()];
})();
$say('changes', $steps);

$sizes = [
    'zz_small' => ['width' => 100, 'height' => 100, 'crop' => true],
    'zz_wide' => ['width' => 300, 'height' => 0],
    'zz_tall' => ['width' => 0, 'height' => 150],
    'zz_bigger' => ['width' => 1000, 'height' => 1000],
    'zz_none' => ['width' => 0, 'height' => 0],
];
$subsizes = (static function () use ($png, $sizes, $mask, $measure, $dir) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    $all = $e->multi_resize($sizes);
    $one = $e->make_subsize(['width' => 50, 'height' => 40, 'crop' => true]);
    $bad = $e->make_subsize(['width' => 0, 'height' => 0]);
    return $mask([$all, array_map(static fn ($meta) => $measure($dir . '/' . $meta['file']), $all), $one, $bad, $e->get_size()]);
})();
$say('sub-sizes', $subsizes);

$saved = (static function () use ($jpg, $dir, $mask, $measure) {
    $e = new Zz_Probe_Image_Editor($jpg);
    $e->load();
    $e->resize(320, 240);
    $plain = $e->save();
    $named = $e->save("$dir/zz-named.jpg");
    $asPng = $e->save("$dir/zz-as.png");
    $byMime = $e->save(null, 'image/png');
    $webp = $e->save("$dir/zz-photo.webp", 'image/webp');
    return $mask([
        [$plain, $measure($plain['path'] ?? null)],
        [$named, $measure($named['path'] ?? null)],
        [$asPng, $measure($asPng['path'] ?? null)],
        [$byMime, $measure($byMime['path'] ?? null)],
        [array_diff_key((array) $webp, ['filesize' => 1]), is_array($webp) ? $measure($webp['path']) : null],
        array_keys((array) $plain),
        is_int($plain['filesize'] ?? null) && $plain['filesize'] > 0,
    ]);
})();
$say('saved', $saved);
$say('saved twice without a name', (static function () use ($jpg, $mask) {
    $e = new Zz_Probe_Image_Editor($jpg);
    $e->load();
    $e->resize(320, 240);
    $first = $e->save();
    $e->resize(160, 120);
    $second = $e->save();
    $third = $e->save(null, 'image/png');
    return $mask([$first['file'] ?? $first, $second['file'] ?? $second, $third['file'] ?? $third]);
})());

$streamed = (static function () use ($png) {
    $e = new Zz_Probe_Image_Editor($png);
    $e->load();
    $e->resize(40, 30);
    ob_start();
    $ok = $e->stream('image/jpeg');
    $bytes = (string) ob_get_clean();
    ob_start();
    $png = $e->stream();
    $pngBytes = (string) ob_get_clean();
    return [$ok, substr(bin2hex($bytes), 0, 4), strlen($bytes) > 100, $png, substr(bin2hex($pngBytes), 0, 16)];
})();
$say('streamed', $streamed);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
