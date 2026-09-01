<?php

declare(strict_types=1);

namespace Minn\Http;

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
        return new self(
            method: Method::fromName($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: is_string($path) && $path !== '' ? $path : '/',
            query: $_GET,
            headers: $headers,
            cookies: array_map(strval(...), $_COOKIE),
            body: (string) file_get_contents('php://input'),
            // The scheme is the server's word, never a request header's.
            secure: (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443',
            host: (string) ($_SERVER['HTTP_HOST'] ?? ''),
            form: $_POST,
            files: $_FILES,
            remoteAddress: (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            server: [
                'software' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? ''),
                'protocol' => (string) ($_SERVER['SERVER_PROTOCOL'] ?? ''),
                'address' => (string) ($_SERVER['SERVER_ADDR'] ?? ''),
            ],
        );
    }

    /** The JSON body as an array, or the form fields when the body is empty. */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return $this->form;
        }
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
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
}
