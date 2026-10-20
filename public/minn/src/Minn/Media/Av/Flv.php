<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * Flash video (FLV) as the reference's media reader measures it (probe
 * media-meta). The onMetaData script tag gives the playing time, frame
 * rate, data rates and codecs; the first audio tag gives the rate, sample
 * size and channels; the first video tag's Sorenson picture header the
 * size, or for H.264 its sequence parameter set, read as the reference
 * reads it. The video's data rate is kept as written (kilobits), the
 * audio's made bits; a file with no audio and no video rate gives the
 * video the file's own rate.
 */
final class Flv
{
    private const VIDEO_CODECS = [2 => 'Sorenson H.263', 3 => 'Screen video', 4 => 'On2 VP6', 5 => 'On2 VP6 with alpha channel', 6 => 'Screen video v2', 7 => 'Sorenson H.264'];

    private const AUDIO_CODECS = 'Linear PCM, platform endian|ADPCM|mp3|Linear PCM, little endian|Nellymoser 16kHz mono|Nellymoser 8kHz mono|Nellymoser|G.711A-law logarithmic PCM|G.711 mu-law logarithmic PCM|reserved|AAC|Speex|||mp3 8kHz|Device-specific sound';

    private const PICTURE_SIZES = [2 => [352, 288], 3 => [176, 144], 4 => [128, 96], 5 => [320, 240], 6 => [160, 120]];

