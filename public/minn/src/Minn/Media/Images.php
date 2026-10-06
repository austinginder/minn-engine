<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Content\Site;
use Minn\Runtime\Runtime;

/** GD sub-size generation for the sizes the site has. */
final readonly class Images
{
    public function __construct(private Site $site)
    {
    }

    /**
     * The sizes an upload is cut into, in the order the reference writes
     * them to the metadata: the four from the site's options, then the ones
     * registered with add_image_size this request, the two big ones every
     * site has first (without plugins, just those two).
     *
     * @return array<string, array{0: int, 1: int, 2: bool}> name => [max width, max height, crop]
     */
    public function ladder(): array
    {
        $option = fn (string $name, int $default): int => (int) ($this->site->option($name) ?? $default);
        $ladder = [
            'medium' => [$option('medium_size_w', 300), $option('medium_size_h', 300), false],
            'large' => [$option('large_size_w', 1024), $option('large_size_h', 1024), false],
            'thumbnail' => [$option('thumbnail_size_w', 150), $option('thumbnail_size_h', 150), ($this->site->option('thumbnail_crop') ?? '1') === '1'],
            'medium_large' => [$option('medium_large_size_w', 768), $option('medium_large_size_h', 0), false],
        ];
        $added = ['1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false], '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false]];
        foreach (Runtime::booted() && \did_action('plugins_loaded') ? (array) ($GLOBALS['_wp_additional_image_sizes'] ?? []) : $added as $name => $size) {
            $ladder[(string) $name] = [(int) $size['width'], (int) $size['height'], (bool) $size['crop']];
        }
        return $ladder;
    }

    /**
     * The ladder after intermediate_image_sizes_advanced when plugins are
     * loaded, so a plugin can drop sizes it registered but wants only for
     * its own uploads (Gravity Forms does). The sizes are cut before the
     * attachment row exists, so the filter's attachment id is 0.
     *
     * @param array<string, array{0: int, 1: int, 2: bool}> $ladder @param array<string, mixed> $imageMeta
     * @return array<string, array{0: int, 1: int, 2: bool}>
     */
    private static function filtered(array $ladder, array $imageMeta): array
    {
        if (!Runtime::booted() || !\function_exists('apply_filters')) {
            return $ladder;
        }
        $sizes = array_map(static fn (array $size): array => ['width' => $size[0], 'height' => $size[1], 'crop' => $size[2]], $ladder);
        $out = [];
        foreach ((array) \apply_filters('intermediate_image_sizes_advanced', $sizes, $imageMeta, 0) as $name => $size) {
            if (is_array($size)) {
                $out[(string) $name] = [(int) ($size['width'] ?? 0), (int) ($size['height'] ?? 0), (bool) ($size['crop'] ?? false)];
            }
        }
        return $out;
    }

    /** Fits (w, h) inside (maxW, maxH); 0 means unconstrained. One rule with the facade's wp_constrain_dimensions. */
    public static function constrain(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        return Sizing::constrain($width, $height, $maxWidth, $maxHeight);
    }

    /** Past this width or height an upload is scaled down to it, as the reference's big_image_size_threshold default. */
    public const BIG = 2560;

    /**
     * The upload's stand-in, as the reference saves one (probe
     * image-pipeline): one past BIG scaled to fit it and saved "-scaled",
     * one a JPEG's EXIF says was taken turned saved upright "-rotated"
     * (both when both). Null when the upload is kept as it is.
     *
     * @return array{path: string, width: int, height: int, filesize: int, rotated: bool}|null
     */
    public function standIn(string $path, string $mime, int $orientation): ?array
    {
        $canvas = in_array($mime, self::SIZED, true) ? Canvas::open($path) : null;
        if ($canvas === null) {
            return null;
        }
        $big = $canvas->width > self::BIG || $canvas->height > self::BIG;
        if ($big) {
            [$width, $height] = self::constrain($canvas->width, $canvas->height, self::BIG, self::BIG);
            $canvas = $canvas->resample([0, 0, 0, 0, $width, $height, $canvas->width, $canvas->height]);
        }
        $rotated = $mime === 'image/jpeg' && $orientation > 1;
        if (!$big && !$rotated) {
            return null;
        }
        $canvas = $rotated ? self::upright($canvas, $orientation) : $canvas;
        $out = preg_replace('/\.([^.\/]+)$/', $big ? '-scaled.$1' : '-rotated.$1', $path);
        $canvas->write((string) $out, $mime, self::quality($mime));
        return ['path' => (string) $out, 'width' => $canvas->width, 'height' => $canvas->height, 'filesize' => (int) filesize((string) $out), 'rotated' => $rotated];
    }

    /** The image turned as its EXIF orientation says: a rotation counter-clockwise, then a mirror left to right. */
    private static function upright(Canvas $canvas, int $orientation): Canvas
    {
        [$angle, $mirror] = match ($orientation) {
            2 => [0, true],
            3 => [180, false],
            4 => [180, true],
            5 => [270, true],
            6 => [270, false],
            7 => [90, true],
            8 => [90, false],
            default => [0, false],
        };
        $turned = $angle ? ($canvas->rotate($angle) ?? $canvas) : $canvas;
        return $mirror ? $turned->flipHorizontal() : $turned;
    }

    /** The quality a size is written at: 86 for WebP, 82 otherwise, the reference's defaults. */
    private static function quality(string $mime): int
    {
        return $mime === 'image/webp' ? 86 : 82;
    }

    private const SIZED = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * Generates the sub-sizes for one image, cut from the upload turned
     * upright when a JPEG's EXIF says so; returns the sizes metadata map.
     * The image's metadata so far rides along for the size filter.
     *
     * @param array<string, mixed> $imageMeta
     */
    public function makeSubsizes(string $path, string $mime, array $imageMeta = [], int $orientation = 1): array
    {
        $source = in_array($mime, self::SIZED, true) ? Canvas::open($path) : null;
        if ($source === null) {
            return [];
        }
        $source = $mime === 'image/jpeg' && $orientation > 1 ? self::upright($source, $orientation) : $source;
        $width = $source->width;
        $height = $source->height;
        $dir = dirname($path);
        $stem = preg_replace('/\.[^.]+$/', '', basename($path));
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $sizes = [];
        foreach (self::filtered($this->ladder(), $imageMeta) as $name => [$maxWidth, $maxHeight, $crop]) {
            if ($crop) {
                if ($width < $maxWidth || $height < $maxHeight) {
                    continue;
                }
                // Centre crop: cover the target box, then trim.
                $scale = max($maxWidth / $width, $maxHeight / $height);
                $cropWidth = (int) round($maxWidth / $scale);
                $cropHeight = (int) round($maxHeight / $scale);
                $box = [0, 0, (int) floor(($width - $cropWidth) / 2), (int) floor(($height - $cropHeight) / 2), $maxWidth, $maxHeight, $cropWidth, $cropHeight];
            } else {
                [$targetWidth, $targetHeight] = self::constrain($width, $height, $maxWidth, $maxHeight);
                if (($targetWidth === $width && $targetHeight === $height) || $targetWidth < 1 || $targetHeight < 1) {
                    continue;
                }
                $box = [0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height];
            }
            $target = $source->resample($box);
            $file = "{$stem}-{$target->width}x{$target->height}.{$ext}";
            $out = "{$dir}/{$file}";
            $target->write($out, $mime, self::quality($mime));
            $sizes[$name] = ['file' => $file, 'width' => $target->width, 'height' => $target->height, 'mime-type' => $mime, 'filesize' => (int) filesize($out)];
        }
        return $sizes;
    }
}
