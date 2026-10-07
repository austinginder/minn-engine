<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The WP_Query arguments a post list request makes, as the reference makes
 * them before rest_{type}_query (probe rest-post-lists): each registered
 * parameter the request carries under its query name, the date bounds,
 * per_page, stickies (only them, or none of them), an exact search, each
 * taxonomy's include and exclude under the request's relation, formats
 * (standard as no format at all), the type, and ids alone for a HEAD
 * request. Then each through rest_query_var-{name}, the orderby names
 * mapped to the query's, and stickies left where they fall unless asked.
 */
final class PostListArgs
{
    private const MAPPINGS = ['author' => 'author__in', 'author_exclude' => 'author__not_in', 'exclude' => 'post__not_in', 'include' => 'post__in', 'ignore_sticky' => 'ignore_sticky_posts', 'menu_order' => 'menu_order', 'offset' => 'offset', 'order' => 'order', 'orderby' => 'orderby', 'page' => 'paged', 'parent' => 'post_parent__in', 'parent_exclude' => 'post_parent__not_in', 'search' => 's', 'search_columns' => 'search_columns', 'slug' => 'post_name__in', 'status' => 'post_status'];
    private const DATES = [['before', 'before', 'post_date'], ['modified_before', 'before', 'post_modified'], ['after', 'after', 'post_date'], ['modified_after', 'after', 'post_modified']];
    private const ORDERBY = ['id' => 'ID', 'include' => 'post__in', 'slug' => 'post_name', 'include_slugs' => 'post_name__in'];
    private const IN = 'post__in';
    private const NOT_IN = 'post__not_in';

    /**
     * The arguments before plugins see them.
     *
     * @param array<string, mixed> $registered the list's parameters
     * @return array<string, mixed>
     */
    public static function of(\WP_REST_Request $request, array $registered, string $type): array
    {
        $args = [];
        foreach (self::MAPPINGS as $param => $var) {
            if (isset($registered[$param], $request[$param])) {
                $args[$var] = $request[$param];
            }
        }
        $args['date_query'] = [];
        foreach (self::DATES as [$param, $bound, $column]) {
            if (isset($registered[$param], $request[$param])) {
                $args['date_query'][] = [$bound => $request[$param], 'column' => $column];
            }
        }
        if (isset($registered['per_page'])) {
            $args['posts_per_page'] = $request['per_page'];
        }
        if (isset($registered['sticky'], $request['sticky'])) {
            $args = self::sticky($args, (bool) $request['sticky']);
        }
        if (!empty($args[self::IN])) {
            // A list of ids keeps its own order: no stickies on top.
            unset($args['ignore_sticky_posts']);
        }
        if (isset($registered['search_semantics'], $request['search_semantics']) && $request['search_semantics'] === 'exact') {
            $args['exact'] = true;
        }
        $args = self::taxonomies($args, $request, $type);
        if (isset($registered['format'], $request['format'])) {
            $args = self::formats($args, (array) $request['format']);
        }
        $args += ['post_type' => $type];
        if ($request->is_method('HEAD')) {
            $args += ['fields' => 'ids', 'update_post_term_cache' => false, 'update_post_meta_cache' => false];
        }
        return $args;
    }

