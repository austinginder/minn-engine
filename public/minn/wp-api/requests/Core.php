<?php
/**
 * The Requests library plugins call directly (WpOrg\Requests\Requests and
 * friends), sending through the engine's own curl client. The flow is the
 * one plugins observe: options merged over the defaults, hooks
 * (requests.before_request, the transport's curl.* hooks,
 * requests.before_parse, requests.before_redirect_check,
 * requests.before_redirect, requests.after_request), the raw response
 * parsed into a Response, redirects followed by the library itself so the
 * history is kept. Captured in contracts/fixtures (tests/requests.test.php).
 */

namespace WpOrg\Requests {
    use Minn\Http\RawResponse;
    use WpOrg\Requests\Cookie\Jar;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Response\Headers;
    use WpOrg\Requests\Utility\InputValidator;

    class Requests
    {
        public const POST = 'POST';
        public const PUT = 'PUT';
        public const GET = 'GET';
        public const HEAD = 'HEAD';
        public const DELETE = 'DELETE';
        public const OPTIONS = 'OPTIONS';
        public const TRACE = 'TRACE';
        public const PATCH = 'PATCH';
        public const BUFFER_SIZE = 1160;
        public const OPTION_DEFAULTS = ['timeout' => 10, 'connect_timeout' => 10, 'useragent' => 'php-requests/2.0.17', 'protocol_version' => 1.1, 'redirected' => 0, 'redirects' => 10, 'follow_redirects' => true, 'blocking' => true, 'type' => self::GET, 'filename' => false, 'auth' => false, 'proxy' => false, 'cookies' => false, 'max_bytes' => false, 'idn' => true, 'hooks' => null, 'transport' => null, 'verify' => null, 'verifyname' => true];
        public const DEFAULT_TRANSPORTS = [Transport\Curl::class => Transport\Curl::class, Transport\Fsockopen::class => Transport\Fsockopen::class];
        public const VERSION = '2.0.17';

        public static $transport = [];
        protected static $transports = [];
        protected static $certificate_path = ABSPATH . WPINC . '/certificates/ca-bundle.crt';

        public static function add_transport($transport)
        {
            if (empty(self::$transports)) {
                self::$transports = self::DEFAULT_TRANSPORTS;
            }
            self::$transports[$transport] = $transport;
        }

        /** Whether a transport here can do what is asked (SSL through curl). */
        public static function has_capabilities(array $capabilities = [])
        {
            foreach (self::$transports ?: self::DEFAULT_TRANSPORTS as $class) {
                if (class_exists($class) && $class::test($capabilities)) {
                    return true;
                }
            }
            return false;
        }

        public static function get($url, $headers = [], $options = [])
        {
            return self::request($url, $headers, null, self::GET, $options);
        }

        public static function head($url, $headers = [], $options = [])
        {
            return self::request($url, $headers, null, self::HEAD, $options);
        }

        public static function delete($url, $headers = [], $options = [])
        {
            return self::request($url, $headers, null, self::DELETE, $options);
        }

        public static function trace($url, $headers = [], $options = [])
        {
            return self::request($url, $headers, null, self::TRACE, $options);
        }

        public static function post($url, $headers = [], $data = [], $options = [])
        {
            return self::request($url, $headers, $data, self::POST, $options);
        }

        public static function put($url, $headers = [], $data = [], $options = [])
        {
            return self::request($url, $headers, $data, self::PUT, $options);
        }

        public static function options($url, $headers = [], $data = [], $options = [])
        {
            return self::request($url, $headers, $data, self::OPTIONS, $options);
        }

        public static function patch($url, $headers, $data = [], $options = [])
        {
            return self::request($url, $headers, $data, self::PATCH, $options);
        }

