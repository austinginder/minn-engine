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

    /**
     * A canvas from an image file, or null when it cannot be decoded or
     * would not fit in memory. The header names the dimensions before a
     * pixel is decoded, so a small file that claims a huge canvas is
     * refused instead of taking the request down.
     */
    public static function open(string $path): ?self
    {
        $size = @getimagesize($path);
        if ($size === false || !self::affordable((int) $size[0], (int) $size[1])) {
            return null;
        }
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
     * Whether a bitmap of these dimensions fits the memory left to this
     * request: five bytes a pixel for the decoded image (GD keeps four and
     * a little), twice over because every operation makes a second canvas,
     * under what memory_limit leaves after what is already in use.
     */
    private static function affordable(int $width, int $height): bool
    {
        if ($width <= 0 || $height <= 0) {
            return false;
        }
        $needed = $width * $height * 5 * 2;
        $limit = self::memoryLimit();
        $room = $limit === null ? 512 * 1048576 : $limit - memory_get_usage(true) - 16 * 1048576;
        return $needed <= $room;
    }

    /** memory_limit in bytes, or null when it is unlimited. */
    private static function memoryLimit(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }
        $unit = strtolower(substr($raw, -1));
        $number = (int) $raw;
        return match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
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
    public function flipVertical(): self
    {
        return $this->flipped(IMG_FLIP_VERTICAL);
    }

    /** A canvas mirrored left to right. */
    public function flipHorizontal(): self
    {
        return $this->flipped(IMG_FLIP_HORIZONTAL);
    }

    /** An untouched copy of the canvas. */
    public function copy(): self
    {
        return $this->resample([0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height]);
    }

    private function flipped(int $mode): self
    {
        $copy = $this->copy();
        imageflip($copy->image, $mode);
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
