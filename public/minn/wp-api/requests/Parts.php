<?php
/**
 * The Requests library's parts around a request: hooks, host-name and
 * address helpers (IDNA, IPv6, IRIs, ports, certificate names), cookies
 * and the cookie jar, basic authentication and HTTP proxies. The logic
 * lives in Minn\Http; these classes carry the names and shapes plugins use.
 */

namespace WpOrg\Requests {
    use Minn\Http\CertificateName;
    use Minn\Http\CookieText;
    use Minn\Http\IriParts;
    use Minn\Http\Ipv6 as MinnIpv6;
    use Minn\Http\Punycode;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Utility\CaseInsensitiveDictionary;
    use WpOrg\Requests\Utility\InputValidator;

    interface Capability
    {
        public const SSL = 'ssl';
        public const ALL = [self::SSL];
    }

    interface HookManager
    {
        public function register($hook, $callback, $priority = 0);

        public function dispatch($hook, $parameters = []);
    }

    /** Callbacks by hook and priority (lower first); parameters reach them by reference. */
    class Hooks implements HookManager
    {
        protected $hooks = [];

        public function register($hook, $callback, $priority = 0)
        {
            if (!is_string($hook)) {
                throw InvalidArgument::create(1, '$hook', 'string', gettype($hook));
            }
            if (!is_callable($callback)) {
                throw InvalidArgument::create(2, '$callback', 'callable', gettype($callback));
            }
            if (!is_int($priority)) {
                throw InvalidArgument::create(3, '$priority', 'integer', gettype($priority));
            }
            $this->hooks[$hook][$priority][] = $callback;
        }

        public function dispatch($hook, $parameters = [])
        {
            foreach ([[1, '$hook', 'string', is_string($hook), $hook], [2, '$parameters', 'array', is_array($parameters), $parameters]] as [$position, $label, $type, $ok, $given]) {
                if (!$ok) {
                    throw InvalidArgument::create($position, $label, $type, gettype($given));
                }
            }
            if (empty($this->hooks[$hook])) {
                return false;
            }
            $byPriority = $this->hooks[$hook];
            ksort($byPriority);
            foreach ($byPriority as $callbacks) {
                foreach ($callbacks as $callback) {
                    call_user_func_array($callback, $parameters);
                }
            }
            return true;
        }

        public function __wakeup()
        {
            throw new \LogicException(__CLASS__ . ' should never be unserialized');
        }
    }

    /** The engine's autoloader already knows these classes; registering is a formality. */
    final class Autoload
    {
        public static function register()
        {
        }

        public static function load($class_name)
        {
            return class_exists((string) $class_name) || interface_exists((string) $class_name);
        }
    }

    final class Port
    {
        public const ACAP = 674;
        public const DICT = 2628;
        public const HTTP = 80;
        public const HTTPS = 443;

        public static function get($type)
        {
            if (!is_string($type)) {
                throw InvalidArgument::create(1, '$type', 'string', gettype($type));
            }
            $type = strtoupper($type);
            if (!defined(self::class . '::' . $type)) {
                throw new Exception(sprintf('Invalid port type (%s) passed', $type), 'portnotsupported');
            }
            return constant(self::class . '::' . $type);
        }
    }

    final class Ssl
    {
        public static function verify_certificate($host, $cert)
        {
            if (!InputValidator::is_string_or_stringable($host)) {
                throw InvalidArgument::create(1, '$host', 'string|Stringable', gettype($host));
            }
            if (!InputValidator::has_array_access($cert)) {
                throw InvalidArgument::create(2, '$cert', 'array|ArrayAccess', gettype($cert));
            }
            return CertificateName::certificateMatches((string) $host, (array) $cert);
        }

        public static function verify_reference_name($reference)
        {
            if (!InputValidator::is_string_or_stringable($reference)) {
                throw InvalidArgument::create(1, '$reference', 'string|Stringable', gettype($reference));
            }
            return CertificateName::valid((string) $reference);
        }

