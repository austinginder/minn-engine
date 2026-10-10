<?php

use Minn\Runtime\Registry;
use Minn\Runtime\Runtime;
use Minn\Runtime\TypeProps;

/**
 * A taxonomy as an object (probes type-object-sharing,
 * type-object-methods). Built with the types it belongs to and its
 * arguments, it fills in the reference's defaults
 * (Runtime\TypeProps::taxonomy) after register_taxonomy_args and
 * register_{$name}_taxonomy_args, keeping given values as given; its
 * labels go through get_taxonomy_labels and its label is their name.
 * register_taxonomy then has it add its rewrite tag, permastruct and query
 * var and its hooks (the admin's add-term answer, for any taxonomy), and
 * unregister_taxonomy remove them. Its REST controller is made once,
 * when it is shown in REST and the class exists.
 */
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
    public $object_type = null;
    public $cap;
    public $rewrite;
    public $query_var;
    public $update_count_callback;
    public $show_in_rest;
    public $rest_base;
    public $rest_namespace;
    public $rest_controller_class;
    public $rest_controller;
    public $default_term;
    public $sort = null;
    public $args = null;
    public $_builtin;

    /** @internal the default labels, kept until reset_default_labels */
    private static $minn_default_labels = [];

    public function __construct($taxonomy, $object_type, $args = [])
    {
        $this->name = $taxonomy;
        $this->set_props($object_type, $args);
    }

    public function set_props($object_type, $args)
    {
        $args = apply_filters('register_taxonomy_args', wp_parse_args($args), $this->name, (array) $object_type);
        $args = apply_filters("register_{$this->name}_taxonomy_args", $args, $this->name, (array) $object_type);
        foreach (TypeProps::taxonomy((string) $this->name, (array) $object_type, (array) $args) as $key => $value) {
            $this->{$key} = $key === 'cap' ? (object) $value : $value;
        }
        $this->labels = get_taxonomy_labels($this);
        $this->label = $this->labels->name;
    }

    public function add_rewrite_rules()
    {
        _minn_rewrite();
        $var = $this->query_var;
        if (is_string($var) && $var !== '') {
            $GLOBALS['wp']->add_query_var($var);
        }
        if (!is_array($this->rewrite) || !Registry::settlesRewrites()) {
            return;
        }
        add_rewrite_tag("%{$this->name}%", empty($this->rewrite['hierarchical']) ? '([^/]+)' : '(.+?)', is_string($var) && $var !== '' ? "{$var}=" : "taxonomy={$this->name}&term=");
        add_permastruct($this->name, "{$this->rewrite['slug']}/%{$this->name}%", ['with_front' => $this->rewrite['with_front'], 'ep_mask' => $this->rewrite['ep_mask']]);
    }

    public function remove_rewrite_rules()
    {
        _minn_rewrite();
        $var = $this->query_var;
        if (is_string($var) && $var !== '') {
            $GLOBALS['wp']->remove_query_var($var);
        }
        if (!is_array($this->rewrite) || !Registry::settlesRewrites()) {
            return;
        }
        remove_rewrite_tag("%{$this->name}%");
        remove_permastruct($this->name);
    }

    public function add_hooks()
    {
        add_filter("wp_ajax_add-{$this->name}", '_wp_ajax_add_hierarchical_term');
    }

    public function remove_hooks()
    {
        remove_filter("wp_ajax_add-{$this->name}", '_wp_ajax_add_hierarchical_term');
    }

    public function get_rest_controller()
    {
        if (!$this->show_in_rest) {
            return null;
        }
        $class = $this->rest_controller_class ?: WP_REST_Terms_Controller::class;
        if (!class_exists($class) || !is_subclass_of($class, WP_REST_Controller::class)) {
            return null;
        }
        return $this->rest_controller ??= new $class($this->name);
    }

    public static function get_default_labels()
    {
        return self::$minn_default_labels ?: self::$minn_default_labels = Runtime::registry()->defaultLabels('post_tag', 'category');
    }

    public static function reset_default_labels()
    {
        self::$minn_default_labels = [];
    }
}
