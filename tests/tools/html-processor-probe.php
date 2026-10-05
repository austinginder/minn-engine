<?php
/**
 * WP_HTML_Processor as plugins and core blocks drive it: every token of a
 * corpus of fragments and documents (type, name, closer, namespace, depth,
 * breadcrumbs, text, whether it expects a closer), where the parser stops
 * and why, normalize() and serialize(), queries by breadcrumbs, bookmarks,
 * edits, and the helper classes. Same protocol as api-probe.php; run on
 * both stacks (tests/tools/run-reference-probe.php, run-api-probe.php).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

/** Every token of one parse, compactly: [type, name, closer, namespace, depth, breadcrumbs, text, expects closer]. */
$walk = static function (?WP_HTML_Processor $p, int $limit = 400): array {
    if ($p === null) {
        return ['null'];
    }
    $tokens = [];
    while ($limit-- > 0 && $p->next_token()) {
        $type = $p->get_token_type();
        $tokens[] = [
            $type,
            $p->get_token_name(),
            $p->is_tag_closer() ? 1 : 0,
            $p->get_namespace(),
            $p->get_current_depth(),
            implode('>', (array) $p->get_breadcrumbs()),
            $type === '#tag' ? null : $p->get_modifiable_text(),
            $p->expects_closer(),
        ];
    }
    $error = $p->get_last_error();
    $unsupported = $p->get_unsupported_exception();
    return [$tokens, $error, $unsupported ? $unsupported->getMessage() : null, $p->paused_at_incomplete_token()];
};

