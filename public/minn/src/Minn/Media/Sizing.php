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
     * The largest box inside the limits that keeps the ratio: only sides over
     * their limit shrink, the smaller ratio wins, and a result one pixel short
     * of a limit it was constrained by snaps to that limit.
     *
     * @return array{0: int, 1: int}
     */
    public static function constrain(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if (!$maxWidth && !$maxHeight) {
            return [$width, $height];
        }
        $widthRatio = $maxWidth > 0 && $width > $maxWidth ? $maxWidth / $width : 1.0;
        $heightRatio = $maxHeight > 0 && $height > $maxHeight ? $maxHeight / $height : 1.0;
        $ratio = min($widthRatio, $heightRatio);
        $w = max(1, (int) round($width * $ratio));
        $h = max(1, (int) round($height * $ratio));
        if ($widthRatio < 1.0 && $w === $maxWidth - 1) {
            $w = $maxWidth;
        }
        if ($heightRatio < 1.0 && $h === $maxHeight - 1) {
            $h = $maxHeight;
        }
        return [$w, $h];
    }

    /**
     * The GD-style resize box (dst x, dst y, src x, src y, dst w, dst h, src w, src h),
     * or null when every asked dimension is larger than the image (nothing to make).
     *
     * @param Closure(int, int, int, int): array{0: int, 1: int} $constrain scales a box into a maximum
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|null
     */
    public static function resize(int $origW, int $origH, int $destW, int $destH, bool $crop, Closure $constrain): ?array
    {
        $beyond = static fn (int $asked, int $has): bool => $asked <= 0 || $asked > $has;
        if ($beyond($destW, $origW) && $beyond($destH, $origH)) {
            return null;
        }
        if (!$crop) {
            [$width, $height] = $constrain($origW, $origH, $destW, $destH);
            return [0, 0, 0, 0, (int) $width, (int) $height, $origW, $origH];
        }
        [$width, $height] = self::cropBox($origW, $origH, $destW, $destH);
        // The part of the original that is kept: the box's shape, as large as the original allows, centred.
        $scale = max($width / $origW, $height / $origH);
        $keptW = (int) round($width / $scale);
        $keptH = (int) round($height / $scale);
        return [0, 0, (int) floor(($origW - $keptW) / 2), (int) floor(($origH - $keptH) / 2), $width, $height, $keptW, $keptH];
    }

    /**
     * A cropped size's box: each side at most the original's, and a side asked as 0 drawn from the
     * other in the original's proportion.
     *
     * @return array{0: int, 1: int}
     */
    private static function cropBox(int $origW, int $origH, int $destW, int $destH): array
    {
        $width = min($destW, $origW);
        $height = min($destH, $origH);
        $proportion = $origW / $origH;
        return match (0) {
            $width => [(int) round($height * $proportion), $height],
            $height => [$width, (int) round($width / $proportion)],
            default => [$width, $height],
        };
    }

    /**
     * Whether a resize box makes the image's own size again, a pixel either
     * way counting as rounding.
     *
     * @param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $box
     */
    public static function sameSize(array $box, int $origW, int $origH): bool
    {
        return abs($box[4] - $origW) <= 1 && abs($box[5] - $origH) <= 1;
    }

    /**
     * The registered size that serves a request: by name, or for a
     * requested box the size of exactly that box, else the smallest larger
     * one in its proportion (the full size never counts), else the
     * thumbnail when it is wide enough; with its path and URL beside the
     * file's.
     *
     * @param array<string, mixed> $meta attachment metadata
     * @param string|array{0: int, 1: int} $size
     * @param Closure(int, int, array): array{0: int, 1: int} $editorConstrain
     * @param Closure(int, int, int, int): bool $matchesRatio
     * @return array<string, mixed>|null
     */
    public static function intermediate(array $meta, string|array $size, ?string $fileUrl, Closure $editorConstrain, Closure $matchesRatio): ?array
    {
        if (empty($meta['sizes'])) {
            return null;
        }
        $data = [];
        if (is_array($size)) {
            // Metadata without a file of its own (a PDF's previews) takes its measure from the 'full' size.
            $full = isset($meta['file']) ? null : ($meta['sizes']['full'] ?? null);
            if ($full !== null) {
                $meta = ['width' => $full['width'], 'height' => $full['height']] + $meta;
            }
            $original = !empty($meta['width']) && !empty($meta['height']) ? [(int) $meta['width'], (int) $meta['height']] : null;
            $asked = [(int) $size[0], (int) $size[1]];
            // A larger size counts only in the requested proportion (the original's when a side is 0).
            $shape = in_array(0, $asked, true) ? [(int) ($meta['width'] ?? 0), (int) ($meta['height'] ?? 0)] : $asked;
            $candidates = [];
            foreach ($meta['sizes'] as $row) {
                $measure = [(int) $row['width'], (int) $row['height']];
                if ($measure === $original) {
                    continue;
                }
                // A size of exactly the asked box ends the search; it is also the smallest.
                $exact = $measure === $asked;
                if ($exact || ($row['width'] >= $size[0] && $row['height'] >= $size[1] && $matchesRatio(...$measure, ...$shape))) {
                    $candidates[$row['width'] * $row['height']] = $row;
                }
                if ($exact) {
                    break;
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
     * the same edit, when the file was edited) no wider than the cap, the
     * source itself winning a width clash and passing the cap.
     *
     * @param array{0: int, 1: int} $sizeArray
     * @param Closure(int, int, int, int): bool $matchesRatio
     * @param Closure(array{0: int, 1: int}): int $maxWidth the widest candidate (max_srcset_image_width; 0 for no cap), asked before the folder is checked
     * @return array<int, array{url: string, descriptor: string, value: int}>
     */
    public static function sources(array $sizeArray, string $src, array $meta, Closure $matchesRatio, string $uploadsUrl, Closure $maxWidth): array
    {
        if (empty($meta['sizes']) || !isset($meta['file']) || !str_contains((string) $meta['file'], '.')) {
            return [];
        }
        $width = (int) $sizeArray[0];
        $height = (int) $sizeArray[1];
        if (!$width || !$height) {
            return [];
        }
        $cap = $maxWidth([$width, $height]);
        // The source must name the image's folder and file (any host will do); the candidates
        // are addressed from the uploads folder, not from the source (probe media-remaining).
        $folder = dirname((string) $meta['file']);
        $folder = $folder === '.' ? '' : $folder . '/';
        if (!str_contains($src, $folder . basename($src))) {
            return [];
        }
        $sizes = $meta['sizes'];
        $sizes['full'] = ['width' => $meta['width'], 'height' => $meta['height'], 'file' => basename((string) $meta['file'])];
        $baseUrl = rtrim($uploadsUrl, '/') . '/' . $folder;
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
            if ($cap > 0 && (int) $image['width'] > $cap && !$isSrc) {
                continue;
            }
            if (!$matchesRatio($width, $height, (int) $image['width'], (int) $image['height'])) {
                continue;
            }
            if (isset($sources[$image['width']]) && !$isSrc) {
                continue;
            }
            $source = ['url' => $baseUrl . $image['file'], 'descriptor' => 'w', 'value' => (int) $image['width']];
            // The image's own source leads the list, as the reference orders it.
            $sources = $isSrc ? [(int) $image['width'] => $source] + $sources : $sources + [(int) $image['width'] => $source];
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

    /**
     * The old thumbnail an image's metadata names (its `thumb`), as a file
     * beside the image with its size, while it is still there (probe
     * image-downsize).
     *
     * @return array{0: string, 1: int, 2: int}|null the file's name, width and height
     */
    public static function oldThumbnail(string $imagefile, string $thumb): ?array
    {
        if ($thumb === '') {
            return null;
        }
        $thumbfile = str_replace(basename($imagefile), basename($thumb), $imagefile);
        $info = is_file($thumbfile) ? @getimagesize($thumbfile) : false;
        return $info ? [basename($thumbfile), (int) $info[0], (int) $info[1]] : null;
    }
}
