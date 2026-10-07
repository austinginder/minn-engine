<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\Page;
use Minn\Content\PostFilter;
use Minn\Content\Site;
use Minn\Front\Kind;
use Minn\Front\PluginRules;
use Minn\Front\Resolution;
use Minn\Front\Resolver;
use Minn\Runtime\MainQuery;
use Minn\Runtime\Runtime;
use Minn\Support\Serialized;

/**
 * Stands the main query for a themed page and runs the front-end steps
 * around it as WP::main does (FrontLifecycle): the request parsed, the main
 * query through WP_Query (so pre_get_posts and every posts_* filter shape
 * it), the 404 decision, the globals, the headers, then "wp" and
 * template_redirect. A listing's posts are the query's; a single post the
 * query does not find (a preview, a draft its author reads) is the one the
 * engine resolved. Without the runtime, the engine's own listing.
 */
final readonly class MainQueryBridge
{
    private const LISTINGS = [Kind::Home, Kind::Category, Kind::Tag, Kind::Taxonomy, Kind::PostTypeArchive, Kind::Author, Kind::Date, Kind::Search];

    public function __construct(
        private Site $site,
        private Posts $posts,
        private int $perPage,
    ) {
    }

    /**
     * The page of posts a resolution shows, through the runtime's main query
     * when it is up; the variables a request adds of its own (a feed's) win.
     *
     * @param array<string, mixed> $extra
     */
    public function stand(Resolution $resolution, array $extra = []): Page
    {
        if (!Runtime::booted()) {
            return $this->listing($resolution);
        }
        // The steps run once a request: a page rendered after a handler declined it (a sitemap that is a 404) stands on that query.
        if (Runtime::current()->get('front_lifecycle') === true) {
            $stood = Runtime::current()->get('main_query_page');
            return $stood instanceof Page ? $stood : Page::empty();
        }
        $vars = $extra + $this->vars($resolution);
        \_minn_seed_wp_request($vars);
        $wp = $GLOBALS['wp'];
        $request = Runtime::current()->request;
        $parsed = FrontLifecycle::parseRequest($wp, $vars, $request === null ? [] : $request->form + $request->query);
        $page = $parsed ? $this->queried($resolution, (array) $wp->query_vars) : $this->seeded($resolution, $vars);
        if ($parsed) {
            FrontLifecycle::handle404($GLOBALS['wp_query'], $resolution->kind === Kind::NotFound);
            $wp->register_globals();
        }
        FrontLifecycle::sendHeaders($wp);
        // The page's Link headers are core's own now (rest_output_link_header, wp_shortlink_header).
        Runtime::current()->set('front_lifecycle', true);
        Runtime::current()->set('main_query_page', $page);
        Runtime::hooks()->action('wp', [$wp]);
        Runtime::hooks()->action('template_redirect', []);
        return $page;
    }

    /** Posts per page. */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * The variables the reference's request parse would arrive at: the
     * resolution's, the posts page by its id, and an unresolved pretty path
     * as the page path it was taken for.
     *
     * @return array<string, mixed>
     */
    private function vars(Resolution $resolution): array
    {
        $vars = MainQuery::vars($resolution);
        $record = $resolution->record ?? [];
        if ($resolution->postsPage && isset($record['ID'])) {
            return ['page_id' => (int) $record['ID']] + $vars;
        }
        $path = trim((string) (Runtime::current()->request?->path ?? ''), '/');
        if ($resolution->kind === Kind::NotFound && $path !== '' && \get_option('permalink_structure') !== '') {
            return ['pagename' => $path] + PluginRules::stashed();
        }
        return $vars;
    }

    /**
     * The main query for these variables: a listing is its posts; any other
     * page stands on the post the engine resolved when the query has none.
     *
     * @param array<string, mixed> $vars
     */
    private function queried(Resolution $resolution, array $vars): Page
    {
        $query = \_minn_run_main_query($vars, 0, $this->perPage);
        if (in_array($resolution->kind, self::LISTINGS, true) || $resolution->postsPage) {
            return new Page(PostRecord::fromRows($query['posts']), (int) $query['total']);
        }
        if ($resolution->kind !== Kind::NotFound && $query['posts'] === [] && isset(($resolution->record ?? [])['ID'])) {
            return $this->seeded($resolution, MainQuery::vars($resolution));
        }
        return Page::empty();
    }

    /** The engine's own page seeded into the query globals, when no query ran or it found nothing to stand on. @param array<string, mixed> $vars */
    private function seeded(Resolution $resolution, array $vars): Page
    {
        $page = $this->listing($resolution);
        \_minn_seed_main_query($vars, $page->ids(), $page->total, $this->perPage, $resolution->postsPage);
        return $page;
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
