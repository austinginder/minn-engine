<?php

declare(strict_types=1);

use Minn\Support\Kses;

/**
 * Every expectation here was captured from the reference with
 * wp_kses($html, 'data') and wp_kses_post($html); the two agreed on all of
 * them. The URL cases are the ones an encoding could hide a scheme behind.
 */
$comment = static fn (string $html): string => Kses::comment($html);

$hrefs = [
    '&#106;avascript:alert(1)' => 'alert(1)',
    'javascript&colon;alert(1)' => 'alert(1)',
    'javascript:alert(1)' => 'alert(1)',
    'jav&#x09;ascript:alert(1)' => 'alert(1)',
    '&#x6A;avascript:alert(1)' => 'alert(1)',
    'JaVaScRiPt:alert(1)' => 'alert(1)',
    'java&#10;script:alert(1)' => 'alert(1)',
    '&#0000106avascript:alert(1)' => 'alert(1)',
    '&nbsp;javascript:alert(1)' => 'alert(1)',
    '&#0;javascript:alert(1)' => 'alert(1)',
    '&amp;#106;avascript:alert(1)' => 'alert(1)',
    '&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;:alert(1)' => 'alert(1)',
    '&#x6A;avascript&#x3A;alert(1)' => 'alert(1)',
    'ja&#x76;ascript:1' => '1',
    '&#106&#97vascript:1' => '1',
    'j%61vascript:x' => 'x',
    'javascript :x' => 'x',
    'javascript:javascript:alert(1)' => 'alert(1)',
    'javascript:alert(1)//http://' => '//',
    'vbscript:x' => 'x',
    'data:text/html,x' => 'text/html,x',
    'http:javascript:alert(1)' => 'http:javascript:alert(1)',
    'http://x.test/?a=1&amp;b=2' => 'http://x.test/?a=1&amp;b=2',
    'http://x.test/?a=1&b=2' => 'http://x.test/?a=1&amp;b=2',
    'http://x.test/?q=&amp;amp;b' => 'http://x.test/?q=&amp;amp;b',
    'http://x.test/&#38;a=&#x26;b' => 'http://x.test/&amp;a=&amp;b',
    '  https://x.test/' => 'https://x.test/',
    '/rel&#39;a' => '/rel&apos;a',
    "it's" => 'it&apos;s',
    'http://x.test/&#8220;q&#8221;' => 'http://x.test/“q”',
    'http://x.test/&#x00;a' => 'http://x.test/&amp;#x00;a',
    'http://x.test/&#x110000;' => 'http://x.test/&amp;#x110000;',
    'mailto:a@b.c?subject=x&amp;body=y' => 'mailto:a@b.c?subject=x&amp;body=y',
    'ftp://x/' => 'ftp://x/',
    'tel:1' => 'tel:1',
    'ws://x' => '//x',
    'http://x:80/' => 'http://x:80/',
    '/path:x' => 'x',
    '//host/x:y' => 'y',
    '?q=a:b' => 'b',
    '#frag:1' => '1',
    'www.x.com:80/x' => '80/x',
    ':x' => 'x',
    'a:b:c' => 'c',
    'a b:c' => 'c',
    '&#65;&#66;' => 'AB',
    'x&nbsp;y' => "x\u{A0}y",
];
$titles = [
    '&#106;avascript:alert(1)' => 'javascript:alert(1)',
    '&amp;#106;x' => '&amp;#106;x',
    '&#106&#97vascript:1' => '&amp;#106&amp;#97vascript:1',
    'a&lt;b&gt;c &amp;amp; d' => 'a&lt;b&gt;c &amp;amp; d',
    '&#8220;q&#8221; &euro; &unknown; &#xZZ;' => '“q” € &amp;unknown; &amp;#xZZ;',
    '&#38; &#60; &#62; &#34; &#1;' => '&amp; &lt; &gt; &quot; &amp;#1;',
    'a&quot;b' => 'a&quot;b',
    'q&#0;r' => 'q&amp;#0;r',
    '&#xD800;' => '&amp;#xD800;',
];
$texts = [
    'a & b &amp; c &unknown; &#8220;q&#8221; &lt;b&gt; it\'s "q" &#39; &#0; &nbsp; &#x110000;'
        => 'a &amp; b &amp; c &amp;unknown; &#8220;q&#8221; &lt;b&gt; it\'s "q" &#039; &amp;#0; &nbsp; &amp;#x110000;',
    '&#38; &#x26; &#60; &#34; &#1;' => '&#038; &#x26; &#060; &#034; &amp;#1;',
    'a < b' => 'a &lt; b',
    'a<b' => 'a&lt;b',
    '1 <2' => '1 &lt;2',
    '<b' => '&lt;b',
    'x >y' => 'x &gt;y',
    'a</b>c' => 'a</b>c',
    'a <!-- c --> b' => 'a <!-- c --> b',
    '<!-- wp:paragraph {"a":1} --> x & y <!-- /wp:paragraph -->' => '<!-- wp:paragraph {"a":1} --> x &amp; y <!-- /wp:paragraph -->',
];

$cases = [];
foreach ($hrefs as $in => $out) {
    $cases["href {$in}"] = static function () use ($comment, $in, $out): bool|string {
        $got = $comment('<a href="' . $in . '">x</a>');
        return $got === '<a href="' . $out . '">x</a>' ? true : $got;
    };
}
foreach ($titles as $in => $out) {
    $cases["title {$in}"] = static function () use ($comment, $in, $out): bool|string {
        $got = $comment('<a title="' . $in . '">x</a>');
        return $got === '<a title="' . $out . '">x</a>' ? true : $got;
    };
}
foreach ($texts as $in => $out) {
    $cases["text {$in}"] = static function () use ($comment, $in, $out): bool|string {
        $got = $comment($in);
        return $got === $out ? true : $got;
    };
}
$cases['single-quoted attribute values are re-quoted'] = static function () use ($comment): bool|string {
    $got = $comment("<a href='http://x.test/?a=\"q\"' title='x'>t</a>");
    return $got === '<a href="http://x.test/?a=&quot;q&quot;" title="x">t</a>' ? true : $got;
};
$cases['tags outside the list go, their text stays, event attributes go'] = static function () use ($comment): bool|string {
    $got = $comment('<a href="&#106;avascript:alert(1)">x</a><script>bad()</script><a onclick="x" href="http://ok/">y</a>');
    return $got === '<a href="alert(1)">x</a>bad()<a href="http://ok/">y</a>' ? true : $got;
};
$cases['a data attribute is decoded like any other under the post list'] = static function (): bool|string {
    $got = Kses::post('<a href="http://x.test/" data-x="&#106;avascript:1" aria-label="a&b">t</a>');
    return $got === '<a href="http://x.test/" data-x="javascript:1" aria-label="a&amp;b">t</a>' ? true : $got;
};
$cases['srcset candidates are judged decoded'] = static function (): bool|string {
    $got = Kses::post('<img src="http://x/a.png" srcset="&#106;avascript:1 1x">');
    return $got === '<img src="http://x/a.png">' ? true : $got;
};
$cases['a style value is judged decoded'] = static function (): bool|string {
    $got = Kses::post('<p style="color:red;background:url(&#106;avascript:1)">t</p>');
    return $got === '<p style="color:red">t</p>' ? true : $got;
};
return $cases;
