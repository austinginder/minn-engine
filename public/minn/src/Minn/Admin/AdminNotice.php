<?php

declare(strict_types=1);

namespace Minn\Admin;

use Closure;
use Minn\I18n\Gettext;
use Minn\Runtime\Notices;

/**
 * An admin notice's markup, as wp_get_admin_notice builds it on the
 * reference (probe functions-formatting-doubts): a type is a string,
 * trimmed, and one holding a space is a mistake; extra classes come only
 * from a list; an attribute that is true is a bare name, a value under a
 * number is a bare name too, an empty one is left out.
 */
final class AdminNotice
{
    /**
     * The notice's div and its inner part (the message, in a paragraph
     * unless paragraph_wrap is off), escaped by $attr.
     *
     * @param array<string, mixed> $args the parsed and filtered arguments
     * @param Closure(string): string $attr the attribute escaper
     * @return array{0: string, 1: string}
     */
    public static function markup(string $message, array $args, Closure $attr): array
    {
        $type = is_string($args['type']) ? trim($args['type']) : '';
        if (str_contains($type, ' ')) {
            Notices::doingItWrong('wp_get_admin_notice', Gettext::text('The <code>type</code> key must be a string without spaces.'), '6.4.0');
        }
        $classes = array_merge(['notice'], $type !== '' ? ['notice-' . $type] : [], $args['dismissible'] ? ['is-dismissible'] : [], is_array($args['additional_classes']) ? $args['additional_classes'] : []);
        $attributes = ($args['id'] !== '' ? ' id="' . $attr((string) $args['id']) . '"' : '') . ' class="' . $attr(implode(' ', $classes)) . '"';
        foreach ((array) $args['attributes'] as $name => $value) {
            $attributes .= !$value ? '' : ' ' . $attr(is_int($name) ? (string) $value : (string) $name) . ($value === true || is_int($name) ? '' : '="' . $attr((string) $value) . '"');
        }
        $paragraph = $args['paragraph_wrap'] ? "<p>{$message}</p>" : $message;
        return ['<div' . $attributes . '>' . $paragraph . '</div>', $paragraph];
    }
}
