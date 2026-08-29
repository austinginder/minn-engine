<?php

declare(strict_types=1);

namespace Minn\Media;

use Closure;

/**
 * The image size arithmetic the media functions share: the crop or scale a
 * resize needs, the registered size a requested box picks, and the srcset
 * candidates an image's sizes yield. Behaviour pinned by contracts/fixtures/api/media.json.
 */
final class Sizing
{
    /**
     * The GD-style resize box (dst x, dst y, src x, src y, dst w, dst h, src w, src h),
     * or null when the image would only grow or nothing changes.
     *
     * @param Closure(int, int, int, int): array{0: int, 1: int} $constrain scales a box into a maximum
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|null
     */
    public static function resize(int $origW, int $origH, int $destW, int $destH, bool $crop, Closure $constrain): ?array
    {
        if ($origW < $destW && $origH < $destH) {
            return null;
        }
        if ($crop) {
            $ratio = $origW / $origH;
            $newW = min($destW, $origW);
            $newH = min($destH, $origH);
            if (!$newW) {
                $newW = (int) round($newH * $ratio);
            }
            if (!$newH) {
                $newH = (int) round($newW / $ratio);
            }
            $sizeRatio = max($newW / $origW, $newH / $origH);
            $cropW = (int) round($newW / $sizeRatio);
            $cropH = (int) round($newH / $sizeRatio);
            $sx = (int) floor(($origW - $cropW) / 2);
            $sy = (int) floor(($origH - $cropH) / 2);
        } else {
            [$cropW, $cropH, $sx, $sy] = [$origW, $origH, 0, 0];
            [$newW, $newH] = $constrain($origW, $origH, $destW, $destH);
        }
        if ($newW >= $origW && $newH >= $origH && $destW !== $origW && $destH !== $origH) {
            return null;
        }
        if ($newW === $origW && $newH === $origH) {
            return null;
        }
        return [0, 0, $sx, $sy, (int) $newW, (int) $newH, (int) $cropW, (int) $cropH];
    }

    /**
     * The registered size that serves a request: by name, or the smallest
     * size that covers a requested box (the full size never counts), with
     * its path and URL beside the file's.
     *
     * @param array<string, mixed> $meta attachment metadata
     * @param string|array{0: int, 1: int} $size
     * @param Closure(int, int, array): array{0: int, 1: int} $editorConstrain
     * @return array<string, mixed>|null
     */
    public static function intermediate(array $meta, string|array $size, ?string $fileUrl, Closure $editorConstrain): ?array
    {
        if (empty($meta['sizes'])) {
            return null;
        }
        $data = [];
        if (is_array($size)) {
            if (!isset($meta['file']) && isset($meta['sizes']['full'])) {
                $meta['height'] = $meta['sizes']['full']['height'];
                $meta['width'] = $meta['sizes']['full']['width'];
            }
            $candidates = [];
            foreach ($meta['sizes'] as $row) {
                if (!empty($meta['width']) && !empty($meta['height']) && (int) $row['width'] === (int) $meta['width'] && (int) $row['height'] === (int) $meta['height']) {
                    continue;
                }
                if ($row['width'] >= $size[0] && $row['height'] >= $size[1]) {
                    $candidates[$row['width'] * $row['height']] = $row;
                    if ((int) $row['width'] === (int) $size[0] && (int) $row['height'] === (int) $size[1]) {
                        break;
                    }
                }
            }
            if ($candidates !== []) {
                ksort($candidates);
                $data = array_shift($candidates);
            } elseif (!empty($meta['sizes']['thumbnail']) && $meta['sizes']['thumbnail']['width'] >= $size[0] && $meta['sizes']['thumbnail']['width'] >= $size[1]) {
                $data = $meta['sizes']['thumbnail'];
            } else {
                return null;
            }
            [$data['width'], $data['height']] = $editorConstrain((int) $data['width'], (int) $data['height'], $size);
        } elseif (!empty($meta['sizes'][$size])) {
            $data = $meta['sizes'][$size];
        }
        if ($data === []) {
            return null;
        }
        if (!isset($data['path']) && !empty($data['file']) && !empty($meta['file'])) {
            $data['path'] = self::join(dirname((string) $meta['file']), (string) $data['file']);
            $data['url'] = self::join(dirname((string) $fileUrl), (string) $data['file']);
        }
        return $data;
    }

