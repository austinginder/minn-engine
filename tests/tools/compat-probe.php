<?php
/**
 * compat.php's stand-ins (probe compat): the UTF-8 charset check, whether
 * PCRE's /u can be used, the multibyte fallbacks (length, substring, a
 * character from its code point and back) as WordPress's own versions
 * answer, and the array and number functions newer PHP brings, which the
 * reference supplies on the PHP 8.3 the probes run on. Nothing is written.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$try = static function (Closure $call) {
    try {
        return ['ok', $call()];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage()];
    }
};
$hex = static fn ($value) => is_string($value) ? bin2hex($value) : $value;
// A float JSON cannot carry (NAN, INF) is written out.
$show = static function ($value) use (&$show) {
    if (is_array($value)) {
        return array_map($show, $value);
    }
    if (is_float($value) && (is_nan($value) || is_infinite($value))) {
        return is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF');
    }
    return $value;
};
$deprecated = [];
add_action('deprecated_argument_run', static function ($function, $message, $version) use (&$deprecated) {
    $deprecated[] = [$function, $version];
}, 10, 3);
add_filter('deprecated_argument_trigger_error', '__return_false');

$say('utf-8 charsets', array_map(static fn ($slug) => [$slug, _is_utf8_charset($slug)], ['utf-8', 'UTF-8', 'utf8', 'UTF8', 'Utf-8', 'utf-16', 'iso-8859-1', '', 'utf_8', ' utf-8']));
$say('pcre /u', [_wp_can_use_pcre_u(), _wp_can_use_pcre_u(false), _wp_can_use_pcre_u(), _wp_can_use_pcre_u('reset'), _wp_can_use_pcre_u(), $deprecated]);

$strings = ['', 'plain', "caf\xC3\xA9", "\xF0\x9F\x98\x80 smile", "bad \xE9 byte", "\xE2\x82", "a\x00b", "\xC3\xA9\xC3\xA9\xC3\xA9"];
$say('mb strlen', array_map(static fn ($s) => [bin2hex($s), _mb_strlen($s), _mb_strlen($s, 'UTF-8'), _mb_strlen($s, 'ISO-8859-1'), _mb_strlen($s, '8bit')], $strings));
$say('mb substr', array_map(static fn ($row) => [bin2hex($row[0]), array_slice($row, 1), $hex(_mb_substr(...$row))], [
    ["caf\xC3\xA9 au lait", 2, 3],
    ["caf\xC3\xA9 au lait", -4],
    ["caf\xC3\xA9 au lait", 3, null],
    ["caf\xC3\xA9 au lait", 0, -2],
    ["caf\xC3\xA9", 10],
    ["\xF0\x9F\x98\x80x", 0, 1],
    ["caf\xC3\xA9", 1, 2, 'ISO-8859-1'],
    ["bad \xE9 byte", 3, 3],
    ['', 0, 1],
]));
$say('mb chr', array_map(static fn ($code) => [$code, $hex(_mb_chr($code)), $hex(_mb_chr($code, 'UTF-8'))], [65, 233, 0x20AC, 0x1F600, 0, 0xD800, 0x110000, -1, 0x10FFFF, '66']));
$say('mb chr, another encoding', [$hex(_mb_chr(65, 'ISO-8859-1')), $hex(_mb_chr(233, 'ISO-8859-1')), $hex(_mb_chr(0x20AC, 'ISO-8859-1'))]);
$say('mb ord', array_map(static fn ($s) => [bin2hex($s), _mb_ord($s), _mb_ord($s, 'UTF-8')], ['A', "\xC3\xA9", "\xE2\x82\xAC", "\xF0\x9F\x98\x80", "\xE9", '', "\xC3", 'AB', "\xC3\xA9x"]));
$say('mb ord, another encoding', [_mb_ord("\xE9", 'ISO-8859-1'), _mb_ord('A', 'ISO-8859-1')]);

$list = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
$even = static fn ($value) => $value % 2 === 0;
$byKey = static fn ($value, $key) => $key === 'c';
$say('array helpers', [
    array_find($list, $even), array_find($list, static fn () => false), array_find([], $even), array_find($list, $byKey),
    array_find_key($list, $even), array_find_key($list, static fn () => false), array_find_key([3, 4], $even),
    array_any($list, $even), array_any($list, static fn () => false), array_any([], $even),
    array_all($list, static fn ($v) => $v > 0), array_all($list, $even), array_all([], $even),
    array_first($list), array_first([]), array_first([5 => 'x', 2 => 'y']), array_first([null, 1]),
    array_last($list), array_last([]), array_last([5 => 'x', 2 => 'y']),
]);
$say('array helpers with a truthy callback', [array_find([0, '', 'x'], static fn ($v) => $v), array_any([0, 1], static fn ($v) => $v), array_all([1, '1', 'a'], static fn ($v) => $v)]);

$say('clamp', array_map(static fn ($row) => [$show($row), $show($try(static fn () => clamp(...$row)))], [
    [5, 1, 10], [-5, 1, 10], [50, 1, 10], [1, 1, 10], [10, 1, 10],
    [5.5, 1, 10], [5, 1.5, 2.5], [0.1, 0.2, 0.3],
    [5, 10, 1], [5, 5, 5], ['7', 1, 10], ['b', 'a', 'c'], ['z', 'a', 'c'],
    [NAN, 1, 10], [5, NAN, 10], [5, 1, NAN], [INF, 1, 10], [-INF, 1, 10],
    [null, 1, 10], [[3], [1], [5]],
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
