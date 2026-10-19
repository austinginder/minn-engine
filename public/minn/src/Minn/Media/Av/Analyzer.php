<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * A media file's analysis, keyed as the reference's media reader keys the
 * one it hands to the wp_read_audio_metadata and wp_read_video_metadata
 * filters (probe media-meta): the file's size, format and type, its audio
 * and video streams, its playing time and bitrate, and its tags. The
 * format is found by the file's first bytes; a file named .mp3 with none
 * is still typed as MPEG audio.
 */
final class Analyzer
{
    private const ATOMS = ['ftyp', 'moov', 'mdat', 'free', 'wide', 'skip', 'pnot'];

    /**
     * The analysis of the file at a path; null when there is no such file.
     *
     * @return array<string, mixed>|null
     */
    public static function analyze(string $path): ?array
    {
        $source = Source::open($path);
        if ($source === null) {
            return null;
        }
        try {
            return self::analyzed($source);
        } finally {
            $source->close();
        }
    }

    /** @return array<string, mixed> */
    private static function analyzed(Source $source): array
    {
        $data = ['filesize' => $source->size];
        if ($source->size === 0) {
            return $data;
        }
        $id3v2 = Id3::v2($source);
        $id3v1 = Id3::v1($source);
        $start = $id3v2['end'] ?? 0;
        $end = $source->size - ($id3v1 === null ? 0 : 128);
        $data += self::stream($source, $start, $end);
        if ($id3v2 !== null) {
            $data['id3v2'] = ['comments' => $id3v2['comments'], 'APIC' => array_values(array_filter($id3v2['pictures'], static fn (array $picture): bool => $picture['frame'] === 'APIC'))];
            if ($id3v2['pictures'] !== []) {
                $data['comments']['picture'] = $id3v2['pictures'];
            }
        }
        if ($id3v1 !== null) {
            $data['id3v1'] = ['comments' => $id3v1];
        }
        if (isset($data['playtime_seconds'])) {
            $data['playtime_string'] = self::playtimeString($data['playtime_seconds']);
        }
        return $data;
    }

    /**
     * The stream's own part of the analysis, by its first bytes.
     *
     * @return array<string, mixed>
     */
    private static function stream(Source $source, int $start, int $end): array
    {
        $magic = $source->bytes($start, 16);
        $second = ord($magic[1] ?? "\0");
        if ($magic !== '' && $magic[0] === "\xFF" && ($second & 0xE0) === 0xE0) {
            if (($second & 0x06) === 0) {
                return ['mime_type' => 'audio/aac', 'error' => ['The reader for raw AAC is not part of this site.']];
            }
            return self::mpeg($source, $start, $end);
        }
        if (Riff::detects($magic)) {
            return Riff::read($source);
        }
        if (IsoMedia::detects($magic)) {
            return IsoMedia::read($source);
        }
        if (strtolower(pathinfo($source->path, PATHINFO_EXTENSION)) === 'mp3') {
            return self::mpeg($source, $start, $end);
        }
        return [];
    }

    /** @return array<string, mixed> */
    private static function mpeg(Source $source, int $start, int $end): array
    {
        $read = Mpeg::read($source, $start, $end);
        if ($read === null) {
            return ['mime_type' => 'audio/mpeg'];
        }
        return ['avdataoffset' => $read['avdataoffset'], 'avdataend' => $read['avdataend'], 'fileformat' => 'mp3', 'audio' => $read['audio'], 'mime_type' => 'audio/mpeg', 'playtime_seconds' => $read['playtime_seconds'], 'bitrate' => $read['bitrate']];
    }

    /** A playing time as a clock: minutes and seconds, with hours past the first. */
    public static function playtimeString(int|float $seconds): string
    {
        $whole = (int) round($seconds);
        $hours = intdiv($whole, 3600);
        $minutes = intdiv($whole % 3600, 60);
        $rest = sprintf('%02d', $whole % 60);
        return $hours > 0 ? sprintf('%d:%02d:%s', $hours, $minutes, $rest) : "{$minutes}:{$rest}";
    }
}