    /**
     * The file's analysis.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $data = ['avdataoffset' => 0, 'avdataend' => $source->size, 'fileformat' => 'flv', 'mime_type' => 'video/x-flv'];
        $meta = [];
        $audioFlags = null;
        $videoTag = null;
        for ($at = $source->bigEndian(5, 4) + 4, $count = 0; $at + 11 <= $source->size && $count < 512 && ($audioFlags === null || $videoTag === null || $meta === []); $count++) {
            $type = ord($source->bytes($at, 1));
            $size = $source->bigEndian($at + 1, 3);
            if ($type === 18 && $meta === []) {
                $meta = self::metaData($source->bytes($at + 11, min($size, 65536)));
            } elseif ($type === 8 && $audioFlags === null) {
                $audioFlags = ord($source->bytes($at + 11, 1));
            } elseif ($type === 9 && $videoTag === null) {
                $videoTag = $source->bytes($at + 11, min($size, 512));
            }
            $at += 11 + $size + 4;
        }
        $playtime = !empty($meta['duration']) ? $meta['duration'] : null;
        if ($audioFlags !== null) {
            $data['audio'] = self::audio($audioFlags, $meta);
        }
        if ($videoTag !== null) {
            $data['video'] = self::video($videoTag, $meta);
        }
        if ($playtime !== null) {
            $data['playtime_seconds'] = $playtime;
            $data['bitrate'] = $source->size * 8 / $playtime;
            // A file of video alone whose metadata names no video rate gives the video the file's rate (H.264 from ffmpeg; probe media-meta).
            if (isset($data['video']) && !isset($data['audio']) && empty($data['video']['bitrate'])) {
                $data['video']['bitrate'] = $data['bitrate'];
            }
        }
        return $data;
    }

    /**
     * The audio: its codec (by the metadata's code), the first tag's rate,
     * size and channels, and the metadata's rate in bits.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function audio(int $flags, array $meta): array
    {
        $audio = ['dataformat' => 'flv'];
        $bitrate = !empty($meta['audiodatarate']) ? (int) round($meta['audiodatarate'] * 1000) : null;
        if ($bitrate !== null) {
            $audio['bitrate'] = $bitrate;
        }
        if (isset($meta['audiocodecid'])) {
            $name = explode('|', self::AUDIO_CODECS)[(int) $meta['audiocodecid']] ?? '';
            $audio['codec'] = $name === '' ? false : $name;
        }
        $channels = ($flags & 1) + 1;
        $audio += ['sample_rate' => [5500, 11025, 22050, 44100][($flags >> 2) & 3], 'bits_per_sample' => ($flags & 2) !== 0 ? 16 : 8, 'channels' => $channels, 'lossless' => false, 'channelmode' => $channels === 1 ? 'mono' : 'stereo'];
        if ($bitrate !== null) {
            $audio['compression_ratio'] = $bitrate / ($channels * $audio['sample_rate'] * $audio['bits_per_sample']);
        }
        return $audio;
    }

    /**
     * The video: its frame rate, its size (from a Sorenson picture header),
     * the metadata's data rate as written, and its codec.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function video(string $tag, array $meta): array
    {
        $video = ['dataformat' => 'flv'];
        if (isset($meta['framerate'])) {
            $video['frame_rate'] = $meta['framerate'];
        }
        [$width, $height] = (ord($tag[0] ?? "\0") & 0x0f) === 7 ? self::avcSize($tag) : self::pictureSize(substr($tag, 1));
        $video += ['resolution_x' => $width, 'resolution_y' => $height];
        if (!empty($meta['videodatarate'])) {
            $video['bitrate'] = $meta['videodatarate'];
        }
        if ($height > 0) {
            $video['pixel_aspect_ratio'] = $width / $height;
        }
        $name = self::VIDEO_CODECS[(int) ($meta['videocodecid'] ?? -1)] ?? null;
        if ($name !== null) {
            $video['codec'] = $name;
        }
        $video['lossless'] = false;
        return $video;
    }

    /**
     * The width and height a Sorenson H.263 picture header gives: its
     * picture size code, or the 8-bit or 16-bit size that follows it.
     *
     * @return array{0: int, 1: int}
     */
    private static function pictureSize(string $bytes): array
    {
        $bits = '';
        foreach (str_split(substr($bytes, 0, 9)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $code = (int) bindec(substr($bits, 30, 3));
        if ($code === 0 || $code === 1) {
            $width = $code === 0 ? 8 : 16;
            return [(int) bindec(substr($bits, 33, $width)), (int) bindec(substr($bits, 33 + $width, $width))];
        }
        return self::PICTURE_SIZES[$code] ?? [0, 0];
    }

    /**
     * The width and height an H.264 sequence header gives, read as the
     * reference reads them: the first parameter set of the decoder
     * configuration, as stored (its emulation-prevention bytes kept), its
     * fields read in the layout of the profiles without chroma settings
     * whatever its profile, and its crop counted in pairs of samples. A
     * tag that is no sequence header has no size.
     *
     * @return array{0: int, 1: int}
     */
    private static function avcSize(string $tag): array
    {
        if (strlen($tag) < 14 || ord($tag[1]) !== 0 || (ord($tag[10]) & 0x1f) === 0) {
            return [0, 0];
        }
        $length = Source::bigEndianValue(substr($tag, 11, 2));
        $bits = new ExpGolomb(substr($tag, 14, max(0, $length - 1)));
        $bits->skip(24);
        $bits->unsigned();
        $bits->unsigned();
        $order = $bits->unsigned();
        if ($order === 0) {
            $bits->unsigned();
        } elseif ($order === 1) {
            $bits->skip(1);
            $bits->signed();
            $bits->signed();
            for ($cycle = $bits->unsigned(); $cycle > 0; $cycle--) {
                $bits->signed();
            }
        }
        $bits->unsigned();
        $bits->skip(1);
        $columns = $bits->unsigned() + 1;
        $rows = $bits->unsigned() + 1;
        $framesOnly = $bits->bit();
        $bits->skip($framesOnly === 1 ? 1 : 2);
        [$left, $right, $top, $bottom] = $bits->bit() === 1 ? [$bits->unsigned(), $bits->unsigned(), $bits->unsigned(), $bits->unsigned()] : [0, 0, 0, 0];
        return [$columns * 16 - 2 * ($left + $right), (2 - $framesOnly) * $rows * 16 - 2 * ($top + $bottom)];
    }

    /**
     * The onMetaData object's numbers, read from its AMF0 encoding.
     *
     * @return array<string, mixed>
     */
    private static function metaData(string $bytes): array
    {
        $at = strpos($bytes, 'onMetaData');
        if ($at === false) {
            return [];
        }
        $at += 10;
        $type = ord($bytes[$at] ?? "\0");
        $at += $type === 8 ? 5 : 1;
        $meta = [];
        while ($at + 3 < strlen($bytes)) {
            $length = Source::bigEndianValue(substr($bytes, $at, 2));
            if ($length === 0) {
                break;
            }
            $key = substr($bytes, $at + 2, $length);
            $at += 2 + $length;
            $valueType = ord($bytes[$at] ?? "\0");
            if ($valueType === 0) {
                $meta[$key] = (float) unpack('E', substr($bytes, $at + 1, 8))[1];
                $at += 9;
            } elseif ($valueType === 1) {
                $meta[$key] = ord($bytes[$at + 1] ?? "\0") !== 0;
                $at += 2;
            } elseif ($valueType === 2) {
                $meta[$key] = substr($bytes, $at + 3, Source::bigEndianValue(substr($bytes, $at + 1, 2)));
                $at += 3 + Source::bigEndianValue(substr($bytes, $at + 1, 2));
            } else {
                break;
            }
        }
        return array_map(static fn (mixed $value): mixed => is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX ? (int) $value : $value, $meta);
    }
}
