<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use InvalidArgumentException;
use Minn\Json;

/**
 * Answers requests in place of the network, and keeps what was sent. A test
 * makes one and hands its client to the code it tests:
 *
 *     $fake = new Fake(['api.example.com/*' => ['ok' => true]]);
 *     $result = (new StatusCheck($fake->http()))->run();
 *     $fake->sent('api.example.com/status');   // the requests that went there
 *
 * Only that client is answered, so there is nothing to put back and nothing
 * else in the process sees the fake.
 */
final class Fake
{
    /** @var list<Outbound> */
    private array $sent = [];

    /**
     * Keys are URL patterns (* matches anything; the scheme and query may be
     * left off); values are an array (sent as JSON), a string (the body), a
     * status code, an Exchange from reply(), or a closure taking the
     * Outbound and returning one of those. A request no pattern matches
     * fails as if nothing were listening.
     *
     * @param array<string, mixed> $answers
     */
    public function __construct(private readonly array $answers = [])
    {
    }

    /**
     * A client this fake answers, bound to $within as an extension's is by
     * its needs.http. The destination rules still run, but names are not resolved.
     *
     * @param list<string> $within
     */
    public function http(array $within = []): \Minn\Http
    {
        return new \Minn\Http($within, $this);
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

    /**
     * A response made by hand, for a fake to answer with. An array body is sent as JSON.
     *
     * @param array<string, string|list<string>> $headers name => value; Set-Cookie values become cookies
     */
    public static function reply(string|array $body = '', int $status = 200, array $headers = []): Exchange
    {
        $lower = [];
        $cookies = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            if ($key === 'set-cookie') {
                $cookies = [...$cookies, ...array_map('strval', (array) $value)];
                continue;
            }
            $lower[$key] = is_array($value) ? array_values(array_map('strval', $value)) : (string) $value;
        }
        if (is_array($body)) {
            $lower['content-type'] ??= 'application/json';
            $body = Json::encode($body);
        }
        return new Exchange($status, $lower, $cookies, $body, null, ["HTTP/1.1 {$status}"]);
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
            is_int($answer) => self::reply(status: $answer),
            is_array($answer), is_string($answer) => self::reply($answer),
            default => throw new InvalidArgumentException('A fake answers with an array (sent as JSON), a string (the body), a status code, an Exchange, or a closure returning one of those.'),
        };
        return $reply->url !== '' ? $reply : new Exchange($reply->code, $reply->headers, $reply->cookies, $reply->body, $reply->error, $reply->head, $reply->errno, $url);
    }
}
