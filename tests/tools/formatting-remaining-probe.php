<?php
/**
 * The rest of formatting.php (probe formatting-remaining): text, user,
 * locale, MIME type and URL sanitizers, entity and character conversion,
 * the deep mappers, query strings, UTF-8 checks, emoji, the HTML splitter,
 * the targeted link rel callback, and antispambot (random, so judged by
 * what it decodes to). Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$texts = ["Hello  <b>World</b>\n\nNext", "  spaced \t tabs  ", "a < b > c", "%AB%cd encoded %zz", "line\rcarriage", "x&amp;y", "<script>alert(1)</script>tail", "octets %e2%80%94 dash", "emoji 😀 here", "\x00null", "<", "a <!-- c --> b", "%", "100% sure"];
$say('text fields', array_map(static fn ($text) => [_sanitize_text_fields($text, false), _sanitize_text_fields($text, true)], $texts));

$say('user names', array_map(static fn ($name) => [sanitize_user($name), sanitize_user($name, true)], ['Zz User', 'zz@example.com', 'ünïcode', '<b>tag</b>', 'a&amp;b', 'per%cent', 'dots.and-dash_es', '  trim  ', 'multi   space', 'josé', "tab\tname", '日本']));

$say('locale names and MIME types', [
    array_map(static fn ($value) => sanitize_locale_name($value), ['en_US', 'de_DE_formal', 'zz-ZZ', 'pt_BR@euro', 'bad locale!', '../x', '', 'en_US.UTF-8']),
    array_map(static fn ($value) => sanitize_mime_type($value), ['image/jpeg', 'IMAGE/PNG', 'text/plain; charset=utf-8', 'application/vnd.ms-excel', 'bad mime!', 'a/b+c.d', '', "x\0y"]),
    array_map(static fn ($value) => tag_escape($value), ['div', 'DIV', 'my-element', 'svg:rect', 'a b', '<p>', 'h1', 'x_y']),
]);

$say('URLs sanitized', array_map(static fn ($url) => [sanitize_url($url), sanitize_url($url, ['https'])], ['https://zz.example/a b?c=d&e=f', 'javascript:alert(1)', 'zz.example/path', '//zz.example/x', 'mailto:a@b.example', 'ftp://zz.example', 'https://zz.example/<script>', 'http://zz.example/?a[]=1', '', '/relative', "https://zz.example/\n", 'https://zz.example/%0D%0Abad']));

$say('characters and entities', [
    array_map(static fn ($value) => convert_chars($value), ['<p>Text &#8211; dash &#150; old &#x2014;</p>', '&#8364; &#128; &#159;', '<title>T</title>&<category>c</category>', '&amp; &lt;', 'plain']),
    array_map(static fn ($value) => ent2ncr($value), ['&nbsp; &copy; &mdash; &amp; &lt; &gt; &quot; &hellip; &zz;', 'plain', '&Alpha;&alefsym;']),
    array_map(static fn ($value) => normalize_whitespace($value), ["  a \r\n b\r c\n\n\n d  ", "\t\ttab\t", "x\xC2\xA0y"]),
    array_map(static fn ($value) => format_to_edit($value), ['<b>bold</b> & co', "line\n"]), format_to_edit('<b>x</b>', true),
    array_map(static fn ($value) => _wp_iso_convert($value), [['=?ISO-8859-1?Q?caf=E9?=', 'caf=E9'], ['x', '_a_b_']]),
]);

$say('deep mappers and query strings', [
    map_deep(['a' => ' x ', 'b' => [' y ', (object) ['c' => ' z ']]], 'trim'),
    urlencode_deep(['a b', ['c&d', 'é']]), rawurlencode_deep(['a b', ['c&d', 'é']]), urldecode_deep(['a+b%20c', ['%C3%A9']]),
    stripslashes_from_strings_only("It\\'s"), stripslashes_from_strings_only(['a\\b']), stripslashes_from_strings_only(5),
    (static function () {
        $out = [];
        foreach (['a=1&b[]=2&b[]=3&c[d]=e', 'x=a+b&y=%20&z', '', 'dup=1&dup=2'] as $query) {
            wp_parse_str($query, $parsed);
            $out[] = $parsed;
        }
        return $out;
    })(),
    esc_sql("It's \"quoted\" \\ back"), esc_sql(["a'b", ['c"d']]),
]);

$say('UTF-8 and emoji', [
    array_map(static fn ($value) => seems_utf8($value), ['plain', 'é', "\xC3\x28", "\xE2\x82\xAC", "\xF0\x9F\x98\x80", "\xFF", '']),
    array_map(static fn ($text) => [wp_check_invalid_utf8($text), wp_check_invalid_utf8($text, true)], ['valid é', "bad \xC3\x28 seq", "\xFF\xFE", '', 'x']),
    array_map(static fn ($value) => wp_encode_emoji($value), ['smile 😀', 'flag 🇺🇸 and family 👨‍👩‍👧', 'no emoji', '©®™', 'mix é 🎉']),
    [count(_wp_emoji_list('entities')), count(_wp_emoji_list('partials')), md5(implode(',', _wp_emoji_list('entities'))), md5(implode(',', _wp_emoji_list('partials'))), array_slice(_wp_emoji_list('entities'), 0, 3), array_slice(_wp_emoji_list('partials'), 0, 3)],
]);

$say('HTML splitting and replacing', [
    get_html_split_regex(),
    preg_split(get_html_split_regex(), 'a <b>c</b> <!-- d --> <![CDATA[ e ]]> <script>f</script> <', -1, PREG_SPLIT_DELIM_CAPTURE),
    wp_replace_in_html_tags('<a title="x y" data-z="x">x y</a> x', ['x' => 'Q']),
    wp_replace_in_html_tags('<p class="one two">one two</p><!-- one -->', ['one' => '1', 'two' => '2']),
    wp_replace_in_html_tags('no tags here', ['here' => 'there']),
    array_map(static fn ($value) => get_url_in_content($value), ['<a href="https://zz.example/a">x</a> <a href="/b">y</a>', 'no links', '<a href=\'https://zz.example/single\'>z</a>', '<a class="c" href = "https://zz.example/spaced">s</a>', '']),
]);

$say('targeted link rel', array_map(static fn ($value) => wp_targeted_link_rel_callback($value), [
    ['<a href="x" target="_blank">', ' href="x" target="_blank"'],
    ['<a href="x" target="_blank" rel="nofollow">', ' href="x" target="_blank" rel="nofollow"'],
    ['<a href="x" target="_self">', ' href="x" target="_self"'],
    ['<a href="x">', ' href="x"'],
    ['<a target="_blank" rel="noopener noreferrer" href="x">', ' target="_blank" rel="noopener noreferrer" href="x"'],
    ['<a href=\'x\' target=\'_blank\'>', ' href=\'x\' target=\'_blank\''],
    ['<a href="x" target="_blank" rel="">', ' href="x" target="_blank" rel=""'],
]));

$say('antispambot decodes to the address', array_map(static function ($email) {
    $made = antispambot($email);
    $hex = antispambot($email, 1);
    return [html_entity_decode($made, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $email, rawurldecode(html_entity_decode($hex, ENT_QUOTES | ENT_HTML5, 'UTF-8')) === $email, strlen($made) >= strlen($email)];
}, ['zz@example.com', 'a.b+c@sub.example.org', 'x']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
