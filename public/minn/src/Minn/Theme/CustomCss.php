<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * Custom CSS as theme.json and blocks write it (a styles node's "css", a
 * block's style.css): nested rules under "&" unfolded beneath the selector
 * they belong to, each wrapped in :root :where() as the reference prints
 * them. Pure: CSS and selectors in, rules out.
 */
final class CustomCss
{
    /**
     * A block's own CSS under its selector (probe editor-styles; a block's
     * style.css too, probe block-supports), split at each ampersand: a part
     * with no rule applies to the block itself; a nested selector that starts
     * with a space is scoped, every comma part after the block's selector;
     * any other is appended to it; a pseudo element moves to the end, outside
     * :where().
     */
    public static function scoped(string $css, string $selector): string
    {
        $out = '';
        foreach (explode('&', $css) as $part) {
            if (!str_contains($part, '{')) {
                if (trim($part) !== '') {
                    $out .= ':root :where(' . trim($selector) . '){' . trim($part) . '}';
                }
                continue;
            }
            $pieces = explode('{', str_replace('}', '', $part));
            if (count($pieces) !== 2) {
                continue;
            }
            [$nested, $body] = $pieces;
            $pseudo = preg_match('/([>+~\s]*::[a-zA-Z-]+)/', $nested, $m) ? $m[1] : '';
            $nested = $pseudo !== '' ? str_replace($pseudo, '', $nested) : $nested;
            $scoped = str_starts_with($nested, ' ') ? self::scope($selector, $nested) : self::append($selector, $nested);
            $out .= ":root :where({$scoped}){$pseudo}{" . trim($body) . '}';
        }
        return $out;
    }

    /** Every comma part of a selector list after every part of the scope. */
    private static function scope(string $scope, string $selector): string
    {
        $scoped = [];
        foreach (explode(',', $scope) as $outer) {
            foreach (explode(',', $selector) as $inner) {
                [$outer, $inner] = [trim($outer), trim($inner)];
                $scoped[] = $outer === '' ? $inner : ($inner === '' ? $outer : "{$outer} {$inner}");
            }
        }
        return implode(', ', $scoped);
    }

    /** Text appended to every comma part of a selector list. */
    private static function append(string $selector, string $suffix): string
    {
        return implode(',', array_map(static fn (string $part): string => $part . $suffix, explode(',', $selector)));
    }

    /**
     * The styles' own CSS (WP_Theme_JSON::get_custom_css): the top-level
     * css, then each block's under its root selector.
     *
     * @param array<string, mixed> $styles
     */
    public static function ofStyles(array $styles): string
    {
        $out = is_string($styles['css'] ?? null) ? $styles['css'] : '';
        foreach ((array) ($styles['blocks'] ?? []) as $name => $blockStyles) {
            if (is_string($blockStyles['css'] ?? null) && $blockStyles['css'] !== '') {
                $out .= self::scoped($blockStyles['css'], GlobalStyles::rootSelector((string) $name));
            }
        }
        return $out;
    }
}
