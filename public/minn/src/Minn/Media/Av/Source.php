<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * A media file read in parts, so a large video is never loaded whole: its
 * size, a run of bytes at an offset, and the integers the containers write.
 * The file's first block is kept, since every reader starts there.
 */
final class Source
{
    private const HEAD = 262144;

    private string $head;

    /** @param resource $handle */
    private function __construct(private $handle, public readonly int $size, public readonly string $path)
    {
        $this->head = (string) fread($this->handle, self::HEAD);
    }

    /** The file opened for reading, or null when it cannot be. */
    public static function open(string $path): ?self
    {
        $handle = is_file($path) ? @fopen($path, 'rb') : false;
        if ($handle === false) {
            return null;
        }
        $size = fstat($handle)['size'] ?? 0;
        return new self($handle, (int) $size, $path);
    }

    /** Up to $length bytes from $offset (fewer at the end of the file). */
    public function bytes(int $offset, int $length): string
    {
        if ($offset < 0 || $length <= 0 || $offset >= $this->size) {
            return '';
        }
        if ($offset + $length <= strlen($this->head)) {
            return substr($this->head, $offset, $length);
        }
        fseek($this->handle, $offset);
        // No more is asked of the file than it holds: a declared length is not an allocation.
        return (string) fread($this->handle, min($length, $this->size - $offset));
    }

    /** A big-endian unsigned integer of $width bytes (1 to 8) at $offset; 0 past the end. */
    public function bigEndian(int $offset, int $width): int
    {
        return self::bigEndianValue(str_pad($this->bytes($offset, $width), $width, "\0", STR_PAD_LEFT));
    }

    /** A little-endian unsigned integer of $width bytes (1 to 8) at $offset; 0 past the end. */
    public function littleEndian(int $offset, int $width): int
    {
        return self::littleEndianValue($this->bytes($offset, $width));
    }

    /** The unsigned integer a run of bytes spells, most significant first. */
    public static function bigEndianValue(string $bytes): int
    {
        $value = 0;
        foreach (str_split($bytes) as $byte) {
            $value = ($value << 8) | ord($byte);
        }
        return $value;
    }

    /** The unsigned integer a run of bytes spells, least significant first. */
    public static function littleEndianValue(string $bytes): int
    {
        return $bytes === '' ? 0 : self::bigEndianValue(strrev($bytes));
    }

    /** The file let go. */
    public function close(): void
    {
        fclose($this->handle);
    }
}
