<?php
/**
 * The HTTP classes (probe http-classes): WP_Http's legacy static helpers
 * (raw responses and headers parsed, chunked bodies decoded, cookie headers
 * built, cookies normalised into a Requests jar, the redirect checks and
 * redirects followed, the first transport), WP_Http_Cookie (parsed, tested
 * against URLs, its header values) and the WP_HTTP_Response and
 * WP_HTTP_Requests_Response wrappers. No request leaves: redirects answer
 * through pre_http_request. Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$cookie = static fn ($c) => $c instanceof WP_Http_Cookie ? ['class' => get_class($c), 'name' => $c->name, 'value' => $c->value, 'expires' => $c->expires, 'path' => $c->path, 'domain' => $c->domain, 'port' => $c->port ?? null, 'host_only' => $c->host_only ?? null] : $c;
$headers = static function ($parsed) use ($cookie) {
    if (!is_array($parsed)) {
        return $parsed;
    }
    $parsed['cookies'] = array_map($cookie, $parsed['cookies'] ?? []);
    return $parsed;
};

$say('raw responses', [
    WP_Http::processResponse("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nX-A: b\r\n\r\nthe body\r\n\r\nmore"),
    WP_Http::processResponse("HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 201 Created\r\nX-B: c\r\n\r\nbody two"),
    WP_Http::processResponse('no separator at all'),
    WP_Http::processResponse(''),
]);
$say('headers parsed', [
    $headers(WP_Http::processHeaders("HTTP/1.1 404 Not Found\r\nContent-Type: text/html\r\nX-Multi: one\r\nX-Multi: two\r\nset-cookie: a=1; path=/x; domain=example.com\r\nSet-Cookie: b=2\r\n  folded: line\r\n", 'https://example.com/page')),
    $headers(WP_Http::processHeaders(['HTTP/1.0 302 Found', 'Location: /next', 'Bad header line'])),
    $headers(WP_Http::processHeaders("HTTP/1.1 301 Moved\r\nHTTP/1.1 200 OK\r\nX-Last: yes")),
    $headers(WP_Http::processHeaders('')),
]);
$say('chunked bodies', [
    WP_Http::chunkTransferDecode("4\r\nWiki\r\n5\r\npedia\r\n0\r\n\r\n"),
    WP_Http::chunkTransferDecode("4;ext=1\r\nWiki\r\n0\r\n\r\n"),
    WP_Http::chunkTransferDecode('not chunked at all'),
    WP_Http::chunkTransferDecode("A\r\n0123456789\r\n0\r\n"),
    WP_Http::chunkTransferDecode(''),
    WP_Http::chunkTransferDecode("4\r\nWiki\r\n0\r\n\r\n"),
    WP_Http::chunkTransferDecode("4\r\nWiki\r\n5\r\npedia\r\n"),
    WP_Http::chunkTransferDecode("4\r\nWiki"),
    WP_Http::chunkTransferDecode("0\r\n\r\n"),
    WP_Http::chunkTransferDecode("  4\r\nWiki\r\n0\r\n"),
    WP_Http::chunkTransferDecode("4\r\nWiki0"),
    WP_Http::chunkTransferDecode("4\r\nWikipedia\r\n0\r\n"),
    WP_Http::chunkTransferDecode("4\r\nWiki5\r\npedia\r\n0\r\n"),
    WP_Http::chunkTransferDecode("g\r\nWiki\r\n0\r\n"),
]);

$plain = new WP_Http_Cookie(['name' => 'zz', 'value' => 'v v', 'expires' => '2040-01-01 00:00:00', 'path' => '/blog', 'domain' => '.example.com', 'port' => '443,8443', 'host_only' => false]);
$header = new WP_Http_Cookie('session=abc%20d; Path=/; Domain=example.com; Expires=Wed, 01 Jan 2031 00:00:00 GMT; HttpOnly; Secure', 'https://www.example.com/a/b');
$hostOnly = new WP_Http_Cookie('solo=1', 'https://example.com/dir/page');
$say('cookies built', [$cookie($plain), $cookie($header), $cookie($hostOnly), $plain->getHeaderValue(), $header->getHeaderValue(), $plain->getFullHeader(), $plain->get_attributes(), $header->get_attributes(), $cookie(new WP_Http_Cookie('')), $cookie(new WP_Http_Cookie(['name' => 'n']))]);
$say('cookies tested', [
    'plain' => array_map([$plain, 'test'], ['https://www.example.com/blog/post', 'https://example.com/blog', 'https://www.example.com/other', 'http://www.example.com:8443/blog', 'https://www.example.com:9000/blog', 'https://evil.com/blog', 'https://notexample.com/blog', 'https://WWW.EXAMPLE.COM/blog', 'https://www.example.com/blogger', 'http://www.example.com/blog']),
    'header' => array_map([$header, 'test'], ['https://www.example.com/', 'https://example.com/x', 'https://sub.www.example.com/']),
    'host only' => array_map([$hostOnly, 'test'], ['https://example.com/dir/other', 'https://www.example.com/dir/', 'https://example.com/elsewhere']),
    'expired' => (new WP_Http_Cookie(['name' => 'old', 'value' => '1', 'expires' => time() - 60, 'domain' => 'example.com']))->test('https://example.com/'),
]);

$request = ['cookies' => ['plain' => 'one two', 'num' => 5, $plain], 'headers' => ['X-Keep' => 'kept']];
WP_Http::buildCookieHeader($request);
$empty = ['cookies' => []];
WP_Http::buildCookieHeader($empty);
$say('cookie headers built', [$request['headers'], array_keys($request), $empty]);
$jar = WP_Http::normalize_cookies(['plain' => 'one', 'num' => 5, 'obj' => $plain]);
$say('cookies normalised', [get_class($jar), array_map(static fn ($c) => [get_class($c), $c->name, $c->value], iterator_to_array($jar))]);

$responseFor = static function (int $status, string $method) {
    $response = new WpOrg\Requests\Response();
    $response->status_code = $status;
    $response->url = 'https://example.com/start';
    return $response;
};
$redirects = [];
foreach ([[301, 'POST'], [302, 'POST'], [303, 'POST'], [303, 'GET'], [307, 'POST'], [302, 'HEAD']] as [$status, $type]) {
    $options = ['type' => $type, 'zz' => 'kept'];
    $location = 'https://example.com/next';
    $headersIn = ['X' => 'y'];
    $data = ['a' => 1];
    WP_Http::browser_redirect_compatibility($location, $headersIn, $data, $options, $responseFor($status, $type));
    $redirects[] = [$status, $type, $options, $data];
}
$say('redirects, browser style', $redirects);
$validate = static function (string $location) {
    try {
        WP_Http::validate_redirects($location);
        return 'allowed';
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage(), method_exists($e, 'getType') ? $e->getType() : null];
    }
};
$say('redirects validated', array_map($validate, ['https://example.com/next', 'http://127.0.0.1/admin', 'ftp://example.com/', 'https://localhost/x', '/relative', 'javascript:alert(1)']));

$followed = [];
$answer = static function ($pre, $args, $url) use (&$followed) {
    $followed[] = [$url, $args['method'] ?? null, $args['redirection'] ?? null];
    return ['headers' => [], 'body' => 'from ' . $url, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
};
add_filter('pre_http_request', $answer, 10, 3);
$result = static function ($r) {
    return is_wp_error($r) ? ['error', $r->get_error_codes(), $r->get_error_messages()] : (is_array($r) ? [$r['body'] ?? null, $r['response']['code'] ?? null] : $r);
};
$redirect = static fn (int $code, ?string $location) => ['headers' => $location === null ? [] : ['location' => $location], 'response' => ['code' => $code, 'message' => 'Moved'], 'body' => '', 'cookies' => []];
$say('redirects followed', [
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(301, 'https://example.com/b'))),
    $followed,
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'POST'], $redirect(302, '/relative/path'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'POST'], $redirect(307, 'https://example.com/c'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 0, '_redirection' => 3, 'method' => 'GET'], $redirect(301, 'https://example.com/b'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(200, 'https://example.com/b'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(301, null))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'HEAD'], $redirect(301, 'https://example.com/d'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 1, '_redirection' => 3, 'method' => 'GET'], ['headers' => ['location' => ['https://example.com/x', 'https://example.com/y']], 'response' => ['code' => 301, 'message' => 'Moved'], 'body' => '', 'cookies' => []])),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(302, 'https://example.com/a'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(399, 'https://example.com/e'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'GET'], $redirect(300, 'https://example.com/f'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'POST'], $redirect(301, 'https://example.com/g'))),
    $result(WP_Http::handle_redirects('https://example.com/a', ['redirection' => 3, '_redirection' => 3, 'method' => 'POST'], $redirect(303, 'https://example.com/h'))),
    $followed,
]);
remove_filter('pre_http_request', $answer, 10);

$http = new WP_Http();
$say('first transport', [$http->_get_first_available_transport([]), $http->_get_first_available_transport(['ssl' => true], 'https://example.com/'), $http->_get_first_available_transport([], 'http://example.com/')]);

$wrapped = new WP_HTTP_Response(['a' => 1], 201, ['X-One' => '1']);
$wrapped->header('X-Two', '2');
$wrapped->header('X-One', 'again', false);
$wrapped->set_status(202);
$say('response wrapper', [$wrapped->get_data(), $wrapped->get_status(), $wrapped->get_headers(), json_encode($wrapped)]);
$requests = new WpOrg\Requests\Response();
$requests->status_code = 404;
$requests->body = 'nothing here';
$requests->headers = new WpOrg\Requests\Response\Headers(['content-type' => 'text/plain', 'x-many' => 'a']);
$requests->headers['x-many'] = 'b';
$requests->cookies = new WpOrg\Requests\Cookie\Jar(['c' => new WpOrg\Requests\Cookie('c', 'v', ['path' => '/', 'domain' => 'example.com'])]);
$from = new WP_HTTP_Requests_Response($requests, '/tmp/zz-file');
$array = $from->to_array();
$array['cookies'] = array_map($cookie, $array['cookies']);
$array['headers'] = $array['headers'] instanceof WpOrg\Requests\Utility\CaseInsensitiveDictionary ? iterator_to_array($array['headers']) : $array['headers'];
$say('requests response wrapper', [
    get_class($from->get_response_object()),
    $from->get_status(),
    $from->get_data(),
    iterator_to_array($from->get_headers()),
    array_map($cookie, $from->get_cookies()),
    $array,
    (static function () use ($from) {
        $from->set_status(500);
        $from->set_data('changed');
        $from->header('X-New', 'n');
        $from->set_headers(['X-Only' => 'o']);
        return [$from->get_status(), $from->get_data(), iterator_to_array($from->get_headers())];
    })(),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
