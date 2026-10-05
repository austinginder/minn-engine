<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Header text as mail carries it (RFC 2047): left alone when it is plain
 * and short enough, quoted when a phrase needs it, otherwise encoded words
 * in Q (mostly ASCII) or B (mostly not), folded to the line length the
 * transport allows: 63 for PHP's mail(), 998 otherwise. Plain text only
 * becomes an encoded word to be folded when it is too long for one line.
 */
final class HeaderWords
{
    public const MAIL_LINE = 63;
    public const SMTP_LINE = 998;
    private const ATEXT_PHRASE = '/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/';

    /**
     * The header text encoded for its position ("text", "phrase" or "comment").
     *
     * @param int $lineLength the transport's limit (MAIL_LINE or SMTP_LINE)
     */
    public static function encode(string $text, string $position, string $charset, int $lineLength, string $eol): string
    {
        $position = strtolower($position);
        if ($position === 'phrase' && !Transfer::has8bit($text)) {
            $escaped = addcslashes($text, "\0..\37\177\\\"");
            return $escaped === $text && !preg_match(self::ATEXT_PHRASE, $text) ? $escaped : '"' . $escaped . '"';
        }
        $count = self::specials($text, $position);
        $charset = Transfer::has8bit($text) ? $charset : 'us-ascii';
        $width = $lineLength - 8 - strlen($charset);
        $method = match (true) {
            $count > strlen($text) / 3 => 'B',
            $count > 0, strlen($text) > $width => 'Q',
            default => '',
        };
        if ($method === '') {
            return $text;
        }
        $lines = $method === 'B' ? self::bLines($text, $charset, $width) : self::qLines($text, $position, $charset, $width, $eol);
        return implode($eol . ' ', array_map(static fn (string $line): string => "=?{$charset}?{$method}?{$line}?=", $lines));
    }

    /** RFC 2047 "Q" encoding for a position: spaces as "_", and the characters the position cannot carry as =XX. */
    public static function q(string $text, string $position = 'text'): string
    {
        $pattern = match (strtolower($position)) {
            'phrase' => '/[^A-Za-z0-9!*+\/ -]/',
            'comment' => '/[()"]|[\x00-\x08\x0B\x0C\x0E-\x1F=?_\x7F-\xFF]/',
            default => '/[\x00-\x08\x0B\x0C\x0E-\x1F=?_\x7F-\xFF]/',
        };
        $encoded = (string) preg_replace("/[\r\n]+/", '', $text);
        $encoded = (string) preg_replace_callback($pattern, static fn (array $m): string => sprintf('=%02X', ord($m[0])), $encoded);
        return str_replace(' ', '_', $encoded);
    }

    /**
     * Base64 lines of at most 63 characters cut at whole characters, so a
     * multibyte character is never split across encoded words.
     *
     * @return list<string>
     */
    public static function base64Lines(string $text, string $charset): array
    {
        $limit = 75 - strlen("=?{$charset}?B?") - 2;
        $lines = [];
        $line = '';
        foreach (mb_str_split($text, 1, $charset) as $char) {
            if ($line !== '' && (int) (ceil(strlen($line . $char) / 3) * 4) > $limit) {
                $lines[] = base64_encode($line);
                $line = '';
            }
            $line .= $char;
        }
        if ($line !== '') {
            $lines[] = base64_encode($line);
        }
        return $lines;
    }

    /** Encoded words decoded (adjacent ones joined) and converted into $charset; other text passes through. */
    public static function decode(string $value, string $charset): string
    {
        $word = '=\?[^?]+\?[bq]\?[^?]*\?=';
        $value = (string) preg_replace('/(' . $word . ')\s+(?=' . $word . ')/i', '$1', $value);
        return (string) preg_replace_callback('/=\?([^?*]+)(?:\*[^?]*)?\?([bq])\?([^?]*)\?=/i', static function (array $m) use ($charset): string {
            $bytes = strtolower($m[2]) === 'b' ? (string) base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            try {
                return (string) mb_convert_encoding($bytes, $charset, $m[1]);
            } catch (\ValueError) {
                return $bytes;
            }
        }, $value);
    }

    /** How many characters the position counts as needing encoding. */
    private static function specials(string $text, string $position): int
    {
        return match ($position) {
            'phrase' => (int) preg_match_all('/[^\x20\x21\x23-\x5B\x5D-\x7E]/', $text),
            'comment' => (int) preg_match_all('/[()"]/', $text) + (int) preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', $text),
            default => (int) preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', $text),
        };
    }

    /** @return list<string> */
    private static function bLines(string $text, string $charset, int $width): array
    {
        if (strlen($text) > mb_strlen($text, $charset)) {
            return self::base64Lines($text, $charset);
        }
        $width -= $width % 4;
        return str_split(base64_encode($text), max(4, $width));
    }

    /** @return list<string> */
    private static function qLines(string $text, string $position, string $charset, int $width, string $eol): array
    {
        $wrapped = TextWrap::quotedPrintable(self::q($text, $position), $width, $charset, $eol);
        return explode("\n", str_replace('=' . $eol, "\n", trim($wrapped)));
    }
}
