<?php

declare(strict_types=1);

namespace Minn\Blocks\Dynamic\Theme;

use DateTimeImmutable;
use DateTimeZone;
use Minn\Blocks\Block;
use Minn\Blocks\Layout;
use Minn\Blocks\Renderer;
use Minn\Blocks\Styles;
use Minn\Blocks\Wrapper;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Support\Html;
use Minn\Support\Serialized;

/** query, post-template, query-title, query-no-results, query-pagination, term-description. */
final class QueryBlocks
{
    /** @var list<array{posts: list<array>, total: int, inherit: bool}> */
    private array $queries = [];

    public function __construct(
        private readonly Posts $posts,
        private readonly Site $site,
        private readonly Permalinks $permalinks,
    ) {
    }

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
        $renderer->registerDynamic('core/term-description', $this->termDescription(...));
    }

    /** The query wrapper is stored markup; the block sets the loop its children read. */
    private function query(Block $block, Renderer $renderer): string
    {
        $context = $renderer->context();
        $attrs = (array) $block->attr('query', []);
        if (!empty($attrs['inherit'])) {
            $this->queries[] = ['posts' => $context->posts, 'total' => $context->total, 'inherit' => true];
        } else {
            $perPage = max(1, (int) ($attrs['perPage'] ?? 10));
            $sticky = ($attrs['sticky'] ?? '') === 'exclude' ? [] : Serialized::intList($this->site->option('sticky_posts'));
            $filter = [];
            if (!empty($attrs['author'])) {
                $filter['author'] = (int) $attrs['author'];
            }
            if (!empty($attrs['search'])) {
                $filter['search'] = (string) $attrs['search'];
            }
            $result = $this->posts->listing($filter, 1, $perPage, $sticky, stickyExtra: true);
            $this->queries[] = ['posts' => $result['posts'], 'total' => $result['total'], 'inherit' => false];
        }
        $out = '';
        $inner = 0;
        foreach ($block->innerContent as $chunk) {
            $out .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$inner++]);
        }
        array_pop($this->queries);
        return Html::addClasses($out, Layout::classes('query', $block->attrs));
    }

    private function current(): array
    {
        return $this->queries[count($this->queries) - 1] ?? ['posts' => [], 'total' => 0, 'inherit' => false];
    }

    private function postTemplate(Block $block, Renderer $renderer): string
    {
        $query = $this->current();
        if ($query['posts'] === []) {
            return '';
        }
        $context = $renderer->context();
        $sticky = Serialized::intList($this->site->option('sticky_posts'));
        $onFrontPage = $context->resolution->kind === Kind::Home && $context->resolution->paged === 1;
        $items = '';
        foreach ($query['posts'] as $post) {
            $context->pushPost($post);
            $inner = '';
            $index = 0;
            foreach ($block->innerContent as $chunk) {
                $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
            }
            $context->popPost();
            $isSticky = $onFrontPage && in_array((int) $post['ID'], $sticky, true);
            $items .= '<li class="' . implode(' ', $this->postClasses($post, $isSticky)) . '">' . $inner . '</li>';
        }
        $columns = (int) ($block->attrs['layout']['columnCount'] ?? 0);
        $open = Wrapper::open(
            'ul',
            'wp-block-post-template',
            $block,
            styleFirst: true,
            extraClasses: array_values(array_filter([$columns > 0 ? 'columns-' . $columns : null, Styles::align($block->attrs)])),
            trailingClasses: Layout::classes('post-template', $block->attrs),
        );
        return $open . $items . '</ul>';
    }

    /** @return list<string> */
    private function postClasses(array $post, bool $sticky): array
    {
        $id = (int) $post['ID'];
        $type = (string) $post['post_type'];
        $classes = ['wp-block-post', 'post-' . $id, $type, 'type-' . $type, 'status-' . $post['post_status']];
        if ($type === 'post') {
            $formats = $this->posts->terms($id, 'post_format');
            $classes[] = 'format-' . ($formats === [] ? 'standard' : str_replace('post-format-', '', $formats[0][1]));
        }
        if ($sticky) {
            $classes[] = 'sticky';
        }
        if ($post['post_password'] !== '') {
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
        $titled = static fn (string $label, string $name) => $prefix ? $label . ': <span>' . $name . '</span>' : $name;
        $text = match ($resolution->kind) {
            Kind::Category => $titled('Category', Html::esc((string) $record['name'])),
            Kind::Tag => $titled('Tag', Html::esc((string) $record['name'])),
            Kind::Author => $titled('Author', Html::esc((string) ($record['display_name'] ?? $resolution->authorName))),
            Kind::Date => $this->dateTitle($resolution->date),
            Kind::Search => 'Search results for: ' . Texturize::text('"' . Html::esc((string) $resolution->search) . '"'),
            default => '',
        };
        if ($text === '') {
            return '';
        }
        $level = (int) $block->attr('level', 1);
        $align = Styles::align($block->attrs);
        return Wrapper::open('h' . $level, 'wp-block-query-title', $block, styleFirst: true, extraClasses: $align === null ? [] : [$align]) . $text . '</h' . $level . '>';
    }

    /** @param array{0: int, 1: ?int, 2: ?int}|null $date */
    private function dateTitle(?array $date): string
    {
        if ($date === null) {
            return '';
        }
        [$year, $month, $day] = $date;
        $utc = new DateTimeZone('UTC');
        if ($day !== null) {
            return 'Day: <span>' . (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $utc))->format($this->site->option('date_format') ?: 'F j, Y') . '</span>';
        }
        if ($month !== null) {
            return 'Month: <span>' . (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $utc))->format('F Y') . '</span>';
        }
        return 'Year: <span>' . $year . '</span>';
    }

    private function noResults(Block $block, Renderer $renderer): string
    {
        if ($this->current()['posts'] !== []) {
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
        $query = $this->current();
        $context = $renderer->context();
        if (!$query['inherit'] || $context->totalPages() <= 1) {
            return '';
        }
        $paged = $context->paged();
        $pages = $context->totalPages();
        $base = $this->paginationBase($renderer);
        $link = fn (int $page): string => $this->permalinks->forPaged($base, $page);
        $arrow = (string) $block->attr('paginationArrow', 'none');
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
            ...Layout::classes('query-pagination', $block->attrs, 'flex'),
        ]));
        return '<nav class="' . implode(' ', $classes) . '" aria-label="Pagination">' . implode("\n", array_filter($parts)) . '</nav>';
    }

    /** The current listing's own URL, without a page number. */
    private function paginationBase(Renderer $renderer): string
    {
        $resolution = $renderer->context()->resolution;
        $record = $resolution->record ?? [];
        return match ($resolution->kind) {
            Kind::Category, Kind::Tag => $this->permalinks->forTerm($record),
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

    private function termDescription(Block $block, Renderer $renderer): string
    {
        $resolution = $renderer->context()->resolution;
        if (!in_array($resolution->kind, [Kind::Category, Kind::Tag], true)) {
            return '';
        }
        $term = (new \Minn\Content\Terms(\Minn\Db::shared()))->row((int) $resolution->record['term_id'], (string) $resolution->record['taxonomy']);
        $description = trim((string) ($term['description'] ?? ''));
        if ($description === '') {
            return '';
        }
        $classes = implode(' ', ['wp-block-term-description', ...Styles::classes($block->attrs)]);
        return '<div class="' . $classes . '"><p>' . Html::esc($description) . '</p></div>';
    }
}
