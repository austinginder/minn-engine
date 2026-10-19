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

// The tables the readers keep, through files changed byte by byte (checksums made again): the LAME
// tag's methods, presets, lowpass, minimum bitrate, gapless flag and source rate; MPEG-4 codes and
// vendors; wave format tags; AVI codecs; Vorbis nominal bitrates and layouts; Flash codec numbers.
$read = static function (string $bytes, string $name, bool $video = false) use ($tmp) {
    file_put_contents("{$tmp}/{$name}", $bytes);
    return $video ? wp_read_video_metadata("{$tmp}/{$name}") : wp_read_audio_metadata("{$tmp}/{$name}");
};
$crc16 = static function (string $bytes): int {
    $crc = 0;
    foreach (str_split($bytes) as $byte) {
        $crc ^= ord($byte);
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 1) !== 0 ? ($crc >> 1) ^ 0xA001 : $crc >> 1;
        }
    }
    return $crc;
};
$lame = static function (string $mp3, int $frame, array $set) use ($crc16): string {
    $word = unpack('N', substr($mp3, $frame, 4))[1];
    $side = (($word >> 19) & 3) === 3 ? ((($word >> 6) & 3) === 3 ? 17 : 32) : ((($word >> 6) & 3) === 3 ? 9 : 17);
    $xing = $frame + 4 + $side;
    $flags = unpack('N', substr($mp3, $xing + 4, 4))[1];
    $at = $xing + 8 + (($flags & 1) ? 4 : 0) + (($flags & 2) ? 4 : 0) + (($flags & 4) ? 100 : 0);
    if (isset($set['quality'])) {
        $mp3 = substr_replace($mp3, pack('N', $set['quality']), $at, 4);
    }
    $at += ($flags & 8) ? 4 : 0;
    if (isset($set['encoder'])) {
        $mp3 = substr_replace($mp3, str_pad($set['encoder'], 9, ' '), $at, 9);
    }
    $mp3[$at + 9] = chr((ord($mp3[$at + 9]) & 0xF0) | ($set['method'] ?? (ord($mp3[$at + 9]) & 15)));
    if (isset($set['lowpass'])) {
        $mp3[$at + 10] = chr(intdiv($set['lowpass'], 100));
    }
    if (isset($set['flags'])) {
        $mp3[$at + 19] = chr(($set['flags'] << 4) | (ord($mp3[$at + 19]) & 15));
    }
    if (isset($set['minimum'])) {
        $mp3[$at + 20] = chr($set['minimum']);
    }
    if (isset($set['source'])) {
        $mp3[$at + 24] = chr(($set['source'] << 6) | (ord($mp3[$at + 24]) & 0x3F));
    }
    if (isset($set['preset'])) {
        $mp3 = substr_replace($mp3, pack('n', (unpack('n', substr($mp3, $at + 26, 2))[1] & 0xF800) | $set['preset']), $at + 26, 2);
    }
    return substr_replace($mp3, pack('n', $crc16(substr($mp3, $frame, $at + 34 - $frame))), $at + 34, 2);
};
$vbr = (string) file_get_contents("{$media}/a02-vbr-v2.mp3");
$cbr = (string) file_get_contents("{$media}/a03-mono-v1.mp3");
$variants = [
    ['method' => 4, 'preset' => 480], ['method' => 3, 'preset' => 480], ['method' => 4, 'preset' => 500, 'lowpass' => 22100], ['method' => 4, 'preset' => 460, 'lowpass' => 18000],
    ['method' => 3, 'preset' => 470, 'lowpass' => 19500], ['method' => 3, 'preset' => 1004, 'lowpass' => 19000], ['method' => 3, 'preset' => 1001], ['method' => 4, 'preset' => 1010],
    ['method' => 3, 'preset' => 430, 'source' => 2], ['method' => 3, 'preset' => 410, 'minimum' => 0], ['method' => 3, 'preset' => 490, 'flags' => 4], ['method' => 4, 'preset' => 440, 'source' => 0],
    ['method' => 3, 'preset' => 0, 'quality' => 57], ['method' => 3, 'preset' => 0, 'quality' => 0], ['method' => 3, 'preset' => 0, 'quality' => 101], ['method' => 2, 'preset' => 0],
    ['method' => 9, 'preset' => 128], ['method' => 1, 'preset' => 0], ['method' => 1, 'preset' => 64], ['method' => 1, 'preset' => 320], ['method' => 8, 'preset' => 0],
    ['method' => 0, 'preset' => 0], ['method' => 7, 'preset' => 0], ['method' => 15, 'preset' => 0], ['method' => 3, 'preset' => 16], ['method' => 3, 'preset' => 405],
    ['method' => 3, 'preset' => 1008], ['method' => 3, 'preset' => 2047], ['method' => 4, 'preset' => 480, 'encoder' => 'LAME3.90.'], ['method' => 4, 'preset' => 480, 'encoder' => 'Lavc61.3'],
];
$say('LAME tags', array_map(static function (array $set) use ($read, $lame, $vbr, $typed): array {
    $meta = $read($lame($vbr, 243, $set), 'lame.mp3');
    return [$set, $typed(array_intersect_key((array) $meta, array_flip(['bitrate', 'bitrate_mode', 'codec', 'encoder', 'encoder_options', 'length'])))];
}, $variants));
$say('LAME tags in an MPEG-2 mono file', array_map(static function (array $set) use ($read, $lame, $cbr, $typed): array {
    $meta = $read($lame($cbr, 0, $set), 'lame2.mp3');
    return [$set, $typed(array_intersect_key((array) $meta, array_flip(['bitrate', 'bitrate_mode', 'encoder_options'])))];
}, [['method' => 1, 'preset' => 64], ['method' => 8, 'preset' => 0], ['method' => 0, 'preset' => 0], ['method' => 4, 'preset' => 470], ['method' => 3, 'preset' => 430, 'source' => 1]]));

