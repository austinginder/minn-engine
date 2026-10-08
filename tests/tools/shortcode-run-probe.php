<?php
/**
 * How shortcodes run (probe shortcode-run), past the basics the api probe
 * pins: pre_do_shortcode_tag and do_shortcode_tag and what they are handed,
 * do_shortcode_tag called directly, the content a callback receives for
 * each closing form, shortcode_parse_atts on awkward text, shortcodes in
 * and around HTML (attribute values, tag names, comments, CDATA) with and
 * without ignore_html, brackets that are not shortcodes, output that holds
 * shortcodes, strip_shortcodes and its tag-names filter, has_shortcode
 * inside another shortcode, apply_shortcodes, the two patterns
 * themselves, the HTML helpers the pass stands on (wp_html_split,
 * wp_kses_attr_parse, wp_kses_one_attr), and wptexturize leaving
 * shortcodes alone. Shortcodes registered here are removed. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$shown = static function ($atts, $content, $tag): string {
    return '{' . $tag . ':' . gettype($atts) . '=' . json_encode($atts) . ':' . gettype($content) . '=' . json_encode($content) . '}';
};
add_shortcode('zz_t', $shown);
add_shortcode('zz-dash', $shown);
add_shortcode('zz_echo', static fn () => '[zz_t]');
add_shortcode('zz_link', static fn ($atts) => 'https://example.com/' . ($atts['p'] ?? 'x'));
add_shortcode('zz_quote', static fn () => 'say "hi" & <b>bye</b>');
add_shortcode('zz_wrap', static fn ($atts, $content) => '<span>' . do_shortcode((string) $content) . '</span>');

// The filters around one shortcode.
$heard = [];
$pre = static function ($return, $tag, $attr, $m) use (&$heard) {
    $heard[] = ['pre', $return, $tag, $attr, $m];
    return $tag === 'zz-dash' ? 'answered early' : $return;
};
$after = static function ($output, $tag, $attr, $m) use (&$heard) {
    $heard[] = ['after', $output, $tag, $attr, $m];
    return $tag === 'zz_t' ? "({$output})" : $output;
};
add_filter('pre_do_shortcode_tag', $pre, 10, 4);
add_filter('do_shortcode_tag', $after, 10, 4);
$say('do_shortcode with the filters', do_shortcode('A [zz_t x="1"]in[/zz_t] B [zz-dash] C [[zz_t]] D'));
$say('the filters heard', $heard);
remove_filter('pre_do_shortcode_tag', $pre, 10);
remove_filter('do_shortcode_tag', $after, 10);

$say('do_shortcode_tag direct', do_shortcode_tag(['[zz_t a=1]', '', 'zz_t', ' a=1', '', '', '']));
$say('do_shortcode_tag direct, escaped', do_shortcode_tag(['[[zz_t]]', '[', 'zz_t', '', '', '', ']']));
$say('do_shortcode_tag direct, enclosing', do_shortcode_tag(['[zz_t]x[/zz_t]', '', 'zz_t', '', '', 'x', '']));

// The content each closing form hands the callback.
foreach (['[zz_t]', '[zz_t/]', '[zz_t /]', '[zz_t][/zz_t]', '[zz_t]x[/zz_t]', '[zz_t a="1"/]', '[zz_t]a[zz_t]b[/zz_t]', '[zz_t] [zz_t]x[/zz_t]'] as $text) {
    $say("content of {$text}", do_shortcode($text));
}

// Attribute text.
foreach ([
    'plain' => 'a="1" b=\'2\' c=3',
    'no space between' => 'a="1"b="2"',
    'upper case names' => 'A="1" Bb=2',
    'empty value' => 'a="" b=\'\'',
    'escaped quote' => 'a="x\\"y" b="c\\\\d"',
    'smart quotes' => 'a=&#8220;1&#8221; b=&#8216;2&#8217;',
    'no-break space' => "a=\"1\"\u{00A0}b=\"2\"",
    'zero-width space' => "a=\"1\"\u{200B}b=\"2\"",
    'html value' => 'a="<b>x</b>" <b>pos</b>',
    'unclosed html' => 'a="<b x" "<i" ok',
    'only spaces' => '   ',
    'one word' => 'flag',
    'dashes and digits' => 'data-x="1" 2col=3',
    'equals in value' => 'a="b=c" d=e=f',
    'slash at the end' => 'a="1" /',
] as $label => $text) {
    $say("shortcode_parse_atts {$label}", shortcode_parse_atts($text));
}
$say('get_shortcode_atts_regex', get_shortcode_atts_regex());

// Shortcodes and HTML.
foreach ([
    'in an attribute value' => '<a href="[zz_link p=go]">x</a>',
    'in a title' => '<a title="[zz_t]">x</a>',
    'single-quoted attribute' => "<a title='[zz_t]'>x</a>",
    'output with quotes in an attribute' => '<a title="[zz_quote]">x</a>',
    'as the tag' => '<[zz_t]>',
    'as an attribute name' => '<div [zz_t]>x</div>',
    'in a comment' => '<!-- [zz_t] --> [zz_t]',
    'in cdata' => '<![CDATA[ [zz_t] ]]>',
    'enclosing html' => '[zz_t]<b>bold</b>[/zz_t]',
    'across a tag' => '<p>[zz_t]</p><p>[/zz_t]</p>',
    'unregistered in an attribute' => '<a title="[zz_nope]">x</a>',
    'escaped in an attribute' => '<a title="[[zz_t]]">x</a>',
    'a bracket in an attribute' => '<a title="a [ b">[zz_t]</a>',
] as $label => $html) {
    $say("do_shortcode {$label}", do_shortcode($html));
    $say("do_shortcode {$label}, ignoring html", do_shortcode($html, true));
}

// Brackets that are not shortcodes, and output that holds one.
foreach (['[zz_nope]', '[ zz_t ]', '[zz_t', 'zz_t]', '[/zz_t]', '[zz_tt]', '[zz-dashed]', '[zz_echo]', '[zz_wrap][zz_t][/zz_wrap]', '[[zz_t]x[/zz_t]]', '&#91;zz_t&#93;'] as $text) {
    $say("do_shortcode {$text}", do_shortcode($text));
}
$say('do_shortcode with no shortcodes registered under that name', do_shortcode('text without brackets'));

// Stripping.
$say('strip_shortcodes', strip_shortcodes('a [zz_t]in[/zz_t] b [zz_t/] c [[zz_t]] d <a title="[zz_t]">e</a> [zz_nope] f'));
$keep = static fn ($tags) => array_values(array_diff($tags, ['zz_t']));
add_filter('strip_shortcodes_tagnames', $keep);
$say('strip_shortcodes keeping one tag', strip_shortcodes('a [zz_t]in[/zz_t] b [zz-dash] c'));
remove_filter('strip_shortcodes_tagnames', $keep);

$say('has_shortcode nested', [has_shortcode('[zz_wrap][zz_t][/zz_wrap]', 'zz_t'), has_shortcode('[zz_wrap][zz_t][/zz_wrap]', 'zz_wrap'), has_shortcode('[[zz_t]]', 'zz_t'), has_shortcode('[zz_t]', 'zz_nope')]);
$say('apply_shortcodes', apply_shortcodes('[zz_t a=1]'));
$say('get_shortcode_regex two tags', get_shortcode_regex(['zz_a', 'zz-b']));

// Brackets already written as entities, and the image context inside a shortcode.
$say('do_shortcode entity brackets beside a shortcode', do_shortcode('[zz_t] &#91;x&#93; &#091;y&#093;'));
$say('do_shortcode a stray bracket in a tag', do_shortcode('<a title="a [ b"> [zz_t] <b class="]">'));
add_shortcode('zz_context', static fn () => apply_filters('wp_get_attachment_image_context', 'wp_get_attachment_image'));
$say('the image context inside a shortcode', [do_shortcode('[zz_context]'), apply_filters('wp_get_attachment_image_context', 'wp_get_attachment_image')]);
remove_shortcode('zz_context');

// The HTML helpers the shortcode pass stands on.
foreach (['text <b>bold</b> <!-- a <b> comment --> <![CDATA[ x < y ]]> <script>if (a < b) {}</script> end', '<p class="a">x</p>', 'no tags', '<!-- unclosed', '<a href="x" title=\'y\'>z</a>'] as $n => $html) {
    $say("wp_html_split {$n}", wp_html_split($html));
}
foreach (['<a href="x" title="y">', '<a  href = "x"  checked >', "<img src='x' alt=a/>", '<div [zz_t]>', '<br/>', '<a title="[zz_t]">', '</p>', '<a href="x" <b>', '<![CDATA[x]]>', '<!-- x -->', '<a title="un closed>'] as $n => $element) {
    $say("wp_kses_attr_parse {$n}", wp_kses_attr_parse($element));
}
foreach ([['title="plain"', 'a'], ['title="say "hi" & <b>bye</b>"', 'a'], ['href="javascript:alert(1)"', 'a'], ['href="https://x.example/?a=1&b=2"', 'a'], ['onclick="x()"', 'a'], ['style="color: red; behaviour: url(x)"', 'p'], ['data-x="1"', 'div'], ['checked', 'input'], ['title=\'single\'', 'a'], ['title=bare', 'a'], ['src="x.png"', 'nope'], ['  title="spaced"  ', 'a']] as $n => [$attr, $element]) {
    $say("wp_kses_one_attr {$n}", wp_kses_one_attr($attr, $element));
}

// wptexturize leaves registered shortcodes as written, and the text inside the ones no_texturize_shortcodes names.
add_shortcode('zz_code', $shown);
$quiet = static fn ($tags) => [...$tags, 'zz_code'];
add_filter('no_texturize_shortcodes', $quiet);
foreach ([
    'attributes' => '[zz_t a="b" c=\'d\'] "quoted" -- text [/zz_t]',
    'unregistered' => '[zz_nope a="b"] "quoted"',
    'escaped' => '[[zz_t a="b"]] "quoted"',
    'quiet' => '[zz_code] "inside" -- x [/zz_code] "after"',
    'html in a shortcode' => '[zz_t a="<b>x</b>"] "q"',
    'nested quiet' => '[zz_code][zz_t a="b"] "in" [/zz_t][/zz_code] "out"',
] as $label => $text) {
    $say("wptexturize {$label}", wptexturize($text, true));
}
remove_filter('no_texturize_shortcodes', $quiet);
wptexturize('', true);
remove_shortcode('zz_code');

foreach (['zz_t', 'zz-dash', 'zz_echo', 'zz_link', 'zz_quote', 'zz_wrap'] as $tag) {
    remove_shortcode($tag);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
