<?php
/**
 * The network half of requests-probe.php, against the staged echo endpoint
 * at MINN_REQUESTS_BASE (tests/fixtures/requests/echo.php). Included by
 * the probe, which owns $say, $try and $flags.
 */

use WpOrg\Requests\Hooks;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Session;

$base = rtrim((string) getenv('MINN_REQUESTS_BASE'), '/');
$echo = "{$base}/echo.php";
$plain = static fn ($v) => is_string($v) ? str_replace($base, '{base}', $v) : $v;
// What the endpoint saw, without the parts that vary by server (host and port).
$seen = static function ($response) {
    $data = json_decode((string) $response->body, true);
    if (!is_array($data)) {
        return $response->body;
    }
    unset($data['headers']['host']);
    return $data;
};
$describe = static function ($r) use ($plain, $seen, $flags): array {
    $headers = [];
    foreach ($r->headers->getAll() as $name => $values) {
        if (!in_array($name, ['date', 'host'], true)) {
            // A cookie's Max-Age counts down from the moment it was set.
            $headers[$name] = array_map(static fn ($v) => (string) preg_replace('/Max-Age=\d+/', 'Max-Age=N', (string) $v), $values);
        }
    }
    return [
        'class' => get_class($r),
        'status' => $r->status_code,
        'protocol' => $r->protocol_version,
        'success' => $r->success,
        'redirects' => $r->redirects,
        'url' => $plain($r->url),
        'history' => array_map(static fn ($h) => [$h->status_code, $plain($h->url), $plain($h->headers['location'])], $r->history),
        'headers' => $headers,
        'raw starts' => substr((string) $r->raw, 0, 15),
        'raw has body' => str_ends_with((string) $r->raw, (string) $r->body),
        'cookies' => array_map(static fn ($c) => is_object($c) ? [$c->name, $c->value, (array) $c->attributes->getAll(), $flags($c->flags)] : $c, iterator_to_array($r->cookies->getIterator())),
        'seen' => $seen($r),
    ];
};

$say('request get', $describe(Requests::get("{$echo}?a=1", ['X-Probe' => 'yes', 'X-List' => ['a', 'b']])));
$say('request get with data', $describe(Requests::request($echo, [], ['b' => '2', 'arr' => ['x', 'y']], Requests::GET)));
$say('request post form', $describe(Requests::post($echo, [], ['name' => 'Ada Lovelace', 'list' => [1, 2], 'nested' => ['k' => 'v']])));
$say('request post string', $describe(Requests::post($echo, ['Content-Type' => 'application/json'], '{"json":true}')));
$say('request put patch delete head options', array_map(static fn ($type) => $describe(Requests::request($echo, [], ['p' => '1'], $type)), ['PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']));
$say('request redirects', $describe(Requests::get("{$echo}?redirect=2")));
$say('request redirect 301 post', $describe(Requests::post("{$echo}?redirect=1&code=301", [], ['p' => '1'])));
$say('request redirect 307 post', $describe(Requests::post("{$echo}?redirect=1&code=307", [], ['p' => '1'])));
$say('request redirect not followed', $describe(Requests::get("{$echo}?redirect=1", [], ['follow_redirects' => false])));
$say('request too many redirects', $try(static fn () => Requests::get("{$echo}?redirect=5", [], ['redirects' => 2])));
$say('request redirect elsewhere', $describe(Requests::get("{$echo}?to=" . rawurlencode("{$echo}?landed=1"))));
$say('request 404', [$describe($r404 = Requests::get("{$echo}?status=404")), $try(static fn () => $r404->throw_for_status())]);
$say('request cookies set', $describe(Requests::get("{$echo}?cookies=1")));
$say('request cookies sent', $describe(Requests::get($echo, [], ['cookies' => ['a' => '1', 'b' => 'two words']])));
$say('request auth', $describe(Requests::get($echo, [], ['auth' => ['user', 'pass']])));
$say('request useragent', $describe(Requests::get($echo, [], ['useragent' => 'minn-probe/1'])));
$say('request not blocking', (static function () use ($echo, $plain) {
    $r = Requests::get($echo, [], ['blocking' => false]);
    return [get_class($r), $r->status_code, $r->body, $r->success, $plain($r->url)];
})());
$file = sys_get_temp_dir() . '/minn-requests-probe-' . getmypid() . '.json';
$say('request to file', (static function () use ($echo, $file, $seen) {
    $r = Requests::get($echo, [], ['filename' => $file]);
    $saved = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (is_file($file)) {
        unlink($file);
    }
    return [$r->status_code, $r->body, is_array($saved) ? $saved['method'] : null];
})());
// curl reports how long it waited; the number varies run to run.
$timing = static fn (array $result): array => array_map(static fn ($v) => is_string($v) ? (string) preg_replace('/\d+ (ms|milliseconds)/', 'N $1', $v) : $v, $result);
$say('request refused', $timing($try(static fn () => Requests::get('http://127.0.0.1:1/', [], ['timeout' => 2]))));
$say('request timeout', $timing($try(static fn () => Requests::get("{$echo}?slow=3", [], ['timeout' => 1]))));

