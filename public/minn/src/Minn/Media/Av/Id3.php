<?php

declare(strict_types=1);

namespace Minn\Media\Av;

/**
 * ID3 tags, as the reference's audio reader names what they say (probe
 * media-meta): an ID3v2 tag (versions 2.2 to 2.4) at the start of a file
 * and an ID3v1 tag in its last 128 bytes. Each frame the reader knows
 * becomes a named list of values in the order the frames come; one it does
 * not know (a play count, a private frame, a podcast flag) is left out.
 * ID3v2 text is made UTF-8 whatever its encoding; ID3v1 text is kept as its
 * bytes.
 */
final class Id3
{
    private const FRAMES = 'COM:comment COMM:comment TAL:album TALB:album TBP:bpm TBPM:bpm TCM:composer TCMP:part_of_a_compilation TCO:genre TCOM:composer TCON:genre TCOP:copyright_message TCP:part_of_a_compilation TCR:copyright_message TDA:date TDAT:date TDEN:encoding_time TDLY:playlist_delay TDOR:original_release_time TDRC:recording_time TDRL:release_time TDTG:tagging_time TDY:playlist_delay TEN:encoded_by TENC:encoded_by TEXT:lyricist TFLT:file_type TFT:file_type TIM:time TIME:time TIPL:involved_people_list TIT1:content_group_description TIT2:title TIT3:subtitle TKE:initial_key TKEY:initial_key TLA:language TLAN:language TLE:length TLEN:length TMCL:musician_credits_list TMED:media_type TMOO:mood TMT:media_type TOA:original_artist TOAL:original_album TOF:original_filename TOFN:original_filename TOL:original_lyricist TOLY:original_lyricist TOPE:original_artist TOR:original_year TORY:original_year TOT:original_album TOWN:file_owner TP1:artist TP2:band TP3:conductor TP4:remixer TPA:part_of_a_set TPB:publisher TPE1:artist TPE2:band TPE3:conductor TPE4:remixer TPOS:part_of_a_set TPRO:produced_notice TPUB:publisher TRC:isrc TRCK:track_number TRD:recording_dates TRDA:recording_dates TRK:track_number TRSN:internet_radio_station_name TRSO:internet_radio_station_owner TS2:album_artist_sort_order TSA:album_sort_order TSC:composer_sort_order TSI:size TSIZ:size TSO2:album_artist_sort_order TSOA:album_sort_order TSOC:composer_sort_order TSOP:performer_sort_order TSOT:title_sort_order TSP:performer_sort_order TSRC:isrc TSS:encoder_settings TSSE:encoder_settings TSST:set_subtitle TST:title_sort_order TT1:content_group_description TT2:title TT3:subtitle TXT:lyricist TXX:text TXXX:text TYE:year TYER:year ULT:unsynchronised_lyric USLT:unsynchronised_lyric WAF:url_file WAR:url_artist WAS:url_source WCM:commercial_information WCOM:commercial_information WCOP:copyright WCP:copyright WOAF:url_file WOAR:url_artist WOAS:url_source WORS:url_station WPAY:url_payment WPB:url_publisher WPUB:url_publisher WXX:url_user WXXX:url_user';

    private const PICTURES = ['APIC', 'PIC'];

    private const DESCRIBED = ['COMM', 'COM', 'USLT', 'ULT'];

    private const OWN_TEXT = ['TXXX', 'TXX', 'WXXX', 'WXX'];

