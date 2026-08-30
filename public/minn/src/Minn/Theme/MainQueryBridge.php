<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Posts;
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

    /** @return array{posts: list<array>, total: int, perPage: int} */
    public function stand(Resolution $resolution): array
    {
        if (Runtime::booted() && in_array($resolution->kind, [Kind::PostTypeArchive, Kind::Taxonomy], true)) {
            $query = \_minn_run_main_query(MainQuery::vars($resolution), $resolution->paged, $this->perPage);
            $this->lifecycle();
            return $query;
        }
        $query = $this->listing($resolution);
        if (Runtime::booted()) {
            \_minn_seed_main_query(MainQuery::vars($resolution), array_map(static fn (array $p) => (int) $p['ID'], $query['posts']), $query['total'], $this->perPage, $resolution->postsPage);
            $this->lifecycle();
        }
        return $query + ['perPage' => $this->perPage];
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

    /** @return array{posts: list<array>, total: int} */
    private function listing(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        $filter = match ($resolution->kind) {
            Kind::Category, Kind::Tag => ['term' => (int) $record['term_taxonomy_id']],
            Kind::Taxonomy => ['term' => (int) $record['term_taxonomy_id'], 'types' => $this->objectTypes((string) $record['taxonomy'])],
            Kind::PostTypeArchive => ['types' => [(string) $record['name']]],
            Kind::Author => ['author' => (int) ($record['ID'] ?? -1)],
            Kind::Date => array_combine(['from', 'to'], Resolver::dateRange(...$resolution->date) ?? ['1970-01-01', '1970-01-01']),
            Kind::Search => ['search' => (string) $resolution->search],
            Kind::Home => [],
            default => null,
        };
        if ($filter === null) {
            return ['posts' => [], 'total' => 0];
        }
        // Sticky posts ride on top of page 1 without consuming its slots, as the reference fills the page.
        $sticky = $resolution->kind === Kind::Home ? Serialized::intList($this->site->option('sticky_posts')) : [];
        return $this->posts->listing($filter, $resolution->paged, $this->perPage, $sticky, stickyExtra: true);
    }
}
