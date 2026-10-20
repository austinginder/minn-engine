<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * MPEG audio (MP3, and layers 1 and 2) as the reference's audio reader
 * measures it (probe media-meta). The first frame gives the version,
 * layer, sample rate and channels. An Xing or Info frame (written by LAME
 * and most encoders) gives the frame and byte counts: a variable bitrate is
 * the bytes over the frames' duration, and the playing time is the audio's
 * bytes less one average frame, at that bitrate. Without one the bitrate is
 * the first frame's and the playing time is every byte at it.
 */
final class Mpeg
{
    private const BITRATES = [
        '3-1' => '32 64 96 128 160 192 224 256 288 320 352 384 416 448',
        '3-2' => '32 48 56 64 80 96 112 128 160 192 224 256 320 384',
        '3-3' => '32 40 48 56 64 80 96 112 128 160 192 224 256 320',
        '2-1' => '32 48 56 64 80 96 112 128 144 160 176 192 224 256',
        '2-2' => '8 16 24 32 40 48 56 64 80 96 112 128 144 160',
    ];

    private const SAMPLE_RATES = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];

    private const CHANNEL_MODES = ['stereo', 'joint stereo', 'dual channel', 'mono'];

    private const SCAN = 131072;

    /**
     * The audio from $start (after any ID3v2 tag) to $end (before any
     * ID3v1 tag): the stream's description, its bitrate and playing time,
     * and where its frames lie; null when no frame is found.
     *
     * @return array{audio: array<string, mixed>, bitrate: int|float, playtime_seconds: int|float, avdataoffset: int, avdataend: int}|null
     */
    public static function read(Source $source, int $start, int $end): ?array
    {
        $offset = self::firstFrame($source, $start, $end);
        if ($offset === null) {
            return null;
        }
        $frame = self::frameHeader($source->bigEndian($offset, 4));
        $span = $end - $offset;
        $info = self::infoFrame($source, $offset, $frame);
        $counted = $info !== null && $info['frames'] > 0 && $info['bytes'] > 0;
        $lame = $info !== null && isset($info['method']);
        $mode = $lame ? Lame::mode($info['method']) : ($counted ? 'vbr' : 'cbr');
        $bitrate = $frame['bitrate'];
        if ($counted && !($lame && $info['method'] === 1)) {
            // A LAME method that is neither VBR nor ABR is averaged in another order, which can differ
            // in the last digit, and at 1152 samples a frame for stereo and 576 for mono, whatever the version.
            $bitrate = $lame && !in_array($mode, ['vbr', 'abr'], true)
                ? (float) $info['bytes'] * 8 / $info['frames'] * $frame['sampleRate'] / ($frame['channels'] === 1 ? 576 : 1152)
                : ($info['bytes'] / $info['frames']) * ($frame['sampleRate'] / $frame['samples']) * 8;
        }
        $playtime = ($span - ($counted ? intdiv($info['bytes'], $info['frames']) : 0)) * 8 / $bitrate;
        $audio = [
            'dataformat' => 'mp' . $frame['layer'],
            'channels' => $frame['channels'],
            'sample_rate' => $frame['sampleRate'],
            'bitrate' => $bitrate,
            'channelmode' => self::CHANNEL_MODES[$frame['mode']],
            'bitrate_mode' => $mode,
        ];
        if ($lame) {
            $audio += ['codec' => 'LAME', 'encoder' => $info['encoder']];
        }
        $audio += [
            'lossless' => false,
            'encoder_options' => Lame::options($counted || $lame ? $info : null, $frame['sampleRate'], $bitrate, $frame['bitrate']),
            'compression_ratio' => $bitrate / ($frame['channels'] * $frame['sampleRate'] * 16),
        ];
        return ['audio' => $audio, 'bitrate' => $bitrate, 'playtime_seconds' => $playtime, 'avdataoffset' => $offset, 'avdataend' => $end];
    }

    /** Where the first frame starts: a header the next one follows, at or after $start. */
    private static function firstFrame(Source $source, int $start, int $end): ?int
    {
        $window = $source->bytes($start, min(self::SCAN, $end - $start));
        for ($at = strpos($window, "\xFF"); $at !== false && $at + 4 <= strlen($window); $at = strpos($window, "\xFF", $at + 1)) {
            $frame = self::frameHeader(Source::bigEndianValue(substr($window, $at, 4)));
            if ($frame === null) {
                continue;
            }
            $next = $start + $at + $frame['length'];
            if ($next + 4 > $end || self::frameHeader($source->bigEndian($next, 4)) !== null) {
                return $start + $at;
            }
        }
        return null;
    }

    /**
     * A frame header's fields; null for one that is not valid MPEG audio:
     * no sync, a reserved version or layer, a free or bad bitrate, or a
     * reserved sample rate.
     *
     * @return array{version: int, layer: int, bitrate: int, sampleRate: int, padding: int, mode: int, channels: int, samples: int, length: int}|null
     */
    public static function frameHeader(int $word): ?array
    {
        $version = ($word >> 19) & 3;
        $layerBits = ($word >> 17) & 3;
        $bitrateIndex = ($word >> 12) & 15;
        $rateIndex = ($word >> 10) & 3;
        if (($word >> 21) !== 0x7FF || $version === 1 || $layerBits === 0 || $bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) {
            return null;
        }
        $layer = 4 - $layerBits;
        $table = $version === 3 ? "3-{$layer}" : ($layer === 1 ? '2-1' : '2-2');
        $bitrate = (int) explode(' ', self::BITRATES[$table])[$bitrateIndex - 1] * 1000;
        $sampleRate = self::SAMPLE_RATES[$version][$rateIndex];
        $padding = ($word >> 9) & 1;
        $mode = ($word >> 6) & 3;
        $samples = $layer === 1 ? 384 : ($layer === 3 && $version !== 3 ? 576 : 1152);
        $length = $layer === 1 ? (intdiv(12 * $bitrate, $sampleRate) + $padding) * 4 : intdiv($samples / 8 * $bitrate, $sampleRate) + $padding;
        return ['version' => $version, 'layer' => $layer, 'bitrate' => $bitrate, 'sampleRate' => $sampleRate, 'padding' => $padding, 'mode' => $mode, 'channels' => $mode === 3 ? 1 : 2, 'samples' => $samples, 'length' => (int) $length];
    }

    /**
     * Every frame from an offset to an end, header by header, until one is
     * not a frame: the first frame's header, the frames' mean bitrate and
     * whether any frame's rate differs from the first's; null when the first
     * is not a frame.
     *
     * @return array{first: array<string, int>, bitrate: float, variable: bool}|null
     */
    public static function scan(Source $source, int $start, int $end): ?array
    {
        $first = null;
        $total = 0;
        $count = 0;
        $variable = false;
        for ($at = $start; $at + 4 <= $end; $at += $frame['length']) {
            $frame = self::frameHeader($source->bigEndian($at, 4));
            if ($frame === null || $frame['length'] < 1) {
                break;
            }
            $first ??= $frame;
            $variable = $variable || $frame['bitrate'] !== $first['bitrate'];
            $total += $frame['bitrate'];
            $count++;
        }
        return $first === null ? null : ['first' => $first, 'bitrate' => $total / $count, 'variable' => $variable];
    }

    /**
     * The Xing or Info frame's counts and the LAME tag after them, read from
     * the first frame; null when the first frame is audio.
     *
     * @param array<string, int> $frame
     * @return array<string, mixed>|null
     */
    private static function infoFrame(Source $source, int $offset, array $frame): ?array
    {
        // The counts follow the side information; an MPEG 2.5 frame is looked into as if it were stereo.
        $side = match (true) {
            $frame['version'] === 3 => $frame['channels'] === 1 ? 17 : 32,
            $frame['version'] === 2 => $frame['channels'] === 1 ? 9 : 17,
            default => 17,
        };
        $at = $offset + 4 + $side;
        $tag = $source->bytes($at, 4);
        if ($frame['layer'] !== 3 || ($tag !== 'Xing' && $tag !== 'Info')) {
            return null;
        }
        $flags = $source->bigEndian($at + 4, 4);
        $at += 8;
        $info = ['tag' => $tag, 'frames' => 0, 'bytes' => 0, 'quality' => null];
        foreach ([1 => 'frames', 2 => 'bytes'] as $bit => $field) {
            if (($flags & $bit) !== 0) {
                $info[$field] = $source->bigEndian($at, 4);
                $at += 4;
            }
        }
        $at += ($flags & 4) !== 0 ? 100 : 0;
        if (($flags & 8) !== 0) {
            $info['quality'] = $source->bigEndian($at, 4);
            $at += 4;
        }
        return $info + Lame::fields($source->bytes($at, 36));
    }
}
