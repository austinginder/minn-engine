<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The prev/next links a paged listing prints: which page sits either side of
 * the one being read, and the anchor that points at it. Posts listings and
 * comment threads both come through here; only the URL builder differs.
 */
final class ListingLinks
{
    /**
     * The page before and after the current one, null past either end. A
     * listing of one page has neither, which is how the navigation blocks
     * know to print nothing at all.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function neighbours(int $current, int $maxPages): array
    {
        $current = max(1, $current);
        return [
            $current > 1 ? $current - 1 : null,
            $current < $maxPages ? $current + 1 : null,
        ];
    }

    /**
     * The anchor the reference prints. Attributes go in verbatim after the
     * href, and the space that separates them stays even when a caller
     * supplied none, so the markup reads `<a href="..." >text</a>`.
     */
    public static function anchor(string $url, string $attributes, string $text): string
    {
        return '<a href="' . $url . '" ' . $attributes . '>' . $text . '</a>';
    }

    /**
     * A navigation label's bare ampersands become entities, while an ampersand
     * that already opens an entity is left as it is.
     */
    public static function label(string $label, string $default): string
    {
        $label = $label === '' ? $default : $label;
        return (string) preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label);
    }

    /**
     * Which page of a comment thread the bare permalink shows: the first when
     * the site reads oldest comments first, the last when it reads newest
     * first. Every other page carries a comment-page-N segment.
     *
     * The page count is whatever the caller passed and is never filled in from
     * the query: a caller that does not say how long the thread is gets no
     * collapse at all under newest-first, because no page matches an unknown
     * last page.
     */
    public static function bareCommentPage(string $defaultPage, int $maxPages): int
    {
        return $defaultPage === 'newest' ? $maxPages : 1;
    }
}