    /**
     * The ID3v2 tag at the start of a file: where the bytes after it begin,
     * its version, its named values and its pictures; null with no tag.
     *
     * @return array{end: int, version: int, comments: array<string, list<string>>, pictures: list<array<string, mixed>>}|null
     */
    public static function v2(Source $source): ?array
    {
        $head = $source->bytes(0, 10);
        if (strlen($head) < 10 || !str_starts_with($head, 'ID3') || ord($head[3]) < 2 || ord($head[3]) > 4) {
            return null;
        }
        $version = ord($head[3]);
        $flags = ord($head[5]);
        $size = self::syncsafe(substr($head, 6, 4));
        $end = 10 + $size + ($version === 4 && ($flags & 0x10) !== 0 ? 10 : 0);
        $body = $source->bytes(10, $size);
        if ($version < 4 && ($flags & 0x80) !== 0) {
            $body = str_replace("\xFF\x00", "\xFF", $body);
        }
        $at = 0;
        if (($flags & 0x40) !== 0 && $version > 2) {
            $at = $version === 3 ? 4 + Source::bigEndianValue(substr($body, 0, 4)) : self::syncsafe(substr($body, 0, 4));
        }
        [$comments, $pictures] = self::frames($body, $at, $version);
        return ['end' => $end, 'version' => $version, 'comments' => $comments, 'pictures' => $pictures];
    }

    /**
     * The ID3v1 tag in a file's last 128 bytes: the title, artist, album,
     * year and comment (kept when empty), the track of a version 1.1 tag and
     * the named genre; null with no tag.
     *
     * @return array<string, list<string|int>>|null
     */
    public static function v1(Source $source): ?array
    {
        if ($source->size < 128) {
            return null;
        }
        $tag = $source->bytes($source->size - 128, 128);
        if (!str_starts_with($tag, 'TAG')) {
            return null;
        }
        $field = static fn (int $offset, int $length): string => rtrim(explode("\0", substr($tag, $offset, $length))[0], ' ');
        $comments = ['title' => [$field(3, 30)], 'artist' => [$field(33, 30)], 'album' => [$field(63, 30)], 'year' => [$field(93, 4)]];
        $versionOne = $tag[125] === "\0" && $tag[126] !== "\0";
        $comments['comment'] = [$field(97, $versionOne ? 28 : 30)];
        if ($versionOne) {
            $comments['track_number'] = [ord($tag[126])];
        }
        $genre = ord($tag[127]) === 255 ? null : Genres::name(ord($tag[127]));
        if ($genre !== null) {
            $comments['genre'] = [$genre];
        }
        return $comments;
    }

    /**
     * Every frame of a tag body, read from $at: the named values (a track
     * "n/m" split into its number and total, a recording time's year
     * added, both after the frames) and the pictures.
     *
     * @return array{0: array<string, list<string>>, 1: list<array<string, mixed>>}
     */
    private static function frames(string $body, int $at, int $version): array
    {
        $names = self::names();
        $idLength = $version === 2 ? 3 : 4;
        $headLength = $version === 2 ? 6 : 10;
        $comments = [];
        $added = [];
        $pictures = [];
        while ($at + $headLength <= strlen($body)) {
            $id = substr($body, $at, $idLength);
            if (preg_match('/^[A-Z0-9]+$/', $id) !== 1) {
                break;
            }
            $sizeBytes = substr($body, $at + $idLength, $version === 2 ? 3 : 4);
            $size = $version === 4 ? self::syncsafe($sizeBytes) : Source::bigEndianValue($sizeBytes);
            $data = self::frameData(substr($body, $at + $headLength, $size), $version, $version === 2 ? 0 : ord($body[$at + 9]));
            $at += $headLength + $size;
            if ($data === null) {
                continue;
            }
            if (in_array($id, self::PICTURES, true)) {
                $picture = $id === 'APIC' ? self::attachedPicture($data) : self::olderPicture($data);
                if ($picture !== null) {
                    $pictures[] = $picture;
                }
                continue;
            }
            $name = $names[$id] ?? null;
            if ($name === null) {
                continue;
            }
            foreach (self::values($id, $data) as $value) {
                if ($name === 'genre') {
                    $comments['genre'] = [...($comments['genre'] ?? []), ...self::genres($value)];
                    continue;
                }
                if ($name === 'track_number' && str_contains($value, '/')) {
                    $parts = explode('/', $value);
                    $value = $parts[0];
                    $added['totaltracks'][] = $parts[1];
                }
                if ($name === 'recording_time' && preg_match('/^\d{4}/', $value) === 1) {
                    $added['year'][] = substr($value, 0, 4);
                }
                $comments[$name][] = $value;
            }
        }
        foreach ($added as $name => $values) {
            $comments[$name] = [...($comments[$name] ?? []), ...$values];
        }
        return [array_filter($comments, static fn (array $values): bool => $values !== []), $pictures];
    }

