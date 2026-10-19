<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * Matroska and WebM files (MKV, MKA, WebM) as the reference's media reader
 * measures them (probe media-meta). The segment information gives the
 * playing time and the date; the first video and audio tracks give the
 * streams, named by their codec where the reader knows it (and by the
 * codec identifier where it does not). MP3 and MP2 audio is described
 * from its first frame, FLAC from its stream information, AC-3 from its
 * first frame; the bitrate is the file's over its playing time, the video
 * keeping what a known audio bitrate leaves.
 */
final class Matroska
{
    private const SEGMENT = 0x18538067;

    private const CONTAINERS = [0x1A45DFA3, 0x18538067, 0x1549A966, 0x1654AE6B, 0xAE, 0xE0, 0xE1];

    private const VIDEO = ['V_VP8' => 'vp8', 'V_MPEG4/ISO/AVC' => 'h264', 'V_MPEG4/ISO/ASP' => 'mpeg4', 'V_MS/VFW/FOURCC' => 'vcm'];

    private const AUDIO = ['A_AAC' => 'aac', 'A_AC3' => 'ac3', 'A_MPEG/L3' => 'mp3', 'A_MPEG/L2' => 'mp2', 'A_FLAC' => 'flac', 'A_PCM/INT/LIT' => 'pcm'];

    private const HEAD = 262144;

    /** Seconds between 1970 and 2001, where a Matroska date is counted from. */
    private const EPOCH = 978307200;

    /** Whether a file's first bytes are an EBML header. */
    public static function detects(string $head): bool
    {
        return str_starts_with($head, "\x1A\x45\xDF\xA3");
    }

    /**
     * The file's analysis.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $bytes = $source->bytes(0, self::HEAD);
        $found = ['doctype' => 'matroska', 'scale' => 1000000, 'duration' => null, 'date' => null, 'tracks' => [], 'segment' => null];
        self::walk($bytes, 0, strlen($bytes), $found);
        $webm = $found['doctype'] === 'webm';
        $data = ['avdataoffset' => 0, 'avdataend' => $source->size, 'fileformat' => $webm ? 'webm' : 'matroska'];
        $video = null;
        $audio = null;
        foreach ($found['tracks'] as $track) {
            if (($track['type'] ?? 0) === 1 && $video === null) {
                $video = $track;
            } elseif (($track['type'] ?? 0) === 2 && $audio === null) {
                $audio = $track;
            }
        }
        $data['mime_type'] = ($video === null ? 'audio/' : 'video/') . ($webm ? 'webm' : 'x-matroska');
        $playtime = $found['duration'] !== null ? $found['duration'] * ($found['scale'] / 1000000000) : null;
        $overall = $playtime ? $source->size * 8 / $playtime : null;
        $audioStream = $audio === null ? null : self::audio($audio, $bytes, $found['segment']);
        if ($audioStream !== null) {
            if ($video === null && $overall !== null) {
                $audioStream['bitrate'] = $overall;
                $audioStream['compression_ratio'] = $overall / ((float) $audioStream['channels'] * $audioStream['sample_rate'] * $audioStream['bits_per_sample']);
            }
            $data['audio'] = $audioStream;
        }
        if ($video !== null) {
            $data['video'] = self::video($video, $overall === null ? null : ($audioStream === null ? $overall : (isset($audioStream['bitrate']) ? $overall - $audioStream['bitrate'] : null)));
        }
        if ($playtime !== null) {
            $data['playtime_seconds'] = $playtime;
            $data['bitrate'] = $overall;
        }
        if ($found['date'] !== null) {
            $data['matroska'] = ['info' => [['DateUTC_unix' => self::EPOCH + intdiv($found['date'], 1000000000)]]];
        }
        return $data;
    }

    /**
     * The elements from $at to $end that matter here, gathered into $found.
     *
     * @param array<string, mixed> $found
     */
    private static function walk(string $bytes, int $at, int $end, array &$found, ?array &$track = null): void
    {
        while ($at < $end) {
            $id = self::identifier($bytes, $at);
            $size = self::size($bytes, $at);
            if ($id === null || $size === null) {
                return;
            }
            $size = min($size, $end - $at);
            if ($id === self::SEGMENT) {
                $found['segment'] = $at;
            }
            if ($id === 0xAE) {
                $entry = [];
                self::walk($bytes, $at, $at + $size, $found, $entry);
                $found['tracks'][] = $entry;
            } elseif (in_array($id, self::CONTAINERS, true)) {
                self::walk($bytes, $at, $at + $size, $found, $track);
            } else {
                self::element($id, substr($bytes, $at, $size), $found, $track);
            }
            if ($id === 0x1F43B675) {
                return;
            }
            $at += $size;
        }
    }

