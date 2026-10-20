<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * MPEG-4 and QuickTime files (MP4, M4A, M4V, MOV) as the reference's media
 * reader measures them (probe media-meta). The movie header gives the
 * playing time and the time it was made; each track's first sample entry
 * gives its codec. Only AAC ("mp4a") is read as audio, and it alone makes a
 * file audio/mp4 (or video/mp4 beside a video track); anything else is
 * video/quicktime. A video entry with a vendor is read the QuickTime way
 * (its compressor's name, lossless false); one without, the ISO way (a
 * named codec, and "mp4" as the format for the codecs it knows). Only H.264
 * and MPEG-4 video have the bitrate the audio leaves.
 */
final class IsoMedia
{
    /** Video codecs the ISO way names, and makes a file "mp4" for (code => name, empty for none). */
    private const VIDEO = 'avc1=H.264/MPEG-4 AVC|cvid=Cinepak|dvc =DVC-NTSC|dvcp=DVC-PAL|gif =GIF|h263=H263|hvc1=H.265/HEVC|jpeg=JPEG|kpcd=PhotoCD|mjpa=Motion JPEG-A|mjpb=Motion JPEG-B|mp4v=|png =PNG|raw =RAW|rle =|rpza=Video|smc =Graphics|SVQ1=Sorenson Video 3|SVQ3=Sorenson Video 3 (Apple Quicktime 5)|tiff=TIFF|v216=|v308=|v408=|v410=|yuv2=ComponentVideo';

    private const DATAFORMATS = ['mp4v' => 'mpeg4', '3IV1' => '3ivx', '3IV2' => '3ivx', '3ivx' => '3ivx', 'DIVX' => 'divx', 'divx' => 'divx', 'DIV3' => 'divx', 'div3' => 'divx', 'XVID' => 'xvid', 'xvid' => 'xvid'];

