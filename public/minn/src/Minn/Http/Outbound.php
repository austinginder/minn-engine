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
}