    /**
     * The srcset candidates by width: every size on the image's ratio (and
     * the same edit, when the file was edited), the source itself winning a
     * width clash.
     *
     * @param array{0: int, 1: int} $sizeArray
     * @param Closure(int, int, int, int): bool $matchesRatio
     * @return array<int, array{url: string, descriptor: string, value: int}>
     */
    public static function sources(array $sizeArray, string $src, array $meta, Closure $matchesRatio): array
    {
        if (empty($meta['sizes']) || !isset($meta['file']) || !str_contains((string) $meta['file'], '.')) {
            return [];
        }
        $width = (int) $sizeArray[0];
        $height = (int) $sizeArray[1];
        if (!$width || !$height) {
            return [];
        }
        $sizes = $meta['sizes'];
        $sizes['full'] = ['width' => $meta['width'], 'height' => $meta['height'], 'file' => basename((string) $meta['file'])];
        $baseUrl = str_replace(basename($src), '', $src);
        $edited = preg_match('/-e[0-9]{13}/', basename((string) $meta['file']), $editHash);
        $sources = [];
        foreach ($sizes as $image) {
            if (!is_array($image) || !isset($image['file'], $image['width'], $image['height'])) {
                continue;
            }
            if (str_contains((string) $image['file'], '.') && $edited && !str_contains((string) $image['file'], $editHash[0])) {
                continue;
            }
            $isSrc = basename($src) === $image['file'];
            if (!$matchesRatio($width, $height, (int) $image['width'], (int) $image['height'])) {
                continue;
            }
            if (isset($sources[$image['width']]) && !$isSrc) {
                continue;
            }
            $sources[(int) $image['width']] = ['url' => $baseUrl . $image['file'], 'descriptor' => 'w', 'value' => (int) $image['width']];
        }
        return $sources;
    }

    /** The srcset attribute for at least two sources; null for fewer. */
    public static function srcset(array $sources): ?string
    {
        if (count($sources) < 2) {
            return null;
        }
        $parts = [];
        foreach ($sources as $source) {
            $parts[] = str_replace(' ', '%20', (string) $source['url']) . ' ' . $source['value'] . $source['descriptor'];
        }
        return implode(', ', $parts);
    }

    /** The image sizes an attachment offers the editor, each with orientation. @param Closure(string): (array|false) $downsize */
    public static function editorSizes(array $meta, string $baseUrl, string $fullUrl, array $names, Closure $downsize): array
    {
        $orientation = static fn (int|float $h, int|float $w): string => $h > $w ? 'portrait' : 'landscape';
        $sizes = [];
        foreach (array_keys($names) as $size) {
            if ($size === 'full') {
                continue;
            }
            $down = $downsize((string) $size);
            if ($down && $down[3]) {
                $sizes[$size] = ['height' => $down[2], 'width' => $down[1], 'url' => $down[0], 'orientation' => $orientation($down[2], $down[1])];
            } elseif (isset($meta['sizes'][$size])) {
                $info = $meta['sizes'][$size];
                $sizes[$size] = ['height' => $info['height'], 'width' => $info['width'], 'url' => $baseUrl . $info['file'], 'orientation' => $orientation($info['height'], $info['width'])];
            }
        }
        if (isset($meta['width'])) {
            $sizes['full'] = ['url' => $fullUrl, 'height' => $meta['height'], 'width' => $meta['width'], 'orientation' => $orientation($meta['height'], $meta['width'])];
        }
        return $sizes;
    }

    private static function join(string $base, string $path): string
    {
        return rtrim($base, '/') . '/' . $path;
    }
}
