<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use Minn\Content\TermRecord;
use Minn\I18n\Gettext;
use Minn\Content\PostRecord;
use Minn\Blocks\Block;
use Minn\Content\Page;
use Minn\Content\Posts;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Front\Kind;
use Minn\Runtime\Runtime;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Support\Serialized;

/** query, post-template, query-title, query-no-results, query-pagination, query-total. */
final class QueryBlocks
{
    /** @var list<array{page: Page, inherit: bool, paged: int, perPage: int}> the loops being rendered, innermost last */
    private array $queries = [];

    public function __construct(
        private readonly Posts $posts,
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

    /** The query wrapper is stored markup; the block sets the loop its children read. */
    private function query(Block $block, Renderer $renderer): string
    {
        $this->queries[] = $this->loop($block, (array) $block->attr('query', []), $renderer);
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        array_pop($this->queries);
        return Html::addClasses($out, Layout::classes('core/query', $block->attrs));
    }

    /**
     * A query loop's posts as the reference finds them: the arguments
     * build_query_vars_from_query_block makes of the block's query (through
     * query_loop_block_query_vars) for the page its query-{id}-page asks
     * for, run through WP_Query, so pre_get_posts and the posts_* filters
     * shape it too.
     *
     * @param array<string, mixed> $attrs the block's query
     */
    private static function queried(Block $block, array $attrs, int $page): Page
    {
        $queryId = $block->attrs['queryId'] ?? null;
        $template = new \WP_Block(['blockName' => 'core/post-template', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []], ['queryId' => $queryId, 'query' => $attrs]);
        $query = new \WP_Query(\build_query_vars_from_query_block($template, $page));
        $rows = array_map(static fn ($post) => array_diff_key(get_object_vars($post), ['filter' => true]), array_filter((array) $query->posts, static fn ($post) => $post instanceof \WP_Post));
        return new Page(PostRecord::fromRows(array_values($rows)), (int) $query->found_posts);
    }

    /**
     * A loop as the query block sets it: the main query's page when it
     * inherits, else its own query for the page its query-{id}-page asks for.
     *
     * @param array<string, mixed> $attrs the block's query
     * @return array{page: Page, inherit: bool, paged: int, perPage: int}
     */
    private function loop(Block $block, array $attrs, Renderer $renderer): array
    {
        $context = $renderer->context();
        $perPage = (int) ($this->site->option('posts_per_page') ?? 10) ?: 10;
        if (!empty($attrs['inherit'])) {
            return ['page' => new Page($context->posts, $context->total), 'inherit' => true, 'paged' => max(1, $context->paged()), 'perPage' => $perPage];
        }
        $queryId = $block->attrs['queryId'] ?? null;
        $paged = (int) (Runtime::current()->request?->query[$queryId === null ? 'query-page' : "query-{$queryId}-page"] ?? 0) ?: 1;
        return ['page' => self::queried($block, $attrs, $paged), 'inherit' => false, 'paged' => $paged, 'perPage' => (int) ($attrs['perPage'] ?? 0) ?: $perPage];
    }

    /** The innermost loop being rendered; outside one, the loop a block's context provides (a post template called by its render callback). */
    private function current(?Renderer $renderer = null): array
    {
        if ($this->queries !== []) {
            return $this->queries[count($this->queries) - 1];
        }
        $provided = $renderer?->blockContext() ?? [];
        if ($renderer === null || !is_array($provided['query'] ?? null)) {
            return ['page' => Page::empty(), 'inherit' => false, 'paged' => 1, 'perPage' => 10];
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

    private function postTemplate(Block $block, Renderer $renderer): string
    {
        $query = $this->current($renderer);
        if ($query['page']->isEmpty()) {
            return '';
        }
        $context = $renderer->context();
        $sticky = Serialized::intList($this->site->option('sticky_posts'));
        $onFrontPage = $context->resolution->kind === Kind::Home && $context->resolution->paged === 1;
        $items = '';
        foreach ($query['page']->posts as $post) {
            $context->pushPost($post);
            $inner = '';
            $index = 0;
            foreach ($block->innerContent as $chunk) {
                $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
            }
            $context->popPost();
            $isSticky = $onFrontPage && in_array($post->id, $sticky, true);
            $items .= '<li class="' . implode(' ', $this->postClasses($post, $isSticky)) . '">' . $inner . '</li>';
        }
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

    /** @return list<string> */
    private function postClasses(PostRecord $post, bool $sticky): array
    {
        $id = $post->id;
        $type = $post->type;
        $classes = ['wp-block-post', 'post-' . $id, $type, 'type-' . $type, 'status-' . $post->status];
        if ($type === 'post') {
            $formats = $this->posts->terms($id, 'post_format');
            $classes[] = 'format-' . ($formats === [] ? 'standard' : str_replace('post-format-', '', $formats[0][1]));
        }
        if ($sticky) {
            $classes[] = 'sticky';
        }
        if ($post->isProtected()) {
            $classes[] = 'post-password-required';
        }
        if ((int) ($this->posts->meta($id, '_thumbnail_id') ?? 0) > 0) {
            $classes[] = 'has-post-thumbnail';
        }
        $classes[] = 'hentry';
        if ($type === 'post') {
            foreach ($this->posts->terms($id, 'category') as [, $slug]) {
                $classes[] = 'category-' . $slug;
            }
            foreach ($this->posts->terms($id, 'post_tag') as [, $slug]) {
                $classes[] = 'tag-' . $slug;
            }
        }
        return $classes;
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
            Kind::Search => 'Search results for: ' . Texturize::text('"' . Html::esc((string) $resolution->search) . '"'),
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

    /** Pagination renders only when the loop it belongs to has more than one page. */
    private function pagination(Block $block, Renderer $renderer): string
    {
        $query = $this->current($renderer);
        $context = $renderer->context();
        if (!$query['inherit'] || $context->totalPages() <= 1) {
            return '';
        }
        $paged = $context->paged();
        $pages = $context->totalPages();
        $base = $this->paginationBase($renderer);
        $link = fn (int $page): string => $this->permalinks->forPaged($base, $page);
        $arrow = (string) $block->attr('paginationArrow', 'none');
        if (!in_array($arrow, ['none', 'arrow', 'chevron'], true)) {
            $arrow = 'none';
        }
        $glyph = static fn (bool $next) => match ($arrow) {
            'arrow' => $next ? '→' : '←',
            'chevron' => $next ? '»' : '«',
            default => '',
        };
        $parts = [];
        foreach ($block->innerBlocks as $inner) {
            $parts[] = match ($inner->name) {
                'core/query-pagination-previous' => $paged > 1
                    ? '<a href="' . Html::attr($link($paged - 1)) . '" class="wp-block-query-pagination-previous">'
                        . ($arrow === 'none' ? '' : '<span class="wp-block-query-pagination-previous-arrow is-arrow-' . $arrow . '" aria-hidden="true">' . $glyph(false) . '</span>')
                        . 'Previous Page</a>'
                    : '',
                'core/query-pagination-next' => $paged < $pages
                    ? '<a href="' . Html::attr($link($paged + 1)) . '" class="wp-block-query-pagination-next">Next Page'
                        . ($arrow === 'none' ? '' : '<span class="wp-block-query-pagination-next-arrow is-arrow-' . $arrow . '" aria-hidden="true">' . $glyph(true) . '</span>')
                        . '</a>'
                    : '',
                'core/query-pagination-numbers' => $this->numbers($paged, $pages, $link),
                default => '',
            };
        }
        $classes = array_values(array_filter([
            'wp-block-query-pagination',
            Styles::align($block->attrs),
            ...Layout::classes('core/query-pagination', $block->attrs),
        ]));
        return '<nav class="' . implode(' ', $classes) . '" aria-label="Pagination">' . implode("\n", array_filter($parts)) . '</nav>';
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

    /** @param callable(int): string $link */
    private function numbers(int $paged, int $pages, callable $link): string
    {
        $items = [];
        for ($page = 1; $page <= $pages; $page++) {
            $items[] = $page === $paged
                ? '<span aria-current="page" class="page-numbers current">' . $page . '</span>'
                : '<a class="page-numbers" href="' . Html::attr($link($page)) . '">' . $page . '</a>';
        }
        return '<div class="wp-block-query-pagination-numbers">' . implode("\n", $items) . '</div>';
    }

    /** A post type archive's title: the type's label, renamed by the post_type_archive_title filter when a plugin does. */
    private function archiveTitle(array $type): string
    {
        $label = (string) ($type['label'] ?? $type['name'] ?? '');
        return (string) \apply_filters('post_type_archive_title', $label, (string) ($type['name'] ?? ''));
    }
}
