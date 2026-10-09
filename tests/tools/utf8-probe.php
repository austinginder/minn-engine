<?php
/**
 * UTF-8 helpers (probe utf8): utf8.php's scrub and noncharacter check, and
 * compat-utf8.php's fallbacks they rest on: well-formedness, noncharacters,
 * scrubbing to U+FFFD, the scanner (stopping at a byte or code point limit,
 * the length of an invalid run, resuming after it), code point counts and
 * spans, and ISO-8859-1 to and from UTF-8. Bytes are compared as hex. Same
 * protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$samples = [
    'empty' => '',
    'ascii' => 'abc',
    'two-byte' => "h\xC3\xA9llo",
    'euro' => "\xE2\x82\xAC",
    'emoji' => "a\xF0\x9F\x98\x80b",
    'overlong' => "\xC0\xAF",
    'overlong three' => "\xE0\x80\xAF",
    'surrogate' => "\xED\xA0\x80",
    'past U+10FFFF' => "\xF4\x90\x80\x80",
    'stray continuation' => "a\x80b",
    'two stray' => "\x80\xBF",
    'truncated' => "\xE2\x82",
    'truncated at end' => "abc\xF0\x9F\x98",
    'truncated then text' => "\xE2\x82x",
    'U+FDD0' => "x\xEF\xB7\x90",
    'U+FFFE' => "\xEF\xBF\xBE",
    'U+1FFFF' => "\xF0\x9F\xBF\xBF",
    'U+10FFFF' => "\xF4\x8F\xBF\xBF",
    'NUL' => "a\x00b",
    'BOM' => "\xEF\xBB\xBFx",
    'latin-1' => "caf\xE9",
    'mixed' => "a\x80b\xE2\x82c\xE2\x82\xAC\xFF",
    'F5 lead' => "\xF5\x80\x80\x80",
    'C1 lead' => "\xC1\xBF",
    'invalid then U+FFFE' => "\x80\xEF\xBF\xBE",
    'U+FDEF and U+FDF0' => "\xEF\xB7\xAF\xEF\xB7\xB0",
];
$hex = static fn ($bytes) => is_string($bytes) ? bin2hex($bytes) : $bytes;

// _wp_has_noncharacters_fallback is deprecated; which calls report it, without the notice.
$deprecated = [];
$listen = static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
};
add_action('deprecated_function_run', $listen, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
$say('deprecated calls', [
    (static function () use (&$deprecated) {
        $deprecated = [];
        _wp_has_noncharacters_fallback("\xEF\xBF\xBE");
        return $deprecated;
    })(),
    (static function () use (&$deprecated) {
        $deprecated = [];
        wp_has_noncharacters("\xEF\xBF\xBE");
        wp_scrub_utf8("\x80");
        _wp_scrub_utf8_fallback("\x80");
        _wp_is_valid_utf8_fallback("\x80");
        return $deprecated;
    })(),
]);

$rows = [];
foreach ($samples as $label => $bytes) {
    $rows[$label] = [
        'valid' => [_wp_is_valid_utf8_fallback($bytes), wp_is_valid_utf8($bytes)],
        'noncharacters' => [_wp_has_noncharacters_fallback($bytes), wp_has_noncharacters($bytes)],
        'scrubbed' => [$hex(_wp_scrub_utf8_fallback($bytes)), $hex(wp_scrub_utf8($bytes))],
        'count' => [_wp_utf8_codepoint_count($bytes), _wp_utf8_codepoint_count($bytes, 1), _wp_utf8_codepoint_count($bytes, 0, 3), _wp_utf8_codepoint_count($bytes, 2, 2)],
    ];
}
$say('per sample', $rows);

$spans = [];
foreach ($samples as $label => $bytes) {
    $found = 0;
    $first = _wp_utf8_codepoint_span($bytes, 0, 2, $found);
    $foundFrom = 0;
    $fromOne = _wp_utf8_codepoint_span($bytes, 1, 1, $foundFrom);
    $spans[$label] = [$first, $found, $fromOne, $foundFrom, _wp_utf8_codepoint_span($bytes, 0, 0), _wp_utf8_codepoint_span($bytes, 0, 100)];
}
$say('code point spans', $spans);

$scans = [];
foreach ($samples as $label => $bytes) {
    $steps = [];
    $at = 0;
    // The whole string, stopping at each invalid run and stepping over it.
    for ($guard = 0; $guard < 12 && $at <= strlen($bytes); $guard++) {
        $invalid = -1;
        $non = null;
        $before = $at;
        $found = _wp_scan_utf8($bytes, $at, $invalid, null, null, $non);
        $steps[] = [$before, $found, $at, $invalid, $non];
        if ($at >= strlen($bytes)) {
            break;
        }
        $at += max(1, $invalid);
    }
    $limited = [];
    foreach ([[2, null], [null, 1], [1, 1], [0, null], [null, 0]] as [$maxBytes, $maxPoints]) {
        $at = 0;
        $invalid = -1;
        $non = false;
        $found = _wp_scan_utf8($bytes, $at, $invalid, $maxBytes, $maxPoints, $non);
        $limited[] = [$maxBytes, $maxPoints, $found, $at, $invalid, $non];
    }
    $scans[$label] = [$steps, $limited];
}
$say('scans', $scans);
$from = static function (string $bytes, int $at, ?int $maxBytes, ?int $maxPoints, $non) {
    $invalid = -1;
    $found = _wp_scan_utf8($bytes, $at, $invalid, $maxBytes, $maxPoints, $non);
    return [$found, $at, $invalid, $non];
};
$say('scans from inside', [
    $from('abcdef', 1, 2, null, null),
    $from('abcdef', 4, 2, null, null),
    $from('abcdef', 2, null, 2, null),
    $from("ab\xE2\x82\xACcd", 2, 1, null, null),
    $from("ab\x80cd", 2, null, null, null),
    $from('abc', 3, null, null, null),
    $from('abc', 1, 0, null, null),
    $from('abc', 0, null, null, true),
    $from("\xEF\xBF\xBE", 0, null, null, true),
    $from("\xE2\x82\xAC", 0, null, null, true),
    $from("\xEF\xBB\xBF", 0, null, null, true),
    $from("\xF0\x9F\x98\x80", 0, null, null, 'x'),
    $from("\xEF\xBF\xBE\xEF\xBB\xBF", 0, null, null, null),
    $from("\xEF\xBB\xBF\xEF\xBF\xBE", 0, null, null, null),
    $from("\xE2\x82\xAC", 0, 1, null, null),
    $from("\xE2\x82x", 0, 1, null, null),
    $from("\xF0\x9F\x98", 0, 2, null, null),
    $from("\xF0\x9F\x98", 0, 5, null, null),
    $from("a\xF0\x9F\x98", 0, 2, null, null),
    $from("a\xF0\x9F\x98", 1, 2, null, null),
    $from("\xEF\xBF\xBEx", 0, null, 1, null),
]);

$latin = [];
foreach ($samples as $label => $bytes) {
    $latin[$label] = [$hex(_wp_utf8_decode_fallback($bytes)), $hex(_wp_utf8_encode_fallback($bytes))];
}
$latin['every latin-1 byte'] = [bin2hex(_wp_utf8_encode_fallback(implode('', array_map('chr', range(0x7E, 0xFF))))), bin2hex(_wp_utf8_decode_fallback(_wp_utf8_encode_fallback(implode('', array_map('chr', range(0x00, 0xFF))))))];
$say('latin-1', $latin);

$say('not strings', [wp_scrub_utf8(''), wp_has_noncharacters('')]);
remove_action('deprecated_function_run', $listen, 10);
remove_filter('deprecated_function_trigger_error', '__return_false');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
