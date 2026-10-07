<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The comments a comments feed's main query carries, as the reference
 * finds them (probe comment-feed-query). For a listing (the site's, an
 * archive's, a search's) they come before its posts, which are then
 * narrowed to the posts those comments are on; for a single post they come
 * after it. Either way the comment_feed_* filters shape the query (handed
 * the WP_Query), only approved comments that are not notes count, newest
 * first, as many as a feed carries.
 */
final class CommentFeedQuery
{
    /** A listing's comments, then its posts narrowed to theirs: the posts query's join and where as they stand after posts_join. */
    public static function listing(\WP_Query $query, PostQueryParts $parts, object $wpdb): void
    {
        $comments = $wpdb->comments;
        $posts = $wpdb->posts;
        if ($query->is_archive || $query->is_search) {
            $join = "JOIN {$posts} ON ( {$comments}.comment_post_ID = {$posts}.ID ) {$parts->join} ";
            $where = "WHERE comment_approved = '1' AND {$comments}.comment_type != 'note' {$parts->where}";
            $groupby = "{$comments}.comment_id";
        } else {
            $join = "JOIN {$posts} ON ( {$comments}.comment_post_ID = {$posts}.ID )";
            $where = "WHERE ( post_status = 'publish' OR ( post_status = 'inherit' AND post_type = 'attachment' ) ) AND comment_approved = '1' AND {$comments}.comment_type != 'note'";
            $groupby = '';
        }
        $sql = self::request($query, $join, $where, $groupby, "SELECT {$parts->distinct} {$comments}.* FROM {$comments}");
        self::keep($query, (array) $wpdb->get_results($sql));
        $ids = implode(',', array_map(static fn ($comment): int => (int) $comment->comment_post_ID, $query->comments));
        $parts->join = '';
        $parts->where = $ids !== '' ? "AND {$posts}.ID IN ({$ids}) " : 'AND 0';
    }

    /** A single post's comments, once the query has found the post. */
    public static function single(\WP_Query $query, object $wpdb): void
    {
        $comments = $wpdb->comments;
        $id = $query->posts[0]->ID;
        $sql = self::request($query, '', "WHERE comment_post_ID = '{$id}' AND comment_approved = '1' AND {$comments}.comment_type != 'note'", '', "SELECT {$comments}.comment_ID FROM {$comments}");
        self::keep($query, (array) $wpdb->get_col($sql));
    }

    /** The request, its pieces through the comment_feed_* filters. */
    private static function request(\WP_Query $query, string $join, string $where, string $groupby, string $select): string
    {
        $join = \apply_filters_ref_array('comment_feed_join', [$join, &$query]);
        $where = \apply_filters_ref_array('comment_feed_where', [$where, &$query]);
        $groupby = \apply_filters_ref_array('comment_feed_groupby', [$groupby, &$query]);
        $orderby = \apply_filters_ref_array('comment_feed_orderby', ['comment_date_gmt DESC', &$query]);
        $limits = \apply_filters_ref_array('comment_feed_limits', ['LIMIT ' . \get_option('posts_per_rss'), &$query]);
        return "{$select} {$join} {$where} " . (!empty($groupby) ? 'GROUP BY ' . $groupby : '') . ' ' . (!empty($orderby) ? 'ORDER BY ' . $orderby : '') . " {$limits}";
    }

    /** @param list<mixed> $rows rows or ids, each made a WP_Comment */
    private static function keep(\WP_Query $query, array $rows): void
    {
        $query->comments = array_values(array_filter(array_map('get_comment', $rows)));
        $query->comment_count = count($query->comments);
    }
}