$fragments = [
    // Text, characters, entities.
    'plain text', 'a &amp; b &lt;c&gt; &copy; &notanentity; &#x41;&#66;', "line\nbreak\r\nand\rcarriage", "nul\0byte", "\0", '', '   ', "<p>\0</p>", "a\0<b>\0b</b>",
    // Paragraphs and implied end tags.
    '<p>one<p>two', '<p>one<div>two</div>', '<p>a</p></p>', '</p>', '<p><h1>head</h1></p>', '<p><table><tr><td>x</td></tr></table>', '<p><ul><li>a</ul>', '<p>para<hr>after',
    '<p><p><p>', '<p>a<address>b</address>', '<p>a<pre>b</pre>', "<pre>\nleading newline</pre>", "<pre>\n\ntwo</pre>", "<textarea>\nkeep</textarea>", "<listing>\nx</listing>",
    // Lists.
    '<ul><li>one<li>two</ul>', '<ol><li>a<ol><li>b</ol><li>c</ol>', '<li>orphan', '<ul><li>a</li></ul></li>', '<dl><dt>t<dd>d<dt>t2</dl>', '<dd>x<dt>y',
    // Headings.
    '<h1>a<h2>b</h2></h1>', '<h1>a</h2>b', '<h3><div>x</div></h3>',
    // Formatting elements.
    '<b>bold<i>both</b>italic</i>', '<b><p>x</b>y</p>', '<a href="1">one<a href="2">two</a>', '<b>1<b>2<b>3<b>4</b></b></b></b>', '<p><b>x<p>y', '<em>a<strong>b</em>c</strong>',
    '<b><i><u><s>deep</s></u></i></b>', '<nobr>a<nobr>b', '<b>unclosed', '</b>', '<u>a</u></u>', '<font color="red">f<div>x</div>y</font>', '<a><div><a>nested</a></div></a>',
    '<b>a<table><tr><td>c</td></tr></table>d</b>', '<i>1<b>2<i>3</i>4</b>5</i>', '<code><pre>x</pre></code>', '<span><b>x</span>y</b>',
    // Void elements and self-closing flags.
    '<br><hr><img src="x"><input type="text"><wbr><meta charset="utf-8"><link rel="x">', '<br/>', '</br>', '<img/>', '<div/>text</div>', '<span/>x', '<embed><area><col><source><track><param><keygen>',
    '<image src="x">', '<isindex>', '<br a="1" b>',
    // Divs, sections, buttons, forms.
    '<div><div><div>deep</div></div></div>', '<div>a</span>b</div>', '<section><article><aside>x</aside></article></section>', '<button>a<button>b</button>', '<button><div>x</div></button>',
    '<form><input><form>nested</form></form>', '<form>a</form></form>', '<fieldset><legend>l</legend>x</fieldset>', '<label>a<input></label>', '<details><summary>s</summary>d</details>',
    '<dialog open>x</dialog>', '<main><nav><header><footer>x', '<figure><img><figcaption>c</figcaption></figure>', '<center>c</center>', '<menu><li>x</menu>',
    // Raw text and RCDATA.
    '<script>if (a < b) { x(); }</script>', '<style>p > a { color: red }</style>', '<title>a <b> c</title>', '<textarea>a <b> &amp; c</textarea>', '<xmp><b>x</b></xmp>', '<iframe><p>x</p></iframe>',
    '<noembed>x</noembed>', '<noframes>x</noframes>', '<noscript><p>x</p></noscript>', '<script>unterminated', '<style>', '<plaintext><b>x</b>', '<template><p>x</p></template>', '<template><td>x</td></template>',
    // Tables.
    '<table><tr><td>a</td></tr></table>', '<table><td>a</table>', '<table><tbody><tr><th>h<td>d</table>', '<table><caption>c</caption><colgroup><col></colgroup><thead><tr><td>x</table>',
    '<table>text<tr><td>x</table>', '<table><tr>a<td>b</td></tr></table>', '<table><table>', '<table><tr><td><table><tr><td>in</td></tr></table></td></tr></table>', '<td>stray</td>', '<tr><td>x', '<table><form><tr><td>x</table>',
    '<table><input type="hidden"><tr><td>x</table>', '<table><input type="text"></table>', '<table><style>x</style><script>y</script></table>', '<table><col><td>x</table>', '<table><tr><td>a<td>b<tr><td>c</table>', '<caption>x</caption>',
    // Select.
    '<select><option>a<option>b</select>', '<select><optgroup label="g"><option>a</optgroup></select>', '<select><div>x</div></select>', '<select><select>', '<select><input>', '<option>alone', '<table><tr><td><select><option>x</td></tr></table>',
    '<select><option>a</option><hr><option>b</select>', '<select><b>x</b></select>',
    // Head-only elements in a body fragment.
    '<base href="/"><basefont><bgsound>', '<head><title>t</title></head>', '<body class="x">b</body>', '<html lang="en">x</html>', '<frameset><frame></frameset>', '<frame>',
    // Foreign content.
    '<svg><circle r="5"/></svg>', '<svg viewBox="0 0 10 10"><path d="M0"/><title>t</title></svg>', '<svg><foreignObject><p>html</p></foreignObject></svg>', '<svg><desc><b>x</b></desc></svg>', '<math><mi>x</mi><mo>+</mo></math>',
    '<math><annotation-xml encoding="text/html"><p>x</p></annotation-xml></math>', '<svg><![CDATA[cdata text]]></svg>', '<![CDATA[in html]]>', '<svg><b>breakout</b></svg>', '<svg><p>breakout</p>after', '<svg><clippath><lineargradient/></clippath></svg>',
    '<svg><style>a{}</style><script>x</script></svg>', '<math><mtext><b>x</b></mtext></math>', '<svg><font color="red">x</font></svg>', '<svg><font>x</font></svg>', '<p><svg><circle/></svg>after</p>', '<svg/>', '<math/>', '<svg><svg><g/></svg></svg>',
    // Comments, doctypes, odd syntax.
    '<!-- comment -->', '<!---->', '<!-->', '<!--->', '<!-- a -- b -->', '<!DOCTYPE html>', '<!doctype html><p>x', '<?php echo 1; ?>', '<?xml version="1.0"?>', '</ funky comment>', '</>', '<!x>', '<!-- unclosed', '<div', '<div class="x', '<',
    '< div>', 'a < b', '<div a=1 b="2" c=\'3\' d>x</div>', '<div class="a" class="b">x</div>', '<DIV CLASS="Up">x</DIV>', '<my-element foo="bar">custom</my-element>', '<x-y><z-w>x</z-w></x-y>',
    // Real-world block markup.
    '<!-- wp:paragraph --><p>Hello <strong>world</strong></p><!-- /wp:paragraph -->',
    '<!-- wp:image {"id":1} --><figure class="wp-block-image"><img src="a.png" alt=""/><figcaption class="wp-element-caption">Cap</figcaption></figure><!-- /wp:image -->',
    '<div class="wp-block-group"><div class="wp-block-group__inner-container"><h2>T</h2><p>x</p></div></div>',
    '<nav class="wp-block-navigation"><ul class="wp-block-navigation__container"><li class="wp-block-navigation-item"><a href="/">Home</a></li><li><a>About</a><ul><li><a>Team</a></li></ul></li></ul></nav>',
    '<table class="has-fixed-layout"><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody><tfoot><tr><td>f</td></tr></tfoot></table>',
    '<details class="wp-block-details"><summary>Q</summary><p>A</p></details>', '<div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Go</a></div></div>',
    '<video controls src="v.mp4"><track kind="captions"></video>', '<audio><source src="a.mp3"></audio>', '<picture><source srcset="a.webp"><img src="a.jpg"></picture>', '<object><param name="x"><embed></object>',
    '<p>Line one<br>Line two</p><p>&nbsp;</p>', '<blockquote class="wp-block-quote"><p>q</p><cite>c</cite></blockquote>', '<pre class="wp-block-code"><code>&lt;?php echo 1;</code></pre>',
    '<ruby>漢<rt>kan</rt><rp>(</rp></ruby>', '<rb>a<rtc>b<rt>c', '<marquee>m</marquee>', '<applet>a</applet>', '<object>o<p>x</object>', '<math><mglyph/><malignmark/></math>',
    // Deep nesting and limits.
    str_repeat('<div>', 60) . 'deep' . str_repeat('</div>', 60), str_repeat('<b>', 20) . 'x', str_repeat('<p>', 30), str_repeat('<a>', 5) . 'x', str_repeat('<table>', 5),
];
$fragmentResults = [];
foreach ($fragments as $index => $html) {
    $fragmentResults[] = [$html, $walk(WP_HTML_Processor::create_fragment($html))];
}
$say('fragments', $fragmentResults);

