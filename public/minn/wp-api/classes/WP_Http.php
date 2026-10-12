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

        /**
         * A cookie from a Set-Cookie value (the value URL-decoded; its domain and path, when it names
         * none, from the URL it came from: the host, and the URL's directory)
         * or from its fields. The path is "/" when nothing gives one; an
         * expiry given as a date becomes a timestamp (probe http-classes).
         */
        public function __construct($data, $requested_url = '')
        {
            if (is_string($data)) {
                $parsed = \Minn\Http\CookieText::parse($data);
                [$this->name, $this->value] = [$parsed['name'], urldecode((string) $parsed['value'])];
                foreach ($parsed['attributes'] as $key => $val) {
                    $key = strtolower((string) $key);
                    $val = $val === true ? '' : (string) $val;
                    if (in_array($key, ['expires', 'path', 'domain', 'port'], true)) {
                        $this->{$key} = $key === 'expires' ? strtotime($val) : $val;
                    }
                }
                if ($requested_url !== '' && ($from = parse_url((string) $requested_url)) !== false) {
                    $this->domain ??= $from['host'] ?? null;
                    $path = $from['path'] ?? '/';
                    $this->path ??= str_ends_with($path, '/') ? $path : dirname($path) . '/';
                }
            } else {
                foreach ((array) $data as $key => $val) {
                    if (property_exists($this, $key)) {
                        $this->{$key} = $val;
                    }
                }
                if (is_string($this->expires) && !is_numeric($this->expires)) {
                    $this->expires = strtotime($this->expires);
                }
            }
            $this->path ??= '/';
        }

        /** Whether the cookie goes with a request to a URL: not expired, the host ending with its domain, the path starting with its path, the port among its ports. */
        public function test($url)
        {
            if ($this->name === null || ($this->expires !== null && time() > (int) $this->expires)) {
                return false;
            }
            $parts = parse_url((string) $url);
            if ($parts === false || !isset($parts['host'])) {
                return false;
            }
            $port = $parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80);
            if (!empty($this->port) && !in_array($port, array_map('intval', explode(',', (string) $this->port)), true)) {
                return false;
            }
            if (!str_starts_with($parts['path'] ?? '/', (string) $this->path)) {
                return false;
            }
            return str_ends_with($parts['host'], ltrim((string) ($this->domain ?? $parts['host']), '.'));
        }

        /** name=value for a Cookie header, the value through wp_http_cookie_value (probe http-transports). */
        public function getHeaderValue()
        {
            return $this->name === null ? '' : $this->name . '=' . apply_filters('wp_http_cookie_value', $this->value, $this->name);
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
            $defaults = ['method' => 'GET', 'timeout' => (float) apply_filters('http_request_timeout', 5, $url), 'redirection' => (int) apply_filters('http_request_redirection_count', 5, $url), 'httpversion' => apply_filters('http_request_version', '1.0', $url), 'user-agent' => apply_filters('http_headers_useragent', 'WordPress/' . $GLOBALS['wp_version'] . '; ' . get_bloginfo('url'), $url), 'reject_unsafe_urls' => apply_filters('http_request_reject_unsafe_urls', false, $url), 'blocking' => true, 'headers' => [], 'cookies' => [], 'body' => null, 'compress' => false, 'decompress' => true, 'sslverify' => true, 'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt', 'stream' => false, 'filename' => null, 'limit_response_size' => null];
            // A HEAD request follows no redirects unless it asks to (probe functions-remaining).
            if (($args['method'] ?? '') === 'HEAD') {
                $defaults['redirection'] = 0;
            }
            // The redirects first asked for, which handle_redirects reads as the hops go down (probe http-transports).
            $defaults['_redirection'] = $args['redirection'] ?? $defaults['redirection'];
            $parsed_args = apply_filters('http_request_args', wp_parse_args($args, $defaults), $url);
            $pre = apply_filters('pre_http_request', false, $parsed_args, $url);
            if ($pre !== false) {
                return $pre;
            }
            // A URL refused, or one WP_HTTP_BLOCK_EXTERNAL blocks, is told to http_api_debug like a failed
            // request, as the URL it became: false when unsafe, without a protocol other than http(s) (probe hook-inputs-2).
            $url = $parsed_args['reject_unsafe_urls'] ? wp_http_validate_url($url) : wp_kses_bad_protocol((string) $url, ['http', 'https', 'ssl']);
            $refused = $this->refusal($url);
            if ($refused !== null) {
                do_action('http_api_debug', $refused, 'response', 'WpOrg\\Requests\\Requests', $parsed_args, $url);
                return $refused;
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
            $hooks->register('requests.before_redirect', [self::class, 'browser_redirect_compatibility']);
            // A request that refuses unsafe URLs refuses them as redirect targets too.
            if (!empty($args['reject_unsafe_urls'])) {
                $hooks->register('requests.before_redirect', [self::class, 'validate_redirects']);
            }
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
                $options['cookies'] = self::normalize_cookies((array) $args['cookies']);
            }
            // https_ssl_verify hears the certificate bundle named (the reference's own path unless a
            // plugin names another) or false; a bundle not on disk here verifies against the system's.
            $verify = apply_filters('https_ssl_verify', $args['sslverify'] ? $args['sslcertificates'] : false, $url);
            $options['verify'] = is_string($verify) ? ($verify !== '' && is_readable($verify) ? $verify : true) : (bool) $verify;
            if (!in_array(strtoupper((string) $args['method']), ['GET', 'HEAD'], true)) {
                $options['data_format'] = 'body';
            }
            $proxy = new WP_HTTP_Proxy();
            if ($proxy->is_enabled() && $proxy->send_through_proxy($url)) {
                $options['proxy'] = $proxy->use_authentication() ? [$proxy->host() . ':' . $proxy->port(), $proxy->username(), $proxy->password()] : $proxy->host() . ':' . $proxy->port();
            }
            return $options;
        }

        /** The cookies a request carries, as the library's jar keyed by name: WordPress cookie objects and name => value pairs alike. */
        public static function normalize_cookies($cookies)
        {
            $jar = new \WpOrg\Requests\Cookie\Jar();
            foreach ((array) $cookies as $name => $cookie) {
                if ($cookie instanceof WP_Http_Cookie) {
                    $jar[(string) $cookie->name] = new \WpOrg\Requests\Cookie((string) $cookie->name, (string) $cookie->value, array_filter($cookie->get_attributes(), static fn ($value) => $value !== null));
                } elseif (is_scalar($cookie)) {
                    $jar[(string) $name] = new \WpOrg\Requests\Cookie((string) $name, (string) $cookie);
                }
            }
            return $jar;
        }

        /** Why a URL is not requested: not an http(s) URL, or blocked; null to go ahead. */
        private function refusal($url): ?WP_Error
        {
            $parsed = is_string($url) && $url !== '' ? parse_url($url) : false;
            if ($parsed === false || empty($parsed['host']) || !in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true)) {
                return new WP_Error('http_request_failed', 'A valid URL was not provided.');
            }
            if ($this->block_request($url)) {
                return new WP_Error('http_request_not_executed', sprintf('User has blocked requests through HTTP to the URL: %s.', $url));
            }
            return null;
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

        /**
         * A path read against a base URL as the reference reads one (probe
         * http-classes): one with a scheme stays as written, and one that
         * starts // takes the base's scheme. Otherwise the result is the
         * base's scheme, host and port (never its user, query or fragment)
         * with: the base's path, for an empty path; the path as written,
         * for one starting /; or the base's folder joined to the path, each
         * segment/../ pair collapsed (a . segment too), climbs past the
         * root dropped, and a leading / added. ./ segments and a trailing
         * .. stay. The path's own query and fragment follow, when not empty.
         */
        public static function make_absolute_url($maybe_relative_path, $url)
        {
            $relative = (string) $maybe_relative_path;
            if (empty($url) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $relative) === 1) {
                return $maybe_relative_path;
            }
            $base = (array) wp_parse_url((string) $url);
            if (str_starts_with($relative, '//')) {
                return ($base['scheme'] ?? '') . ':' . $relative;
            }
            $parts = (array) wp_parse_url($relative);
            $path = (string) ($parts['path'] ?? '');
            $basePath = (string) ($base['path'] ?? '');
            if ($path === '') {
                $path = $basePath;
            } elseif (!str_starts_with($path, '/')) {
                $slash = strrpos($basePath, '/');
                $path = ($slash === false ? '/' : substr($basePath, 0, $slash + 1)) . $path;
                do {
                    $path = (string) preg_replace('#/(?!\.\./)[^/]+/\.\./#', '/', $path, 1, $collapsed);
                } while ($collapsed > 0);
                while (str_starts_with($path, '/../')) {
                    $path = substr($path, 3);
                }
                $path = str_starts_with($path, '/') ? $path : '/' . $path;
            }
            return ($base['scheme'] ?? '') . '://' . ($base['host'] ?? '') . (isset($base['port']) ? ':' . $base['port'] : '') . $path
                . (($parts['query'] ?? '') !== '' ? '?' . $parts['query'] : '') . (($parts['fragment'] ?? '') !== '' ? '#' . $parts['fragment'] : '');
        }

        /** A raw response split at its first blank line into the header block and the body. */
        public static function processResponse($response)
        {
            $parts = explode("\r\n\r\n", (string) $response, 2);
            return ['headers' => $parts[0], 'body' => $parts[1] ?? ''];
        }

        /** A header block read into its status, lower-cased headers and Set-Cookie cookies (read against the URL): Minn\Http\LegacyHeaders::read. */
        public static function processHeaders($headers, $url = '')
        {
            $read = \Minn\Http\LegacyHeaders::read(is_array($headers) ? $headers : (string) $headers);
            $read['cookies'] = array_map(static fn (string $value): WP_Http_Cookie => new WP_Http_Cookie($value, $url), $read['cookies']);
            return $read;
        }

        /** The request's cookies made WP_Http_Cookie objects in place (name => value pairs too) and joined into its Cookie header (probe http-transports). */
        public static function buildCookieHeader(&$r)
        {
            if (empty($r['cookies'])) {
                return;
            }
            $r['cookies'] = (array) $r['cookies'];
            foreach ($r['cookies'] as $name => $value) {
                if (!$value instanceof WP_Http_Cookie) {
                    $r['cookies'][$name] = new WP_Http_Cookie(['name' => $name, 'value' => $value]);
                }
            }
            $r['headers']['cookie'] = implode('; ', array_map(static fn (WP_Http_Cookie $cookie) => $cookie->getHeaderValue(), $r['cookies']));
        }

        /** A chunked body decoded with the reference's quirk (Minn\Http\LegacyHeaders::dechunk); a body that is not chunked comes back as it was. */
        public static function chunkTransferDecode($body)
        {
            return preg_match('/^[0-9a-f]+[^\r\n]*\r\n/i', trim((string) $body)) === 1 ? \Minn\Http\LegacyHeaders::dechunk((string) $body) : $body;
        }

        /** A 302 is followed with a GET, as browsers do (the Requests library's before_redirect hook). */
        public static function browser_redirect_compatibility($location, $headers, $data, &$options, $original)
        {
            if ($original->status_code === 302) {
                $options['type'] = \WpOrg\Requests\Requests::GET;
            }
        }

        /** A redirect target refused unless it is a URL WordPress may request (wp_http_validate_url). */
        public static function validate_redirects($location)
        {
            if (!wp_http_validate_url($location)) {
                throw new \WpOrg\Requests\Exception(__('A valid URL was not provided.'), 'wp_http.redirect_failed_validation');
            }
        }

        /**
         * A 3xx response's Location followed (the last when there are several,
         * read against the URL), with one fewer redirect left; a POST answered
         * by 302 or 303 goes on as a GET. False for no Location or no 3xx;
         * an error when no redirects are left.
         */
        public static function handle_redirects($url, $args, $response)
        {
            $code = (int) ($response['response']['code'] ?? 0);
            // A request that asked for no redirects (_redirection 0) keeps the 3xx (probe http-transports).
            if (!isset($response['headers']['location']) || $code < 300 || $code > 399 || ($args['_redirection'] ?? null) === 0) {
                return false;
            }
            if ($args['redirection']-- <= 0) {
                return new WP_Error('http_request_failed', __('Too many redirects.'));
            }
            $location = $response['headers']['location'];
            $location = self::make_absolute_url(is_array($location) ? end($location) : $location, $url);
            if (($args['method'] ?? '') === 'POST' && in_array($code, [302, 303], true)) {
                $args['method'] = 'GET';
            }
            return wp_remote_request($location, $args);
        }

        /** The first transport (http_api_transports, curl then streams) this PHP can use, by its WP_Http_ class name; false for none. */
        public function _get_first_available_transport($args, $url = null)
        {
            foreach ((array) apply_filters('http_api_transports', ['curl', 'streams'], $args, $url) as $transport) {
                $usable = match ($transport) {
                    'curl' => function_exists('curl_init') && function_exists('curl_exec'),
                    'streams' => (bool) ini_get('allow_url_fopen'),
                    default => false,
                };
                if ($usable) {
                    return 'WP_Http_' . ucfirst((string) $transport);
                }
            }
            return false;
        }

        /**
         * Whether WP_HTTP_BLOCK_EXTERNAL blocks a URL: one with no host always,
         * localhost and the site's own host only when block_local_requests
         * says so, any other host unless WP_ACCESSIBLE_HOSTS lists it (exactly,
         * or ignoring case once any entry has a * wildcard).
         */
        public function block_request($uri)
        {
            if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL) {
                return false;
            }
            $host = parse_url((string) $uri, PHP_URL_HOST);
            if (!is_string($host) || $host === '') {
                return true;
            }
            if ($host === 'localhost' || $host === parse_url((string) get_option('siteurl'), PHP_URL_HOST)) {
                return (bool) apply_filters('block_local_requests', false);
            }
            if (!defined('WP_ACCESSIBLE_HOSTS')) {
                return true;
            }
            $hosts = preg_split('|,\s*|', (string) WP_ACCESSIBLE_HOSTS);
            if (!str_contains((string) WP_ACCESSIBLE_HOSTS, '*')) {
                return !in_array($host, $hosts, true);
            }
            $patterns = array_map(static fn ($entry) => str_replace('\\*', '.+', preg_quote($entry, '/')), $hosts);
            return !preg_match('/^(' . implode('|', $patterns) . ')$/i', $host);
        }
    }
}