    private const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'udta', 'ilst', 'covr'];

    /** Seconds between 1904, where the movie's clock starts, and 1970. */
    private const EPOCH = 2082844800;

    private const MOOV_LIMIT = 67108864;

    /** The most boxes read at one level (the file's, or a container's). */
    private const BOX_LIMIT = 4096;

    /** Whether a file's first bytes are an MPEG-4 or QuickTime box. */
    public static function detects(string $head): bool
    {
        return in_array(substr($head, 4, 4), ['ftyp', 'moov', 'mdat', 'free', 'wide', 'skip', 'pnot'], true);
    }

    /**
     * The file's analysis: format, type, playing time and bitrate, the
     * audio and video streams, the movie header for its creation time and
     * the cover.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $movie = self::movie($source);
        $data = ['fileformat' => 'quicktime', 'mime_type' => 'video/quicktime'];
        if ($movie['dataStart'] !== null) {
            $data = ['avdataoffset' => $movie['dataStart'], 'avdataend' => $source->size] + $data;
        }
        if ($movie['moov'] === null) {
            return $data;
        }
        $moov = self::children($movie['moov'], 0, strlen($movie['moov']));
        $tracks = array_map(static fn (array $trak): array => self::track($movie['moov'], $trak), array_values(array_filter($moov, static fn (array $box): bool => $box['type'] === 'trak')));
        $header = self::movieHeader($movie['moov'], $moov);
        $playtime = $header !== null && $header['timescale'] > 0 ? $header['duration'] / $header['timescale'] : null;
        $overall = $playtime && $movie['dataStart'] !== null ? ($source->size - $movie['dataStart']) * 8 / $playtime : null;
        $audio = null;
        $video = ['dataformat' => 'quicktime', 'rotate' => 0, 'resolution_x' => 0.0, 'resolution_y' => 0.0];
        $videoEntry = null;
        foreach ($tracks as $track) {
            if ($track['entry'] === null) {
                continue;
            }
            if ($track['entry']['kind'] === 'renaming') {
                $video['codec'] = $track['entry']['codec'];
            } elseif ($track['entry']['kind'] === 'audio' && $audio === null) {
                $audio = $track['entry']['audio'];
            } elseif ($track['entry']['kind'] === 'video' && $videoEntry === null) {
                $videoEntry = $track['entry'];
                $video = $track['entry']['video'] + $video;
                if ($track['entry']['mp4']) {
                    $data['fileformat'] = 'mp4';
                }
            }
        }
        if ($audio !== null) {
            $data['fileformat'] = 'mp4';
            $data['mime_type'] = $videoEntry === null ? 'audio/mp4' : 'video/mp4';
        }
        if ($overall !== null) {
            if ($audio === null && $videoEntry === null) {
                $audio = ['bitrate' => $overall];
            } elseif ($audio === null) {
                $video['bitrate'] = $overall;
            } elseif (isset($audio['bitrate']) && $videoEntry !== null && in_array($videoEntry['fourcc'], ['avc1', 'mp4v'], true)) {
                $video['bitrate'] = $overall - $audio['bitrate'];
            }
        }
        $data += array_filter(['audio' => $audio, 'video' => $data['mime_type'] === 'audio/mp4' ? null : self::ordered($video)], static fn (?array $part): bool => $part !== null);
        if ($playtime !== null) {
            $data['playtime_seconds'] = $playtime;
        }
        if ($overall !== null) {
            $data['bitrate'] = $overall;
        }
        $first = $moov[0] ?? null;
        $data['quicktime'] = ['moov' => ['subatoms' => $first === null ? [] : [['name' => $first['type']] + ($first['type'] === 'mvhd' && $header !== null ? ['creation_time_unix' => $header['created'] - self::EPOCH] : [])]]];
        $cover = self::cover($movie['moov'], $moov);
        if ($cover !== null) {
            $data['comments']['picture'] = [$cover];
        }
        return $data;
    }

    /**
     * The movie box's bytes (null when there is none, or it is too large to
     * read) and where the first media data begins.
     *
     * @return array{moov: ?string, dataStart: ?int}
     */
    private static function movie(Source $source): array
    {
        $moov = null;
        $dataStart = null;
        // The walk ends once both are found, and after a file's worth of boxes (real ones hold a few dozen; fragments a few thousand).
        for ($at = 0, $count = 0; $at + 8 <= $source->size && ($moov === null || $dataStart === null) && $count < self::BOX_LIMIT; $count++) {
            [$size, $type, $head] = self::boxHeader($source->bytes($at, 16), $source->size - $at);
            if ($size < $head) {
                break;
            }
            if ($type === 'mdat' && $dataStart === null) {
                $dataStart = $at + $head;
            }
            if ($type === 'moov' && $moov === null && $size <= self::MOOV_LIMIT) {
                $moov = $source->bytes($at + $head, $size - $head);
            }
            $at += $size;
        }
        return ['moov' => $moov, 'dataStart' => $dataStart];
    }

    /**
     * A box's size, type and header length from its first bytes; a size of
     * 0 runs to $room, a size of 1 is the 64-bit size that follows.
     *
     * @return array{0: int, 1: string, 2: int}
     */
    private static function boxHeader(string $bytes, int $room): array
    {
        $size = Source::bigEndianValue(substr($bytes, 0, 4));
        $type = substr($bytes, 4, 4);
        if ($size === 1) {
            return [Source::bigEndianValue(substr($bytes, 8, 8)), $type, 16];
        }
        return [$size === 0 ? $room : $size, $type, 8];
    }

    /**
     * The boxes inside a run of bytes, each with its body's place.
     *
     * @return list<array{type: string, start: int, end: int}>
     */
    private static function children(string $bytes, int $start, int $end): array
    {
        $boxes = [];
        $at = $start;
        while ($at + 8 <= $end && count($boxes) < self::BOX_LIMIT) {
            [$size, $type, $head] = self::boxHeader(substr($bytes, $at, 16), $end - $at);
            if ($size < $head || $at + $size > $end) {
                break;
            }
            $boxes[] = ['type' => $type, 'start' => $at + $head, 'end' => $at + $size];
            $at += $size;
        }
        return $boxes;
    }

    /**
     * The first box of a type along a path of containers, or null.
     *
     * @param list<array{type: string, start: int, end: int}> $boxes
     * @param list<string> $path
     * @return array{type: string, start: int, end: int}|null
     */
    private static function find(string $bytes, array $boxes, array $path): ?array
    {
        $type = array_shift($path);
        foreach ($boxes as $box) {
            if ($box['type'] !== $type) {
                continue;
            }
            if ($path === []) {
                return $box;
            }
            $skip = $box['type'] === 'meta' ? 4 : 0;
            $found = self::find($bytes, self::children($bytes, $box['start'] + $skip, $box['end']), $path);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * The movie header's timescale, duration and creation time.
     *
     * @param list<array{type: string, start: int, end: int}> $moov
     * @return array{timescale: int, duration: int, created: int}|null
     */
    private static function movieHeader(string $bytes, array $moov): ?array
    {
        $box = self::find($bytes, $moov, ['mvhd']);
        if ($box === null) {
            return null;
        }
        $body = substr($bytes, $box['start'], $box['end'] - $box['start']);
        $wide = ord($body[0] ?? "\0") === 1;
        $field = static fn (int $offset, int $width): int => Source::bigEndianValue(substr($body, $offset, $width));
        return $wide
            ? ['created' => $field(4, 8), 'timescale' => $field(20, 4), 'duration' => $field(24, 8)]
            : ['created' => $field(4, 4), 'timescale' => $field(12, 4), 'duration' => $field(16, 4)];
    }

    /**
     * A track's size (from its header) and its first sample entry, read as
     * audio or video.
     *
     * @param array{type: string, start: int, end: int} $trak
     * @return array{entry: array<string, mixed>|null}
     */
    private static function track(string $bytes, array $trak): array
    {
        $boxes = self::children($bytes, $trak['start'], $trak['end']);
        $header = self::find($bytes, $boxes, ['tkhd']);
        $size = [0.0, 0.0];
        if ($header !== null) {
            $wide = ord($bytes[$header['start']]) === 1;
            $at = $header['start'] + ($wide ? 88 : 76);
            $size = [Source::bigEndianValue(substr($bytes, $at, 4)) / 65536, Source::bigEndianValue(substr($bytes, $at + 4, 4)) / 65536];
        }
        $stsd = self::find($bytes, $boxes, ['mdia', 'minf', 'stbl', 'stsd']);
        if ($stsd === null || $stsd['end'] - $stsd['start'] < 24) {
            return ['entry' => null];
        }
        $entryStart = $stsd['start'] + 8;
        $entry = substr($bytes, $entryStart, Source::bigEndianValue(substr($bytes, $entryStart, 4)));
        $handler = self::find($bytes, $boxes, ['mdia', 'hdlr']);
        $kind = $handler === null ? '' : substr($bytes, $handler['start'] + 8, 4);
        if ($kind === 'soun') {
            $fourcc = substr($entry, 4, 4);
            if ($fourcc === 'mp4a') {
                return ['entry' => ['kind' => 'audio', 'audio' => self::aac($entry)]];
            }
            // An entry is read by its code, not its track: uncompressed sound ("raw ") names the video codec.
            $name = self::videoCodecs()[$fourcc] ?? '';
            return ['entry' => $name !== '' && substr($entry, 20, 4) === "\0\0\0\0" ? ['kind' => 'renaming', 'codec' => $name] : null];
        }
        return ['entry' => $kind === 'vide' ? self::videoEntry($entry, $size) : null];
    }

    /**
     * An AAC sample entry's stream: its rate and channels as the version 0
     * layout places them (whatever the version), and the average bitrate an
     * elementary stream descriptor directly inside it gives.
     *
     * @return array<string, mixed>
     */
    private static function aac(string $entry): array
    {
        $channels = Source::bigEndianValue(substr($entry, 24, 2));
        $bits = Source::bigEndianValue(substr($entry, 26, 2));
        $rate = Source::bigEndianValue(substr($entry, 32, 4)) / 65536;
        $audio = ['dataformat' => 'mp4'];
        $bitrate = substr($entry, 40, 4) === 'esds' ? self::averageBitrate(substr($entry, 44)) : null;
        if ($bitrate !== null) {
            $audio['bitrate'] = $bitrate;
        }
        $audio += ['codec' => 'ISO/IEC 14496-3 AAC', 'sample_rate' => (float) $rate, 'channels' => $channels, 'bits_per_sample' => $bits, 'lossless' => false];
        if ($channels === 1 || $channels === 2) {
            $audio['channelmode'] = $channels === 1 ? 'mono' : 'stereo';
        }
        if ($bitrate !== null && $channels > 0 && $rate > 0 && $bits > 0) {
            $audio['compression_ratio'] = $bitrate / ($channels * $rate * $bits);
        }
        return $audio;
    }

    /** The average bitrate an elementary stream descriptor's decoder configuration gives; null without one. */
    private static function averageBitrate(string $esds): ?int
    {
        $at = 4;
        if (ord($esds[$at] ?? "\0") !== 0x03) {
            return null;
        }
        $at = self::afterLength($esds, $at + 1) + 3;
        $flags = ord($esds[$at - 1] ?? "\0");
        $at += (($flags & 0x80) !== 0 ? 2 : 0) + (($flags & 0x40) !== 0 ? 1 + ord($esds[$at] ?? "\0") : 0) + (($flags & 0x20) !== 0 ? 2 : 0);
        if (ord($esds[$at] ?? "\0") !== 0x04) {
            return null;
        }
        $at = self::afterLength($esds, $at + 1);
        return Source::bigEndianValue(substr($esds, $at + 9, 4));
    }

    /** Where a descriptor's body starts, after its length of one to four bytes. */
    private static function afterLength(string $bytes, int $at): int
    {
        for ($i = 0; $i < 4 && (ord($bytes[$at] ?? "\0") & 0x80) !== 0; $i++) {
            $at++;
        }
        return $at + 1;
    }

    /**
     * A video sample entry read the QuickTime way (it names a vendor) or the
     * ISO way.
     *
     * @param array{0: float, 1: float} $trackSize
     * @return array<string, mixed>
     */
    private static function videoEntry(string $entry, array $trackSize): array
    {
        $fourcc = substr($entry, 4, 4);
        $video = ['dataformat' => self::DATAFORMATS[$fourcc] ?? 'quicktime'];
        if (substr($entry, 20, 4) !== "\0\0\0\0") {
            $name = substr($entry, 51, min(31, ord($entry[50] ?? "\0")));
            return ['kind' => 'video', 'fourcc' => $fourcc, 'mp4' => false, 'video' => $video + ['resolution_x' => $trackSize[0], 'resolution_y' => $trackSize[1], 'codec' => $name !== '' ? $name : $fourcc, 'lossless' => false]];
        }
        $known = self::videoCodecs();
        $video += ['resolution_x' => Source::bigEndianValue(substr($entry, 32, 2)), 'resolution_y' => Source::bigEndianValue(substr($entry, 34, 2))];
        if (($known[$fourcc] ?? '') !== '') {
            $video['codec'] = $known[$fourcc];
        }
        return ['kind' => 'video', 'fourcc' => $fourcc, 'mp4' => isset($known[$fourcc]), 'video' => $video];
    }

    /**
     * The cover an iTunes tag list holds: its bytes and type.
     *
     * @param list<array{type: string, start: int, end: int}> $moov
     * @return array<string, mixed>|null
     */
    private static function cover(string $bytes, array $moov): ?array
    {
        $box = self::find($bytes, $moov, ['udta', 'meta', 'ilst', 'covr', 'data']);
        if ($box === null || $box['end'] - $box['start'] <= 8) {
            return null;
        }
        $type = Source::bigEndianValue(substr($bytes, $box['start'] + 1, 3));
        $image = substr($bytes, $box['start'] + 8, $box['end'] - $box['start'] - 8);
        $mime = match ($type) {
            13 => 'image/jpeg',
            14 => 'image/png',
            27 => 'image/bmp',
            default => (string) (@getimagesizefromstring($image)['mime'] ?? ''),
        };
        return ['image_mime' => $mime, 'data' => $image, 'description' => 'cover'];
    }

    /**
     * A video stream's fields in the order the reference keeps them.
     *
     * @param array<string, mixed> $video
     * @return array<string, mixed>
     */
    private static function ordered(array $video): array
    {
        $order = ['dataformat', 'rotate', 'resolution_x', 'resolution_y', 'codec', 'lossless', 'bitrate'];
        return array_merge(array_intersect_key(array_flip($order), $video), $video);
    }

    /** @return array<string, string> code => name */
    private static function videoCodecs(): array
    {
        static $codecs = null;
        if ($codecs === null) {
            $codecs = [];
            foreach (explode('|', self::VIDEO) as $pair) {
                [$code, $name] = explode('=', $pair, 2);
                $codecs[$code] = $name;
            }
        }
        return $codecs;
    }
}
