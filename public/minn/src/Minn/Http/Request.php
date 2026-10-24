<?php

declare(strict_types=1);

namespace Minn\Http;

use Minn\Json;

/**
 * An immutable picture of the incoming request. Built once from the PHP
 * globals at the edge; handlers only ever see this object.
 */
final readonly class Request
{
    /**
     * @param array<string, string|array> $query
     * @param array<string, string> $headers lower-cased names
     * @param array<string, string> $cookies
     * @param array<string, mixed> $form decoded form fields, for bodies that are not JSON
     * @param array<string, array> $files uploaded files, keyed by field
     */
    public function __construct(
        public Method $method,
        public string $path,
        public array $query,
        public array $headers,
        public array $cookies,
        public string $body,
        public bool $secure,
        public string $host,
        public array $form = [],
        public array $files = [],
        public string $remoteAddress = '',
        /** what the server says about itself: software, protocol, and address; diagnostics only */
        public array $server = [],
    ) {
    }

    /**
     * A server variable as PHP holds it now, null when unset: it may have
     * changed since the request was read (a proxy plugin marking HTTPS, the
     * WordPress layer filling in REQUEST_URI).
     */
    public static function serverVariable(string $name): mixed
    {
        return $_SERVER[$name] ?? null;
    }

    /** A request variable (query or form) as PHP holds it now, null when unset. */
    public static function requestVariable(string $name): mixed
    {
        return $_REQUEST[$name] ?? null;
    }

    /** A query variable as PHP holds it now, null when unset: plugin code may have set it since the request was read. */
    public static function queryVariable(string $name): mixed
    {
        return $_GET[$name] ?? null;
    }

    /** A cookie as PHP holds it now, null when unset: plugin code may have set it since the request was read. */
    public static function cookieVariable(string $name): mixed
    {
        return $_COOKIE[$name] ?? null;
    }

    /**
     * Runs plugin code that reads its fields from the posted form (a widget
     * control's update callback) with these fields as $_POST and $_REQUEST;
     * both are as they were after.
     *
     * @param array<string, mixed> $fields
     */
    public static function withPostedForm(array $fields, \Closure $run): mixed
    {
        [$post, $request] = [$_POST, $_REQUEST];
        [$_POST, $_REQUEST] = [$fields, $fields];
        try {
            return $run();
        } finally {
            [$_POST, $_REQUEST] = [$post, $request];
        }
    }

    /** The request PHP received, read once from the superglobals. */
    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // The path is everything before the query; parse_url would read a leading "//" as an authority.
        $path = strstr($uri, '?', true);
        $path = $path === false ? $uri : $path;
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        // The scheme and the client's address are the server's word, never a
        // request header's, unless the site named the proxy in front of it.
        $proxies = TrustedProxies::configured();
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
        return new self(
            method: Method::fromName($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: is_string($path) && $path !== '' ? $path : '/',
            query: $_GET,
            headers: $headers,
            cookies: array_map(strval(...), $_COOKIE),
            body: (string) file_get_contents('php://input'),
            secure: $secure || $proxies->forwardedSecure($remoteAddress, $headers['x-forwarded-proto'] ?? ''),
            host: (string) ($_SERVER['HTTP_HOST'] ?? ''),
            form: $_POST,
            files: $_FILES,
            remoteAddress: $proxies->clientAddress($remoteAddress, $headers['x-forwarded-for'] ?? ''),
            server: [
                'software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
                'protocol' => (string) ($_SERVER['SERVER_PROTOCOL'] ?? ''),
                'address' => (string) ($_SERVER['SERVER_ADDR'] ?? ''),
            ],
        );
    }

    /** The JSON body as an array, or the form fields when the body is empty or a form. */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return $this->form;
        }
        $decoded = Json::decode($this->body, or: null);
        if (is_array($decoded)) {
            return $decoded;
        }
        // A form-encoded body (a form post, or a plugin's in-process call with body params) reads as its fields.
        if ($this->form !== []) {
            return $this->form;
        }
        if (str_contains(strtolower((string) $this->header('content-type')), 'application/x-www-form-urlencoded')) {
            parse_str($this->body, $fields);
            return $fields;
        }
        return [];
    }

    /**
     * The same request with its query's arguments read beneath its body's,
     * as the reference's REST request reads a write's parameters: a JSON or
     * form body names an argument first and the query fills in the rest. A
     * raw body (an upload) is left as it came.
     *
     * @param list<string> $reserved query keys that address the request rather than name an argument
     */
    public function withQueryBeneathBody(array $reserved): self
    {
        $query = array_diff_key($this->query, array_flip($reserved));
        if ($query === []) {
            return $this;
        }
        $body = $this->body;
        $form = $this->form;
        $decoded = trim($body) === '' || $form !== [] ? null : Json::decode($body, or: null);
        if (trim($body) === '' || $form !== []) {
            $form += $query;
        } elseif (is_array($decoded) && ($decoded === [] || !array_is_list($decoded))) {
            $body = Json::encode($decoded + $query);
        } elseif (str_contains(strtolower((string) $this->header('content-type')), 'application/x-www-form-urlencoded')) {
            parse_str($body, $fields);
            $form = $fields + $query;
        } else {
            return $this;
        }
        return new self($this->method, $this->path, $this->query, $this->headers, $this->cookies, $body, $this->secure, $this->host, $form, $this->files, $this->remoteAddress, $this->server);
    }

    /** The same request addressed to another path (a REST route carried in ?rest_route=). */
    public function withPath(string $path): self
    {
        return new self($this->method, $path, $this->query, $this->headers, $this->cookies, $this->body, $this->secure, $this->host, $this->form, $this->files, $this->remoteAddress, $this->server);
    }

    /** One query value as a string, or the default when it is absent or not a string. */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** A query value read as the reference reads a boolean argument: true, 1, yes, on; anything else is false. */
    public function flag(string $key): bool
    {
        return filter_var($this->query($key, ''), FILTER_VALIDATE_BOOLEAN);
    }

    /** A boolean the query or the body sets, as a DELETE may carry force in either (probes rest-posts-write, rest-terms-controller). */
    public function flagged(string $key): bool
    {
        return $this->flag($key) || filter_var($this->json()[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** Whether the query carries this key at all, even empty. */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    /** A request header by case-insensitive name, or null. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** A cookie's value, or null. */
    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** The path split into non-empty segments: "/a/b/" becomes ["a", "b"]. */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), static fn (string $s) => $s !== ''));
    }

    /** The query string with the given keys removed, ready to append to a redirect. */
    public function queryStringWithout(string ...$keys): string
    {
        $kept = array_diff_key($this->query, array_flip($keys));
        return $kept === [] ? '' : '?' . http_build_query($kept);
    }

    /**
     * The server variables some hosts leave out or get wrong, set as the
     * reference's boot sets them (wp_fix_server_vars) before anything loads:
     * an empty software name and request URI first, the request URI built
     * from IIS's headers or the script and path info, a php.cgi script file
     * and path info corrected, PHP_SELF from the request URI (and the global
     * of that name), and Basic credentials from the Authorization header.
     * Under WP-CLI that leaves REQUEST_URI an empty string, which plugins
     * read without checking it is set.
     */
    public static function fixServerVars(): void
    {
        $_SERVER = array_merge(['SERVER_SOFTWARE' => '', 'REQUEST_URI' => ''], $_SERVER);
        if (empty($_SERVER['REQUEST_URI']) || (PHP_SAPI !== 'cgi-fcgi' && preg_match('/^Microsoft-IIS\//', (string) $_SERVER['SERVER_SOFTWARE']))) {
            $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'] ?? $_SERVER['HTTP_X_REWRITE_URL'] ?? self::uriFromParts();
        }
        if (isset($_SERVER['SCRIPT_FILENAME']) && str_ends_with((string) $_SERVER['SCRIPT_FILENAME'], 'php.cgi')) {
            $_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'] ?? $_SERVER['SCRIPT_FILENAME'];
        }
        if (isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], 'php.cgi')) {
            unset($_SERVER['PATH_INFO']);
        }
        if (empty($_SERVER['PHP_SELF'])) {
            $_SERVER['PHP_SELF'] = preg_replace('/(\?.*)?$/', '', (string) $_SERVER['REQUEST_URI']);
        }
        $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'];
        self::basicAuthFromHeader();
    }

    /** Basic credentials from an Authorization header into PHP_AUTH_USER and PHP_AUTH_PW, when PHP did not set them (wp_populate_basic_auth_from_authorization_header). */
    public static function basicAuthFromHeader(): void
    {
        $credentials = self::basicCredentials($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
        $unset = !isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_PW']);
        if ($unset && $credentials !== null) {
            [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] = $credentials;
        }
    }

    /**
     * The user and password a Basic Authorization header carries (split at
     * the first colon), or null for any other header or one that does not
     * decode.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function basicCredentials(mixed $header): ?array
    {
        if (!is_string($header) || strncasecmp($header, 'basic ', 6) !== 0) {
            return null;
        }
        $decoded = base64_decode(substr($header, 6), true);
        return is_string($decoded) && str_contains($decoded, ':') ? explode(':', $decoded, 2) : null;
    }

    /** A request URI from the script name, the path info (or its original, which fills it in) and the query string. */
    private static function uriFromParts(): string
    {
        if (isset($_SERVER['ORIG_PATH_INFO'])) {
            $_SERVER['PATH_INFO'] ??= $_SERVER['ORIG_PATH_INFO'];
        }
        $query = empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING'];
        $path = $_SERVER['PATH_INFO'] ?? null;
        if ($path === null) {
            return $query;
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        return ($path === $script ? '' : $script) . $path . $query;
    }
}
