<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;

/** One outgoing HTTP request, normalised: the transport needs nothing else. */
final readonly class Outbound
{
    /**
     * @param list<string> $headers "Name: value" lines
     * @param (Closure(\CurlHandle): void)|null $prepare a last word on the curl handle before it is sent
     * @param float|null $connectTimeout seconds to connect, when not the whole timeout
     * @param int|null $maxBytes the largest body accepted; a longer one fails the exchange
     */
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
        public ?Closure $prepare = null,
        public ?float $connectTimeout = null,
        public ?int $maxBytes = null,
    ) {
    }

    /**
     * The same request sent somewhere else, as the next hop of a redirect.
     *
     * @param list<string> $headers "Name: value" lines
     */
    public function to(string $url, string $method, ?string $body, array $headers): self
    {
        return new self($method, $url, $headers, $body,$this->timeout, $this->redirects, $this->verifySsl, $this->userAgent, $this->caInfo, $this->blocking, $this->prepare, $this->connectTimeout, $this->maxBytes);
    }

    /** The same request with a last word on the curl handle, run after any it already has. */
    public function preparing(Closure $prepare): self
    {
        $first = $this->prepare;
        $both = $first === null ? $prepare : static function (\CurlHandle $handle) use ($first, $prepare): void {
            $first($handle);
            $prepare($handle);
        };
        return new self($this->method, $this->url, $this->headers, $this->body, $this->timeout, $this->redirects, $this->verifySsl, $this->userAgent, $this->caInfo, $this->blocking, $both, $this->connectTimeout, $this->maxBytes);
    }
}
