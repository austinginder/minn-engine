<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Script elements as the reference builds them (probe script-tags): the
 * attributes sorted by name, a src or href through esc_url and every other
 * value escaped (true or null printed bare, false left out); inline code
 * between newlines, with any "<script" or "</script" that would end or
 * open an element written with its "s" as a \u escape. Only JavaScript and
 * JSON can carry that escape: a script of another type whose code holds
 * such a sequence is not printed at all.
 */
final class ScriptTag
{
    /** The script types inline code is JavaScript or JSON in (lowercase, trimmed; no type at all counts as JavaScript). */
    private const ESCAPABLE = [
        '', 'module', 'application/json', 'importmap', 'speculationrules',
        'application/ecmascript', 'application/javascript', 'application/x-ecmascript', 'application/x-javascript',
        'text/ecmascript', 'text/javascript', 'text/javascript1.0', 'text/javascript1.1', 'text/javascript1.2',
        'text/javascript1.3', 'text/javascript1.4', 'text/javascript1.5', 'text/jscript', 'text/livescript',
        'text/x-ecmascript', 'text/x-javascript',
    ];

    /** A sequence that would open or close a script element inside one. */
    private const TAG = '#<(/?)(s)(cript[\t\n\f\r />])#i';

    /**
     * The empty element for a script file.
     *
     * @param array<string, mixed> $attributes
     */
    public static function element(array $attributes): string
    {
        return '<script' . self::attributes($attributes) . "></script>\n";
    }

    /**
     * The element for inline code (already between its newlines), or '' when
     * its type cannot hold the code safely.
     *
     * @param array<string, mixed> $attributes
     */
    public static function inline(string $code, array $attributes): string
    {
        $type = strtolower(trim(is_string($attributes['type'] ?? null) ? $attributes['type'] : ''));
        if (!in_array($type, self::ESCAPABLE, true)) {
            if (preg_match(self::TAG, $code) === 1) {
                return '';
            }
        } else {
            $code = (string) preg_replace_callback(self::TAG, static fn (array $m): string => '<' . $m[1] . ($m[2] === 's' ? '\u0073' : '\u0053') . $m[3], $code);
        }
        return '<script' . self::attributes($attributes) . '>' . $code . "</script>\n";
    }

    /**
     * The attributes as markup, each with its leading space.
     *
     * @param array<string, mixed> $attributes
     */
    private static function attributes(array $attributes): string
    {
        ksort($attributes, SORT_STRING);
        $out = '';
        foreach ($attributes as $name => $value) {
            if ($value === false) {
                continue;
            }
            $name = (string) $name;
            if ($value === true || $value === null) {
                $out .= ' ' . $name;
                continue;
            }
            $value = in_array(strtolower($name), ['src', 'href'], true)
                ? \esc_url((string) $value)
                : htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
            $out .= ' ' . $name . '="' . $value . '"';
        }
        return $out;
    }
}
