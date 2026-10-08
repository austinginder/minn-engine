<?php

declare(strict_types=1);

namespace Minn\Http;

use RuntimeException;

/**
 * An HTTP response as text, the way the Requests library hands it from
 * transport to parser: the status line and headers, a blank line, the
 * body. Built from an exchange, split back up, chunked bodies joined and
 * compressed ones inflated.
 */
final class RawResponse
{
    /** The exchange as raw response text: its status line, header lines, a blank line, the body. */
    public static function fromExchange(Exchange $exchange): string
    {
        $head = $exchange->head !== [] ? $exchange->head : ['HTTP/1.1 ' . $exchange->code];
        return implode("\r\n", $head) . "\r\n\r\n" . $exchange->body;
    }

    /**
     * The text split into protocol, status, header pairs (folded lines
     * joined) and body.
     *
     * @return array{protocol: float, status: int, headers: list<array{0: string, 1: string}>, body: string}
     * @throws RuntimeException with code 1 when no blank line separates head and body, 2 when the status line does not parse
     */
    public static function parse(string $raw): array
    {
        $at = strpos($raw, "\r\n\r\n");
        if ($at === false) {
            throw new RuntimeException('Missing header/body separator', 1);
        }
        return ['body' => substr($raw, $at + 4)] + self::parseHead(substr($raw, 0, $at));
    }

    /**
     * A head alone (the body went to a file): protocol, status, header pairs, and an empty body.
     *
     * @return array{protocol: float, status: int, headers: list<array{0: string, 1: string}>, body: string}
     * @throws RuntimeException with code 2 when the status line does not parse
     */
    public static function parseHead(string $head): array
    {
        $body = '';
        $lines = explode("\n", (string) preg_replace('/\n[ \t]/', ' ', str_replace("\r\n", "\n", $head)));
        if (!preg_match('#^HTTP/(1\.\d|2(?:\.0)?)[ \t]+(\d+)#i', (string) array_shift($lines), $m)) {
            throw new RuntimeException('Response could not be parsed', 2);
        }
        $headers = [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[] = [trim($name), (string) preg_replace('/\s+/', ' ', trim($value))];
            }
        }
        return ['protocol' => (float) $m[1], 'status' => (int) $m[2], 'headers' => $headers, 'body' => $body];
    }

    /** A chunked body joined; text that is not chunked comes back as it was. */
    public static function unchunk(string $body): string
    {
        $out = '';
        $rest = $body;
        while (preg_match('/^([0-9a-fA-F]+)(?:;[^\r\n]*)?\r\n/', $rest, $m)) {
            $length = (int) hexdec($m[1]);
            if ($length === 0) {
                return $out;
            }
            $out .= substr($rest, strlen($m[0]), $length);
            $rest = substr($rest, strlen($m[0]) + $length + 2);
        }
        return $out === '' ? $body : $out;
    }

    /**
     * A gzip body inflated past its header fields (extra, name, comment,
     * header CRC) and trailer, or a zlib one past its two-byte header, as
     * WP_Http_Encoding::compatible_gzinflate reads them; false when neither
     * inflates.
     */
    public static function inflateLoose(string $data): string|false
    {
        if (str_starts_with($data, "\x1f\x8b\x08")) {
            $at = 10;
            $flags = ord($data[3] ?? "\0");
            $at += $flags & 4 ? 2 + (int) (unpack('v', substr($data, $at, 2))[1] ?? 0) : 0;
            foreach ([8, 16] as $field) {
                $at = $flags & $field ? (int) strpos($data, "\0", $at) + 1 : $at;
            }
            $inflated = @gzinflate(substr($data, $at + ($flags & 2 ? 2 : 0), -8));
            if ($inflated !== false) {
                return $inflated;
            }
        }
        return @gzinflate(substr($data, 2));
    }

    /** A gzip or zlib body inflated; anything else (raw deflate included) comes back as it was. */
    public static function inflate(string $data): string
    {
        if (trim($data) === '') {
            return $data;
        }
        if (str_starts_with($data, "\x1f\x8b")) {
            $decoded = @gzdecode($data);
            return $decoded === false ? $data : $decoded;
        }
        $header = unpack('n', substr($data, 0, 2));
        if (strlen($data) > 2 && (ord($data[0]) & 0x0F) === 8 && is_array($header) && $header[1] % 31 === 0) {
            $decoded = @gzuncompress($data);
            return $decoded === false ? $data : $decoded;
        }
        return $data;
    }
}