        public static function match_domain($host, $reference)
        {
            if (!InputValidator::is_string_or_stringable($host)) {
                throw InvalidArgument::create(1, '$host', 'string|Stringable', gettype($host));
            }
            return CertificateName::matches((string) $host, (string) $reference);
        }
    }

    /** Host names in ASCII (Punycode, no Nameprep). */
    class IdnaEncoder
    {
        public const ACE_PREFIX = 'xn--';
        public const MAX_LENGTH = 64;
        public const BOOTSTRAP_BASE = 36;
        public const BOOTSTRAP_TMIN = 1;
        public const BOOTSTRAP_TMAX = 26;
        public const BOOTSTRAP_SKEW = 38;
        public const BOOTSTRAP_DAMP = 700;
        public const BOOTSTRAP_INITIAL_BIAS = 72;
        public const BOOTSTRAP_INITIAL_N = 128;

        public static function encode($hostname)
        {
            if (!InputValidator::is_string_or_stringable($hostname)) {
                throw InvalidArgument::create(1, '$hostname', 'string|Stringable', gettype($hostname));
            }
            return implode('.', array_map([static::class, 'to_ascii'], explode('.', (string) $hostname)));
        }

        public static function to_ascii($text)
        {
            try {
                return Punycode::label((string) $text);
            } catch (\InvalidArgumentException $e) {
                throw new Exception($e->getMessage(), $e->getCode() === 1 ? 'idna.provided_too_long' : 'idna.encoded_too_long', $text);
            }
        }

        public static function punycode_encode($input)
        {
            return Punycode::encode((string) $input);
        }
    }

    final class Ipv6
    {
        public static function uncompress($ip)
        {
            if (!InputValidator::is_string_or_stringable($ip)) {
                throw InvalidArgument::create(1, '$ip', 'string|Stringable', gettype($ip));
            }
            return MinnIpv6::expand((string) $ip);
        }

        public static function compress($ip)
        {
            if (!InputValidator::is_string_or_stringable($ip)) {
                throw InvalidArgument::create(1, '$ip', 'string|Stringable', gettype($ip));
            }
            return MinnIpv6::compress((string) $ip);
        }

        public static function check_ipv6($ip)
        {
            if (!InputValidator::is_string_or_stringable($ip)) {
                throw InvalidArgument::create(1, '$ip', 'string|Stringable', gettype($ip));
            }
            return MinnIpv6::valid((string) $ip);
        }
    }

    /**
     * An IRI read into its parts. Reading ->iri, ->uri and the parts
     * (scheme, userinfo, host, port, path, query, fragment, and the i-
     * forms) gives them normalized; setting a part rebuilds the IRI.
     */
    class Iri
    {
        private IriParts $minn_parts;

        public function __construct($iri = null)
        {
            if ($iri !== null && !InputValidator::is_string_or_stringable($iri)) {
                throw InvalidArgument::create(1, '$iri', 'string|Stringable|null', gettype($iri));
            }
            $this->minn_parts = IriParts::parse((string) $iri);
        }

        public function __toString(): string
        {
            return $this->minn_parts->toIri();
        }

        public function __get($name)
        {
            $p = $this->minn_parts;
            return match ($name) {
                'iri' => $p->toIri(),
                'uri' => $p->toUri(),
                'scheme' => $p->scheme,
                'userinfo', 'iuserinfo' => $p->userinfo,
                'host', 'ihost' => $p->host,
                'port' => $p->port,
                'path', 'ipath' => $p->path,
                'query', 'iquery' => $p->query,
                'fragment', 'ifragment' => $p->fragment,
                'authority', 'iauthority' => $p->host === null ? null : ($p->userinfo === null ? '' : $p->userinfo . '@') . $p->host . ($p->port === null ? '' : ':' . $p->port),
                default => null,
            };
        }

        public function __set($name, $value)
        {
            $p = $this->minn_parts;
            $this->minn_parts = match ($name) {
                'host', 'ihost' => $p->withHost($value === null ? null : strtolower((string) $value)),
                'path', 'ipath' => $p->withPath((string) $value),
                'iri', 'uri' => IriParts::parse((string) $value),
                default => $p,
            };
        }

