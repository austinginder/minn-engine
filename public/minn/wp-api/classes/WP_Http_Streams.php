<?php

/**
 * The socket transport WordPress kept from before it sent through the
 * Requests library (probe http-transports). The request is written to a
 * socket as its arguments say (no Content-Length but the caller's own; a
 * request to localhost goes to 127.0.0.1) and the answer read to the end.
 * A certificate is checked (https_ssl_verify) against sslcertificates and
 * then by name. Redirects go to WP_Http::handle_redirects; a socket that
 * will not open is http_request_failed with its error number and words, or
 * the certificate message when a secure one fails without either.
 *
 * Kept differently on purpose: HTTP/1.1 asks the server to close the
 * connection, where the reference waits on a kept-alive one.
 */
#[AllowDynamicProperties]
class WP_Http_Streams
{
    public function request($url, $args = [])
    {
        $args = _minn_http_transport_args($args);
        $parts = parse_url((string) $url) ?: [];
        $secure = strtolower($parts['scheme'] ?? '') === 'https';
        $host = (string) ($parts['host'] ?? '');
        $verify = apply_filters('https_ssl_verify', $args['sslverify'] ?? true, $url);
        $file = _minn_http_transport_file($args);
        if ($file instanceof WP_Error) {
            return $file;
        }
        $ssl = ['verify_peer' => (bool) $verify, 'verify_peer_name' => false, 'capture_peer_cert' => (bool) $verify, 'allow_self_signed' => false, 'SNI_enabled' => true, 'peer_name' => $host];
        if ($verify && !empty($args['sslcertificates'])) {
            $ssl['cafile'] = (string) $args['sslcertificates'];
        }
        $target = ($secure ? 'ssl://' : 'tcp://') . (strtolower($host) === 'localhost' ? '127.0.0.1' : $host) . ':' . ($parts['port'] ?? ($secure ? 443 : 80));
        $timeout = (float) $args['timeout'];
        $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
        $unverified = new WP_Error('http_request_failed', __('The SSL certificate for the host could not be verified.'));
        if ($socket === false) {
            return $secure && $errno === 0 && $errstr === '' ? $unverified : new WP_Error('http_request_failed', $errno . ': ' . $errstr);
        }
        if ($secure && $verify && !self::verify_ssl_certificate($socket, $host)) {
            fclose($socket);
            return $unverified;
        }
        stream_set_timeout($socket, (int) floor($timeout), (int) (fmod($timeout, 1) * 1000000));
        fwrite($socket, self::minn_request_text($parts, $args));
        if (!$args['blocking']) {
            fclose($socket);
            return _minn_http_transport_unanswered();
        }
        $raw = self::minn_read($socket, $args['limit_response_size'] ?? null);
        fclose($socket);
        $split = WP_Http::processResponse($raw);
        return _minn_http_transport_answer((string) $url, $args, $split['headers'], $split['body'], $file);
    }

    /** Whether a secure stream's captured certificate names the host (its subject alternative names); false for a stream without one. */
    public static function verify_ssl_certificate($stream, $host)
    {
        $options = is_resource($stream) ? stream_context_get_options($stream) : [];
        $certificate = isset($options['ssl']['peer_certificate']) ? openssl_x509_parse($options['ssl']['peer_certificate']) : false;
        return is_array($certificate) && \WpOrg\Requests\Ssl::verify_certificate((string) $host, $certificate);
    }

    /** Whether PHP can open sockets (with OpenSSL, when SSL is asked for), as use_streams_transport decides. */
    public static function test($args = [])
    {
        $usable = function_exists('stream_socket_client');
        if ($usable && !empty($args['ssl'])) {
            $usable = extension_loaded('openssl') && function_exists('openssl_x509_parse');
        }
        return apply_filters('use_streams_transport', $usable, $args);
    }

    /** The request as written: its line, the host, who asks, the caller's headers, a blank line, the body. */
    private static function minn_request_text(array $parts, array $args): string
    {
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $version = (string) $args['httpversion'];
        $lines = [strtoupper((string) $args['method']) . ' ' . $path . ' HTTP/' . $version, 'Host: ' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '')];
        if (!empty($args['user-agent'])) {
            $lines[] = 'User-Agent: ' . $args['user-agent'];
        }
        $headers = _minn_http_transport_headers($args['headers']);
        if ($version !== '1.0' && preg_grep('/^connection:/i', $headers) === []) {
            $headers[] = 'Connection: close';
        }
        return implode("\r\n", [...$lines, ...$headers]) . "\r\n\r\n" . _minn_http_transport_body($args['body']);
    }

    /** The answer read to its end, or until the body holds $limit bytes or a read times out. */
    private static function minn_read($socket, $limit): string
    {
        $raw = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 8192);
            if ($chunk === false || ($chunk === '' && stream_get_meta_data($socket)['timed_out'])) {
                break;
            }
            $raw .= $chunk;
            $split = strpos($raw, "\r\n\r\n");
            if ($limit !== null && $split !== false && strlen($raw) - $split - 4 >= (int) $limit) {
                break;
            }
        }
        return $raw;
    }
}
