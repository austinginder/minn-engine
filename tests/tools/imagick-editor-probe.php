<?php
/**
 * The Imagick image editor (probe imagick-editor): what it supports, and
 * what each operation does to images the probe makes itself (a gradient, four
 * solid quadrants, a PNG with alpha, a JPEG turned by its EXIF orientation and
 * carrying profiles, a PDF). Each operation runs on a fresh editor; the hooks
 * it fires are listed, and the file it writes is described by what a site
 * sees: size, format, quality, alpha, the profiles and comment kept, its
 * orientation, and the colour at each corner, coarsely. Byte sizes and pixel
 * values beyond that are the encoder's own and are left out. Files are made
 * and removed in a folder of the probe's own.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!class_exists('WP_Image_Editor_Imagick', false) && is_file(ABSPATH . WPINC . '/class-wp-image-editor-imagick.php')) {
    require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
    require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
}
$dir = sys_get_temp_dir() . '/minn-imagick-probe-' . getmypid();
@mkdir($dir, 0755, true);
register_shutdown_function(static function () use ($dir): void {
    foreach (glob($dir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
});
$mask = static fn ($value) => json_decode(str_replace(str_replace('/', '\/', $dir), '{dir}', (string) json_encode($value, JSON_UNESCAPED_UNICODE)), true);

// The images.
$gradient = "{$dir}/gradient.jpg";
$im = imagecreatetruecolor(400, 300);
for ($x = 0; $x < 400; $x++) {
    for ($y = 0; $y < 300; $y++) {
        imagesetpixel($im, $x, $y, (intdiv($x * 255, 400) << 16) | (intdiv($y * 255, 300) << 8) | 96);
    }
}
imagejpeg($im, $gradient, 90);
$quadrants = "{$dir}/quadrants.jpg";
$im = imagecreatetruecolor(200, 100);
foreach ([[0, 0, 0xff0000], [100, 0, 0x00ff00], [0, 50, 0x0000ff], [100, 50, 0xffffff]] as [$x, $y, $colour]) {
    imagefilledrectangle($im, $x, $y, $x + 99, $y + 49, $colour);
}
imagejpeg($im, $quadrants, 95);
$alpha = "{$dir}/alpha.png";
$im = imagecreatetruecolor(200, 100);
imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagefilledrectangle($im, 0, 0, 99, 99, imagecolorallocatealpha($im, 200, 30, 60, 0));
imagepng($im, $alpha);
$palette = "{$dir}/palette.png";
$im = imagecreate(120, 60);
imagecolorallocate($im, 255, 255, 255);
imagefilledrectangle($im, 0, 0, 59, 59, imagecolorallocate($im, 0, 0, 255));
imagepng($im, $palette);
$turned = "{$dir}/turned.jpg";
$magick = new Imagick();
$magick->newPseudoImage(300, 200, 'gradient:red-blue');
$magick->setImageFormat('jpeg');
$magick->setImageProfile('exif', "Exif\0\0II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, 6, 0) . pack('V', 0));
$magick->setImageProfile('xmp', '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>ZZ</dc:title></rdf:Description></rdf:RDF></x:xmpmeta>');
$magick->commentImage('ZZ comment');
$magick->setImageOrientation(6);
$magick->writeImage($turned);
$pdf = "{$dir}/document.pdf";
$magick = new Imagick();
$magick->newPseudoImage(200, 100, 'xc:white');
$magick->setImageFormat('pdf');
$magick->writeImage($pdf);
$clearPdf = "{$dir}/clear.pdf";
$magick = new Imagick();
$magick->newPseudoImage(200, 100, 'xc:none');
$magick->setImageFormat('pdf');
$magick->writeImage($clearPdf);
$text = "{$dir}/not-an-image.jpg";
file_put_contents($text, 'ZZ not an image');

// What a written file looks like.
$describe = static function (string $file): array {
    if (!is_file($file)) {
        return ['missing' => basename($file)];
    }
    $image = new Imagick($file);
    $w = $image->getImageWidth();
    $h = $image->getImageHeight();
    $corner = static function (int $x, int $y) use ($image): string {
        $colour = $image->getImagePixelColor($x, $y)->getColor();
        return implode(',', array_map(static fn ($channel) => intdiv((int) $channel, 64), [$colour['r'], $colour['g'], $colour['b']]));
    };
    $inset = static fn (int $size) => max(0, (int) floor($size * 0.15));
    $resolution = $image->getImageResolution();
    return [
        'size' => [$w, $h], 'format' => $image->getImageFormat(), 'quality' => $image->getImageCompressionQuality(),
        'alpha' => $image->getImageAlphaChannel(), 'interlace' => $image->getImageInterlaceScheme(), 'depth' => $image->getImageDepth(),
        'profiles' => array_keys($image->getImageProfiles('*', true)), 'orientation' => $image->getImageOrientation(),
        'comment' => $image->getImageProperty('comment'), 'resolution' => [round($resolution['x']), round($resolution['y'])],
        'png colour type' => $image->getImageProperty('png:IHDR.color_type'), 'sampling' => $image->getImageProperty('jpeg:sampling-factor'),
        'corners' => [$corner($inset($w), $inset($h)), $corner($w - 1 - $inset($w), $inset($h)), $corner($inset($w), $h - 1 - $inset($h)), $corner($w - 1 - $inset($w), $h - 1 - $inset($h))],
        'bytes' => filesize($file) > 0,
    ];
};
// The hooks an operation fires, those about images only.
$heard = [];
$listen = static function ($hook) use (&$heard) {
    if (preg_match('/image|imagick|jpeg|quality|editor|intermediate|exif|mime/', (string) $hook) && !in_array($hook, $heard, true)) {
        $heard[] = $hook;
    }
    return func_num_args() > 1 ? func_get_arg(1) : null;
};
$run = static function (Closure $operation) use (&$heard, $listen, $mask) {
    $heard = [];
    add_filter('all', $listen);
    try {
        $result = $operation();
    } finally {
        remove_filter('all', $listen);
    }
    $plain = static fn ($value) => $value instanceof WP_Error ? ['error' => $value->get_error_code(), 'message' => $value->get_error_message()] : $value;
    return ['result' => $mask(is_array($result) ? array_map($plain, $result) : $plain($result)), 'hooks' => $heard];
};
$open = static function (string $file) {
    $editor = new WP_Image_Editor_Imagick($file);
    $loaded = $editor->load();
    return [$editor, $loaded];
};
$saved = static fn ($result) => is_array($result) ? array_merge($result, ['filesize' => ($result['filesize'] ?? 0) > 0]) : $result;

$say('what it supports', [
    'test' => WP_Image_Editor_Imagick::test(), 'methods' => WP_Image_Editor_Imagick::test(['methods' => ['resize', 'rotate', 'flip']]),
    'an unknown method' => WP_Image_Editor_Imagick::test(['methods' => ['zz_nope']]), 'webp' => WP_Image_Editor_Imagick::test(['mime_type' => 'image/webp']),
    'an unknown type' => WP_Image_Editor_Imagick::test(['mime_type' => 'image/zz']),
    'types' => array_map(static fn ($type) => WP_Image_Editor_Imagick::supports_mime_type($type), array_combine($types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic', 'application/pdf', 'image/svg+xml', 'zz'], $types)),
]);

$say('loading', [
    'a JPEG' => $run(static function () use ($open, $gradient) {
        [$editor, $loaded] = $open($gradient);
        return [$loaded, $editor->get_size(), $editor->get_quality()];
    }),
    'a missing file' => $run(static fn () => (new WP_Image_Editor_Imagick('/zz/missing.jpg'))->load()),
    'not an image' => $run(static fn () => (new WP_Image_Editor_Imagick($text))->load()),
    'twice' => $run(static function () use ($open, $gradient) {
        [$editor] = $open($gradient);
        return $editor->load();
    }),
]);

// Resizing, then saving under the name the editor makes.
$resized = static function (int $w, int $h, bool $crop) use ($open, $gradient, $saved, $describe, $run) {
    $out = $run(static function () use ($open, $gradient, $w, $h, $crop, $saved) {
        [$editor] = $open($gradient);
        $result = $editor->resize($w, $h, $crop);
        $size = $editor->get_size();
        return [$result, $size, $saved($editor->save()), $editor->get_quality()];
    });
    $out['written'] = is_array($out['result'][2] ?? null) ? $describe(str_replace('{dir}', dirname($gradient), $out['result'][2]['path'])) : null;
    return $out;
};
$say('resizing', [
    'cropped to a square' => $resized(150, 150, true),
    'to a width' => $resized(300, 0, false),
    'to a height' => $resized(0, 100, false),
    'larger than the image' => $resized(800, 600, false),
    'larger, cropped' => $resized(800, 600, true),
    'to its own size' => $resized(400, 300, false),
    'to nothing' => $resized(0, 0, false),
]);

$say('several sizes at once', (static function () use ($open, $gradient, $saved, $describe, $run, $dir) {
    $out = $run(static function () use ($open, $gradient, $saved) {
        [$editor] = $open($gradient);
        $sizes = $editor->multi_resize([
            'thumb' => ['width' => 150, 'height' => 150, 'crop' => true], 'medium' => ['width' => 300, 'height' => 300], 'big' => ['width' => 1000, 'height' => 1000],
            'height only' => ['height' => 100], 'none' => [], 'own size' => ['width' => 400, 'height' => 300],
        ]);
        return [array_map($saved, $sizes), $editor->get_size()];
    });
    $out['written'] = array_map(static fn ($size) => $describe("{$dir}/" . $size['file']), (array) ($out['result'][0] ?? []));
    return $out;
})());
$say('one sub-size', [
    'cropped' => $run(static function () use ($open, $gradient, $saved) {
        [$editor] = $open($gradient);
        return [$saved($editor->make_subsize(['width' => 200, 'height' => 200, 'crop' => true])), $editor->get_size()];
    }),
    'a width only' => $run(static function () use ($open, $gradient, $saved) {
        [$editor] = $open($gradient);
        return [$saved($editor->make_subsize(['width' => 120])), $editor->get_size()];
    }),
    'no size' => $run(static function () use ($open, $gradient) {
        [$editor] = $open($gradient);
        return $editor->make_subsize(['crop' => true]);
    }),
]);

// Cropping, turning and flipping: the four quadrants show where each corner went.
$shape = static function (Closure $change, string $name) use ($open, $quadrants, $describe, $run, $dir) {
    $out = $run(static function () use ($open, $quadrants, $change, $name, $dir) {
        [$editor] = $open($quadrants);
        $result = $change($editor);
        $size = $editor->get_size();
        $written = $editor->save("{$dir}/{$name}.jpg");
        return [$result, $size, is_array($written) ? [$written['width'], $written['height']] : $written];
    });
    $out['written'] = $describe("{$dir}/{$name}.jpg");
    return $out;
};
$say('the quadrants as they are', $describe($quadrants));
$say('cropping', [
    'the top left' => $shape(static fn ($editor) => $editor->crop(0, 0, 100, 50), 'crop-top-left'),
    'scaled down' => $shape(static fn ($editor) => $editor->crop(50, 0, 100, 100, 50, 50), 'crop-scaled'),
    'scaled up' => $shape(static fn ($editor) => $editor->crop(100, 50, 100, 50, 200, 100), 'crop-up'),
    'by its far corner' => $shape(static fn ($editor) => $editor->crop(100, 0, 200, 50, null, null, true), 'crop-absolute'),
    'no width' => $shape(static fn ($editor) => $editor->crop(0, 0, 0, 50), 'crop-empty'),
    'past the edge' => $shape(static fn ($editor) => $editor->crop(150, 75, 100, 100), 'crop-past'),
]);
$say('turning', [
    'a quarter' => $shape(static fn ($editor) => $editor->rotate(90), 'rotate-90'),
    'back a quarter' => $shape(static fn ($editor) => $editor->rotate(-90), 'rotate-minus-90'),
    'half' => $shape(static fn ($editor) => $editor->rotate(180), 'rotate-180'),
    'a full turn' => $shape(static fn ($editor) => $editor->rotate(360), 'rotate-360'),
]);
$say('flipping', [
    'top to bottom' => $shape(static fn ($editor) => $editor->flip(true, false), 'flip-horz'),
    'side to side' => $shape(static fn ($editor) => $editor->flip(false, true), 'flip-vert'),
    'both' => $shape(static fn ($editor) => $editor->flip(true, true), 'flip-both'),
    'neither' => $shape(static fn ($editor) => $editor->flip(false, false), 'flip-none'),
]);

$say('quality', $run(static function () use ($open, $gradient) {
    [$editor] = $open($gradient);
    $out = [];
    foreach ([50, 100, 1, 0, 101, -5, '60', null] as $quality) {
        $set = $editor->set_quality($quality);
        $out[] = [$quality, $set instanceof WP_Error ? $set->get_error_code() : $set, $editor->get_quality()];
    }
    $editor->set_quality(50);
    $editor->resize(200, 150);
    $out[] = ['set, then resized', $editor->get_quality()];
    return $out;
}));

// Other formats, by the type asked for or the name's extension.
$format = static function (string $label, ?string $name, ?string $type, string $source = '') use ($open, $gradient, $saved, $describe, $run, $dir) {
    $out = $run(static function () use ($open, $gradient, $saved, $name, $type, $source, $dir) {
        [$editor] = $open($source !== '' ? $source : $gradient);
        $editor->resize(100, 100);
        $result = $saved($editor->save($name === null ? null : "{$dir}/{$name}", $type));
        return [$result, $editor->get_quality()];
    });
    $out['written'] = is_array($out['result'][0] ?? null) ? $describe(str_replace('{dir}', $dir, $out['result'][0]['path'])) : null;
    return $out;
};
$say('saving in other formats', [
    'webp' => $format('webp', null, 'image/webp'),
    'png by extension' => $format('png', 'as-png.png', null),
    'gif' => $format('gif', 'as-gif.gif', 'image/gif'),
    'avif' => $format('avif', 'as-avif.avif', 'image/avif'),
    'an extension and another type' => $format('mixed', 'mixed.png', 'image/webp'),
    'an unknown type' => $format('unknown', 'unknown.zz', 'image/zz'),
    'a PNG with alpha' => $format('alpha', null, null, $alpha),
    'a PNG with alpha, as JPEG' => $format('alpha-jpeg', 'alpha-flat.jpg', 'image/jpeg', $alpha),
    'a palette PNG' => $format('palette', null, null, $palette),
]);
$say('a PNG cropped, not resampled', (static function () use ($open, $alpha, $describe, $run, $dir) {
    $out = $run(static function () use ($open, $alpha, $dir) {
        [$editor] = $open($alpha);
        $result = $editor->crop(50, 0, 100, 100);
        $written = $editor->save("{$dir}/alpha-cropped.png");
        return [$result, is_array($written) ? [$written['width'], $written['height'], $written['mime-type']] : $written];
    });
    $out['written'] = $describe("{$dir}/alpha-cropped.png");
    return $out;
})());

$say('turned by its EXIF orientation', [
    'as it is' => $describe($turned),
    'upright' => (static function () use ($open, $turned, $describe, $run, $dir) {
        $out = $run(static function () use ($open, $turned, $dir) {
            [$editor] = $open($turned);
            $rotated = $editor->maybe_exif_rotate();
            $size = $editor->get_size();
            $editor->resize(100, 100);
            $written = $editor->save("{$dir}/upright.jpg");
            return [$rotated, $size, is_array($written) ? [$written['width'], $written['height']] : $written, $editor->maybe_exif_rotate()];
        });
        $out['written'] = $describe("{$dir}/upright.jpg");
        return $out;
    })(),
    'saved as it is' => (static function () use ($open, $turned, $describe, $run, $dir) {
        $out = $run(static function () use ($open, $turned, $dir) {
            [$editor] = $open($turned);
            $written = $editor->save("{$dir}/unturned.jpg");
            return is_array($written) ? [$written['width'], $written['height']] : $written;
        });
        $out['written'] = $describe("{$dir}/unturned.jpg");
        return $out;
    })(),
    'no orientation' => $run(static function () use ($open, $gradient) {
        [$editor] = $open($gradient);
        return [$editor->maybe_exif_rotate(), $editor->get_size()];
    }),
]);

$say('a PDF', (static function () use ($pdf, $describe, $run, $dir, $saved) {
    $out = $run(static function () use ($pdf, $saved) {
        $editor = new WP_Image_Editor_Imagick($pdf);
        $loaded = $editor->load();
        $size = $editor->get_size();
        $editor->resize(100, 100);
        return [$loaded, $size, $saved($editor->save())];
    });
    $out['written'] = is_array($out['result'][2] ?? null) ? $describe(str_replace('{dir}', $dir, $out['result'][2]['path'])) : null;
    return $out;
})());

$say('a clear PDF, as JPEG', (static function () use ($clearPdf, $describe, $run, $dir, $saved) {
    $out = $run(static function () use ($clearPdf, $saved, $dir) {
        $editor = new WP_Image_Editor_Imagick($clearPdf);
        $loaded = $editor->load();
        return [$loaded, $saved($editor->save("{$dir}/clear.jpg", 'image/jpeg'))];
    });
    $out['written'] = $describe("{$dir}/clear.jpg");
    return $out;
})());

$say('streaming', $run(static function () use ($open, $gradient) {
    $out = [];
    foreach ([null, 'image/png', 'image/webp'] as $type) {
        [$editor] = $open($gradient);
        $editor->resize(50, 50);
        ob_start();
        $result = $editor->stream($type);
        $bytes = (string) ob_get_clean();
        $out[] = [$type, $result, bin2hex(substr($bytes, 0, 4)), strlen($bytes) > 0];
    }
    return $out;
}));

$say('the editor WordPress picks', [
    'for a JPEG' => get_class(wp_get_image_editor($gradient)),
    'supports' => [wp_image_editor_supports(['mime_type' => 'image/heic']), wp_image_editor_supports(['methods' => ['rotate', 'flip']]), wp_image_editor_supports(['mime_type' => 'application/pdf'])],
]);

$deprecated = [];
$note = static function ($function) use (&$deprecated) {
    $deprecated[] = $function;
};
add_action('deprecated_function_run', $note);
add_filter('deprecated_function_trigger_error', '__return_false');
$limit = [WP_Image_Editor_Imagick::set_imagick_time_limit()];
remove_filter('deprecated_function_trigger_error', '__return_false');
remove_action('deprecated_function_run', $note);
$say('the time limit, deprecated', ['returned' => $limit, 'deprecated' => $deprecated]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
