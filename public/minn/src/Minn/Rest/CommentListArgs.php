<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The WP_Comment_Query arguments a comment list request makes, as the
 * reference makes them before rest_comment_query (probe
 * rest-comment-lists): each declared parameter the request carries under
 * its query name, an empty email and search when none is given, the
 * orderby name mapped to the query's, the found rows counted, the posts
 * loaded, the date bounds as one clause, the offset the page makes when
 * none is given, and ids alone for a HEAD request.
 */
final class CommentListArgs
{
    private const MAPPINGS = ['author' => 'author__in', 'author_email' => 'author_email', 'author_exclude' => 'author__not_in', 'exclude' => 'comment__not_in', 'include' => 'comment__in', 'offset' => 'offset', 'order' => 'order', 'parent' => 'parent__in', 'parent_exclude' => 'parent__not_in', 'per_page' => 'number', 'post' => 'post__in', 'search' => 'search', 'status' => 'status', 'type' => 'type'];
    private const ORDERBY = ['date' => 'comment_date', 'date_gmt' => 'comment_date_gmt', 'id' => 'comment_ID', 'include' => 'comment__in', 'post' => 'comment_post_ID', 'parent' => 'comment_parent', 'type' => 'comment_type'];

    /**
     * The arguments before plugins see them: a parameter maps when the
     * collection (as rest_comment_collection_params left it) still has it.
     *
     * @param array<string, mixed> $registered
     * @return array<string, mixed>
     */
    public static function of(\WP_REST_Request $wp, array $registered, string $method): array
    {
        $args = [];
        foreach (self::MAPPINGS as $param => $var) {
            if (isset($registered[$param], $wp[$param])) {
                $args[$var] = $wp[$param];
            }
        }
        $args += ['author_email' => '', 'search' => ''];
        $args['orderby'] = self::ORDERBY[(string) $wp['orderby']] ?? 'comment_date_gmt';
        $args['no_found_rows'] = false;
        $args['update_comment_post_cache'] = true;
        $bounds = array_filter(['before' => $wp['before'], 'after' => $wp['after']], static fn ($date) => $date !== null && $date !== '');
        $args['date_query'] = $bounds === [] ? [] : [$bounds];
        if (empty($wp['offset'])) {
            $args['offset'] = (int) ($args['number'] ?? 0) * (abs((int) ($wp['page'])) - 1);
        }
        if ($method === 'HEAD') {
            $args += ['fields' => 'ids', 'update_comment_meta_cache' => false];
        }
        return $args;
    }
}
