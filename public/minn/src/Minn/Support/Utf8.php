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
