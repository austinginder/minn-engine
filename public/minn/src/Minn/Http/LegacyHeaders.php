<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * WP_Http's legacy readers, kept for plugins that still call them (probe
 * http-classes). They read a header block the old way, and decode a chunked
 * body with the reference's quirk. RawResponse does both properly for the
 * engine's own requests.
 */
final class LegacyHeaders
{
    /**
     * A header block (a string, its folded lines joined, or a list of lines)
     * read into the last status line's code and message, lower-cased
     * headers (a repeated one as a list) and the Set-Cookie values. Each
     * status line starts the headers over.
     *
     * @param string|list<string> $headers
     * @return array{response: array{code: int, message: string}, headers: array<string, string|list<string>>, cookies: list<string>}
     */
    public static function read(string|array $headers): array
    {
        if (is_string($headers)) {
            $headers = explode("\n", (string) preg_replace('/\n[ \t]/', ' ', str_replace("\r\n", "\n", $headers)));
        }
        $read = ['response' => ['code' => 0, 'message' => ''], 'headers' => [], 'cookies' => []];
        foreach ($headers as $line) {
            $line = (string) $line;
            if ($line === '' || $line === '0') {
                continue;
            }
            if (!str_contains($line, ':')) {
                $status = explode(' ', $line, 3) + [1 => '', 2 => ''];
                $read = ['response' => ['code' => (int) $status[1], 'message' => $status[2]], 'headers' => [], 'cookies' => []];
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower($key);
            $value = trim($value);
            $read['headers'][$key] = isset($read['headers'][$key]) ? array_merge((array) $read['headers'][$key], [$value]) : $value;
            if ($key === 'set-cookie') {
                $read['cookies'][] = $value;
            }
        }
        return $read;
    }

    /**
     * A chunked body decoded as the reference's WP_Http::chunkTransferDecode
     * decodes one. The CRLF after a chunk is not stepped over, so it decodes
     * only when chunks run into each other and a final 0 ends the body; any
     * other shape comes back as it was.
     */
    public static function dechunk(string $body): string
    {
        $chunk = '/^([0-9a-f]+)[^\r\n]*\r\n/i';
        $decoded = '';
        $rest = $body;
        while (preg_match($chunk, $rest, $match) === 1 && !empty($match[1])) {
            $length = (int) hexdec($match[1]);
            $decoded .= substr($rest, strlen($match[0]), $length);
            $rest = substr($rest, strlen($match[0]) + $length);
            if (trim($rest) === '0') {
                return $decoded;
            }
        }
        return $body;
    }
}
