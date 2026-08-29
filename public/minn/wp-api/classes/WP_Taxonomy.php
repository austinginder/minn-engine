<?php

/** A registered taxonomy as an object; the registry array is the source. */
#[AllowDynamicProperties]
final class WP_Taxonomy
{
    public $name;
    public $label;
    public $labels;
    public $description = '';
    public $public = true;
    public $publicly_queryable = true;
    public $hierarchical = false;
    public $show_ui = true;
    public $show_in_menu = true;
    public $show_in_nav_menus = true;
    public $show_tagcloud = true;
    public $show_in_quick_edit = true;
    public $show_admin_column = false;
    public $meta_box_cb = null;
    public $meta_box_sanitize_cb = null;
    public $object_type = [];
    public $cap;
    public $rewrite;
    public $query_var;
    public $update_count_callback = '';
    public $show_in_rest;
    public $rest_base;
    public $rest_namespace;
    public $rest_controller_class;
    public $rest_controller;
    public $default_term;
    public $sort = null;
    public $args = null;
    public $_builtin = false;

    public function __construct($taxonomy, $object_type = [], $args = [])
    {
        if (is_array($args) && isset($args['name'])) {
            foreach ($args as $key => $value) {
                if ($key === 'labels' || $key === 'cap') {
                    $this->{$key} = (object) $value;
                    continue;
                }
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
            return;
        }
        $this->name = (string) $taxonomy;
        $this->object_type = (array) $object_type;
    }

    public function get_rest_controller()
    {
        return null;
    }

    public static function get_default_labels()
    {
        return (object) (Minn\Runtime\Runtime::registry()->taxonomy('post_tag')['labels'] ?? []);
    }
}
