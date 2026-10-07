<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\I18n\Gettext;
use Minn\Support\Escape;

/**
 * WP_Admin_Bar's markup, piece by piece, as the reference prints it (probe
 * admin-bar): the wrapper (a skip link until the page has opened its body,
 * a class for phones), a group's list (named by the menu it opens from),
 * and an item: a link or an empty div, its focus order, its attributes
 * from meta, an arrow when it opens a menu below the top level, and any
 * markup a plugin hangs after it.
 */
final class ToolbarMarkup
{
    /** The meta keys an item carries onto its link or div, in the reference's order. */
    private const ATTRIBUTES = ['onclick', 'target', 'title', 'rel', 'lang', 'dir'];

    /** The bar's wrapper up to its first group. */
    public static function open(): string
    {
        $class = 'nojq nojs' . (\wp_is_mobile() ? ' mobile' : '');
        $skip = !\is_admin() && !\did_action('wp_body_open')
            ? "\t\t\t\t\t\t\t<a class=\"screen-reader-shortcut\" href=\"#wp-toolbar\" tabindex=\"1\">" . Gettext::text('Skip to toolbar') . "</a>\n"
            : '';
        return "\t\t<div id=\"wpadminbar\" class=\"{$class}\">\n" . $skip
            . "\t\t\t\t\t\t<div class=\"quicklinks\" id=\"wp-toolbar\" role=\"navigation\" aria-label=\"" . \esc_attr__('Toolbar') . "\">\n\t\t\t\t";
    }

    /** The bar's wrapper after its last group. */
    public static function close(): string
    {
        return "\t\t\t</div>\n\t\t</div>\n\n\t\t";
    }

    /** A group's opening list tag, labelled by the menu it opens from when that has a menu title. */
    public static function groupOpen(object $node, mixed $menuTitle): string
    {
        $class = empty($node->meta['class']) ? '' : ' class="' . Escape::attr(trim((string) $node->meta['class'])) . '"';
        $label = $menuTitle ? " aria-label='" . Escape::attr((string) $menuTitle) . "'" : '';
        return "<ul role='menu'{$label} id='" . Escape::attr('wp-admin-bar-' . $node->id) . "'{$class}>";
    }

    /** An item up to its submenu: the list item, then its link (or a div when it has none) with its title. */
    public static function itemOpen(object $node): string
    {
        $opens = !empty($node->children);
        $linked = !empty($node->href);
        $tabindex = isset($node->meta['tabindex']) && is_numeric($node->meta['tabindex']) ? ' tabindex="' . (int) $node->meta['tabindex'] . '"' : '';
        $aria = $tabindex . ' role="menuitem"' . ($opens ? ' aria-expanded="false"' : '');
        $classes = ($opens ? 'menupop ' : '') . (string) ($node->meta['class'] ?? '');
        $class = $classes !== '' ? ' class="' . Escape::attr(trim($classes)) . '"' : '';
        $arrow = $opens && !in_array($node->parent, ['root-default', 'top-secondary'], true) ? '<span class="wp-admin-bar-arrow" aria-hidden="true"></span>' : '';
        $out = "<li role='group' id='" . Escape::attr('wp-admin-bar-' . $node->id) . "'{$class}>"
            . ($linked ? "<a class='ab-item'{$aria} href='" . \esc_url((string) $node->href) . "'" : '<div class="ab-item ab-empty-item"' . $aria);
        foreach (self::ATTRIBUTES as $attribute) {
            if (!empty($node->meta[$attribute])) {
                $value = (string) $node->meta[$attribute];
                $out .= " {$attribute}='" . ($attribute === 'onclick' ? \esc_js($value) : Escape::attr($value)) . "'";
            }
        }
        return $out . '>' . $arrow . $node->title . ($linked ? '</a>' : '</div>');
    }

    /** An item after its submenu: any markup a plugin hangs on it, and the list item's end. */
    public static function itemClose(object $node): string
    {
        return (empty($node->meta['html']) ? '' : (string) $node->meta['html']) . '</li>';
    }
}