        /** One request: hooks around the transport, the raw response parsed, redirects followed. */
        public static function request($url, $headers = [], $data = [], $type = self::GET, $options = [])
        {
            foreach ([[1, '$url', 'string|Stringable', InputValidator::is_string_or_stringable($url), $url], [4, '$type', 'string', is_string($type), $type], [5, '$options', 'array', is_array($options), $options]] as [$position, $label, $expected, $ok, $given]) {
                if (!$ok) {
                    throw InvalidArgument::create($position, $label, $expected, gettype($given));
                }
            }
            if (empty($options['type'])) {
                $options['type'] = $type;
            }
            $options = array_merge(self::OPTION_DEFAULTS, $options);
            self::minn_prepare($url, $headers, $data, $type, $options);
            $options['hooks']->dispatch('requests.before_request', [&$url, &$headers, &$data, &$type, &$options]);
            $raw = self::minn_transport($options)->request($url, $headers, $data, $options);
            $options['hooks']->dispatch('requests.before_parse', [&$raw, $url, $headers, $data, $type, $options]);
            return self::minn_parse((string) $raw, $url, $headers, $data, $options);
        }

        /** Several requests; each answer is a Response, or the exception its transport raised, under the same key. */
        public static function request_multiple($requests, $options = [])
        {
            if (!InputValidator::has_array_access($requests) || !InputValidator::is_iterable($requests)) {
                throw InvalidArgument::create(1, '$requests', 'array|ArrayAccess&Traversable', gettype($requests));
            }
            if (!is_array($options)) {
                throw InvalidArgument::create(2, '$options', 'array', gettype($options));
            }
            $answers = [];
            foreach ($requests as $id => $request) {
                $answers[$id] = self::minn_one_of_many((array) $request, $options);
                $complete = $request['options']['complete'] ?? $options['complete'] ?? null;
                if (is_callable($complete)) {
                    $complete($answers[$id], $id);
                }
            }
            return $answers;
        }

        public static function get_certificate_path()
        {
            return self::$certificate_path;
        }

        public static function set_certificate_path($path)
        {
            if (!InputValidator::is_string_or_stringable($path) && !is_bool($path)) {
                throw InvalidArgument::create(1, '$path', 'string|Stringable|bool', gettype($path));
            }
            self::$certificate_path = $path;
        }

        /** A raw multi-request answer parsed in place (the transport's callback). */
        public static function parse_multiple(&$response, $request)
        {
            try {
                $response = self::minn_parse((string) $response, $request['url'], $request['headers'], $request['data'], $request['options']);
                $request['options']['hooks']->dispatch('multiple.request.complete', [&$response, $request['url']]);
            } catch (Exception $e) {
                $response = $e;
            }
        }

        /** Headers as "Name: value" lines (an array value prints as "Array"). */
        public static function flatten($dictionary)
        {
            if (!InputValidator::is_iterable($dictionary)) {
                throw InvalidArgument::create(1, '$dictionary', 'iterable', gettype($dictionary));
            }
            $lines = [];
            foreach ($dictionary as $name => $value) {
                $lines[] = sprintf('%s: %s', $name, is_array($value) ? 'Array' : $value);
            }
            return $lines;
        }

        public static function decompress($data)
        {
            if (!is_string($data) && !InputValidator::is_stringable_object($data)) {
                throw InvalidArgument::create(1, '$data', 'string|Stringable', gettype($data));
            }
            return RawResponse::inflate((string) $data);
        }

        public static function compatible_gzinflate($gz_data)
        {
            if (!is_string($gz_data) && !InputValidator::is_stringable_object($gz_data)) {
                throw InvalidArgument::create(1, '$gz_data', 'string|Stringable', gettype($gz_data));
            }
            $data = (string) $gz_data;
            $inflated = RawResponse::inflate($data);
            return $inflated !== $data ? $inflated : (@gzinflate($data) ?: false);
        }

        /** Defaults that need objects: an http(s) URL only, hooks, auth, proxy, the cookie jar, an ASCII host. */
        private static function minn_prepare(&$url, &$headers, &$data, &$type, &$options): void
        {
            if (!preg_match('/^https?:\/\//i', (string) $url)) {
                throw new Exception('Only HTTP(S) requests are handled.', 'nonhttp', $url);
            }
            $options['hooks'] ??= new Hooks();
            self::minn_register($options);
            if ($options['idn'] !== false) {
                $iri = new Iri($url);
                $iri->host = IdnaEncoder::encode((string) $iri->ihost);
                $url = $iri->uri;
            }
        }

