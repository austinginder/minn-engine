<?php

require_once __DIR__ . '/WP_REST_Meta_Fields.php';

/** A post type's REST meta field: meta type post, its post type the subtype and REST field type. */
#[AllowDynamicProperties]
class WP_REST_Post_Meta_Fields extends WP_REST_Meta_Fields
{
    protected $post_type;

    public function __construct($post_type)
    {
        $this->post_type = $post_type;
    }

    protected function get_meta_type()
    {
        return 'post';
    }

    protected function get_meta_subtype()
    {
        return $this->post_type;
    }

    public function get_rest_field_type()
    {
        return $this->post_type;
    }
}
