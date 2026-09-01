<?php

declare(strict_types=1);

namespace Minn\Blocks;

/** The query variables a Query Loop block's context asks for, the way the reference's query block builds them. */
final class QueryVars
{
    /**
     * The query vars a query block's context amounts to.
     *
     * @param array<string, mixed>|null $context the block's `query` context
     * @param list<int> $sticky the site's sticky post ids
     * @param callable(string): bool $postTypeExists
     * @param callable(string): bool $taxonomyViewable
     */
    public static function fromContext(?array $context, int $page, array $sticky, callable $postTypeExists, callable $taxonomyViewable): array
    {
        $query = ['post_type' => 'post', 'order' => 'DESC', 'orderby' => 'date', 'post__not_in' => [], 'tax_query' => []];
        if ($context === null) {
            return $query;
        }
        if (!empty($context['postType']) && $postTypeExists((string) $context['postType'])) {
            $query['post_type'] = (string) $context['postType'];
        }
        if (isset($context['sticky']) && $context['sticky'] !== '') {
            if ($context['sticky'] === 'only') {
                $query['post__in'] = $sticky === [] ? [0] : $sticky;
                $query['ignore_sticky_posts'] = 1;
            } else {
                $query['post__not_in'] = [...$query['post__not_in'], ...$sticky];
            }
        }
        if (!empty($context['exclude'])) {
            $query['post__not_in'] = [...$query['post__not_in'], ...array_map('intval', (array) $context['exclude'])];
        }
        if (!empty($context['perPage'])) {
            $query['offset'] = ((int) $context['perPage'] * ($page - 1)) + (int) ($context['offset'] ?? 0);
            $query['posts_per_page'] = (int) $context['perPage'];
        }
        $query['tax_query'] = self::taxQuery($context, $taxonomyViewable);
        if (isset($context['order'])) {
            $query['order'] = strtoupper((string) $context['order']);
        }
        if (isset($context['orderBy'])) {
            $query['orderby'] = (string) $context['orderBy'];
        }
        if (isset($context['author'])) {
            $query['author__in'] = array_map('intval', preg_split('/[\s,]+/', (string) $context['author'], -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }
        if (!empty($context['search'])) {
            $query['s'] = (string) $context['search'];
        }
        if (!empty($context['parents'])) {
            $query['post_parent__in'] = array_map('intval', (array) $context['parents']);
        }
        return $query;
    }

    /** Taxonomy terms and post formats each become one clause group; the two groups AND together. */
    private static function taxQuery(array $context, callable $taxonomyViewable): array
    {
        $groups = [];
        if (!empty($context['taxQuery']) && is_array($context['taxQuery'])) {
            $clauses = [];
            foreach ($context['taxQuery'] as $taxonomy => $terms) {
                if ($taxonomyViewable((string) $taxonomy) && !empty($terms)) {
                    $clauses[] = ['taxonomy' => (string) $taxonomy, 'terms' => array_map('intval', array_filter((array) $terms)), 'include_children' => false];
                }
            }
            $groups[] = $clauses;
        }
        if (!empty($context['format']) && is_array($context['format'])) {
            $formats = array_values(array_filter($context['format'], static fn ($format) => $format !== 'standard'));
            $clause = ['relation' => 'OR'];
            if ($formats !== []) {
                $clause[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => array_map(static fn ($format) => 'post-format-' . $format, $formats), 'operator' => 'IN'];
            }
            if (in_array('standard', $context['format'], true)) {
                $clause[] = ['taxonomy' => 'post_format', 'operator' => 'NOT EXISTS'];
            }
            $groups[] = $clause;
        }
        return $groups === [] ? [] : ['relation' => 'AND'] + $groups;
    }
}
