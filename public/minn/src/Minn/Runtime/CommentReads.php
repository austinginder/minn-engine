<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What WordPress's comment functions tell about comments: the comments a
 * query finds, how many a post or the site has in each status, and when an
 * approved comment last arrived.
 */
final class CommentReads
{
    /** The comments a query finds (get_comments): comments, ids or a count, as the comment query answers. */
    public static function query(mixed $args = ''): mixed
    {
        return Handoff::newCommentQuery()->query($args);
    }

    /** How many comments a post or the site has in each status (wp_count_comments), unless wp_count_comments answers first. */
    public static function counts(mixed $postId = 0): object
    {
        $postId = (int) $postId;
        $filtered = Runtime::hooks()->filter('wp_count_comments', [[], $postId]);
        if (!empty($filtered)) {
            return (object) $filtered;
        }
        return (object) (new CommentQuery(Runtime::current()->db))->breakdown($postId);
    }

    /**
     * When an approved comment last arrived (get_lastcommentmodified), in
     * GMT ("gmt", and the server's own, the GMT date moved by the server's
     * offset in the database, with microseconds) or the site's time
     * ("blog"); false with none.
     */
    public static function lastModified(mixed $timezone = 'server'): mixed
    {
        $timezone = strtolower((string) $timezone);
        $column = match ($timezone) {
            'blog' => 'comment_date',
            'gmt' => 'comment_date_gmt',
            default => "DATE_ADD(comment_date_gmt, INTERVAL '" . (int) date('Z') . "' SECOND)",
        };
        $wpdb = $GLOBALS['wpdb'];
        $value = $wpdb->get_var("SELECT {$column} FROM {$wpdb->comments} WHERE comment_approved = '1' ORDER BY comment_date_gmt DESC LIMIT 1");
        return Runtime::hooks()->filter('get_lastcommentmodified', [$value === null ? false : $value, $timezone]);
    }
}