        public function __isset($name)
        {
            return in_array($name, ['iri', 'uri'], true);
        }

        public function __unset($name)
        {
        }

        /** The relative reference resolved against the base, or false when the base cannot be one. */
        public static function absolutize($base, $relative)
        {
            $base = $base instanceof self ? $base : new self($base);
            $relative = $relative instanceof self ? $relative : new self($relative);
            $resolved = $base->minn_parts->resolve($relative->minn_parts);
            return $resolved === null ? false : new self($resolved->toIri());
        }

        public function is_valid()
        {
            return $this->minn_parts->valid();
        }

        public function __wakeup()
        {
            $this->minn_parts = IriParts::parse('');
        }
    }

    /** One cookie: name, value, attributes (normalized) and flags, judged against a domain, path and URI. */
    class Cookie
    {
        public $name;
        public $value;
        public $attributes = [];
        public $flags = [];
        public $reference_time = 0;

        public function __construct($name, $value, $attributes = [], $flags = [], $reference_time = null)
        {
            $listLike = InputValidator::has_array_access($attributes) && InputValidator::is_iterable($attributes);
            foreach ([[1, '$name', 'string', is_string($name), $name], [2, '$value', 'string', is_string($value), $value], [3, '$attributes', 'array|ArrayAccess&Traversable', $listLike, $attributes], [4, '$flags', 'array', is_array($flags), $flags]] as [$position, $label, $type, $ok, $given]) {
                if (!$ok) {
                    throw InvalidArgument::create($position, $label, $type, gettype($given));
                }
            }
            $this->name = $name;
            $this->value = $value;
            $this->attributes = is_array($attributes) ? new CaseInsensitiveDictionary($attributes) : $attributes;
            $this->flags = array_merge(['creation' => time(), 'last-access' => time(), 'persistent' => false, 'host-only' => true], $flags);
            $this->reference_time = $reference_time === null ? time() : (int) $reference_time;
            $this->normalize();
        }

        public function __toString(): string
        {
            return (string) $this->value;
        }

        public function is_expired()
        {
            $limit = $this->attributes['max-age'] ?? $this->attributes['expires'] ?? null;
            return $limit !== null && (int) $limit < $this->reference_time;
        }

        public function uri_matches(Iri $uri)
        {
            $secure = !empty($this->attributes['secure']);
            return $this->domain_matches((string) $uri->host) && $this->path_matches((string) $uri->path) && (!$secure || $uri->scheme === 'https');
        }

        public function domain_matches($domain)
        {
            if (!is_string($domain)) {
                return false;
            }
            $cookieDomain = isset($this->attributes['domain']) ? (string) $this->attributes['domain'] : null;
            return $this->flags['host-only'] ? CookieText::hostMatches($cookieDomain, $domain) : CookieText::domainMatches($cookieDomain, $domain);
        }

        public function path_matches($request_path)
        {
            $cookiePath = $this->attributes['path'] ?? null;
            return CookieText::pathMatches($cookiePath === null ? null : (string) $cookiePath, (string) $request_path);
        }

        public function normalize()
        {
            foreach ($this->attributes as $key => $value) {
                $normalized = CookieText::normalizeAttribute((string) $key, $value, (int) $this->reference_time);
                if ($normalized === null) {
                    unset($this->attributes[$key]);
                } else {
                    $this->attributes[$key] = $normalized;
                }
            }
            return true;
        }

        public function format_for_header()
        {
            return sprintf('%s=%s', $this->name, $this->value);
        }

        /** The cookie as a Set-Cookie value; any attributes object adds "; " even when it is empty, as on the reference. */
        public function format_for_set_cookie()
        {
            $parts = [];
            foreach ($this->attributes as $key => $value) {
                $parts[] = is_numeric($key) ? (string) $value : sprintf('%s=%s', $key, $value === true ? '1' : $value);
            }
            return $this->format_for_header() . (empty($this->attributes) ? '' : '; ' . implode('; ', $parts));
        }

