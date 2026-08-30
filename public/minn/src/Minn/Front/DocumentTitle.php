<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The document title as parts (title, tagline, page, site) in the order the
 * reference joins them, so plugin code filtering document_title_parts sees
 * the same array, and the plain composition the engine prints without one.
 */
final class DocumentTitle
{
    /**
     * @param array<string, string> $record the resolved record, when there is one
     * @return array<string, string>
     */
    public static function parts(Resolution $resolution, string $site, string $tagline): array
    {
        if ($resolution->front || ($resolution->kind === Kind::Home && !$resolution->postsPage)) {
            // The front page (static or the listing) carries the tagline, after the page number.
            $parts = ['title' => $site];
            if ($resolution->paged > 1) {
                $parts['page'] = 'Page ' . $resolution->paged;
            }
            if ($tagline !== '') {
                $parts['tagline'] = $tagline;
            }
            return $parts;
        }
        $record = $resolution->record ?? [];
        $title = match ($resolution->kind) {
            Kind::Home, Kind::Single, Kind::Page => (string) ($record['post_title'] ?? ''),
            Kind::NotFound => 'Page not found',
            Kind::Category, Kind::Tag, Kind::Taxonomy => (string) ($record['name'] ?? ''),
            Kind::PostTypeArchive => (string) ($record['label'] ?? $record['name'] ?? ''),
            Kind::Author => (string) ($record['display_name'] ?? $resolution->authorName),
            Kind::Search => 'Search Results for &#8220;' . (string) $resolution->search . '&#8221;',
            default => '',
        };
        if ($title === '') {
            return ['title' => $site];
        }
        return ['title' => $title, 'site' => $site];
    }

    /** @param array<string, string> $parts */
    public static function compose(array $parts): string
    {
        return implode(' &#8211; ', array_filter($parts, static fn (string $p) => $p !== ''));
    }
}