$events = [];
$hooks = new Hooks();
foreach (['requests.before_request', 'requests.before_parse', 'requests.after_request', 'requests.before_redirect_check', 'requests.before_redirect', 'requests.failed', 'curl.before_request', 'curl.before_send', 'curl.after_send', 'curl.after_request', 'fsockopen.before_request'] as $hook) {
    $hooks->register($hook, static function (...$args) use (&$events, $hook, $plain) {
        $first = $args[0] ?? null;
        // Raw responses carry the server's Date; keep the status line and the header names.
        if (is_string($first) && str_starts_with($first, 'HTTP/')) {
            $first = (string) preg_replace('/^Date: .*$/m', 'Date: (now)', explode("\r\n\r\n", $first, 2)[0]);
        }
        $events[] = [$hook, count($args), is_string($first) ? $plain($first) : get_debug_type($first)];
    });
}
$hooks->register('requests.before_request', static function ($url, &$headers) {
    $headers['X-Hooked'] = 'yes';
}, 5);
$hooked = Requests::get("{$echo}?redirect=1", [], ['hooks' => $hooks]);
$say('request hooks', [$events, $seen($hooked)['headers']['x-hooked'] ?? null]);

$responses = Requests::request_multiple([
    'one' => ['url' => "{$echo}?n=1"],
    'two' => ['url' => $echo, 'type' => Requests::POST, 'data' => ['n' => '2']],
    'bad' => ['url' => 'http://127.0.0.1:1/'],
], ['timeout' => 2]);
// The answers arrive in the order the requests finish; compare them by key.
ksort($responses);
$say('request_multiple', array_map(static fn ($r) => $r instanceof WpOrg\Requests\Response ? [$r->status_code, $seen($r)['method'] ?? null, $seen($r)['query'] ?? null, $seen($r)['form'] ?? null] : [get_class($r), $r->getType()], $responses));
$completed = [];
Requests::request_multiple(['a' => ['url' => "{$echo}?n=a"]], ['complete' => static function ($response, $id) use (&$completed) {
    $completed[] = [$id, get_debug_type($response)];
}]);
$say('request_multiple complete', $completed);

$session = new Session("{$base}/", ['X-Session' => 'on'], ['s' => '1'], ['useragent' => 'minn-session']);
$say('session get', $describe($session->get('echo.php?q=2', ['X-Extra' => '1'])));
$say('session post', $describe($session->post('echo.php', [], ['p' => '3'])));
$say('legacy request', (static function () use ($echo, $seen) {
    $notes = [];
    set_error_handler(static function ($no, $str) use (&$notes) { $notes[] = [$no, $str]; return true; });
    $r = \Requests::get($echo);
    restore_error_handler();
    return [get_class($r), $r->status_code, $seen($r)['method'] ?? null, $notes];
})());

// WordPress's own HTTP API on the same wire: the headers it sends and the
// http_api_curl hook that hands plugins the curl handle.
$curl = [];
add_action('http_api_curl', static function ($handle, $args, $url) use (&$curl, $plain) {
    $curl[] = [get_debug_type($handle), $plain($url), func_num_args(), $args['method'] ?? null];
}, 10, 3);
$wp = wp_remote_post("{$echo}?wp=1", ['body' => ['k' => 'v']]);
$say('wp_remote_post', is_wp_error($wp) ? ['error', $wp->get_error_message()] : [wp_remote_retrieve_response_code($wp), $curl, (static function ($body) {
    $data = json_decode((string) $body, true);
    unset($data['headers']['host'], $data['headers']['user-agent']);
    return $data;
})(wp_remote_retrieve_body($wp))]);

