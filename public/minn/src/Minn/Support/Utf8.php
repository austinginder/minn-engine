<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Whether bytes are well-formed UTF-8 as the reference judges them: overlong
 * forms, surrogates, code points past U+10FFFF, stray continuation bytes and
 * truncated sequences fail; noncharacters, NUL and a byte order mark pass.
 */
final class Utf8
{
    /** True for well-formed UTF-8, including the empty string. */
    public static function isValid(string $bytes): bool
    {
        return $bytes === '' || preg_match('//u', $bytes) === 1;
    }
}
