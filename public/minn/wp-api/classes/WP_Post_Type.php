<?php

use Minn\Runtime\Registry;
use Minn\Runtime\Runtime;
use Minn\Runtime\TypeProps;

/**
 * A post type as an object (probes type-object-sharing,
 * type-object-methods). Built with its arguments, it fills in the
 * reference's defaults (Runtime\TypeProps::postType) after
 * register_post_type_args and register_{$name}_post_type_args, keeping
 * given values as given; its labels go through get_post_type_labels and
 * its label is their name. register_post_type then has it do each step
 * of a registration, and unregister_post_type undo them: its supports
 * (title and editor when it names none, nothing for false, autosave
 * wherever there is an editor), its rewrite tag, permastruct, archive
 * rules and query var, its meta box callback, the hook that publishes
 * its scheduled posts, and its taxonomies. Its REST controllers are made
 * once, when it is shown in REST and the class exists.
 */
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
    public $supports;
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

    /** @internal the default labels, kept until reset_default_labels */
    private static $minn_default_labels = [];

    public function __construct($post_type, $args = [])
    {
        $this->name = $post_type;
        $this->set_props($args);
    }

    public function set_props($args)
    {
        $args = apply_filters('register_post_type_args', wp_parse_args($args), $this->name);
        $args = apply_filters("register_{$this->name}_post_type_args", $args, $this->name);
        foreach (TypeProps::postType((string) $this->name, (array) $args) as $key => $value) {
            $this->{$key} = $key === 'cap' ? (object) $value : $value;
        }
        $this->labels = get_post_type_labels($this);
        $this->label = $this->labels->name;
    }

    public function add_supports()
    {
        if (!empty($this->supports)) {
            foreach ((array) $this->supports as $feature => $args) {
                is_array($args) ? add_post_type_support($this->name, $feature, $args) : add_post_type_support($this->name, $args);
            }
            unset($this->supports);
        } elseif ($this->supports !== false) {
            add_post_type_support($this->name, ['title', 'editor']);
        }
        if (post_type_supports($this->name, 'editor')) {
            add_post_type_support($this->name, 'autosave');
        }
    }

    public function add_rewrite_rules()
    {
        $rewrite = _minn_rewrite();
        $var = $this->query_var;
        if (is_string($var) && $var !== '' && !empty($this->publicly_queryable)) {
            $GLOBALS['wp']->add_query_var($var);
        }
        if (!is_array($this->rewrite) || !Registry::settlesRewrites()) {
            return;
        }
        foreach (Minn\Runtime\RewriteRules::typeArchive($rewrite, (string) $this->name, get_object_vars($this)) as $regex => $query) {
            add_rewrite_rule($regex, $query, 'top');
        }
        add_rewrite_tag("%{$this->name}%", $this->hierarchical ? '(.+?)' : '([^/]+)', is_string($var) && $var !== '' ? "{$var}=" : "post_type={$this->name}&" . ($this->hierarchical ? 'pagename=' : 'name='));
        add_permastruct($this->name, "{$this->rewrite['slug']}/%{$this->name}%", ['with_front' => $this->rewrite['with_front'], 'ep_mask' => $this->rewrite['ep_mask'], 'feed' => $this->rewrite['feeds']]);
    }

    public function register_meta_boxes()
    {
        if ($this->register_meta_box_cb) {
            add_action("add_meta_boxes_{$this->name}", $this->register_meta_box_cb, 10, 1);
        }
    }

    public function add_hooks()
    {
        add_action("future_{$this->name}", '_future_post_hook', 5, 2);
    }

    public function register_taxonomies()
    {
        foreach ((array) $this->taxonomies as $taxonomy) {
            register_taxonomy_for_object_type($taxonomy, $this->name);
        }
    }

    public function remove_supports()
    {
        Runtime::registry()->removeSupports((string) $this->name);
    }

    public function remove_rewrite_rules()
    {
        $rewrite = _minn_rewrite();
        $var = $this->query_var;
        if (is_string($var) && $var !== '' && !empty($this->publicly_queryable)) {
            $GLOBALS['wp']->remove_query_var($var);
        }
        if (!is_array($this->rewrite) || !Registry::settlesRewrites()) {
            return;
        }
        remove_rewrite_tag("%{$this->name}%");
        remove_permastruct($this->name);
        foreach (Minn\Runtime\RewriteRules::typeArchive($rewrite, (string) $this->name, get_object_vars($this)) as $regex => $query) {
            unset($rewrite->extra_rules_top[$regex]);
        }
    }

    public function unregister_meta_boxes()
    {
        if ($this->register_meta_box_cb) {
            remove_action("add_meta_boxes_{$this->name}", $this->register_meta_box_cb, 10);
        }
    }

    public function unregister_taxonomies()
    {
        foreach (get_object_taxonomies($this->name) as $taxonomy) {
            unregister_taxonomy_for_object_type($taxonomy, $this->name);
        }
    }

    public function remove_hooks()
    {
        remove_action("future_{$this->name}", '_future_post_hook', 5);
    }

    public function get_rest_controller()
    {
        if (!$this->show_in_rest) {
            return null;
        }
        return $this->rest_controller ??= $this->minn_controller($this->rest_controller_class ?: WP_REST_Posts_Controller::class);
    }

    public function get_revisions_rest_controller()
    {
        if (!$this->show_in_rest || !post_type_supports($this->name, 'revisions')) {
            return null;
        }
        return $this->revisions_rest_controller ??= $this->minn_controller($this->revisions_rest_controller_class ?: WP_REST_Revisions_Controller::class);
    }

    public function get_autosave_rest_controller()
    {
        if (!$this->show_in_rest || !post_type_supports($this->name, 'autosave')) {
            return null;
        }
        return $this->autosave_rest_controller ??= $this->minn_controller($this->autosave_rest_controller_class ?: WP_REST_Autosaves_Controller::class);
    }

    public static function get_default_labels()
    {
        return self::$minn_default_labels ?: self::$minn_default_labels = Runtime::registry()->defaultLabels('post', 'page');
    }

    public static function reset_default_labels()
    {
        self::$minn_default_labels = [];
    }

    /** @internal one of the type's REST controllers, or null when its class is missing or not a REST controller */
    private function minn_controller($class)
    {
        return is_string($class) && class_exists($class) && is_subclass_of($class, WP_REST_Controller::class) ? new $class($this->name) : null;
    }
}
