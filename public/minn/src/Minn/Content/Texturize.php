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
    private const SKIP = ['pre', 'code', 'kbd', 'style', 'script', 'tt'];

    /** Whether text is texturized at all (run_wptexturize), once asked; null until then. */
    private static ?bool $run = null;

    /**
     * Whether text is texturized at all (run_wptexturize): asked on the first
     * text of a request and again after askAgain(); once the answer is no, it
     * holds for the rest of the request (probe filter-inputs-3).
     */
    public static function enabled(): bool
    {
        self::$run ??= (bool) Runtime::hooks()->filter('run_wptexturize', [true]);
        return self::$run;
    }

    /** Has the next text ask run_wptexturize again (wptexturize's reset), unless the answer was already no. */
    public static function askAgain(): void
    {
        if (self::$run === true) {
            self::$run = null;
        }
    }

    /**
     * The elements whose text stays as written (no_texturize_tags: pre, code,
     * kbd, style, script and tt), read on every text, as a pattern alternation.
     */
    public static function skipPattern(): string
    {
        $tags = array_filter((array) Runtime::hooks()->filter('no_texturize_tags', [self::SKIP]), static fn ($tag) => is_string($tag) && $tag !== '');
        return $tags === [] ? '(?!)' : implode('|', array_map(static fn (string $tag) => preg_quote($tag, '#'), $tags));
    }

    /**
     * Curly quotes, dashes, and ellipses in the text of HTML, leaving tags
     * and the skipped elements alone; registered shortcodes stay as written
     * (probe shortcode-run), and so does the text inside the ones
     * no_texturize_shortcodes names ([code] by default). Nothing changes once
     * texturizing is off. A caller that already read the skip pattern hands
     * it over, so each text asks the filters once.
     */
    public static function html(string $html, ?string $skip = null): string
    {
        if ($skip === null) {
            if ($html === '' || !self::enabled()) {
                return $html;
            }
            $skip = self::skipPattern();
        }
        $shortcode = self::shortcodePattern($html);
        $parts = preg_split(self::splitPattern($shortcode), $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $quiet = (array) Runtime::hooks()->filter('no_texturize_shortcodes', [['code']]);
        $depth = 0;
        $quieted = [];
        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (preg_match('#^</(?:' . $skip . ')\b#i', $part)) {
                    $depth = max(0, $depth - 1);
                } elseif (preg_match('#^<(?:' . $skip . ')\b#i', $part) && !str_ends_with($part, '/>')) {
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

    /** Bare ampersands made entities outside the skipped elements, as wptexturize leaves them after the rest. */
    public static function entities(string $html, string $skip): string
    {
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $depth = 0;
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                if (preg_match('#^<(/?)(?:' . $skip . ')\b#i', $part, $m)) {
                    $depth += $m[1] === '/' ? -1 : 1;
                }
                continue;
            }
            if ($depth <= 0) {
                $parts[$index] = (string) preg_replace('/&(?!#?\w+;)/', '&#038;', $part);
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
        return self::shortcodeTags($tags);
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

    /**
     * The pattern that cuts markup for texturizing (wp_html_split's pieces
     * with comments whole and, given, shortcodes): comments run to "-->" or
     * the end, a tag to its first ">", and each piece is captured.
     */
    public static function splitPattern(string $shortcodes = ''): string
    {
        return '/(<!--.*?(?:-->|$)|<[^>]*>' . ($shortcodes === '' ? '' : '|' . $shortcodes) . ')/s';
    }

    /**
     * The pattern fragment for shortcodes with these names: an opening or
     * closing tag, or an escaped [[one]], its name ended by a space, "/" or
     * "]", its attributes up to the first "]".
     *
     * @param list<string> $tags
     */
    public static function shortcodeTags(array $tags): string
    {
        return '\[[\/\[]?(?:' . implode('|', array_map(static fn (string $tag): string => preg_quote($tag, '/'), $tags)) . ')(?=[\s\]\/])(?:[^\[\]<>]+|<[^\[\]>]*>)*+\]\]?';
    }

    /**
     * Keeps the stack of open elements texturize leaves alone: an opening
     * tag or shortcode of a listed name is pushed (a self-closing one is
     * not), its closing one pops it when it is the last opened.
     *
     * @param list<string> $stack
     * @param list<string> $disabled
     */
    public static function pushPop(string $text, array &$stack, array $disabled): void
    {
        if (preg_match('#^[<\[]/?([^\s>\]/]+)#', $text, $m) !== 1) {
            return;
        }
        $name = $m[1];
        if (($text[1] ?? '') !== '/') {
            if (in_array($name, $disabled, true) && !str_ends_with($text, '/>') && !str_ends_with($text, '/]')) {
                $stack[] = $name;
            }
        } elseif (end($stack) === $name) {
            array_pop($stack);
        }
    }

    /**
     * Each $needle in the text made a prime after a digit; otherwise a
     * double quote becomes the closing quote, and a single one does only
     * where a word ends (before the end, whitespace, a no-break space or
     * . , ; : ! ? ) ] } -), staying an apostrophe or opening quote elsewhere.
     */
    public static function primes(string $haystack, string $needle, string $prime, string $closeQuote): string
    {
        if ($needle === '') {
            return $haystack;
        }
        $pieces = explode($needle, $haystack);
        $out = $pieces[0];
        $count = count($pieces);
        for ($i = 1; $i < $count; $i++) {
            $before = substr($pieces[$i - 1], -1);
            $after = $pieces[$i];
            if ($before !== '' && ctype_digit($before)) {
                $out .= $prime;
            } elseif ($needle !== "'" || self::endsWord($after !== '' ? $after : ($i < $count - 1 ? $needle : ''))) {
                $out .= $closeQuote;
            } else {
                $out .= $needle;
            }
            $out .= $after;
        }
        return $out;
    }

    /** Whether what follows a single quote ends a word: nothing, whitespace, a no-break space, or . , ; : ! ? ) ] } -. */
    private static function endsWord(string $next): bool
    {
        return $next === '' || preg_match('/^(?:\s|\xC2\xA0|[.,;:!?)\]}\-])/', $next) === 1;
    }
}
