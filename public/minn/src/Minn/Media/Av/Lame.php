<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * The LAME tag an encoder writes after the Xing or Info counts, and what
 * the reference's audio reader makes of it (probe media-meta): the bitrate
 * method names the bitrate mode, and the encoder options are guessed from
 * the preset number, or failing that from the mode, then the minimum
 * bitrate, gapless encoding, a lowpass other than the preset's own and a
 * resampled rate follow.
 */
final class Lame
{
    private const MODES = [0 => 'unk', 1 => 'cbr', 2 => 'abr', 3 => 'vbr', 4 => 'vbr', 5 => 'vbr', 6 => 'vbr', 8 => 'cbr', 9 => 'abr', 15 => 'res'];

    /** Preset numbers from 8 to 319 that name no preset, so the mode's guess stands. */
    private const UNNAMED = [16, 24, 40, 56, 112, 128, 160, 192, 256];

    private const NAMED = [
        320 => 'insane', 410 => '-V9', 420 => '-V8', 430 => 'radio', 440 => '-V6', 450 => 'portable', 460 => 'medium',
        470 => '--r3mix', 480 => 'standard', 490 => '-V1', 500 => 'extreme', 1000 => '--r3mix', 1001 => '--alt-preset standard',
        1002 => '--alt-preset extreme', 1003 => '--alt-preset insane', 1004 => '--alt-preset fast standard', 1005 => '--alt-preset fast extreme',
        1006 => '--alt-preset medium', 1007 => '--alt-preset fast medium', 1010 => '--preset portable', 1015 => '--preset radio',
    ];

    /** Each preset's own lowpass, which its options leave unsaid; a preset named here may add a different one. */
    private const LOWPASS = [
        320 => [20500], 450 => [], 460 => [18000], 470 => [18000, 19500], 480 => [19000], 500 => [19500], 1000 => [18000, 19500],
        1001 => [19000], 1002 => [19500], 1003 => [20500], 1004 => [19000], 1005 => [19500], 1006 => [18000], 1007 => [18000], 1010 => [],
    ];

    /**
     * The tag's fields from its 36 bytes: the encoder, the bitrate method,
     * lowpass, encoding flags, the minimum or average bitrate, the source's
     * sample rate class and the preset number. An encoder that is not LAME
     * leaves only its name.
     *
     * @return array<string, mixed>
     */
    public static function fields(string $tag): array
    {
        $encoder = rtrim(substr($tag, 0, 9), " \0");
        if (strlen($tag) < 36 || !str_starts_with($encoder, 'LAME')) {
            return ['encoder' => ''];
        }
        return [
            'encoder' => $encoder === 'LAME3.90.' ? 'LAME3.90.3' : $encoder,
            'method' => ord($tag[9]) & 15,
            'lowpass' => ord($tag[10]) * 100,
            'flags' => ord($tag[19]) >> 4,
            'minimum' => ord($tag[20]),
            'sourceRate' => ord($tag[24]) >> 6,
            'preset' => Source::bigEndianValue(substr($tag, 26, 2)) & 0x7FF,
        ];
    }

    /** The bitrate mode a LAME tag's method names ("cbr", "abr", "vbr"; "unk", "res" or empty for the rest). */
    public static function mode(int $method): string
    {
        return self::MODES[$method] ?? '';
    }

    /**
     * The options a file was most likely encoded with, from its LAME tag
     * (or "CBR" and the bitrate without an Xing frame, "VBR" with one from
     * another encoder).
     *
     * @param array<string, mixed>|null $info the Xing or Info frame and its LAME tag
     */
    public static function options(?array $info, int $sampleRate, int|float $bitrate, int $headerBitrate): string
    {
        if ($info === null) {
            return 'CBR' . intdiv($headerBitrate, 1000);
        }
        if (($info['method'] ?? null) === null) {
            return 'VBR';
        }
        $method = (int) $info['method'];
        $preset = (int) $info['preset'];
        $options = self::presetName($preset, $method) ?? self::guess($method, $info['quality'], $bitrate);
        if ($method !== 1 && $method !== 2 && $info['minimum'] > 0) {
            $options .= ' -b' . $info['minimum'];
        }
        if (($info['flags'] & 0x0C) !== 0) {
            $options .= ' --nogap';
        }
        $lowpass = (int) $info['lowpass'];
        if (isset(self::LOWPASS[$preset]) && $lowpass > 0 && $lowpass < min($sampleRate / 2 - 550, 22050) && !in_array($lowpass, self::LOWPASS[$preset], true)) {
            $options .= ' --lowpass ' . $lowpass;
        }
        // The radio preset is made at 32 kHz, so that rate is its own rather than a resample.
        $radio = ($preset === 430 || $preset === 1015) && $sampleRate === 32000;
        if (!$radio && self::resampled((int) $info['sourceRate'], $sampleRate)) {
            $options .= ' --resample ' . $sampleRate;
        }
        return $options;
    }

    /** A preset number's name, or null when it names none and the mode's guess stands. */
    private static function presetName(int $preset, int $method): ?string
    {
        if ($preset === 0 || in_array($preset, self::UNNAMED, true)) {
            return null;
        }
        if ($preset >= 8 && $preset < 320) {
            return '--alt-preset ' . ($method === 1 ? 'cbr ' : '') . $preset;
        }
        $name = self::NAMED[$preset] ?? null;
        if ($name === null) {
            return "new/unknown preset: {$preset} - report to info@getid3.org";
        }
        if (str_starts_with($name, '-')) {
            return $name;
        }
        return '--preset ' . ($method === 4 && $preset !== 320 && $preset !== 430 ? 'fast ' : '') . $name;
    }

    /** The options a mode suggests without a preset: the constant rate, "ABR", or the VBR level and quality. */
    private static function guess(int $method, ?int $quality, int|float $bitrate): string
    {
        $mode = self::mode($method);
        if ($mode === 'cbr') {
            return 'CBR' . (int) ceil($bitrate / 1000);
        }
        if ($mode === 'abr') {
            return 'ABR';
        }
        if ($mode === 'vbr') {
            $scale = 100 - (int) $quality;
            $level = (int) floor($scale / 10);
            return (int) $quality === 0 ? 'VBR' : sprintf('-V%d -q%d', $level, $scale - $level * 10);
        }
        return strtoupper($mode);
    }

    /** Whether the stream's rate differs from the source's class (32 kHz or less, 44.1, 48, more than 48). */
    private static function resampled(int $class, int $sampleRate): bool
    {
        return match ($class) {
            0 => $sampleRate > 32000,
            1 => $sampleRate !== 44100,
            2 => $sampleRate !== 48000,
            default => $sampleRate <= 48000,
        };
    }
}
