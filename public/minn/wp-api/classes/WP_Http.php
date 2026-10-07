<?php

namespace {

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

    /** A Requests library response as WordPress hands it to plugins: the library's object inside, the array shape outside. */
    class WP_HTTP_Requests_Response extends WP_HTTP_Response
    {
        protected $response;
        protected $filename;

        public function __construct(\WpOrg\Requests\Response $response, $filename = '')
        {
            $this->response = $response;
            $this->filename = $filename;
        }

        public function get_response_object()
        {
            return $this->response;
        }

        /** The headers, one value as a string and several as a list. */
        public function get_headers()
        {
            $headers = new \WpOrg\Requests\Utility\CaseInsensitiveDictionary();
            foreach ($this->response->headers->getAll() as $name => $values) {
                $headers[$name] = count($values) === 1 ? $values[0] : $values;
            }
            return $headers;
        }

        public function set_headers($headers)
        {
            $this->response->headers = new \WpOrg\Requests\Response\Headers((array) $headers);
        }

        public function header($key, $value, $replace = true)
        {
            if ($replace) {
                unset($this->response->headers[$key]);
            }
            $this->response->headers[$key] = $value;
        }

        public function get_status()
        {
            return $this->response->status_code;
        }

        public function set_status($code)
        {
            $this->response->status_code = absint($code);
        }

        public function get_data()
        {
            return $this->response->body;
        }

        public function set_data($data)
        {
            $this->response->body = $data;
        }

        /** The cookies the response set, as WordPress's cookie objects (values decoded). */
        public function get_cookies()
        {
            $cookies = [];
            foreach ($this->response->cookies as $cookie) {
                $cookies[] = new WP_Http_Cookie(['name' => $cookie->name, 'value' => urldecode($cookie->value), 'expires' => $cookie->attributes['expires'] ?? null, 'path' => $cookie->attributes['path'] ?? null, 'domain' => $cookie->attributes['domain'] ?? null, 'host_only' => $cookie->flags['host-only'] ?? null]);
            }
            return $cookies;
        }

        public function to_array()
        {
            return ['headers' => $this->get_headers(), 'body' => $this->get_data(), 'response' => ['code' => $this->get_status(), 'message' => get_status_header_desc($this->get_status())], 'cookies' => $this->get_cookies(), 'filename' => $this->filename];
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
            if ($parsed_args['stream'] && empty($parsed_args['filename'])) {
                $parsed_args['filename'] = get_temp_dir() . wp_basename((string) parse_url($url, PHP_URL_PATH));
            }
            try {
                $sent = \WpOrg\Requests\Requests::request($url, self::request_headers($parsed_args['headers']), $parsed_args['body'], strtoupper((string) $parsed_args['method']), $this->request_options($url, $parsed_args));
                $http = new WP_HTTP_Requests_Response($sent, $parsed_args['stream'] ? (string) $parsed_args['filename'] : '');
                $response = $http->to_array();
                $response['filename'] = $parsed_args['stream'] ? $parsed_args['filename'] : null;
                $response['http_response'] = $http;
            } catch (\WpOrg\Requests\Exception $failure) {
                $response = new WP_Error('http_request_failed', $failure->getMessage());
            }
            do_action('http_api_debug', $response, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
            if ($response instanceof WP_Error) {
                return $response;
            }
            if (!$parsed_args['blocking']) {
                return ['headers' => [], 'body' => '', 'response' => ['code' => false, 'message' => false], 'cookies' => [], 'http_response' => null];
            }
            return apply_filters('http_response', $response, $parsed_args, $url);
        }

        /** The headers a request was given, as a list of name => value (a raw header block is split into lines). */
        private static function request_headers($headers): array
        {
            if (!is_string($headers)) {
                return (array) $headers;
            }
            $out = [];
            foreach (preg_split('/\r?\n/', $headers) ?: [] as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $out[trim($name)] = trim($value);
                }
            }
            return $out;
        }

        /**
         * What the Requests library is told: the time it may take, who asks,
         * whether it waits, the hooks WordPress forwards as requests-{hook}
         * actions, where a streamed body goes, how many redirects it follows
         * (none at 0), the byte limit, the cookies, certificate checking
         * (through https_ssl_verify), a body for anything but GET and HEAD,
         * and the proxy WordPress is configured with.
         */
        private function request_options(string $url, array $args): array
        {
            $hooks = new WP_HTTP_Requests_Hooks($url, $args);
            // A 302 answering a POST is followed with a GET, as browsers do.
            $hooks->register('requests.before_redirect', static function (&$location, &$headers, &$data, &$options, $original): void {
                if ($original->status_code === 302 && $options['type'] === \WpOrg\Requests\Requests::POST) {
                    $options['type'] = \WpOrg\Requests\Requests::GET;
                }
            });
            $options = ['timeout' => (float) $args['timeout'], 'useragent' => (string) $args['user-agent'], 'blocking' => (bool) $args['blocking'], 'hooks' => $hooks];
            if ($args['stream']) {
                $options['filename'] = $args['filename'];
            }
            if (empty($args['redirection'])) {
                $options['follow_redirects'] = false;
            } else {
                $options['redirects'] = (int) $args['redirection'];
            }
            if ($args['limit_response_size'] !== null) {
                $options['max_bytes'] = (int) $args['limit_response_size'];
            }
            if (!empty($args['cookies'])) {
                $options['cookies'] = self::request_cookies((array) $args['cookies']);
            }
            // Verification as plugins leave it: off, the certificate bundle given, or on.
            $verify = !$args['sslverify'] ? false : (!empty($args['sslcertificates']) ? (string) $args['sslcertificates'] : true);
            $options['verify'] = apply_filters('https_ssl_verify', $verify, $url);
            if (!in_array(strtoupper((string) $args['method']), ['GET', 'HEAD'], true)) {
                $options['data_format'] = 'body';
            }
            $proxy = new WP_HTTP_Proxy();
            if ($proxy->is_enabled() && $proxy->send_through_proxy($url)) {
                $options['proxy'] = $proxy->use_authentication() ? [$proxy->host() . ':' . $proxy->port(), $proxy->username(), $proxy->password()] : $proxy->host() . ':' . $proxy->port();
            }
            return $options;
        }

        /** The cookies a request carries, as the library's jar: WordPress cookie objects and name => value pairs alike. */
        private static function request_cookies(array $cookies): \WpOrg\Requests\Cookie\Jar
        {
            $jar = new \WpOrg\Requests\Cookie\Jar();
            foreach ($cookies as $name => $cookie) {
                if ($cookie instanceof WP_Http_Cookie) {
                    $jar[(string) $cookie->name] = new \WpOrg\Requests\Cookie((string) $cookie->name, (string) $cookie->value, array_filter($cookie->get_attributes(), static fn ($value) => $value !== null));
                } elseif (is_scalar($cookie)) {
                    $jar[(string) $name] = new \WpOrg\Requests\Cookie((string) $name, (string) $cookie);
                }
            }
            return $jar;
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
