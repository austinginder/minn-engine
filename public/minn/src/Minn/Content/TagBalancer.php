<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * Closes what markup leaves open and drops what it closes without opening,
 * as the reference's force_balance_tags does, from its observed rules
 * (contracts/runtime.md "The content filters"): tag names are lowercased; a
 * void element is written self-closed (bare as <br />, with attributes as
 * <img src="x"/>, one already self-closed as given); reopening the
 * innermost open element closes it first unless it is one of the ten that
 * may contain themselves; a closer shuts every element opened inside its
 * own; a closer with nothing to close goes; what is still open at the end
 * closes in reverse. Script and style text is left as it is, and a "<" not
 * followed by a name is text.
 */
final class TagBalancer
{
    private const VOID = ['area', 'base', 'basefont', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    private const NESTABLE = ['article', 'aside', 'blockquote', 'details', 'div', 'figure', 'object', 'q', 'section', 'span'];
    private const RAW = ['script', 'style'];
    private const TAG = '/<(\/?)([a-zA-Z][\w:-]*)(\s[^>]*|\/)?>/';

    /** The markup with every element closed in order and every stray closer gone. */
    public static function balance(string $text): string
    {
        $stack = [];
        $out = '';
        $offset = 0;
        while (preg_match(self::TAG, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $at = $m[0][1];
            $out .= substr($text, $offset, $at - $offset);
            $offset = $at + strlen($m[0][0]);
            $name = strtolower($m[2][0]);
            $attributes = $m[3][0] ?? '';
            if ($m[1][0] === '/') {
                $out .= self::close($stack, $name);
                continue;
            }
            if (in_array($name, self::VOID, true)) {
                $out .= '<' . $name . self::voidAttributes($attributes) . '>';
                continue;
            }
            if ($stack !== [] && end($stack) === $name && !in_array($name, self::NESTABLE, true)) {
                array_pop($stack);
                $out .= "</{$name}>";
            }
            $stack[] = $name;
            $out .= '<' . $name . rtrim($attributes) . '>';
            if (in_array($name, self::RAW, true)) {
                [$raw, $offset] = self::rawText($text, $offset, $name);
                $out .= $raw;
            }
        }
        $out .= substr($text, $offset);
        while ($stack !== []) {
            $out .= '</' . array_pop($stack) . '>';
        }
        return $out;
    }

    /** A closer: every element opened inside the named one closes with it; with nothing to close it is dropped. @param list<string> $stack */
    private static function close(array &$stack, string $name): string
    {
        if (in_array($name, self::VOID, true)) {
            return '';
        }
        $at = array_search($name, array_reverse($stack, true), true);
        if ($at === false) {
            return '';
        }
        $out = '';
        while (count($stack) > $at) {
            $out .= '</' . array_pop($stack) . '>';
        }
        return $out;
    }

    /** A void element's attributes, written self-closed. */
    private static function voidAttributes(string $attributes): string
    {
        $trimmed = trim($attributes);
        if ($trimmed === '' || $trimmed === '/') {
            return ' /';
        }
        return str_ends_with($trimmed, '/') ? rtrim($attributes) : rtrim($attributes) . '/';
    }

    /** Script or style text up to the element's own closer, which stays for the main loop. @return array{0: string, 1: int} */
    private static function rawText(string $text, int $offset, string $name): array
    {
        $end = stripos($text, "</{$name}", $offset);
        if ($end === false) {
            return [substr($text, $offset), strlen($text)];
        }
        return [substr($text, $offset, $end - $offset), $end];
    }
}