$contexts = [['<td>x</td><td>y', '<tr>'], ['<li>a<li>b', '<ul>'], ['<p>x', '<div>'], ['<tr><td>x', '<tbody>'], ['<option>a', '<select>'], ['<b>x', '<p class="x">'], ['x', '<template>'], ['<td>x', '<table>'], ['<p>x', '<svg>'], ['x', '<title>'], ['x', '<textarea>'], ['x', '<script>'], ['x', '<body>'], ['x', '<html>'], ['x', 'not a tag'], ['x', '<div><p>'], ['x', '</div>'], ['x', '<br>']];
$contextResults = [];
foreach ($contexts as [$html, $context]) {
    $contextResults[] = [$html, $context, $walk(WP_HTML_Processor::create_fragment($html, $context))];
}
$say('fragment contexts', $contextResults);
$say('fragment encodings', [WP_HTML_Processor::create_fragment('x', '<body>', 'iso-8859-1') === null, WP_HTML_Processor::create_fragment('x', '<body>', 'utf-8') !== null]);

$documents = [
    '<!DOCTYPE html><html><head><title>T</title></head><body><p>x</p></body></html>', '<p>no html', 'text only', '', '<!DOCTYPE html>', '<html><body></body></html>after', '<head><meta charset="utf-8"><link rel="x"><style>s</style><script>j</script></head><p>b',
    '<title>t</title><p>b', '<!-- before --><!DOCTYPE html><html>', '<html><!-- in html --><head></head><!-- between --><body></body></html><!-- after -->', '<body onload="x"><p>y</body>', '<frameset><frame></frameset>',
    '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><p>q', '<!DOCTYPE html SYSTEM "about:legacy-compat"><p>x', '<html><head><noscript><link rel="x"></noscript></head>', "  \n <html> x", '<head></head> <body>', '<p>x</p></body><p>y',
    '<html><html lang="x"><body><body class="b">', '<table><tr><td>doc</td></tr></table>', '<svg><title>x</title></svg>', '<template><p>x</p></template>', '<head><template><p>x</p></template></head>',
];
$documentResults = [];
foreach ($documents as $html) {
    $documentResults[] = [$html, $walk(WP_HTML_Processor::create_full_parser($html))];
}
$say('documents', $documentResults);

$normalize = ['<p>one<p>two', '<b>bold<i>both</b>italic</i>', '<table><td>a</table>', '<div class=x id=y>t</div>', "<p>a &amp; b &lt; &quot; &#39; &nbsp; ü</p>", '<img src="a.png" alt>', '<!-- c --><p>x', '<br/><img/>',
    '<script>a < b</script><style>p>a{}</style>', '<textarea>&lt;x&gt;</textarea>', '<svg><circle r="1"/><path d="M0 0"></path></svg>', '<li>a<li>b', '<p title="a&quot;b">x</p>', "<pre>\nx</pre>", '<DIV CLASS="Up">x</DIV>',
    '<select><option>a<option>b</select>', '<p><svg><foreignObject><b>x</b></foreignObject></svg></p>', '<a href="x"><a href="y">z</a></a>', '<div a="1" a="2">x</div>', '<![CDATA[x]]><?pi x?></funky>', '', 'just text', "nul\0", '<template><td>x</td></template>',
    '<math><mi>x</mi></math>', '<button><button>x', '<h1><h2>x', '<b><p>x</b>y'];
$say('normalize', array_map(static fn ($html) => [$html, WP_HTML_Processor::normalize($html)], $normalize));

