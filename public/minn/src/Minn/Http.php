<?php

declare(strict_types=1);

namespace Minn;

use InvalidArgumentException;
use LogicException;
use Minn\Http\Destination;
use Minn\Http\Exchange;
use Minn\Http\Fake;
use Minn\Http\Location;
use Minn\Http\Outbound;
use Minn\Http\Transport;

/**
 * Outgoing HTTP, called straight from anywhere with no import:
 *
 *     $data = Minn\Http::get('https://api.example.com/status')->json();
 *     Minn\Http::post($url, json: ['email' => $email], timeout: 10)->throw();
 *
 * Every verb answers with an Exchange (->ok(), ->failed(), ->json(),
 * ->header(), ->cookie(), ->throw(), ->code, ->body) and takes the same
 * named arguments, each optional:
 *
 * - `query`: array added to the URL's query string
 * - `headers`: `name => value` (a list value repeats the header), or "Name: value" lines
 * - `json`, `form`, `body` (post, put, patch): an array sent as JSON, an array
 *   form-encoded, or a string sent as it is; one of the three, and the first
 *   two set the Content-Type
 * - `timeout`: seconds for the whole exchange, 5 by default
 * - `redirects`: how many to follow, 5 by default; 0 hands back the 3xx
 * - `hosts`: URL prefixes every hop must start with (`['https://api.wordpress.org/']`)
 * - `private`: hosts allowed to reach a private address. Loopback, LAN,
 *   link-local and cloud-metadata addresses are refused otherwise, at every
 *   hop and after DNS, so a URL someone typed is safe to fetch
 * - `maxBytes`: the largest body accepted; a longer one fails the exchange
 * - `userAgent`: Minn/{version} by default
 *
 * A misspelt argument is an error at the line that has it. Credentials
 * (Authorization, Cookie) are dropped when a redirect leaves the origin.
 */
final class Http
{
    private const REDIRECTS = [301, 302, 303, 307, 308];
    private const CREDENTIALS = ['authorization', 'cookie', 'proxy-authorization'];

    private static ?Fake $fake = null;