$movie = (string) file_get_contents("{$media}/v01-h264-aac.mp4");
$entry = strpos($movie, 'stsd') + 12;
$sound = strpos($movie, 'mp4a', strpos($movie, 'stsd', $entry));
$codes = ['avc1', 'hvc1', 'mp4v', 'jpeg', 'mjpa', 'png ', 'rle ', 'SVQ3', 'cvid', 'DIVX', 'xvid', '3IV2', 'avc3', 'zzzz'];
$say('MPEG-4 video codes', array_map(static fn (string $code) => [$code, array_intersect_key((array) $read(substr_replace($movie, $code, $entry + 4, 4), 'code.mp4', true), array_flip(['bitrate', 'mime_type', 'fileformat', 'dataformat', 'codec', 'lossless']))], $codes));
$say('MPEG-4 video with a vendor', array_map(static fn (string $code) => [$code, array_intersect_key((array) $read(substr_replace(substr_replace($movie, $code, $entry + 4, 4), 'appl', $entry + 20, 4), 'vendor.mp4', true), array_flip(['bitrate', 'fileformat', 'dataformat', 'codec', 'lossless']))], ['avc1', 'jpeg', 'mp4v']));
$say('MPEG-4 sound codes', array_map(static fn (string $code) => [$code, array_intersect_key((array) $read(substr_replace($movie, $code, $sound, 4), 'sound.mp4', true), array_flip(['bitrate', 'mime_type', 'fileformat', 'codec', 'audio']))], ['alac', 'raw ', 'sowt', 'ac-3']));

$wave = (string) file_get_contents("{$media}/a08-pcm16.wav");
$tagAt = strpos($wave, 'fmt ') + 8;
$say('wave format tags', array_map(static fn (int $tag) => [$tag, array_intersect_key((array) $read(substr_replace($wave, pack('v', $tag), $tagAt, 2), 'tag.wav'), array_flip(['dataformat', 'codec', 'lossless']))], [0, 1, 2, 3, 6, 7, 0x11, 0x31, 0x50, 0x92, 0x161, 0x2000, 0x08AE, 0x674F, 0x1234]));
$avi = (string) file_get_contents("{$media}/v07-mpeg4.avi");
$handler = strpos($avi, 'strh') + 12;
$compression = strpos($avi, 'strf', $handler) + 24;
$say('AVI video codecs', array_map(static fn (array $pair) => [$pair, $read(substr_replace(substr_replace($avi, $pair[0], $handler, 4), $pair[1], $compression, 4), 'codec.avi', true)['codec'] ?? null], [['XVID', 'XVID'], ['DIVX', 'DIVX'], ['FMP4', 'XVID'], ['H264', 'H264'], ['MJPG', 'MJPG'], ['divx', 'divx'], ['xvid', 'xvid'], ['MP42', 'MP42'], ['zzzz', 'zzzz']]));