        /** Basic auth, a proxy and the cookie jar, each made from its option and hooked in. */
        private static function minn_register(array &$options): void
        {
            if (is_array($options['auth'])) {
                $options['auth'] = new Auth\Basic($options['auth']);
            }
            if ($options['auth'] !== false) {
                $options['auth']->register($options['hooks']);
            }
            if (is_string($options['proxy']) || is_array($options['proxy'])) {
                $options['proxy'] = new Proxy\Http($options['proxy']);
            }
            if ($options['proxy'] !== false) {
                $options['proxy']->register($options['hooks']);
            }
            $options['cookies'] = is_array($options['cookies']) ? new Jar($options['cookies']) : ($options['cookies'] ?: new Jar());
            $options['cookies']->register($options['hooks']);
        }

        private static function minn_transport(array $options): Transport
        {
            $transport = $options['transport'] ?? null;
            if (is_string($transport) && class_exists($transport)) {
                return new $transport();
            }
            return $transport instanceof Transport ? $transport : new Transport\Curl();
        }

        /** The raw text as a Response; a redirect is followed while the options allow, and the final answer keeps the ones before it. */
        private static function minn_parse(string $raw, $url, $headers, $data, array $options)
        {
            $response = new Response();
            if (!$options['blocking']) {
                return $response;
            }
            self::minn_fill($response, $raw, (string) $url, (bool) $options['filename']);
            $options['hooks']->dispatch('requests.before_redirect_check', [&$response, $headers, $data, $options]);
            if ($response->is_redirect() && $options['follow_redirects'] === true && $options['redirected'] >= $options['redirects']) {
                throw new Exception('Too many redirects', 'toomanyredirects', $response);
            }
            if ($response->is_redirect() && $options['follow_redirects'] === true && isset($response->headers['location'])) {
                return self::minn_redirect($response, $url, $headers, $data, $options);
            }
            $response->redirects = $options['redirected'];
            $options['hooks']->dispatch('requests.after_request', [&$response, $headers, $data, $options]);
            return $response;
        }

        private static function minn_fill(Response $response, string $raw, string $url, bool $toFile): void
        {
            try {
                $parts = $toFile ? RawResponse::parseHead($raw) : RawResponse::parse($raw);
            } catch (\RuntimeException $e) {
                throw new Exception($e->getMessage(), $e->getCode() === 1 ? 'requests.no_crlf_separator' : 'noversion', $raw);
            }
            $response->raw = $raw;
            $response->url = $url;
            $response->protocol_version = $parts['protocol'];
            $response->status_code = $parts['status'];
            $response->success = $parts['status'] >= 200 && $parts['status'] < 300;
            $response->body = $parts['body'];
            foreach ($parts['headers'] as [$name, $value]) {
                $response->headers[$name] = $value;
            }
            unset($response->headers['connection']);
            if (isset($response->headers['transfer-encoding'])) {
                $response->body = RawResponse::unchunk($response->body);
                unset($response->headers['transfer-encoding']);
            }
            if (isset($response->headers['content-encoding'])) {
                $response->body = RawResponse::inflate($response->body);
            }
        }

        private static function minn_redirect(Response $response, $url, $headers, $data, array $options)
        {
            if ($response->status_code === 303) {
                $options['type'] = self::GET;
            }
            $options['redirected']++;
            $location = (string) $response->headers['location'];
            if (!preg_match('#^https?://#i', $location)) {
                $resolved = Iri::absolutize($url, $location);
                $location = $resolved === false ? $location : $resolved->uri;
            }
            $options['hooks']->dispatch('requests.before_redirect', [&$location, &$headers, &$data, &$options, $response]);
            $next = self::request($location, $headers, $data, $options['type'], $options);
            $next->history[] = $response;
            return $next;
        }

