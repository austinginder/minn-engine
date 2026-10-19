<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * FLAC, on its own or as the first packet of an Ogg stream, as the
 * reference's media reader measures it (probe media-meta): the stream
 * information block gives the rate, channels, depth and samples, the
 * comment block names the encoder, and a picture block is the cover.
 */
final class Flac
{
    /**
     * A native FLAC file's analysis: the data after the metadata blocks,
     * the playing time from the sample count and a bitrate over the whole
     * file.
     *
     * @return array<string, mixed>
     */
    public static function read(Source $source): array
    {
        $blocks = self::blocks($source, 4);
        $data = ['avdataoffset' => $blocks['end'], 'avdataend' => $source->size, 'fileformat' => 'flac', 'mime_type' => 'audio/flac'];
        $info = $blocks['info'];
        if ($info === null) {
            return $data;
        }
        $playtime = $info['samples'] > 0 && $info['sampleRate'] > 0 ? $info['samples'] / $info['sampleRate'] : null;
        $bitrate = $playtime ? $source->size * 8 / $playtime : null;
        $data['audio'] = self::stream($info, $bitrate, $blocks['vendor']);
        if ($playtime) {
            $data['playtime_seconds'] = $playtime;
            $data['bitrate'] = $bitrate;
        }
        if ($blocks['picture'] !== null) {
            $data['comments']['picture'] = [$blocks['picture']];
        }
        return $data;
    }

    /**
     * The stream a FLAC stream information block and vendor describe, with
     * the bitrate when one is known.
     *
     * @param array{sampleRate: int, channels: int, bits: int, samples: int} $info
     * @return array<string, mixed>
     */
    public static function stream(array $info, int|float|null $bitrate, ?string $vendor): array
    {
        $audio = ['dataformat' => 'flac', 'bitrate_mode' => 'vbr', 'lossless' => true, 'sample_rate' => $info['sampleRate'], 'channels' => $info['channels'], 'bits_per_sample' => $info['bits']];
        if ($bitrate !== null) {
            $audio['bitrate'] = $bitrate;
        }
        if ($vendor !== null) {
            $audio['encoder'] = $vendor;
        }
        if ($info['channels'] === 1 || $info['channels'] === 2) {
            $audio['channelmode'] = $info['channels'] === 1 ? 'mono' : 'stereo';
        }
        if ($bitrate !== null && $info['channels'] > 0 && $info['sampleRate'] > 0 && $info['bits'] > 0) {
            $audio['compression_ratio'] = $bitrate / ($info['channels'] * $info['sampleRate'] * $info['bits']);
        }
        return $audio;
    }

    /**
     * A stream information block's rate, channels, depth and sample count.
     *
     * @return array{sampleRate: int, channels: int, bits: int, samples: int}|null
     */
    public static function streamInfo(string $block): ?array
    {
        if (strlen($block) < 18) {
            return null;
        }
        $packed = Source::bigEndianValue(substr($block, 10, 8));
        return ['sampleRate' => $packed >> 44, 'channels' => (($packed >> 41) & 7) + 1, 'bits' => (($packed >> 36) & 31) + 1, 'samples' => $packed & 0xFFFFFFFFF];
    }

    /**
     * The metadata blocks from $at: the stream information, the comment
     * block's vendor, the first picture, and where the audio begins.
     *
     * @return array{info: ?array, vendor: ?string, picture: ?array, end: int}
     */
    private static function blocks(Source $source, int $at): array
    {
        $found = ['info' => null, 'vendor' => null, 'picture' => null, 'end' => $at];
        for ($count = 0; $at + 4 <= $source->size && $count < 128; $count++) {
            $head = $source->bigEndian($at, 4);
            $type = ($head >> 24) & 0x7F;
            $length = $head & 0xFFFFFF;
            $body = $type === 0 || $type === 4 || $type === 6 ? $source->bytes($at + 4, $length) : '';
            if ($type === 0) {
                $found['info'] ??= self::streamInfo($body);
            } elseif ($type === 4 && strlen($body) >= 4) {
                $found['vendor'] ??= substr($body, 4, Source::littleEndianValue(substr($body, 0, 4)));
            } elseif ($type === 6) {
                $found['picture'] ??= self::picture($body);
            }
            $at += 4 + $length;
            $found['end'] = $at;
            if (($head >> 31) === 1) {
                break;
            }
        }
        return $found;
    }

    /**
     * A picture block's image: its type and bytes.
     *
     * @return array<string, mixed>|null
     */
    private static function picture(string $body): ?array
    {
        $mimeLength = Source::bigEndianValue(substr($body, 4, 4));
        $mime = substr($body, 8, $mimeLength);
        $at = 8 + $mimeLength;
        $at += 4 + Source::bigEndianValue(substr($body, $at, 4)) + 16;
        $length = Source::bigEndianValue(substr($body, $at, 4));
        $image = substr($body, $at + 4, $length);
        return $image === '' || strlen($image) < $length ? null : ['data' => $image, 'image_mime' => $mime];
    }
}
