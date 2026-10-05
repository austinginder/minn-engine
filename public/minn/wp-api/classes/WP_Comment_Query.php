<?php

use Minn\Runtime\CommentQuery;
use Minn\Runtime\Runtime;

/** The comment query object: vars in the reference's order, rows from Minn\Runtime\CommentQuery. */
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

    public function get_comments()
    {
        $this->parse_query();
        do_action_ref_array('pre_get_comments', [&$this]);
        $vars = $this->query_vars;
        $args = array_intersect_key($vars, CommentQuery::DEFAULTS) + CommentQuery::DEFAULTS;
        foreach (['post__in', 'include_unapproved', 'comment__in', 'comment__not_in', 'author__in'] as $list) {
            $args[$list] = $args[$list] === '' ? [] : (array) $args[$list];
        }
        $args['orderby'] = $args['orderby'] === '' ? 'comment_date_gmt' : $args['orderby'];
        if ($args['number'] !== '' && (int) $args['number'] > 0 && (int) $vars['paged'] > 1 && (int) $args['offset'] === 0) {
            $args['offset'] = ((int) $vars['paged'] - 1) * (int) $args['number'];
        }
        $engine = new CommentQuery(Runtime::current()->db);
        if ($vars['count']) {
            return $engine->count($args);
        }
        $rows = $engine->rows($args);
        if ($args['number'] !== '' && (int) $args['number'] > 0 && !$vars['no_found_rows']) {
            $this->found_comments = $engine->count(['number' => '', 'offset' => 0] + $args);
            $this->max_num_pages = (int) ceil($this->found_comments / (int) $args['number']);
        }
        if ($vars['fields'] === 'ids') {
            $this->comments = array_map(static fn (array $r) => (int) $r['comment_ID'], $rows);
            return $this->comments;
        }
        $comments = array_map(static fn (array $r) => new WP_Comment((object) $r), $rows);
        $comments = apply_filters_ref_array('the_comments', [$comments, &$this]);
        $this->comments = $vars['hierarchical'] === 'threaded' ? $this->threaded($comments) : $comments;
        return $this->comments;
    }

    /** Replies hang under their parents; the top level comes back. */
    private function threaded(array $comments): array
    {
        $byId = [];
        foreach ($comments as $comment) {
            $byId[(int) $comment->comment_ID] = $comment;
        }
        $top = [];
        foreach ($comments as $comment) {
            $parent = (int) $comment->comment_parent;
            if ($parent > 0 && isset($byId[$parent])) {
                $byId[$parent]->add_child($comment);
            } else {
                $top[] = $comment;
            }
        }
        return $top;
    }
}
