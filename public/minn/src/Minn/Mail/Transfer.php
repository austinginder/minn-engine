<?php

declare(strict_types=1);

namespace Minn\Mail;

use InvalidArgumentException;

/**
 * Body text in a content transfer encoding, and the line-ending helpers
 * mail composition leans on.
 */
final class Transfer
{
    public const MAX_LINE = 998;

    /**
     * The text in an encoding: 7bit, 8bit and binary keep the text (the first
     * two with normalized line breaks and a final one), base64 in 76-column
     * lines, quoted-printable by PHP's encoder.
     *
     * @throws InvalidArgumentException for an encoding there is no rule for
     */
    public static function encode(string $text, string $encoding, string $eol): string
    {
        return match (strtolower($encoding)) {
            'base64' => chunk_split(base64_encode($text), 76, $eol),
            '7bit', '8bit' => str_ends_with($text = self::normalizeBreaks($text, $eol), $eol) ? $text : $text . $eol,
            'binary' => $text,
            'quoted-printable' => self::quotedPrintable($text, $eol),
            default => throw new InvalidArgumentException($encoding),
        };
    }

    /** Quoted-printable text with the encoder's line breaks normalized. */
    public static function quotedPrintable(string $text, string $eol): string
    {
        return self::normalizeBreaks(quoted_printable_encode($text), $eol);
    }

    /** Every CRLF, CR or LF as $eol. */
    public static function normalizeBreaks(string $text, string $eol = "\r\n"): string
    {
        return str_replace("\n", $eol, str_replace(["\r\n", "\r"], "\n", $text));
    }

    /** The text without trailing spaces, tabs and line breaks. */
    public static function stripTrailingSpace(string $text): string
    {
        return rtrim($text, " \r\n\t");
    }

    /** The text without trailing line breaks. */
    public static function stripTrailingBreaks(string $text): string
    {
        return rtrim($text, "\r\n");
    }

    /** Whether any byte is outside 7-bit ASCII. */
    public static function has8bit(string $text): bool
    {
        return (bool) preg_match('/[\x80-\xFF]/', $text);
    }

    /** Whether any line runs past the 998 characters SMTP allows. */
    public static function hasLongLine(string $text): bool
    {
        if (preg_match_all('/^(.{' . (self::MAX_LINE + 2) . ',})/m', $text, $m) === 0) {
            return false;
        }
        return true;
    }
}
