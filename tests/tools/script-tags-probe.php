<?php
/**
 * The script tags an enqueued script prints as, as the reference prints
 * them (probe script-tags): a plain script, deferred and async ones, one
 * with localized data and inline code before and after, one in the
 * footer group; what wp_script_attributes and wp_inline_script_attributes
 * are handed for each tag and what script_loader_tag is given; a plugin
 * adding an attribute to every tag and a nonce to every inline one (as
 * CSP plugins do); and the tag helpers called directly. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
add_filter('wp_script_attributes', static function ($attributes) use (&$heard) {
    $heard[] = ['wp_script_attributes', $attributes];
    return $attributes;
});
add_filter('wp_inline_script_attributes', static function ($attributes, $data) use (&$heard) {
    $heard[] = ['wp_inline_script_attributes', $attributes, strlen((string) $data)];
    return $attributes;
}, 10, 2);
add_filter('script_loader_tag', static function ($tag, $handle, $src) use (&$heard) {
    $heard[] = ['script_loader_tag', $handle, $src];
    return $tag;
}, 10, 3);

wp_register_script('zz-plain', 'https://zz.example/plain.js', [], '1');
wp_register_script('zz-defer', 'https://zz.example/defer.js', [], '1', ['strategy' => 'defer']);
wp_register_script('zz-async', 'https://zz.example/async.js', [], '1', ['strategy' => 'async']);
wp_register_script('zz-inline', 'https://zz.example/inline.js', [], '1');
wp_localize_script('zz-inline', 'zzData', ['a' => 1]);
wp_add_inline_script('zz-inline', 'var zzBefore = 1;', 'before');
wp_add_inline_script('zz-inline', 'var zzAfter = 1;');
wp_register_script('zz-nosrc', false, [], '1');
wp_add_inline_script('zz-nosrc', 'var zzOnly = 1;');
wp_register_script('zz-footer', 'https://zz.example/footer.js', [], '1', ['in_footer' => true, 'strategy' => 'defer']);
wp_register_script('zz-two', 'https://zz.example/two.js', ['zz-plain'], '1');
wp_add_inline_script('zz-two', 'var zzB1 = 1;', 'before');
wp_add_inline_script('zz-two', 'var zzB2 = 2;', 'before');
wp_add_inline_script('zz-two', 'var zzA1 = 1;');
wp_add_inline_script('zz-two', "  var zzA2 = '</script>';  ");
wp_localize_script('zz-two', 'zzOne', ['a' => '1']);
wp_localize_script('zz-two', 'zzTwo', ['b' => '2']);
$print = static function (string $handle) use (&$heard): array {
    $heard = [];
    ob_start();
    wp_print_scripts([$handle]);
    return [ob_get_clean(), $heard];
};
foreach (['zz-plain', 'zz-defer', 'zz-async', 'zz-inline', 'zz-nosrc', 'zz-footer', 'zz-two'] as $handle) {
    $say("{$handle}: printed, and what the filters heard", $print($handle));
}

add_filter('wp_script_attributes', static fn ($attributes) => $attributes + ['data-zz' => 'yes', 'nonce' => 'zz-nonce']);
add_filter('wp_inline_script_attributes', static fn ($attributes) => $attributes + ['nonce' => 'zz-nonce']);
foreach (['zz-plain', 'zz-inline'] as $handle) {
    wp_dequeue_script($handle);
    wp_scripts()->done = array_values(array_diff(wp_scripts()->done, [$handle]));
    $say("{$handle} again, with a plugin's attributes", $print($handle));
}

$heard = [];
$say('a tag built directly', [wp_get_script_tag(['src' => 'https://zz.example/d.js', 'async' => true, 'defer' => false, 'id' => 'zz-d', 'data-x' => '"q"']), $heard]);
$heard = [];
$say('an inline tag built directly', [wp_get_inline_script_tag("\n var zz = '</script>';\n", ['id' => 'zz-i', 'type' => 'module']), $heard]);
$say('attributes in any order and case, with awkward values', wp_get_script_tag(['src' => 'https://zz.example/o.js?a=1&b=2', 'b' => '1', 'a' => "it's <&amp;> \"x\"", 'Z' => '3', 'crossorigin' => true, 'data-n' => 5, 'data-null' => null, 'data-empty' => '']));
$say('the attributes alone', wp_sanitize_script_attributes(['src' => 'https://zz.example/s.js', 'async' => true, 'id' => 'zz-s', 'b' => 'x', 'type' => 'text/javascript', 'x' => false, 'y' => "a'b&amp;"]));
foreach (['a closing tag in capitals' => "var a = '</SCRIPT >';", 'a comment opener' => "var b = '<!--';", 'an opening tag' => "var c = '<script>';", 'a comment closer' => "var d = '-->';", 'a closing tag without its end' => "var e = '</script';"] as $what => $code) {
    $say("inline code with {$what}", wp_get_inline_script_tag($code));
}
$say('inline JSON with a closing tag', wp_get_inline_script_tag('{"a":"</script>","b":"<!--"}', ['type' => 'application/json']));
$say('an inline template with a closing tag', wp_get_inline_script_tag('<p></script></p>', ['type' => 'text/template']));
$say('URLs and entities in attributes', wp_get_script_tag(['src' => 'https://zz.example/a.js?x="1"&y=<2>&z=&#038;', 'href' => 'https://zz.example/?a=1&b=2', 'data-u' => 'https://zz.example/?a=1&b=2', 'data-e' => '&#038;&lt;']));
foreach (['application/ld+json', 'importmap', 'speculationrules', 'text/plain', 'TEXT/JAVASCRIPT', 'text/javascript; charset=utf-8', 'application/javascript', 'text/ecmascript', 'MODULE', ''] as $type) {
    $say("a closing tag in an inline script of type \"{$type}\"", [wp_get_inline_script_tag("var x = '</script>';", ['type' => $type]), wp_get_inline_script_tag('var ok = 1;', ['type' => $type])]);
}
$say('a harmless template', wp_get_inline_script_tag('<p>x</p>', ['type' => 'text/template']));
$say('a comment opener before an opening tag', wp_get_inline_script_tag("var f = '<!-- <script>';"));
$say('inline code with a tab, a slash and a newline after the tag name', wp_get_inline_script_tag("a('<script\t'); b('</script/'); c('</script\n'); d('<scripts>');"));
$html5 = get_theme_support('html5');
remove_theme_support('html5');
$say('the attributes alone without html5 script support', wp_sanitize_script_attributes(['type' => 'text/javascript', 'defer' => true, 'x' => false, 'y' => "a'b"]));
$say('a tag and an inline tag without html5 script support', [wp_get_script_tag(['src' => 'https://zz.example/t.js', 'id' => 'zz-t']), wp_get_inline_script_tag('var t = 1;', ['id' => 'zz-ti']), wp_get_inline_script_tag('var u = 1;', ['type' => 'module'])]);
if ($html5) {
    add_theme_support('html5', ...$html5);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
