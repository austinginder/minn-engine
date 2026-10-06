<?php

use Minn\Widgets\WidgetForms;
/** The tag cloud widget: a taxonomy's terms sized by use, titled after the taxonomy. */
class WP_Widget_Tag_Cloud extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('tag_cloud', 'Tag Cloud', ['classname' => 'widget_tag_cloud', 'description' => 'A cloud of your most used tags.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $taxonomy = $this->_get_current_taxonomy($instance);
        if (!empty($instance['title'])) {
            $title = $instance['title'];
        } else {
            $title = $taxonomy === 'post_tag' ? 'Tags' : (get_taxonomy($taxonomy)->labels->name ?? '');
        }
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        $cloud = wp_tag_cloud(apply_filters('widget_tag_cloud_args', ['taxonomy' => $taxonomy, 'echo' => false, 'show_count' => !empty($instance['count'])], $instance));
        if (empty($cloud)) {
            return;
        }
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo '<div class="tagcloud">' . $cloud . "</div>\n";
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['count'] = !empty($new_instance['count']) ? 1 : 0;
        $instance['taxonomy'] = stripslashes((string) ($new_instance['taxonomy'] ?? 'post_tag'));
        return $instance;
    }

    public function _get_current_taxonomy($instance)
    {
        return !empty($instance['taxonomy']) && taxonomy_exists($instance['taxonomy']) ? $instance['taxonomy'] : 'post_tag';
    }

    /** The settings form (Widgets\WidgetForms::tagCloud). */
    public function form($instance)
    {
        echo WidgetForms::tagCloud($this, (array) $instance);
    }
}
