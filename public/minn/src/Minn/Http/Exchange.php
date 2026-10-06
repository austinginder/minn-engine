<?php

declare(strict_types=1);

namespace Minn\Http;

/** What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error. */
final readonly class Exchange
{
    /**
     * @param array<string, string|list<string>> $headers names lower-cased
     * @param list<string> $cookies raw Set-Cookie header values
     * @param list<string> $head the final response's status line and header lines as they came
     * @param int $errno curl's error number when the transport failed; 0 when the request was refused before it was sent
     * @param string $url where the final response came from
     */
    public function __construct(public int $code, public array $headers, public array $cookies, public string $body, public ?string $error = null, public array $head = [], public int $errno = 0, public string $url = '')
    {
    }

    /** An exchange in which no response arrived. */
    public static function failure(string $error, int $errno, string $url): self
    {
        return new self(0, [], [], '', $error, [], $errno, $url);
    }

    /** Whether the transport failed before any status came back. */
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

    /** One header by any spelling of its name; a repeated header's values joined with ", ". */
    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;
        return is_array($value) ? implode(', ', $value) : $value;
    }

    /** One cookie's value from the Set-Cookie headers, percent-decoded; the last one set wins. */
    public function cookie(string $name): ?string
    {
        $found = null;
        foreach ($this->cookies as $line) {
            [$pair] = explode(';', $line, 2);
            [$cookie, $value] = array_pad(explode('=', $pair, 2), 2, '');
            if (trim($cookie) === $name) {
                $found = rawurldecode(trim($value));
            }
        }
        return $found;
    }

    /**
     * This exchange when it is ok(), otherwise a RequestFailed exception
     * (a RuntimeException) carrying it, for callers who would rather catch
     * than check.
     *
     * @throws RequestFailed
     */
    public function throw(): self
    {
        if ($this->ok()) {
            return $this;
        }
        $host = (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
        throw new RequestFailed($this, $this->failed() ? (string) $this->error : "{$host} answered {$this->code}.");
    }
}
