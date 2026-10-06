<?php

use Minn\Widgets\WidgetForms;
/** The recent posts widget: the newest published posts, with their dates when asked. */
class WP_Widget_Recent_Posts extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('recent-posts', 'Recent Posts', ['classname' => 'widget_recent_entries', 'description' => 'Your site&#8217;s most recent Posts.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Recent Posts' : $instance['title'], $instance, $this->id_base);
        $number = empty($instance['number']) ? 5 : absint($instance['number']);
        $number = $number > 0 ? $number : 5;
        $showDate = !empty($instance['show_date']);
        $query = new WP_Query(apply_filters('widget_posts_args', ['posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true], $instance));
        if (!$query->have_posts()) {
            return;
        }
        echo "\n\t\t" . $args['before_widget'] . "\n\t\t";
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo "\n\t\t<ul>\n";
        foreach ($query->posts as $post) {
            $current = get_queried_object_id() === $post->ID ? ' aria-current="page"' : '';
            $postTitle = get_the_title($post) ?: $post->ID;
            echo "\t\t\t\t\t\t\t\t\t\t\t<li>\n\t\t\t\t\t<a href=\"" . get_permalink($post) . "\"{$current}>{$postTitle}</a>\n";
            if ($showDate) {
                echo "\t\t\t\t\t\t\t\t\t\t\t<span class=\"post-date\">" . get_the_date('', $post) . "</span>\n";
            }
            echo "\t\t\t\t\t\t\t\t\t</li>\n";
        }
        echo "\t\t\t\t\t</ul>\n\n\t\t" . $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['number'] = (int) ($new_instance['number'] ?? 5);
        $instance['show_date'] = isset($new_instance['show_date']) ? (bool) $new_instance['show_date'] : false;
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::recentPosts). */
    public function form($instance)
    {
        echo WidgetForms::recentPosts($this, (array) $instance);
    }
}
