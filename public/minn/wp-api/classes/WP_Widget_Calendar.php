<?php
/**
 * The calendar widget: a month of the site's posts, drawn by get_calendar()
 * inside a wrap whose first instance on a page carries the calendar_wrap id.
 * Registered as the reference registers it, so a plugin that extends it
 * (Polylang swaps in its own) finds the class and its options in place.
 */
class WP_Widget_Calendar extends WP_Widget
{
    private static $instance = 0;

    public function __construct()
    {
        parent::__construct(
            'calendar',
            'Calendar',
            ['classname' => 'widget_calendar', 'customize_selective_refresh' => true, 'description' => 'A calendar of your site’s posts.', 'show_instance_in_rest' => true]
        );
    }

    public function widget($args, $instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo self::$instance === 0 ? '<div id="calendar_wrap" class="calendar_wrap">' : '<div class="calendar_wrap">';
        get_calendar();
        echo '</div>';
        echo $args['after_widget'];
        self::$instance++;
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        return $instance;
    }

    public function form($instance)
    {
        $instance = wp_parse_args((array) $instance, ['title' => '']);
        $id = $this->get_field_id('title');
        echo "\t\t<p>\n\t\t\t<label for=\"{$id}\">Title:</label>\n\t\t\t<input class=\"widefat\" id=\"{$id}\" name=\"" . $this->get_field_name('title') . "\" type=\"text\" value=\"" . esc_attr((string) $instance['title']) . "\" />\n\t\t</p>\n\t\t";
    }
}
