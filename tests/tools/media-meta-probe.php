<?php
/**
 * What wp_read_audio_metadata and wp_read_video_metadata make of a file
 * (probe media-meta): every file in tests/fixtures/media (MP3, MPEG-4,
 * WAV, Ogg, FLAC, raw AAC; MPEG-4, QuickTime, WebM, Matroska, AVI, Flash
 * and Windows Media video, built by tests/tools/media-corpus.sh), each
 * reader handed the other kind of file, files it cannot read, and ID3
 * tags built here byte by byte in front of untagged MPEG frames: versions
 * 2.2 to 2.4, text encodings, the frames a tagger writes, genres, an ID3v1
 * tag beside an ID3v2 one, and markup in a value. A float is written out
 * with its type, so 44100 and 44100.0 differ; a picture is its length and
 * digest, and text that is not UTF-8 its bytes. Same protocol as api-probe.php; nothing is saved.
 */

if (defined('ABSPATH') && !function_exists('wp_read_audio_metadata') && is_file(ABSPATH . 'wp-admin/includes/media.php')) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$typed = static function ($value) use (&$typed) {
    if (is_float($value)) {
        return '(float) ' . var_export($value, true);
    }
    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
        return '(bytes) ' . bin2hex($value);
    }
    if (is_array($value)) {
        if (isset($value['data']) && is_string($value['data']) && isset($value['mime'])) {
            $value['data'] = ['bytes' => strlen($value['data']), 'sha1' => sha1($value['data'])];
        }
        return array_map($typed, $value);
    }
    return $value;
};
$media = dirname(__DIR__) . '/fixtures/media';
$calls = [];
$seen = static function ($metadata, $file, $format, $data) use (&$calls) {
    $calls[] = [basename((string) $file), $format, is_array($data)];
    return $metadata;
};
add_filter('wp_read_audio_metadata', $seen, 10, 4);
add_filter('wp_read_video_metadata', $seen, 10, 4);

foreach (glob("{$media}/*") as $path) {
    $name = basename($path);
    $say($name, $typed(str_starts_with($name, 'a') ? wp_read_audio_metadata($path) : wp_read_video_metadata($path)));
}
// Each reader handed the other kind of file.
foreach (['a01-cbr-tags.mp3', 'a06-aac.m4a', 'a12-flac.flac'] as $name) {
    $say("video reader: {$name}", $typed(wp_read_video_metadata("{$media}/{$name}")));
}
foreach (['v01-h264-aac.mp4', 'v04-vp9-opus.webm', 'v07-mpeg4.avi'] as $name) {
    $say("audio reader: {$name}", $typed(wp_read_audio_metadata("{$media}/{$name}")));
}

// Files neither reader can follow, in a temporary folder.
$tmp = sys_get_temp_dir() . '/minn-media-meta-' . getmypid();
@mkdir($tmp);
$odd = [
    'empty.mp3' => '',
    'text.mp3' => str_repeat("hello world\n", 50),
    'text.wav' => str_repeat("hello world\n", 50),
    'text.mp4' => str_repeat("hello world\n", 50),
    'text.zz' => str_repeat("hello world\n", 50),
    'cut.mp4' => substr((string) file_get_contents("{$media}/v01-h264-aac.mp4"), 0, 2000),
    'cut.flac' => substr((string) file_get_contents("{$media}/a12-flac.flac"), 0, 300),
];
foreach ($odd as $name => $bytes) {
    file_put_contents("{$tmp}/{$name}", $bytes);
    $say("unreadable {$name}", $typed(['audio' => wp_read_audio_metadata("{$tmp}/{$name}"), 'video' => wp_read_video_metadata("{$tmp}/{$name}")]));
}
$say('no such file', [wp_read_audio_metadata("{$tmp}/none.mp3"), wp_read_video_metadata("{$tmp}/none.mp4")]);

