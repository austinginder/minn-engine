<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Html;
use Minn\I18n\Gettext;
use Minn\Support\Entities;
use Minn\Support\KsesEntities;

/**
 * A meta box's frame as do_meta_boxes prints it on the reference (probe
 * admin-doubts): a labelled region, its title as a heading the three
 * handle buttons (move up, move down, show or hide) describe, each with
 * its tooltip, then the box's inside, which the caller fills.
 */
final class MetaBoxMarkup
{
    /** The box up to the start of its inside: the region, the header with its title, and the handle buttons. */
    public static function open(string $id, string $title, string $classes): string
    {
        $buttons = self::button($id, 'handle-order-higher', 'order-higher-indicator', Gettext::text('Move up'), "{$id}-handle-order-higher-description", '')
            . self::button($id, 'handle-order-lower', 'order-lower-indicator', Gettext::text('Move down'), "{$id}-handle-order-lower-description", '')
            . self::button($id, 'handlediv', 'toggle-indicator', Gettext::text('Show or hide panel'), "{$id}-handlediv", ' aria-expanded="true"');
        return '<div id="' . $id . '" class="postbox ' . Html::attr($classes) . '"  role="region" aria-label="' . Entities::specialchars(Html::stripAllTags($title), ENT_QUOTES, false, KsesEntities::known(...)) . '">' . "\n"
            . '<div class="postbox-header"><h2 class="hndle" id="' . $id . '-title">' . $title . "</h2>\n"
            . '<div class="handle-actions hide-if-no-js">' . $buttons . '</div></div><div class="inside">' . "\n";
    }

    /** One handle button and its tooltip. */
    private static function button(string $id, string $class, string $indicator, string $label, string $tip, string $extra): string
    {
        return '<span class="wp-tooltip wp-is-tooltip">' . "\n\t\t\t\t"
            . '<button aria-label="' . Html::attr($label) . '" type="button" class="' . $class . ' wp-tooltip__toggle"' . $extra . ' aria-describedby="' . $id . '-title">' . "\n\t\t\t\t\t\t\t"
            . '<span class="screen-reader-text">' . $label . '</span>' . "\n\t\t\t\t\t\t\t"
            . '<span class="' . $indicator . '" aria-hidden="true"></span>' . "\n\t\t\t\t\t\t</button>\n\t\t\t\t"
            . '<span popover="hint" id="' . $tip . '" class="wp-tooltip__bubble" role="tooltip"><span id="' . $tip . '-text" class="wp-tooltip__text">' . $label . '</span></span></span>';
    }
}
