<?php
/**
 * The Requests library plugins call (WpOrg\Requests\* and the deprecated
 * Requests_* names): the parts that need no network (IDNA, IPv6, cookies,
 * exceptions, headers, IRIs, hooks, argument checks, decompression), then,
 * when MINN_REQUESTS_BASE names the staged echo endpoint
 * (tests/fixtures/requests/echo.php), real requests: methods, headers,
 * bodies, redirects, cookies, errors, hooks, sessions, request_multiple.
 * Same protocol as api-probe.php.
 */

use WpOrg\Requests\Cookie;
use WpOrg\Requests\Exception as RequestsException;
use WpOrg\Requests\Hooks;
use WpOrg\Requests\IdnaEncoder;
use WpOrg\Requests\Ipv6;
use WpOrg\Requests\Iri;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Response;
use WpOrg\Requests\Response\Headers;

// The argument errors name their caller; a named function reads the same on any stack.
function minn_requests_probe_complain(string $which)
{
    return $which === 'invalid' ? WpOrg\Requests\Exception\InvalidArgument::create(1, '$url', 'string|Stringable', 'array') : WpOrg\Requests\Exception\ArgumentCount::create('an array with two elements', 1, 'proxy.http.args');
}

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// Cookie flags carry the moment they were made; within a minute of now reads "now".
$flags = static fn (array $f): array => array_map(static fn ($v) => is_int($v) && abs($v - time()) < 60 ? 'now' : $v, $f);
$try = static function (callable $fn) {
    try {
        return ['ok', $fn()];
    } catch (Throwable $e) {
        return ['throws', get_class($e), $e->getMessage(), $e->getCode(), $e instanceof RequestsException ? $e->getType() : null];
    }
};

$say('idna', array_map(static fn ($host) => $try(static fn () => IdnaEncoder::encode($host)), ['bücher.example', 'example.com', 'ÄÖÜ.de', 'xn--bcher-kva.example', 'mañana.com', '例え.テスト', 'a.b.c', str_repeat('ü', 70) . '.com', '']));
$say('idna to_ascii', array_map(static fn ($label) => $try(static fn () => IdnaEncoder::to_ascii($label)), ['bücher', 'plain', 'Bücher']));
$say('ipv6 check', array_map(static fn ($ip) => Ipv6::check_ipv6($ip), ['::1', '2001:db8::1', '2001:0db8:0000:0000:0000:0000:0000:0001', '::ffff:192.0.2.128', 'fe80::1%eth0', '1.2.3.4', 'gggg::1', '1:2:3:4:5:6:7:8:9', '']));
$say('ipv6 compress', array_map(static fn ($ip) => Ipv6::compress($ip), ['2001:0db8:0000:0000:0000:0000:0000:0001', '0:0:0:0:0:0:0:1', '2001:db8:0:0:1:0:0:1', '2001:db8::1', '::ffff:192.0.2.128']));
$say('ipv6 uncompress', array_map(static fn ($ip) => Ipv6::uncompress($ip), ['2001:db8::1', '::1', '::', '1::', '::ffff:192.0.2.128']));

