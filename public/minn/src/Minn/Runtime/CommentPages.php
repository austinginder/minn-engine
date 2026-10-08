<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Url;

/**
 * Which page of a post's comments a comment falls on, and the link that
 * opens it there, as the reference works them out (probe comment-pages):
 * the page size from the arguments, the comments_per_page query variable
 * or the option (no pages at all when comments are not paged); a reply
 * takes its top-level comment's page while comments are threaded; a page
 * is the count of older top-level comments over the page size. The
 * default page loses its number when the oldest comments come first.
 */
final class CommentPages
{
    private const DEFAULTS = ['type' => 'all', 'page' => '', 'per_page' => '', 'max_depth' => ''];

    /**
     * The page a comment is on, through get_page_of_comment (handed the
     * arguments as settled and as given); a threaded reply answers with
     * its top-level comment's page. Null for a comment that is not there.
     *
     * @param array<string, mixed> $args
     */
    public static function pageOf(mixed $commentId, array $args): ?int
    {
        $comment = \get_comment($commentId);
        if (!$comment instanceof \WP_Comment) {
            return null;
        }
        $args = \wp_parse_args($args, self::DEFAULTS);
        $original = $args;
        if (Runtime::options()->filtered('page_comments')) {
            $args['per_page'] = $args['per_page'] === '' ? \get_query_var('comments_per_page') : $args['per_page'];
            $args['per_page'] = $args['per_page'] === '' ? Runtime::options()->filtered('comments_per_page') : $args['per_page'];
        }
        if (empty($args['per_page'])) {
            $args['per_page'] = 0;
            $args['page'] = 0;
        }
        $page = 1;
        if ($args['per_page'] >= 1) {
            if ($args['max_depth'] === '') {
                $args['max_depth'] = Runtime::options()->filtered('thread_comments') ? Runtime::options()->filtered('thread_comments_depth') : -1;
            }
            if ($args['max_depth'] > 1 && (int) $comment->comment_parent !== 0) {
                return self::pageOf($comment->comment_parent, $args);
            }
            $older = (int) (new \WP_Comment_Query())->query(self::olderQuery($comment, $args));
            $page = $older === 0 ? 1 : (int) ceil(($older + 1) / $args['per_page']);
        }
        return (int) \apply_filters('get_page_of_comment', $page, $args, $original, $commentId);
    }

    /** @param array<string, mixed> $args the count of the top-level comments older than this one, through get_page_of_comment_query_args */
    private static function olderQuery(\WP_Comment $comment, array $args): array
    {
        $query = [
            'type' => $args['type'],
            'post_id' => $comment->comment_post_ID,
            'fields' => 'ids',
            'count' => true,
            'status' => 'approve',
            'orderby' => 'none',
            'parent' => 0,
            'date_query' => [['column' => $GLOBALS['wpdb']->comments . '.comment_date_gmt', 'before' => $comment->comment_date_gmt]],
        ];
        $unapproved = \is_user_logged_in() ? \get_current_user_id() : \wp_get_unapproved_comment_author_email();
        if ($unapproved) {
            $query['include_unapproved'] = [$unapproved];
        }
        return (array) \apply_filters('get_page_of_comment_query_args', $query);
    }

    /**
     * The page a comment's link names (before get_comment_link): the one
     * asked for, the loop's, or the comment's own; '' for the default page
     * when the oldest comments come first.
     *
     * @param array<string, mixed> $args
     */
    public static function linkPage(?\WP_Comment $comment, array &$args): mixed
    {
        if ($args['cpage'] !== null) {
            return $args['cpage'];
        }
        if ($args['per_page'] === '' && Runtime::options()->filtered('page_comments')) {
            $args['per_page'] = Runtime::options()->filtered('comments_per_page');
        }
        if (empty($args['per_page'])) {
            $args['per_page'] = 0;
            $args['page'] = 0;
        }
        $page = $args['page'];
        if ($page === '' || $page === null) {
            $page = !empty($GLOBALS['in_comment_loop']) ? \get_query_var('cpage') : \get_page_of_comment($comment?->comment_ID, $args);
        }
        return Runtime::options()->filtered('default_comments_page') === 'oldest' && $page === 1 ? '' : $page;
    }

    /**
     * The comments page being shown (the cpage query variable) as the list's
     * links and reply links name it: '' for the default page when the
     * oldest comments come first.
     */
    public static function shown(mixed $cpage): mixed
    {
        return Runtime::options()->filtered('default_comments_page') !== 'newest' && (int) $cpage === 1 ? '' : $cpage;
    }

    /** A post's link opened at a page of its comments, as pretty or plain links write it. */
    public static function link(string $permalink, mixed $page): string
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $pretty = $rewrite instanceof \WP_Rewrite && $rewrite->using_permalinks();
        if ($page && Runtime::options()->filtered('page_comments')) {
            $permalink = $pretty
                ? Url::withTrailingSlash($permalink) . $rewrite->comments_pagination_base . '-' . $page
                : \add_query_arg('cpage', $page, $permalink);
        }
        return $pretty ? \user_trailingslashit($permalink, 'comment') : $permalink;
    }
}
