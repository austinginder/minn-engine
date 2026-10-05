<?php

/** The admin screen object, enough for plugins to read id/base/post_type and register options. */
#[AllowDynamicProperties]
final class WP_Screen
{
    public $action = '';
    public $base = '';
    public $id = '';
    public $post_type = '';
    public $taxonomy = '';
    public $parent_base = '';
    public $parent_file = '';
    public $is_network = false;
    public $is_user = false;
    public $is_block_editor = false;
    private $options = [];
    private $help_tabs = [];
    private static $registry = [];

    public static function get($hook_name = '')
    {
        if ($hook_name instanceof self) {
            return $hook_name;
        }
        $id = (string) ($hook_name === '' ? ($GLOBALS['hook_suffix'] ?? '') : $hook_name);
        $id = preg_replace('/\.php$/', '', $id);
        if (isset(self::$registry[$id])) {
            return self::$registry[$id];
        }
        $screen = new self();
        $screen->id = $id;
        $screen->base = $id;
        if (in_array($id, ['post', 'page'], true)) {
            $screen->base = 'post';
            $screen->post_type = $id;
        } elseif (str_starts_with($id, 'edit-')) {
            $screen->base = 'edit';
            $screen->post_type = substr($id, 5);
        }
        self::$registry[$id] = $screen;
        return $screen;
    }

    public function set_current_screen()
    {
        $GLOBALS['current_screen'] = $this;
        do_action('current_screen', $this);
    }

    public function in_admin($admin = null)
    {
        return $admin === null ? 'site' : $admin === 'site';
    }

    public function is_block_editor($set = null)
    {
        if ($set !== null) {
            $this->is_block_editor = (bool) $set;
        }
        return $this->is_block_editor;
    }

    public function add_option($option, $args = [])
    {
        $this->options[$option] = $args;
    }

    public function remove_option($option)
    {
        unset($this->options[$option]);
    }

    public function remove_options()
    {
        $this->options = [];
    }

    public function get_options()
    {
        return $this->options;
    }

    public function get_option($option, $key = false)
    {
        if (!isset($this->options[$option])) {
            return null;
        }
        return $key === false ? $this->options[$option] : ($this->options[$option][$key] ?? null);
    }

    public function add_help_tab($args)
    {
        if (!empty($args['id'])) {
            $this->help_tabs[$args['id']] = $args;
        }
    }

    public function remove_help_tab($id)
    {
        unset($this->help_tabs[$id]);
    }

    public function get_help_tabs()
    {
        return $this->help_tabs;
    }

    public function set_help_sidebar($content)
    {
    }

    public function render_screen_meta()
    {
    }

    public function get_columns()
    {
        return 1;
    }
}