$cookie = Cookie::parse('session=abc 123; Path=/app; Domain=.Example.com; Expires=Wed, 21 Oct 2037 07:28:00 GMT; Max-Age=3600; Secure; HttpOnly; SameSite=Lax', '', 1790000000);
$say('cookie parse', [$cookie->name, $cookie->value, (array) $cookie->attributes->getAll(), $flags($cookie->flags), $cookie->reference_time, (string) $cookie, $cookie->format_for_header(), $cookie->format_for_set_cookie(), $cookie->is_expired()]);
$say('cookie match', [$cookie->domain_matches('example.com'), $cookie->domain_matches('www.example.com'), $cookie->domain_matches('notexample.com'), $cookie->path_matches('/app'), $cookie->path_matches('/app/x'), $cookie->path_matches('/application'), $cookie->path_matches('/'), $cookie->uri_matches(new Iri('https://www.example.com/app/x')), $cookie->uri_matches(new Iri('http://www.example.com/app/x'))]);
$plain = Cookie::parse('plain=1', 'override', 1790000000);
$say('cookie plain', [$plain->name, $plain->value, $plain->format_for_header(), $plain->format_for_set_cookie(), $plain->normalize(), (array) $plain->attributes->getAll(), $flags($plain->flags)]);
$expired = new Cookie('old', 'x', ['expires' => 1000], [], 1790000000);
$say('cookie expired', [$expired->is_expired(), (new Cookie('n', 'v', ['max-age' => -1], [], 1790000000))->is_expired()]);
$headers = new Headers();
$headers['Set-Cookie'] = 'a=1; Path=/';
$headers['Set-Cookie'] = 'b=2';
$parsed = Cookie::parse_from_headers($headers, new Iri('https://example.com/dir/page'), 1790000000);
$say('cookie from headers', array_map(static fn ($c) => [$c->name, $c->value, (array) $c->attributes->getAll(), $flags($c->flags)], $parsed));
$normalized = Cookie::parse('n=v; Domain=.Example.COM; Path=/x; Max-Age=60; Expires=Wed, 21 Oct 2037 07:28:00 GMT', '', 1790000000);
$say('cookie normalize', [$normalized->normalize(), (array) $normalized->attributes->getAll(), $normalized->domain_matches('example.com'), $normalized->domain_matches('sub.example.com'), $normalized->domain_matches('192.168.0.1')]);
$hostOnly = new Cookie('h', 'v', ['domain' => 'example.com'], ['host-only' => false], 1790000000);
$say('cookie domain rules', [$hostOnly->domain_matches('example.com'), $hostOnly->domain_matches('a.example.com'), $hostOnly->domain_matches('aexample.com'), $hostOnly->domain_matches('127.0.0.1'), (new Cookie('h', 'v', ['domain' => '127.0.0.1'], ['host-only' => false]))->domain_matches('127.0.0.1')]);
$jar = new WpOrg\Requests\Cookie\Jar(['a' => '1', 'b' => new Cookie('b', '2')]);
$describe = static fn ($v) => is_object($v) ? [get_class($v), $v->name ?? null, $v->value ?? null] : $v;
$say('cookie jar', [isset($jar['a']), $describe($jar['a']), $describe($jar['b']), array_map($describe, iterator_to_array($jar->getIterator())), $jar['missing'] ?? 'unset', $describe($jar->normalize_cookie('raw', 'name'))]);

$h = new Headers(['Content-Type' => 'text/html', 'X-Multi' => 'one']);
$h['x-multi'] = 'two';
$say('headers', [$h['content-type'], $h['X-MULTI'], $h->getValues('x-multi'), $h->getValues('missing'), $h['missing'], $h->flatten(['a', 'b']), $h->flatten('s'), iterator_to_array($h->getIterator()), $h->getAll()]);
$say('dictionary', $try(static function () {
    $d = new WpOrg\Requests\Utility\CaseInsensitiveDictionary(['A' => 1]);
    $d[] = 2;
    return $d->getAll();
}));

$response = new Response();
$say('response defaults', [$response->body, $response->raw, get_class($response->headers), $response->status_code, $response->protocol_version, $response->success, $response->redirects, $response->url, $response->history, get_class($response->cookies), $response->is_redirect()]);
foreach ([200, 301, 304, 404, 418, 503, 599, 99] as $code) {
    $r = new Response();
    $r->status_code = $code;
    $r->success = $code >= 200 && $code < 300;
    $say("throw_for_status {$code}", [$r->is_redirect(), $try(static fn () => $r->throw_for_status()), $try(static fn () => $r->throw_for_status(false))]);
}
$r = new Response();
$r->body = '{"a":1,"b":[1,2]}';
$bad = new Response();
$bad->body = '{nope';
$say('decode_body', [$r->decode_body(), $r->decode_body(false), $try(static fn () => $bad->decode_body())]);

