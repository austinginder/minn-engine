<?php

declare(strict_types=1);

namespace Minn\Media;

use GdImage;

/**
 * One GD bitmap and the operations the media layer needs on it. Every
 * operation returns a new canvas; alpha is preserved throughout, which is
 * what makes PNG and WebP sub-sizes match the reference's.
 */
final readonly class Canvas
{
    private function __construct(private GdImage $image, public int $width, public int $height)
    {
    }

    /** A canvas from an image file, or null when it cannot be decoded. */
    public static function open(string $path): ?self
    {
        $bytes = @file_get_contents($path);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (!$image) {
            return null;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return new self($image, imagesx($image), imagesy($image));
    }

    /**
     * Resamples a source box onto a destination box.
     *
     * @param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $box dst x, dst y, src x, src y, dst w, dst h, src w, src h (the GD argument order)
     */
    public function resample(array $box): self
    {
        [$dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH] = $box;
        $target = imagecreatetruecolor(max(1, $dstW), max(1, $dstH));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $this->image, $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
        return new self($target, imagesx($target), imagesy($target));
    }

    /** A canvas cut to a rectangle, resized to a target when given. */
    public function crop(int $x, int $y, int $width, int $height, ?int $targetWidth = null, ?int $targetHeight = null): self
    {
        return $this->resample([0, 0, $x, $y, $targetWidth ?? $width, $targetHeight ?? $height, $width, $height]);
    }

    /** A canvas turned by an angle, or null when GD refuses. */
    public function rotate(float $angle): ?self
    {
        $rotated = imagerotate($this->image, $angle, 0);
        return $rotated ? new self($rotated, imagesx($rotated), imagesy($rotated)) : null;
    }

    /** A canvas mirrored on either axis. */
    public function flip(bool $vertical, bool $horizontal): self
    {
        $copy = $this->resample([0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height]);
        if ($vertical) {
            imageflip($copy->image, IMG_FLIP_VERTICAL);
        }
        if ($horizontal) {
            imageflip($copy->image, IMG_FLIP_HORIZONTAL);
        }
        return $copy;
    }

    /** Writes the bitmap in the given format; the directory is created when missing. */
    public function write(string $path, string $mime, int $quality): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
        return $this->encode($mime, $path, $quality);
    }

    /** Writes the image to the output in a format, at a quality. */
    public function stream(string $mime, int $quality): bool
    {
        return $this->encode($mime, null, $quality);
    }

    private function encode(string $mime, ?string $path, int $quality): bool
    {
        return (bool) match ($mime) {
            'image/png' => imagepng($this->image, $path),
            'image/gif' => imagegif($this->image, $path),
            'image/webp' => imagewebp($this->image, $path, $quality),
            default => imagejpeg($this->image, $path, $quality),
        };
    }
}
