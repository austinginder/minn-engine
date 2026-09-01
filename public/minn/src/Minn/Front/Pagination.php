<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;

/**
 * Numbered page links in the reference's shape: previous, the end and
 * middle runs with one ellipsis per gap, the current page as a span, next.
 */
final class Pagination
{
    /**
     * The page links the reference's paginate_links builds, or null for one page.
     *
     * @param Closure(int): string $link the URL for a page number
     * @return list<string>|null the link elements, null with fewer than two pages
     */
    public static function links(array $args, Closure $link): ?array
    {
        $total = (int) $args['total'];
        $current = max(1, (int) $args['current']);
        if ($total < 2) {
            return null;
        }
        $endSize = max(1, (int) $args['end_size']);
        $midSize = max(0, (int) $args['mid_size']);
        $aria = (string) $args['aria_current'];
        $before = (string) $args['before_page_number'];
        $after = (string) $args['after_page_number'];
        // No per-number aria-labels: the reference prints none (WooCommerce
        // adds them through the paginate_links_output filter where it runs).
        $out = [];
        if ($args['prev_next'] && $current > 1) {
            $out[] = '<a class="prev page-numbers" href="' . $link($current - 1) . '">' . $args['prev_text'] . '</a>';
        }
        $dots = false;
        for ($n = 1; $n <= $total; $n++) {
            if ($n === $current) {
                $out[] = '<span aria-current="' . $aria . '" class="page-numbers current">' . $before . number_format($n) . $after . '</span>';
                $dots = true;
                continue;
            }
            $shown = $args['show_all'] || $n <= $endSize || ($n >= $current - $midSize && $n <= $current + $midSize) || $n > $total - $endSize;
            if ($shown) {
                $out[] = '<a class="page-numbers" href="' . $link($n) . '">' . $before . number_format($n) . $after . '</a>';
                $dots = true;
            } elseif ($dots) {
                $out[] = '<span class="page-numbers dots">&hellip;</span>';
                $dots = false;
            }
        }
        if ($args['prev_next'] && $current < $total) {
            $out[] = '<a class="next page-numbers" href="' . $link($current + 1) . '">' . $args['next_text'] . '</a>';
        }
        return $out;
    }

    /**
     * The links in the requested shape.
     *
     * @param list<string> $links
     */
    public static function format(array $links, string $type): array|string
    {
        return match ($type) {
            'array' => $links,
            'list' => "<ul class='page-numbers'>\n\t<li>" . implode("</li>\n\t<li>", $links) . "</li>\n</ul>\n",
            default => implode("\n", $links),
        };
    }
}
