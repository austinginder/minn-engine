<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * The GNU gettext binary catalog (.mo), read and written. The file opens
 * with a magic number that also says its byte order, then a revision, the
 * message count, and the offsets of two tables of (length, offset) pairs:
 * the originals, then the translations. The entry with an empty original
 * carries the headers; a plural original is "singular NUL plural" and its
 * translations are NUL-separated; a context precedes the original with a
 * 0x04 byte.
 */
final class MoFile
{
    private const MAGIC = 0x950412de;

    /** The catalog in a .mo file's bytes, or null when they are not one. */
    public static function read(string $bytes): ?Catalog
    {
        $format = self::byteOrder($bytes);
        if ($format === null || strlen($bytes) < 20) {
            return null;
        }
        $head = unpack("{$format}count/{$format}originals/{$format}translations", $bytes, 8);
        if ($head === false) {
            return null;
        }
        $headers = [];
        $entries = [];
        for ($i = 0; $i < (int) $head['count']; $i++) {
            $original = self::string($bytes, $format, (int) $head['originals'] + $i * 8);
            $translation = self::string($bytes, $format, (int) $head['translations'] + $i * 8);
            if ($original === null || $translation === null) {
                return null;
            }
            if ($original === '') {
                $headers = self::headers($translation);
                continue;
            }
            [$key, $plural] = array_pad(explode("\0", $original, 2), 2, null);
            $entries[$key] = ['translations' => explode("\0", $translation), 'plural' => $plural];
        }
        return Catalog::from($headers, $entries);
    }

    /**
     * A .mo file's bytes for headers and entries, little-endian, entries in
     * the sorted order gettext expects.
     *
     * @param array<string, string> $headers
     * @param array<string, array{translations: list<string>, plural: ?string}> $entries
     */
    public static function write(array $headers, array $entries): string
    {
        $pairs = ['' => self::headerBlock($headers)];
        foreach ($entries as $key => $entry) {
            $pairs[$entry['plural'] === null ? (string) $key : $key . "\0" . $entry['plural']] = implode("\0", $entry['translations']);
        }
        ksort($pairs, SORT_STRING);
        $count = count($pairs);
        $originalsAt = 28;
        $translationsAt = $originalsAt + $count * 8;
        $offset = $translationsAt + $count * 8;
        $tables = ['', ''];
        $data = '';
        foreach ([array_keys($pairs), array_values($pairs)] as $table => $strings) {
            foreach ($strings as $string) {
                $string = (string) $string;
                $tables[$table] .= pack('VV', strlen($string), $offset + strlen($data));
                $data .= $string . "\0";
            }
        }
        return pack('V7', self::MAGIC, 0, $count, $originalsAt, $translationsAt, 0, $offset) . $tables[0] . $tables[1] . $data;
    }

    /** "V" for a little-endian file, "N" for a big-endian one, null for anything else. */
    private static function byteOrder(string $bytes): ?string
    {
        $magic = unpack('V', substr($bytes, 0, 4))[1] ?? 0;
        return match ($magic) {
            self::MAGIC => 'V',
            0xde120495 => 'N',
            default => null,
        };
    }

    private static function string(string $bytes, string $format, int $at): ?string
    {
        $pair = unpack("{$format}length/{$format}offset", $bytes, $at);
        if ($pair === false || $pair['offset'] + $pair['length'] > strlen($bytes)) {
            return null;
        }
        return substr($bytes, (int) $pair['offset'], (int) $pair['length']);
    }

    /** @return array<string, string> */
    private static function headers(string $block): array
    {
        $headers = [];
        foreach (explode("\n", $block) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        return $headers;
    }

    /** @param array<string, string> $headers */
    private static function headerBlock(array $headers): string
    {
        $lines = '';
        foreach ($headers as $name => $value) {
            $lines .= "{$name}: {$value}\n";
        }
        return $lines;
    }
}