    /** A GET. */
    public static function get(string $url, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?int $maxBytes = null, ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('GET', $url, $query, $headers, [null, null], $timeout, $maxBytes, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /** A HEAD: the status and headers a GET would bring, without the body. */
    public static function head(string $url, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('HEAD', $url, $query, $headers, [null, null], $timeout, null, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /** A DELETE. */
    public static function delete(string $url, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?int $maxBytes = null, ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('DELETE', $url, $query, $headers, [null, null], $timeout, $maxBytes, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /** A POST with a json:, form: or body: payload. */
    public static function post(string $url, ?array $json = null, ?array $form = null, ?string $body = null, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?int $maxBytes = null, ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('POST', $url, $query, $headers, self::payload($json, $form, $body), $timeout, $maxBytes, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /** A PUT with a json:, form: or body: payload. */
    public static function put(string $url, ?array $json = null, ?array $form = null, ?string $body = null, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?int $maxBytes = null, ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('PUT', $url, $query, $headers, self::payload($json, $form, $body), $timeout, $maxBytes, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /** A PATCH with a json:, form: or body: payload. */
    public static function patch(string $url, ?array $json = null, ?array $form = null, ?string $body = null, array $query = [], array $headers = [], float $timeout = 5.0, int $redirects = 5, array $hosts = [], array $private = [], ?int $maxBytes = null, ?string $userAgent = null): Exchange
    {
        return self::follow(self::outbound('PATCH', $url, $query, $headers, self::payload($json, $form, $body), $timeout, $maxBytes, $userAgent), $redirects, new Destination($hosts, $private));
    }

    /**
     * Sends exactly what the Outbound says, with none of the verbs' rules
     * about where a request may go: the door WordPress's own HTTP API comes
     * through after applying its rules, and the one a fake answers at.
     */
    public static function send(Outbound $request): Exchange
    {
        return self::$fake?->answer($request) ?? Transport::send($request);
    }

    /**
     * Answers requests from $answers instead of the network until
     * restore(), for tests. Keys are URL patterns (* matches anything; the
     * scheme and query may be left off); values are an array (sent as JSON),
     * a string (the body), a status code, an Exchange from reply(), or a
     * closure taking the Outbound and returning one of those. A request no
     * pattern matches fails as if nothing were listening. The fake answers
     * wp_remote_*() too, and refuses to start outside the command line.
     *
     * @param array<string, mixed> $answers
     */
    public static function fake(array $answers = []): Fake
    {
        if (PHP_SAPI !== 'cli') {
            throw new LogicException('Minn\Http::fake() is for tests, which run on the command line.');
        }
        return self::$fake = new Fake($answers);
    }

    /** Puts the network back after fake(). */
    public static function restore(): void
    {
        self::$fake = null;
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
            $body = (string) json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        return new Exchange($status, $lower, $cookies, $body, null, ["HTTP/1.1 {$status}"]);
    }

    /** Sends each hop, judging every one against the destination rules, until a response is not a redirect. */
    private static function follow(Outbound $request, int $redirects, Destination $where): Exchange
    {
        for ($hop = 0; ; $hop++) {
            $refusal = $where->refusal($request->url);
            if ($refusal !== null) {
                return Exchange::failure($refusal, 0, $request->url);
            }
            $reply = self::deliver($request, $where);
            $location = $reply->header('location');
            if (!in_array($reply->code, self::REDIRECTS, true) || $location === null || $redirects <= 0) {
                return $reply;
            }
            if ($hop >= $redirects) {
                return Exchange::failure("Stopped after {$redirects} redirects.", CURLE_TOO_MANY_REDIRECTS, $request->url);
            }
            $request = self::nextHop($request, $reply->code, Location::resolve($request->url, $location));
        }
    }

    private static function deliver(Outbound $request, Destination $where): Exchange
    {
        if (self::$fake !== null) {
            return self::$fake->answer($request);
        }
        $ready = $where->pinned($request);
        return $ready instanceof Exchange ? $ready : Transport::send($ready);
    }

    /** The request a redirect asks for: 303, and 301/302 after a POST, become a GET without the body, as browsers and curl do. */
    private static function nextHop(Outbound $request, int $status, string $url): Outbound
    {
        $toGet = $request->method !== 'HEAD' && ($status === 303 || (in_array($status, [301, 302], true) && $request->method === 'POST'));
        $headers = Location::sameOrigin($request->url, $url) ? $request->headers : array_values(array_filter(
            $request->headers,
            static fn (string $line): bool => !in_array(strtolower(trim(strtok($line, ':') ?: '')), self::CREDENTIALS, true),
        ));
        return $request->to($url, $toGet ? 'GET' : $request->method, $toGet ? null : $request->body, $headers);
    }

    /** @param array{0: ?string, 1: ?string} $payload the body and the content type it implies */
    private static function outbound(string $method, string $url, array $query, array $headers, array $payload, float $timeout, ?int $maxBytes, ?string $userAgent): Outbound
    {
        return new Outbound(
            $method,
            self::withQuery($url, $query),
            self::lines($headers, $payload[1]),
            $payload[0],
            $timeout,
            redirects: 0,
            userAgent: $userAgent ?? 'Minn/' . (defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : 'dev'),
            maxBytes: $maxBytes,
        );
    }

    /** @return array{0: ?string, 1: ?string} the body and the content type it implies */
    private static function payload(?array $json, ?array $form, ?string $body): array
    {
        $given = count(array_filter([$json, $form, $body], static fn (mixed $part): bool => $part !== null));
        if ($given > 1) {
            throw new InvalidArgumentException("Send one of json:, form: or body:, not {$given} of them.");
        }
        return match (true) {
            $json !== null => [json_encode($json, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json'],
            $form !== null => [http_build_query($form, '', '&'), 'application/x-www-form-urlencoded'],
            default => [$body, null],
        };
    }

    /**
     * Headers as the transport's "Name: value" lines, with the payload's Content-Type unless one was given.
     *
     * @return list<string>
     */
    private static function lines(array $headers, ?string $type): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $lines[] = (string) $value;
                continue;
            }
            foreach ((array) $value as $one) {
                $lines[] = "{$name}: {$one}";
            }
        }
        $named = array_map(static fn (string $line): string => strtolower(trim(strtok($line, ':') ?: '')), $lines);
        if ($type !== null && !in_array('content-type', $named, true)) {
            $lines[] = "Content-Type: {$type}";
        }
        return $lines;
    }

    private static function withQuery(string $url, array $query): string
    {
        if ($query === []) {
            return $url;
        }
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $joined = $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $fragment === null ? $joined : $joined . '#' . $fragment;
    }
}
