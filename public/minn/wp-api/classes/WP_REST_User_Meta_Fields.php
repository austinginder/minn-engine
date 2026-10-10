<?php

require_once __DIR__ . '/WP_REST_Meta_Fields.php';

/** Users' REST meta field: meta type, subtype and REST field type all user. */
#[AllowDynamicProperties]
class WP_REST_User_Meta_Fields extends WP_REST_Meta_Fields
{
    protected function get_meta_type()
    {
        return 'user';
    }

    protected function get_meta_subtype()
    {
        return 'user';
    }

    public function get_rest_field_type()
    {
        return 'user';
    }
}
