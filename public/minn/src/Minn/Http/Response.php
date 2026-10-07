<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;

/**
 * What a handler returns. Nothing is written to the client until the
 * kernel calls send(), so a response can be inspected, wrapped, or
 * replaced on the way out. Work that belongs after the client has its
 * answer (a cron run a page found due) rides along as afterSend closures.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers
     * @param list<array{0: string, 1: string, 2: array}> $cookies name, value, setcookie options
     * @param list<Closure(): void> $afterSend run once the response has been handed to the client
     */
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = '',
        public array $cookies = [],
        public array $afterSend = [],
    ) {
    }

    /** An HTML response. */
    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
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

    /** The same response with one header set, after those already set (one already there keeps its place). */
    public function withHeader(string $name, string $value): self
    {
        return new self($this->status, array_replace($this->headers, [$name => $value]), $this->body, $this->cookies, $this->afterSend);
    }

    /** The same response without one header. */
    public function withoutHeader(string $name): self
    {
        return new self($this->status, array_diff_key($this->headers, [$name => true]), $this->body, $this->cookies, $this->afterSend);
    }

    /**
     * The same response with a cookie to set.
     *
     * @param array<string, mixed> $options setcookie options: expires, path, secure, httponly, samesite
     */
    public function withCookie(string $name, string $value, array $options): self
    {
        return new self($this->status, $this->headers, $this->body, [...$this->cookies, [$name, $value, $options]], $this->afterSend);
    }

    /** The same response with an empty body, the HEAD answer. */
    public function withoutBody(): self
    {
        return new self($this->status, $this->headers, '', $this->cookies, $this->afterSend);
    }

    /** The same response with work to run once the client has been answered. @param Closure(): void $work */
    public function afterSend(Closure $work): self
    {
        return new self($this->status, $this->headers, $this->body, $this->cookies, [...$this->afterSend, $work]);
    }

    /**
     * One header line sent now, as plugin code sends one, ahead of the
     * response's own (which win on a shared name). Nothing outside a web
     * request, or once the head is out.
     */
    public static function emitHeader(string $line): void
    {
        if (!headers_sent() && PHP_SAPI !== 'cli') {
            header($line);
        }
    }

    /**
     * Writes the status, the headers, and the cookies now, and nothing else:
     * for an answer that code outside the engine may finish on its own (an
     * ajax handler that ends the request), which must find them already said.
     */
    public function sendHead(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        foreach ($this->cookies as [$name, $value, $options]) {
            setcookie($name, $value, $options);
        }
    }

    /**
     * Writes the status, the headers, the cookies, and the body, ends the
     * request for the client, then runs the after-send work in the same
     * process (the server that can close the connection first does).
     */
    public function send(): void
    {
        $this->sendHead();
        echo $this->body;
        if ($this->afterSend === []) {
            return;
        }
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }
        foreach ($this->afterSend as $work) {
            $work();
        }
    }
}