$crc32 = static function (string $page): int {
    static $table = null;
    if ($table === null) {
        for ($i = 0; $i < 256; $i++) {
            $value = $i << 24;
            for ($bit = 0; $bit < 8; $bit++) {
                $value = ($value & 0x80000000) !== 0 ? (($value << 1) ^ 0x04C11DB7) & 0xFFFFFFFF : ($value << 1) & 0xFFFFFFFF;
            }
            $table[$i] = $value;
        }
    }
    $crc = 0;
    foreach (str_split($page) as $byte) {
        $crc = (($crc << 8) & 0xFFFFFFFF) ^ $table[(($crc >> 24) & 0xFF) ^ ord($byte)];
    }
    return $crc;
};
$ogg = (string) file_get_contents("{$media}/a10-vorbis.ogg");
$vorbis = static function (array $set) use ($ogg, $crc32): string {
    $head = strpos($ogg, "\x01vorbis") + 7;
    if (isset($set['channels'])) {
        $ogg[$head + 4] = chr($set['channels']);
    }
    if (isset($set['rate'])) {
        $ogg = substr_replace($ogg, pack('V', $set['rate']), $head + 5, 4);
    }
    $ogg = substr_replace($ogg, pack('V', $set['nominal']), $head + 13, 4);
    $length = 27 + ord($ogg[26]) + array_sum(array_map('ord', str_split(substr($ogg, 27, ord($ogg[26])))));
    $ogg = substr_replace($ogg, "\0\0\0\0", 22, 4);
    return substr_replace($ogg, pack('V', $crc32(substr($ogg, 0, $length))), 22, 4);
};
$say('Vorbis nominal bitrates', array_map(static fn (array $set) => [$set, $read($vorbis($set), 'nominal.ogg')['encoder_options'] ?? null], [
    ['nominal' => 0], ['nominal' => 45000], ['nominal' => 63500], ['nominal' => 112000], ['nominal' => 130000], ['nominal' => 259500], ['nominal' => 499821], ['nominal' => 671000],
    ['nominal' => 112000, 'channels' => 1], ['nominal' => 112000, 'rate' => 48000], ['nominal' => 112000, 'rate' => 32000], ['nominal' => 40222, 'channels' => 1, 'rate' => 22050],
]));
$flash = (string) file_get_contents("{$media}/v08-flash.flv");
$say('Flash codec numbers', array_map(static function (int $code) use ($flash, $read): array {
    $bytes = substr_replace($flash, pack('E', (float) $code), strpos($flash, 'videocodecid') + 13, 8);
    $bytes = substr_replace($bytes, pack('E', (float) $code), strpos($bytes, 'audiocodecid') + 13, 8);
    $meta = (array) $read($bytes, 'codec.flv', true);
    return [$code, $meta['codec'] ?? null, $meta['audio']['codec'] ?? null];
}, range(0, 15)));

