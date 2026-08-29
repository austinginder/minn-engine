<?php

namespace WpOrg\Requests\Utility {
    /** The header dictionary shape plugin code reads from a response. */
    final class CaseInsensitiveDictionary implements \ArrayAccess, \IteratorAggregate, \Countable
    {
        private array $data = [];

        public function __construct(array $data = [])
        {
            foreach ($data as $key => $value) {
                $this->offsetSet($key, $value);
            }
        }

        public function offsetExists($offset): bool
        {
            return isset($this->data[strtolower((string) $offset)]);
        }

        public function offsetGet($offset): mixed
        {
            return $this->data[strtolower((string) $offset)] ?? null;
        }

        public function offsetSet($offset, $value): void
        {
            if ($offset === null) {
                throw new \InvalidArgumentException('Object is a dictionary, not a list');
            }
            $this->data[strtolower((string) $offset)] = $value;
        }

        public function offsetUnset($offset): void
        {
            unset($this->data[strtolower((string) $offset)]);
        }

        public function getIterator(): \ArrayIterator
        {
            return new \ArrayIterator($this->data);
        }

        public function count(): int
        {
            return count($this->data);
        }

        public function getAll()
        {
            return $this->data;
        }
    }
}

namespace {
    class WP_HTTP_Response
    {
        public $data;
        public $headers;
        public $status;

        public function __construct($data = null, $status = 200, $headers = [])
        {
            $this->set_data($data);
            $this->set_status($status);
            $this->set_headers($headers);
        }

        public function get_headers()
        {
            return $this->headers;
        }

        public function set_headers($headers)
        {
            $this->headers = $headers;
        }

        public function header($key, $value, $replace = true)
        {
            if ($replace || !isset($this->headers[$key])) {
                $this->headers[$key] = $value;
            } else {
                $this->headers[$key] .= ', ' . $value;
            }
        }

        public function get_status()
        {
            return $this->status;
        }

        public function set_status($status)
        {
            $this->status = absint($status);
        }

        public function get_data()
        {
            return $this->data;
        }

        public function set_data($data)
        {
            $this->data = $data;
        }

        public function jsonSerialize(): mixed
        {
            return $this->get_data();
        }
    }

    class WP_HTTP_Requests_Response extends WP_HTTP_Response
    {
        protected $response;
        protected $filename;

        public function __construct($response, $filename = '')
        {
            $this->response = $response;
            $this->filename = $filename;
        }

        public function get_response_object()
        {
            return $this->response;
        }

        public function get_headers()
        {
            return $this->response['headers'] ?? new \WpOrg\Requests\Utility\CaseInsensitiveDictionary();
        }

        public function get_status()
        {
            return (int) ($this->response['code'] ?? 0);
        }

        public function get_data()
        {
            return $this->response['body'] ?? '';
        }

        public function to_array()
        {
            return ['headers' => $this->get_headers(), 'body' => $this->get_data(), 'response' => ['code' => $this->get_status(), 'message' => get_status_header_desc($this->get_status())], 'cookies' => [], 'filename' => $this->filename];
        }
    }

    class WP_Http_Cookie
    {
        public $name;
        public $value;
        public $expires;
        public $path;
        public $domain;
        public $port;
        public $host_only;

