<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Support\Html;

/**
 * The links to the posts either side of this one, and the nav block that
 * wraps them. The caller's format wraps the anchor: `%link` is the whole
 * anchor and `%title` the post's title, and the anchor carries rel="prev"
 * or rel="next".
 *
 * Titles arrive already filtered; this only assembles.
 */
final class PostNavigation
{
    public static function link(string $url, string $title, string $format, string $linkFormat, bool $previous): string
    {
        $anchor = '<a href="' . Html::attr($url) . '" rel="' . ($previous ? 'prev' : 'next') . '">'
            . str_replace('%title', $title, $linkFormat) . '</a>';
        return str_replace('%link', $anchor, $format);
    }

    /**
     * The aria-label a navigation block carries: an explicit one wins,
     * otherwise a caller-supplied screen-reader text stands in for it, and
     * only with neither does the default apply.
     *
     * @param array<string, mixed> $args the caller's own arguments
     */
    public static function ariaLabel(array $args, string $default): string
    {
        if (!empty($args['aria_label'])) {
            return (string) $args['aria_label'];
        }
        return !empty($args['screen_reader_text']) ? (string) $args['screen_reader_text'] : $default;
    }
}
