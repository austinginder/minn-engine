<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * Advanced Systems Format files (WMV, WMA, ASF) as the reference's media
 * reader measures them (probe media-meta). The file properties give the
 * playing time (less the preroll) and creation date; each stream's
 * properties its size or wave format; the codec list the names; the
 * stream bitrates the bitrate, the file's being their sum. A video file
 * with no audio still describes an empty ASF audio stream.
 */
final class Asf
{
    private const HEADER = '3026b2758e66cf11a6d900aa0062ce6c';

    private const FILE_PROPERTIES = 'a1dcab8c47a9cf118ee400c00c205365';

    private const STREAM_PROPERTIES = '9107dcb7b7a9cf118ee600c00c205365';

    private const STREAM_BITRATES = 'ce75f87b8d46d1118d82006097c9a2b2';

    private const CODEC_LIST = '4052d1861d31d011a3a400a0c90348f6';

    private const AUDIO = '409e69f84d5bcf11a8fd00805f5c442b';

    private const VIDEO = 'c0ef19bc4d5bcf11a8fd00805f5c442b';

    /** Seconds between 1601, where a Windows file time starts, and 1970. */
    private const EPOCH = 11644473600;

    /** Whether a file's first bytes are an ASF header object. */
    public static function detects(string $head): bool
    {
        return bin2hex(substr($head, 0, 16)) === self::HEADER;
    }

    /**
     * The file's analysis.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $headerSize = $source->littleEndian(16, 8);
        $count = $source->littleEndian(24, 4);
        $header = $source->bytes(30, max(0, min($headerSize, 4194304) - 30));
        $objects = [];
        for ($at = 0, $i = 0; $i < $count && $at + 24 <= strlen($header); $i++) {
            $size = Source::littleEndianValue(substr($header, $at + 16, 8));
            if ($size < 24) {
                break;
            }
            $objects[] = ['guid' => bin2hex(substr($header, $at, 16)), 'body' => substr($header, $at + 24, $size - 24)];
            $at += $size;
        }
        $properties = null;
        $streams = [];
        $bitrates = [];
        $codecs = [];
        foreach ($objects as $object) {
            match ($object['guid']) {
                self::FILE_PROPERTIES => $properties = $object['body'],
                self::STREAM_PROPERTIES => $streams[] = $object['body'],
                self::STREAM_BITRATES => $bitrates = self::bitrates($object['body']),
                self::CODEC_LIST => $codecs = self::codecs($object['body']),
                default => null,
            };
        }
        $audioStream = null;
        $videoStream = null;
        foreach ($streams as $stream) {
            $type = bin2hex(substr($stream, 0, 16));
            $number = Source::littleEndianValue(substr($stream, 48, 2)) & 0x7F;
            $specific = substr($stream, 54, Source::littleEndianValue(substr($stream, 40, 4)));
            if ($type === self::AUDIO && $audioStream === null) {
                $audioStream = ['number' => $number, 'data' => $specific];
            } elseif ($type === self::VIDEO && $videoStream === null) {
                $videoStream = ['number' => $number, 'data' => $specific];
            }
        }
        $data = ['avdataoffset' => $headerSize + 50, 'avdataend' => min($source->size, $headerSize + $source->littleEndian($headerSize + 16, 8)), 'fileformat' => 'asf'];
        $audio = $audioStream === null ? ['dataformat' => 'asf', 'lossless' => false] : self::audio($audioStream['data'], $codecs['audio'] ?? null);
        $field = static fn (int $offset): int => Source::littleEndianValue(substr((string) $properties, $offset, 8));
        $playtime = $properties === null ? null : $field(40) / 10000000 - $field(56) / 1000;
        $overall = null;
        $videoBitrate = $videoStream === null ? null : ($bitrates[$videoStream['number']] ?? null);
        if ($videoBitrate === null && $videoStream !== null && isset($audio['bitrate']) && $playtime > 0) {
            // The movie's rate is its data's over the playing time, rounded; the video has what the audio leaves.
            $overall = round(($data['avdataend'] - $data['avdataoffset']) * 8 / $playtime);
            $videoBitrate = $overall - $audio['bitrate'];
        }
        $video = $videoStream === null ? null : self::video($videoStream['data'], $videoBitrate, $codecs['video'] ?? null);
        $data['mime_type'] = $video === null ? 'audio/x-ms-wma' : ($video['dataformat'] === 'wmv' ? 'video/x-ms-wmv' : 'video/x-ms-asf');
        $data['audio'] = $audio;
        if ($video !== null) {
            $data['video'] = $video;
        }
        if ($playtime !== null) {
            $data['playtime_seconds'] = $playtime;
            $data['bitrate'] = $overall ?? ($audio['bitrate'] ?? 0) + ($videoBitrate ?? 0);
            $data['asf'] = ['file_properties_object' => ['creation_date_unix' => intdiv($field(24), 10000000) - self::EPOCH]];
        }
        return $data;
    }

    /**
     * Each stream's bitrate by its number.
     *
     * @return array<int, int>
     */
    private static function bitrates(string $body): array
    {
        $bitrates = [];
        $count = Source::littleEndianValue(substr($body, 0, 2));
        for ($i = 0; $i < $count; $i++) {
            $entry = substr($body, 2 + $i * 6, 6);
            $bitrates[Source::littleEndianValue(substr($entry, 0, 2)) & 0x7F] = Source::littleEndianValue(substr($entry, 2, 4));
        }
        return $bitrates;
    }

