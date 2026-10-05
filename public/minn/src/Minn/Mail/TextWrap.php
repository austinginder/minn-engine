<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Word wrapping for mail text. Lines break at spaces once they would pass
 * the length; existing line breaks stay; a word longer than the line stays
 * whole in plain text, and in quoted-printable text is cut into pieces
 * ending in a soft break ("="), never inside an =XX escape or (for UTF-8)
 * inside an encoded character. A line broken at a space keeps the space
 * and, in quoted-printable text, a soft break after it.
 */
final class TextWrap
{
    /** Plain text wrapped to the length, every line ending in $eol. */
    public static function plain(string $text, int $length, string $eol): string
    {
        return self::wrapWith($text, $length, $eol, 'us-ascii', $eol);
    }

    /** Quoted-printable text wrapped to the length with soft breaks, every line ending in $eol. */
    public static function quotedPrintable(string $text, int $length, string $charset, string $eol): string
    {
        return self::wrapWith($text, $length, ' =' . $eol, $charset, $eol);
    }

    /**
     * Where to cut quoted-printable UTF-8 text at or before $max without
     * splitting an encoded character.
     */
    public static function utf8Boundary(string $encoded, int $max): int
    {
        while (true) {
            $at = strrpos(substr($encoded, 0, $max), '=');
            if ($at === false || $at < $max - 3) {
                return $max;
            }
            $byte = hexdec(substr($encoded, $at + 1, 2));
            if ($byte < 128 || $byte >= 192) {
                return $at;
            }
            $max = $at;
        }
    }

    private static function wrapWith(string $text, int $length, string $softBreak, string $charset, string $eol): string
    {
        $text = Transfer::normalizeBreaks($text, $eol);
        if (str_ends_with($text, $eol)) {
            $text = substr($text, 0, -strlen($eol));
        }
        $out = '';
        foreach (explode($eol, $text) as $line) {
            $out .= self::line($line, $length, $softBreak, $charset, $eol);
        }
        return $out;
    }

    private static function line(string $line, int $length, string $softBreak, string $charset, string $eol): string
    {
        $qp = $softBreak !== $eol;
        $out = '';
        $buffer = '';
        foreach (explode(' ', $line) as $index => $word) {
            if ($qp && strlen($word) > $length) {
                if ($index > 0) {
                    [$out, $word] = self::closeLine($word, $out, $buffer, $length, $softBreak, $charset, $eol);
                }
                [$out, $buffer] = self::pieces($word, $out, $length, $charset, $eol);
                continue;
            }
            $before = $buffer;
            $buffer .= ($index === 0 ? '' : ' ') . $word;
            if ($before !== '' && strlen($buffer) > $length) {
                $out .= $before . $softBreak;
                $buffer = $word;
            }
        }
        return $out . $buffer . $eol;
    }

    /**
     * Ends the line before a long word: with as much of the word as fits
     * when more than twenty characters are left, else with a soft break.
     *
     * @return array{0: string, 1: string} the output and what is left of the word
     */
    private static function closeLine(string $word, string $out, string $buffer, int $length, string $softBreak, string $charset, string $eol): array
    {
        $room = $length - strlen($buffer) - strlen($eol);
        if ($room <= 20) {
            return [$out . $buffer . $softBreak, $word];
        }
        $cut = self::cut($word, $room, $charset);
        return [$out . $buffer . ' ' . substr($word, 0, $cut) . '=' . $eol, substr($word, $cut)];
    }

    /**
     * A long word in line-length pieces, each but the last ending in a soft break.
     *
     * @return array{0: string, 1: string} the output and the last piece, which starts the next line
     */
    private static function pieces(string $word, string $out, int $length, string $charset, string $eol): array
    {
        $last = '';
        while ($word !== '' && $length > 0) {
            $cut = self::cut($word, $length, $charset);
            $piece = substr($word, 0, $cut);
            $word = (string) substr($word, $cut);
            if ($word === '') {
                $last = $piece;
            } else {
                $out .= $piece . '=' . $eol;
            }
        }
        return [$out, $last];
    }

    /** How much of the word to take: at most $max, never inside an escape. */
    private static function cut(string $word, int $max, string $charset): int
    {
        if (strtolower($charset) === 'utf-8') {
            return self::utf8Boundary($word, $max);
        }
        if (substr($word, $max - 1, 1) === '=') {
            return $max - 1;
        }
        return substr($word, $max - 2, 1) === '=' ? $max - 2 : $max;
    }
}
