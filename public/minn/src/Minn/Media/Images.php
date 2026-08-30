<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Content\Site;

/** GD sub-size generation from the size options the site stores. */
final readonly class Images
{
    public function __construct(private Site $site)
    {
    }

    /** @return array<string, array{0: int, 1: int, 2: bool}> name => [max width, max height, crop] */
    public function ladder(): array
    {
        $option = fn (string $name, int $default): int => (int) ($this->site->option($name) ?? $default);
        return [
            'medium' => [$option('medium_size_w', 300), $option('medium_size_h', 300), false],
            'large' => [$option('large_size_w', 1024), $option('large_size_h', 1024), false],
            'thumbnail' => [$option('thumbnail_size_w', 150), $option('thumbnail_size_h', 150), ($this->site->option('thumbnail_crop') ?? '1') === '1'],
            'medium_large' => [$option('medium_large_size_w', 768), $option('medium_large_size_h', 0), false],
        ];
    }

    /** Fits (w, h) inside (maxW, maxH); 0 means unconstrained. One rule with the facade's wp_constrain_dimensions. */
    public static function constrain(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        return Sizing::constrain($width, $height, $maxWidth, $maxHeight);
    }

    /** Generates the sub-sizes for one image; returns the sizes metadata map. */
    public function makeSubsizes(string $path, string $mime): array
    {
        $source = in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) ? Canvas::open($path) : null;
        if ($source === null) {
            return [];
        }
        $width = $source->width;
        $height = $source->height;
        $dir = dirname($path);
        $stem = preg_replace('/\.[^.]+$/', '', basename($path));
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $sizes = [];
        foreach ($this->ladder() as $name => [$maxWidth, $maxHeight, $crop]) {
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
            $target->write($out, $mime, 82);
            $sizes[$name] = ['file' => $file, 'width' => $target->width, 'height' => $target->height, 'mime-type' => $mime, 'filesize' => (int) filesize($out)];
        }
        return $sizes;
    }
}