        /** @return Response|Exception */
        private static function minn_one_of_many(array $request, array $options)
        {
            $request += ['headers' => [], 'data' => [], 'type' => self::GET, 'options' => []];
            try {
                return self::request($request['url'] ?? '', $request['headers'], $request['data'], $request['type'], array_merge($options, (array) $request['options']));
            } catch (Exception $e) {
                return $e->getType() === 'curlerror' ? new Exception\Transport\Curl($e->getMessage(), Exception\Transport\Curl::EASY, $e->getData(), $e->getCode()) : $e;
            }
        }
    }

    /** What came back from one request. */
    class Response
    {
        public $body = '';
        public $raw = '';
        public $headers = [];
        public $status_code = false;
        public $protocol_version = false;
        public $success = false;
        public $redirects = 0;
        public $url = '';
        public $history = [];
        public $cookies = [];

        public function __construct()
        {
            $this->headers = new Headers();
            $this->cookies = new Jar();
        }

        /** A 3xx that sends the client on (304 does not). */
        public function is_redirect()
        {
            $code = $this->status_code;
            return in_array($code, [300, 301, 302, 303, 307], true) || (is_int($code) && $code > 307 && $code < 400);
        }

        /** Throws for a redirect when redirects are not allowed, and for any other answer that is not a success. */
        public function throw_for_status($allow_redirects = true)
        {
            if ($this->is_redirect()) {
                if (!$allow_redirects) {
                    throw new Exception('Redirection not allowed', 'response.no_redirects', $this);
                }
            } elseif (!$this->success) {
                $exception = Exception\Http::get_class($this->status_code);
                throw new $exception(null, $this);
            }
        }

        public function decode_body($associative = true, $depth = 512, $options = 0)
        {
            $data = json_decode((string) $this->body, $associative, $depth, $options);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Unable to parse JSON data: ' . json_last_error_msg(), 'response.invalid', $this);
            }
            return $data;
        }
    }

    interface Transport
    {
        public function request($url, $headers = [], $data = [], $options = []);

        public function request_multiple($requests, $options);

        public static function test($capabilities = []);
    }

    /** A base URL, headers, data and options shared by the requests made through it. */
    class Session
    {
        public $url;
        public $headers = [];
        public $data = [];
        public $options = [];

        public function __construct($url = null, $headers = [], $data = [], $options = [])
        {
            if ($url !== null && !InputValidator::is_string_or_stringable($url)) {
                throw InvalidArgument::create(1, '$url', 'string|Stringable|null', gettype($url));
            }
            foreach ([2 => ['$headers', $headers], 3 => ['$data', $data], 4 => ['$options', $options]] as $position => [$name, $value]) {
                if (!is_array($value)) {
                    throw InvalidArgument::create($position, $name, 'array', gettype($value));
                }
            }
            [$this->url, $this->headers, $this->data, $this->options] = [$url, $headers, $data, $options];
            if (empty($this->options['cookies'])) {
                $this->options['cookies'] = new Jar();
            }
        }

        public function __get($name)
        {
            return $this->options[$name] ?? null;
        }

        public function __set($name, $value)
        {
            $this->options[$name] = $value;
        }

        public function __isset($name)
        {
            return isset($this->options[$name]);
        }

        public function __unset($name)
        {
            unset($this->options[$name]);
        }

        public function get($url, $headers = [], $options = [])
        {
            return $this->request($url, $headers, null, Requests::GET, $options);
        }

        public function head($url, $headers = [], $options = [])
        {
            return $this->request($url, $headers, null, Requests::HEAD, $options);
        }

        public function delete($url, $headers = [], $options = [])
        {
            return $this->request($url, $headers, null, Requests::DELETE, $options);
        }

        public function post($url, $headers = [], $data = [], $options = [])
        {
            return $this->request($url, $headers, $data, Requests::POST, $options);
        }

        public function put($url, $headers = [], $data = [], $options = [])
        {
            return $this->request($url, $headers, $data, Requests::PUT, $options);
        }

        public function patch($url, $headers, $data = [], $options = [])
        {
            return $this->request($url, $headers, $data, Requests::PATCH, $options);
        }

        public function request($url, $headers = [], $data = [], $type = Requests::GET, $options = [])
        {
            $request = $this->minn_merge(['url' => $url, 'headers' => $headers, 'data' => $data, 'options' => $options]);
            return Requests::request($request['url'], $request['headers'], $request['data'], $type, $request['options']);
        }

        public function request_multiple($requests, $options = [])
        {
            if (!InputValidator::has_array_access($requests) || !InputValidator::is_iterable($requests)) {
                throw InvalidArgument::create(1, '$requests', 'array|ArrayAccess&Traversable', gettype($requests));
            }
            $merged = [];
            foreach ($requests as $key => $request) {
                $merged[$key] = $this->minn_merge((array) $request, false);
            }
            return Requests::request_multiple($merged, array_merge($this->options, (array) $options));
        }

        public function __wakeup()
        {
            throw new \LogicException(__CLASS__ . ' should never be unserialized');
        }

        /** A request's data under the session's: the session's alone when the request has none, both merged when both are arrays. */
        private function minn_data($data)
        {
            if (empty($data) && is_array($this->data)) {
                return $this->data;
            }
            return is_array($data) && is_array($this->data) ? array_merge($this->data, $data) : $data;
        }

        /** The session's URL, headers, data and (unless told not to) options under the request's own. */
        private function minn_merge(array $request, bool $withOptions = true): array
        {
            if ($this->url !== null) {
                $resolved = Iri::absolutize($this->url, (string) ($request['url'] ?? ''));
                $request['url'] = $resolved === false ? ($request['url'] ?? '') : $resolved->uri;
            }
            $request['headers'] = array_merge($this->headers, (array) ($request['headers'] ?? []));
            $request['data'] = $this->minn_data($request['data'] ?? null);
            if ($withOptions) {
                $request['options'] = array_merge($this->options, (array) ($request['options'] ?? []));
                unset($request['options']['type']);
            }
            return $request;
        }
    }
}

