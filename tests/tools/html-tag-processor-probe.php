<?php
/**
 * Behaviour probe for WP_HTML_Tag_Processor: tokenising, tag queries,
 * attribute reads and writes, classes, bookmarks and seeking, text tokens,
 * comments and doctypes, and the updated HTML. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$walk = static function (string $html, bool $tokens = false, array $query = []): array {
    $p = new WP_HTML_Tag_Processor($html);
    $rows = [];
    $guard = 0;
    while ($guard++ < 60 && ($tokens ? $p->next_token() : $p->next_tag($query))) {
        $rows[] = [$p->get_token_type(), $p->get_token_name(), $p->get_tag(), $p->is_tag_closer(), $p->has_self_closing_flag(), $p->get_modifiable_text()];
    }
    return $rows;
};

$say('empty', $walk(''));
$say('text only', [$walk('just text'), $walk('just text', true)]);
$say('simple', $walk('<div class="a"><p>Hi <b>there</b></p><br/><img src="x"></div>'));
$say('simple tokens', $walk("<div class=\"a\">\n<p>Hi <b>there</b></p><br/><img src=\"x\"><!-- c --></div>", true));
$say('closers', $walk('<div><p>x</p></div>', false, ['tag_closers' => 'visit']));
$say('case', $walk('<DIV><Span></SPAN></div>'));
$say('void and self closing', $walk('<br><br/><br /><img src=x /><div/><svg><path/></svg>'));
$say('raw text', $walk('<script>var a = "<b>";</script><style>p{}</style><textarea><p>x</p></textarea><title><b>t</b></title><p>after</p>'));
$say('raw text tokens', $walk('<script>var a = "<b>";</script><textarea><p>x</p></textarea><p>after</p>', true));
$say('comments', $walk('<!-- a --><!--><!--->x<!---->y<!DOCTYPE html><![CDATA[c]]><?php echo 1; ?><p>z</p><!-- unterminated', true));
$say('funky', $walk('</3><!3><? x ?><p></ p><a b="c"/></div class="x">', true));
$say('incomplete', (static function () {
    $p = new WP_HTML_Tag_Processor('<div><p class="a');
    $r = [];
    $r[] = $p->next_tag();
    $r[] = $p->get_tag();
    $r[] = $p->next_tag();
    $r[] = $p->paused_at_incomplete_token();
    $r[] = $p->get_tag();
    $r[] = $p->get_updated_html();
    return $r;
})());

$attrHtml = '<div id="one" class="a b" data-x="1" DATA-Y=two z=\'q "u" o\' bool disabled="" ENT="&amp; &lt; &quot; &#39; &#x41; &notanentity; &nbsp;" dup="first" dup="second" empty>';
$say('get_attribute', (static function () use ($attrHtml) {
    $p = new WP_HTML_Tag_Processor($attrHtml);
    $p->next_tag();
    return [$p->get_attribute('id'), $p->get_attribute('ID'), $p->get_attribute('class'), $p->get_attribute('data-x'), $p->get_attribute('data-y'), $p->get_attribute('z'), $p->get_attribute('bool'), $p->get_attribute('disabled'), $p->get_attribute('ent'), $p->get_attribute('dup'), $p->get_attribute('empty'), $p->get_attribute('missing'), $p->get_attribute_names_with_prefix('data-'), $p->get_attribute_names_with_prefix('DATA-'), $p->get_attribute_names_with_prefix(''), $p->get_attribute_names_with_prefix('nope')];
})());
$say('get_attribute before next', (static function () {
    $p = new WP_HTML_Tag_Processor('<div id="x">');
    return [$p->get_attribute('id'), $p->get_tag(), $p->get_attribute_names_with_prefix('i'), $p->has_class('x'), $p->set_attribute('id', 'y'), $p->add_class('c'), $p->get_updated_html()];
})());
$say('get_attribute on closer', (static function () {
    $p = new WP_HTML_Tag_Processor('<div id="x"></div>');
    $p->next_tag(['tag_closers' => 'visit']);
    $p->next_tag(['tag_closers' => 'visit']);
    return [$p->get_tag(), $p->is_tag_closer(), $p->get_attribute('id'), $p->set_attribute('id', 'y'), $p->add_class('c'), $p->get_updated_html()];
})());

$set = static function (string $html, array $ops): array {
    $p = new WP_HTML_Tag_Processor($html);
    $p->next_tag();
    $results = [];
    foreach ($ops as $op) {
        $method = array_shift($op);
        $results[] = $p->$method(...$op);
    }
    return [$results, $p->get_updated_html(), (string) $p];
};
$say('set new attribute', $set('<div class="a">x</div>', [['set_attribute', 'id', 'one'], ['set_attribute', 'data-n', 5], ['set_attribute', 'data-f', 1.5]]));
$say('set existing attribute', $set('<div id=\'old\' class="a">x</div>', [['set_attribute', 'id', 'new'], ['set_attribute', 'CLASS', 'b c']]));
$say('set escaping', $set('<div>', [['set_attribute', 'title', 'a "b" <c> & \'d\' &amp; é'], ['set_attribute', 'data-json', '{"a":1}']]));
$say('set URL attributes', $set('<a href="x">', [['set_attribute', 'href', 'https://x.example/?a=1&b=2 c'], ['set_attribute', 'src', 'javascript:alert(1)'], ['set_attribute', 'action', '/relative?q="x"'], ['set_attribute', 'data-href', 'https://x.example/?a=1&b=2'], ['set_attribute', 'cite', ''], ['set_attribute', 'HREF', 'https://x.example/ü']]));
$say('set boolean', $set('<input type="text" disabled="disabled">', [['set_attribute', 'required', true], ['set_attribute', 'disabled', true], ['set_attribute', 'readonly', false], ['set_attribute', 'type', false]]));
$say('set null', $set('<div id="x" class="a">', [['set_attribute', 'id', null], ['set_attribute', 'title', null]]));
$say('set invalid names', $set('<div>', [['set_attribute', 'bad name', 'x'], ['set_attribute', '', 'x'], ['set_attribute', 'x=y', 'x'], ['set_attribute', 'ok-name_1:x', 'x'], ['set_attribute', 'UPPER', 'x'], ['set_attribute', 'a"b', 'x']]));
$say('set duplicate attribute', $set('<div dup="first" dup="second" data-z="1">', [['set_attribute', 'dup', 'third'], ['get_attribute', 'dup']]));
$say('set twice', $set('<div a="1">', [['set_attribute', 'a', '2'], ['set_attribute', 'a', '3'], ['set_attribute', 'b', '1'], ['set_attribute', 'b', '2'], ['remove_attribute', 'b'], ['set_attribute', 'b', '4']]));
$say('remove attribute', $set('<div id="x" class="a"  data-y=1 bool>', [['remove_attribute', 'id'], ['remove_attribute', 'BOOL'], ['remove_attribute', 'missing'], ['remove_attribute', 'data-y']]));
$say('remove duplicate', $set('<div dup="first" x="1" dup="second">', [['remove_attribute', 'dup']]));
$say('set then remove same call', $set('<div id="x">', [['set_attribute', 'id', 'y'], ['remove_attribute', 'id']]));
$say('remove then set', $set('<div id="x">', [['remove_attribute', 'id'], ['set_attribute', 'id', 'y']]));
$say('self closing set', $set('<img src="a"/>', [['set_attribute', 'alt', 'b'], ['remove_attribute', 'src']]));
$say('whitespace tag', $set("<div\n  id=\"x\"\n  class=\"a\"\n>", [['set_attribute', 'id', 'y'], ['set_attribute', 'data-n', '1'], ['remove_attribute', 'class']]));
$say('unquoted set', $set('<div id=x class=a>', [['set_attribute', 'id', 'y'], ['remove_attribute', 'class']]));

$say('add_class', $set('<div class="a b">', [['add_class', 'c'], ['add_class', 'a'], ['add_class', 'd e'], ['add_class', ''], ['add_class', 'C']]));
$say('add_class no attr', $set('<div id="x">', [['add_class', 'c'], ['add_class', 'd']]));
$say('add_class messy', $set("<div class=\"  a   b\n c \">", [['add_class', 'z']]));
$say('add_class messy remove', $set("<div class=\"  a   b\n c \">", [['remove_class', 'b']]));
$say('remove_class', $set('<div class="a b c">', [['remove_class', 'b'], ['remove_class', 'missing'], ['remove_class', 'B']]));
$say('remove_class last', $set('<div class="a" id="x">', [['remove_class', 'a']]));
$say('remove_class empty attr', $set('<div class="" id="x">', [['remove_class', 'a'], ['add_class', 'q']]));
$say('add then set class', $set('<div class="a">', [['add_class', 'b'], ['set_attribute', 'class', 'z']]));
$say('set then add class', $set('<div class="a">', [['set_attribute', 'class', 'z'], ['add_class', 'b']]));
$say('remove then add class', $set('<div class="a b">', [['remove_class', 'a'], ['add_class', 'c'], ['remove_class', 'c']]));
$say('remove class attr then add', $set('<div class="a">', [['remove_attribute', 'class'], ['add_class', 'b']]));
$say('has_class', (static function () {
    $p = new WP_HTML_Tag_Processor('<div class="a  b B">');
    $p->next_tag();
    $r = [$p->has_class('a'), $p->has_class('A'), $p->has_class('B'), $p->has_class('c'), iterator_to_array($p->class_list(), false)];
    $p->add_class('c');
    $r[] = $p->has_class('c');
    $r[] = iterator_to_array($p->class_list(), false);
    $p->remove_class('a');
    $r[] = $p->has_class('a');
    $r[] = iterator_to_array($p->class_list(), false);
    $p->set_attribute('class', 'q r');
    $r[] = [$p->has_class('q'), $p->has_class('c')];
    $r[] = iterator_to_array($p->class_list(), false);
    $p2 = new WP_HTML_Tag_Processor('<div>');
    $p2->next_tag();
    $r[] = [$p2->has_class('a'), iterator_to_array($p2->class_list(), false)];
    return $r;
})());
$say('class entity', (static function () {
    $p = new WP_HTML_Tag_Processor('<div class="a&amp;b &lt;c">');
    $p->next_tag();
    return [$p->get_attribute('class'), $p->has_class('a&b'), $p->has_class('a&amp;b'), iterator_to_array($p->class_list(), false)];
})());

$say('next_tag string query', (static function () {
    $p = new WP_HTML_Tag_Processor('<div><span>a</span><SPAN>b</SPAN><p>c</p></div>');
    $r = [];
    $r[] = $p->next_tag('span');
    $r[] = $p->get_tag();
    $r[] = $p->next_tag('SPAN');
    $r[] = $p->next_tag('span');
    $r[] = $p->get_tag();
    $r[] = $p->next_tag('p');
    $r[] = $p->get_tag();
    $r[] = $p->next_tag('div');
    $r[] = $p->get_tag();
    return $r;
})());
$say('next_tag array query', (static function () {
    $p = new WP_HTML_Tag_Processor('<div class="x"><p class="y z">1</p><p class="y">2</p><p>3</p><span class="y">4</span></div>');
    $r = [];
    $r[] = $p->next_tag(['tag_name' => 'p', 'class_name' => 'y']);
    $r[] = $p->get_attribute('class');
    $r[] = $p->next_tag(['class_name' => 'y']);
    $r[] = [$p->get_tag(), $p->get_attribute('class')];
    $r[] = $p->next_tag(['class_name' => 'y']);
    $r[] = $p->get_tag();
    $r[] = $p->next_tag(['class_name' => 'Y']);
    $p = new WP_HTML_Tag_Processor('<p>1</p><p>2</p><p>3</p>');
    $r[] = $p->next_tag(['tag_name' => 'p', 'match_offset' => 2]);
    $r[] = $p->get_modifiable_text();
    $p = new WP_HTML_Tag_Processor('<p>1</p><p>2</p><p>3</p>');
    $r[] = $p->next_tag(['match_offset' => 3]);
    $r[] = $p->get_modifiable_text();
    $r[] = $p->next_tag(['match_offset' => 0]);
    $p = new WP_HTML_Tag_Processor('<div><p>1</p></div>');
    $r[] = $p->next_tag(['tag_name' => 'p', 'tag_closers' => 'visit']);
    $r[] = $p->next_tag(['tag_closers' => 'visit']);
    $r[] = [$p->get_tag(), $p->is_tag_closer()];
    $r[] = $p->next_tag(['tag_name' => 'div', 'tag_closers' => 'visit']);
    $r[] = [$p->get_tag(), $p->is_tag_closer()];
    $p = new WP_HTML_Tag_Processor('<div><p>1</p></div>');
    $r[] = $p->next_tag(['tag_closers' => 'skip']);
    $r[] = $p->next_tag(['tag_name' => 'nope']);
    $r[] = $p->get_tag();
    $r[] = $p->next_tag();
    return $r;
})());
$say('next_tag after end', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>1</p>');
    $r = [$p->next_tag(), $p->next_tag(), $p->next_tag(), $p->get_tag(), $p->get_attribute('x'), $p->set_attribute('x', 'y'), $p->get_updated_html()];
    return $r;
})());

$say('bookmarks', (static function () {
    $p = new WP_HTML_Tag_Processor('<div id="a"><p id="b">1</p><p id="c">2</p></div>');
    $r = [];
    $p->next_tag('div');
    $r[] = $p->set_bookmark('div');
    $p->next_tag('p');
    $r[] = $p->set_bookmark('first');
    $r[] = $p->has_bookmark('first');
    $r[] = $p->has_bookmark('nope');
    $p->next_tag('p');
    $p->set_attribute('data-seen', '1');
    $r[] = $p->seek('first');
    $r[] = $p->get_attribute('id');
    $p->set_attribute('data-first', '1');
    $r[] = $p->seek('div');
    $r[] = $p->get_attribute('id');
    $p->add_class('seen');
    $r[] = $p->next_tag('p');
    $r[] = $p->get_attribute('id');
    $r[] = $p->seek('nope');
    $r[] = $p->release_bookmark('first');
    $r[] = $p->release_bookmark('first');
    $r[] = $p->has_bookmark('first');
    $r[] = $p->seek('first');
    $r[] = $p->get_updated_html();
    return $r;
})());
$say('bookmark then modify earlier', (static function () {
    $p = new WP_HTML_Tag_Processor('<div id="a"><p id="b">1</p><p id="c">2</p></div>');
    $p->next_tag('div');
    $p->next_tag('p');
    $p->next_tag('p');
    $p->set_bookmark('last');
    $p->seek('last');
    $p->set_attribute('data-x', 'y');
    $p->seek('last');
    $r = [$p->get_attribute('id'), $p->get_attribute('data-x')];
    $p->set_attribute('data-x', 'z');
    $r[] = $p->get_updated_html();
    return $r;
})());
$say('bookmark limit', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>1</p>');
    $p->next_tag();
    $r = [];
    for ($i = 0; $i < 12; $i++) {
        $r[] = $p->set_bookmark('b' . $i);
    }
    return $r;
})());
$say('seek limit', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>1</p><p>2</p>');
    $p->next_tag();
    $p->set_bookmark('a');
    $r = [];
    for ($i = 0; $i < 1002; $i++) {
        $ok = $p->seek('a');
        if (!$ok) {
            $r[] = ['failed at', $i];
            break;
        }
    }
    $r[] = count($r);
    return $r;
})());

$say('modifiable text', (static function () {
    $rows = [];
    $p = new WP_HTML_Tag_Processor('<p>a &amp; b &lt; c &nbsp; &#8217; &copy </p><script>x &amp; y</script><textarea>t &amp; u</textarea><title>ti &amp;</title><!-- co &amp; -->plain &amp; text');
    while ($p->next_token()) {
        $rows[] = [$p->get_token_type(), $p->get_token_name(), $p->get_modifiable_text()];
    }
    return $rows;
})());
$say('set modifiable text', (static function () {
    $r = [];
    $p = new WP_HTML_Tag_Processor('<p>old</p><script>a</script><textarea>t</textarea><title>x</title><!-- c --><img alt="i">');
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('new & <b>')];
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('replaced text')];
    $p->next_token();
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('var b = "</script>";')];
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('u < v & w')];
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('new title')];
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text(' new comment ')];
    $p->next_token();
    $r[] = [$p->get_token_name(), $p->set_modifiable_text('nope')];
    $r[] = $p->get_updated_html();
    return $r;
})());
$say('set modifiable text empty', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>old</p>');
    $p->next_token();
    $p->next_token();
    $r = [$p->set_modifiable_text(''), $p->get_updated_html()];
    $p2 = new WP_HTML_Tag_Processor('<p>x</p>');
    $p2->next_tag();
    $r[] = $p2->set_modifiable_text('y');
    $r[] = $p2->get_updated_html();
    return $r;
})());
$say('text then set attribute', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>one</p><p>two</p>');
    $p->next_tag();
    $p->set_attribute('id', 'a');
    $p->next_token();
    $p->set_modifiable_text('ONE');
    $p->next_tag();
    $p->set_attribute('id', 'b');
    return $p->get_updated_html();
})());

$say('comment details', (static function () {
    $rows = [];
    $p = new WP_HTML_Tag_Processor('<!-- normal --><!--- dash --><!--><!--->y<!DOCTYPE html><![CDATA[x]]><?php ?><!3 abc><?xml v?><!--a--!><!--b--><!-- c -- d --><!--e');
    while ($p->next_token()) {
        $rows[] = [$p->get_token_type(), $p->get_token_name(), $p->get_comment_type(), $p->get_modifiable_text(), $p->get_full_comment_text()];
    }
    return $rows;
})());
$say('doctype', (static function () {
    $rows = [];
    foreach (['<!DOCTYPE html>', '<!doctype HTML>', '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">', '<!DOCTYPE>', '<!DOCTYPE html SYSTEM "about:legacy-compat">'] as $html) {
        $p = new WP_HTML_Tag_Processor($html);
        $p->next_token();
        $info = $p->get_doctype_info();
        $rows[] = [$p->get_token_type(), $p->get_token_name(), $info === null ? null : [$info->name, $info->public_identifier, $info->system_identifier, $info->indicated_compatability_mode]];
    }
    return $rows;
})());
$say('namespaces', (static function () {
    $p = new WP_HTML_Tag_Processor('<svg viewBox="0 0 1 1"><foreignObject><div>x</div></foreignObject><linearGradient/></svg><math><mi>y</mi></math>');
    $rows = [];
    while ($p->next_tag()) {
        $rows[] = [$p->get_tag(), $p->get_namespace(), $p->get_qualified_tag_name(), $p->get_attribute_names_with_prefix('view'), $p->get_qualified_attribute_name('viewbox')];
    }
    return $rows;
})());
$say('attribute edge cases', (static function () {
    $rows = [];
    foreach (['<a href=x>', '<a href = "x" >', '<a href="x"y="z">', '<a b c d>', '<a =x>', '<a "b"="c">', "<a b='it\"s' c=\"it's\">", '<a b=x/y>', '<a b=x/>', '<a b="x"/>', '<a/b>', '<a /b>', '<a b=>', '<a b= c>', '<a b=&amp;>', '<a b="&amp;x&ampy&#38;&#x26;&#xg;&#;">'] as $html) {
        $p = new WP_HTML_Tag_Processor($html);
        $p->next_tag();
        $names = $p->get_attribute_names_with_prefix('') ?? [];
        $values = [];
        foreach ($names as $n) {
            $values[$n] = $p->get_attribute($n);
        }
        $rows[] = [$html, $p->get_tag(), $p->has_self_closing_flag(), $values];
    }
    return $rows;
})());
$say('unicode', (static function () {
    $p = new WP_HTML_Tag_Processor('<p title="héllo 日本">ünïcode</p>');
    $p->next_tag();
    $p->set_attribute('data-x', 'ç');
    $p->add_class('ñ');
    $p->next_token();
    return [$p->get_attribute('title'), $p->get_modifiable_text(), $p->get_updated_html()];
})());
$say('big', (static function () {
    $html = str_repeat('<div class="row"><span>x</span></div>', 300);
    $p = new WP_HTML_Tag_Processor($html);
    $n = 0;
    while ($p->next_tag('span')) {
        $n++;
        $p->set_attribute('data-i', (string) $n);
    }
    return [$n, strlen($p->get_updated_html()), md5($p->get_updated_html())];
})());
$say('toString before parsing', (string) new WP_HTML_Tag_Processor('<p>x</p>'));
$say('get_updated_html partial', (static function () {
    $p = new WP_HTML_Tag_Processor('<p>x</p><p>y</p>');
    $p->next_tag();
    $p->set_attribute('a', '1');
    $a = $p->get_updated_html();
    $p->next_tag();
    $p->set_attribute('b', '2');
    return [$a, $p->get_updated_html(), $p->get_attribute('b')];
})());

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
