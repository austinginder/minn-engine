<?php
/**
 * What wp_kses does with the parts of an allowlist beyond tag and attribute
 * names: the protocols the caller allows, the attributes that hold URIs, and
 * the value rules an attribute may carry (values, value_callback, required,
 * valueless, lengths and bounds). Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl', 'upload_dir'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$rel = static fn ($v) => is_string($v) ? str_replace(['https://' . $bare, 'http://' . $bare], ['{home}', '{home}'], $v) : $v;
$deep = static function ($v) use (&$deep, $rel) {
    return is_array($v) ? array_map($deep, $v) : $rel($v);
};
$uploads = (string) wp_upload_dir(null, false)['baseurl'];
$put = static fn (string $html): string => str_replace('{uploads}', $uploads, $html);

// --- The post allowlist's PDF object: type and data are both required, the
// type must be application/pdf, and the data must pass the callback.
$objects = [
    'local pdf' => '<object data="{uploads}/2026/10/doc.pdf" type="application/pdf"></object>',
    'local pdf upper type' => '<object data="{uploads}/doc.pdf" type="APPLICATION/PDF"></object>',
    'local pdf query' => '<object data="{uploads}/doc.pdf?x=1" type="application/pdf"></object>',
    'local pdf upper ext' => '<object data="{uploads}/doc.PDF" type="application/pdf"></object>',
    'local txt' => '<object data="{uploads}/doc.txt" type="application/pdf"></object>',
    'external pdf' => '<object data="https://example.com/doc.pdf" type="application/pdf"></object>',
    'root relative pdf' => '<object data="/wp-content/uploads/doc.pdf" type="application/pdf"></object>',
    'traversal pdf' => '<object data="{uploads}/../../wp-config.pdf" type="application/pdf"></object>',
    'javascript' => '<object data="javascript:alert(1)" type="application/pdf"></object>',
    'html type' => '<object data="{uploads}/doc.pdf" type="text/html"></object>',
    'no type' => '<object data="{uploads}/doc.pdf"></object>',
    'no data' => '<object type="application/pdf"></object>',
    'neither' => '<object class="x"></object>',
    'extra attrs' => '<object class="c" data="{uploads}/doc.pdf" type="application/pdf" width="600" height="400" title="t"></object>',
];
$out = [];
foreach ($objects as $name => $html) {
    $out[$name] = $deep(wp_kses_post($put($html)));
}
$say('object pdf rule', $out);
$callback = [];
$host = (string) parse_url($home, PHP_URL_HOST);
foreach (['{uploads}/doc.pdf', '{uploads}/a/b/doc.pdf', '{uploads}/doc.pdf#page=2', '{uploads}/doc.pdf?x=1', '{uploads}/doc.txt', 'https://example.com/doc.pdf', '/wp-content/uploads/doc.pdf', '{uploads}', '', '{home}/doc.pdf', 'http://{bare}/wp-content/uploads/doc.pdf',
    "https://{$host}/x?a=.pdf", "https://{$host}/x#.pdf", "https://{$host}.evil.test/d.pdf", "https://{$host}:8443/d.pdf", "https://www.{$host}/d.pdf", "https://user@{$host}/d.pdf",
    'https://' . strtoupper($host) . '/d.pdf', "HTTPS://{$host}/d.pdf", "//{$host}/d.pdf", "ftp://{$host}/d.pdf", "https://{$host}/d.PDF", "https://{$host}//d.pdf", "https://{$host}/.pdf", "https://{$host}/d.pdf ", "https://{$host}/d.pdf?"] as $url) {
    $url = str_replace(['{uploads}', '{home}', '{bare}'], [$uploads, $home, $bare], $url);
    $callback[] = [$rel($url), _wp_kses_allow_pdf_objects($url)];
}
$say('_wp_kses_allow_pdf_objects', $callback);

// --- The comment table takes only the attributes it lists: no global ones.
$globals = '<a href="https://x.test/" style="position:fixed;top:0" class="c" id="i" role="button" tabindex="1" data-x="1" aria-label="l" title="t" rel="nofollow">x</a> <b style="color:red" class="k">b</b> <blockquote cite="javascript:alert(1)" class="q">q</blockquote>';
$say('comment context', [wp_kses($globals, 'data'), wp_kses_data($globals)]);
$say('post context', wp_kses_post('<p aria-pressed="true" aria-label="l" data-x="1" onclick="x()" class="c">p</p>'));

// --- The valueless download attribute.
$downloads = [];
foreach (['<a download>x</a>', '<a download="file.txt">x</a>', '<a download="">x</a>', '<a download=download>x</a>', '<a href="/f" download>x</a>'] as $html) {
    $downloads[] = wp_kses_post($html);
}
$say('a download', $downloads);

// --- The protocols a caller passes, and the global list through its filter.
$links = '<a href="http://example.com/">h</a><a href="https://example.com/">s</a><a href="mailto:a@b.c">m</a><a href="skype:me">k</a><a href="/rel">r</a>';
$aOnly = ['a' => ['href' => true]];
$say('protocols default', wp_kses($links, $aOnly));
$say('protocols https only', wp_kses($links, $aOnly, ['https']));
$say('protocols empty list', wp_kses($links, $aOnly, []));
// kses_allowed_protocols is not pinned here: the reference follows the filter
// only until wp_loaded starts, and the engine's probe runner never fires it.

// --- Every URI attribute, with an unsafe and a safe value, where a custom
// allowlist permits it.
$uriTags = [
    'form' => ['action'], 'object' => ['archive', 'classid', 'codebase', 'data'], 'body' => ['background'],
    'blockquote' => ['cite'], 'button' => ['formaction'], 'a' => ['href', 'ping'], 'command' => ['icon'],
    'img' => ['longdesc', 'src', 'usemap'], 'html' => ['manifest', 'xmlns'], 'video' => ['poster'], 'head' => ['profile'],
    'div' => ['data-href'],
];
$allowed = [];
foreach ($uriTags as $tag => $attributes) {
    $allowed[$tag] = array_fill_keys($attributes, true);
}
$uri = [];
foreach ($uriTags as $tag => $attributes) {
    foreach ($attributes as $attribute) {
        $uri["{$tag} {$attribute}"] = [
            wp_kses("<{$tag} {$attribute}=\"javascript:alert(1)\">x</{$tag}>", $allowed),
            wp_kses("<{$tag} {$attribute}=\"https://example.com/x\">x</{$tag}>", $allowed),
        ];
    }
}
$say('uri attributes', $uri);
$addDataHref = static fn ($attributes) => array_merge($attributes, ['data-href']);
add_filter('wp_kses_uri_attributes', $addDataHref);
$say('uri attributes filtered', wp_kses('<div data-href="javascript:alert(1)">x</div>', $allowed));
remove_filter('wp_kses_uri_attributes', $addDataHref);

// --- Value rules in a caller's own allowlist.
$rules = [
    'values' => [['input' => ['type' => ['values' => ['text', 'email']]]], ['<input type="text">', '<input type="EMAIL">', '<input type="password">', '<input type="">', '<input type>']],
    'value_callback' => [['span' => ['title' => ['value_callback' => 'is_numeric']]], ['<span title="12">x</span>', '<span title="abc">x</span>']],
    'required' => [['span' => ['title' => ['required' => true], 'class' => true]], ['<span title="t" class="c">x</span>', '<span class="c">x</span>', '<span>x</span>']],
    'required false' => [['span' => ['title' => ['required' => false]]], ['<span>x</span>']],
    'valueless y' => [['input' => ['checked' => ['valueless' => 'y']]], ['<input checked>', '<input checked="checked">', '<input checked="">']],
    'valueless n' => [['input' => ['value' => ['valueless' => 'n']]], ['<input value>', '<input value="v">']],
    'maxlen' => [['span' => ['title' => ['maxlen' => 3]]], ['<span title="abc">x</span>', '<span title="abcd">x</span>']],
    'minlen' => [['span' => ['title' => ['minlen' => 3]]], ['<span title="ab">x</span>', '<span title="abc">x</span>']],
    'maxval' => [['input' => ['size' => ['maxval' => 10]]], ['<input size="10">', '<input size="11">', '<input size="x">', '<input size="-1">']],
    'minval' => [['input' => ['size' => ['minval' => 2]]], ['<input size="1">', '<input size="2">', '<input size="x">']],
    'unknown rule' => [['span' => ['title' => ['bogus' => 1]]], ['<span title="t">x</span>']],
    'false spec' => [['span' => ['title' => false]], ['<span title="t">x</span>']],
    'two rules' => [['span' => ['title' => ['maxlen' => 3, 'minlen' => 2]]], ['<span title="a">x</span>', '<span title="ab">x</span>', '<span title="abcd">x</span>']],
];
foreach ($rules as $name => [$spec, $inputs]) {
    $say("rule {$name}", array_map(static fn (string $html): string => wp_kses($html, $spec), $inputs));
}

// --- wp_kses_check_attr_val on its own.
$checks = [
    ['abc', 'n', 'maxlen', 3], ['abcd', 'n', 'maxlen', 3], ['ab', 'n', 'minlen', 3], ['5', 'n', 'maxval', 4],
    ['3', 'n', 'minval', 4], ['x', 'y', 'valueless', 'y'], ['x', 'n', 'valueless', 'y'], ['x', 'y', 'valueless', 'n'],
    ['x', 'n', 'values', ['x', 'y']], ['X', 'n', 'values', ['x']], ['z', 'n', 'values', ['x']], ['1', 'n', 'value_callback', 'is_numeric'],
    ['a', 'n', 'value_callback', 'is_numeric'], ['a', 'n', 'nonsense', 1], ['a', 'n', 'MAXLEN', 0],
];
$say('wp_kses_check_attr_val', array_map(static fn (array $c): bool => wp_kses_check_attr_val(...$c), $checks));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
