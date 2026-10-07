<?php

use Minn\Query\CommentOrder;
use Minn\Runtime\CommentQueryRunner;

/**
 * The comment query plugin code runs, as the reference runs it:
 * Minn\Runtime\CommentQueryRunner fires the query's hooks, writes its
 * clauses and request, and threads the results when asked.
 */
#[AllowDynamicProperties]
class WP_Comment_Query
{
    public $request;
    public $meta_query = false;
    public $date_query = false;
    public $query_vars;
    public $query_var_defaults;
    public $comments;
    public $found_comments = 0;
    public $max_num_pages = 0;
    protected $sql_clauses = ['select' => '', 'from' => '', 'where' => '', 'groupby' => '', 'orderby' => '', 'limits' => ''];

    public function __construct($query = '')
    {
        $this->query_var_defaults = ['author_email' => '', 'author_url' => '', 'author__in' => '', 'author__not_in' => '', 'include_unapproved' => '', 'fields' => '', 'ID' => '', 'comment__in' => '', 'comment__not_in' => '', 'karma' => '', 'number' => '', 'offset' => '', 'no_found_rows' => true, 'orderby' => '', 'order' => 'DESC', 'paged' => 1, 'parent' => '', 'parent__in' => '', 'parent__not_in' => '', 'post_author__in' => '', 'post_author__not_in' => '', 'post_ID' => '', 'post_id' => 0, 'post__in' => '', 'post__not_in' => '', 'post_author' => '', 'post_name' => '', 'post_parent' => '', 'post_status' => '', 'post_type' => '', 'status' => 'all', 'type' => '', 'type__in' => '', 'type__not_in' => '', 'user_id' => '', 'search' => '', 'count' => false, 'meta_key' => '', 'meta_value' => '', 'meta_query' => '', 'date_query' => null, 'hierarchical' => false, 'cache_domain' => 'core', 'update_comment_meta_cache' => true, 'update_comment_post_cache' => false];
        if (!empty($query)) {
            $this->query($query);
        }
    }

    public function parse_query($query = '')
    {
        if (empty($query)) {
            $query = $this->query_vars;
        }
        $this->query_vars = wp_parse_args($query, $this->query_var_defaults);
        do_action_ref_array('parse_comment_query', [&$this]);
    }

    /** The rows (or ids, or the count) for the vars given. */
    public function query($query)
    {
        $this->query_vars = wp_parse_args($query);
        return $this->get_comments();
    }

    /** The comments (or ids, or a count) for the vars: Minn\Runtime\CommentQueryRunner runs the reference's steps. */
    public function get_comments()
    {
        global $wpdb;
        return (new CommentQueryRunner($wpdb))->run($this);
    }

    protected function parse_orderby($orderby)
    {
        global $wpdb;
        $clauses = $this->meta_query instanceof WP_Meta_Query ? (array) $this->meta_query->get_clauses() : [];
        return CommentOrder::clause((string) $orderby, (array) $this->query_vars, $wpdb->comments, $wpdb->commentmeta, $clauses) ?: false;
    }

    protected function parse_order($order)
    {
        return CommentOrder::direction($order);
    }
}
