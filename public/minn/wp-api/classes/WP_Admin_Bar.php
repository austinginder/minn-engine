<?php

use Minn\Front\ToolbarMarkup;
use Minn\Front\ToolbarMenus;
use Minn\Front\ToolbarTree;

/**
 * The toolbar plugins add to, as the reference keeps it (probe admin-bar):
 * nodes added (merged into one already there, an id taken from the title
 * when none is given, the old parent names moved on), grouped, read back
 * as copies, and removed; bound into a tree once, when rendered, after
 * which it holds no nodes to read. The protected steps stay methods, so a
 * subclass named through wp_admin_bar_class can still override them.
 */
class WP_Admin_Bar
{
    private $nodes = [];
    private $bound = false;
    public $user = null;
    public $menu = [];

    /** Who the bar is for, the head printers a page bumps itself with, the bar's assets, then admin_bar_init. */
    public function initialize()
    {
        $this->user = new stdClass();
        if (is_user_logged_in()) {
            $this->user->blogs = get_blogs_of_user(get_current_user_id());
            $this->user->active_blog = $this->user->blogs[get_current_blog_id()] ?? null;
            $this->user->domain = trailingslashit(home_url());
            $this->user->account_domain = $this->user->domain;
        }
        add_action('wp_head', 'wp_admin_bar_header');
        add_action('admin_head', 'wp_admin_bar_header');
        $support = current_theme_supports('admin-bar') ? get_theme_support('admin-bar') : null;
        $bump = is_array($support) ? ($support[0]['callback'] ?? null) : null;
        add_action('wp_head', $bump ?: '_admin_bar_bump_cb');
        wp_enqueue_script('admin-bar');
        wp_enqueue_style('admin-bar');
        do_action('admin_bar_init');
    }

    public function add_menu($node)
    {
        $this->add_node($node);
    }

    public function remove_menu($id)
    {
        $this->remove_node($id);
    }

    public function add_node($args)
    {
        // The old call shape: add_node($parent_id, $unused, $args).
        if (func_num_args() >= 3 && is_string($args)) {
            $args = array_merge(['parent' => $args], (array) func_get_arg(2));
        }
        $args = is_object($args) ? get_object_vars($args) : (array) $args;
        if (empty($args['id'])) {
            if (empty($args['title'])) {
                return;
            }
            _doing_it_wrong(__METHOD__, __('The menu ID should not be empty.'), '3.3.0');
            $args['id'] = esc_attr(sanitize_title(trim($args['title'])));
        }
        $existing = $this->get_node($args['id']);
        $defaults = $existing ? get_object_vars($existing) : ['id' => false, 'title' => false, 'parent' => false, 'href' => false, 'group' => false, 'meta' => []];
        if (!empty($defaults['meta']) && !empty($args['meta'])) {
            $args['meta'] = wp_parse_args($args['meta'], $defaults['meta']);
        }
        $args = wp_parse_args($args, $defaults);
        $renamed = ['my-account-with-avatar' => 'my-account', 'my-blogs' => 'my-sites'];
        if (is_string($args['parent']) && isset($renamed[$args['parent']])) {
            _deprecated_argument(__METHOD__, '3.3', sprintf('Use <code>%s</code> as the parent for the <code>%s</code> admin bar node instead of <code>%s</code>.', $renamed[$args['parent']], $args['id'], $args['parent']));
            $args['parent'] = $renamed[$args['parent']];
        }
        $this->_set_node($args);
    }

    protected function _set_node($args)
    {
        $this->nodes[$args['id']] = (object) $args;
    }

    public function get_node($id)
    {
        $node = $this->_get_node($id);
        return $node ? clone $node : null;
    }

    protected function _get_node($id)
    {
        if ($this->bound) {
            return null;
        }
        return $this->nodes[$id ?: 'root'] ?? null;
    }

    public function get_nodes()
    {
        $nodes = $this->_get_nodes();
        if (!$nodes) {
            return null;
        }
        return array_map(static fn ($node) => clone $node, $nodes);
    }

    protected function _get_nodes()
    {
        return $this->bound ? null : $this->nodes;
    }

    public function add_group($args)
    {
        $args['group'] = true;
        $this->add_node($args);
    }

    public function remove_node($id)
    {
        $this->_unset_node($id);
    }

    protected function _unset_node($id)
    {
        unset($this->nodes[$id]);
    }

    public function render()
    {
        $root = $this->_bind();
        if ($root) {
            $this->_render($root);
        }
    }

    /** The nodes as the tree they print as, under a root, once; afterwards the bar holds no nodes to read. */
    protected function _bind()
    {
        if ($this->bound) {
            return null;
        }
        $this->remove_node('root');
        $this->add_node(['id' => 'root', 'group' => false]);
        $root = ToolbarTree::bind((array) $this->_get_nodes(), fn ($id) => $this->_get_node($id), fn ($args) => $this->_set_node($args));
        $this->bound = true;
        return $root;
    }

    protected function _render($root)
    {
        echo ToolbarMarkup::open();
        foreach ($root->children as $group) {
            $this->_render_group($group);
        }
        echo ToolbarMarkup::close();
    }

    protected function _render_container($node)
    {
        if ($node->type !== 'container' || empty($node->children)) {
            return;
        }
        echo '<div id="' . esc_attr('wp-admin-bar-' . $node->id) . '" class="ab-group-container">';
        foreach ($node->children as $group) {
            $this->_render_group($group);
        }
        echo '</div>';
    }

    protected function _render_group($node, $menu_title = false)
    {
        if ($node->type === 'container') {
            $this->_render_container($node);
            return;
        }
        if ($node->type !== 'group' || empty($node->children)) {
            return;
        }
        echo ToolbarMarkup::groupOpen($node, $menu_title);
        foreach ($node->children as $item) {
            $this->_render_item($item);
        }
        echo '</ul>';
    }

    protected function _render_item($node)
    {
        if ($node->type !== 'item') {
            return;
        }
        echo ToolbarMarkup::itemOpen($node);
        if (!empty($node->children)) {
            echo '<div class="ab-sub-wrapper">';
            foreach ($node->children as $group) {
                $this->_render_group($group, empty($node->meta['menu_title']) ? false : $node->meta['menu_title']);
            }
            echo '</div>';
        }
        echo ToolbarMarkup::itemClose($node);
    }

    public function recursive_render($id, $node)
    {
        _deprecated_function(__METHOD__, '3.3.0', 'WP_Admin_bar::render(), WP_Admin_Bar::_render_item()');
        $this->_render_item($node);
    }

    /** WordPress's own menus, each on admin_bar_menu at its place, then add_admin_bar_menus. */
    public function add_menus()
    {
        foreach (ToolbarMenus::MENUS as [$callback, $priority]) {
            add_action('admin_bar_menu', $callback, $priority);
        }
        do_action('add_admin_bar_menus');
    }
}
