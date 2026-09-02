<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Posts;
use Minn\Content\Page;
use Minn\Content\PostFilter;
use Minn\Content\Site;
use Minn\Front\Kind;
use Minn\Front\Resolution;
use Minn\Front\Resolver;
use Minn\Runtime\MainQuery;
use Minn\Runtime\Runtime;
use Minn\Support\Serialized;

/**
 * Stands the main query for a themed page: a plugin's archive runs through
 * WP_Query (pre_get_posts shapes it), everything else is the engine's own
 * listing seeded into the query globals. Then the front-end lifecycle
 * fires: "wp" with the request object, then template_redirect.
 */
final readonly class MainQueryBridge
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private int $perPage,
    ) {
    }

    /** The page of posts a resolution shows, through the runtime's main query when it is up. */
    public function stand(Resolution $resolution): Page
    {
        if (Runtime::booted() && in_array($resolution->kind, [Kind::PostTypeArchive, Kind::Taxonomy], true)) {
            $query = \_minn_run_main_query(MainQuery::vars($resolution), $resolution->paged, $this->perPage);
            $this->lifecycle();
            return new Page($query['posts'], (int) $query['total']);
        }
        $page = $this->listing($resolution);
        if (Runtime::booted()) {
            \_minn_seed_main_query(MainQuery::vars($resolution), $page->ids(), $page->total, $this->perPage, $resolution->postsPage);
            $this->lifecycle();
        }
        return $page;
    }

    /** Posts per page. */
    public function perPage(): int
    {
        return $this->perPage;
    }

    private function lifecycle(): void
    {
        Runtime::hooks()->action('wp', [$GLOBALS['wp'] ?? null]);
        Runtime::hooks()->action('template_redirect', []);
    }

    /** @return list<string> the post types a plugin's taxonomy attaches to */
    private function objectTypes(string $taxonomy): array
    {
        $row = Runtime::booted() ? Runtime::registry()->taxonomy($taxonomy) : null;
        $types = array_values(array_map('strval', (array) ($row['object_type'] ?? [])));
        return $types === [] ? ['post'] : $types;
    }

    private function listing(Resolution $resolution): Page
    {
        $record = $resolution->record ?? [];
        $all = PostFilter::all();
        $filter = match ($resolution->kind) {
            Kind::Category, Kind::Tag => $all->inTerm((int) $record['term_taxonomy_id']),
            Kind::Taxonomy => PostFilter::types(...$this->objectTypes((string) $record['taxonomy']))->inTerm((int) $record['term_taxonomy_id']),
            Kind::PostTypeArchive => PostFilter::types((string) $record['name']),
            Kind::Author => $all->byAuthor((int) ($record['ID'] ?? -1)),
            Kind::Date => $all->between(...(Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01'])),
            Kind::Search => $all->matching((string) $resolution->search),
            Kind::Home => $all,
            default => null,
        };
        if ($filter === null) {
            return Page::empty();
        }
        // Sticky posts ride on top of page 1 without consuming its slots, as the reference fills the page.
        $sticky = $resolution->kind === Kind::Home ? Serialized::intList($this->site->option('sticky_posts')) : [];
        return $this->posts->listing($filter, $resolution->paged, $this->perPage, $sticky);
    }
}
