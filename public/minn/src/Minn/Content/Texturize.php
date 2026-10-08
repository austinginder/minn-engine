<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Runtime\Runtime;

/**
 * The texturize subset the reference applies to rendered text: straight
 * quotes, apostrophes, ellipses, dashes, and primes become numeric
 * entities, outside the skip elements only. Coverage is pinned by the
 * texturize battery post; anything beyond it is a documented gap.
 */
final class Texturize
{
    private const SKIP = 'pre|code|kbd|style|script|tt|textarea';

    /**
     * Curly quotes, dashes, and ellipses in the text of HTML, leaving tags
     * and pre, code, kbd, style, and script alone; registered shortcodes
     * stay as written (probe shortcode-run), and so does the text inside
     * the ones no_texturize_shortcodes names ([code] by default).
     */
    public static function html(string $html): string
    {
        $shortcode = self::shortcodePattern($html);
        $parts = preg_split('/(<[^>]*>' . ($shortcode === '' ? '' : '|' . $shortcode) . ')/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $quiet = $shortcode === '' ? [] : (array) Runtime::hooks()->filter('no_texturize_shortcodes', [['code']]);
        $depth = 0;
        $quieted = [];
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
            if ($shortcode !== '' && $part[0] === '[' && preg_match('/^' . $shortcode . '$/', $part)) {
                if (!str_starts_with($part, '[[')) {
                    self::quiet($part, $quieted, $quiet);
                }
                continue;
            }
            if ($depth === 0 && $quieted === []) {
                $parts[$index] = self::text($part);
            }
        }
        return implode('', $parts);
    }

    /** The pattern for the registered shortcodes the text opens a bracket with, or "" for none. */
    private static function shortcodePattern(string $html): string
    {
        if (!str_contains($html, '[')) {
            return '';
        }
        preg_match_all('@\[/?([^<>&/\[\]\x00-\x20=]++)@', $html, $found);
        $tags = array_values(array_intersect(Runtime::shortcodes()->names(), $found[1]));
        if ($tags === []) {
            return '';
        }
        return '\[[\/\[]?(?:' . implode('|', array_map(static fn (string $tag): string => preg_quote($tag, '/'), $tags)) . ')(?=[\s\]\/])(?:[^\[\]<>]+|<[^\[\]>]*>)*+\]\]?';
    }

    /**
     * Keeps count of the quiet shortcodes open around the text: an opening
     * one is pushed, its closing one pops it, others pass.
     *
     * @param list<string> $open @param list<string> $quiet
     */
    private static function quiet(string $delimiter, array &$open, array $quiet): void
    {
        $closing = ($delimiter[1] ?? '') === '/';
        if ($closing && $open === []) {
            return;
        }
        preg_match('#^\[/?([^\s\]/]+)#', $delimiter, $name);
        $tag = $name[1] ?? '';
        if (!in_array($tag, $quiet, true)) {
            return;
        }
        if (!$closing) {
            $open[] = $tag;
        } elseif (end($open) === $tag) {
            array_pop($open);
        }
    }

    /** The same substitutions on a plain string with no tags. */
    public static function text(string $text): string
    {
        $text = str_replace('...', '&#8230;', $text);
        $text = str_replace('---', '&#8212;', $text);
        $text = str_replace(' -- ', ' &#8212; ', $text);
        $text = str_replace('--', '&#8211;', $text);
        // A hyphen standing alone between spaces (or at an end) is an en dash; a-b stays.
        $text = (string) preg_replace('/(?<=\s|^)-(?=\s|$)/', '&#8211;', $text);
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
