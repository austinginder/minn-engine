<?php

/**
 * The curl transport WordPress kept from before it sent through the Requests
 * library (probe http-transports). A request goes out as its arguments say,
 * through the engine's curl client: the method, HTTP/1.0 unless 1.1 is
 * asked for, the headers with a Cookie header built from its cookies, the
 * body, the certificate checked (https_ssl_verify) against sslcertificates,
 * and http_api_curl handed the handle before it is sent. Redirects go to
 * WP_Http::handle_redirects; a failure is http_request_failed with curl's
 * own words, and a request that does not wait answers with no status.
 */
#[AllowDynamicProperties]
class WP_Http_Curl
{
    public function request($url, $args = [])
    {
        $args = _minn_http_transport_args($args);
        $verify = apply_filters('https_ssl_verify', $args['sslverify'] ?? true, $url);
        $file = _minn_http_transport_file($args);
        if ($file instanceof WP_Error) {
            return $file;
        }
        $prepare = static function (\CurlHandle $handle) use ($args, $url, $verify): void {
            curl_setopt($handle, CURLOPT_HTTP_VERSION, (string) $args['httpversion'] === '1.0' ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1);
            // An empty bundle is curl's error to report, as on the reference.
            if ($verify) {
                curl_setopt($handle, CURLOPT_CAINFO, (string) ($args['sslcertificates'] ?? ''));
            }
            do_action_ref_array('http_api_curl', [&$handle, $args, $url]);
        };
        $outbound = new \Minn\Http\Outbound(strtoupper((string) $args['method']), (string) $url, _minn_http_transport_headers($args['headers']), _minn_http_transport_body($args['body']), (float) $args['timeout'], 0, (bool) $verify, (string) ($args['user-agent'] ?? ''), null, (bool) $args['blocking'], $prepare);
        $exchange = (new \Minn\Http())->send($outbound);
        if (!$args['blocking']) {
            return _minn_http_transport_unanswered();
        }
        if ($exchange->failed()) {
            return new WP_Error('http_request_failed', $exchange->error);
        }
        return _minn_http_transport_answer((string) $url, $args, implode("\r\n", $exchange->head), $exchange->body, $file);
    }

    /** Whether curl can send (with SSL, when asked), as use_curl_transport decides. */
    public static function test($args = [])
    {
        $usable = function_exists('curl_init') && function_exists('curl_exec');
        if ($usable && !empty($args['ssl'])) {
            $usable = (bool) (curl_version()['features'] & CURL_VERSION_SSL);
        }
        return apply_filters('use_curl_transport', $usable, $args);
    }
}