// The two helpers the readers use, handed analyses shaped as the reference's media reader shapes them.
$tags = static function (array $data, array $metadata = []): array {
    wp_add_id3_tag_data($metadata, $data);
    return $metadata;
};
$say('wp_add_id3_tag_data', [
    'ID3v2 first values' => $tags(['id3v2' => ['comments' => ['title' => ['T2', 'T2b'], 'artist' => ['A2']]]]),
    'ID3v1 when there is no ID3v2' => $tags(['id3v1' => ['comments' => ['title' => ['T1'], 'year' => ['']]]]),
    'ID3v2 wins outright' => $tags(['id3v2' => ['comments' => ['title' => ['T2']]], 'id3v1' => ['comments' => ['title' => ['T1'], 'artist' => ['A1']]]]),
    'an empty ID3v2 list falls through' => $tags(['id3v2' => ['comments' => []], 'id3v1' => ['comments' => ['title' => ['one']]]]),
    'other tags are not read' => $tags(['tags' => ['id3v2' => ['title' => ['T']]], 'comments' => ['title' => ['C']], 'quicktime' => ['comments' => ['title' => ['Q']]]]),
    'length left out' => $tags(['id3v2' => ['comments' => ['length' => ['100'], 'LENGTH' => ['5'], 'title' => ['t']]]]),
    'values made text' => $tags(['id3v2' => ['comments' => ['title' => [], 'artist' => [''], 'n' => [0], 'num' => [7], 'f' => [1.5], 'b' => [true]]]]),
    'lists kept, cleaned' => $tags(['id3v2' => ['comments' => ['genre' => [['<script>x</script><b>y</b>', ['<i>z</i>']]], 'picture' => [['data' => 'x']]]]]),
    'markup cleaned' => $tags(['id3v2' => ['comments' => ['title' => ['<script>x</script><b>y</b> & <a href="javascript:z">l</a>']]]]),
    'the first value by order' => $tags(['id3v2' => ['comments' => ['title' => [3 => 'three', 1 => 'one']]]]),
    'terms of use mended' => $tags(['id3v2' => ['comments' => ['terms_of_use' => ['yright notice. abc'], 'copyright' => ['yright x']]]]),
    'metadata kept beside' => $tags(['id3v2' => ['comments' => ['title' => ['new']]]], ['title' => 'old', 'keep' => 1]),
]);
$say('wp_get_media_creation_timestamp', array_map(static fn (array $data) => wp_get_media_creation_timestamp($data), [
    'nothing' => [],
    'no format' => ['quicktime' => ['moov' => ['subatoms' => [['creation_time_unix' => 5]]]]],
    'QuickTime' => ['fileformat' => 'quicktime', 'quicktime' => ['moov' => ['subatoms' => [['name' => 'mvhd', 'creation_time_unix' => 1234]]]]],
    'MPEG-4 reads the first box whatever it is' => ['fileformat' => 'mp4', 'quicktime' => ['moov' => ['subatoms' => [['name' => 'trak', 'creation_time_unix' => 5], ['name' => 'mvhd', 'creation_time_unix' => 77]]]]],
    'MPEG-4 zero and before 1970' => ['fileformat' => 'mp4', 'quicktime' => ['moov' => ['subatoms' => [['creation_time_unix' => -2082844800]]]]],
    'a number as text' => ['fileformat' => 'quicktime', 'quicktime' => ['moov' => ['subatoms' => [['creation_time_unix' => '12.7']]]]],
    'Matroska creation time' => ['fileformat' => 'matroska', 'matroska' => ['comments' => ['creation_time' => ['2020-01-02T03:04:05Z']], 'info' => [['DateUTC_unix' => 555]]]],
    'Matroska creation time that is no date' => ['fileformat' => 'matroska', 'matroska' => ['comments' => ['creation_time' => ['not a date']], 'info' => [['DateUTC_unix' => 555]]]],
    'WebM date' => ['fileformat' => 'webm', 'matroska' => ['info' => [['DateUTC_unix' => '556']]]],
    'a date only in the second info' => ['fileformat' => 'matroska', 'matroska' => ['info' => [['x' => 1], ['DateUTC_unix' => 558]]]],
    'Windows Media' => ['fileformat' => 'asf', 'asf' => ['file_properties_object' => ['creation_date_unix' => 0]]],
    'another format' => ['fileformat' => 'riff', 'riff' => []],
    'a format is matched exactly' => ['fileformat' => 'MP4', 'quicktime' => ['moov' => ['subatoms' => [['creation_time_unix' => 9]]]]],
]));

array_map('unlink', glob("{$tmp}/*"));
@rmdir($tmp);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
