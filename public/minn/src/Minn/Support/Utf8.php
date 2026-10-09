<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * UTF-8 as the reference reads it. Well-formed means no overlong forms,
 * surrogates, code points past U+10FFFF, stray continuation bytes or
 * truncated sequences; noncharacters, NUL and a byte order mark pass. Split
 * into characters (the multibyte fallbacks in compat.php), a malformed byte
 * is one character and so is the longest start of a sequence that breaks
 * off, so "\xE2\x82" counts once.
 */
final class Utf8
{
    /** One well-formed character. */
    private const CHARACTER = '[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';

    /** The start of a sequence that breaks off. */
    private const BROKEN = '\xE0[\xA0-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]|\xED[\x80-\x9F]|\xF0[\x90-\xBF][\x80-\xBF]?|[\xF1-\xF3][\x80-\xBF]{1,2}|\xF4[\x80-\x8F][\x80-\xBF]?';

    /** True for well-formed UTF-8, including the empty string. */
    public static function isValid(string $bytes): bool
    {
        return $bytes === '' || preg_match('//u', $bytes) === 1;
    }

    /**
     * The bytes cut into characters, a malformed byte or a broken-off
     * sequence counting as one.
     *
     * @return list<string>
     */
    public static function characters(string $bytes): array
    {
        preg_match_all('/' . self::CHARACTER . '|' . self::BROKEN . '|[\x00-\xFF]/s', $bytes, $found);
        return $found[0];
    }

    /**
     * Reads well-formed characters from $at, as the reference's scanner does
     * (probe utf8). It stops $maxBytes past $at (a character begun before
     * that is read whole), after $maxCodePoints, or at a malformed byte. The
     * answer: the characters read, where it stopped, the length of the
     * malformed run there (a broken-off start of a sequence, else one byte,
     * cut at the byte limit; 0 when there is none), and whether a character
     * that could be a noncharacter was one (null when none could be: only
     * EF and four-byte sequences hold them).
     *
     * @return array{int, int, int, ?bool}
     */
    public static function scan(string $bytes, int $at, ?int $maxBytes = null, ?int $maxCodePoints = null): array
    {
        $end = $maxBytes === null ? strlen($bytes) : min(strlen($bytes), $at + $maxBytes);
        $limit = $maxCodePoints ?? PHP_INT_MAX;
        $found = 0;
        $noncharacter = null;
        while ($at < $end && $found < $limit) {
            if (preg_match('/\G[\x00-\x7F]+/', $bytes, $run, 0, $at) === 1) {
                $take = min(strlen($run[0]), $end - $at, $limit - $found);
                $at += $take;
                $found += $take;
                continue;
            }
            if (preg_match('/\G(?:' . self::CHARACTER . ')/s', $bytes, $character, 0, $at) !== 1) {
                $broken = preg_match('/\G(?:' . self::BROKEN . ')/s', $bytes, $start, 0, $at) === 1 ? strlen($start[0]) : 1;
                return [$found, $at, min($broken, $end - $at), $noncharacter];
            }
            $length = strlen($character[0]);
            if ($length === 4 || ($length === 3 && $character[0][0] === "\xEF")) {
                $noncharacter = $noncharacter === true || self::isNoncharacter((int) self::firstCodePoint($character[0]));
            }
            $at += $length;
            $found++;
        }
        return [$found, $at, 0, $noncharacter];
    }

    /** Whether a code point is a noncharacter: U+FDD0 to U+FDEF, or the last two of any plane. */
    public static function isNoncharacter(int $code): bool
    {
        return ($code >= 0xFDD0 && $code <= 0xFDEF) || ($code & 0xFFFE) === 0xFFFE;
    }

    /** Whether any well-formed character is a noncharacter, malformed bytes stepped over. */
    public static function hasNoncharacters(string $bytes): bool
    {
        foreach (self::characters($bytes) as $character) {
            $code = self::firstCodePoint($character);
            if ($code !== null && strlen($character) >= 3 && self::isNoncharacter($code)) {
                return true;
            }
        }
        return false;
    }

    /** The bytes with each malformed run (as characters() cuts them) replaced by U+FFFD. */
    public static function scrub(string $bytes): string
    {
        if (self::isValid($bytes)) {
            return $bytes;
        }
        return implode('', array_map(static fn (string $c): string => self::firstCodePoint($c) === null ? "\u{FFFD}" : $c, self::characters($bytes)));
    }

    /**
     * How many bytes from $offset the first $maxCodePoints characters take,
     * and how many there were.
     *
     * @return array{int, int}
     */
    public static function span(string $bytes, int $offset, int $maxCodePoints): array
    {
        $characters = array_slice(self::characters(substr($bytes, $offset)), 0, max(0, $maxCodePoints));
        return [strlen(implode('', $characters)), count($characters)];
    }

    /** UTF-8 as ISO-8859-1: a character past U+00FF, or a malformed run, becomes "?". */
    public static function toLatin1(string $bytes): string
    {
        return implode('', array_map(static function (string $c): string {
            $code = self::firstCodePoint($c);
            return $code !== null && $code <= 0xFF ? chr($code) : '?';
        }, self::characters($bytes)));
    }

    /** ISO-8859-1 as UTF-8: each byte the character of its value. */
    public static function fromLatin1(string $bytes): string
    {
        return (string) preg_replace_callback('/[\x80-\xFF]/', static fn (array $m): string => (string) self::fromCodePoint(ord($m[0])), $bytes);
    }

    /** The UTF-8 bytes for a code point, or null for a negative one, a surrogate, or one past U+10FFFF. */
    public static function fromCodePoint(int $code): ?string
    {
        return match (true) {
            $code < 0, $code > 0x10FFFF, $code >= 0xD800 && $code <= 0xDFFF => null,
            $code < 0x80 => chr($code),
            $code < 0x800 => chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F),
            $code < 0x10000 => chr(0xE0 | $code >> 12) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
            default => chr(0xF0 | $code >> 18) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F),
        };
    }

    /** The code point of the first character, or null when the bytes do not start with a whole, well-formed one. */
    public static function firstCodePoint(string $bytes): ?int
    {
        if (preg_match('/^(?:' . self::CHARACTER . ')/s', $bytes, $m) !== 1) {
            return null;
        }
        $length = strlen($m[0]);
        $code = ord($m[0][0]) & (0xFF >> ($length === 1 ? 1 : $length + 1));
        for ($i = 1; $i < $length; $i++) {
            $code = $code << 6 | ord($m[0][$i]) & 0x3F;
        }
        return $code;
    }
}
