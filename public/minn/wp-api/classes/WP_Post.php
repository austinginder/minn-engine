<?php

/** A post row as an object. */
final class WP_Post
{
    public $ID;
    public $post_author = 0;
    public $post_date = '0000-00-00 00:00:00';
    public $post_date_gmt = '0000-00-00 00:00:00';
    public $post_content = '';
    public $post_title = '';
    public $post_excerpt = '';
    public $post_status = 'publish';
    public $comment_status = 'open';
    public $ping_status = 'open';
    public $post_password = '';
    public $post_name = '';
    public $to_ping = '';
    public $pinged = '';
    public $post_modified = '0000-00-00 00:00:00';
    public $post_modified_gmt = '0000-00-00 00:00:00';
    public $post_content_filtered = '';
    public $post_parent = 0;
    public $guid = '';
    public $menu_order = 0;
    public $post_type = 'post';
    public $post_mime_type = '';
    public $comment_count = 0;
    public $filter;
    public $ancestors = [];
    public $page_template = '';
    public $post_category = [];
    public $tags_input = [];

    public function __construct($post)
    {
        foreach (get_object_vars($post) as $key => $value) {
            $this->{$key} = $value;
        }
        foreach (['ID', 'post_author', 'post_parent', 'menu_order', 'comment_count'] as $int) {
            $this->{$int} = (int) $this->{$int};
        }
    }

    public static function get_instance($post_id)
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return false;
        }
        return get_post($post_id) ?? false;
    }

    public function __isset($key)
    {
        return in_array($key, ['ancestors', 'page_template', 'post_category', 'tags_input'], true) || metadata_exists('post', $this->ID, $key);
    }

    public function __get($key)
    {
        if ($key === 'page_template' && $this->post_type === 'page') {
            return get_post_meta($this->ID, '_wp_page_template', true);
        }
        $value = get_post_meta($this->ID, $key, true);
        return $value;
    }

    public function filter($filter)
    {
        return $this;
    }

    public function to_array()
    {
        $post = get_object_vars($this);
        unset($post['filter'], $post['ancestors'], $post['page_template'], $post['post_category'], $post['tags_input']);
        return $post;
    }
}
