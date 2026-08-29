<?php

/** A registered post type as an object; the registry array is the source. */
#[AllowDynamicProperties]
final class WP_Post_Type
{
    public $name;
    public $label;
    public $labels;
    public $description = '';
    public $public = false;
    public $hierarchical = false;
    public $exclude_from_search = null;
    public $publicly_queryable = null;
    public $embeddable = null;
    public $show_ui = null;
    public $show_in_menu = null;
    public $show_in_nav_menus = null;
    public $show_in_admin_bar = null;
    public $menu_position = null;
    public $menu_icon = null;
    public $capability_type = 'post';
    public $map_meta_cap = false;
    public $register_meta_box_cb = null;
    public $taxonomies = [];
    public $has_archive = false;
    public $query_var;
    public $can_export = true;
    public $delete_with_user = null;
    public $template = [];
    public $template_lock = false;
    public $_builtin = false;
    public $_edit_link = 'post.php?post=%d';
    public $cap;
    public $rewrite;
    public $show_in_rest;
    public $rest_base;
    public $rest_namespace;
    public $rest_controller_class;
    public $rest_controller;
    public $revisions_rest_controller_class;
    public $revisions_rest_controller;
    public $autosave_rest_controller_class;
    public $autosave_rest_controller;
    public $late_route_registration;
    private $supportsList = [];

    public function __construct($post_type, $args = [])
    {
        if (is_array($args) && isset($args['name'])) {
            foreach ($args as $key => $value) {
                if ($key === 'supports') {
                    $this->supportsList = $value;
                    continue;
                }
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
        $this->name = (string) $post_type;
    }

    public function supports()
    {
        return $this->supportsList;
    }

    public function add_supports()
    {
    }

    public function get_rest_controller()
    {
        return null;
    }

    public function get_revisions_rest_controller()
    {
        return null;
    }

    public function get_autosave_rest_controller()
    {
        return null;
    }

    public static function get_default_labels()
    {
        return (object) (Minn\Runtime\Runtime::registry()->postType('post')['labels'] ?? []);
    }
}
