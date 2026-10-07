<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Support\Escape;

/**
 * The links between the pages of a post split with <!--nextpage-->, as
 * wp_link_pages writes them (probe placeholders-a). By number: the before
 * markup, each page (the current one a span, unless the whole post is not
 * being shown and this is its first page) after a space for the first and
 * the separator for the rest, the after markup. By next and previous, only
 * where the whole post is shown: the previous page's link, the separator,
 * the next page's. Nothing for a post in one page.
 */
final class PageLinks
{
    /**
     * The links for the page being shown of a split post.
     *
     * @param array<string, mixed> $args wp_link_pages's arguments with their defaults
     * @param int $more the reference's $more global: 1 when the whole post is shown
     * @param Closure(int): string $open the opening anchor of page $i
     * @param Closure(string, int): string $filter a link through wp_link_pages_link
     */
    public static function render(array $args, int $page, int $pages, int $more, Closure $open, Closure $filter): string
    {
        if ($pages < 2) {
            return '';
        }
        $around = static fn (string $label): string => $args['link_before'] . $label . $args['link_after'];
        if ($args['next_or_number'] === 'number') {
            $out = (string) $args['before'];
            for ($i = 1; $i <= $pages; $i++) {
                $link = $around(str_replace('%', (string) $i, (string) $args['pagelink']));
                $link = $i !== $page || ($more === 0 && $page === 1)
                    ? $open($i) . $link . '</a>'
                    : '<span class="post-page-numbers current" aria-current="' . Escape::attr((string) $args['aria_current']) . '">' . $link . '</span>';
                $out .= ($i === 1 ? ' ' : $args['separator']) . $filter($link, $i);
            }
            return $out . $args['after'];
        }
        if ($more === 0) {
            return '';
        }
        $out = (string) $args['before'];
        if ($page > 1) {
            $out .= $filter($open($page - 1) . $around((string) $args['previouspagelink']) . '</a>', $page - 1);
        }
        if ($page < $pages) {
            $out .= ($page > 1 ? $args['separator'] : '') . $filter($open($page + 1) . $around((string) $args['nextpagelink']) . '</a>', $page + 1);
        }
        return $out . $args['after'];
    }
}
