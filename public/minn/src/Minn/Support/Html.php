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
                $attributes = str_replace($c[0], ' class="' . ($c[1] === '' ? $joined : $c[1] . ' ' . $joined) . '"', $attributes);
            } else {
                $attributes .= ' class="' . $joined . '"';
            }
            return '<' . $tag . $attributes . ($selfClose === '/' ? '/' : '') . '>';
        }, $html, 1);
    }
}
