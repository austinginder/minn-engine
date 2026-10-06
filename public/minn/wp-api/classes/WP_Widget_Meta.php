<?php

use Minn\Widgets\WidgetForms;
/** The meta widget: register and sign-in links, the two feeds, and the powered-by line. */
class WP_Widget_Meta extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('meta', 'Meta', ['classname' => 'widget_meta', 'description' => 'Login, RSS, &amp; WordPress.org links.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Meta' : $instance['title'], $instance, $this->id_base);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo "\n\t\t<ul>\n\t\t\t" . wp_register('<li>', '</li>', false)
            . "\t\t\t<li>" . wp_loginout('', false) . "</li>\n"
            . "\t\t\t<li><a href=\"" . esc_url(get_bloginfo('rss2_url')) . "\">Entries feed</a></li>\n"
            . "\t\t\t<li><a href=\"" . esc_url(get_bloginfo('comments_rss2_url')) . "\">Comments feed</a></li>\n\n\t\t\t"
            . apply_filters('widget_meta_poweredby', '<li><a href="https://wordpress.org/">WordPress.org</a></li>' . "\n", $instance)
            . "\t\t</ul>\n\n\t\t";
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
