<?php
/**
 * wpautop on content it does not usually see (probe autop): unclosed tags
 * and comments, blank lines inside attributes, pre, script, style, svg and
 * math, media and form tags, br sequences, block openers and closers, and
 * real posts, each with and without line breaks. The inputs come from a
 * seeded generator, so both stacks see the same ones. Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$fragments = ['<pre>', '</pre>', '<pre class="x">', '<script>', '</script>', '<style>', '</style>', '<textarea>', '</textarea>', '<!--', '-->', '--!>', '<!-- c -->', '<!-- wp:paragraph -->', '<![CDATA[', ']]>',
    '<p>', '</p>', '<br>', '<br/>', '<br />', '<BR>', '<div>', '</div>', '<blockquote>', '</blockquote>', '<BLOCKQUOTE>', '<ul>', '</ul>', '<li>', '</li>', '<h2>', '</h2>', '<table>', '</table>', '<tr>', '<td>', '</td>',
    '<select>', '<option>', '</option>', '<hr>', '<hr />', '<figure>', '<figcaption>', '</figcaption>', '<object>', '</object>', '<param name="a">', '<embed src="x">', '<svg>', '</svg>', '<math>', '</math>', '<source src="a">', '<audio>', '</audio>',
    '<img src="i.png">', '<a href="#">', '</a>', '<a title="x' . "\n\n" . 'y">', '<span>', '</span>', '[caption]', '[/caption]', '&nbsp;', '<', '>', '"', 'text', 'Hello world.', "\t", '<p' . "\n\n" . '>', '<div' . "\n" . 'class="z">', '<address>', '</form>', '<path/>', '<thead>'];
$separators = ['', ' ', "\n", "\n\n", "\n\n\n", "\r\n", "\r\n\r\n", "\n \n"];
mt_srand(7131);
$mixes = [];
for ($i = 0; $i < 300; $i++) {
    $text = '';
    for ($j = mt_rand(1, 8); $j > 0; $j--) {
        $text .= $separators[mt_rand(0, count($separators) - 1)] . $fragments[mt_rand(0, count($fragments) - 1)] . (mt_rand(0, 2) === 0 ? 'word' : '');
    }
    $mixes[] = $text;
}
$both = static fn (array $inputs): array => array_map(static fn (string $text): array => [$text, wpautop($text), wpautop($text, false)], $inputs);

$say('single fragments', $both(array_map(static fn (string $fragment): string => "x {$fragment} y\n\nw", $fragments)));
$say('fragments on their own lines', $both(array_map(static fn (string $fragment): string => "a\n{$fragment}\nb", $fragments)));
$say('seeded mixes', $both($mixes));
$say('posts', $both([
    "First paragraph.\n\nSecond paragraph with a\nline break.\n\n<ul>\n<li>One</li>\n<li>Two</li>\n</ul>\n\nAfter list.",
    "<h2>Heading</h2>\nText under heading.\n\n<blockquote>Quoted\n\nTwo paras</blockquote>\n\nEnd.",
    "[caption id=\"attachment_1\" width=\"300\"]<img src=\"a.jpg\" alt=\"\" width=\"300\" height=\"200\" /> A caption[/caption]\n\nText after.",
    "<pre>code line 1\n\ncode line 3\n  indented</pre>\n\nAfter pre.\n\n<pre class=\"x\">a\nb</pre>",
    "<script>var a = 1;\n\nvar b = 2;</script>\nText\n\n<style>p {\n\ncolor: red; }</style>",
    "Line one<br>\nLine two<br />\n\nPara two<br><br>Para three",
    "<select name=\"s\">\n<option value=\"1\">One</option>\n\n<option>Two</option>\n</select>\n\nText",
    "<object width=\"400\">\n<param name=\"movie\" value=\"x\">\n<embed src=\"x\" width=\"400\">\n</embed>\n</object>\n\nAfter",
    "Text with <a href=\"x\">link\n\nacross</a> paragraphs.",
    "<div class=\"wrap\">\nInside div\n\nSecond inside\n</div>\nOutside",
    "<svg viewBox=\"0 0 10 10\">\n<path d=\"M0 0\"/>\n\n<path d=\"M1 1\"/>\n</svg>\n\nText",
    "<!-- a\n\ncomment -->\n\nText",
    "First line\r\nSecond line\r\n\r\nThird",
    "   \n\n  ",
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
