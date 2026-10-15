<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\I18n\Numbers;

/**
 * A count of bytes as a person reads it (size_format): in the largest unit
 * it reaches, from bytes to exabytes in steps of 1024, the number written
 * for the site's locale.
 */
final class ByteSize
{
    private const UNITS = ['EB' => 1024 ** 6, 'PB' => 1024 ** 5, 'TB' => 1024 ** 4, 'GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024, 'B' => 1];

    /** The size with the decimals asked for; false for less than a byte. */
    public static function format(mixed $bytes, mixed $decimals = 0): mixed
    {
        if ($bytes === 0 || $bytes === '0' || $bytes === 0.0) {
            return Numbers::format(0, $decimals) . ' B';
        }
        foreach (self::UNITS as $unit => $magnitude) {
            if ((float) $bytes >= $magnitude) {
                return Numbers::format($bytes / $magnitude, $decimals) . ' ' . $unit;
            }
        }
        return false;
    }
}
