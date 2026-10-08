<?php
/**
 * formatting.php's callbacks and inner helpers (probe formatting-callbacks),
 * each run on its own: autop's newline keeper, what texturize's shortcode and
 * split patterns match (their behaviour, never their text), its element
 * stack and its primes, the link callbacks behind links_add_base_url,
 * links_add_target and make_clickable, a smiley translated, a URL shortened,
 * the less-than callback, an ISO 8601 date read, and emoji in a mail made
 * images. Emoji image addresses are masked: Minn serves its own copies.
 * Nothing is written. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$zone = [get_option('timezone_string'), get_option('gmt_offset')];
register_shutdown_function(static function () use ($zone): void {
    update_option('timezone_string', $zone[0]);
    update_option('gmt_offset', $zone[1]);
});
$emoji = static fn ($value) => is_string($value) ? (string) preg_replace('#https?://[^"\']+/(?=[0-9a-f-]+\.(?:png|svg))#', '{emoji}/', $value) : $value;

$say('autop newlines kept', [_autop_newline_preservation_helper(["a\nb\n"]), _autop_newline_preservation_helper(['none']), _autop_newline_preservation_helper(["\r\n"])]);

$shortcode = _get_wptexturize_shortcode_regex(['caption', 'gallery-x']);
$say('texturize shortcode pattern', array_map(static function (string $sample) use ($shortcode) {
    return [$sample, @preg_match('/' . $shortcode . '/', $sample, $m) === 1 ? $m[0] : null];
}, ['[caption]', '[caption id="x"]', '[/caption]', '[[caption]]', '[gallery-x a=1]', '[gallery]', 'text [caption] text', '[caption a="]"]', '[caption]inner[/caption]', '[ caption]', '[captions]']));
$splitting = static fn (string $regex, array $samples): array => array_map(static fn (string $s) => [$s, preg_split($regex, $s, -1, PREG_SPLIT_DELIM_CAPTURE)], $samples);
$samples = ['plain', 'a <b>bold</b> c', 'x <!-- c --> y', 'x <!-- a > b --> y', '<p>[caption]text[/caption]</p>', '1 < 2 > 0', '<a title="x>y">t</a>', 'a <!-- un', '<![CDATA[ x ]]>', '[[escaped]]'];
$say('texturize split pattern', $splitting(_get_wptexturize_split_regex(), $samples));
$say('texturize split pattern with shortcodes', $splitting(_get_wptexturize_split_regex($shortcode), $samples));

$stack = [];
$pushes = [];
foreach (['<code>', '<pre class="x">', '</pre>', '<b>', '</code>', '</code>', '<code/>', '<CODE>', '</p>'] as $tag) {
    _wptexturize_pushpop_element($tag, $stack, ['code', 'pre', 'kbd']);
    $pushes[] = [$tag, $stack];
}
$say('texturize element stack', $pushes);
$say('texturize primes', array_map(static fn ($text) => wptexturize_primes($text, "'", '&#8242;', '&#8216;', '&#8217;'), ["9' tall", "'quoted' here", "1990's", "a 'b' c 'd", "it's 5' 'x'", "''", "x"]));
$say('texturize double primes', array_map(static fn ($text) => wptexturize_primes($text, '"', '&#8243;', '&#8220;', '&#8221;'), ['6" wide', '"said" 6"', '"open', 'a "b" c']));

global $_links_add_base, $_links_add_target;
$_links_add_base = 'https://base.example/dir/page';
$say('links made absolute', [
    _links_add_base(['src="img.png"', 'src', '"', 'img.png']),
    _links_add_base(["href='/root'", 'href', "'", '/root']),
    _links_add_base(['href="https://other.test/x"', 'href', '"', 'https://other.test/x']),
    _links_add_base(['href="mailto:a@b.test"', 'href', '"', 'mailto:a@b.test']),
    _links_add_base(['href="#top"', 'href', '"', '#top']),
    _links_add_base(['href="//cdn.test/x"', 'href', '"', '//cdn.test/x']),
    links_add_base_url('<a href="x/y">a</a><img src=\'/z.png\'> <a href="https://k.test/">k</a>', 'https://base.example/dir/'),
    links_add_base_url('<a href="#top">t</a><img src="//cdn.test/i.png"><a href="mailto:a@b.test">m</a><a href="../up">u</a><a HREF="Case">c</a>', 'https://base.example/dir/page'),
]);
$_links_add_target = '_blank';
$say('links given a target', [
    _links_add_target(['<a href="x">', 'a', ' href="x"']),
    _links_add_target(['<a href="x" target="_self">', 'a', ' href="x" target="_self"']),
    _links_add_target(["<a target='old' href=\"x\">", 'a', " target='old' href=\"x\""]),
    _links_add_target(['<area href="x"/>', 'area', ' href="x"/']),
    links_add_target('<a href="x" target="_self">a</a> <A HREF="y">b</A>'),
    links_add_target('<a href="x">a</a><area href="y">', '_top', ['a', 'area']),
]);

$say('clickable rel', array_map(static fn ($url) => _make_clickable_rel_attr($url), ['https://example.test/', home_url('/page/'), 'http://minn.localhost/x', 'https://sub.minn.localhost/', '/relative/path', 'ftp://files.test/y', 'mailto:a@b.test', 'HTTPS://EXAMPLE.TEST/']));
$rel = static fn ($rel, $url) => $rel . ' ugc';
add_filter('make_clickable_rel', $rel, 10, 2);
$say('clickable rel, filtered', [_make_clickable_rel_attr('https://example.test/'), _make_clickable_rel_attr(home_url('/'))]);
remove_filter('make_clickable_rel', $rel, 10);
$say('clickable url', [
    _make_url_clickable_cb([' https://example.test/a', ' ', 'https://example.test/a', '', '']),
    _make_url_clickable_cb([' https://example.test/a.', ' ', 'https://example.test/a.', '', '']),
    _make_url_clickable_cb(['(https://example.test/a)', '(', 'https://example.test/a', ')', '']),
    _make_url_clickable_cb([' https://example.test/a)', ' ', 'https://example.test/a)', '', '']),
    _make_url_clickable_cb([' javascript:alert(1)', ' ', 'javascript:alert(1)', '', '']),
]);
$say('clickable www and ftp', [
    _make_web_ftp_clickable_cb([' www.example.test', ' ', 'www.example.test', '']),
    _make_web_ftp_clickable_cb([' ftp.example.test/x', ' ', 'ftp.example.test/x', '']),
    _make_web_ftp_clickable_cb([' www.example.test.', ' ', 'www.example.test.', '']),
    _make_web_ftp_clickable_cb([' www.example.test)', ' ', 'www.example.test)', '']),
]);
$say('clickable email', [
    _make_email_clickable_cb([' a@b.test', ' ', 'a', 'b.test']),
    _make_email_clickable_cb(['a.b+c@d-e.test', '', 'a.b+c', 'd-e.test']),
]);

$say('whole text made clickable', array_map(static fn ($text) => make_clickable($text), [
    'see www.example.test now',
    'mail a@b.test or c.d@e-f.test.',
    'url https://x.test/a). end',
    '(https://x.test/a)',
    'ftp.example.test/x and ftp://files.test/y',
    'already <a href="https://x.test/">https://x.test/</a> linked',
    'in <code>https://x.test/code</code> and <pre>www.pre.test</pre>',
    'https://x.test/a?b=1&c=2#frag, then',
    'trailing https://x.test/a.',
    'javascript:alert(1) and http://',
    'site ' . home_url('/inside/') . ' link',
    "two\nlines https://x.test/\nnext",
    'x@y',
    'wiki https://x.test/wiki/A_(b) end and (see https://x.test/c_(d))',
    'list https://x.test/a,b,c and https://x.test/q?x=1;y=2',
    'local http://localhost:8080/x and HTTPS://X.TEST/UP',
    'www.example.test/path?q=1. and WWW.UPPER.TEST',
    'A@B.TEST and mailto:a@b.test',
    'a<b>https://x.test/in</b>c',
    '<a href="x">www.inside.test</a> <img alt="https://x.test/alt" src="y">',
    'https://x.test/ https://y.test/',
    "https://\u{fc}n\u{ef}.test/p",
    'https://x.test/a"b',
    "https://x.test/a'b",
    'see https://x.test/a!',
    'https://x.test/a?',
    '<!-- https://x.test/comment --> after',
    'https://x.test/<b>',
    '<script>var u = "https://x.test/s";</script><style>a{background:url(https://x.test/st)}</style>',
    '<textarea>https://x.test/ta</textarea><kbd>https://x.test/k</kbd><tt>https://x.test/tt</tt>',
    '<code>in <b>https://x.test/deep</b></code> https://x.test/out',
    '<a href="x">see https://x.test/inner and www.inner.test</a>',
    '<CODE>https://x.test/upper</CODE>',
    'www.a.test,www.b.test',
    'https://x.test/a-b_c~d%20e',
    '>https://x.test/gt',
    '=https://x.test/eq',
]));
add_filter('comment_text', $clickable = static fn ($text) => make_clickable($text));
$say('whole text made clickable in a comment', [apply_filters('comment_text', 'see https://x.test/ and www.example.test'), apply_filters('comment_text', 'home ' . home_url('/in/') . ' and a@b.test')]);
remove_filter('comment_text', $clickable);
$say('url schemes cleaned', array_map(static fn ($url) => [esc_url($url), esc_url_raw($url)], ['HTTPS://X.TEST/UP', 'Mailto:A@B.TEST', 'JavaScript:alert(1)', 'HTTP://x.test/?a=B', 'Ftp://f.test/', 'hTtPs://x.test/', 'x.test/path']));
// WP-CLI keeps site_url on the configured scheme (its own site_url filter); the engine's runner has no
// such filter, so the site's address is masked whole.
$site = static fn ($value) => is_string($value) ? (string) preg_replace('#https?://' . preg_quote((string) wp_parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value) : $value;
$say('smileys translated', array_map(static fn ($code) => $site($emoji(translate_smiley([$code]))), [':)', ' :)', ':-)', ':mrgreen:', ':roll:', ':?:', '8-)']));
$say('urls shortened', [url_shorten('https://www.example.test/a/very/long/path/that/goes/on/and/on'), url_shorten('http://example.test/'), url_shorten('https://www.example.test/short', 10), url_shorten('www.example.test/x/'), url_shorten('example.test', 5), url_shorten('')]);
$say('less-than callback', [wp_pre_kses_less_than_callback(['<b>']), wp_pre_kses_less_than_callback(['< 6 & "x"']), wp_pre_kses_less_than_callback(['<a']), wp_pre_kses_less_than_callback(['<'])]);

$say('iso 8601 read', [
    iso8601_to_datetime('2026-01-02T03:04:05Z'),
    iso8601_to_datetime('2026-01-02T03:04:05Z', 'gmt'),
    iso8601_to_datetime('2026-01-02T03:04:05+05:00'),
    iso8601_to_datetime('2026-01-02T03:04:05+05:00', 'gmt'),
    iso8601_to_datetime('20260102T030405Z', 'gmt'),
    iso8601_to_datetime('2026-01-02T03:04:05', 'gmt'),
    iso8601_to_datetime('2026-01-02T03:04:05-0230', 'gmt'),
    iso8601_to_datetime('not a date'),
    iso8601_to_datetime('not a date', 'gmt'),
]);
update_option('timezone_string', 'America/New_York');
$say('iso 8601 read in a site timezone', [
    iso8601_to_datetime('2026-01-02T03:04:05Z'),
    iso8601_to_datetime('2026-01-02T03:04:05Z', 'gmt'),
    iso8601_to_datetime('2026-07-02T03:04:05+05:00'),
    iso8601_to_datetime('2026-07-02T03:04:05+05:00', 'gmt'),
    iso8601_to_datetime('2026-01-02T03:04:05'),
    iso8601_to_datetime('2026-01-02T03:04:05', 'gmt'),
]);
update_option('timezone_string', '');
update_option('gmt_offset', 5.5);
$say('iso 8601 read at a numeric offset', [iso8601_to_datetime('2026-01-02T03:04:05Z'), iso8601_to_datetime('2026-01-02T03:04:05', 'gmt')]);
update_option('timezone_string', $zone[0]);
update_option('gmt_offset', $zone[1]);

$mail = static fn (string $message, $headers) => ['to' => 'a@b.test', 'subject' => "Hi \u{1F600}", 'message' => $message, 'headers' => $headers, 'attachments' => []];
$say('emoji in a mail', array_map(static fn ($atts) => array_map($emoji, wp_staticize_emoji_for_email($atts)), [
    $mail("<p>Smile \u{1F600}</p>", 'Content-Type: text/html; charset=UTF-8'),
    $mail("Smile \u{1F600}", ''),
    $mail("<p>Smile \u{1F600}</p>", ['Content-Type: text/html', 'X-Other: 1']),
    $mail("<p>Smile \u{1F600}</p>", "From: a@b.test\r\ncontent-type: TEXT/HTML"),
    $mail("Smile \u{1F600}", 'Content-Type: text/plain'),
    $mail("<p>Smile \u{1F600}</p>", "From: a@b.test\r\ncontent-type: text/html"),
    $mail("<p>Smile \u{1F600}</p>", 'Content-Type: TEXT/HTML'),
    $mail("<p>Smile \u{1F600}</p>", "X-A: 1\nContent-Type: text/html;charset=utf-8"),
    $mail("<p>Smile \u{1F600}</p>", 'Content-Type:text/html'),
]));
$html = static fn () => 'text/html';
add_filter('wp_mail_content_type', $html);
$say('emoji in a mail made html by filter', array_map($emoji, wp_staticize_emoji_for_email($mail("Smile \u{1F600}", ''))));
remove_filter('wp_mail_content_type', $html);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
