<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * An AVIF file's primary image as wp_get_avif_info reports it (probe
 * media-tags): width and height from its spatial-extent property, bit depth
 * and channel count from its pixel-information property. The file is an
 * ISO base media file: an ftyp box naming the avif or avis brand, then a meta
 * box whose primary item (pitm) is tied to its properties (ipco) by the
 * association table (ipma). Anything else, or a file it cannot follow, is
 * unknown in every field.
 */
final class AvifInfo
{
    private const UNKNOWN = ['width' => false, 'height' => false, 'bit_depth' => false, 'num_channels' => false];

    /**
     * The primary image's size, bit depth and channels from a file's first bytes.
     *
     * @return array{width: int|false, height: int|false, bit_depth: int|false, num_channels: int|false}
     */
    public static function read(string $bytes): array
    {
        $top = self::boxes($bytes, 0, strlen($bytes));
        if (!isset($top['ftyp']) || !self::isAvif($bytes, $top['ftyp'][0])) {
            return self::UNKNOWN;
        }
        $meta = $top['meta'][0] ?? null;
        if ($meta === null) {
            return self::UNKNOWN;
        }
        $children = self::boxes($bytes, $meta[0] + 4, $meta[1]);
        $primary = isset($children['pitm']) ? self::primaryItem($bytes, $children['pitm'][0]) : null;
        $iprp = $children['iprp'][0] ?? null;
        if ($primary === null || $iprp === null) {
            return self::UNKNOWN;
        }
        $iprpChildren = self::boxes($bytes, $iprp[0], $iprp[1]);
        if (!isset($iprpChildren['ipco'], $iprpChildren['ipma'])) {
            return self::UNKNOWN;
        }
        $properties = self::ordered($bytes, $iprpChildren['ipco'][0][0], $iprpChildren['ipco'][0][1]);
        $info = self::UNKNOWN;
        foreach (self::associations($bytes, $iprpChildren['ipma'][0], $primary) as $index) {
            [$type, $start] = $properties[$index - 1] ?? ['', 0];
            if ($type === 'ispe' && $info['width'] === false) {
                $size = (array) unpack('N2', substr($bytes, $start + 4, 8));
                [$info['width'], $info['height']] = [(int) $size[1], (int) $size[2]];
            }
            if ($type === 'pixi' && $info['num_channels'] === false) {
                $channels = ord($bytes[$start + 4] ?? "\0");
                $info['num_channels'] = $channels;
                $info['bit_depth'] = $channels > 0 ? ord($bytes[$start + 5] ?? "\0") : false;
            }
        }
        return $info;
    }

    /** Whether the ftyp box names the avif or avis brand, as its major brand or a compatible one. */
    private static function isAvif(string $bytes, array $ftyp): bool
    {
        [$start, $end] = $ftyp;
        for ($at = $start; $at + 4 <= $end; $at += 4) {
            // The major brand, then the minor version (skipped), then the compatible brands.
            if ($at !== $start + 4 && in_array(substr($bytes, $at, 4), ['avif', 'avis'], true)) {
                return true;
            }
        }
        return false;
    }

    /** The primary item's id from a pitm box: 16 bits in version 0, 32 after. */
    private static function primaryItem(string $bytes, array $pitm): ?int
    {
        $version = ord($bytes[$pitm[0]] ?? "\0");
        $field = substr($bytes, $pitm[0] + 4, $version === 0 ? 2 : 4);
        $id = (array) unpack($version === 0 ? 'n' : 'N', str_pad($field, $version === 0 ? 2 : 4, "\0"));
        return (int) $id[1];
    }

    /**
     * The 1-based property indices the ipma box ties to one item.
     *
     * @param array{0: int, 1: int} $ipma the box's content span
     * @return list<int>
     */
    private static function associations(string $bytes, array $ipma, int $item): array
    {
        [$at, $end] = $ipma;
        $version = ord($bytes[$at] ?? "\0");
        $wide = (ord($bytes[$at + 3] ?? "\0") & 1) === 1;
        $count = (int) ((array) unpack('N', substr($bytes, $at + 4, 4) ?: "\0\0\0\0"))[1];
        $at += 8;
        for ($entry = 0; $entry < $count && $at < $end; $entry++) {
            $idLength = $version < 1 ? 2 : 4;
            $id = (int) ((array) unpack($idLength === 2 ? 'n' : 'N', str_pad(substr($bytes, $at, $idLength), $idLength, "\0")))[1];
            $links = ord($bytes[$at + $idLength] ?? "\0");
            $at += $idLength + 1;
            $indices = [];
            for ($link = 0; $link < $links; $link++) {
                // The top bit marks the property essential; the rest is its index.
                $indices[] = $wide ? ((int) ((array) unpack('n', substr($bytes, $at, 2) ?: "\0\0"))[1]) & 0x7FFF : ord($bytes[$at] ?? "\0") & 0x7F;
                $at += $wide ? 2 : 1;
            }
            if ($id === $item) {
                return $indices;
            }
        }
        return [];
    }

    /**
     * The boxes between two offsets by type, each as its content span (after
     * the header). A box of size 1 carries a 64-bit size; size 0 runs to the end.
     *
     * @return array<string, list<array{0: int, 1: int}>>
     */
    private static function boxes(string $bytes, int $from, int $to): array
    {
        $found = [];
        foreach (self::ordered($bytes, $from, $to) as [$type, $start, $end]) {
            $found[$type][] = [$start, $end];
        }
        return $found;
    }

    /**
     * The boxes between two offsets in file order: type, content start, end.
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private static function ordered(string $bytes, int $from, int $to): array
    {
        $boxes = [];
        $at = $from;
        while ($at + 8 <= $to) {
            $size = (int) ((array) unpack('N', substr($bytes, $at, 4)))[1];
            $type = substr($bytes, $at + 4, 4);
            $header = 8;
            if ($size === 1 && $at + 16 <= $to) {
                $size = (int) ((array) unpack('J', substr($bytes, $at + 8, 8)))[1];
                $header = 16;
            } elseif ($size === 0) {
                $size = $to - $at;
            }
            if ($size < $header || $at + $size > $to) {
                break;
            }
            $boxes[] = [$type, $at + $header, $at + $size];
            $at += $size;
        }
        return $boxes;
    }
}
