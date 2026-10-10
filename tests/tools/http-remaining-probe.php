<?php
/**
 * The rest of http.php (probe http-remaining): the shared HTTP object, the
 * safe request helpers (what they pass on, heard through pre_http_request
 * and answered there, so nothing leaves; local and malformed addresses
 * refused), cookies read back from a response, and the allowed origins with
 * the origin headers' answer for each request origin. Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code()];
    }
    return is_string($value) ? str_replace($host, '{host}', $value) : $value;
};

$say('the shared HTTP object', [get_class(_wp_http_get_object()), _wp_http_get_object() === _wp_http_get_object()]);

$heard = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$heard) {
    $heard[] = [$url, $args['method'], $args['reject_unsafe_urls'] ?? null, $args['body'] ?? null, $args['timeout'], $args['redirection'], $args['headers'] ?? []];
    return ['headers' => ['set-cookie' => 'x=y'], 'body' => 'answered', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}, 10, 3);
$requests = [];
foreach ([
    'head' => static fn () => wp_safe_remote_head('https://zz.example/head'),
    'post' => static fn () => wp_safe_remote_post('https://zz.example/post', ['body' => ['a' => '1'], 'timeout' => 3]),
    'request' => static fn () => wp_safe_remote_request('https://zz.example/request', ['method' => 'PUT', 'redirection' => 0, 'headers' => ['X-Zz' => 'yes']]),
    'request, a local address' => static fn () => wp_safe_remote_request('http://127.0.0.1:1/x'),
    'post, no scheme' => static fn () => wp_safe_remote_post('zz.example/no-scheme'),
    'head, credentials in the address' => static fn () => wp_safe_remote_head('https://user:pass@zz.example/'),
] as $label => $call) {
    $heard = [];
    $result = $call();
    $requests[$label] = [is_wp_error($result) ? ['error' => $result->get_error_code()] : wp_remote_retrieve_body($result), $heard];
}
$say('the safe request helpers', $mask($requests));

$response = ['cookies' => [new WP_Http_Cookie(['name' => 'zz_one', 'value' => 'first', 'domain' => 'zz.example']), new WP_Http_Cookie(['name' => 'zz_two', 'value' => 'second value']), new WP_Http_Cookie(['name' => 'zz_one', 'value' => 'again'])]];
$cookie = static fn ($found) => $found instanceof WP_Http_Cookie ? [$found->name, $found->value, $found->domain] : $found;
$say('cookies read back', [
    $cookie(wp_remote_retrieve_cookie($response, 'zz_one')), $cookie(wp_remote_retrieve_cookie($response, 'zz_two')), $cookie(wp_remote_retrieve_cookie($response, 'zz_none')), $cookie(wp_remote_retrieve_cookie([], 'zz_one')), $cookie(wp_remote_retrieve_cookie(new WP_Error('x'), 'zz_one')),
    wp_remote_retrieve_cookie_value($response, 'zz_one'), wp_remote_retrieve_cookie_value($response, 'zz_two'), wp_remote_retrieve_cookie_value($response, 'zz_none'), wp_remote_retrieve_cookie_value(new WP_Error('x'), 'zz_one'),
]);

$origins = [get_allowed_http_origins()];
add_filter('allowed_http_origins', $extra = static fn ($origins) => array_merge($origins, ['https://zz-partner.example', 'http://' . parse_url(home_url(), PHP_URL_HOST)]));
$origins[] = get_allowed_http_origins();
$answers = [];
foreach (['https://' . $host, 'http://' . $host, 'https://zz-partner.example', 'https://zz-stranger.example', '', 'null'] as $origin) {
    $_SERVER['HTTP_ORIGIN'] = $origin;
    $answers[] = [$origin, get_http_origin(), send_origin_headers()];
}
unset($_SERVER['HTTP_ORIGIN']);
$answers[] = ['no origin', get_http_origin(), send_origin_headers()];
remove_filter('allowed_http_origins', $extra, 10);
$origins[] = $answers;
$say('the allowed origins', $mask($origins));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