$say('exceptions', [
    $try(static fn () => throw new RequestsException('msg', 'type.x', ['d' => 1], 7)),
    (static function () { $e = new WpOrg\Requests\Exception\Http\Status404('Gone away', ['x']); return [$e->getMessage(), $e->getCode(), $e->getReason(), $e->getType(), $e->getData()]; })(),
    (static function () { $e = new WpOrg\Requests\Exception\Http\Status403(); return [$e->getMessage(), $e->getCode(), $e->getReason(), $e->getType()]; })(),
    (static function () { $r = new Response(); $r->status_code = 599; $e = new WpOrg\Requests\Exception\Http\StatusUnknown(null, $r); return [$e->getMessage(), $e->getCode(), $e->getReason()]; })(),
    (static function () { $e = new WpOrg\Requests\Exception\Http\StatusUnknown('Why', 'not a response'); return [$e->getMessage(), $e->getCode(), $e->getReason()]; })(),
    array_map(static fn ($code) => WpOrg\Requests\Exception\Http::get_class($code), [404, 418, 599, '500', 'x']),
    (static function () { $e = minn_requests_probe_complain('invalid'); return [get_class($e), $e->getMessage()]; })(),
    (static function () { $e = minn_requests_probe_complain('count'); return [get_class($e), $e->getMessage(), $e->getType()]; })(),
    (static function () { $e = new WpOrg\Requests\Exception\Transport\Curl('Could not resolve host', WpOrg\Requests\Exception\Transport\Curl::EASY, null, 6); return [$e->getMessage(), $e->getCode(), $e->getType(), $e->getReason()]; })(),
]);

$say('argument checks', [
    $try(static fn () => Requests::request(['x'])),
    $try(static fn () => Requests::request('http://x.test/', 'nope')),
    $try(static fn () => Requests::request('http://x.test/', [], [], 'GET', 'nope')),
    $try(static fn () => Requests::request('http://x.test/', [], [], 5)),
    $try(static fn () => Requests::request_multiple('nope')),
    $try(static fn () => Requests::request('ftp://x.test/', [], [], 'GET', ['transport' => null])),
    $try(static fn () => Requests::get('not a url at all')),
]);

$iri = new Iri('HTTP://User:Pw@Example.COM:80/a/./b/../c?q=1#f');
$say('iri', [(string) $iri, $iri->scheme, $iri->host, $iri->port, $iri->path, $iri->query, $iri->fragment, $iri->userinfo, $iri->iri, $iri->uri, $iri->ihost, $iri->is_valid(), isset($iri->host), $iri->nothing ?? 'null']);
$say('iri absolutize', array_map(static fn ($pair) => (string) Iri::absolutize($pair[0], $pair[1]), [['http://a/b/c/d;p?q', '../../g'], ['http://a/b/c/d;p?q', '?y'], ['http://a/b/c/d;p?q', 'g#s'], ['https://x.test/dir/', 'é ü']]));
$say('iri invalid', [Iri::absolutize('not a base', 'x'), (string) new Iri('relative/path'), (new Iri('relative/path'))->is_valid()]);
$say('port', [WpOrg\Requests\Port::get('http'), WpOrg\Requests\Port::get('HTTPS'), $try(static fn () => WpOrg\Requests\Port::get('gopher')), $try(static fn () => WpOrg\Requests\Port::get(80))]);
$say('ssl', [WpOrg\Requests\Ssl::match_domain('www.example.com', '*.example.com'), WpOrg\Requests\Ssl::match_domain('a.b.example.com', '*.example.com'), WpOrg\Requests\Ssl::match_domain('example.com', 'example.com'), WpOrg\Requests\Ssl::match_domain('192.168.0.1', '192.168.0.1'), WpOrg\Requests\Ssl::verify_reference_name('*.example.com'), WpOrg\Requests\Ssl::verify_reference_name('*.com'), WpOrg\Requests\Ssl::verify_reference_name('w*.example.com'), WpOrg\Requests\Ssl::verify_certificate('www.example.com', ['subject' => ['CN' => 'www.example.com']]), WpOrg\Requests\Ssl::verify_certificate('www.example.com', ['extensions' => ['subjectAltName' => 'DNS: *.example.com, DNS: example.com']])]);

