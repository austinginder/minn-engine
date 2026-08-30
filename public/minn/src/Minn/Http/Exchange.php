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
}