namespace WpOrg\Requests\Transport {
    use Minn\Http\Outbound;
    use Minn\Http\RawResponse;
    use WpOrg\Requests\Capability;
    use WpOrg\Requests\Exception;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Hooks;
    use WpOrg\Requests\Requests;
    use WpOrg\Requests\Transport;
    use WpOrg\Requests\Utility\InputValidator;

    /**
     * Sends through the engine's curl client. The curl.* hooks see the real
     * handle before it is sent; the answer goes back as raw text.
     */
    final class Curl implements Transport
    {
        public const CURL_7_10_5 = 461317;
        public const CURL_7_16_2 = 462850;

        public $headers = '';
        public $response_data = '';
        public $info = [];
        public $version;

        public function __construct()
        {
            $this->version = curl_version()['version_number'] ?? 0;
        }

        public function __destruct()
        {
        }

        public function request($url, $headers = [], $data = [], $options = [])
        {
            $checks = [[1, '$url', 'string|Stringable', InputValidator::is_string_or_stringable($url), $url], [2, '$headers', 'array', is_array($headers), $headers], [3, '$data', 'array|string', is_array($data) || is_string($data) || $data === null, $data], [4, '$options', 'array', is_array($options), $options]];
            foreach ($checks as [$position, $label, $expected, $ok, $given]) {
                if (!$ok) {
                    throw InvalidArgument::create($position, $label, $expected, gettype($given));
                }
            }
            return $this->minn_send((string) $url, $headers, $data, $options);
        }