        public function __construct($data, $requested_url = '')
        {
            if (is_string($data)) {
                $pairs = explode(';', $data);
                [$this->name, $this->value] = array_map('trim', explode('=', array_shift($pairs), 2) + ['', '']);
                foreach ($pairs as $pair) {
                    [$key, $val] = array_map('trim', explode('=', $pair, 2) + ['', '']);
                    $key = strtolower($key);
                    if (in_array($key, ['expires', 'path', 'domain', 'port'], true)) {
                        $this->{$key} = $key === 'expires' ? strtotime($val) : $val;
                    }
                }
                return;
            }
            foreach ((array) $data as $key => $val) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $val;
                }
            }
        }

        public function getHeaderValue()
        {
            return $this->name === null ? '' : $this->name . '=' . $this->value;
        }

        public function getFullHeader()
        {
            return 'Cookie: ' . $this->getHeaderValue();
        }

        public function get_attributes()
        {
            return ['expires' => $this->expires, 'path' => $this->path, 'domain' => $this->domain];
        }
    }

    /** The HTTP client behind wp_remote_*: curl, with the reference's response shape. */
    class WP_Http
    {
        const HEAD = 'HEAD';
        const GET = 'GET';
        const POST = 'POST';
        const PUT = 'PUT';
        const DELETE = 'DELETE';
        const PATCH = 'PATCH';
        const OPTIONS = 'OPTIONS';
        const TRACE = 'TRACE';
        const CONTINUE_ = 100;
        const OK = 200;
        const NOT_FOUND = 404;

        public function request($url, $args = [])
        {
            $defaults = ['method' => 'GET', 'timeout' => (float) apply_filters('http_request_timeout', 5, $url), 'redirection' => (int) apply_filters('http_request_redirection_count', 5, $url), 'httpversion' => apply_filters('http_request_version', '1.0', $url), 'user-agent' => apply_filters('http_headers_useragent', 'WordPress/' . $GLOBALS['wp_version'] . '; ' . get_bloginfo('url'), $url), 'reject_unsafe_urls' => apply_filters('http_request_reject_unsafe_urls', false, $url), 'blocking' => true, 'headers' => [], 'cookies' => [], 'body' => null, 'compress' => false, 'decompress' => true, 'sslverify' => true, 'sslcertificates' => '', 'stream' => false, 'filename' => null, 'limit_response_size' => null];
            $parsed_args = wp_parse_args($args, $defaults);
            $parsed_args = apply_filters('http_request_args', $parsed_args, $url);
            $pre = apply_filters('pre_http_request', false, $parsed_args, $url);
            if ($pre !== false) {
                return $pre;
            }
            if ($url === '' || $url === null || !is_string($url)) {
                return new WP_Error('http_request_failed', 'A valid URL was not provided.');
            }
            $parsed = parse_url($url);
            if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
                return new WP_Error('http_request_failed', 'A valid URL was not provided.');
            }
            if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                return new WP_Error('http_request_failed', 'Only HTTP requests are allowed.');
            }
            if ($parsed_args['reject_unsafe_urls']) {
                $url = wp_http_validate_url($url);
                if (!$url) {
                    return new WP_Error('http_request_failed', 'A valid URL was not provided.');
                }
            }
            $method = strtoupper((string) $parsed_args['method']);
            $handle = curl_init();
            $headers = [];
            foreach ((array) $parsed_args['headers'] as $name => $value) {
                $headers[] = is_int($name) ? (string) $value : "{$name}: {$value}";
            }
            $cookieHeader = [];
            foreach ((array) $parsed_args['cookies'] as $name => $cookie) {
                if ($cookie instanceof WP_Http_Cookie) {
                    $cookieHeader[] = $cookie->getHeaderValue();
                } elseif (is_string($name)) {
                    $cookieHeader[] = $name . '=' . $cookie;
                }
            }
            if ($cookieHeader !== []) {
                $headers[] = 'Cookie: ' . implode('; ', $cookieHeader);
            }
            $body = $parsed_args['body'];
            if (is_array($body) || is_object($body)) {
                $body = http_build_query((array) $body, '', '&');
                if (!array_filter($headers, static fn ($h) => stripos($h, 'content-type:') === 0)) {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset');
                }
            }
            $responseHeaders = [];
            curl_setopt_array($handle, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_NOBODY => $method === 'HEAD',
                CURLOPT_TIMEOUT_MS => (int) ($parsed_args['timeout'] * 1000),
                CURLOPT_CONNECTTIMEOUT_MS => (int) ($parsed_args['timeout'] * 1000),
                CURLOPT_FOLLOWLOCATION => (int) $parsed_args['redirection'] > 0,
                CURLOPT_MAXREDIRS => max(0, (int) $parsed_args['redirection']),
                CURLOPT_SSL_VERIFYPEER => (bool) $parsed_args['sslverify'],
                CURLOPT_SSL_VERIFYHOST => $parsed_args['sslverify'] ? 2 : 0,
                CURLOPT_USERAGENT => (string) $parsed_args['user-agent'],
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                    $responseHeaders[] = $line;
                    return strlen($line);
                },
            ]);
            if ($body !== null && $body !== '' && $method !== 'GET' && $method !== 'HEAD') {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }
            if (!empty($parsed_args['sslcertificates']) && is_file($parsed_args['sslcertificates'])) {
                curl_setopt($handle, CURLOPT_CAINFO, $parsed_args['sslcertificates']);
            }
            if (!$parsed_args['blocking']) {
                curl_setopt($handle, CURLOPT_TIMEOUT_MS, 1000);
            }
            $raw = curl_exec($handle);
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if (!$parsed_args['blocking']) {
                return ['headers' => [], 'body' => '', 'response' => ['code' => false, 'message' => false], 'cookies' => [], 'http_response' => null];
            }
            if ($errno !== 0 && $raw === false) {
                $response = new WP_Error('http_request_failed', $error !== '' ? $error : 'cURL error ' . $errno);
                do_action('http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
                return $response;
            }
            $dictionary = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary();
            $cookies = [];
            $block = [];
            foreach ($responseHeaders as $line) {
                $line = rtrim($line, "\r\n");
                if ($line === '') {
                    $block = [];
                    continue;
                }
                if (str_starts_with($line, 'HTTP/')) {
                    $block = [];
                    $dictionary = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary();
                    $cookies = [];
                    continue;
                }
                if (!str_contains($line, ':')) {
                    continue;
                }
                [$name, $value] = explode(':', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if (strtolower($name) === 'set-cookie') {
                    $cookies[] = new WP_Http_Cookie($value, $url);
                    continue;
                }
                if (isset($dictionary[$name])) {
                    $existing = $dictionary[$name];
                    $dictionary[$name] = is_array($existing) ? [...$existing, $value] : [$existing, $value];
                } else {
                    $dictionary[$name] = $value;
                }
            }
            $bodyText = $method === 'HEAD' ? '' : (string) $raw;
            if ($parsed_args['limit_response_size'] !== null) {
                $bodyText = substr($bodyText, 0, (int) $parsed_args['limit_response_size']);
            }
            $filename = null;
            if ($parsed_args['stream']) {
                $filename = $parsed_args['filename'] ?: get_temp_dir() . wp_basename((string) parse_url($url, PHP_URL_PATH)) ?: wp_tempnam();
                file_put_contents($filename, $bodyText);
                $bodyText = '';
            }
            $response = ['headers' => $dictionary, 'body' => $bodyText, 'response' => ['code' => $code, 'message' => get_status_header_desc($code)], 'cookies' => $cookies, 'filename' => $filename];
            $response['http_response'] = new WP_HTTP_Requests_Response(['headers' => $dictionary, 'body' => $bodyText, 'code' => $code], (string) $filename);
            do_action('http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
            return apply_filters('http_response', $response, $parsed_args, $url);
        }

        public function post($url, $args = [])
        {
            return $this->request($url, wp_parse_args($args, ['method' => 'POST']));
        }

        public function get($url, $args = [])
        {
            return $this->request($url, wp_parse_args($args, ['method' => 'GET']));
        }

        public function head($url, $args = [])
        {
            return $this->request($url, wp_parse_args($args, ['method' => 'HEAD']));
        }

        public static function is_ip_address($maybe_ip)
        {
            return filter_var((string) $maybe_ip, FILTER_VALIDATE_IP) !== false;
        }

        public static function parse_url($url)
        {
            return wp_parse_url($url);
        }

        public static function make_absolute_url($maybe_relative_path, $url)
        {
            if (empty($url) || str_starts_with((string) $maybe_relative_path, 'http')) {
                return $maybe_relative_path;
            }
            $parts = parse_url((string) $url);
            $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '');
            return str_starts_with((string) $maybe_relative_path, '/') ? $base . $maybe_relative_path : rtrim($base . dirname($parts['path'] ?? '/'), '/') . '/' . $maybe_relative_path;
        }

        public function block_request($uri)
        {
            return defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL;
        }
    }
}
