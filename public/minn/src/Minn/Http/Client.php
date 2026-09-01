<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The engine's outgoing HTTP transport over curl. Redirects are followed by
 * curl, so the header lines of every hop arrive in order; only the last
 * response's block is kept, the way plugin code expects to read it.
 */
final class Client
{
    /** The common cases, so a one-off request needs no Outbound at the call site. */
    public static function get(string $url, array $headers = [], float $timeout = 5.0): Exchange
    {
        return self::send(Outbound::get($url, $headers, $timeout));
    }

    public static function post(string $url, ?string $body = null, array $headers = [], float $timeout = 5.0): Exchange
    {
        return self::send(Outbound::post($url, $body, $headers, $timeout));
    }

    public static function head(string $url, array $headers = [], float $timeout = 5.0): Exchange
    {
        return self::send(Outbound::head($url, $headers, $timeout));
    }

    public static function send(Outbound $request): Exchange
    {
        $handle = curl_init();
        $lines = [];
        $milliseconds = (int) ($request->timeout * 1000);
        curl_setopt_array($handle, [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_NOBODY => $request->method === 'HEAD',
            CURLOPT_TIMEOUT_MS => $request->blocking ? $milliseconds : 1000,
            CURLOPT_CONNECTTIMEOUT_MS => $milliseconds,
            CURLOPT_FOLLOWLOCATION => $request->redirects > 0,
            CURLOPT_MAXREDIRS => max(0, $request->redirects),
            CURLOPT_SSL_VERIFYPEER => $request->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $request->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => $request->userAgent,
            CURLOPT_HTTPHEADER => $request->headers,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$lines): int {
                $lines[] = $line;
                return strlen($line);
            },
        ]);
        if ($request->body !== null && $request->body !== '' && !in_array($request->method, ['GET', 'HEAD'], true)) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }
        if ($request->caInfo !== null && is_file($request->caInfo)) {
            curl_setopt($handle, CURLOPT_CAINFO, $request->caInfo);
        }
        $raw = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($errno !== 0 && $raw === false) {
            return new Exchange(0, [], [], '', $error !== '' ? $error : 'cURL error ' . $errno);
        }
        [$headers, $cookies] = self::lastBlock($lines);
        return new Exchange($code, $headers, $cookies, $request->method === 'HEAD' ? '' : (string) $raw);
    }

    /**
     * @param list<string> $lines
     * @return array{0: array<string, string|list<string>>, 1: list<string>}
     */
    private static function lastBlock(array $lines): array
    {
        $headers = [];
        $cookies = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'HTTP/')) {
                $headers = [];
                $cookies = [];
                continue;
            }
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (strtolower($name) === 'set-cookie') {
                $cookies[] = $value;
                continue;
            }
            $key = strtolower($name);
            if (isset($headers[$key])) {
                $headers[$key] = is_array($headers[$key]) ? [...$headers[$key], $value] : [$headers[$key], $value];
            } else {
                $headers[$key] = $value;
            }
        }
        return [$headers, $cookies];
    }
}
