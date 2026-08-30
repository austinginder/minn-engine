<?php

declare(strict_types=1);

namespace Minn\Support;

final class Html
{
    public static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function attr(?string $value): string
    {
        return self::esc($value);
    }

    /**
     * Appends classes to the first element in a fragment (or to the first
     * element already carrying $onClass), adding a class attribute when
     * the element has none. Existing classes keep their order.
     *
     * @param list<string> $classes
     */
    public static function addClasses(string $html, array $classes, ?string $onClass = null): string
    {
        if ($classes === []) {
            return $html;
        }
        $pattern = $onClass === null
            ? '/<([a-zA-Z][\w-]*)((?:\s+[^\s=>\/]+(?:="[^"]*")?)*)\s*(\/?)>/'
            : '/<([a-zA-Z][\w-]*)((?:\s+[^\s=>\/]+(?:="[^"]*")?)*\s+class="[^"]*\b' . preg_quote($onClass, '/') . '\b[^"]*"(?:\s+[^\s=>\/]+(?:="[^"]*")?)*)\s*(\/?)>/';
        $joined = self::attr(implode(' ', $classes));
        return (string) preg_replace_callback($pattern, static function (array $m) use ($joined): string {
            [$whole, $tag, $attributes, $selfClose] = $m;
            if (preg_match('/\sclass="([^"]*)"/', $attributes, $c)) {
                // A class the element already carries is not added twice.
                $have = preg_split('/\s+/', trim($c[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $fresh = array_values(array_diff(preg_split('/\s+/', $joined, -1, PREG_SPLIT_NO_EMPTY) ?: [], $have));
                $merged = trim($c[1] . ($fresh === [] ? '' : ' ' . implode(' ', $fresh)));
                $attributes = str_replace($c[0], ' class="' . $merged . '"', $attributes);
            } else {
                $attributes .= ' class="' . $joined . '"';
            }
            return '<' . $tag . $attributes . ($selfClose === '/' ? '/' : '') . '>';
        }, $html, 1);
    }

    /** Drops script and style elements with their contents, then every other tag; whitespace runs optionally collapse to one space. */
    public static function stripAllTags(string $text, bool $collapseWhitespace = false): string
    {
        $text = strip_tags((string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text));
        if ($collapseWhitespace) {
            $text = (string) preg_replace('/[\r\n\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}
