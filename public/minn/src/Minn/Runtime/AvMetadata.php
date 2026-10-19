<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Media\Av\Analyzer;

/**
 * An audio or video file's metadata as WordPress's media readers keep it
 * (wp_read_audio_metadata, wp_read_video_metadata; probe media-meta), from
 * the engine's own analysis (Media\Av): an audio file's stream described
 * whole, a video's size, format and codec with its audio beside, the
 * playing time rounded and as a clock, when it was made, its ID3 tags and
 * cover, each through the reader's filter.
 */
final class AvMetadata
{
    /** An audio file's metadata (wp_read_audio_metadata); false when there is no such file. */
    public static function audio(mixed $file): mixed
    {
        if (!file_exists((string) $file)) {
            return false;
        }
        $data = Analyzer::analyze((string) $file) ?? [];
        $metadata = [];
        if (!empty($data['audio'])) {
            $metadata = self::withoutStreams($data['audio']);
        }
        if (!empty($data['fileformat'])) {
            $metadata['fileformat'] = $data['fileformat'];
        }
        $metadata = self::common($metadata, $data);
        return Runtime::hooks()->filter('wp_read_audio_metadata', [self::finished($metadata, $data), $file, $data['fileformat'] ?? null, $data]);
    }

    /** A video file's metadata (wp_read_video_metadata); false when there is no such file. */
    public static function video(mixed $file): mixed
    {
        if (!file_exists((string) $file)) {
            return false;
        }
        $data = Analyzer::analyze((string) $file) ?? [];
        $video = (array) ($data['video'] ?? []);
        $metadata = [];
        if (isset($video['lossless'])) {
            $metadata['lossless'] = $video['lossless'];
        }
        if (!empty($video['bitrate'])) {
            $metadata['bitrate'] = (int) $video['bitrate'];
        }
        if (!empty($video['bitrate_mode'])) {
            $metadata['bitrate_mode'] = $video['bitrate_mode'];
        }
        $metadata = self::common($metadata, $data);
        foreach (['resolution_x' => 'width', 'resolution_y' => 'height'] as $field => $key) {
            if (!empty($video[$field])) {
                $metadata[$key] = (int) $video[$field];
            }
        }
        if (!empty($data['fileformat'])) {
            $metadata['fileformat'] = $data['fileformat'];
        }
        foreach (['dataformat', 'encoder', 'codec'] as $key) {
            if (!empty($video[$key])) {
                $metadata[$key] = $video[$key];
            }
        }
        if (!empty($data['audio'])) {
            $metadata['audio'] = self::withoutStreams($data['audio']);
        }
        return Runtime::hooks()->filter('wp_read_video_metadata', [self::finished($metadata, $data), $file, $data['fileformat'] ?? null, $data]);
    }

    /**
     * Metadata with an analysis's ID3 tags added (wp_add_id3_tag_data): the
     * first value under each name of the ID3v2 tag, or of the ID3v1 tag when
     * there is no ID3v2 one, cleaned as post content; the length is left
     * out, and a terms-of-use notice the tag cut short is mended.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function withTags(array $metadata, mixed $data): array
    {
        foreach (['id3v2', 'id3v1'] as $version) {
            $comments = $data[$version]['comments'] ?? null;
            if (empty($comments)) {
                continue;
            }
            foreach (is_array($comments) ? $comments : [] as $key => $values) {
                if ($key === 'length' || !is_array($values) || $values === []) {
                    continue;
                }
                $value = self::cleaned(reset($values));
                if ($key === 'terms_of_use' && is_string($value) && str_starts_with($value, 'yright notice.')) {
                    $value = 'Cop' . $value;
                }
                $metadata[$key] = $value;
            }
            break;
        }
        return $metadata;
    }

    /**
     * When an analysed file was made (wp_get_media_creation_timestamp): a
     * Windows Media file's creation date, a Matroska or WebM file's creation
     * time (false when it is no date) or its date, or the time the first box
     * of an MPEG-4 or QuickTime movie gives; false for any other.
     */
    public static function creationTimestamp(mixed $data): int|false
    {
        $format = $data['fileformat'] ?? null;
        if ($format === 'asf' && isset($data['asf']['file_properties_object']['creation_date_unix'])) {
            return (int) $data['asf']['file_properties_object']['creation_date_unix'];
        }
        if ($format === 'matroska' || $format === 'webm') {
            if (isset($data['matroska']['comments']['creation_time'][0])) {
                return strtotime((string) $data['matroska']['comments']['creation_time'][0]);
            }
            if (isset($data['matroska']['info'][0]['DateUTC_unix'])) {
                return (int) $data['matroska']['info'][0]['DateUTC_unix'];
            }
        }
        if (($format === 'quicktime' || $format === 'mp4') && isset($data['quicktime']['moov']['subatoms'][0]['creation_time_unix'])) {
            return (int) $data['quicktime']['moov']['subatoms'][0]['creation_time_unix'];
        }
        return false;
    }

    /**
     * The size, type and playing time both readers record.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function common(array $metadata, array $data): array
    {
        $kept = ['filesize' => 'filesize', 'mime_type' => 'mime_type', 'length' => 'playtime_seconds', 'length_formatted' => 'playtime_string'];
        foreach ($kept as $key => $field) {
            $value = $data[$field] ?? null;
            if (empty($value)) {
                continue;
            }
            $metadata[$key] = match ($key) {
                'filesize' => (int) $value,
                'length' => (int) round($value),
                default => $value,
            };
        }
        return $metadata;
    }

    /**
     * What both readers add last: when the file was made, its tags and its
     * cover (an ID3v2 picture with its size, any other without).
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function finished(array $metadata, array $data): array
    {
        $created = self::creationTimestamp($data);
        if ($created !== false) {
            $metadata['created_timestamp'] = $created;
        }
        $metadata = self::withTags($metadata, $data);
        $attached = $data['id3v2']['APIC'][0] ?? null;
        $picture = $data['comments']['picture'][0] ?? null;
        if (!empty($attached['data'])) {
            $metadata['image'] = ['data' => $attached['data'], 'mime' => $attached['image_mime'], 'width' => $attached['image_width'] ?? null, 'height' => $attached['image_height'] ?? null];
        } elseif (!empty($picture['data'])) {
            $metadata['image'] = ['data' => $picture['data'], 'mime' => $picture['image_mime']];
        }
        return $metadata;
    }

    /**
     * A stream's description without its per-stream list.
     *
     * @param array<string, mixed> $stream
     * @return array<string, mixed>
     */
    private static function withoutStreams(array $stream): array
    {
        unset($stream['streams']);
        return $stream;
    }

    /** A tag's value cleaned as post content, text inside a list included. */
    private static function cleaned(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::cleaned($item), $value);
        }
        return KsesRules::post(is_scalar($value) ? (string) $value : '');
    }
}
