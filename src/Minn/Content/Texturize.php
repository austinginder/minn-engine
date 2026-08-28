<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * The texturize subset the reference applies to rendered text: straight
 * quotes, apostrophes, ellipses, dashes, and primes become numeric
 * entities, outside the skip elements only. Coverage is pinned by the
 * texturize battery post; anything beyond it is a documented gap.
 */
final class Texturize
{
    private const SKIP = 'pre|code|kbd|style|script|tt|textarea';

    public static function html(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $depth = 0;
        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('#^</(?:' . self::SKIP . ')\b#i', $part)) {
                    $depth = max(0, $depth - 1);
                } elseif (preg_match('#^<(?:' . self::SKIP . ')\b#i', $part) && !str_ends_with($part, '/>')) {
                    $depth++;
                }
                continue;
            }
            if ($depth > 0) {
                continue;
            }
            $parts[$index] = self::text($part);
        }
        return implode('', $parts);
    }

    public static function text(string $text): string
    {
        $text = str_replace('...', '&#8230;', $text);
        $text = str_replace('---', '&#8212;', $text);
        $text = str_replace(' -- ', ' &#8212; ', $text);
        $text = str_replace('--', '&#8211;', $text);
        // Abbreviated years: '99
        $text = preg_replace('/(^|[\s(\[{<])\'(?=\d\d)/', '$1&#8217;', $text);
        // A double quote after a digit is a prime (6'2" → 2&#8243;); a single
        // quote after a digit stays an apostrophe.
        $text = preg_replace('/(?<=\d)"/', '&#8243;', $text);
        // Opening singles, then everything left is a closing quote or apostrophe.
        $text = preg_replace('/(^|[\s(\[{<"])\'(?=\S)/', '$1&#8216;', $text);
        $text = str_replace("'", '&#8217;', $text);
        // Opening doubles, then closers.
        $text = preg_replace('/(^|[\s(\[{<])"(?=\S)/', '$1&#8220;', $text);
        return str_replace('"', '&#8221;', $text);
    }
}
