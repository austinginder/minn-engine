<?php

use Minn\Widgets\WidgetForms;
/** The search widget: the site's search form under an optional title. */
class WP_Widget_Search extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('search', 'Search', ['classname' => 'widget_search', 'description' => 'A search form for your site.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        get_search_form();
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::titleOnly). */
    public function form($instance)
    {
        echo WidgetForms::titleOnly($this, (array) $instance);
    }
}
