<?php

declare(strict_types=1);

use Minn\Media\Av\{Ac3, Analyzer, ExpGolomb, Genres, Mpeg, Source};

/**
 * The media readers (Media\Av) on the corpus in tests/fixtures/media, with
 * no site behind them. The figures are the reference's (probe media-meta,
 * which compares every field); these pin the ones a change would most
 * likely move: each format's type, playing time and stream, MP3's
 * bitrate and LAME options, and the small decoders.
 */
$media = dirname(__DIR__) . '/fixtures/media';
$read = static fn (string $name): array => Analyzer::analyze("{$media}/{$name}") ?? [];
$expect = static fn (mixed $got, mixed $want): bool|string => $got === $want ? true : var_export($got, true) . ' (wanted ' . var_export($want, true) . ')';

$cases = [];
$cases['a missing file is no analysis'] = static fn (): bool|string => $expect(Analyzer::analyze("{$media}/none.mp3"), null);
$cases['bytes read either way round'] = static fn (): bool|string => $expect([Source::bigEndianValue("\x01\x02"), Source::littleEndianValue("\x01\x02")], [258, 513]);
$cases['genre numbers named, gaps unnamed'] = static fn (): bool|string => $expect([Genres::name(17), Genres::name(191), Genres::name(192), Genres::name(240), Genres::name(255)], ['Rock', 'Psybient', null, 'Sacred', null]);
$cases['an MPEG frame header'] = static fn (): bool|string => $expect(array_intersect_key(Mpeg::frameHeader(0xFFFB9004) ?? [], array_flip(['layer', 'bitrate', 'sampleRate', 'length'])), ['layer' => 3, 'bitrate' => 128000, 'sampleRate' => 44100, 'length' => 417]);
$cases['not an MPEG frame header'] = static fn (): bool|string => $expect([Mpeg::frameHeader(0xFFE00000), Mpeg::frameHeader(0x12345678)], [null, null]);
$cases['an AC-3 frame: a mono frame reads its low frequency channel early'] = static fn (): bool|string => $expect(Ac3::frame((string) hex2bin('0b77b92f4c402f84')), ['sampleRate' => 44100, 'bitrate' => 96000, 'channels' => '1.1', 'mode' => 'mono']);
$cases['an AC-3 5.1 frame'] = static fn (): bool|string => $expect(Ac3::frame((string) hex2bin('0b77ef545e40ebf8')), ['sampleRate' => 44100, 'bitrate' => 448000, 'channels' => '5.1', 'mode' => 'surround']);
$cases['a constant-rate MP3 is timed from its Info frame'] = static function () use ($read, $expect): bool|string {
    $data = $read('a01-cbr-tags.mp3');
    return $expect([$data['playtime_seconds'], $data['audio']['bitrate'], $data['audio']['encoder_options'], $data['id3v2']['comments']['totaltracks']], [2.638, 128000, 'CBR128', ['9']]);
};
$cases['a variable-rate MP3 is averaged over its Xing counts'] = static function () use ($read, $expect): bool|string {
    $data = $read('a02-vbr-v2.mp3');
    return $expect([$data['audio']['bitrate'], $data['audio']['encoder_options']], [99079.28427419355, '--preset fast standard -b32 --lowpass 18500']);
};
$cases['an ID3v1 tag is kept as its bytes'] = static fn (): bool|string => $expect($read('a03-mono-v1.mp3')['id3v1']['comments']['genre'], ['Rock']);
$cases['each format typed'] = static function () use ($read, $expect): bool|string {
    $types = [];
    foreach (['a06-aac.m4a', 'a07-alac.m4a', 'a08-pcm16.wav', 'a10-vorbis.ogg', 'a11-opus.ogg', 'a12-flac.flac', 'v01-h264-aac.mp4', 'v02-h264-silent.mp4', 'v04-vp9-opus.webm', 'v07-mpeg4.avi', 'v08-flash.flv', 'v09-matroska.mkv', 'v11-windows.wmv'] as $name) {
        $data = $read($name);
        $types[$name] = $data['fileformat'] . ' ' . $data['mime_type'];
    }
    return $expect($types, [
        'a06-aac.m4a' => 'mp4 audio/mp4', 'a07-alac.m4a' => 'quicktime video/quicktime', 'a08-pcm16.wav' => 'wav audio/wav', 'a10-vorbis.ogg' => 'ogg application/ogg',
        'a11-opus.ogg' => 'ogg audio/ogg; codecs=opus', 'a12-flac.flac' => 'flac audio/flac', 'v01-h264-aac.mp4' => 'mp4 video/mp4', 'v02-h264-silent.mp4' => 'mp4 video/quicktime',
        'v04-vp9-opus.webm' => 'webm video/webm', 'v07-mpeg4.avi' => 'avi video/avi', 'v08-flash.flv' => 'flv video/x-flv', 'v09-matroska.mkv' => 'matroska video/x-matroska', 'v11-windows.wmv' => 'asf video/x-ms-wmv',
    ]);
};
$cases['a video keeps the bitrate its audio leaves'] = static function () use ($read, $expect): bool|string {
    return $expect([$read('v01-h264-aac.mp4')['video']['bitrate'], $read('v07-mpeg4.avi')['video']['bitrate'], $read('v11-windows.wmv')['video']['bitrate']], [131164.0, 89806.45161290321, 327587.0]);
};
$cases['playing times from each container'] = static function () use ($read, $expect): bool|string {
    return $expect([$read('a10-vorbis.ogg')['playtime_seconds'], $read('a12-flac.flac')['playtime_seconds'], $read('v04-vp9-opus.webm')['playtime_seconds'], $read('v08-flash.flv')['playtime_seconds'], $read('v11-windows.wmv')['playtime_seconds']], [2.2, 1.6, 1.52, 1.025, 1.0459999999999998]);
};
$cases['covers found in each format'] = static function () use ($read, $expect): bool|string {
    return $expect([$read('a01-cbr-tags.mp3')['id3v2']['APIC'][0]['image_width'], $read('a06-aac.m4a')['comments']['picture'][0]['image_mime'], $read('a12-flac.flac')['comments']['picture'][0]['image_mime']], [32, 'image/png', 'image/jpeg']);
};
$cases['exponential-Golomb codes, unsigned and signed'] = static function () use ($expect): bool|string {
    $bits = new ExpGolomb((string) hex2bin('a64280'));
    return $expect([$bits->unsigned(), $bits->unsigned(), $bits->unsigned(), $bits->signed(), $bits->signed()], [0, 1, 2, 2, -2]);
};
$cases['codecs inside other containers: MP3 and AC-3 in WAV, MP2 and Vorbis in Matroska, H.264 in Flash'] = static function () use ($read, $expect): bool|string {
    return $expect([
        $read('a16-mp3-vbr.wav')['audio']['bitrate_mode'], $read('a17-ac3.wav')['audio']['channelmode'], $read('a18-ac3-51.wav')['audio']['channels'],
        $read('a19-mp2.mka')['audio']['bitrate'], $read('a20-vorbis.mka')['audio']['sample_rate'],
        [$read('v15-h264-high.flv')['video']['resolution_x'], $read('v15-h264-high.flv')['video']['resolution_y']],
        [$read('v16-h264-baseline.flv')['video']['resolution_x'], $read('v16-h264-baseline.flv')['video']['resolution_y']],
        [$read('v17-h264-ep.flv')['video']['resolution_x'], $read('v17-h264-ep.flv')['video']['resolution_y']],
    ], ['vbr', 'surround', '5.1', 192000, 44100, [16, 160], [100, 50], [16, 64]]);
};
$cases['files made to hurt the readers end quickly, with what they can say'] = static function () use ($expect): bool|string {
    $folder = sys_get_temp_dir() . '/minn-av-hostile-' . getmypid();
    @mkdir($folder);
    $ebml = static fn (string $id, string $payload): string => $id . "\x01" . substr(pack('J', strlen($payload)), 1) . $payload;
    $amf = static fn (string $value): string => "\x02" . pack('n', 10) . 'onMetaData' . "\x08" . pack('N', 1) . pack('n', 8) . 'duration' . $value . "\0\0\x09";
    $flvMeta = static fn (string $amf): string => "FLV\x01\x05\x00\x00\x00\x09\0\0\0\0\x12" . substr(pack('N', strlen($amf)), 1) . "\0\0\0\0\0\0\0" . $amf . pack('N', 11 + strlen($amf));
    $sps = "\x67\x42\x00\x1e" . chr(0b11010000);
    $avc = "\x17\x00\x00\x00\x00\x01\x42\x00\x1e\xff\xe1" . pack('n', strlen($sps)) . $sps;
    $title = "\x00a" . str_repeat("\0", 1048576);
    $frame = 'TIT2' . pack('N', strlen($title)) . "\0\0" . $title;
    $size = strlen($frame);
    $files = [
        // A cycle of reference frames that runs off its parameter set (4,294,967,295 of them, once).
        'cycle.flv' => "FLV\x01\x01\x00\x00\x00\x09\0\0\0\0\x09" . substr(pack('N', strlen($avc)), 1) . "\0\0\0\0\0\0\0" . $avc . pack('N', 11 + strlen($avc)),
        // A title of a million terminators.
        'terminators.mp3' => "ID3\x03\x00\x00" . chr(($size >> 21) & 0x7f) . chr(($size >> 14) & 0x7f) . chr(($size >> 7) & 0x7f) . chr($size & 0x7f) . $frame,
        // A tag that claims 256 MB in a file of twenty bytes.
        'claims.mp3' => "ID3\x03\x00\x00\x7f\x7f\x7f\x7f" . str_repeat("\0", 10),
        // Stream lists that claim the rest of the file, 4,096 times.
        'lists.avi' => 'RIFF' . pack('V', 0) . 'AVI ' . str_repeat('LIST' . pack('V', 16) . 'hdrl' . 'LIST' . pack('V', 0xFFFFFFF0) . 'strl', 4096),
        // A movie box of a million empty tracks.
        'tracks.mov' => pack('N', 16) . "ftypqt  \0\0\0\0" . pack('N', 8 + 8 * 1048576) . 'moov' . str_repeat(pack('N', 8) . 'trak', 1048576),
        // A sound track sampled zero times a second.
        'zero.mkv' => $ebml("\x1A\x45\xDF\xA3", $ebml("\x42\x82", 'matroska')) . $ebml("\x18\x53\x80\x67", $ebml("\x15\x49\xA9\x66", $ebml("\x44\x89", pack('G', 1000.0))) . $ebml("\x16\x54\xAE\x6B", $ebml("\xAE", $ebml("\xD7", "\x01") . $ebml("\x83", "\x02") . $ebml("\x86", 'A_PCM/INT/LIT') . $ebml("\xE1", $ebml("\xB5", pack('G', 0.0)) . $ebml("\x9F", "\x02"))))),
        // A playing time in words, and one that is a flag.
        'words.flv' => $flvMeta($amf("\x02" . pack('n', 3) . 'abc')),
        'flag.flv' => $flvMeta($amf("\x01\x01")),
    ];
    $seen = [];
    $started = microtime(true);
    try {
        foreach ($files as $name => $bytes) {
            file_put_contents("{$folder}/{$name}", $bytes);
            $data = Analyzer::analyze("{$folder}/{$name}");
            $seen[$name] = [$data['video']['resolution_x'] ?? null, isset($data['playtime_seconds'])];
        }
    } finally {
        array_map('unlink', glob("{$folder}/*") ?: []);
        @rmdir($folder);
    }
    $quick = microtime(true) - $started < 5;
    return $expect([$quick, $seen], [true, ['cycle.flv' => [0, false], 'terminators.mp3' => [null, false], 'claims.mp3' => [null, false], 'lists.avi' => [null, false], 'tracks.mov' => [0.0, false], 'zero.mkv' => [null, false], 'words.flv' => [null, false], 'flag.flv' => [null, false]]]);
};
return $cases;
