<?php
/**
 * The legacy HTTP transports (probe http-transports): WP_Http_Curl,
 * WP_Http_Streams and WP_HTTP_Fsockopen sending to the test site itself.
 * A file the probe writes into uploads (GET, HEAD, HTTP/1.1, a byte limit,
 * streamed to a file, not waiting), the REST API's refusals for a body and
 * a method (with and without a Content-Length), a 404, the login redirect
 * kept and followed, the errors for a refused port, an unknown host, a TLS
 * handshake that fails and a certificate no bundle trusts, what each test()
 * answers and the filters it asks, the arguments the curl hook is handed,
 * the cookie header built from a request's cookies, WP_Http::handle_redirects
 * honouring _redirection, and the streams transport's certificate check.
 * PHP warnings the transports raise are swallowed on both stacks. The
 * probe's file is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$upload = wp_get_upload_dir();
$file = $upload['basedir'] . '/zz-transport-probe.txt';
$streamed = get_temp_dir() . 'zz-transport-probe-streamed.txt';
$unused = get_temp_dir() . 'zz-transport-probe-unused.txt';
register_shutdown_function(static function () use ($file, $streamed, $unused): void {
    foreach ([$file, $streamed, $unused] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
file_put_contents($file, str_repeat("zz transport probe line\n", 200));
$url = $upload['baseurl'] . '/zz-transport-probe.txt';
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$temp = get_temp_dir();
// The site's own certificate authority, so a verified request has something to trust.
$authority = getenv('HOME') . '/Library/Application Support/Caddy/pki/authorities/local/root.crt';
$authority = is_file($authority) ? $authority : '';

// Values that differ between runs or servers: the date, the file's stamps, the PHP behind the site.
$varies = ['date', 'last-modified', 'etag', 'x-powered-by', 'server'];
$mask = static function ($value) use (&$mask, $host, $temp) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($temp, '{temp}/', $value);
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    return (string) preg_replace('/after \d+ ms/', 'after {n} ms', $value);
};
$shape = static function ($response) use ($mask, $varies) {
    if ($response instanceof WP_Error) {
        return ['error' => $response->get_error_code(), 'message' => $mask($response->get_error_message()), 'data' => $response->get_error_data()];
    }
    if (!is_array($response)) {
        return ['not an array' => gettype($response)];
    }
    $headers = $response['headers'] ?? null;
    if (is_object($headers)) {
        $headers = ['object' => get_class($headers), 'names' => array_keys($headers->getAll())];
    } elseif (is_array($headers)) {
        foreach ($headers as $name => $value) {
            $headers[$name] = in_array($name, $varies, true) ? '{varies}' : $mask($value);
        }
    }
    $body = (string) ($response['body'] ?? '');
    $decoded = json_decode($body, true);
    return [
        'keys' => array_keys($response),
        'headers' => $headers,
        'response' => $response['response'] ?? null,
        'cookies' => array_map(static fn ($cookie) => is_object($cookie) ? [get_class($cookie), $cookie->name, $cookie->value] : $cookie, (array) ($response['cookies'] ?? [])),
        'filename' => array_key_exists('filename', $response) ? $mask($response['filename']) : 'none',
        // A JSON body by its code, the probe's file by its digest, any other page by whether it has one.
        'body' => is_array($decoded) && isset($decoded['code']) ? ['json code' => $decoded['code']] : (str_starts_with($body, 'zz transport probe') || strlen($body) <= 100 ? [strlen($body), md5($body)] : ['a page' => $body !== '']),
    ];
};
$args = static fn (array $over = []) => $over + ['method' => 'GET', 'timeout' => 5, 'redirection' => 5, '_redirection' => 5, 'httpversion' => '1.0', 'user-agent' => 'zz-probe', 'blocking' => true, 'headers' => [], 'cookies' => [], 'body' => null, 'compress' => false, 'decompress' => true, 'sslverify' => false, 'sslcertificates' => '', 'stream' => false, 'filename' => null, 'limit_response_size' => null];
// The transports raise PHP warnings (a socket that will not open, a key a caller left out); neither stack prints them here.
set_error_handler(static fn (int $level) => in_array($level, [E_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true));
$try = static function (Closure $run) use ($shape) {
    try {
        return $shape($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};

$say('the classes', [get_parent_class('WP_HTTP_Fsockopen'), is_subclass_of('WP_HTTP_Fsockopen', 'WP_Http_Streams'), get_parent_class('WP_Http_Curl'), get_parent_class('WP_Http_Streams')]);
$asked = [];
$listen = static function (string $hook) use (&$asked) {
    $callback = static function ($value, $args = null) use (&$asked, $hook) {
        $asked[] = [$hook, $value, $args];
        return $value;
    };
    add_filter($hook, $callback, 10, 2);
    return $callback;
};
$curlAsked = $listen('use_curl_transport');
$streamsAsked = $listen('use_streams_transport');
$say('tests', [
    'curl' => [WP_Http_Curl::test(), WP_Http_Curl::test(['ssl' => true]), WP_Http_Curl::test(['ssl' => false, 'zz' => 1])],
    'streams' => [WP_Http_Streams::test(), WP_Http_Streams::test(['ssl' => true]), WP_Http_Streams::test(['ssl' => false])],
    'fsockopen' => [WP_HTTP_Fsockopen::test(), WP_HTTP_Fsockopen::test(['ssl' => true])],
]);
$say('the filters tests ask', $asked);
remove_filter('use_curl_transport', $curlAsked, 10);
remove_filter('use_streams_transport', $streamsAsked, 10);
add_filter('use_curl_transport', '__return_false');
add_filter('use_streams_transport', '__return_false');
$say('tests refused by filters', [WP_Http_Curl::test(), WP_Http_Streams::test(), WP_HTTP_Fsockopen::test(['ssl' => true])]);
remove_filter('use_curl_transport', '__return_false');
remove_filter('use_streams_transport', '__return_false');

$transports = ['curl' => new WP_Http_Curl(), 'streams' => new WP_Http_Streams(), 'fsockopen' => new WP_HTTP_Fsockopen()];
$json = ['Content-Type' => 'application/json'];
$cases = [
    'the file' => [$url, []],
    'a byte limit' => [$url, ['limit_response_size' => 100]],
    'not waiting' => [$url, ['blocking' => false]],
    'a filename without streaming' => [$url, ['filename' => $unused]],
    'a JSON body, no length' => [home_url('/wp-json/wp/v2/posts'), ['method' => 'POST', 'headers' => $json, 'body' => '{bad']],
    'a JSON body with its length' => [home_url('/wp-json/wp/v2/posts'), ['method' => 'POST', 'headers' => $json + ['Content-Length' => 4], 'body' => '{bad']],
    'an empty object' => [home_url('/wp-json/wp/v2/posts'), ['method' => 'POST', 'headers' => $json + ['Content-Length' => 2], 'body' => '{}']],
    'a PUT' => [home_url('/wp-json/wp/v2/posts/1'), ['method' => 'PUT', 'headers' => $json + ['Content-Length' => 4], 'body' => '{bad']],
    'a DELETE' => [home_url('/wp-json/wp/v2/posts/1'), ['method' => 'DELETE']],
    'a missing page' => [home_url('/zz-transport-probe-none/'), []],
    'a redirect kept' => [home_url('/wp-login.php'), ['redirection' => 0, '_redirection' => 0]],
    'a redirect followed' => [home_url('/wp-login.php'), []],
    'no redirects, _redirection left out' => [home_url('/wp-login.php'), ['redirection' => 0, '_redirection' => null]],
    'plain http' => [str_replace('https://', 'http://', $url), ['redirection' => 0, '_redirection' => 0]],
    'a refused port' => ['http://127.0.0.1:1/', []],
    'an unknown host' => ['https://zz-transport-probe.invalid/', []],
    'TLS to a plain port' => ['https://' . $host . ':80/', []],
    'TLS to localhost' => ['https://localhost/', []],
    'a bundle without the site' => [$url, ['sslverify' => true, 'sslcertificates' => '/etc/ssl/cert.pem']],
];
if ($authority !== '') {
    $cases['the site\'s authority'] = [$url, ['sslverify' => true, 'sslcertificates' => $authority]];
}
foreach (['curl', 'streams'] as $name) {
    $sent = [];
    foreach ($cases as $label => [$to, $over]) {
        $given = $args($over);
        if (array_key_exists('_redirection', $over) && $over['_redirection'] === null) {
            unset($given['_redirection']);
        }
        $sent[$label] = $try(static fn () => $transports[$name]->request($to, $given));
    }
    $say("{$name} requests", $sent);
}
$say('curl only', [
    'a HEAD' => $try(static fn () => $transports['curl']->request($url, $args(['method' => 'HEAD']))),
    'HTTP/1.1' => $try(static fn () => $transports['curl']->request($url, $args(['httpversion' => '1.1']))),
    'an empty bundle' => $try(static fn () => $transports['curl']->request($url, $args(['sslverify' => true, 'sslcertificates' => '']))),
]);
// The streams transport reads to the end, so HTTP/1.1 waits on a kept-alive connection unless the caller closes it.
$say('streams over HTTP/1.1, closed', $try(static fn () => $transports['streams']->request($url, $args(['httpversion' => '1.1', 'headers' => ['Connection' => 'close']]))));
$say('fsockopen', [$try(static fn () => $transports['fsockopen']->request($url, $args())), $try(static fn () => $transports['fsockopen']->request('http://127.0.0.1:1/', $args()))]);

$say('streamed where no file can be made', [$try(static fn () => $transports['curl']->request($url, $args(['stream' => true, 'filename' => '/zz-transport-probe-none/streamed.txt']))), $try(static fn () => $transports['streams']->request($url, $args(['stream' => true, 'filename' => '/zz-transport-probe-none/streamed.txt'])))]);
$say('streamed to a file', array_map(static function ($transport) use ($try, $url, $args, $streamed, $mask) {
    $result = $try(static fn () => $transport->request($url, $args(['stream' => true, 'filename' => $streamed])));
    $written = is_file($streamed) ? [filesize($streamed), md5_file($streamed)] : null;
    if (is_file($streamed)) {
        unlink($streamed);
    }
    return [$result, $written];
}, ['curl' => $transports['curl'], 'streams' => $transports['streams']]));

$heard = [];
$hear = static function (string $hook, int $count) use (&$heard, $mask) {
    $callback = static function (...$given) use (&$heard, $hook, $mask) {
        $heard[] = [$hook, $mask(array_map(static fn ($value) => is_object($value) ? ($value instanceof WP_Http_Cookie ? ['cookie', $value->name, $value->value] : get_class($value)) : (is_array($value) ? array_map(static fn ($item) => $item instanceof WP_Http_Cookie ? ['cookie', $item->name, $item->value] : $item, $value) : $value), $given))];
        return $given[0];
    };
    add_filter($hook, $callback, 10, $count);
    return $callback;
};
$hooks = ['https_ssl_verify' => $hear('https_ssl_verify', 2), 'http_api_curl' => $hear('http_api_curl', 3), 'wp_http_cookie_value' => $hear('wp_http_cookie_value', 2)];
$cookies = ['zz' => 'one two', 'num' => 5, new WP_Http_Cookie(['name' => 'zz-object', 'value' => 'w'])];
foreach (['curl', 'streams'] as $name) {
    $transports[$name]->request(home_url('/wp-json/'), $args(['cookies' => $cookies, 'headers' => ['X-Zz' => '1']]));
    $transports[$name]->request(str_replace('https://', 'http://', home_url('/wp-json/')), $args(['redirection' => 0, '_redirection' => 0]));
}
// What a caller leaves out is filled in, in the transport's own order.
$transports['curl']->request($url, ['sslverify' => false, 'sslcertificates' => '', 'user-agent' => 'zz-probe', 'compress' => false, 'limit_response_size' => null, '_redirection' => 0]);
$say('hooks a request runs', $heard);
foreach ($hooks as $hook => $callback) {
    remove_filter($hook, $callback, 10);
}
$say('streams with only some arguments', $try(static fn () => $transports['streams']->request($url, ['sslverify' => false, 'sslcertificates' => '', 'user-agent' => 'zz-probe', 'compress' => false, 'limit_response_size' => null, '_redirection' => 0])));

$changed = static fn ($value, $name) => $name === 'zz' ? 'changed' : $value;
add_filter('wp_http_cookie_value', $changed, 10, 2);
$request = ['cookies' => ['zz' => 'v', 'keep' => 'k', new WP_Http_Cookie(['name' => 'zz', 'value' => 'object'])], 'headers' => []];
WP_Http::buildCookieHeader($request);
remove_filter('wp_http_cookie_value', $changed, 10);
$say('a cookie header built', [$request['headers'], array_map(static fn ($cookie) => is_object($cookie) ? [get_class($cookie), $cookie->name, $cookie->value, $cookie->path] : $cookie, $request['cookies'])]);

$redirect = ['headers' => ['location' => 'https://example.com/b'], 'response' => ['code' => 301, 'message' => 'Moved'], 'body' => '', 'cookies' => []];
$answer = static fn ($pre, $args, $to) => ['headers' => [], 'body' => 'followed ' . $to . ' r' . $args['redirection'] . ' _r' . json_encode($args['_redirection'] ?? 'none'), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
add_filter('pre_http_request', $answer, 10, 3);
$handled = [];
foreach ([[0, 0], [2, 0], [0, 3], [2, 2], [3, 1], [1, null]] as [$left, $asked]) {
    $given = ['redirection' => $left, 'method' => 'GET'] + ($asked === null ? [] : ['_redirection' => $asked]);
    $result = WP_Http::handle_redirects('https://example.com/a', $given, $redirect);
    $handled["{$left}, " . json_encode($asked)] = $result instanceof WP_Error ? $result->get_error_message() : (is_array($result) ? $result['body'] : $result);
}
remove_filter('pre_http_request', $answer, 10);
$say('redirects and _redirection', $handled);
$handedOn = [];
$capture = static function ($pre, $args) use (&$handedOn) {
    $handedOn[] = [array_keys($args), $args['redirection'], $args['_redirection'] ?? 'none'];
    return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
};
add_filter('pre_http_request', $capture, 10, 2);
wp_remote_get('https://example.com/zz', ['redirection' => 3]);
wp_remote_get('https://example.com/zz', ['redirection' => 3, '_redirection' => 7]);
wp_remote_head('https://example.com/zz');
remove_filter('pre_http_request', $capture, 10);
$say('the arguments WP_Http::request hands on', $handedOn);
// WP_Http itself speaks the HTTP version asked for, and the server answers in kind.
$versions = [];
foreach (['1.0', '1.1', '2.0', '2'] as $version) {
    $response = wp_remote_get(home_url('/minn-admin/login'), ['httpversion' => $version, 'redirection' => 0, 'sslverify' => false, 'timeout' => 15]);
    $versions[$version] = $response instanceof WP_Error ? ['error' => $mask($response->get_error_message())] : [array_keys($response['headers']->getAll()), $response['response'], $response['http_response']->get_response_object()->protocol_version];
}
$say('WP_Http over each HTTP version', $versions);

$checked = [];
$context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
$secure = stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
if ($secure) {
    foreach (['the host' => $host, 'in capitals' => strtoupper($host), 'a subdomain' => 'zz.' . $host, 'another host' => 'zz-other.localhost', 'localhost' => 'localhost', 'an address' => '127.0.0.1', 'nothing' => ''] as $label => $name) {
        $checked[$label] = WP_Http_Streams::verify_ssl_certificate($secure, $name);
    }
    fclose($secure);
}
$uncaptured = stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'peer_name' => $host]]));
if ($uncaptured) {
    $checked['no certificate captured'] = WP_Http_Streams::verify_ssl_certificate($uncaptured, $host);
    fclose($uncaptured);
}
$plain = stream_socket_client('tcp://' . $host . ':80', $errno, $errstr, 15);
if ($plain) {
    $checked['a plain socket'] = WP_Http_Streams::verify_ssl_certificate($plain, $host);
    fclose($plain);
}
$say('certificates checked', $checked);
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
