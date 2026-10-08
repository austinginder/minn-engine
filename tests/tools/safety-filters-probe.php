<?php
/**
 * Two filters plugins use to loosen or tighten a safety check, as the
 * reference consults them (probe safety-filters): safe_style_css, which
 * names the CSS properties style attributes keep (the list it is handed,
 * and a property a plugin adds surviving safecss_filter_attr; what each
 * declaration may hold, case by case), with
 * safecss_filter_attr_allow_css for each declaration; and https_ssl_verify,
 * which an outgoing HTTPS request consults (whether it is asked, and a
 * plugin's false honoured). The request goes to a .invalid host, which
 * fails at lookup. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$handed = null;
$grab = static function ($properties) use (&$handed) {
    $handed ??= $properties;
    return $properties;
};
add_filter('safe_style_css', $grab);
$say('a style kept by default', safecss_filter_attr('color: red; zoom: 2; text-align: center'));
remove_filter('safe_style_css', $grab);
$say('the properties safe_style_css is handed', is_array($handed) ? array_values($handed) : $handed);
// What each declaration may hold: bare values, the functions a value may use, url()s and gradients by property, the characters that drop one.
$say('safecss_filter_attr, declaration by declaration', array_map(static fn (string $css) => [$css, safecss_filter_attr($css)], ['640px', '50%', '  640px  ', 'var:preset|spacing|50', 'url(x)', 'calc(100% - 2rem)', '50%;color:red', 'a:b', 'x', 'width:rgb(1,2,3)', 'width:a&b', 'width:min(1px, 2px)', 'width:a=b', 'width:a/*b', 'width:a}b', 'width:a{b', 'width:a<b', 'width:expression(x)', 'width:javascript', 'width:data:x', 'width:fit-content(10px)', 'width:attr(x)', 'width:rotate(1deg)', 'width:inset(rgb(1,2,3))', 'width:', 'width :1px', 'WIDTH:1px', 'width:1px !important', '--x:1px', '--x:a(b)', 'color:var(--a, rgb(1,2,3))', 'color:linear-gradient(red,blue)', 'transform:rotate(1deg) scale(2)', 'transform:a&b', 'clip-path:polygon(0 0, 1px 1px)', 'clip-path:url(#c)', 'grid-template-columns:repeat(2, minmax(0, 1fr))', 'background:red url(x.png) no-repeat', 'background:url(javascript:alert(1))', 'background-image:url(data:x)', 'background-image:url("https://x/y.png")', 'background-image:url(x.png) , url(y.png)', 'background-image:url(x)(y)', 'background-image:url(&quot;x&quot;)', 'background-image:url(/x?a=1&b=2)', 'background-image:linear-gradient(red, blue)', 'background-image:url(x.png), linear-gradient(red,blue)', 'list-style:url(x.png)', 'width:url(x.png)', 'filter:url(#x)', 'filter:url(javascript:x)', 'filter:url(#a) url(#b)', 'filter:linear-gradient(red,blue)', 'cursor:url(x.cur), auto', 'mask:url(#m)']));
add_filter('safe_style_css', static fn ($properties) => [...(array) $properties, 'zoom']);
$say('a property a plugin adds', safecss_filter_attr('color: red; zoom: 2; text-align: center'));
$asked = [];
add_filter('safecss_filter_attr_allow_css', static function ($allow, $declaration) use (&$asked) {
    $asked[] = [$allow, $declaration];
    return str_starts_with((string) $declaration, 'text-align') ? false : $allow;
}, 10, 2);
$say('a declaration a plugin refuses', safecss_filter_attr('color: red; text-align: center'));
$say('the declarations it was asked about', $asked);

$verify = [];
add_filter('https_ssl_verify', static function ($value, $url) use (&$verify) {
    $verify[] = [$value === false ? false : 'verifying', $url];
    return false;
}, 10, 2);
$result = wp_remote_get('https://zz-ssl.invalid/probe', ['timeout' => 3]);
$say('an https request: what https_ssl_verify is asked', $verify);
$say('an https request: its answer', is_wp_error($result) ? $result->get_error_code() : 'a response');
$verify = [];
wp_remote_get('https://zz-ssl.invalid/probe', ['timeout' => 3, 'sslverify' => false]);
$say('an https request without verification: what https_ssl_verify is asked', $verify);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