// A second corpus at the edges: adoption agency, tables, templates, select, foreign content, forms,
// frameset and the content around the body, each walked and normalized.
$compact = static function (?WP_HTML_Processor $p): array {
    if ($p === null) {
        return ['null'];
    }
    $t = [];
    $guard = 0;
    while ($guard++ < 300 && $p->next_token()) {
        $t[] = ($p->is_tag_closer() ? '-' : '+') . $p->get_token_name() . ($p->get_token_type() === '#tag' ? '' : '=' . json_encode($p->get_modifiable_text(), JSON_UNESCAPED_UNICODE)) . '@' . $p->get_current_depth() . ':' . $p->get_namespace();
    }
    return [implode(' ', $t), $p->get_last_error(), $p->get_unsupported_exception()?->getMessage(), $p->paused_at_incomplete_token()];
};
$edgeFragments = ['<div><form><p>a</div></form>', '<form><div>a</form>b', '<a>x<p>y</a>z', '<a><b>x</a>y', '<b><a>x</b>y', '<i><b></i>x', '<font size=2><p>x</font>', '<p><b><i>x</i></b></p>', '<b>x</b><b>y</b>', '<a>1</a><a>2</a>', '<nobr><b>x</nobr>', '<table><tr><td><b>x</td><td>y</td></tr></table>', '<table><caption><b>x</caption></table>',
  '<table><tbody></tbody><tr><td>x</table>', '<table><thead><tr><th>h</thead><tbody><tr><td>d</table>', '<table><tr><td>a</table>b', '<table><colgroup><col span=2></colgroup><col><td>x</table>', '<table><colgroup>x</colgroup></table>', '<table><template><tr><td>x</td></tr></template></table>', '<table><tr><td>x</td></tr></tbody></table>', '<table></div></table>', '<table><tr></td></tr></table>', '<table><caption>c<table><tr><td>x</table></caption></table>',
  '<table><td><table><td>x</table></table>', '<table>  </table>', '<table><!--c--><tr><td>x</table>', '<table><tr><td>a</td><!--c--></tr></table>', '<td><table><td>x</table>', '<template><caption>c</caption></template>', '<template><col></template>', '<template><tr><td>x</tr></template>', '<template><template><p>x</template></template>', '<template></template>after', '<template><td></template><td>x',
  '<select><optgroup><option>a<optgroup><option>b</select>', '<select><option>a<select>b', '<select><textarea>t</textarea>', '<select><keygen>', '<select><option><b>x</option>y</select>', '<select><svg><circle/></svg></select>', '<select><table><tr><td>x</table></select>', '<option>a<option>b', '<optgroup>a<option>b', '<select><option>a</option></select><option>b',
  '<svg><title><b>x</b></title><p>y</svg>', '<svg><foreignObject><svg><p>x</p></svg></foreignObject></svg>', '<math><mi><svg><circle/></svg></mi></math>', '<math><annotation-xml><svg/></annotation-xml></math>', '<math><annotation-xml encoding="TEXT/HTML"><div>x</div></annotation-xml></math>', '<svg><a><circle/></a></svg>', '<svg></circle></svg>', '<svg><g></svg>x', '<svg><g><p>x', '<svg><style><b>x</b></style></svg>', '<svg><script>a<b</script></svg>',
  '<svg>a<![CDATA[b]]>c</svg>', '<svg>\0x</svg>', '<math><ms>\0</ms></math>', '<svg><desc><svg><g/></svg></desc></svg>', '<svg><tspan><br></tspan></svg>', '<svg><feBlend/><linearGradient/></svg>', '<math><mtext><mglyph/></mtext></math>', '<math><mi><mglyph/>x</mi></math>', '<svg></p></svg>', '<svg></br></svg>',
  '<li><ul><li>x</ul></li>', '<dl><dt>a<div>x<dd>b</dl>', '<li>a<div>b<li>c', '<p><li>x', '<ul><p><li>x', '<h1><p>x</h1>y', '<h2>a<h3>b', '<div><h1>a</div>b', '<button><p>a</button>b', '<p><button>a<p>b</button>', '<address><p>a<li>b</address>', '<rb>x', '<ruby>a<rb>b<rt>c<rtc>d<rp>e</ruby>', '<ruby><rtc><rt>x</ruby>',
  '<marquee><b>x</marquee>y', '<object><b>x</object>y', '<applet>a</applet>', '</div>x', '</table>x', '</li>x', '</h1>', '</select>', '</body>x', '</html>x', '<body><p>x</body>after', '<frameset>', '<head>x', '<html><b>x</html>', '<br>x<br/>', '<hr/><hr>', '<p>a<hr>b', '<img><image>', '<input type=hidden><input>', '<embed><keygen><wbr>', '<menuitem>x', '<search>x</search><hgroup>y</hgroup>', '<dialog><p>x</dialog>',
  '<a href=x><p>y</p></a>', '<b><p>x</p></b>', '<p><b>x</p>y', '<b>x<div>y</div>z</b>', '<u><table><td>x</table></u>', '<em><em><em><em>x</em></em></em></em>', '<b class=x><b class=x><b class=x><b class=x>y', '<s><strike><small><big><tt>x</tt></big></small></strike></s>', '<code>a<pre>b</code>c</pre>',
  "<textarea>a</textarea>", "<pre>\n\nx\n</pre>", "<listing>\r\nx</listing>", "<p>a\r\nb\rc</p>", "<p> \t\n</p>", "&lt;&gt;&amp;", "a&#0;b", "<p>&#x0;</p>", '<title>a<b>c</title>', '<iframe>a<b>c</iframe>', '<noembed>a</noembed>', '<noframes><b></noframes>', '<xmp>a<xmp>b</xmp>', '<p><xmp>x</xmp>', '<noscript><noscript>x', '<style>a</style><style>b',
  '<div id="a" class="b c" data-x=1>x</div>', '<input disabled checked value="v">', '<a href="/x?a=1&b=2">t</a>', '<div title="&amp;&lt;">x</div>', '<img src="a.png" srcset="a.png 1x, b.png 2x" sizes="100vw" alt="">',
];
$say('edge fragments', array_map(static fn ($html) => [$html, $compact(WP_HTML_Processor::create_fragment($html)), WP_HTML_Processor::normalize($html)], $edgeFragments));
$edgeDocuments = ['<!DOCTYPE html><table><p>x', '<table><p>x', '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 3.2 Final//EN"><p><table><td>x</table>', '<!DOCTYPE html><p><table><td>x</table>', '<p><table><td>x</table>', '<!DOCTYPE html><body><frameset>', '<!DOCTYPE html><p>x</p><frameset>', '<html><head></head><body></body><!-- c --></html>', '<body></body><!-- c -->', '<head></head><link rel=x><body>', '<head></head><title>t</title>', '<head></head><meta charset=utf-8>x',
    '<frameset><frame><frame></frameset><noframes>n</noframes>', '<frameset></frameset><!-- c -->', '<frameset>x</frameset>', '<!DOCTYPE html><html><head><base href=/><basefont><bgsound><link><meta><title>t</title><noscript>n</noscript><noframes>f</noframes><style>s</style><script>j</script><template>t</template></head></html>', '<head><noscript>x</noscript></head>', '<head><noscript><p>x</p></noscript>', '<head></body>x', '<html> <head> </head> <body> x </body> </html>', '<!DOCTYPE><p>x', '<!DOCTYPE foo><table><p>x', "\n<!DOCTYPE html>\n<p>x", '<!DOCTYPE html><!DOCTYPE html><p>x', '<html></html><p>x', '</html><p>x', '<html><body></body></html> ', '<html><body></body></html>x<!--c-->'];
