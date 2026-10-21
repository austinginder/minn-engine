<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Support\Entities;
use Minn\Support\KsesEntities;

/**
 * A settings form's submit input as get_submit_button builds it: "button"
 * and a class for each type (primary, small and large prefixed with
 * "button-", secondary meaning none), the id given among the other
 * attributes or the name, the other attributes escaped one by one (or a
 * string of them as given).
 */
final class SubmitButton
{
    /**
     * The input element.
     *
     * @param list<string> $types
     * @param array<string, mixed>|string $otherAttributes
     */
    public static function input(string $text, array $types, string $name, array|string $otherAttributes): string
    {
        $classes = ['button'];
        foreach ($types as $type) {
            if (in_array($type, ['primary', 'small', 'large'], true)) {
                $classes[] = 'button-' . $type;
            } elseif (!in_array($type, ['secondary', 'button-secondary', ''], true)) {
                $classes[] = $type;
            }
        }
        $id = $name;
        $attributes = is_string($otherAttributes) ? $otherAttributes : '';
        if (is_array($otherAttributes)) {
            $id = (string) ($otherAttributes['id'] ?? $name);
            unset($otherAttributes['id']);
            foreach ($otherAttributes as $attribute => $value) {
                $attributes .= $attribute . '="' . self::attr((string) $value) . '" ';
            }
        }
        return '<input type="submit" name="' . self::attr($name) . '" id="' . self::attr($id) . '" class="' . self::attr(implode(' ', array_unique($classes)))
            . '" value="' . self::attr($text) . '" ' . $attributes . ' />';
    }

    /** An attribute value escaped without encoding a reference twice. */
    private static function attr(string $value): string
    {
        return Entities::specialchars($value, ENT_QUOTES, false, KsesEntities::known(...));
    }
}
