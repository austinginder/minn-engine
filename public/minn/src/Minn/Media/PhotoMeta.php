<?php

declare(strict_types=1);

namespace Minn\Media;

use Closure;

/**
 * A photo's own description, read as the reference's wp_read_image_metadata
 * reads it (probe image-meta): IPTC first (headline or object name for the
 * title, caption, credit or byline, copyright, keywords, the date and time
 * it was made), then EXIF for what IPTC left empty (a short description as
 * the title, the user comment or description as the caption, the artist,
 * copyright, model, the digitized time, aperture, focal length, ISO,
 * exposure, orientation). Text that is not UTF-8 is read as Latin-1, and
 * every value is cleaned (the reference runs wp_kses_post) and made a
 * string; a short caption doubles as the title.
 */
final class PhotoMeta
{
    /** EXIF is read for these image types only: JPEG and TIFF, both byte orders. */
    public const EXIF_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];

    /**
     * The metadata, the image type, the IPTC and EXIF it came from; null
     * when there is no such file.
     *
     * @param list<int> $exifTypes
     * @param Closure(string): string $clean
     * @return array{meta: array<string, mixed>, type: int|null, iptc: array<string, mixed>, exif: array<string, mixed>}|null
     */
    public static function read(string $file, array $exifTypes, Closure $clean): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $info = [];
        $size = @getimagesize($file, $info);
        $type = is_array($size) ? (int) $size[2] : null;
        $meta = Metadata::blankImageMeta();
        $iptc = [];
        if (is_callable('iptcparse') && !empty($info['APP13'])) {
            $parsed = @iptcparse($info['APP13']);
            $iptc = is_array($parsed) ? $parsed : [];
            $meta = self::fromIptc($meta, $iptc);
        }
        $exif = [];
        if ($type !== null && is_callable('exif_read_data') && in_array($type, $exifTypes, true)) {
            $read = @exif_read_data($file);
            $exif = is_array($read) ? $read : [];
            $meta = self::fromExif($meta, $exif);
        }
        return ['meta' => self::cleaned($meta, $clean), 'type' => $type, 'iptc' => $iptc, 'exif' => $exif];
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $iptc
     * @return array<string, mixed>
     */
    private static function fromIptc(array $meta, array $iptc): array
    {
        $first = static fn (string $key): string => isset($iptc[$key][0]) ? trim((string) $iptc[$key][0]) : '';
        $meta['title'] = $first('2#105') !== '' ? $first('2#105') : $first('2#005');
        if ($first('2#120') !== '') {
            $meta['caption'] = $first('2#120');
            if ($meta['title'] === '' && mb_strlen($meta['caption']) < 80) {
                $meta['title'] = $meta['caption'];
            }
        }
        $meta['credit'] = $first('2#110') !== '' ? $first('2#110') : $first('2#080');
        if ($first('2#055') !== '' && $first('2#060') !== '') {
            $made = strtotime($first('2#055') . ' ' . $first('2#060'));
            $meta['created_timestamp'] = $made === false ? '0' : (string) $made;
        }
        if ($first('2#116') !== '') {
            $meta['copyright'] = $first('2#116');
        }
        if (!empty($iptc['2#025']) && is_array($iptc['2#025'])) {
            $meta['keywords'] = array_values(array_map('strval', $iptc['2#025']));
        }
        return $meta;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $exif
     * @return array<string, mixed>
     */
    private static function fromExif(array $meta, array $exif): array
    {
        $text = static fn (string $key): string => isset($exif[$key]) && is_scalar($exif[$key]) ? trim((string) $exif[$key]) : '';
        $meta = self::described($meta, $text('ImageDescription'), isset($exif['COMPUTED']['UserComment']) ? trim((string) $exif['COMPUTED']['UserComment']) : '', $text('Comments'));
        if ($meta['credit'] === '') {
            $meta['credit'] = $text('Artist') !== '' ? $text('Artist') : $text('Author');
        }
        if ($meta['copyright'] === '' && $text('Copyright') !== '') {
            $meta['copyright'] = $text('Copyright');
        }
        if (!empty($exif['FNumber'])) {
            $meta['aperture'] = (string) round(self::fraction($exif['FNumber']), 2);
        }
        if ($text('Model') !== '') {
            $meta['camera'] = $text('Model');
        }
        if ($meta['created_timestamp'] === '0' && $text('DateTimeDigitized') !== '') {
            $meta['created_timestamp'] = self::exifTime($text('DateTimeDigitized'));
        }
        foreach (['FocalLength' => 'focal_length', 'ExposureTime' => 'shutter_speed'] as $tag => $field) {
            if (!empty($exif[$tag])) {
                $meta[$field] = (string) self::fraction($exif[$tag]);
            }
        }
        if (!empty($exif['ISOSpeedRatings'])) {
            $meta['iso'] = trim((string) (is_array($exif['ISOSpeedRatings']) ? reset($exif['ISOSpeedRatings']) : $exif['ISOSpeedRatings']));
        }
        if (!empty($exif['Orientation'])) {
            $meta['orientation'] = (string) $exif['Orientation'];
        }
        return $meta;
    }

    /**
     * The title and caption EXIF gives when IPTC left them empty: a short
     * description is the title, and the user comment (else the description)
     * the caption; a description that is not the title runs on into the
     * comment; a comment alone is the caption; a short caption doubles as the
     * title.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function described(array $meta, string $description, string $comment, string $comments): array
    {
        if ($description !== '' && $meta['title'] === '' && mb_strlen($description) < 80) {
            $meta['title'] = $description;
            $meta['caption'] = $meta['caption'] === '' ? ($comment !== '' ? $comment : $description) : $meta['caption'];
        } elseif ($description !== '' && $meta['caption'] === '') {
            $meta['caption'] = trim($description . ' ' . $comment);
        } elseif ($meta['caption'] === '') {
            $meta['caption'] = $comment !== '' ? $comment : $comments;
        }
        if ($meta['title'] === '' && $meta['caption'] !== '' && mb_strlen($meta['caption']) < 80) {
            $meta['title'] = $meta['caption'];
        }
        return $meta;
    }

    /** "28/10" as 2.8; a zero denominator reads 0. */
    private static function fraction(mixed $value): float
    {
        $value = is_array($value) ? reset($value) : $value;
        if (!is_string($value) || !str_contains($value, '/')) {
            return (float) $value;
        }
        [$numerator, $denominator] = array_map('floatval', explode('/', $value, 2));
        return $denominator == 0.0 ? 0.0 : $numerator / $denominator;
    }

    /** An EXIF time ("2024:05:06 07:08:09") as a Unix time read as UTC. */
    private static function exifTime(string $time): string
    {
        [$date, $clock] = array_pad(explode(' ', $time, 2), 2, '00:00:00');
        $at = strtotime(str_replace(':', '-', $date) . ' ' . $clock . ' UTC');
        return $at === false ? '0' : (string) $at;
    }

    /**
     * Text made UTF-8 (Latin-1 read as such) and cleaned, every value a string.
     *
     * @param array<string, mixed> $meta
     * @param Closure(string): string $clean
     * @return array<string, mixed>
     */
    private static function cleaned(array $meta, Closure $clean): array
    {
        $utf8 = static fn (string $text): string => mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        foreach ($meta as $key => $value) {
            $meta[$key] = is_array($value) ? array_map(static fn ($word): string => $clean($utf8((string) $word)), $value) : $clean($utf8((string) $value));
        }
        return $meta;
    }
}
