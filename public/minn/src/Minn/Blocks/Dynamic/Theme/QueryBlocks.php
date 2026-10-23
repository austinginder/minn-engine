<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Content\TermRecord;
use Minn\I18n\Gettext;
use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Content\Page;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Front\Kind;
use Minn\Objects\Post;
use Minn\Objects\Query;
use Minn\Runtime\BlockQueries;
use Minn\Runtime\Handoff;
use Minn\Runtime\QueryArgs;
use Minn\Runtime\MainQuery;
use Minn\Runtime\Runtime;
use Minn\Front\Permalinks;
use Minn\Html;
use Minn\Support\Escape;

/** query, post-template, query-title, query-no-results, query-pagination, query-total. */
final class QueryBlocks
{
    /** @var list<array{page: Page, query: ?Query, inherit: bool, paged: int, perPage: int, id: int}|\Closure> the loops being rendered, innermost last; each runs when a block inside first asks for it */
    private array $queries = [];
    /** @var list<bool> whether each loop being rendered navigates on the client (enhancedPagination), innermost last */
    private array $enhanced = [];

    /** A navigating link's directives (previous and next), in the reference's order, a space after them. */
    private const NAVIGATE = 'data-wp-on--click="core/query::actions.navigate" data-wp-on--mouseenter="core/query::actions.prefetch" data-wp-watch="core/query::callbacks.prefetch" ';

    public function __construct(
        private readonly Site $site,
        private readonly Permalinks $permalinks,
    ) {
    }

    /** Registers this family's blocks with the renderer. */
    public function register(Renderer $renderer): void
    {
        $renderer->registerDynamic('core/query', $this->query(...));
        $renderer->registerDynamic('core/post-template', $this->postTemplate(...));
        $renderer->registerDynamic('core/query-title', $this->queryTitle(...));
        $renderer->registerDynamic('core/query-no-results', $this->noResults(...));
        $renderer->registerDynamic('core/query-pagination', $this->pagination(...));
        $renderer->registerDynamic('core/query-pagination-previous', static fn () => '');
        $renderer->registerDynamic('core/query-pagination-next', static fn () => '');
        $renderer->registerDynamic('core/query-pagination-numbers', static fn () => '');
        $renderer->registerDynamic('core/query-total', $this->total(...));
    }

    /**
     * The query wrapper is stored markup; the block sets the loop its
     * children read, run only once one of them asks (a post template,
     * pagination, no results, the total), as the reference runs it in
     * those blocks: a query block with nothing that lists posts queries
     * nothing (security review 2026-10-22).
     */
    private function query(Block $block, Renderer $renderer): string
    {
        $this->queries[] = fn (): array => $this->loop($block, (array) $block->attr('query', []), $renderer);
        $enhanced = $block->attr('enhancedPagination', false) === true;
        $this->enhanced[] = $enhanced;
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        array_pop($this->queries);
        array_pop($this->enhanced);
        $out = Html::addClasses($out, Layout::classes('core/query', $block->attrs));
        return $enhanced ? $this->navigating($out, (int) $block->attr('queryId', 0)) : $out;
    }

    /**
     * A loop that navigates on the client (enhancedPagination), as the
     * reference marks it: the wrapper an interactive core/query region keyed
     * by the query's id, its attributes ahead of the stored ones, and the
     * block's view module on the page. The router region, once processed,
     * brings the loading bar.
     */
    private function navigating(string $out, int $queryId): string
    {
        Runtime::scriptModules()->enqueue('@wordpress/block-library/query/view', '', [], false, []);
        $directives = 'data-wp-context="{}" data-wp-interactive="core/query" data-wp-key="' . $queryId . '" data-wp-router-region="query-' . $queryId . '"';
        return (string) preg_replace('/^(\s*<[a-z][a-z0-9-]*)(?=[\s>\/])/i', '$1 ' . $directives, $out, 1);
    }