    /** A frame's data with its own flags undone; null for a compressed or encrypted frame. */
    private static function frameData(string $data, int $version, int $format): ?string
    {
        if ($version === 3) {
            if (($format & 0xC0) !== 0) {
                return null;
            }
            return ($format & 0x20) !== 0 ? substr($data, 1) : $data;
        }
        if ($version === 4) {
            if (($format & 0x0C) !== 0) {
                return null;
            }
            $data = substr($data, (($format & 0x40) !== 0 ? 1 : 0) + (($format & 0x01) !== 0 ? 4 : 0));
            return ($format & 0x02) !== 0 ? str_replace("\xFF\x00", "\xFF", $data) : $data;
        }
        return $data;
    }

    /**
     * A frame's values: a text frame's (several in version 2.4), a comment's
     * or lyric's text after its language and description, a user frame's
     * value after its description, or a link frame's address. Empty values
     * are left out.
     *
     * @return list<string>
     */
    private static function values(string $id, string $data): array
    {
        if ($id[0] === 'W' && !in_array($id, self::OWN_TEXT, true)) {
            $url = explode("\0", $data)[0];
            return $url === '' ? [] : [$url];
        }
        if ($data === '') {
            return [];
        }
        $encoding = ord($data[0]);
        $text = substr($data, 1);
        if (in_array($id, self::DESCRIBED, true)) {
            $text = self::afterDescription(substr($text, 3), $encoding);
        } elseif (in_array($id, self::OWN_TEXT, true)) {
            $text = self::afterDescription($text, $encoding);
            if ($id[0] === 'W') {
                $url = explode("\0", $text)[0];
                return $url === '' ? [] : [$url];
            }
        }
        return array_values(array_filter(self::decoded($text, $encoding), static fn (string $value): bool => $value !== ''));
    }

    /** What follows a description ended by the encoding's terminator. */
    private static function afterDescription(string $text, int $encoding): string
    {
        $end = self::terminator($text, $encoding);
        return $end === null ? '' : substr($text, $end + self::terminatorLength($encoding));
    }

    /** Where a string's terminator starts, looking from $from: a NUL, or a NUL pair on a two-byte boundary for UTF-16. */
    private static function terminator(string $text, int $encoding, int $from = 0): ?int
    {
        if (self::terminatorLength($encoding) === 1) {
            $at = strpos($text, "\0", $from);
            return $at === false ? null : $at;
        }
        for ($at = strpos($text, "\0\0", $from); $at !== false; $at = strpos($text, "\0\0", $at + 1)) {
            if (($at - $from) % 2 === 0) {
                return $at;
            }
        }
        return null;
    }

    /**
     * Text in one of the four encodings, made UTF-8 and split at its
     * terminators: Latin-1, UTF-16 with a byte-order mark, UTF-16
     * big-endian, UTF-8.
     *
     * @return list<string>
     */
    private static function decoded(string $text, int $encoding): array
    {
        // Read along the text rather than cut from it, so a run of terminators costs once each; past 1024 values
        // the rest is left (the first is the one shown).
        $parts = [];
        for ($at = 0, $size = strlen($text); $at < $size && count($parts) < 1024;) {
            $end = self::terminator($text, $encoding, $at);
            $parts[] = substr($text, $at, ($end ?? $size) - $at);
            $at = $end === null ? $size : $end + self::terminatorLength($encoding);
        }
        return array_map(static fn (string $part): string => match ($encoding) {
            0 => mb_convert_encoding($part, 'UTF-8', 'ISO-8859-1'),
            1 => self::utf16($part),
            2 => mb_convert_encoding($part, 'UTF-8', 'UTF-16BE'),
            default => $part,
        }, $parts);
    }

