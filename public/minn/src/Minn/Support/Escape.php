<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * Text made safe for HTML the way WordPress's escapers make it (esc_html,
 * esc_attr): invalid UTF-8 emptied, entities already present normalised
 * rather than encoded again, the specials and both quotes encoded, then the
 * filter plugins hook (esc_html, attribute_escape) handed the result and the
 * original.
 */
final class Escape
{
    /** Text for an element's content. */
    public static function html(mixed $text): string
    {
        return (string) \apply_filters('esc_html', self::encoded((string) $text), $text);
    }

    /** Text for an attribute's value. */
    public static function attr(mixed $text): string
    {
        return (string) \apply_filters('attribute_escape', self::encoded((string) $text), $text);
    }

    private static function encoded(string $text): string
    {
        if ($text !== '' && preg_match('/^./us', $text) !== 1) {
            return '';
        }
        if (str_contains($text, '&')) {
            $text = Kses::normalizeEntities($text);
        }
        return Entities::specialchars($text, ENT_QUOTES, false, KsesEntities::known(...));
    }
}