    /** Whether the innermost loop being rendered navigates on the client. */
    private function enhanced(): bool
    {
        return $this->enhanced !== [] && end($this->enhanced);
    }

    /**
     * A query loop's posts as the reference finds them: the arguments
     * build_query_vars_from_query_block makes of the block's query (through
     * query_loop_block_query_vars) for the page its query-{id}-page asks
     * for, run through WP_Query, so pre_get_posts and the posts_* filters
     * shape it too.
     *
     * @param array<string, mixed> $attrs the block's query
     * @return array{Page, Query}
     */
    private static function queried(Block $block, array $attrs, int $page): array
    {
        $queryId = $block->attrs['queryId'] ?? null;
        $template = Handoff::newBlock(['blockName' => 'core/post-template', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['queryId' => $queryId, 'query' => $attrs]);
        $query = Handoff::newQuery(BlockQueries::posts($template, $page));
        return [self::page($query), $query];
    }

    /** A query's posts as a page of records. */
    private static function page(Query $query): Page
    {
        return new Page(array_map(PostRecord::fromPost(...), array_values(array_filter((array) $query->posts, static fn ($post) => $post instanceof Post))), (int) $query->found_posts);
    }

    /**
     * A loop as the query block sets it: the main query's page when it
     * inherits, else its own query for the page its query-{id}-page asks for.
     * An inheriting loop inside the main query's loop (a singular page's
     * template, a post's content in a listing) walks a copy of the main query
     * from its start, as the reference does (no query runs again), so it shows
     * the main query's posts rather than finding that loop already under way.
     *
     * @param array<string, mixed> $attrs the block's query
     * @return array{page: Page, query: ?Query, inherit: bool, paged: int, perPage: int, id: int}
     */
    private function loop(Block $block, array $attrs, Renderer $renderer): array
    {
        $id = (int) ($block->attrs['queryId'] ?? 0);
        $context = $renderer->context();
        $perPage = (int) ($this->site->option('posts_per_page') ?? 10) ?: 10;
        if (!empty($attrs['inherit'])) {
            $main = MainQuery::current();
            if ($main->in_the_loop) {
                $query = clone $main;
                $query->rewind_posts();
                return ['page' => self::page($query), 'query' => $query, 'inherit' => true, 'paged' => max(1, $context->paged()), 'perPage' => $perPage, 'id' => $id];
            }
            return ['page' => new Page($context->posts, $context->total), 'query' => $main, 'inherit' => true, 'paged' => max(1, $context->paged()), 'perPage' => $perPage, 'id' => $id];
        }
        $queryId = $block->attrs['queryId'] ?? null;
        $paged = (int) (Runtime::current()->request?->query[$queryId === null ? 'query-page' : "query-{$queryId}-page"] ?? 0) ?: 1;
        [$page, $query] = self::queried($block, $attrs, $paged);
        return ['page' => $page, 'query' => $query, 'inherit' => false, 'paged' => $paged, 'perPage' => (int) ($attrs['perPage'] ?? 0) ?: $perPage, 'id' => $id];
    }

    /** The innermost loop being rendered; outside one, the loop a block's context provides (a post template called by its render callback). */
    private function current(?Renderer $renderer = null): array
    {
        if ($this->queries !== []) {
            $last = count($this->queries) - 1;
            if ($this->queries[$last] instanceof \Closure) {
                $this->queries[$last] = ($this->queries[$last])();
            }
            return $this->queries[$last];
        }
        $provided = $renderer?->blockContext() ?? [];
        if ($renderer === null || !is_array($provided['query'] ?? null)) {
            return ['page' => Page::empty(), 'query' => null, 'inherit' => false, 'paged' => 1, 'perPage' => 10, 'id' => 0];
        }
        return $this->loop(new Block('core/query', ['queryId' => $provided['queryId'] ?? null], [], '', []), $provided['query'], $renderer);
    }

    /** How many posts the loop found, or which of them this page shows (displayType range-display). */
    private function total(Block $block, Renderer $renderer): string
    {
        $query = $this->current($renderer);
        $total = $query['page']->total;
        if ((string) $block->attr('displayType', 'total-results') === 'range-display') {
            $start = $total === 0 ? 0 : ($query['paged'] - 1) * $query['perPage'] + 1;
            $end = $total === 0 ? 0 : $start + $query['page']->count() - 1;
            $text = $start === $end
                ? sprintf(Gettext::text('Displaying %1$s of %2$s'), $start, $total)
                : sprintf(Gettext::text('Displaying %1$s – %2$s of %3$s'), $start, $end, $total);
        } else {
            $text = sprintf(Gettext::plural('%d result found', '%d results found', $total), $total);
        }
        return Wrapper::open('div', 'wp-block-query-total', $block) . $text . '</div>';
    }

    /**
     * The loop's posts, walked as the reference walks them: the loop's query
     * is asked whether it has posts (an empty one fires loop_no_results),
     * each post is set up with the_post (loop_start before the first,
     * loop_end after the last), and the main query's post is set up again
     * once the loop is done. Plugins see the global post and in_the_loop()
     * move with the loop.
     */
    private function postTemplate(Block $block, Renderer $renderer): string
    {
        $loop = $this->current($renderer)['query'];
        if ($loop === null || !$loop->have_posts()) {
            return '';
        }
        $context = $renderer->context();
        $items = '';
        while ($loop->have_posts()) {
            $loop->the_post();
            if (!$loop->post instanceof Post) {
                continue;
            }
            $post = PostRecord::fromPost($loop->post);
            $context->pushPost($post);
            // Inside a block of no type (core/null) carrying the post.
            $parsed = ['blockName' => 'core/null', 'attrs' => [], 'innerBlocks' => array_map(static fn (Block $inner): array => $inner->toArray(), $block->innerBlocks), 'innerHTML' => $block->innerHtml, 'innerContent' => $block->innerContent];
            $inner = $renderer->renderItem($parsed, ['postType' => $post->type, 'postId' => $post->id], static function () use ($block, $renderer): string {
                $inner = '';
                $index = 0;
                foreach ($block->innerContent as $chunk) {
                    $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
                }
                return $inner;
            });
            $context->popPost();
            // Its classes are asked for once its blocks are rendered, through post_class, as the reference asks,
            // for the item's own post: a loop inside its blocks has set the main query's post up again.
            $key = $this->enhanced() ? 'data-wp-key="post-template-item-' . $post->id . '" ' : '';
            $items .= '<li ' . $key . 'class="' . Html::attr(implode(' ', (array) Handoff::postClass('wp-block-post', $post->id))) . '">' . $inner . '</li>';
        }
        MainQuery::current()->reset_postdata();
        $columns = (int) ($block->attrs['layout']['columnCount'] ?? 0);
        $open = Wrapper::open(
            'ul',
            'wp-block-post-template',
            $block,
            styleFirst: true,
            extraClasses: array_values(array_filter([$columns > 0 ? 'columns-' . $columns : null, Styles::align($block->attrs)])),
            trailingClasses: Layout::classes('core/post-template', $block->attrs),
        );
        return $open . $items . '</ul>';
    }

    private function queryTitle(Block $block, Renderer $renderer): string
    {
        $resolution = $renderer->context()->resolution;
        $record = $resolution->record ?? [];
        // Without its prefix an archive title is the bare name.
        $prefix = (bool) $block->attr('showPrefix', true);
        [$label, $name] = \Minn\Theme\ArchiveTitle::parts($resolution, (string) ($this->site->option('date_format') ?? ''));
        $text = match ($resolution->kind) {
            Kind::PostTypeArchive => Html::esc($this->archiveTitle($record)),
            Kind::Search => sprintf(\Minn\I18n\Gettext::text('Search results for: &#8220;%s&#8221;'), Html::esc((string) $resolution->search)),
            default => $name === '' ? '' : ($prefix ? \Minn\Theme\ArchiveTitle::compose($label, $name) : $name),
        };
        if ($text === '') {
            return '';
        }
        $level = (int) $block->attr('level', 1);
        $align = Styles::align($block->attrs);
        return Wrapper::open('h' . $level, 'wp-block-query-title', $block, styleFirst: true, extraClasses: $align === null ? [] : [$align]) . $text . '</h' . $level . '>';
    }

    private function noResults(Block $block, Renderer $renderer): string
    {
        if (!$this->current($renderer)['page']->isEmpty()) {
            return '';
        }
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        return $out;
    }

    /**
     * Pagination renders only when the loop it belongs to has more than one
     * page: the main query's pages for an inheriting loop, its own query's
     * (as many as the block's page limit allows) for any other, paged by
     * query-{id}-page. The links keep the address's other arguments; the
     * numbers show the first and last page and those around the current one,
     * dots between (probe query-pagination-custom).
     */
    private function pagination(Block $block, Renderer $renderer): string
    {
        $query = $this->current($renderer);
        $context = $renderer->context();
        $request = (array) (Runtime::current()->request?->query ?? []);
        if ($query['inherit']) {
            $paged = $context->paged();
            $pages = $context->totalPages();
            $base = $this->paginationBase($renderer);
            $kept = array_diff_key($request, ['paged' => true]);
            $link = fn (int $page): string => QueryArgs::add($kept, $this->permalinks->forPaged($base, $page));
            $number = $link;
        } else {
            $key = 'query-' . $query['id'] . '-page';
            $paged = $query['paged'];
            $limit = (int) ($renderer->blockContext()['query']['pages'] ?? 0);
            $pages = (int) ($query['query']->max_num_pages ?? 0);
            $pages = $limit > 0 ? min($limit, $pages) : $pages;
            $here = (string) (Runtime::current()->request?->path ?? '/');
            // Previous and next replace the page in the address, or add it after the rest.
            $link = static fn (int $page): string => $here . '?' . http_build_query(array_key_exists($key, $request) ? array_replace($request, [$key => $page]) : $request + [$key => $page]);
            $others = array_diff_key($request, [$key => true]);
            $rest = $others === [] ? '' : '&' . http_build_query($others);
            // The numbers are addresses relative to the page; past the first page they carry cst, as the reference's do.
            $number = static fn (int $page): string => '?' . ($page === 1 ? 'cst' : $key . '=' . $page . ($paged > 1 ? '&cst' : '')) . $rest;
        }
        if ($pages <= 1) {
            return '';
        }
        $arrow = (string) $block->attr('paginationArrow', 'none');
        if (!in_array($arrow, ['none', 'arrow', 'chevron'], true)) {
            $arrow = 'none';
        }
        $glyph = static fn (bool $next) => match ($arrow) {
            'arrow' => $next ? '→' : '←',
            'chevron' => $next ? '»' : '«',
            default => '',
        };
        // A navigating loop's links carry their keys and the query's navigate and prefetch actions, ahead of the href.
        $navigate = $this->enhanced();
        $wired = static fn (string $key): string => $navigate ? 'data-wp-key="' . $key . '" ' . self::NAVIGATE : '';
        // The parts in the markup's own order, the whitespace between them as written.
        $parts = '';
        $index = 0;
        foreach ($block->innerContent as $chunk) {
            if ($chunk !== null) {
                $parts .= $chunk;
                continue;
            }
            $inner = $block->innerBlocks[$index++];
            $parts .= match ($inner->name) {
                'core/query-pagination-previous' => $paged > 1
                    ? '<a ' . $wired('query-pagination-previous') . 'href="' . Escape::url($link($paged - 1)) . '" class="wp-block-query-pagination-previous">'
                        . ($arrow === 'none' ? '' : "<span class='wp-block-query-pagination-previous-arrow is-arrow-{$arrow}' aria-hidden='true'>" . $glyph(false) . '</span>')
                        . 'Previous Page</a>'
                    : '',
                'core/query-pagination-next' => $paged < $pages
                    ? '<a ' . $wired('query-pagination-next') . 'href="' . Escape::url($link($paged + 1)) . '" class="wp-block-query-pagination-next">Next Page'
                        . ($arrow === 'none' ? '' : "<span class='wp-block-query-pagination-next-arrow is-arrow-{$arrow}' aria-hidden='true'>" . $glyph(true) . '</span>')
                        . '</a>'
                    : '',
                'core/query-pagination-numbers' => $this->numbers($paged, $pages, $number, (int) $inner->attr('midSize', 2)),
                default => '',
            };
        }
        $classes = array_values(array_filter([
            Styles::align($block->attrs),
            $block->className(),
            'wp-block-query-pagination',
            ...Layout::classes('core/query-pagination', $block->attrs),
        ]));
        return '<nav class="' . Html::attr(implode(' ', $classes)) . '" aria-label="Pagination">' . $parts . '</nav>';
    }

    /** The current listing's own URL, without a page number. */
    private function paginationBase(Renderer $renderer): string
    {
        $resolution = $renderer->context()->resolution;
        $record = $resolution->record ?? [];
        return match ($resolution->kind) {
            Kind::Category, Kind::Tag, Kind::Taxonomy => $this->permalinks->forTerm($record instanceof TermRecord ? $record : TermRecord::fromRow((array) $record)),
            Kind::PostTypeArchive => $this->permalinks->forPostTypeArchive($record),
            Kind::Author => $record === [] ? $this->permalinks->url('/author/' . $resolution->authorName . '/') : $this->permalinks->forAuthor($record),
            Kind::Date => $this->permalinks->forDate(...$resolution->date),
            Kind::Search => $this->permalinks->forSearch((string) $resolution->search),
            default => $this->permalinks->url('/'),
        };
    }

    /**
     * The page numbers: the first and last page, and those within $around of
     * the current one, with dots where pages are left out. In a navigating
     * loop each item is keyed by its place (index-N) and each link navigates.
     *
     * @param callable(int): string $link
     */
    private function numbers(int $paged, int $pages, callable $link, int $around): string
    {
        $navigate = $this->enhanced();
        $items = [];
        $dots = false;
        // Each item's key is its place in the list, dots included, as the reference numbers them.
        $key = static function () use (&$items, $navigate): string {
            return $navigate ? 'data-wp-key="index-' . count($items) . '" ' : '';
        };
        for ($page = 1; $page <= $pages; $page++) {
            if ($page === $paged) {
                $items[] = '<span ' . $key() . 'aria-current="page" class="page-numbers current">' . $page . '</span>';
                $dots = true;
            } elseif ($page === 1 || $page === $pages || abs($page - $paged) <= $around) {
                $items[] = '<a ' . $key() . ($navigate ? 'data-wp-on--click="core/query::actions.navigate" ' : '') . 'class="page-numbers" href="' . Escape::url($link($page)) . '">' . $page . '</a>';
                $dots = true;
            } elseif ($dots) {
                $items[] = '<span ' . $key() . 'class="page-numbers dots">&hellip;</span>';
                $dots = false;
            }
        }
        return '<div class="wp-block-query-pagination-numbers">' . implode("\n", $items) . '</div>';
    }

    /** A post type archive's title: the type's label, renamed by the post_type_archive_title filter when a plugin does. */
    private function archiveTitle(array $type): string
    {
        $label = (string) ($type['label'] ?? $type['name'] ?? '');
        return (string) Runtime::hooks()->filter('post_type_archive_title', [$label, (string) ($type['name'] ?? '')]);
    }
}
