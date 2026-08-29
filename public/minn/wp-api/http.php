<?php
/** The HTTP API over WP_Http. */

use Minn\Runtime\Runtime;
use Minn\Support\Url;

function _wp_http_get_object()
{
    $http = Runtime::current()->get('http');
    if (!$http instanceof WP_Http) {
        $http = new WP_Http();
        Runtime::current()->set('http', $http);
    }
    return $http;
}

function wp_remote_request($url, $args = [])
{
    return _wp_http_get_object()->request($url, $args);
}

function wp_remote_get($url, $args = [])
{
    return _wp_http_get_object()->get($url, $args);
}

function wp_remote_post($url, $args = [])
{
    return _wp_http_get_object()->post($url, $args);
}

function wp_remote_head($url, $args = [])
{
    return _wp_http_get_object()->head($url, $args);
}

function wp_safe_remote_request($url, $args = [])
{
    $args['reject_unsafe_urls'] = true;
    return _wp_http_get_object()->request($url, $args);
}

function wp_safe_remote_get($url, $args = [])
{
    $args['reject_unsafe_urls'] = true;
    return _wp_http_get_object()->get($url, $args);
}

function wp_safe_remote_post($url, $args = [])
{
    $args['reject_unsafe_urls'] = true;
    return _wp_http_get_object()->post($url, $args);
}

function wp_safe_remote_head($url, $args = [])
{
    $args['reject_unsafe_urls'] = true;
    return _wp_http_get_object()->head($url, $args);
}

function wp_remote_retrieve_headers($response)
{
    if (is_wp_error($response) || !isset($response['headers'])) {
        return [];
    }
    return $response['headers'];
}

function wp_remote_retrieve_header($response, $header)
{
    if (is_wp_error($response) || !isset($response['headers'])) {
        return '';
    }
    return $response['headers'][$header] ?? '';
}

function wp_remote_retrieve_response_code($response)
{
    if (is_wp_error($response) || !isset($response['response']) || !is_array($response['response'])) {
        return '';
    }
    return $response['response']['code'];
}

function wp_remote_retrieve_response_message($response)
{
    if (is_wp_error($response) || !isset($response['response']) || !is_array($response['response'])) {
        return '';
    }
    return $response['response']['message'];
}

function wp_remote_retrieve_body($response)
{
    if (is_wp_error($response) || !isset($response['body'])) {
        return '';
    }
    return $response['body'];
}

function wp_remote_retrieve_cookies($response)
{
    if (is_wp_error($response) || empty($response['cookies'])) {
        return [];
    }
    return $response['cookies'];
}

function wp_remote_retrieve_cookie($response, $name)
{
    foreach (wp_remote_retrieve_cookies($response) as $cookie) {
        if ($cookie->name === $name) {
            return $cookie;
        }
    }
    return '';
}

function wp_remote_retrieve_cookie_value($response, $name)
{
    $cookie = wp_remote_retrieve_cookie($response, $name);
    return $cookie instanceof WP_Http_Cookie ? $cookie->value : '';
}

function wp_http_supports($capabilities = [], $url = null)
{
    $capabilities = wp_parse_args($capabilities);
    $count = count($capabilities);
    if ($count && count(array_filter(array_keys($capabilities), 'is_numeric')) === $count) {
        $capabilities = array_combine(array_values($capabilities), array_fill(0, $count, true));
    }
    foreach ($capabilities as $capability => $needed) {
        if ($capability === 'ssl' && $needed && !extension_loaded('curl') && !extension_loaded('openssl')) {
            return false;
        }
    }
    return true;
}

function wp_http_validate_url($url)
{
    if (!is_string($url) || $url === '') {
        return false;
    }
    $checked = wp_kses_bad_protocol($url, ['http', 'https']);
    if ($checked === '' || strtolower($checked) !== strtolower($url)) {
        return false;
    }
    $home = parse_url(home_url());
    return Url::validateForHttp($checked, (string) ($home['host'] ?? ''), (int) ($home['port'] ?? 0), static fn (string $host, string $url): bool => (bool) apply_filters('http_request_host_is_external', false, $host, $url)) ?? false;
}

function get_http_origin()
{
    $origin = (string) (Runtime::current()->request?->header('origin') ?? '');
    return apply_filters('http_origin', $origin);
}

function get_allowed_http_origins()
{
    $home = parse_url(home_url());
    $site = parse_url(site_url());
    $origins = ['http://' . ($home['host'] ?? ''), 'https://' . ($home['host'] ?? ''), 'http://' . ($site['host'] ?? ''), 'https://' . ($site['host'] ?? '')];
    return array_unique(apply_filters('allowed_http_origins', $origins));
}

function is_allowed_http_origin($origin = null)
{
    $origin ??= get_http_origin();
    if ($origin !== '' && !in_array($origin, get_allowed_http_origins(), true)) {
        $origin = '';
    }
    return apply_filters('allowed_http_origin', $origin, $origin);
}

function send_origin_headers()
{
    $origin = get_http_origin();
    if (is_allowed_http_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        return $origin;
    }
    return false;
}

function download_url($url, $timeout = 300, $signature_verification = false)
{
    if (!$url) {
        return new WP_Error('http_no_url', 'No URL Provided.');
    }
    $url_path = parse_url((string) $url, PHP_URL_PATH);
    $url_filename = '';
    if (is_string($url_path) && $url_path !== '') {
        $url_filename = wp_basename($url_path);
    }
    $tmpfname = wp_tempnam($url_filename);
    if (!$tmpfname) {
        return new WP_Error('http_no_file', 'Could not create temporary file.');
    }
    $response = wp_safe_remote_get($url, ['timeout' => $timeout, 'stream' => true, 'filename' => $tmpfname]);
    if (is_wp_error($response)) {
        @unlink($tmpfname);
        return $response;
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        @unlink($tmpfname);
        return new WP_Error('http_404', trim((string) wp_remote_retrieve_response_message($response)), ['code' => $code]);
    }
    return $tmpfname;
}