// The rest of WordPress's HTTP API: it sends through the Requests library,
// so the library's hooks fire as requests-{hook} actions, and the answer
// carries the library's response object.
$fired = [];
foreach (['requests.before_request', 'requests.before_parse', 'requests.before_redirect_check', 'requests.before_redirect', 'requests.after_request', 'curl.before_request', 'curl.before_send', 'curl.after_send', 'curl.after_request'] as $hook) {
    add_action("requests-{$hook}", static function () use (&$fired, $hook) {
        $fired[] = $hook;
    });
}
$debug = [];
add_action('http_api_debug', static function ($response, $context, $class, $args, $url) use (&$debug, $plain) {
    $debug[] = [get_debug_type($response), $context, $class, $plain($url), func_num_args()];
}, 10, 5);
$shape = static function ($response) use ($plain) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), (string) preg_replace('/after \d+ ms/', 'after N ms', $response->get_error_message())];
    }
    $object = $response['http_response'] ?? null;
    $inner = is_object($object) && method_exists($object, 'get_response_object') ? $object->get_response_object() : null;
    return [
        'keys' => array_keys($response),
        'headers' => get_debug_type($response['headers']),
        'type' => wp_remote_retrieve_header($response, 'content-type'),
        'multi' => wp_remote_retrieve_header($response, 'x-multi'),
        'response' => $response['response'],
        'cookies' => array_map(static fn ($c) => [get_debug_type($c), $c->name, $c->value, $c->path ?? null], $response['cookies']),
        'filename' => $response['filename'] === null ? null : (string) preg_replace('/\d+/', 'N', basename((string) $response['filename'])),
        'body length' => strlen((string) $response['body']),
        'http_response' => get_debug_type($object),
        'inner' => get_debug_type($inner),
        'inner redirects' => $inner->redirects ?? null,
        'inner url' => $plain($inner->url ?? null),
        'inner history' => is_object($inner) ? count($inner->history) : null,
        'status' => is_object($object) ? $object->get_status() : null,
    ];
};
$fired = [];
$say('wp_remote_get', [$shape(wp_remote_get("{$echo}?wp=2")), $fired]);
$fired = [];
$say('wp_remote_get following redirects', [$shape(wp_remote_get("{$echo}?redirect=2")), $fired]);
$say('wp_remote_get with redirection 0', $shape(wp_remote_get("{$echo}?redirect=1", ['redirection' => 0])));
$say('wp_remote_get past its redirection', $shape(wp_remote_get("{$echo}?redirect=3", ['redirection' => 1])));
$say('wp_remote_get cookies', $shape(wp_remote_get("{$echo}?cookies=1")));
$sent = wp_remote_get("{$echo}?send=1", ['cookies' => ['a' => 'b', new WP_Http_Cookie(['name' => 'c', 'value' => 'd'])], 'headers' => ['X-Probe' => 'yes']]);
$say('wp_remote_get sends cookies and headers', is_wp_error($sent) ? 'error' : [json_decode(wp_remote_retrieve_body($sent), true)['cookies'] ?? null, json_decode(wp_remote_retrieve_body($sent), true)['headers']['x-probe'] ?? null]);
$say('wp_remote_get limited', $shape(wp_remote_get($echo, ['limit_response_size' => 10])));
$file = sys_get_temp_dir() . '/minn-wp-http-' . getmypid() . '.json';
$streamed = wp_remote_get($echo, ['stream' => true, 'filename' => $file]);
$say('wp_remote_get streamed', [$shape($streamed), is_file($file) ? strlen((string) file_get_contents($file)) > 0 : false]);
@unlink($file);
$say('wp_remote_head', $shape(wp_remote_head("{$echo}?head=1")));
$say('wp_remote_get a 404', $shape(wp_remote_get("{$echo}?status=404")));
$say('wp_remote_get nowhere', $shape(wp_remote_get('http://127.0.0.1:1/', ['timeout' => 2])));
$say('wp_remote_get not blocking', $shape(wp_remote_get($echo, ['blocking' => false])));
$say('http_api_debug', $debug);
$method = static fn ($body) => json_decode((string) $body, true)['method'] ?? null;
$posted = wp_remote_post("{$echo}?redirect=1", ['body' => ['k' => 'v']]);
$say('wp_remote_post through a 302', is_wp_error($posted) ? 'error' : $method(wp_remote_retrieve_body($posted)));
$say('Requests::post through a 302 and a 303', [$method(Requests::post("{$echo}?redirect=1", [], ['k' => 'v'])->body), $method(Requests::post("{$echo}?redirect=1&code=303", [], ['k' => 'v'])->body)]);
$deleted = wp_remote_request("{$echo}?d=1", ['method' => 'DELETE', 'body' => ['k' => 'v']]);
$say('wp_remote_request DELETE with a body', is_wp_error($deleted) ? 'error' : [json_decode(wp_remote_retrieve_body($deleted), true)['query'] ?? null, json_decode(wp_remote_retrieve_body($deleted), true)['body'] ?? null]);
