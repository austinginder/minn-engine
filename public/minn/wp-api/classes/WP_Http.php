<?php

namespace {
    use Minn\Http\Exchange;
    use Minn\Http\Outbound;

    #[AllowDynamicProperties]
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

    #[AllowDynamicProperties]
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
                $parsed = \Minn\Http\CookieText::parse($data);
                [$this->name, $this->value] = [$parsed['name'], $parsed['value']];
                foreach ($parsed['attributes'] as $key => $val) {
                    $key = strtolower((string) $key);
                    $val = $val === true ? '' : (string) $val;
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
    #[AllowDynamicProperties]
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
            $parsed_args = apply_filters('http_request_args', wp_parse_args($args, $defaults), $url);
            $pre = apply_filters('pre_http_request', false, $parsed_args, $url);
            if ($pre !== false) {
                return $pre;
            }
            $url = $this->validated_url($url, (bool) $parsed_args['reject_unsafe_urls']);
            if ($url instanceof WP_Error) {
                return $url;
            }
            $exchange = \Minn\Http::send($this->outbound($url, $parsed_args));
            if (!$parsed_args['blocking']) {
                return ['headers' => [], 'body' => '', 'response' => ['code' => false, 'message' => false], 'cookies' => [], 'http_response' => null];
            }
            if ($exchange->failed()) {
                $response = new WP_Error('http_request_failed', (string) $exchange->error);
                do_action('http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
                return $response;
            }
            $response = $this->shape($exchange, $url, $parsed_args);
            do_action('http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
            return apply_filters('http_response', $response, $parsed_args, $url);
        }

        private function validated_url($url, bool $rejectUnsafe)
        {
            if (!is_string($url) || $url === '') {
                return new WP_Error('http_request_failed', 'A valid URL was not provided.');
            }
            $parsed = parse_url($url);
            if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
                return new WP_Error('http_request_failed', 'A valid URL was not provided.');
            }
            if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                return new WP_Error('http_request_failed', 'Only HTTP requests are allowed.');
            }
            if ($rejectUnsafe) {
                $url = wp_http_validate_url($url);
            }
            return $url ?: new WP_Error('http_request_failed', 'A valid URL was not provided.');
        }

        private function outbound(string $url, array $args): Outbound
        {
            $headers = [];
            foreach ((array) $args['headers'] as $name => $value) {
                $headers[] = is_int($name) ? (string) $value : "{$name}: {$value}";
            }
            $cookieHeader = [];
            foreach ((array) $args['cookies'] as $name => $cookie) {
                if ($cookie instanceof WP_Http_Cookie) {
                    $cookieHeader[] = $cookie->getHeaderValue();
                } elseif (is_string($name)) {
                    $cookieHeader[] = $name . '=' . $cookie;
                }
            }
            if ($cookieHeader !== []) {
                $headers[] = 'Cookie: ' . implode('; ', $cookieHeader);
            }
            $body = $args['body'];
            if (is_array($body) || is_object($body)) {
                // curl labels a form body application/x-www-form-urlencoded, as on the reference; nothing is added here.
                $body = http_build_query((array) $body, '', '&');
            }
            if (!array_filter($headers, static fn ($h) => stripos($h, 'connection:') === 0)) {
                $headers[] = 'Connection: close';
            }
            // Verification as plugins leave it: off, the certificate bundle given, or on (https_ssl_verify).
            $verify = !$args['sslverify'] ? false : (!empty($args['sslcertificates']) ? (string) $args['sslcertificates'] : true);
            $verify = apply_filters('https_ssl_verify', $verify, $url);
            return new Outbound(
                method: strtoupper((string) $args['method']),
                url: $url,
                headers: $headers,
                body: $body === null ? null : (string) $body,
                timeout: (float) $args['timeout'],
                redirects: (int) $args['redirection'],
                verifySsl: $verify !== false,
                userAgent: (string) $args['user-agent'],
                caInfo: is_string($verify) && $verify !== '' ? $verify : null,
                blocking: (bool) $args['blocking'],
                prepare: self::curl_prepare($args, $url),
            );
        }

        /** As the reference's transport does: curl negotiates compression, and plugins tune the handle through http_api_curl. */
        private static function curl_prepare(array $args, string $url): \Closure
        {
            return static function (\CurlHandle $handle) use ($args, $url): void {
                if (!empty($args['decompress'])) {
                    curl_setopt($handle, CURLOPT_ENCODING, '');
                }
                do_action_ref_array('http_api_curl', [&$handle, $args, $url]);
            };
        }

        private function shape(Exchange $exchange, string $url, array $args): array
        {
            $dictionary = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary($exchange->headers);
            $cookies = array_map(static fn (string $value) => new WP_Http_Cookie($value, $url), $exchange->cookies);
            $bodyText = $args['limit_response_size'] !== null ? substr($exchange->body, 0, (int) $args['limit_response_size']) : $exchange->body;
            $filename = null;
            if ($args['stream']) {
                $filename = $args['filename'] ?: get_temp_dir() . wp_basename((string) parse_url($url, PHP_URL_PATH)) ?: wp_tempnam();
                file_put_contents($filename, $bodyText);
                $bodyText = '';
            }
            $response = ['headers' => $dictionary, 'body' => $bodyText, 'response' => ['code' => $exchange->code, 'message' => get_status_header_desc($exchange->code)], 'cookies' => $cookies, 'filename' => $filename];
            $response['http_response'] = new WP_HTTP_Requests_Response(['headers' => $dictionary, 'body' => $bodyText, 'code' => $exchange->code], (string) $filename);
            return $response;
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
            $base = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
            if (str_starts_with((string) $maybe_relative_path, '/')) {
                return $base . $maybe_relative_path;
            }
            $path = (string) ($parts['path'] ?? '/');
            $directory = str_ends_with($path, '/') ? $path : (dirname($path) === '/' ? '/' : dirname($path) . '/');
            return $base . $directory . $maybe_relative_path;
        }

        public function block_request($uri)
        {
            return defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL;
        }
    }
}
