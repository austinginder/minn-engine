<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * The query variables a Query Loop block's context asks for, the way the
 * reference's build_query_vars_from_query_block builds them (probe
 * query-loop): a viewable post type; stickies only, left out, or ignored;
 * exclusions; a page size and offset for the page asked for; the old
 * category and tag ids and each viewable taxonomy's terms, merged; formats
 * (standard as no format) OR'd, beside the terms in a group of their own;
 * order, orderby, authors (a list, a comma list, or one id), a search, and
 * parents for a hierarchical type.
 */
final class QueryVars
{
    /**
     * The query vars a query block's context amounts to.
     *
     * @param array<string, mixed>|null $context the block's `query` context
     * @param list<int> $sticky the site's sticky post ids
     * @param array{viewableType: callable(string): bool, hierarchicalType: callable(string): bool, viewableTaxonomy: callable(string): bool, formats: list<string>} $site what the site says about types, taxonomies and formats
     */
    public static function fromContext(?array $context, int $page, array $sticky, array $site): array
    {
        $query = ['post_type' => 'post', 'order' => 'DESC', 'orderby' => 'date', 'post__not_in' => [], 'tax_query' => []];
        if ($context === null) {
            return $query;
        }
        if (!empty($context['postType']) && ($site['viewableType'])((string) $context['postType'])) {
            $query['post_type'] = $context['postType'];
        }
        $query = self::sticky($query, $context['sticky'] ?? null, $sticky);
        if (!empty($context['exclude'])) {
            $query['post__not_in'] = array_merge($query['post__not_in'], array_filter(array_map('intval', (array) $context['exclude'])));
        }
        if (isset($context['perPage']) && is_numeric($context['perPage'])) {
            $perPage = abs((int) $context['perPage']);
            $offset = isset($context['offset']) && is_numeric($context['offset']) ? abs((int) $context['offset']) : 0;
            $query['offset'] = ($perPage * ($page - 1)) + $offset;
            $query['posts_per_page'] = $perPage;
        }
        $query['tax_query'] = array_merge($query['tax_query'], self::idTerms($context), self::taxonomyTerms($context, $site['viewableTaxonomy']));
        $query = self::formats($query, $context['format'] ?? null, $site['formats']);
        if (isset($context['order']) && in_array(strtoupper((string) $context['order']), ['ASC', 'DESC'], true)) {
            $query['order'] = strtoupper((string) $context['order']);
        }
        if (isset($context['orderBy'])) {
            $query['orderby'] = $context['orderBy'];
        }
        $query = self::author($query, $context['author'] ?? null);
        if (!empty($context['search'])) {
            $query['s'] = $context['search'];
        }
        if (!empty($context['parents']) && ($site['hierarchicalType'])((string) $query['post_type'])) {
            $query['post_parent__in'] = array_unique(array_map('intval', (array) $context['parents']));
        }
        return $query;
    }

    /** @param array<string, mixed> $query @param list<int> $sticky @return array<string, mixed> */
    private static function sticky(array $query, mixed $setting, array $sticky): array
    {
        return match ($setting) {
            'only' => $query + ['post__in' => $sticky === [] ? [0] : $sticky, 'ignore_sticky_posts' => 1],
            'exclude' => array_replace($query, ['post__not_in' => array_merge($query['post__not_in'], $sticky)]),
            'ignore' => $query + ['ignore_sticky_posts' => 1],
            default => $query,
        };
    }

    /** The old categoryIds and tagIds settings as clauses. @param array<string, mixed> $context @return list<array<string, mixed>> */
    private static function idTerms(array $context): array
    {
        $clauses = [];
        foreach (['categoryIds' => 'category', 'tagIds' => 'post_tag'] as $key => $taxonomy) {
            if (!empty($context[$key])) {
                $clauses[] = ['taxonomy' => $taxonomy, 'terms' => array_filter(array_map('intval', (array) $context[$key])), 'include_children' => false];
            }
        }
        return $clauses;
    }

    /** Each viewable taxonomy's terms as a clause. @param array<string, mixed> $context @param callable(string): bool $viewable @return list<array<string, mixed>> */
    private static function taxonomyTerms(array $context, callable $viewable): array
    {
        $clauses = [];
        foreach ((array) ($context['taxQuery'] ?? []) as $taxonomy => $terms) {
            if ($viewable((string) $taxonomy) && !empty($terms)) {
                $clauses[] = ['taxonomy' => $taxonomy, 'terms' => array_filter(array_map('intval', (array) $terms)), 'include_children' => false];
            }
        }
        return $clauses;
    }

    /**
     * The formats asked for (standard, or one the site knows) OR'd; beside
     * other term clauses, AND'd with them as a group.
     *
     * @param array<string, mixed> $query
     * @param list<string> $known
     * @return array<string, mixed>
     */
    private static function formats(array $query, mixed $formats, array $known): array
    {
        if (empty($formats) || !is_array($formats)) {
            return $query;
        }
        $formats = array_intersect($formats, ['standard', ...$known]);
        $clause = ['relation' => 'OR'];
        $standard = array_search('standard', $formats, true);
        if ($standard !== false) {
            $clause[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'operator' => 'NOT EXISTS'];
            unset($formats[$standard]);
        }
        if ($formats !== []) {
            $clause[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => array_map(static fn ($format) => "post-format-{$format}", $formats), 'operator' => 'IN'];
        }
        if (count($clause) === 1) {
            return $query;
        }
        $query['tax_query'] = empty($query['tax_query']) ? $clause : ['relation' => 'AND', $query['tax_query'], $clause];
        return $query;
    }

    /** Authors: a list or a comma list as author__in, one id as author. @param array<string, mixed> $query @return array<string, mixed> */
    private static function author(array $query, mixed $author): array
    {
        return match (true) {
            is_array($author) => $query + ['author__in' => array_filter(array_map('intval', $author))],
            is_string($author) => $query + ['author__in' => array_filter(array_map('intval', explode(',', $author)))],
            is_int($author) && $author > 0 => $query + ['author' => $author],
            default => $query,
        };
    }
}
