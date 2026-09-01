<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * The _wp_attachment_metadata blob: parsed by scanning for the shapes it
 * holds (top-level dims and file, the sizes map, the image_meta scalars)
 * and written back in the reference's stored form: a:6 at the top,
 * image_meta as a:13 with alt last.
 */
final class Metadata
{
    private const IMAGE_META_KEYS = [
        'aperture', 'credit', 'camera', 'caption', 'created_timestamp', 'copyright',
        'focal_length', 'iso', 'shutter_speed', 'title', 'orientation',
    ];

    /**
     * The attachment metadata blob as an array, tolerant of anything missing.
     *
     * @return array{width: int, height: int, file: string, filesize: int, sizes: array<string, array>, image_meta: ?array}
     */
    public static function parse(?string $blob): array
    {
        $meta = ['width' => 0, 'height' => 0, 'file' => '', 'filesize' => 0, 'sizes' => [], 'image_meta' => null];
        if ($blob === null || $blob === '') {
            return $meta;
        }
        $head = substr($blob, 0, strpos($blob, '"sizes"') ?: strlen($blob));
        foreach (['width', 'height', 'filesize'] as $key) {
            if (preg_match('/s:' . strlen($key) . ':"' . $key . '";i:(\d+);/', $head, $m)) {
                $meta[$key] = (int) $m[1];
            }
        }
        if (preg_match('/s:4:"file";s:\d+:"([^"]*)";/', $head, $m)) {
            $meta['file'] = $m[1];
        }

        $sizesPattern = '/s:\d+:"([^"]+)";a:\d+:\{s:4:"file";s:\d+:"([^"]*)";s:5:"width";i:(\d+);s:6:"height";i:(\d+);s:9:"mime-type";s:\d+:"([^"]*)";(?:s:8:"filesize";i:(\d+);)?\}/';
        if (preg_match('/s:5:"sizes";a:\d+:\{(.*)\}s:10:"image_meta"/s', $blob, $m)
            || preg_match('/s:5:"sizes";a:\d+:\{(.*)\}\}$/s', $blob, $m)) {
            if (preg_match_all($sizesPattern, $m[1], $all, PREG_SET_ORDER)) {
                foreach ($all as $size) {
                    $meta['sizes'][$size[1]] = [
                        'file' => $size[2],
                        'width' => (int) $size[3],
                        'height' => (int) $size[4],
                        'mime-type' => $size[5],
                    ] + (isset($size[6]) && $size[6] !== '' ? ['filesize' => (int) $size[6]] : []);
                }
            }
        }

        if (str_contains($blob, '"image_meta"')) {
            $image = [];
            foreach (self::IMAGE_META_KEYS as $key) {
                $image[$key] = '';
                if (preg_match('/s:' . strlen($key) . ':"' . $key . '";s:\d+:"([^"]*)";/', $blob, $m)) {
                    $image[$key] = $m[1];
                }
            }
            $image['keywords'] = [];
            $image['alt'] = preg_match('/s:3:"alt";s:\d+:"([^"]*)";/', $blob, $m) ? $m[1] : '';
            $meta['image_meta'] = $image;
        }
        return $meta;
    }

    /** The metadata as the reference's serialized blob, without unserialize ever being needed. */
    public static function serialize(array $meta): string
    {
        $string = static fn (string $value): string => 's:' . strlen($value) . ':"' . $value . '";';
        $pair = static fn (string $key, string $encoded): string => $string($key) . $encoded;

        $sizes = '';
        foreach ($meta['sizes'] as $name => $size) {
            $entry = $pair('file', $string($size['file']))
                . $pair('width', 'i:' . $size['width'] . ';')
                . $pair('height', 'i:' . $size['height'] . ';')
                . $pair('mime-type', $string($size['mime-type']))
                . (isset($size['filesize']) ? $pair('filesize', 'i:' . $size['filesize'] . ';') : '');
            $sizes .= $string((string) $name) . 'a:' . (isset($size['filesize']) ? 5 : 4) . ':{' . $entry . '}';
        }

        $image = $meta['image_meta'];
        $imageMeta = '';
        foreach (self::IMAGE_META_KEYS as $key) {
            $imageMeta .= $pair($key, $string((string) $image[$key]));
        }
        $imageMeta .= $pair('keywords', 'a:0:{}') . $pair('alt', $string((string) ($image['alt'] ?? '')));

        return 'a:6:{'
            . $pair('width', 'i:' . $meta['width'] . ';')
            . $pair('height', 'i:' . $meta['height'] . ';')
            . $pair('file', $string($meta['file']))
            . $pair('filesize', 'i:' . $meta['filesize'] . ';')
            . $pair('sizes', 'a:' . count($meta['sizes']) . ':{' . $sizes . '}')
            . $pair('image_meta', 'a:13:{' . $imageMeta . '}')
            . '}';
    }

    /** The image_meta block a fresh upload carries. */
    public static function blankImageMeta(): array
    {
        return [
            'aperture' => '0',
            'credit' => '',
            'camera' => '',
            'caption' => '',
            'created_timestamp' => '0',
            'copyright' => '',
            'focal_length' => '0',
            'iso' => '0',
            'shutter_speed' => '0',
            'title' => '',
            'orientation' => '0',
            'keywords' => [],
            'alt' => '',
        ];
    }
}
