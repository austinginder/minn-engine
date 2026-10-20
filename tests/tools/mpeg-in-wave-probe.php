<?php
/**
 * MPEG audio inside a wave file, as wp_read_audio_metadata measures it: the
 * data must begin with a frame; a run of fewer than 51 frames that stops
 * short of the end is passed over for the next run; 51 frames at one rate
 * make the stream constant at that rate (nothing after them read), and
 * otherwise every frame to the end counts toward the mean, across any
 * bytes between runs. Built in a temporary folder of the probe's own.
 * Same protocol as api-probe.php.
 */

require_once ABSPATH . 'wp-admin/includes/media.php';
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$frame = static fn (int $index, int $length): string => pack('N', (0x7FF << 21) | (2 << 19) | (1 << 17) | (1 << 16) | ($index << 12) | (1 << 10) | (3 << 6)) . str_repeat("\0", $length - 4);
$low = $frame(1, 24);
$high = $frame(2, 48);
$gap = str_repeat("\0", 1000);
$cases = [
    '10 frames at 8 kbps, then 16 kbps' => str_repeat($low, 10) . str_repeat($high, 2000),
    '50 at 8, then 16' => str_repeat($low, 50) . str_repeat($high, 2000),
    '51 at 8, then 16' => str_repeat($low, 51) . str_repeat($high, 2000),
    '1000 at 8, then 16' => str_repeat($low, 1000) . str_repeat($high, 2000),
    '10 at 8, a gap, 100 at 16' => str_repeat($low, 10) . $gap . str_repeat($high, 100),
    '50 at 8, a gap, 100 at 16' => str_repeat($low, 50) . $gap . str_repeat($high, 100),
    '51 at 8, a gap, 100 at 16' => str_repeat($low, 51) . $gap . str_repeat($high, 100),
    'alternating, a gap, 3 at 16' => str_repeat($low . $high, 100) . $gap . str_repeat($high, 3),
    '5 at 8 and 95 at 16, a gap, 200 at 8' => str_repeat($low, 5) . str_repeat($high, 95) . $gap . str_repeat($low, 200),
    '30 at 8 to the end' => str_repeat($low, 30),
    '4 at 8 and one at 16 to the end' => str_repeat($low, 4) . $high,
    'bytes before 60 at 8' => str_repeat("\x55", 777) . str_repeat($low, 60),
    'no frames' => str_repeat("\0", 3000),
];
$folder = sys_get_temp_dir() . '/zz-mpeg-in-wave-' . getmypid();
@mkdir($folder);
$format = 'fmt ' . pack('V', 16) . pack('vvVVvv', 0x55, 1, 24000, 0, 1, 0);
try {
    foreach ($cases as $label => $data) {
        $path = $folder . '/case.wav';
        file_put_contents($path, 'RIFF' . pack('V', 0) . 'WAVE' . $format . 'data' . pack('V', strlen($data)) . $data);
        $meta = wp_read_audio_metadata($path);
        $say($label, [$meta['bitrate'] ?? null, $meta['bitrate_mode'] ?? null, $meta['length'] ?? null]);
        unlink($path);
    }
} finally {
    @rmdir($folder);
}
echo json_encode($log);