    /**
     * One element's value kept where it belongs.
     *
     * @param array<string, mixed> $found
     * @param array<string, mixed>|null $track
     */
    private static function element(int $id, string $value, array &$found, ?array &$track): void
    {
        $unsigned = static fn (): int => Source::bigEndianValue($value);
        match ($id) {
            0x4282 => $found['doctype'] = $value,
            0x2AD7B1 => $found['scale'] = $unsigned(),
            0x4489 => $found['duration'] = self::float($value),
            0x4461 => $found['date'] = self::signed($value),
            default => null,
        };
        if ($track === null) {
            return;
        }
        $key = [0xD7 => 'number', 0x83 => 'type', 0x86 => 'codec', 0x63A2 => 'private', 0x22B59C => 'language', 0x23E383 => 'frameDuration', 0xB0 => 'width', 0xBA => 'height', 0x54B0 => 'displayWidth', 0x54BA => 'displayHeight', 0x54B2 => 'displayUnit', 0xB5 => 'rate', 0x9F => 'channels', 0x6264 => 'depth'][$id] ?? null;
        if ($key !== null) {
            $track[$key] = match ($key) {
                'codec', 'private', 'language' => $value,
                'rate' => self::float($value),
                default => $unsigned(),
            };
        }
    }

    /**
     * A track's audio as the reference describes it: the codec's name, rate,
     * channels, language and depth, then what the codec's own reader adds.
     *
     * @param array<string, mixed> $track
     * @return array<string, mixed>
     */
    private static function audio(array $track, string $bytes, ?int $segment): array
    {
        $codec = (string) ($track['codec'] ?? '');
        $audio = ['dataformat' => self::AUDIO[$codec] ?? $codec, 'sample_rate' => $track['rate'] ?? 8000.0, 'channels' => $track['channels'] ?? 1];
        if (isset($track['language'])) {
            $audio['language'] = $track['language'];
        }
        $audio['bits_per_sample'] = $track['depth'] ?? 16;
        if ($codec === 'A_FLAC') {
            $info = Flac::streamInfo(substr((string) ($track['private'] ?? ''), 8));
            if ($info !== null) {
                $audio = ['sample_rate' => $info['sampleRate'], 'channels' => $info['channels'], 'bits_per_sample' => $info['bits'], 'bitrate_mode' => 'vbr', 'lossless' => true] + $audio;
                $audio = array_merge(['dataformat' => 'flac', 'sample_rate' => 0, 'channels' => 0], array_diff_key($audio, ['bits_per_sample' => 0, 'bitrate_mode' => 0, 'lossless' => 0]), ['bits_per_sample' => $info['bits'], 'bitrate_mode' => 'vbr', 'lossless' => true]);
            }
        } elseif ($codec === 'A_MPEG/L3' || $codec === 'A_MPEG/L2') {
            $frame = self::firstBlock($bytes, $segment, (int) ($track['number'] ?? 0));
            $header = $frame === null ? null : Mpeg::frameHeader(Source::bigEndianValue(substr($frame, 0, 4)));
            if ($header !== null) {
                $audio = ['sample_rate' => $header['sampleRate'], 'channels' => $header['channels']] + $audio;
                $audio = array_merge(['dataformat' => $audio['dataformat']], $audio);
                $audio += ['bitrate' => $header['bitrate'], 'channelmode' => ['stereo', 'joint stereo', 'dual channel', 'mono'][$header['mode']], 'bitrate_mode' => 'cbr', 'lossless' => false, 'encoder_options' => 'CBR' . intdiv($header['bitrate'], 1000), 'compression_ratio' => $header['bitrate'] / ($header['channels'] * $header['sampleRate'] * 16)];
                return $audio;
            }
        } elseif ($codec === 'A_AC3') {
            $frame = self::firstBlock($bytes, $segment, (int) ($track['number'] ?? 0));
            $header = $frame === null ? null : Ac3::frame($frame);
            if ($header !== null) {
                $audio = array_merge(['dataformat' => $audio['dataformat']], ['sample_rate' => $header['sampleRate'], 'channels' => $header['channels']] + $audio);
                $audio += ['bitrate_mode' => 'cbr', 'lossless' => false, 'bitrate' => $header['bitrate'], 'channelmode' => $header['mode'], 'compression_ratio' => $header['bitrate'] / ((float) $header['channels'] * $header['sampleRate'] * $audio['bits_per_sample'])];
                return $audio;
            }
        } elseif ($codec === 'A_PCM/INT/LIT') {
            $audio['bitrate'] = $audio['sample_rate'] * $audio['channels'] * $audio['bits_per_sample'];
        }
        if ($audio['channels'] === 1 || $audio['channels'] === 2) {
            $audio['channelmode'] = $audio['channels'] === 1 ? 'mono' : 'stereo';
        }
        if ($codec === 'A_PCM/INT/LIT') {
            $audio['compression_ratio'] = $audio['bitrate'] / ($audio['channels'] * $audio['sample_rate'] * $audio['bits_per_sample']);
        }
        return $audio;
    }

