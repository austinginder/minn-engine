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

    /** Fits (w, h) inside (maxW, maxH); 0 means unconstrained. Rounds like the reference. */
    public static function constrain(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratios = [];
        if ($maxWidth > 0) {
            $ratios[] = $maxWidth / $width;
        }
        if ($maxHeight > 0) {
            $ratios[] = $maxHeight / $height;
        }
        $ratio = $ratios === [] ? 1 : min($ratios);
        if ($ratio >= 1) {
            return [$width, $height];
        }
        return [(int) round($width * $ratio), (int) round($height * $ratio)];
    }

    /** Generates the sub-sizes for one image; returns the sizes metadata map. */
    public function makeSubsizes(string $path, string $mime): array
    {
        [$width, $height] = getimagesize($path);
        $source = match ($mime) {
            'image/png' => imagecreatefrompng($path),
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => null,
        };
        if (!$source) {
            return [];
        }
        imagesavealpha($source, true);
        $dir = dirname($path);
        $stem = preg_replace('/\.[^.]+$/', '', basename($path));
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $sizes = [];

        foreach ($this->ladder() as $name => [$maxWidth, $maxHeight, $crop]) {
            if ($crop) {
                if ($width < $maxWidth || $height < $maxHeight) {
                    continue;
                }
                $targetWidth = $maxWidth;
                $targetHeight = $maxHeight;
                // Centre crop: cover the target box, then trim.
                $scale = max($maxWidth / $width, $maxHeight / $height);
                $cropWidth = (int) round($maxWidth / $scale);
                $cropHeight = (int) round($maxHeight / $scale);
                $sourceX = (int) floor(($width - $cropWidth) / 2);
                $sourceY = (int) floor(($height - $cropHeight) / 2);
            } else {
                [$targetWidth, $targetHeight] = self::constrain($width, $height, $maxWidth, $maxHeight);
                if (($targetWidth === $width && $targetHeight === $height) || $targetWidth < 1 || $targetHeight < 1) {
                    continue;
                }
                $sourceX = 0;
                $sourceY = 0;
                $cropWidth = $width;
                $cropHeight = $height;
            }
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
            $file = "{$stem}-{$targetWidth}x{$targetHeight}.{$ext}";
            $out = "{$dir}/{$file}";
            match ($mime) {
                'image/png' => imagepng($target, $out),
                'image/jpeg' => imagejpeg($target, $out, 82),
                'image/gif' => imagegif($target, $out),
                'image/webp' => imagewebp($target, $out, 82),
            };
            imagedestroy($target);
            $sizes[$name] = [
                'file' => $file,
                'width' => $targetWidth,
                'height' => $targetHeight,
                'mime-type' => $mime,
                'filesize' => (int) filesize($out),
            ];
        }
        imagedestroy($source);
        return $sizes;
    }
}
