<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostRecord;
use Minn\Content\Page;
use Minn\Front\Kind;
use Minn\Front\RequestParse;
use Minn\Front\Resolution;
use Minn\Runtime\Runtime;

/**
 * Stands the main query for a themed page and runs the front-end steps
 * around it as WP::main does (FrontLifecycle): the request parsed, the main
 * query through WP_Query (so pre_get_posts and every posts_* filter shape
 * it), the 404 decision, the globals, the headers, then "wp" and
 * template_redirect. A listing's posts are the query's, and so is the 404
 * for an empty page past the first or an empty date; a single post the
 * query does not find (a preview, a draft its author reads) is the one the
 * engine resolved. When a plugin takes the parse over no query runs, as
 * on the reference, and the page stands empty.
 */
final readonly class MainQueryBridge
{
    private const LISTINGS = [Kind::Home, Kind::Category, Kind::Tag, Kind::Taxonomy, Kind::PostTypeArchive, Kind::Author, Kind::Date, Kind::Search];

    public function __construct(
        private int $perPage,
    ) {
    }

    /**
     * The page of posts a resolution shows, through the runtime's main
     * query; the variables a request adds of its own (a feed's) win.
     *
     * @param array<string, mixed> $extra
     */
    public function stand(Resolution $resolution, array $extra = []): Page
    {
        // The steps run once a request: a page rendered after a handler declined it (a sitemap that is a 404) stands on that query.
        if (Runtime::current()->get('front_lifecycle') === true) {
            $stood = Runtime::current()->get('main_query_page');
            return $stood instanceof Page ? $stood : Page::empty();
        }
        $vars = $extra + $this->vars($resolution);
        \_minn_seed_wp_request($vars);
        $wp = $GLOBALS['wp'];
        $request = Runtime::current()->request;
        $given = $request === null ? [] : $request->form + $request->query;
        // With the request known, the vars are the reference's parse of it; what the caller adds (a feed's kind, a sitemap's name) takes its place among them.
        $parse = $request === null ? null : static function (array $public) use ($resolution, $given, $extra): array {
            $vars = RequestParse::vars($resolution->vars, $public, $given);
            foreach ($extra as $name => $value) {
                $vars[$name] = is_bool($value) ? ($value ? 'true' : '') : $value;
            }
            return $vars;
        };
        $parsed = FrontLifecycle::parseRequest($wp, $vars, $given, $parse);
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

    /**
     * The resolution as the main query left it: a 404 when the query
     * decided one (an empty page past the first, an empty date), with the
     * vars it was asked under.
     */
    public static function verdict(Resolution $resolution): Resolution
    {
        $query = $GLOBALS['wp_query'] ?? null;
        $decided = $query instanceof \WP_Query && Runtime::current()->get('front_lifecycle') === true && $query->is_404();
        return $decided && $resolution->kind !== Kind::NotFound ? Resolution::notFound()->withVars($resolution->vars) : $resolution;
    }

    /** Posts per page. */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * The variables the request stands on before the parse runs (and in
     * its place, when a plugin takes the parse over): the matched rule's,
     * the posts page by its id.
     *
     * @return array<string, mixed>
     */
    private function vars(Resolution $resolution): array
    {
        $record = $resolution->record ?? [];
        return $resolution->postsPage && isset($record['ID']) ? ['page_id' => (int) $record['ID']] + $resolution->vars : $resolution->vars;
    }

    /**
     * The variables that name the post the engine resolved, for a page the
     * query found nothing to stand on (a preview, a draft its author reads).
     *
     * @return array<string, mixed>
     */
    private function postVars(Resolution $resolution): array
    {
        $post = $resolution->record;
        if (!$post instanceof PostRecord) {
            return $resolution->vars;
        }
        return match ($post->type) {
            'page' => ['page_id' => $post->id, 'pagename' => $post->slug],
            'post' => ['p' => $post->id, 'post_type' => 'post', 'name' => $post->slug],
            default => [$post->type => $post->slug, 'post_type' => $post->type, 'name' => $post->slug],
        };
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
            return $this->seeded($resolution, $this->postVars($resolution));
        }
        return Page::empty();
    }

    /** The query globals seeded with no posts of their own (a single the query did not find is looked up by its vars), when no query ran or it found nothing to stand on. @param array<string, mixed> $vars */
    private function seeded(Resolution $resolution, array $vars): Page
    {
        \_minn_seed_main_query($vars, [], 0, $this->perPage, $resolution->postsPage);
        return Page::empty();
    }
}
