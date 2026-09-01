<?php

declare(strict_types=1);

namespace Minn\Http;

/** One outgoing HTTP request, normalised: the client below needs nothing else. */
final readonly class Outbound
{
    /** @param list<string> $headers "Name: value" lines */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public float $timeout = 5.0,
        public int $redirects = 5,
        public bool $verifySsl = true,
        public string $userAgent = '',
        public ?string $caInfo = null,
        public bool $blocking = true,
    ) {
    }

    /**
     * A GET.
     *
     * @param list<string> $headers "Name: value" lines
     */
    public static function get(string $url, array $headers = [], float $timeout = 5.0): self
    {
        return new self('GET', $url, $headers, timeout: $timeout);
    }

    /**
     * A POST with an optional body.
     *
     * @param list<string> $headers "Name: value" lines
     */
    public static function post(string $url, ?string $body = null, array $headers = [], float $timeout = 5.0): self
    {
        return new self('POST', $url, $headers, $body, $timeout);
    }

    /**
     * A HEAD.
     *
     * @param list<string> $headers "Name: value" lines
     */
    public static function head(string $url, array $headers = [], float $timeout = 5.0): self
    {
        return new self('HEAD', $url, $headers, timeout: $timeout);
    }
}