$say('edge documents', array_map(static fn ($html) => [$html, $compact(WP_HTML_Processor::create_full_parser($html))], $edgeDocuments));
// The site's own content: every post's markup, walked as a fragment.
$say('post content', array_map(static function ($row) use ($compact) {
    $walked = $compact(WP_HTML_Processor::create_fragment((string) $row->post_content));
    return [(int) $row->ID, md5((string) $walked[0]), substr_count((string) $walked[0], ' ') + 1, $walked[1], $walked[2], md5((string) WP_HTML_Processor::normalize((string) $row->post_content))];
}, $GLOBALS['wpdb']->get_results("SELECT ID, post_content FROM {$GLOBALS['wpdb']->posts} WHERE post_content <> '' AND post_status IN ('publish', 'draft', 'private', 'inherit') ORDER BY ID LIMIT 120")));

// Queries and navigation.
$say('next_tag queries', (static function () {
    $html = '<div><p class="a">1</p><section><p class="a b">2</p><ul><li><p>3</p></li></ul></section><figure><img src="x"><figcaption>c</figcaption></figure></div>';
    $out = [];
    foreach ([['breadcrumbs' => ['P']], ['breadcrumbs' => ['SECTION', 'P']], ['breadcrumbs' => ['DIV', '*', 'P']], ['breadcrumbs' => ['FIGURE', 'IMG']], ['tag_name' => 'p', 'class_name' => 'a'], ['tag_name' => 'P', 'match_offset' => 3], ['breadcrumbs' => ['LI', 'P'], 'tag_closers' => 'visit'], 'img', ['breadcrumbs' => ['*', '*', 'P']]] as $query) {
        $p = WP_HTML_Processor::create_fragment($html);
        $found = [];
        while ($p->next_tag($query)) {
            $found[] = [$p->get_tag(), $p->is_tag_closer(), implode('>', $p->get_breadcrumbs()), $p->get_attribute('class')];
        }
        $out[] = [$query, $found];
    }
    $p = WP_HTML_Processor::create_fragment($html);
    $p->next_tag('li');
    $p->next_tag('p');
    $out[] = ['matches', $p->matches_breadcrumbs(['LI', 'P']), $p->matches_breadcrumbs(['UL', '*', 'P']), $p->matches_breadcrumbs(['SECTION', 'P']), $p->matches_breadcrumbs(['*']), $p->matches_breadcrumbs(['p'])];
    return $out;
})());
$say('step and depth', (static function () {
    $p = WP_HTML_Processor::create_fragment('<div><p>a<b>b</b></p></div>');
    $out = [];
    while ($p->next_token()) {
        $out[] = [$p->get_token_name(), $p->is_tag_closer(), $p->get_current_depth(), $p->expects_closer()];
    }
    return $out;
})());
$say('bookmarks', (static function () {
    $p = WP_HTML_Processor::create_fragment('<div><p>one</p><p>two</p><img></div>');
    $p->next_tag('p');
    $a = $p->set_bookmark('first');
    $p->next_tag('img');
    $b = [$p->has_bookmark('first'), $p->seek('first'), $p->get_tag(), implode('>', $p->get_breadcrumbs()), $p->release_bookmark('first'), $p->has_bookmark('first'), $p->seek('first')];
    $p->next_tag('p');
    return [$a, $b, $p->get_tag(), $p->get_modifiable_text(), implode('>', (array) $p->get_breadcrumbs())];
})());
$say('edits', (static function () {
    $p = WP_HTML_Processor::create_fragment('<div class="a"><p id="x">t</p><img src="i"></div>');
    $p->next_tag('div');
    $p->add_class('b');
    $p->remove_class('a');
    $p->set_attribute('data-x', 'y "q"');
    $p->next_tag('p');
    $p->remove_attribute('id');
    $p->set_attribute('hidden', true);
    $p->next_tag('img');
    $p->set_attribute('alt', '<a>');
    return [$p->get_updated_html(), (string) $p, $p->get_attribute('alt'), $p->get_attribute_names_with_prefix('a'), $p->has_class('b'), iterator_to_array($p->class_list())];
})());
$say('serialize', (static function () {
    $out = [];
    foreach (['<p>one<p>two', '<div><b>x</div>', '<table><td>x', '<ul><li>a<li>b', '<p>&amp;&lt;</p>'] as $html) {
        $p = WP_HTML_Processor::create_fragment($html);
        $tokens = [];
        while ($p->next_token()) {
            $tokens[] = $p->serialize_token();
        }
        $out[] = [$html, WP_HTML_Processor::create_fragment($html)->serialize(), $tokens];
    }
    return $out;
})());
$say('static helpers', [array_map([WP_HTML_Processor::class, 'is_void'], ['BR', 'br', 'img', 'IMG', 'div', 'source', 'template', 'keygen', 'basefont', 'frame', 'image', '']), array_map([WP_HTML_Processor::class, 'is_special'], ['ADDRESS', 'div', 'P', 'b', 'A', 'TABLE', 'svg', 'MATH', 'mi', 'math mi', 'svg foreignObject', 'svg title', 'html', 'SPAN'])]);
$say('constructor', (static function () {
    $out = [];
    foreach ([[null], ['wrong'], [WP_HTML_Processor::CONSTRUCTOR_UNLOCK_CODE]] as $args) {
        // The notice is _doing_it_wrong()'s, which the engine does not raise yet (contracts/runtime.md).
        set_error_handler(static fn () => true);
        try {
            $p = new WP_HTML_Processor('<p>x', ...$args);
            $out[] = [get_class($p), $p->next_tag(), $p->get_tag()];
        } catch (Throwable $e) {
            $out[] = ['throws', get_class($e), $e->getMessage()];
        }
        restore_error_handler();
    }
    return $out;
})());
$say('unsupported details', (static function () {
    $out = [];
    foreach (['<b><p>x</b>y', '<a><div><a>nested</a></div></a>', '<svg><p>breakout</p>after', '<table><table>'] as $html) {
        $p = WP_HTML_Processor::create_fragment($html);
        while ($p->next_token()) {
        }
        $e = $p->get_unsupported_exception();
        $out[] = [$html, $p->get_last_error(), $e ? [get_class($e), $e->getMessage(), $e->token_name, $e->token_at, $e->token, $e->stack_of_open_elements, $e->active_formatting_elements] : null];
    }
    return $out;
})());
$say('decoder', !class_exists('WP_HTML_Decoder') ? 'missing' : [
    WP_HTML_Decoder::decode_text_node('a &amp; b &lt;c&gt; &copy &notit; &#x1F600; &#0; &#xD800; &#128;'),
    WP_HTML_Decoder::decode_attribute('a &amp; b &copy=x &notit; &ampx'),
    WP_HTML_Decoder::attribute_starts_with('&#x6A;avascript:alert(1)', 'javascript:', 'ascii-case-insensitive'),
    WP_HTML_Decoder::attribute_starts_with('JaVaScRiPt:x', 'javascript:', 'case-sensitive'),
    WP_HTML_Decoder::code_point_to_utf8_bytes(0x1F600),
    (static function () { $len = null; $r = WP_HTML_Decoder::read_character_reference('data', '&notin;x', 0, $len); return [$r, $len]; })(),
    (static function () { $len = null; $r = WP_HTML_Decoder::read_character_reference('attribute', '&not=x', 0, $len); return [$r, $len]; })(),
]);
$say('token map', !class_exists('WP_Token_Map') ? 'missing' : (static function () {
    $map = WP_Token_Map::from_array(['apple' => 'A', 'app' => 'a', 'banana' => 'B', 'b' => 'b']);
    $len = null;
    return [$map->contains('app'), $map->contains('APP'), $map->contains('APP', 'ascii-case-insensitive'), $map->read_token('applesauce', 0, $len), $len, $map->read_token('xbanana', 1), $map->read_token('zzz'), $map->to_array()];
})());

