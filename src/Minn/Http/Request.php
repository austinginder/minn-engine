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
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
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
            secure: ($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off',
            host: (string) ($_SERVER['HTTP_HOST'] ?? ''),
        );
    }

    /** The same request addressed to another path (a REST route carried in ?rest_route=). */
    public function withPath(string $path): self
    {
        return new self($this->method, $path, $this->query, $this->headers, $this->cookies, $this->body, $this->secure, $this->host);
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

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
