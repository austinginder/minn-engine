<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * A WebP file's size and kind from its first 40 bytes, as wp_get_webp_info
 * reads them (probe plugin-queue8): a simple lossy frame (VP8), a lossless
 * one (VP8L), or the extended form (VP8X), which the reference names
 * animated-alpha whatever its flags say. Anything shorter or of another
 * chunk is unknown.
 */
final class WebpInfo
{
    /** The width, height and kind a file's first bytes declare. @return array{width: int|false, height: int|false, type: string|false} */
    public static function read(string $head): array
    {
        $unknown = ['width' => false, 'height' => false, 'type' => false];
        if (strlen($head) < 40) {
            return $unknown;
        }
        switch (substr($head, 12, 4)) {
            case 'VP8 ':
                $parts = (array) unpack('v2', substr($head, 26, 4));
                return ['width' => $parts[1] & 0x3FFF, 'height' => $parts[2] & 0x3FFF, 'type' => 'lossy'];
            case 'VP8L':
                $b = (array) unpack('C4', substr($head, 21, 4));
                return ['width' => ($b[1] | (($b[2] & 0x3F) << 8)) + 1, 'height' => ((($b[2] & 0xC0) >> 6) | ($b[3] << 2) | (($b[4] & 0x03) << 10)) + 1, 'type' => 'lossless'];
            case 'VP8X':
                $width = (array) unpack('V', substr($head, 24, 3) . "\x00");
                $height = (array) unpack('V', substr($head, 27, 3) . "\x00");
                return ['width' => ($width[1] & 0xFFFFFF) + 1, 'height' => ($height[1] & 0xFFFFFF) + 1, 'type' => 'animated-alpha'];
        }
        return $unknown;
    }
}