    /**
     * The query variables from the arguments plugins left: each through
     * rest_query_var-{name}, and the list's orderby names as the query's.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public static function queryVars(array $args, \WP_REST_Request $request): array
    {
        $vars = [];
        foreach ($args as $key => $value) {
            $vars[$key] = \apply_filters("rest_query_var-{$key}", $value);
        }
        $orderby = $request['orderby'];
        if (isset($vars['orderby'], $orderby) && is_string($orderby) && isset(self::ORDERBY[$orderby])) {
            $vars['orderby'] = self::ORDERBY[$orderby];
        }
        if (($vars['post_type'] ?? '') === 'attachment') {
            $vars = self::mimeTypes($vars, $request);
        }
        // A list puts no stickies on top unless it asks for them (and an id list never does).
        return $vars + ['ignore_sticky_posts' => true];
    }

    /**
     * A media list's MIME types, set after plugins have seen the query: the
     * MIME types asked for (those the site allows), else every one of the
     * media types asked for.
     *
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    private static function mimeTypes(array $vars, \WP_REST_Request $request): array
    {
        $byType = PostCollectionParams::mediaTypes();
        $allowed = array_merge(...array_values($byType));
        $asked = array_values(array_intersect(array_map('strval', (array) $request['mime_type']), $allowed));
        if ($asked === []) {
            $asked = array_merge(...array_map(static fn ($type) => $byType[(string) $type] ?? [], array_values((array) $request['media_type'])));
        }
        return $asked === [] ? $vars : ['post_mime_type' => $asked] + $vars;
    }

    /** Only sticky posts (those among the ids asked for), or none of them. @param array<string, mixed> $args @return array<string, mixed> */
    private static function sticky(array $args, bool $only): array
    {
        $sticky = \get_option('sticky_posts', []);
        $sticky = is_array($sticky) ? $sticky : [];
        $in = $args[self::IN] ?? [];
        if ($only) {
            $in = !empty($in) ? array_intersect($sticky, (array) $in) : $sticky;
            // No ids in common still means no posts.
            return array_replace($args, [self::IN => $in ?: [0]]);
        }
        return $sticky === [] ? $args : array_replace($args, [self::NOT_IN => array_merge((array) ($args[self::NOT_IN] ?? []), $sticky)]);
    }

    /** Each REST taxonomy's include (ids, or a query with children and an operator) and exclude, under the request's relation. @param array<string, mixed> $args @return array<string, mixed> */
    private static function taxonomies(array $args, \WP_REST_Request $request, string $type): array
    {
        if ($request['tax_relation']) {
            $args['tax_query'] = ['relation' => $request['tax_relation']];
        }
        foreach (\get_object_taxonomies($type, 'objects') as $taxonomy) {
            if (empty($taxonomy->show_in_rest)) {
                continue;
            }
            $base = $taxonomy->rest_base ?: $taxonomy->name;
            foreach ([$base => 'IN', "{$base}_exclude" => 'NOT IN'] as $param => $operator) {
                $clause = self::termClause($request[$param], $operator);
                if ($clause !== null) {
                    $args['tax_query'][] = ['taxonomy' => $taxonomy->name, 'field' => 'term_id'] + $clause;
                }
            }
        }
        return $args;
    }

    /** One term filter as a clause: its terms, whether children count, and its operator (an include may ask for all). @return array<string, mixed>|null */
    private static function termClause(mixed $given, string $operator): ?array
    {
        if (!$given) {
            return null;
        }
        [$terms, $children] = [[], false];
        if (\rest_is_array($given)) {
            $terms = $given;
        } elseif (\rest_is_object($given)) {
            $terms = empty($given['terms']) ? [] : $given['terms'];
            $children = !empty($given['include_children']);
            $operator = $operator === 'IN' && ($given['operator'] ?? null) === 'AND' ? 'AND' : $operator;
        }
        return $terms ? ['terms' => $terms, 'include_children' => $children, 'operator' => $operator] : null;
    }

    /**
     * Formats as an OR of their terms, standard as no format at all; beside
     * other term filters, a group of their own.
     *
     * @param array<string, mixed> $args
     * @param array<array-key, string> $formats
     * @return array<string, mixed>
     */
    private static function formats(array $args, array $formats): array
    {
        $query = ['relation' => 'OR'];
        $standard = array_search('standard', $formats, true);
        if ($standard !== false) {
            $query[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'operator' => 'NOT EXISTS'];
            unset($formats[$standard]);
        }
        if ($formats !== []) {
            $query[] = ['taxonomy' => 'post_format', 'field' => 'slug', 'terms' => array_map(static fn (string $format) => "post-format-{$format}", $formats), 'operator' => 'IN'];
        }
        if (isset($args['tax_query'])) {
            $args['tax_query'][] = ['relation' => 'AND', $query];
        } else {
            $args['tax_query'] = $query;
        }
        return $args;
    }
}
