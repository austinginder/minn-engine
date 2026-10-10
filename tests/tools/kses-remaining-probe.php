<?php
/**
 * The rest of kses.php (probe kses-remaining): the slashed filters (title
 * and post), the post filter applied deep, lower-cased allowed lists, the
 * attribute check (by reference), a bad protocol taken off once, entities
 * decoded, attributes split into pairs, the pre_kses hook, slashes taken off
 * quotes, and the version. Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$markup = [
    '<b>bold</b> <script>alert(1)</script> <a href="javascript:x" onclick="y">link</a>',
    '<p class=\\"zz\\">slashed \\"quotes\\"</p>',
    'plain & simple',
    '<img src="https://zz.example/a.png" alt="A" onerror="x">',
    '<div style="color: red; background: url(javascript:x)">styled</div>',
    '<iframe src="https://zz.example"></iframe><em>kept</em>',
    "it\\'s",
];
wp_set_current_user(1);
$say('slashed filters', [
    array_map(static fn ($text) => wp_filter_kses($text), $markup),
    array_map(static fn ($text) => wp_filter_post_kses($text), $markup),
]);
$say('the post filter deep', [
    wp_kses_post_deep(['a' => '<b>x</b><script>s</script>', 'b' => ['<i>i</i>', 5, true, null], 'c' => (object) ['d' => '<u>u</u><script>z</script>']]),
    wp_kses_post_deep('<b>string</b><script>s</script>'), wp_kses_post_deep(42), wp_kses_post_deep(null),
]);
$say('lower-cased lists', [
    wp_kses_array_lc(['A' => ['HREF' => true, 'Title' => ['X' => 1]], 'b' => [], 'IMG' => ['SRC' => true]]), wp_kses_array_lc([]), wp_kses_array_lc(['A' => true]),
]);

$checks = [];
foreach ([
    ['href', 'https://zz.example', 'href="https://zz.example"', 'n', 'a', ['a' => ['href' => true]]],
    ['onclick', 'x()', 'onclick="x()"', 'n', 'a', ['a' => ['href' => true]]],
    ['title', 'T', 'title="T"', 'n', 'a', ['a' => ['href' => true]]],
    ['data-zz', '1', 'data-zz="1"', 'n', 'div', ['div' => ['data-*' => true]]],
    ['data-zz', '1', 'data-zz="1"', 'n', 'div', ['div' => ['class' => true]]],
    ['aria-label', 'L', 'aria-label="L"', 'n', 'div', ['div' => true]],
    ['disabled', '', 'disabled', 'y', 'button', ['button' => ['disabled' => true]]],
    ['maxlength', '200', 'maxlength="200"', 'n', 'input', ['input' => ['maxlength' => ['maxval' => 100]]]],
    ['maxlength', '50', 'maxlength="50"', 'n', 'input', ['input' => ['maxlength' => ['maxval' => 100]]]],
    ['style', 'color: red', 'style="color: red"', 'n', 'p', ['p' => ['style' => true]]],
    ['style', 'behavior: url(x)', 'style="behavior: url(x)"', 'n', 'p', ['p' => ['style' => true]]],
    ['target', '_blank', 'target="_blank"', 'n', 'a', ['a' => ['target' => ['values' => ['_self']]]]],
    ['target', '_self', 'target="_self"', 'n', 'a', ['a' => ['target' => ['values' => ['_self']]]]],
    ['href', 'x', 'href="x"', 'n', 'zz', ['a' => ['href' => true]]],
    ['HREF', 'x', 'HREF="x"', 'n', 'A', ['a' => ['href' => true]]],
] as [$name, $value, $whole, $vless, $element, $allowed]) {
    $result = wp_kses_attr_check($name, $value, $whole, $vless, $element, $allowed);
    $checks[] = [$name, $result, $name, $value, $whole, $vless];
}
$say('the attribute check', $checks);

$say('bad protocols once', array_map(static fn ($args) => wp_kses_bad_protocol_once(...$args), [
    ['javascript:alert(1)', ['http', 'https']], ['https://zz.example', ['http', 'https']], ['JAVASCRIPT:x', ['http']], ['javascript:javascript:x', ['http']], ['javascript:javascript:x', ['http'], 2],
    ['mailto:a@b', ['http', 'mailto']], ['java&#115;cript:x', ['http']], ['  javascript:x', ['http']], ['data:text/html,x', ['http']], ['relative/path:x', ['http']], ['x', []],
]));
$say('bad protocols with spaces', array_map(static fn ($value) => [wp_kses_bad_protocol($value, ['http', 'https']), wp_kses_bad_protocol_once($value, ['http', 'https'])], [' https://zz.example ', 'https://zz.example ', 'HTTPS://zz.example ', ' javascript:x ', 'http://a b ', ' HTTP://x ', 'https://x', 'ftp://x ', ' mailto:x ', 'https:x ']));
$say('entities decoded', array_map(static fn ($text) => wp_kses_decode_entities($text), ['&#106;&#97;', '&#x6a;&#X61;', '&amp;&lt;&gt;', '&#0;&#99999999;', '&#58;', 'plain', '&#x3A;', '&#39;&quot;']));
$say('attributes split', array_map(static fn ($attr) => wp_kses_hair($attr, wp_allowed_protocols()), [
    'href="https://zz.example" title=\'T\' disabled', 'href=javascript:x onclick=y', 'a="1" a="2"', 'DATA-X="Y"', 'class="a b" style = "c"', '', 'broken="', 'src=https://zz.example/x.png alt=""',
    "title='a\"b'", 'title="a\'b"', 'title=a&amp;b', 'title="a<b>c"', 'title=" x "', 'title="&zz; &#38; &#x26; &copy;"', 'href=" https://zz.example "', 'href="  //zz.example/a b "', "title=''", 'data-a="1"   data-b',
]));
add_filter('pre_kses', $pre = static fn ($content, $allowed, $protocols) => str_replace('zz-swap', 'swapped', (string) $content) . '|' . (is_string($allowed) ? $allowed : 'array') . '|' . count((array) $protocols), 10, 3);
$say('the pre_kses hook', [wp_kses_hook('zz-swap here', 'post', ['http']), wp_kses_hook('x', ['b' => []], [])]);
remove_filter('pre_kses', $pre, 10);
$say('slashes off quotes', array_map(static fn ($text) => wp_kses_stripslashes($text), ['say \\"hi\\"', "it\\'s", 'back\\\\slash', 'none', '\\"\\"']));
$say('the version', [wp_kses_version()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