    /** UTF-16 read by its byte-order mark (little-endian without one). */
    private static function utf16(string $text): string
    {
        $mark = substr($text, 0, 2);
        if ($mark === "\xFE\xFF") {
            return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        }
        return mb_convert_encoding($mark === "\xFF\xFE" ? substr($text, 2) : $text, 'UTF-8', 'UTF-16LE');
    }

    /** How many bytes end a string in an encoding: two for UTF-16, one otherwise. */
    private static function terminatorLength(int $encoding): int
    {
        return $encoding === 1 || $encoding === 2 ? 2 : 1;
    }

    /**
     * An APIC frame's image: its bytes, type and the size it draws at;
     * null when it holds no bytes.
     *
     * @return array<string, mixed>|null
     */
    private static function attachedPicture(string $data): ?array
    {
        $mimeEnd = strpos($data, "\0", 1);
        if ($data === '' || $mimeEnd === false) {
            return null;
        }
        $bytes = self::pictureBytes(substr($data, $mimeEnd + 2), ord($data[0]));
        if ($bytes === '') {
            return null;
        }
        $picture = ['data' => $bytes, 'image_mime' => substr($data, 1, $mimeEnd - 1), 'frame' => 'APIC'];
        $size = @getimagesizefromstring($bytes);
        return $size === false ? $picture : $picture + ['image_width' => $size[0], 'image_height' => $size[1]];
    }

    /**
     * A version 2.2 PIC frame's image: its bytes and the type its
     * three-letter format names; null when it holds no bytes.
     *
     * @return array<string, mixed>|null
     */
    private static function olderPicture(string $data): ?array
    {
        $format = strtolower(substr($data, 1, 3));
        $bytes = $data === '' ? '' : self::pictureBytes(substr($data, 5), ord($data[0]));
        return $bytes === '' ? null : ['data' => $bytes, 'image_mime' => 'image/' . ($format === 'jpg' ? 'jpeg' : $format), 'frame' => 'PIC'];
    }

    /** A picture's bytes: what follows its description. */
    private static function pictureBytes(string $rest, int $encoding): string
    {
        $end = self::terminator($rest, $encoding);
        return $end === null ? '' : substr($rest, $end + self::terminatorLength($encoding));
    }

    /**
     * The genres a genre frame's value names, the first being the one a
     * site shows: a number in parentheses or alone is looked up (0 names
     * nothing), a value starting "(RX)" or "(CR)" or an unknown reference is
     * kept whole, "((" starts literal text, and otherwise the text before a
     * slash or semicolon is the genre, its parentheses dropped.
     *
     * @return list<string>
     */
    private static function genres(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        if (str_starts_with($value, '(RX)') || str_starts_with($value, '(CR)')) {
            return [$value];
        }
        if ($value[0] === '(') {
            $close = strpos($value, ')');
            if ($close === false) {
                return [$value];
            }
            $inside = substr($value, 1, $close - 1);
            if (ctype_digit($inside)) {
                return self::numbered((int) $inside);
            }
            return [str_starts_with($inside, '(') ? $inside : $value];
        }
        $first = trim((string) preg_split('#[/;]#', $value)[0]);
        if (ctype_digit($first)) {
            return self::numbered((int) $first);
        }
        return [str_replace(['(', ')'], '', $first)];
    }

    /**
     * A genre by number in an ID3v2 tag: its name, an empty string for a
     * number with none, "Unknown" for 255, and nothing for 0.
     *
     * @return list<string>
     */
    private static function numbered(int $number): array
    {
        if ($number === 0) {
            return [];
        }
        return [$number === 255 ? 'Unknown' : (Genres::name($number) ?? '')];
    }

    /** A 28-bit size written seven bits to a byte. */
    private static function syncsafe(string $bytes): int
    {
        $value = 0;
        foreach (str_split(str_pad($bytes, 4, "\0")) as $byte) {
            $value = ($value << 7) | (ord($byte) & 0x7f);
        }
        return $value;
    }

    /** @return array<string, string> frame id => the name its values go under */
    private static function names(): array
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach (explode(' ', self::FRAMES) as $pair) {
                [$id, $name] = explode(':', $pair);
                $names[$id] = $name;
            }
        }
        return $names;
    }
}
