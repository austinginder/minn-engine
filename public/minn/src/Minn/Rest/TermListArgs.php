<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The get_terms arguments a term list request makes, as the reference
 * makes them before rest_{taxonomy}_query (probe rest-term-lists): the
 * taxonomy, each declared parameter the request carries under its query
 * name (include_slugs ordering as slug__in), the offset (the page's unless
 * the list takes one and it is given), the parent a hierarchical list
 * takes, and ids alone for a HEAD request.
 */
final class TermListArgs
{
    private const MAPPINGS = ['exclude' => 'exclude', 'include' => 'include', 'order' => 'order', 'orderby' => 'orderby', 'post' => 'post', 'hide_empty' => 'hide_empty', 'per_page' => 'number', 'search' => 'search', 'slug' => 'slug'];

    /**
     * The arguments before plugins see them.
     *
     * @param array<string, mixed> $registered the collection, as rest_{taxonomy}_collection_params left it
     * @return array<string, mixed>
     */
    public static function of(\WP_REST_Request $wp, array $registered, string $taxonomy, string $method): array
    {
        $args = ['taxonomy' => $taxonomy];
        foreach (self::MAPPINGS as $param => $var) {
            if (isset($registered[$param], $wp[$param])) {
                $args[$var] = $param === 'orderby' && $wp[$param] === 'include_slugs' ? 'slug__in' : $wp[$param];
            }
        }
        $args['offset'] = isset($registered['offset'], $wp['offset']) ? $wp['offset'] : (int) ($args['number'] ?? 0) * (abs((int) ($wp['page'])) - 1);
        if (isset($registered['parent'], $wp['parent'])) {
            $args['parent'] = $wp['parent'];
        }
        if ($method === 'HEAD') {
            $args += ['fields' => 'ids', 'update_term_meta_cache' => false];
        }
        return $args;
    }
}
