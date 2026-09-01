<?php

declare(strict_types=1);

namespace Minn\Http;

/** What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error. */
final readonly class Exchange
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param list<string> $cookies raw Set-Cookie header values
     */
    public function __construct(public int $code, public array $headers, public array $cookies, public string $body, public ?string $error = null)
    {
    }

    public function failed(): bool
    {
        return $this->error !== null;
    }

    /** A response arrived and it was a 2xx. */
    public function ok(): bool
    {
        return $this->error === null && $this->code >= 200 && $this->code < 300;
    }

    /** The body decoded as JSON, or null when it is not JSON. */
    public function json(): mixed
    {
        $decoded = json_decode($this->body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