// ID3 tags built byte by byte in front of untagged frames.
$frames = (string) file_get_contents("{$media}/a04-plain.mp3");
$syncsafe = static fn (int $n): string => chr(($n >> 21) & 0x7f) . chr(($n >> 14) & 0x7f) . chr(($n >> 7) & 0x7f) . chr($n & 0x7f);
$tag = static function (int $version, array $frameList) use ($syncsafe): string {
    $body = '';
    foreach ($frameList as [$id, $data]) {
        $size = strlen($data);
        $body .= match ($version) {
            2 => $id . substr(pack('N', $size), 1),
            3 => $id . pack('N', $size) . "\0\0",
            default => $id . $syncsafe($size) . "\0\0",
        } . $data;
    }
    return 'ID3' . chr($version) . "\0\0" . $syncsafe(strlen($body)) . $body;
};
$v1 = static function (string $title, string $artist, string $album, string $year, string $comment, int $track, int $genre): string {
    $pad = static fn (string $s, int $n): string => str_pad(substr($s, 0, $n), $n, "\0");
    return 'TAG' . $pad($title, 30) . $pad($artist, 30) . $pad($album, 30) . $pad($year, 4) . ($track > 0 ? $pad($comment, 28) . "\0" . chr($track) : $pad($comment, 30)) . chr($genre);
};
$wide = static fn (string $s): string => "\xFF\xFE" . mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');
$latin = static fn (string $s): string => "\0" . $s;
$png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAADCAIAAAA2iEnWAAAAEklEQVR4nGP8z8DAwMDAxAAABQQBAgWQ/2EAAAAASUVORK5CYII=');
$jpeg = (string) file_get_contents("{$media}/a01-cbr-tags.mp3", false, null, 0, 0);
$id3 = [
    'v2.3 frames a tagger writes' => $tag(3, [
        ['TIT2', $latin('Latin Title')], ['TPE1', $latin('Latin Artist')], ['TALB', $latin('Latin Album')], ['TYER', $latin('2004')],
        ['TCON', $latin('(17)')], ['TRCK', $latin('05/11')], ['TPOS', $latin('1/2')], ['TPE2', $latin('Band Name')], ['TCOM', $latin('A Composer')],
        ['TENC', $latin('An Encoder')], ['TCOP', $latin('2004 Someone')], ['TBPM', $latin('120')], ['TPUB', $latin('A Label')], ['TLEN', $latin('2040')],
        ['TIT1', $latin('Grouping')], ['TIT3', $latin('Subtitle')], ['TEXT', $latin('Lyricist')], ['TPE3', $latin('Conductor')], ['TPE4', $latin('Remixer')],
        ['TKEY', $latin('Am')], ['TLAN', $latin('eng')], ['TMED', $latin('CD')], ['TOAL', $latin('Orig Album')], ['TOPE', $latin('Orig Artist')], ['TORY', $latin('1990')],
        ['TSRC', $latin('USRC17607839')], ['TSSE', $latin('Settings')], ['TOWN', $latin('Owner')], ['TRSN', $latin('Radio')], ['TFLT', $latin('MPG/3')],
        ['TDAT', $latin('0506')], ['TIME', $latin('1230')], ['TSIZ', $latin('1000')], ['TOFN', $latin('orig.mp3')], ['TOLY', $latin('Orig Lyricist')],
        ['COMM', "\0engShort\0The comment text"], ['COMM', "\0eng\0Plain comment"], ['TXXX', "\0MyKey\0My Value"], ['TXXX', "\0Other\0Other Value"],
        ['WXXX', "\0Home\0https://example.com/x"], ['WOAR', 'https://example.com/artist'], ['WCOM', 'https://example.com/buy'],
        ['USLT', "\0engLyrics\0Line one\nLine two"], ['POPM', "a@b.c\0\xC8\0\0\0\x05"], ['PCNT', "\0\0\0\x07"],
        ['PRIV', "owner.example\0\x01\x02\x03"], ['UFID', "http://musicbrainz.org\0abc-123"],
        ['APIC', "\0image/png\0\x03Front\0" . $png], ['APIC', "\0image/jpeg\0\x04Back\0" . $png],
    ]),
    'v2.4 UTF-8, several values' => $tag(4, [
        ['TIT2', "\x03Ünï Title\0Second Value"], ['TPE1', "\x03Ärtist One\0Ärtist Two"], ['TALB', "\x03Älbum"], ['TDRC', "\x032003-05-06"],
        ['TCON', "\x0317\0Pop"], ['TRCK', "\x037"], ['TSOP', "\x03Sort Artist"], ['TSOA', "\x03Sort Album"], ['TSOT', "\x03Sort Title"],
        ['TSO2', "\x03Sort Album Artist"], ['TMOO', "\x03Calm"], ['TPRO', "\x03Produced"], ['TSST', "\x03Set Subtitle"], ['TDRL', "\x032003"],
        ['TDEN', "\x032003-01-01"], ['TIPL', "\x03producer\0Someone"], ['TMCL', "\x03guitar\0Player"],
        ['COMM', "\x03eng\0UTF-8 comment"], ['APIC', "\x03image/png\0\x03\0" . $png],
    ]),
    'v2.4 UTF-16 big-endian' => $tag(4, [['TIT2', "\x02" . mb_convert_encoding('Big Énd', 'UTF-16BE', 'UTF-8')], ['TPE1', "\x02" . mb_convert_encoding('Ärtist', 'UTF-16BE', 'UTF-8')]]),
    'v2.3 UTF-16' => $tag(3, [
        ['TIT2', "\x01" . $wide('Wide Tïtle')], ['TPE1', "\x01" . $wide('Wide Ärtist')], ['TCON', "\x01" . $wide('(8)Jazz')],
        ['COMM', "\x01eng" . $wide('d') . "\0\0" . $wide('Wide comment')], ['TRCK', "\x01" . $wide('3')],
    ]),
    'v2.3 Latin-1 high bytes' => $tag(3, [['TIT2', "\0Caf\xE9 \xD1and\xFA"], ['TPE1', "\0\xC5ngstr\xF6m"]]),
    'v2.2' => $tag(2, [
        ['TT2', "\0Old Title"], ['TP1', "\0Old Artist"], ['TAL', "\0Old Album"], ['TYE', "\x001995"], ['TCO', "\0Rock"], ['TRK', "\x002/5"],
        ['COM', "\0engx\0Old comment"], ['TEN', "\0Old Encoder"], ['PIC', "\0PNG\x03Pic\0" . $png],
    ]),
    'an ID3v1 tag beside an ID3v2 one' => ['head' => $tag(3, [['TIT2', $latin('V2 Title')], ['TALB', $latin('V2 Album')]]), 'tail' => $v1('V1 Title', 'V1 Artist', 'V1 Album', '1980', 'V1 comment', 9, 13)],
    'ID3v1 alone, no track or genre' => ['head' => '', 'tail' => $v1('Only V1', 'Someone', '', '', '', 0, 255)],
    'ID3v1.1, Latin-1' => ['head' => '', 'tail' => $v1("Caf\xE9", 'Band', 'Alb', '2001', 'note', 12, 80)],
    'markup in values' => $tag(3, [['TIT2', $latin('<b>Bold</b> <script>alert(1)</script> & <a href="javascript:x">l</a>')], ['TPE1', $latin("Tab\tNew\nline")], ['TCON', $latin('(255)')]]),
    'genres by number, refinement and name' => $tag(3, [['TCON', $latin('(RX)(CR)')], ['TIT2', $latin('Genres')]]),
    'genre as a bare number' => $tag(3, [['TCON', $latin('52')]]),
    'genre number then name' => $tag(3, [['TCON', $latin('(31)Trance')]]),
    'an empty picture' => $tag(3, [['APIC', "\0image/jpeg\0\x03\0" . $jpeg], ['TIT2', $latin('No Picture')]]),
];
foreach ($id3 as $label => $bytes) {
    [$head, $tail] = is_array($bytes) ? [$bytes['head'], $bytes['tail']] : [$bytes, ''];
    $file = "{$tmp}/id3-" . count($log) . '.mp3';
    file_put_contents($file, $head . $frames . $tail);
    $read = wp_read_audio_metadata($file);
    $say("ID3: {$label}", $typed(is_array($read) ? array_diff_key($read, array_flip(['dataformat', 'channels', 'sample_rate', 'bitrate', 'channelmode', 'bitrate_mode', 'lossless', 'encoder_options', 'compression_ratio', 'fileformat', 'mime_type', 'length', 'length_formatted'])) : $read));
}
$say('filter calls (file, format, data is an array)', array_map(static fn (array $call) => [preg_replace('/^id3-\d+/', 'id3', $call[0]), $call[1], $call[2]], $calls));
remove_filter('wp_read_audio_metadata', $seen, 10);
remove_filter('wp_read_video_metadata', $seen, 10);

// What the filters return is what the readers return.
$changed = static fn ($metadata) => ['zz_added' => true] + (array) $metadata;
add_filter('wp_read_audio_metadata', $changed);
add_filter('wp_read_video_metadata', $changed);
$say('the filters answer', $typed([array_slice((array) wp_read_audio_metadata("{$media}/a08-pcm16.wav"), 0, 3), array_slice((array) wp_read_video_metadata("{$media}/v05-vp8-silent.webm"), 0, 3)]));
remove_filter('wp_read_audio_metadata', $changed);
remove_filter('wp_read_video_metadata', $changed);

array_map('unlink', glob("{$tmp}/*"));
@rmdir($tmp);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
