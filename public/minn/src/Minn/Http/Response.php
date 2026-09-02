<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * What a handler returns. Nothing is written to the client until the
 * kernel calls send(), so a response can be inspected, wrapped, or
 * replaced on the way out.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     * @param list<array{0: string, 1: string, 2: array}> $cookies name, value, setcookie options
     */
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = '',
        public array $cookies = [],
    ) {
    }

    /** An HTML response. */
    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    /** A JSON response with the payload encoded. */
    public static function json(mixed $payload, int $status = 200): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /** A redirect to a location. */
    public static function redirect(string $location, int $status = 301): self
    {
        return new self($status, ['Location' => $location], '');
    }

    /** The same response with one header set. */
    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, [$name => $value] + $this->headers, $this->body, $this->cookies);
    }

    /**
     * The same response with a cookie to set.
     *
     * @param array<string, mixed> $options setcookie options: expires, path, secure, httponly, samesite
     */
    public function withCookie(string $name, string $value, array $options): self
    {
        return new self($this->status, $this->headers, $this->body, [...$this->cookies, [$name, $value, $options]]);
    }

    /** The same response with an empty body, the HEAD answer. */
    public function withoutBody(): self
    {
        return new self($this->status, $this->headers, '', $this->cookies);
    }

    /** Writes the status, the headers, the cookies, and the body, and ends the request. */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        foreach ($this->cookies as [$name, $value, $options]) {
            setcookie($name, $value, $options);
        }
        echo $this->body;
    }
}
