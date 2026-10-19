<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * Ogg files as the reference's media reader measures them (probe
 * media-meta): Vorbis, Opus, or FLAC in its first packet. The audio is
 * counted from the end of the comment header to the start of the last
 * page, and the last page's granule position over the rate is the playing
 * time (Opus's pre-skip included). A Vorbis stream's options are the
 * quality its nominal bitrate stands for, when libVorbis made it in stereo
 * at 44.1 or 48 kHz, and otherwise the nominal bitrate itself.
 */
final class Ogg
{
    private const SCAN = 1048576;

    /**
     * The file's analysis.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $first = self::page($source, 0);
        $data = ['fileformat' => 'ogg', 'mime_type' => 'application/ogg'];
        if ($first === null) {
            return $data;
        }
        $packet = $first['body'];
        if (str_starts_with($packet, "\x7FFLAC")) {
            $info = Flac::streamInfo(substr($packet, 17));
            return $info === null ? $data : $data + ['audio' => Flac::stream($info, null, self::flacVendor($source, $first['end']))];
        }
        $vorbis = str_starts_with($packet, "\x01vorbis");
        if (!$vorbis && !str_starts_with($packet, 'OpusHead')) {
            return $data;
        }
        $second = self::page($source, $first['end']);
        $comment = $second === null ? '' : $second['packet'];
        $vendor = self::vendor($comment, $vorbis ? 7 : 8);
        // A Vorbis comment header's framing bit is left out of it, so the audio starts one byte early.
        $start = $second === null ? $first['end'] : $second['start'] + $second['head'] + strlen($comment) - ($vorbis ? 1 : 0);
        $last = self::lastPage($source);
        $channels = ord($packet[$vorbis ? 11 : 9] ?? "\0");
        $rate = $vorbis ? Source::littleEndianValue(substr($packet, 12, 4)) : 48000;
        $playtime = $last !== null && $rate > 0 && $last['granule'] > 0 ? $last['granule'] / $rate : null;
        $end = $last['start'] ?? $source->size;
        $bitrate = $playtime ? ($end - $start) * 8 * $rate / $last['granule'] : null;
        $data = ['avdataoffset' => $start, 'avdataend' => $end] + $data;
        if (!$vorbis) {
            $data['mime_type'] = 'audio/ogg; codecs=opus';
        }
        $audio = $vorbis ? ['dataformat' => 'vorbis', 'lossless' => false, 'channels' => $channels, 'sample_rate' => $rate, 'bitrate_mode' => 'vbr']
            : ['dataformat' => 'opus', 'bitrate_mode' => 'vbr', 'lossless' => false, 'channels' => $channels, 'sample_rate_input' => Source::littleEndianValue(substr($packet, 12, 4)), 'sample_rate' => 48000];
        if ($bitrate !== null) {
            $audio['bitrate'] = $bitrate;
        }
        if ($vendor !== null) {
            $audio['encoder'] = $vendor;
        }
        $options = $vorbis ? self::vorbisOptions(Source::littleEndianValue(substr($packet, 20, 4)), $channels, $rate, (string) $vendor) : null;
        if ($options !== null) {
            $audio['encoder_options'] = $options;
        }
        if ($channels === 1 || $channels === 2) {
            $audio['channelmode'] = $channels === 1 ? 'mono' : 'stereo';
        }
        if ($bitrate !== null && $channels > 0) {
            $audio['compression_ratio'] = $bitrate / ($channels * $rate * 16);
        }
        $data['audio'] = $audio;
        if ($playtime !== null) {
            $data['playtime_seconds'] = $playtime;
            $data['bitrate'] = $bitrate;
        }
        return $data;
    }

    /**
     * A page at an offset: where it starts and ends, its header length,
     * granule position, its whole body and its first packet.
     *
     * @return array{start: int, end: int, head: int, granule: int, body: string, packet: string}|null
     */
    private static function page(Source $source, int $at): ?array
    {
        $head = $source->bytes($at, 27);
        if (strlen($head) < 27 || !str_starts_with($head, 'OggS')) {
            return null;
        }
        $segments = ord($head[26]);
        $lacing = $source->bytes($at + 27, $segments);
        $length = array_sum(array_map('ord', str_split($lacing)) ?: [0]);
        $packet = 0;
        foreach (str_split($lacing) as $value) {
            $packet += ord($value);
            if (ord($value) < 255) {
                break;
            }
        }
        $body = $source->bytes($at + 27 + $segments, $length);
        return ['start' => $at, 'end' => $at + 27 + $segments + $length, 'head' => 27 + $segments, 'granule' => Source::littleEndianValue(substr($head, 6, 8)), 'body' => $body, 'packet' => substr($body, 0, $packet)];
    }

    /**
     * The last page of the file, found from its end.
     *
     * @return array{start: int, end: int, head: int, granule: int, body: string, packet: string}|null
     */
    private static function lastPage(Source $source): ?array
    {
        $from = max(0, $source->size - self::SCAN);
        $tail = $source->bytes($from, $source->size - $from);
        for ($at = strrpos($tail, 'OggS'); $at !== false; $at = $at > 0 ? strrpos(substr($tail, 0, $at), 'OggS') : false) {
            $page = self::page($source, $from + $at);
            if ($page !== null) {
                return $page;
            }
        }
        return null;
    }

    /** The vendor string a comment header carries after its $skip-byte signature. */
    private static function vendor(string $comment, int $skip): ?string
    {
        if (strlen($comment) < $skip + 4) {
            return null;
        }
        return substr($comment, $skip + 4, Source::littleEndianValue(substr($comment, $skip, 4)));
    }

    /** The vendor of an Ogg FLAC stream's comment block, in the page after its first. */
    private static function flacVendor(Source $source, int $at): ?string
    {
        $page = self::page($source, $at);
        if ($page === null || (ord($page['packet'][0] ?? "\0") & 0x7F) !== 4) {
            return null;
        }
        return self::vendor($page['packet'], 4);
    }

    /** A Vorbis stream's options: its quality, or its nominal bitrate, or none without one. */
    private static function vorbisOptions(int $nominal, int $channels, int $rate, string $vendor): ?string
    {
        if ($channels === 2 && ($rate === 44100 || $rate === 48000) && str_starts_with($vendor, 'Xiph.Org libVorbis')) {
            $quality = match (true) {
                $nominal <= 128000 => $nominal / 16000 - 4,
                $nominal <= 256000 => 4 + ($nominal - 128000) / 32000,
                $nominal <= 320000 => 8 + ($nominal - 256000) / 64000,
                default => 9 + ($nominal - 320000) / 180000,
            };
            return '-q ' . round($quality, 1);
        }
        return $nominal > 0 ? 'Nominal bitrate: ' . round($nominal / 1000) . 'kbps' : null;
    }
}
