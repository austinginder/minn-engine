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
     * them to the metadata: the four from the site's options, the two big
     * ones every site has, then any a theme or plugin registered with
     * add_image_size this request.
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
            '1536x1536' => [1536, 1536, false],
            '2048x2048' => [2048, 2048, false],
        ];
        foreach (Runtime::booted() ? (array) Runtime::current()->get('image_sizes', []) : [] as $name => $size) {
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

    /**
     * Generates the sub-sizes for one image; returns the sizes metadata map.
     * The image's metadata so far rides along for the size filter.
     *
     * @param array<string, mixed> $imageMeta
     */
    public function makeSubsizes(string $path, string $mime, array $imageMeta = []): array
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
            $target->write($out, $mime, 82);
            $sizes[$name] = ['file' => $file, 'width' => $target->width, 'height' => $target->height, 'mime-type' => $mime, 'filesize' => (int) filesize($out)];
        }
        return $sizes;
    }
}
