<?php

use Minn\Runtime\CommentThreads;

/** A comment row as an object. */
#[AllowDynamicProperties]
final class WP_Comment
{
    public $comment_ID;
    public $comment_post_ID = 0;
    public $comment_author = '';
    public $comment_author_email = '';
    public $comment_author_url = '';
    public $comment_author_IP = '';
    public $comment_date = '0000-00-00 00:00:00';
    public $comment_date_gmt = '0000-00-00 00:00:00';
    public $comment_content;
    public $comment_karma = 0;
    public $comment_approved = '1';
    public $comment_agent = '';
    public $comment_type = 'comment';
    public $comment_parent = 0;
    public $user_id = 0;
    protected $children;
    protected $populated_children = false;
    protected $post_fields = ['post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count'];

    public static function get_instance($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return false;
        }
        $comment = get_comment($id);
        return $comment ?? false;
    }

    public function __construct($comment)
    {
        foreach (get_object_vars($comment) as $key => $value) {
            $this->{$key} = is_int($value) || is_float($value) ? (string) $value : $value;
        }
    }

    public function to_array()
    {
        return get_object_vars($this);
    }

    /** The replies, queried once (threaded, every status by default) then kept; flat lists them depth first. */
    public function get_children($args = [])
    {
        $args = wp_parse_args($args, ['format' => 'tree', 'status' => 'all', 'hierarchical' => 'threaded', 'orderby' => '']);
        $args['parent'] = $this->comment_ID;
        if (!$this->populated_children) {
            $found = get_comments($args);
            array_map([$this, 'add_child'], array_filter(is_array($found) ? $found : [], static fn ($c) => $c instanceof WP_Comment));
            $this->populated_children = true;
        }
        return $args['format'] === 'flat' ? CommentThreads::flatten($this->children ?? [], $args) : ($this->children ?? []);
    }

    public function add_child(WP_Comment $child)
    {
        $this->children[$child->comment_ID] = $child;
    }

    public function get_child($child_id)
    {
        return $this->children[$child_id] ?? false;
    }

    public function populated_children($set)
    {
        $this->populated_children = (bool) $set;
    }

    public function __isset($name)
    {
        return in_array($name, $this->post_fields, true) && $this->comment_post_ID > 0;
    }

    public function __get($name)
    {
        if (in_array($name, $this->post_fields, true)) {
            $post = get_post((int) $this->comment_post_ID);
            return $post === null ? null : $post->{$name};
        }
        return null;
    }
}