        /** Sends, fires the after hooks, and returns the raw answer (only the head when the body went to a file). */
        private function minn_send(string $url, array $headers, $data, array $options): string
        {
            $hooks = $options['hooks'] ?? new Hooks();
            $exchange = \Minn\Http::send($this->minn_outbound($url, $headers, $data, $options, $hooks));
            $hooks->dispatch('curl.after_send', []);
            if ($exchange->failed()) {
                throw new Exception(sprintf('cURL error %d: %s', $exchange->errno, $exchange->error), 'curlerror', null);
            }
            $raw = RawResponse::fromExchange($exchange);
            $this->info = ['url' => $url, 'http_code' => $exchange->code];
            $split = (int) strpos($raw, "\r\n\r\n");
            if (!empty($options['filename'])) {
                file_put_contents((string) $options['filename'], $exchange->body);
                $raw = substr($raw, 0, $split);
            } elseif (!empty($options['max_bytes'])) {
                $raw = substr($raw, 0, $split + 4 + (int) $options['max_bytes']);
            }
            $hooks->dispatch('curl.after_request', [&$raw, &$this->info]);
            return $this->headers = $raw;
        }

        public function request_multiple($requests, $options)
        {
            $answers = [];
            foreach ($requests as $id => $request) {
                try {
                    $answers[$id] = $this->request($request['url'], $request['headers'] ?? [], $request['data'] ?? [], $request['options'] ?? $options);
                } catch (Exception $e) {
                    $answers[$id] = $e;
                }
            }
            return $answers;
        }

        public function get_subrequest_handle($url, $headers, $data, $options)
        {
            return curl_init((string) $url);
        }

        public function process_response($response, $options)
        {
            return $response;
        }

        public function stream_headers($handle, $headers)
        {
            $this->headers .= $headers;
            return strlen($headers);
        }

        public function stream_body($handle, $data)
        {
            $this->response_data .= $data;
            return strlen($data);
        }

        public static function test($capabilities = [])
        {
            if (!function_exists('curl_init') || !function_exists('curl_exec')) {
                return false;
            }
            return empty($capabilities[Capability::SSL]) || (bool) (curl_version()['features'] & CURL_VERSION_SSL);
        }

        /** Data goes in the query for GET, HEAD and DELETE and in the body otherwise; curl negotiates compression; the connection closes after. */
        private function minn_outbound(string $url, array $headers, $data, array $options, Hooks $hooks): Outbound
        {
            $method = strtoupper((string) ($options['type'] ?? Requests::GET));
            $body = null;
            if (in_array($method, [Requests::GET, Requests::HEAD, Requests::DELETE], true)) {
                if (!empty($data)) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . (is_array($data) ? http_build_query($data, '', '&') : $data);
                }
            } elseif ($data !== null && $data !== []) {
                $body = is_array($data) ? http_build_query($data, '', '&') : (string) $data;
            }
            $headers += ['Connection' => 'close'];
            $verify = $options['verify'] ?? Requests::get_certificate_path();
            $prepare = static function (\CurlHandle $handle) use ($hooks): void {
                curl_setopt($handle, CURLOPT_ENCODING, '');
                $hooks->dispatch('curl.before_request', [&$handle]);
                $hooks->dispatch('curl.before_send', [&$handle]);
            };
            return new Outbound($method, $url, Requests::flatten($headers), $body, (float) $options['timeout'], 0, $verify !== false, (string) $options['useragent'], is_string($verify) ? $verify : null, (bool) $options['blocking'], $prepare, (float) $options['connect_timeout']);
        }
    }

    /** The socket transport's name; requests through it go out the same way as through curl. */
    final class Fsockopen implements Transport
    {
        public const SECOND_IN_MICROSECONDS = 1000000;

        public $headers = '';
        public $info = [];

        public function request($url, $headers = [], $data = [], $options = [])
        {
            return (new Curl())->request($url, $headers, $data, $options);
        }

        public function request_multiple($requests, $options)
        {
            return (new Curl())->request_multiple($requests, $options);
        }

        public function connect_error_handler($errno, $errstr)
        {
            return false;
        }

        public function verify_certificate_from_context($host, $context)
        {
            return true;
        }

        public static function test($capabilities = [])
        {
            return function_exists('fsockopen') && (empty($capabilities[Capability::SSL]) || extension_loaded('openssl'));
        }
    }
}