$order = [];
$hooks = new Hooks();
$hooks->register('evt', static function ($a, &$b) use (&$order) { $order[] = "p0 {$a}"; $b .= '+0'; });
$hooks->register('evt', static function ($a, &$b) use (&$order) { $order[] = "p-5 {$a}"; $b .= '+(-5)'; }, -5);
$hooks->register('evt', static function ($a, &$b) use (&$order) { $order[] = "p10 {$a}"; $b .= '+10'; return false; }, 10);
$value = 'v';
$say('hooks', [$hooks->dispatch('evt', ['x', &$value]), $order, $value, $hooks->dispatch('none'), $try(static fn () => $hooks->register(5, 'strlen')), $try(static fn () => $hooks->dispatch('evt', 'nope'))]);

$gz = gzencode('hello gzip');
$say('decompress', [Requests::decompress($gz), Requests::decompress(gzdeflate('raw deflate')), Requests::decompress(gzcompress('zlib')), Requests::decompress('plain text'), Requests::compatible_gzinflate($gz), Requests::flatten(['A' => 'b', 'C' => ['d', 'e']])]);
$say('capabilities', [Requests::has_capabilities(), Requests::has_capabilities(['ssl' => true]), Requests::VERSION, Requests::OPTION_DEFAULTS, basename((string) Requests::get_certificate_path())]);
$say('validator', [WpOrg\Requests\Utility\InputValidator::is_string_or_stringable(new Iri('http://x')), WpOrg\Requests\Utility\InputValidator::is_numeric_array_key('5'), WpOrg\Requests\Utility\InputValidator::is_numeric_array_key(1.5), WpOrg\Requests\Utility\InputValidator::has_array_access(new ArrayObject()), WpOrg\Requests\Utility\InputValidator::is_iterable(new ArrayIterator([])), WpOrg\Requests\Utility\InputValidator::is_curl_handle(curl_init())]);
$say('filtered iterator', iterator_to_array(new WpOrg\Requests\Utility\FilteredIterator(['a' => 1, 'b' => 2], static fn ($v) => $v * 10)));
$auth = new WpOrg\Requests\Auth\Basic(['user', 'pass']);
$say('auth basic', [$auth->user, $auth->pass, $auth->getAuthString(), $try(static fn () => new WpOrg\Requests\Auth\Basic(['only'])), $try(static fn () => new WpOrg\Requests\Auth\Basic('string'))]);
$proxy = new WpOrg\Requests\Proxy\Http(['proxy.test:3128', 'u', 'p']);
$say('proxy http', [$proxy->proxy, $proxy->user, $proxy->pass, $proxy->use_authentication, $proxy->get_auth_string(), (new WpOrg\Requests\Proxy\Http('p.test:1'))->proxy, $try(static fn () => new WpOrg\Requests\Proxy\Http(['a', 'b']))]);
$session = new WpOrg\Requests\Session('https://api.example.test/v1/', ['X-A' => '1'], ['k' => 'v'], ['timeout' => 3]);
$session->useragent = 'minn-probe';
$say('session', [$session->url, $session->headers, $session->data, array_map(static fn ($v) => is_object($v) ? get_class($v) : $v, $session->options), isset($session->useragent), $session->useragent, $session->missing ?? 'null']);
$reasons = [];
foreach ([304, 305, 306, 400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 428, 429, 431, 500, 501, 502, 503, 504, 505, 511] as $code) {
    $class = 'WpOrg\\Requests\\Exception\\Http\\Status' . $code;
    $e = new $class();
    $reasons[$code] = [$e->getMessage(), $e->getCode()];
}
$say('status reasons', $reasons);
$legacy = [];
set_error_handler(static function ($no, $str) use (&$legacy) { $legacy[] = [$no, $str]; return true; });
$say('legacy names', [class_exists('Requests'), get_parent_class('Requests'), class_exists('Requests_Exception'), class_exists('Requests_Response'), (new ReflectionClass('Requests_Exception_HTTP_404'))->getName(), class_exists('Requests_Cookie_Jar')]);
restore_error_handler();
$say('legacy deprecation', $legacy);

$base = getenv('MINN_REQUESTS_BASE');
if (is_string($base) && $base !== '') {
    require __DIR__ . '/requests-probe-network.php';
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
