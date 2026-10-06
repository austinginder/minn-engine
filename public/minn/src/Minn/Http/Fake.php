<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use InvalidArgumentException;

/**
 * Answers requests in place of the network while a test runs, and keeps
 * what was sent. Made by Minn\Http::fake(); every request through
 * Minn\Http, wp_remote_*() and the Requests library reaches it.
 */
final class Fake
{
    /** @var list<Outbound> */
    private array $sent = [];

    /** @param array<string, mixed> $answers URL pattern => answer, see Minn\Http::fake() */
    public function __construct(private readonly array $answers)
    {
    }

    /** The answer for one request, which is recorded as sent. */
    public function answer(Outbound $request): Exchange
    {
        $this->sent[] = $request;
        foreach ($this->answers as $pattern => $answer) {
            if (self::matches((string) $pattern, $request->url)) {
                return self::exchange($answer instanceof Closure ? $answer($request) : $answer, $request->url);
            }
        }
        return Exchange::failure("No fake answers {$request->method} {$request->url}.", CURLE_COULDNT_CONNECT, $request->url);
    }

    /**
     * The requests sent so far, oldest first; with a pattern, only those whose URL matches it.
     *
     * @return list<Outbound>
     */
    public function sent(string $pattern = '*'): array
    {
        return array_values(array_filter($this->sent, static fn (Outbound $request): bool => self::matches($pattern, $request->url)));
    }

    /** Puts the network back. */
    public function restore(): void
    {
        \Minn\Http::restore();
    }

    /** Whether a URL matches a pattern in which * stands for anything; the scheme and the query string may be left off. */
    public static function matches(string $pattern, string $url): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        $bare = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url);
        return preg_match($regex, $url) === 1 || preg_match($regex, $bare) === 1 || preg_match($regex, strtok($bare, '?') ?: $bare) === 1;
    }

    private static function exchange(mixed $answer, string $url): Exchange
    {
        $reply = match (true) {
            $answer instanceof Exchange => $answer,
            is_int($answer) => \Minn\Http::reply(status: $answer),
            is_array($answer), is_string($answer) => \Minn\Http::reply($answer),
            default => throw new InvalidArgumentException('A fake answers with an array (sent as JSON), a string (the body), a status code, an Exchange, or a closure returning one of those.'),
        };
        return $reply->url !== '' ? $reply : new Exchange($reply->code, $reply->headers, $reply->cookies, $reply->body, $reply->error, $reply->head, $reply->errno, $url);
    }
}