    /**
     * A track's video: its codec's name (an AVI codec's named as AVI does),
     * size, display size, frame rate and the bitrate it has.
     *
     * @param array<string, mixed> $track
     * @return array<string, mixed>
     */
    private static function video(array $track, int|float|null $bitrate): array
    {
        $codec = (string) ($track['codec'] ?? '');
        $video = ['dataformat' => self::VIDEO[$codec] ?? $codec, 'resolution_x' => $track['width'] ?? 0, 'resolution_y' => $track['height'] ?? 0, 'display_unit' => ($track['displayUnit'] ?? 0) === 0 ? 'pixels' : 'unknown', 'display_x' => $track['displayWidth'] ?? $track['width'] ?? 0, 'display_y' => $track['displayHeight'] ?? $track['height'] ?? 0];
        if (!empty($track['frameDuration'])) {
            $video['frame_rate'] = 1000000000 / $track['frameDuration'];
        }
        if ($codec === 'V_MS/VFW/FOURCC') {
            $name = Riff::videoCodec(substr((string) ($track['private'] ?? ''), 16, 4));
            if ($name !== '') {
                $video['codec'] = $name;
            }
        }
        if ($bitrate !== null) {
            $video['bitrate'] = $bitrate;
        }
        return $video;
    }

    /** The first frame of a track, from the first cluster's blocks. */
    private static function firstBlock(string $bytes, ?int $segment, int $number): ?string
    {
        $at = strpos($bytes, "\x1F\x43\xB6\x75", $segment ?? 0);
        if ($at === false) {
            return null;
        }
        $at += 4;
        $size = self::size($bytes, $at) ?? 0;
        $end = min(strlen($bytes), $at + $size);
        while ($at < $end) {
            $id = self::identifier($bytes, $at);
            $length = self::size($bytes, $at);
            if ($id === null || $length === null) {
                return null;
            }
            if ($id === 0xA0) {
                $inner = $at;
                $innerId = self::identifier($bytes, $inner);
                $innerLength = self::size($bytes, $inner);
                if ($innerId === 0xA1 && $innerLength !== null) {
                    $block = self::blockFor(substr($bytes, $inner, $innerLength), $number);
                    if ($block !== null) {
                        return $block;
                    }
                }
            } elseif ($id === 0xA3) {
                $block = self::blockFor(substr($bytes, $at, $length), $number);
                if ($block !== null) {
                    return $block;
                }
            }
            $at += $length;
        }
        return null;
    }

    /** A block's payload when it belongs to the track numbered $number. */
    private static function blockFor(string $block, int $number): ?string
    {
        $at = 0;
        $track = self::size($block, $at);
        return $track === $number ? substr($block, $at + 3) : null;
    }

    /** An element id at $at, which it moves past: a variable-length number with its marker kept. */
    private static function identifier(string $bytes, int &$at): ?int
    {
        $first = ord($bytes[$at] ?? "\0");
        if ($first === 0) {
            return null;
        }
        $length = 8 - (int) floor(log($first, 2));
        $value = $first;
        for ($i = 1; $i < $length; $i++) {
            $value = ($value << 8) | ord($bytes[$at + $i] ?? "\0");
        }
        $at += $length;
        return $value;
    }

    /** An element size at $at, which it moves past: its marker dropped, all ones (an unknown size) read as the most there can be. */
    private static function size(string $bytes, int &$at): ?int
    {
        $first = ord($bytes[$at] ?? "\0");
        if ($first === 0) {
            return null;
        }
        $length = 8 - (int) floor(log($first, 2));
        $value = $first & (0xFF >> $length);
        $all = $value === (0xFF >> $length);
        for ($i = 1; $i < $length; $i++) {
            $byte = ord($bytes[$at + $i] ?? "\0");
            $value = ($value << 8) | $byte;
            $all = $all && $byte === 0xFF;
        }
        $at += $length;
        return $all ? PHP_INT_MAX : $value;
    }

    /** A float element's value (four or eight bytes, big-endian). */
    private static function float(string $value): float
    {
        return match (strlen($value)) {
            4 => (float) unpack('G', $value)[1],
            8 => (float) unpack('E', $value)[1],
            default => 0.0,
        };
    }

    /** A signed big-endian integer element's value. */
    private static function signed(string $value): int
    {
        $unsigned = Source::bigEndianValue($value);
        $bits = strlen($value) * 8;
        return $bits < 64 && $unsigned >= (1 << ($bits - 1)) ? $unsigned - (1 << $bits) : $unsigned;
    }
}
