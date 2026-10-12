<?php
/**
 * One request served by the engine inside this process, as the web server
 * would hand it over (for tests/tools/line-coverage.php, which cannot load
 * a coverage driver into the site's web server): the test site's
 * index.php with the request in the superglobals, the response discarded.
 *
 * php tests/tools/coverage-request.php <path> [--method=POST] [--cookie=<header>] [--nonce=<wp_rest nonce>] [--body=<form or JSON>]
 */

require __DIR__ . '/../lib.php';

$path = (string) ($argv[1] ?? '/');
// The options follow the path, where getopt() stops looking; read by hand.
$options = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--(method|cookie|nonce|body)=(.*)$/s', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}
$host = (string) parse_url(minn_test_url(), PHP_URL_HOST);
$method = strtoupper((string) ($options['method'] ?? 'GET'));
$_SERVER = array_merge($_SERVER, [
    'REQUEST_URI' => $path,
    'REQUEST_METHOD' => $method,
    'HTTP_HOST' => $host,
    'SERVER_NAME' => $host,
    'HTTPS' => 'on',
    'SERVER_PORT' => '443',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_USER_AGENT' => 'minn-line-coverage',
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
]);
$query = (string) parse_url($path, PHP_URL_QUERY);
parse_str($query, $_GET);
$_SERVER['QUERY_STRING'] = $query;
if (isset($options['cookie'])) {
    $_SERVER['HTTP_COOKIE'] = (string) $options['cookie'];
    foreach (explode(';', (string) $options['cookie']) as $pair) {
        [$name, $value] = array_map('trim', explode('=', $pair, 2)) + [1 => ''];
        $_COOKIE[$name] = rawurldecode($value);
    }
}
if (isset($options['nonce'])) {
    $_SERVER['HTTP_X_WP_NONCE'] = (string) $options['nonce'];
}
if (isset($options['body'])) {
    $body = (string) $options['body'];
    if (str_starts_with($body, '{')) {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
    } else {
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        parse_str($body, $_POST);
    }
}
$_REQUEST = array_merge($_GET, $_POST);
ob_start(static fn (): string => '');
require minn_test_site_root() . '/public/index.php';
