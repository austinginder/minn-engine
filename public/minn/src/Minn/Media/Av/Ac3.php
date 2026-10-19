<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * An AC-3 (Dolby Digital) frame's header as the reference's media reader
 * reads it (probe media-meta): the rate, bitrate and channels, the low
 * frequency channel counted as ".1". The centre mix level is read for every
 * odd coding mode, mono included, so a mono frame reads its next bits as
 * the low frequency channel; the channel modes follow the coding mode.
 */
final class Ac3
{
    private const BITRATES = [32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384, 448, 512, 576, 640];

    private const CHANNELS = [2, 1, 2, 3, 3, 4, 4, 5];

    private const MODES = ['stereo', 'mono', 'surround', 'stereo', 'stereo', 'surround', 'surround', 'surround'];

    /**
     * A frame's rate, bitrate, channels (an integer, or "n.1" with the low
     * frequency channel) and channel mode; null for bytes that are no frame.
     *
     * @return array{sampleRate: int, bitrate: int, channels: int|string, mode: string}|null
     */
    public static function frame(string $bytes): ?array
    {
        if (!str_starts_with($bytes, "\x0B\x77") || strlen($bytes) < 8) {
            return null;
        }
        $word = Source::bigEndianValue(substr($bytes, 4, 4));
        $rateCode = $word >> 30;
        $sizeCode = ($word >> 24) & 63;
        if ($rateCode === 3 || ($sizeCode >> 1) >= count(self::BITRATES)) {
            return null;
        }
        $mode = ($word >> 13) & 7;
        $bit = 12;
        $bit -= ($mode & 1) !== 0 ? 2 : 0;
        $bit -= ($mode & 4) !== 0 ? 2 : 0;
        $bit -= $mode === 2 ? 2 : 0;
        $lowFrequency = (($word >> $bit) & 1) === 1;
        $count = self::CHANNELS[$mode];
        return [
            'sampleRate' => [48000, 44100, 32000][$rateCode],
            'bitrate' => self::BITRATES[$sizeCode >> 1] * 1000,
            'channels' => $lowFrequency ? "{$count}.1" : $count,
            'mode' => self::MODES[$mode],
        ];
    }
}
