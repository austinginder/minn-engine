<?php

require_once __DIR__ . '/WP_REST_Meta_Fields.php';

/** Comments' REST meta field: meta type, subtype and REST field type all comment. */
#[AllowDynamicProperties]
class WP_REST_Comment_Meta_Fields extends WP_REST_Meta_Fields
{
    protected function get_meta_type()
    {
        return 'comment';
    }

    protected function get_meta_subtype()
    {
        return 'comment';
    }

    public function get_rest_field_type()
    {
        return 'comment';
    }
}