    /**
     * The codec list's first video and first audio name.
     *
     * @return array{video?: string, audio?: string}
     */
    private static function codecs(string $body): array
    {
        $names = [];
        $count = Source::littleEndianValue(substr($body, 16, 4));
        for ($at = 20, $i = 0; $i < $count && $at + 4 <= strlen($body); $i++) {
            $type = Source::littleEndianValue(substr($body, $at, 2)) === 1 ? 'video' : 'audio';
            $at += 2;
            $text = [];
            foreach (['name', 'description'] as $part) {
                $length = Source::littleEndianValue(substr($body, $at, 2)) * 2;
                $text[$part] = rtrim(mb_convert_encoding(substr($body, $at + 2, $length), 'UTF-8', 'UTF-16LE'), "\0");
                $at += 2 + $length;
            }
            $at += 2 + Source::littleEndianValue(substr($body, $at, 2));
            $names[$type] ??= $text['name'];
        }
        return $names;
    }

    /**
     * A video stream's size and its codec's name.
     *
     * @return array<string, mixed>
     */
    private static function video(string $specific, int|float|null $bitrate, ?string $name): array
    {
        $fourcc = substr($specific, 11 + 16, 4);
        $video = ['dataformat' => in_array(strtoupper($fourcc), ['WMV1', 'WMV2', 'WMV3', 'WVC1', 'WMVA'], true) ? 'wmv' : 'asf', 'bitrate_mode' => 'cbr'];
        if ($bitrate !== null) {
            $video['bitrate'] = (float) $bitrate;
        }
        if ($name !== null) {
            $video['encoder'] = $name;
        }
        return $video + ['lossless' => false, 'pixel_aspect_ratio' => 1.0, 'resolution_x' => Source::littleEndianValue(substr($specific, 0, 4)), 'resolution_y' => Source::littleEndianValue(substr($specific, 4, 4))];
    }

    /**
     * An audio stream described by its wave format and its codec's name.
     *
     * @return array<string, mixed>
     */
    private static function audio(string $format, ?string $name): array
    {
        $field = static fn (int $offset, int $width): int => Source::littleEndianValue(substr($format, $offset, $width));
        $tag = $field(0, 2);
        $channels = $field(2, 2);
        $rate = $field(4, 4);
        $bitrate = $field(8, 4) * 8;
        $bits = $field(14, 2);
        $audio = ['codec' => $name ?? '', 'channels' => $channels, 'sample_rate' => $rate];
        if ($bitrate > 0) {
            $audio['bitrate'] = $bitrate;
        }
        if ($bits > 0) {
            $audio['bits_per_sample'] = $bits;
        }
        $audio += ['dataformat' => match ($tag) { 0x161 => 'wma', 0x55 => 'mp3', default => 'asf' }, 'bitrate_mode' => 'cbr', 'lossless' => false, 'encoder' => $name ?? ''];
        if ($channels === 1 || $channels === 2) {
            $audio['channelmode'] = $channels === 1 ? 'mono' : 'stereo';
        }
        if ($bitrate > 0 && $channels > 0 && $rate > 0) {
            $audio['compression_ratio'] = $bitrate / ($channels * $rate * ($bits ?: 16));
        }
        return $audio;
    }
}