        public static function parse($cookie_header, $name = '', $reference_time = null)
        {
            if (!is_string($cookie_header)) {
                throw InvalidArgument::create(1, '$cookie_header', 'string', gettype($cookie_header));
            }
            if (!is_string($name)) {
                throw InvalidArgument::create(2, '$name', 'string', gettype($name));
            }
            $parsed = CookieText::parse($cookie_header, $name);
            return new static($parsed['name'], $parsed['value'], $parsed['attributes'], [], $reference_time);
        }

        /** The cookies a response sets, each given the origin's host and path when it names none, kept only when the origin may set it. */
        public static function parse_from_headers(Response\Headers $headers, $origin = null, $time = null)
        {
            $cookies = [];
            foreach ((array) $headers->getValues('Set-Cookie') as $header) {
                $cookie = static::parse((string) $header, '', $time);
                $cookie->flags['host-only'] = empty($cookie->attributes['domain']);
                if (empty($cookie->attributes['domain']) && $origin instanceof Iri) {
                    $cookie->attributes['domain'] = $origin->host;
                }
                $path = (string) ($cookie->attributes['path'] ?? '');
                if (($path === '' || $path[0] !== '/') && $origin instanceof Iri) {
                    $cookie->attributes['path'] = CookieText::defaultPath((string) $origin->path);
                }
                if ($origin instanceof Iri && !$cookie->domain_matches((string) $origin->host)) {
                    continue;
                }
                $cookies[$cookie->name] = $cookie;
            }
            return $cookies;
        }
    }

    interface Auth
    {
        public function register(Hooks $hooks);
    }

    interface Proxy
    {
        public function register(Hooks $hooks);
    }
}

namespace WpOrg\Requests\Cookie {
    use ArrayIterator;
    use ReturnTypeWillChange;
    use WpOrg\Requests\Cookie;
    use WpOrg\Requests\Exception;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\HookManager;
    use WpOrg\Requests\Iri;
    use WpOrg\Requests\Response;

    /** Cookies by name, kept as given (a string or a Cookie); sent with matching requests, filled from redirects' Set-Cookie. */
    class Jar implements \ArrayAccess, \IteratorAggregate
    {
        protected $cookies = [];

        public function __construct($cookies = [])
        {
            if (!is_array($cookies)) {
                throw InvalidArgument::create(1, '$cookies', 'array', gettype($cookies));
            }
            $this->cookies = $cookies;
        }

        public function normalize_cookie($cookie, $key = '')
        {
            return $cookie instanceof Cookie ? $cookie : Cookie::parse((string) $cookie, (string) $key);
        }

        #[ReturnTypeWillChange]
        public function offsetExists($offset)
        {
            return isset($this->cookies[$offset]);
        }

        #[ReturnTypeWillChange]
        public function offsetGet($offset)
        {
            return $this->cookies[$offset] ?? null;
        }

        #[ReturnTypeWillChange]
        public function offsetSet($offset, $value)
        {
            if ($offset === null) {
                throw new Exception('Object is a dictionary, not a list', 'invalidset');
            }
            $this->cookies[$offset] = $value;
        }

        #[ReturnTypeWillChange]
        public function offsetUnset($offset)
        {
            unset($this->cookies[$offset]);
        }

        #[ReturnTypeWillChange]
        public function getIterator()
        {
            return new ArrayIterator($this->cookies);
        }

        public function register(HookManager $hooks)
        {
            $hooks->register('requests.before_request', [$this, 'before_request']);
            $hooks->register('requests.before_redirect_check', [$this, 'before_redirect_check']);
        }

        public function before_request($url, &$headers, &$data, &$type, &$options)
        {
            $url = $url instanceof Iri ? $url : new Iri($url);
            if ($this->cookies === []) {
                return;
            }
            $sent = [];
            foreach ($this->cookies as $key => $cookie) {
                $cookie = $this->normalize_cookie($cookie, (string) $key);
                if (!$cookie->is_expired() && $cookie->domain_matches((string) $url->host)) {
                    $sent[] = $cookie->format_for_header();
                }
            }
            $headers['Cookie'] = implode('; ', $sent);
        }

