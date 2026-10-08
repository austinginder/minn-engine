<?php
/**
 * kses's inner helpers as the reference runs them on their own (probe
 * kses-internals): which code points are valid, the entity callbacks
 * (decoding a number, normalising named, decimal and hex references, XML
 * names), a scheme judged against the allowed protocols, an attribute
 * string parsed into pairs and the parse's error recovery, one element's
 * attributes filtered, and one tag through the split pass and its
 * callback. Nothing is written. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$try = static function (Closure $call) {
    try {
        return $call();
    } catch (Throwable $e) {
        return ['throws', get_class($e)];
    }
};

$say('valid unicode', array_map(static fn ($i) => [$i, valid_unicode($i)], [0x9, 0xA, 0xD, 0x20, 0x41, 0x7F, 0xD7FF, 0xD800, 0xDFFF, 0xE000, 0xFFFD, 0xFFFE, 0xFFFF, 0x10000, 0x10FFFF, 0x110000, 0, 1, 0x8, 0xB, 0x1F, -1, '65', '0x41']));

$say('decode a decimal reference', array_map(static fn ($n) => bin2hex((string) _wp_kses_decode_entities_chr(['&#' . $n . ';', $n])), ['65', '0065', '169', '8364', '128512', '0', '1114111']));
$say('decode a hex reference', array_map(static fn ($n) => bin2hex((string) _wp_kses_decode_entities_chr_hexdec(['&#x' . $n . ';', $n])), ['41', '0041', 'a9', 'A9', '20AC', '1F600', '0']));

$say('named entities', array_map(static fn ($name) => wp_kses_named_entities(['&' . $name . ';', $name]), ['amp', 'nbsp', 'copy', 'hellip', 'eacute', 'bogus', 'AMP', 'apos', 'quot', 'lt', '']));
$say('xml named entities', array_map(static fn ($name) => wp_kses_xml_named_entities(['&' . $name . ';', $name]), ['amp', 'lt', 'gt', 'quot', 'apos', 'nbsp', 'copy', 'hellip', 'bogus', '']));
$say('decimal references normalised', array_map(static fn ($n) => wp_kses_normalize_entities2(['&#' . $n . ';', $n]), ['65', '065', '0000065', '169', '0', '8', '55296', '65533', '65534', '65535', '1114111', '1114112', '99999999']));
$say('hex references normalised', array_map(static fn ($n) => wp_kses_normalize_entities3(['&#x' . $n . ';', $n]), ['41', '041', 'a9', 'A9', '0', '8', 'D800', 'FFFD', 'FFFE', 'ffff', '10FFFF', '110000', 'fffffff']));

$say('comments through the whole pass', array_map(static fn ($html) => wp_kses_post($html), [
    '<!-- a > b -->',
    'x <!-- <b>kept</b><script>gone</script> --> y',
    '<!-- one -- two -->',
    '<!---->',
    '<!-- unclosed <b>bold</b>',
    '<p>t</p><!-- c --><p>u</p>',
]));
$say('whole markup normalised', [wp_kses_normalize_entities('a &#65534; b &#xFFFE; c &#65533; d &#xffff; e &#1114111;'), wp_kses_normalize_entities('x &#65534; y', 'xml')]);

$protocols = ['http', 'https', 'mailto', 'feed'];
$say('a scheme judged', array_map(static fn ($scheme) => wp_kses_bad_protocol_once2($scheme, $protocols), ['http', 'HTTP', 'https', 'javascript', 'JavaScript', 'jav&#x61;script', 'jav&#97;script', ' http', 'mailto', 'feed', 'data', '', 'ht tp', "java\tscript"]));

$say('whole URLs judged', array_map(static fn ($url) => wp_kses_bad_protocol($url, $protocols), ['HTTP://x.test/', 'Https://x.test/', 'JaVaScript:alert(1)', 'MAILTO:a@b.test', 'h t t p://x.test/']));
$say('an attribute string parsed', array_map(static fn ($attr) => $try(static fn () => wp_kses_hair_parse($attr)), [
    'a="b" c=d e',
    'href="x" title=\'y z\' data-x',
    'a = "b"',
    'a="b',
    '"bad" a="b"',
    '',
    '   ',
    'a="b"c="d"',
    'x=y/',
    'one two three',
    'a="<>"',
]));
$say('a parse error skipped', array_map(static fn ($attr) => $try(static fn () => wp_kses_html_error($attr)), ['bad stuff a="b"', '"q" c', 'nothing', '', '= "x" y', 'z']));

$post = wp_kses_allowed_html('post');
$all = wp_allowed_protocols();
$say('one element\'s attributes', array_map(static fn ($row) => wp_kses_attr($row[0], $row[1], $post, $all), [
    ['a', ' href="https://example.test/" title="t" onclick="evil()"', ],
    ['a', ' href="javascript:alert(1)"'],
    ['a', ''],
    ['p', ' /'],
    ['br', ' /'],
    ['img', ' src="x.png" alt="a" width="10" onerror="x"'],
    ['script', ' src="x.js"'],
    ['div', ' class="c" style="color:red;position:fixed" data-x="1"'],
    ['A', ' HREF="https://example.test/"'],
    ['span', ' class="x" class="y"'],
]));
$say('one tag through the split pass', array_map(static fn ($tag) => wp_kses_split2($tag, $post, $all), [
    '<a href="https://example.test/" onclick="x">',
    '</a>',
    '</ p>',
    '<script>',
    '<!-- note -->',
    '<!-- <b>inner</b> -->',
    '<p class="x"/>',
    '<br/>',
    '<>',
    '<',
    '>',
    '<1>',
    '<blink>',
    '<IMG SRC="x.png">',
]));
global $pass_allowed_html, $pass_allowed_protocols;
$pass_allowed_html = ['b' => [], 'a' => ['href' => true]];
$pass_allowed_protocols = ['https'];
$say('the split callback, with the pass\'s rules', array_map(static fn ($tag) => _wp_kses_split_callback([$tag]), ['<b>', '<a href="https://x.test/" title="t">', '<a href="http://x.test/">', '<i>', '</b>']));
unset($GLOBALS['pass_allowed_html'], $GLOBALS['pass_allowed_protocols']);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
