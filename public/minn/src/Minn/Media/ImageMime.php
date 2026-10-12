<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * An image file's real type from its first bytes, as the reference sniffs
 * it before trusting an upload's extension (probe hook-inputs-2): PHP's own
 * header check for the classic formats (JPEG, PNG, GIF, WebP, BMP, ICO,
 * TIFF, AVIF), then an ISO media file's major brand for the HEIF family
 * PHP does not know. A file too short or of another kind has no image type.
 */
final class ImageMime
{
    /** An ISO base media file's major brand and the image type it declares. */
    private const BRANDS = [
        'heic' => 'image/heic',
        'heix' => 'image/heic',
        'hevc' => 'image/heic-sequence',
        'hevx' => 'image/heic-sequence',
        'mif1' => 'image/heif',
        'heim' => 'image/heif',
        'heis' => 'image/heif',
        'msf1' => 'image/heif-sequence',
        'avif' => 'image/avif',
        'avis' => 'image/avif',
    ];

    /** The image type the file's header declares, or null. */
    public static function of(string $file): ?string
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $type = \function_exists('exif_imagetype') ? @exif_imagetype($file) : false;
        if (is_int($type)) {
            return image_type_to_mime_type($type);
        }
        $info = @getimagesize($file);
        if (is_array($info) && isset($info['mime'])) {
            return (string) $info['mime'];
        }
        $head = (string) @file_get_contents($file, false, null, 0, 12);
        return strlen($head) === 12 && substr($head, 4, 4) === 'ftyp' ? self::BRANDS[substr($head, 8, 4)] ?? null : null;
    }
}