        public function before_redirect_check(Response $response)
        {
            $url = $response->url instanceof Iri ? $response->url : new Iri($response->url);
            $this->cookies = array_merge($this->cookies, Cookie::parse_from_headers($response->headers, $url));
            $response->cookies = $this;
        }
    }
}

namespace WpOrg\Requests\Auth {
    use WpOrg\Requests\Auth;
    use WpOrg\Requests\Exception\ArgumentCount;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Hooks;

    class Basic implements Auth
    {
        public $user;
        public $pass;

        public function __construct($args = null)
        {
            if (is_array($args)) {
                if (count($args) !== 2) {
                    throw ArgumentCount::create('an array with exactly two elements', count($args), 'authbasicbadargs');
                }
                [$this->user, $this->pass] = array_values($args);
                return;
            }
            if ($args !== null) {
                throw InvalidArgument::create(1, '$args', 'array|null', gettype($args));
            }
        }

        public function register(Hooks $hooks)
        {
            $hooks->register('curl.before_send', [$this, 'curl_before_send']);
            $hooks->register('fsockopen.after_headers', [$this, 'fsockopen_header']);
        }

        public function curl_before_send(&$handle)
        {
            curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($handle, CURLOPT_USERPWD, $this->getAuthString());
        }

        public function fsockopen_header(&$out)
        {
            $out .= sprintf("Authorization: Basic %s\r\n", base64_encode($this->getAuthString()));
        }

        public function getAuthString()
        {
            return $this->user . ':' . $this->pass;
        }
    }
}

namespace WpOrg\Requests\Proxy {
    use WpOrg\Requests\Exception\ArgumentCount;
    use WpOrg\Requests\Exception\InvalidArgument;
    use WpOrg\Requests\Hooks;
    use WpOrg\Requests\Proxy;

    final class Http implements Proxy
    {
        public $proxy;
        public $user;
        public $pass;
        public $use_authentication = false;

        public function __construct($args = null)
        {
            if (is_string($args)) {
                $this->proxy = $args;
            } elseif (is_array($args)) {
                if (count($args) === 1) {
                    [$this->proxy] = array_values($args);
                } elseif (count($args) === 3) {
                    [$this->proxy, $this->user, $this->pass] = array_values($args);
                    $this->use_authentication = true;
                } else {
                    throw ArgumentCount::create('an array with exactly one element or exactly three elements', count($args), 'proxyhttpbadargs');
                }
            } elseif ($args !== null) {
                throw InvalidArgument::create(1, '$args', 'array|string|null', gettype($args));
            }
        }

        public function register(Hooks $hooks)
        {
            $hooks->register('curl.before_send', [$this, 'curl_before_send']);
            $hooks->register('fsockopen.remote_socket', [$this, 'fsockopen_remote_socket']);
            $hooks->register('fsockopen.remote_host_path', [$this, 'fsockopen_remote_host_path']);
            if ($this->use_authentication) {
                $hooks->register('fsockopen.after_headers', [$this, 'fsockopen_header']);
            }
        }

        public function curl_before_send(&$handle)
        {
            curl_setopt($handle, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($handle, CURLOPT_PROXY, $this->proxy);
            if ($this->use_authentication) {
                curl_setopt($handle, CURLOPT_PROXYAUTH, CURLAUTH_ANY);
                curl_setopt($handle, CURLOPT_PROXYUSERPWD, $this->get_auth_string());
            }
        }

        public function fsockopen_remote_socket(&$remote_socket)
        {
            $remote_socket = $this->proxy;
        }

        public function fsockopen_remote_host_path(&$path, $url)
        {
            $path = $url;
        }

        public function fsockopen_header(&$out)
        {
            $out .= sprintf("Proxy-Authorization: Basic %s\r\n", base64_encode($this->get_auth_string()));
        }

        public function get_auth_string()
        {
            return $this->user . ':' . $this->pass;
        }
    }
}
