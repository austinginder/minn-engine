<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Front\Kind;
use Minn\Front\Resolution;

/**
 * The query variables the reference's main query would carry for a URL the
 * engine has resolved, so plugin code reading is_page(), get_queried_object(),
 * or get_search_query() during a front-end render sees the same page.
 */
final class MainQuery
{
    /** @return array<string, mixed> */
    public static function vars(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        $vars = match ($resolution->kind) {
            Kind::Home => [],
            Kind::Single => ($record['post_type'] ?? 'post') === 'post'
                ? ['p' => (int) ($record['ID'] ?? 0), 'post_type' => 'post', 'name' => (string) ($record['post_name'] ?? '')]
                : [(string) $record['post_type'] => (string) ($record['post_name'] ?? ''), 'post_type' => (string) $record['post_type'], 'name' => (string) ($record['post_name'] ?? '')],
            Kind::Taxonomy => [(string) ($record['taxonomy'] ?? '') => (string) ($record['slug'] ?? '')],
            Kind::PostTypeArchive => ['post_type' => (string) ($record['name'] ?? '')],
            Kind::Page => $resolution->postsPage ? [] : ['page_id' => (int) ($record['ID'] ?? 0), 'pagename' => (string) ($record['post_name'] ?? '')],
            Kind::Category => ['cat' => (int) ($record['term_id'] ?? 0), 'category_name' => (string) ($record['slug'] ?? '')],
            Kind::Tag => ['tag' => (string) ($record['slug'] ?? '')],
            Kind::Author => ['author_name' => (string) $resolution->authorName, 'author' => (int) ($record['ID'] ?? 0)],
            Kind::Date => array_filter(['year' => $resolution->date[0] ?? null, 'monthnum' => $resolution->date[1] ?? null, 'day' => $resolution->date[2] ?? null], static fn ($v) => $v !== null),
            Kind::Search => ['s' => (string) $resolution->search],
            Kind::NotFound => ['error' => '404'],
            default => [],
        };
        if ($resolution->paged > 1) {
            $vars['paged'] = $resolution->paged;
        }
        return $vars;
    }
}