// Text runs, escaping, the static helpers, seeking, edits on implied tokens, match offsets.
$details = (static function (): array {
$walk = static function (?WP_HTML_Processor $p): array {
    if ($p === null) return ['null'];
    $t = [];
    while ($p->next_token()) {
        $t[] = ($p->is_tag_closer() ? '-' : '+') . $p->get_token_name() . ($p->get_token_type() === '#tag' ? '' : '=' . json_encode($p->get_modifiable_text(), JSON_UNESCAPED_UNICODE)) . '@' . $p->get_current_depth();
    }
    return [implode(' ', $t), $p->get_last_error(), $p->get_unsupported_exception()?->getMessage()];
};
$out = [];
foreach ([' a', "\n\ta b", '<div> a</div>', '<p> </p>', '<div>a </div>', "<table> \n<tr> <td> x </td> </tr> </table>", '<table> x</table>', '<select> <option> a</select>', "\0 a", " \0a", "<pre>x</pre>", "<pre> \nx</pre>", "<pre>\r\nx</pre>", "<textarea>\r\nx</textarea>", "<ul> <li>a</li> </ul>", "<svg> <g/> x</svg>", "<math> <mi>x</mi> </math>"] as $html) {
    $out['text'][] = [$html, $walk(WP_HTML_Processor::create_fragment($html))];
}
foreach (['<pre>x</pre>', '<textarea>x</textarea>', '<listing>x</listing>', '<p>a > b "c" \'d\' & e</p>', '<div title="<a> & \' &quot;">x</div>', '<div title=\'a"b\'>x</div>', '<!-- a -- b --><!---->', '<!--><!--->', '</funky comment>', '<?xml version="1.0"?>', '<!DOCTYPE html>', '<svg><![CDATA[a<b]]></svg>', '<svg><foreignObject x="1"/><clipPath/></svg>', '<math><annotation-xml encoding="text/html"></annotation-xml></math>', '<svg viewbox="0 0 1 1" xlink:href="x"></svg>', '<div data-ü="1">x</div>', '<img src="a?b=1&amp;c=2">', '<a href="x">y</a>', "<script>\nx</script>", '<title>a &amp; b</title>', '<textarea>a &amp; b</textarea>', '<style>&amp;</style>', '<xmp>&amp;</xmp>', '<iframe>&amp;</iframe>', '<noscript>&amp;</noscript>', "<p>\0x</p>"] as $html) {
    $out['normalize'][] = [$html, WP_HTML_Processor::normalize($html)];
}
$out['is_special'] = array_map([WP_HTML_Processor::class, 'is_special'], ['MATH MI', 'SVG FOREIGNOBJECT', 'SVG TITLE', 'SVG DESC', 'MATH ANNOTATION-XML', 'SVG SVG', 'MATH MTEXT', 'td', 'TEMPLATE', 'LI', 'BUTTON', 'NOSCRIPT', 'IMG', 'FORM', 'FONT', 'NOBR']);
$out['is_void'] = array_map([WP_HTML_Processor::class, 'is_void'], ['AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR', 'MENUITEM', 'IMAGE', 'SVG']);
$seek = static function (array $script): array {
    $p = WP_HTML_Processor::create_fragment('<div><p>one</p><p>two</p><img><span>s</span></div>');
    $r = [];
    foreach ($script as [$op, $arg]) {
        $res = match ($op) { 'tag' => $p->next_tag($arg), 'token' => $p->next_token(), 'set' => $p->set_bookmark($arg), 'seek' => $p->seek($arg), 'release' => $p->release_bookmark($arg) };
        $r[] = [$op, $arg, $res, $p->get_token_name(), $p->is_tag_closer(), implode('>', (array) $p->get_breadcrumbs()), $p->get_current_depth()];
    }
    return $r;
};
$out['seek'] = [
    $seek([['tag', 'p'], ['set', 'a'], ['tag', 'img'], ['seek', 'a'], ['tag', 'p'], ['tag', 'span']]),
    $seek([['tag', 'p'], ['set', 'a'], ['release', 'a'], ['seek', 'a'], ['tag', 'p']]),
    $seek([['tag', 'img'], ['set', 'i'], ['tag', 'span'], ['seek', 'i'], ['token', null], ['token', null]]),
    $seek([['seek', 'none'], ['tag', 'p']]),
    $seek([['token', null], ['token', null], ['token', null], ['token', null], ['set', 'closer'], ['token', null], ['seek', 'closer']]),
    $seek([['tag', 'span'], ['set', 's'], ['tag', 'p'], ['seek', 's'], ['token', null]]),
];
$out['virtual edits'] = (static function () {
    $p = WP_HTML_Processor::create_fragment('<table><td class="c">x</table>');
    $r = [];
    while ($p->next_tag()) {
        $r[] = [$p->get_tag(), $p->get_attribute('class'), $p->set_attribute('data-v', '1'), $p->add_class('k'), $p->set_bookmark('b' . count($r))];
    }
    return [$r, $p->get_updated_html()];
})();
$out['text edits'] = (static function () {
    $p = WP_HTML_Processor::create_fragment('<p>hello</p><script>x</script>');
    $p->next_token();
    $p->next_token();
    $a = $p->set_modifiable_text('bye & <b>');
    $p->next_token();
    $p->next_token();
    $b = $p->set_modifiable_text('y();');
    return [$a, $b, $p->get_updated_html()];
})();
$out['next_tag closers'] = (static function () {
    $p = WP_HTML_Processor::create_fragment('<div><p>a<p>b</div>');
    $r = [];
    while ($p->next_tag(['tag_closers' => 'visit'])) {
        $r[] = ($p->is_tag_closer() ? '-' : '+') . $p->get_tag();
    }
    return $r;
})();
$out['direct'] = (static function () {
    set_error_handler(static fn () => true);
    $p = new WP_HTML_Processor('<p>x', WP_HTML_Processor::CONSTRUCTOR_UNLOCK_CODE);
    restore_error_handler();
    $r = [];
    while ($p->next_token()) {
        $r[] = ($p->is_tag_closer() ? '-' : '+') . $p->get_token_name() . '@' . implode('>', $p->get_breadcrumbs());
    }
    return [$r, $p->get_last_error()];
})();
    return $out;
})();
foreach ($details as $label => $value) {
    $say('detail: ' . $label, $value);
}
$say('match offsets and arguments', (static function (): array {
$html = '<div><p class="a">1</p><section><p class="a b">2</p><ul><li><p>3</p></li></ul></section><p>4</p></div>';
$out = [];
foreach ([['tag_name' => 'P', 'match_offset' => 2], ['breadcrumbs' => ['P'], 'match_offset' => 2], ['class_name' => 'a', 'match_offset' => 2], ['match_offset' => 3], ['tag_name' => 'P', 'match_offset' => 0], ['tag_name' => 'p', 'tag_closers' => 'visit', 'match_offset' => 2], ['breadcrumbs' => ['DIV', 'P'], 'match_offset' => 2]] as $q) {
    $p = WP_HTML_Processor::create_fragment($html);
    $r = [];
    $guard = 0;
    while ($guard++ < 20 && $p->next_tag($q)) {
        $r[] = ($p->is_tag_closer() ? '-' : '+') . $p->get_tag() . ':' . $p->get_attribute('class');
    }
    $out[] = [$q, $r];
}
foreach (['UTF-8', 'utf-8', 'UTF8', 'Utf-8'] as $enc) {
    $out[] = [$enc, WP_HTML_Processor::create_fragment('x', '<body>', $enc) !== null, WP_HTML_Processor::create_full_parser('x', $enc) !== null];
}
foreach (['<body>', '<BODY>', '<body class="x">', '<body >'] as $ctx) {
    $out[] = [$ctx, WP_HTML_Processor::create_fragment('x', $ctx) !== null];
}
    return $out;
})());
// The helper classes and doctype info.
$say('helpers', (static function (): array {
$out = [];
foreach (['<!DOCTYPE html>', '<!doctype HTML>', '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">', '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">', '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">', '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "x">', '<!DOCTYPE>', '<!DOCTYPE foo>', '<!DOCTYPE html SYSTEM "about:legacy-compat">', "<!DOCTYPE html PUBLIC 'x' 'y'>", '<!DOCTYPE html PUBLIC>', '<!DOCTYPE html bogus>', '<p>', '<!DOCTYPE html><p>'] as $h) {
    $d = WP_HTML_Doctype_Info::from_doctype_token($h);
    $t = new WP_HTML_Tag_Processor($h);
    $t->next_token();
    $i = $t->get_doctype_info();
    $out['doctype'][] = [$h, $d ? get_object_vars($d) : null, $i ? get_object_vars($i) : null];
}
$tok = new WP_HTML_Token('bm', 'DIV', false);
$out['token'] = [get_object_vars($tok)];
$out['span'] = get_object_vars(new WP_HTML_Span(1, 2));
$out['replacement'] = get_object_vars(new WP_HTML_Text_Replacement(1, 2, 'x'));
$out['attribute'] = get_object_vars(new WP_HTML_Attribute_Token('n', 1, 2, 0, 5, false));
$out['event'] = array_keys(get_object_vars(new WP_HTML_Stack_Event($tok, 'push', 'real')));
$st = new WP_HTML_Processor_State();
$out['state'] = array_map(static fn ($v) => is_object($v) ? get_class($v) : $v, get_object_vars($st));
$oe = new WP_HTML_Open_Elements();
$a = new WP_HTML_Token('a', 'P', false);
$b = new WP_HTML_Token('b', 'B', false);
$oe->push($a);
$oe->push($b);
$out['open'] = [$oe->count(), $oe->current_node()->node_name, $oe->contains('P'), $oe->contains('DIV'), $oe->has_element_in_scope('P'), $oe->has_p_in_button_scope(), $oe->current_node_is('B'), $oe->at(1)->node_name, $oe->pop(), $oe->count(), array_map(static fn ($n) => $n->node_name, iterator_to_array($oe->walk_down(), false))];
$af = new WP_HTML_Active_Formatting_Elements();
$af->push($b);
$af->insert_marker();
$out['afe'] = [$af->count(), $af->current_node()->node_name, $af->contains_node($b), $af->clear_up_to_last_marker(), $af->count()];
    return $out;
})());
// The tag processor underneath: processing instructions, presumptuous tags, leading line breaks, text edits.
$say('tag processor tokens', (static function (): array {
$out = [];
foreach (['<?pi x?>', '<?PI x?>', '<?php x?>', '<?PHP x?>', '<?xml v?>', '<?XML v?>', '<?xml-stylesheet h?>', '<?xmlx a?>', '<?a?>', '<?a ?>', '<?a  b?>', '<?a-b.c_d:e f?>', '<?a b>', '<?1a b?>', '<?a/b?>', "<?a\tb?>", "<?a\nb?>", '<?a?b?>', '<? x?>', '<?>', '<?a b c ? >', "<textarea>\r\nx</textarea>", "<textarea>\rx</textarea>", "<textarea>\n\nx</textarea>", "<textarea> \nx</textarea>", "<pre>\r\nx", "<pre>\n\nx", "<listing>\nx", "<pre></pre>\nx", "<pre><b>\nx", "<PRE>\nx", "</ >", "</\n>", "</ a>", "</1>"] as $h) {
    $p = new WP_HTML_Tag_Processor($h);
    $t = [];
    while ($p->next_token()) {
        $t[] = [$p->get_token_type(), $p->get_token_name(), $p->get_tag(), $p->get_modifiable_text(), $p->get_comment_type(), $p->get_full_comment_text()];
    }
    $out[] = [$h, $t, $p->paused_at_incomplete_token()];
}
    return $out;
})());
$say('tag processor text edits', (static function (): array {
$out = [];
foreach (['<p>x</p>' => 2, '<title>x</title>' => 1, '<textarea>x</textarea>' => 1, '<script>x</script>' => 1, '<style>x</style>' => 1, '<!-- x -->' => 1] as $html => $n) {
    $p = new WP_HTML_Tag_Processor($html);
    for ($i = 0; $i < $n; $i++) $p->next_token();
    $r = $p->set_modifiable_text('a<b>c"d\'e&f&amp;</title></script>-->');
    $out[] = [$html, $r, $p->get_updated_html(), $p->get_modifiable_text()];
}
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
