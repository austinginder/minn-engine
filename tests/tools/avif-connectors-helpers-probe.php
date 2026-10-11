<?php
/**
 * Two small helper families (probe avif-connectors-helpers): the AVIF
 * parser's stream and byte helpers (Avifinfo\read, Avifinfo\skip,
 * Avifinfo\read_big_endian) on a stream in memory, at its start, its end
 * and past it; and the connectors the Connectors options page is given
 * (_wp_connectors_get_connector_script_module_data), with no keys stored
 * and with one stored, and the hook that adds them. The probe's option is
 * put back as it was.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// The parser's helpers load with its file, as wp_get_avif_info loads them.
if (!function_exists('Avifinfo\read')) {
    require_once ABSPATH . WPINC . '/class-avif-info.php';
}
$stream = static function (string $bytes) {
    $handle = fopen('php://memory', 'w+b');
    fwrite($handle, $bytes);
    rewind($handle);
    return $handle;
};
$hex = static fn ($value) => is_string($value) ? bin2hex($value) : $value;

$handle = $stream("\x00\x00\x00\x1cftypavif\x01\x02\x03");
$reads = [];
$reads['four bytes'] = [$hex(Avifinfo\read($handle, 4)), ftell($handle)];
$reads['the type'] = [$hex(Avifinfo\read($handle, 4)), ftell($handle)];
$reads['skipped four'] = [Avifinfo\skip($handle, 4), ftell($handle)];
$reads['what is left, asked for more'] = [$hex(Avifinfo\read($handle, 8)), ftell($handle)];
$reads['at the end'] = [$hex(Avifinfo\read($handle, 1)), ftell($handle)];
$reads['skipped past the end'] = [Avifinfo\skip($handle, 10), ftell($handle)];
$reads['skipped nothing'] = [Avifinfo\skip($stream('abc'), 0)];
$say('reading a stream', $reads);

$say('big-endian numbers', [
    'one byte' => Avifinfo\read_big_endian("\x7f", 1),
    'two bytes' => Avifinfo\read_big_endian("\x01\x00", 2),
    'three bytes' => Avifinfo\read_big_endian("\x01\x02\x03", 3),
    'four bytes' => Avifinfo\read_big_endian("\x00\x00\x00\x1c", 4),
    'the largest four' => Avifinfo\read_big_endian("\xff\xff\xff\xff", 4),
    'fewer than the input holds' => Avifinfo\read_big_endian("\x01\x02\x03\x04", 2),
]);

// Connectors register on init, and plugins add their own (Minn Admin adds CleanTalk): a CLI boot of the engine
// has not run the lifecycle yet, as the reference has by the time eval-file runs.
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
add_filter('doing_it_wrong_trigger_error', '__return_false');
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, $message];
}, 10, 2);
// The Connectors options page's data, with no key and with one stored in the database.
$setting = 'connectors_ai_anthropic_api_key';
$kept = get_option($setting, null);
$exists = $kept !== null;
$connectors = static fn () => _wp_connectors_get_connector_script_module_data(['zz' => 1]);
$say('the connectors the options page is given', [
    'hooked' => has_filter('script_module_data_options-connectors-wp-admin', '_wp_connectors_get_connector_script_module_data'),
    'with no key stored' => $connectors(),
]);
update_option($setting, 'sk-ant-zz-probe-key-000000000000');
$wrong = [];
$say('an AI provider with a key stored', [$connectors()['connectors']['anthropic'] ?? null, 'mistakes reported' => $wrong]);
$exists ? update_option($setting, $kept) : delete_option($setting);
// A spam filter with a key stored is connected.
$akismet = get_option('wordpress_api_key', null);
update_option('wordpress_api_key', 'zzakismetprobekey');
$say('a spam filter with a key stored', $connectors()['connectors']['akismet'] ?? null);
$akismet === null ? delete_option('wordpress_api_key') : update_option('wordpress_api_key', $akismet);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
