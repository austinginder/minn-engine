<?php

use Minn\Widgets\WidgetForms;
/**
 * The navigation menu widget: one of the site's menus, drawn by wp_nav_menu.
 * The file is named after the parent so it loads once WP_Widget exists.
 */
class WP_Nav_Menu_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('nav_menu', 'Navigation Menu', ['classname' => 'widget_nav_menu', 'description' => 'Add a navigation menu to your sidebar.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $menu = !empty($instance['nav_menu']) ? wp_get_nav_menu_object($instance['nav_menu']) : false;
        if (!$menu) {
            return;
        }
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        wp_nav_menu(apply_filters('widget_nav_menu_args', ['fallback_cb' => '', 'menu' => $menu], $menu, $args, $instance));
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = [];
        if (!empty($new_instance['title'])) {
            $instance['title'] = sanitize_text_field((string) $new_instance['title']);
        }
        if (!empty($new_instance['nav_menu'])) {
            $instance['nav_menu'] = (int) $new_instance['nav_menu'];
        }
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::navMenu). */
    public function form($instance)
    {
        echo WidgetForms::navMenu($this, (array) $instance);
    }
}
