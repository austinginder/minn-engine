<?php

use Minn\Widgets\WidgetForms;

/**
 * The custom HTML widget of the reference's classic widget set. It exists so
 * a plugin that references it (Polylang copies its instance when it
 * translates a sidebar) loads; on a classic theme the widget prints its
 * stored markup between the sidebar's wrappers, and there is no form.
 */
class WP_Widget_Custom_HTML extends WP_Widget
{
    public $default_instance = ['title' => '', 'content' => ''];

    public function __construct()
    {
        parent::__construct(
            'custom_html',
            'Custom HTML',
            ['classname' => 'widget_custom_html', 'description' => 'Arbitrary HTML code.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true],
            ['width' => 400, 'height' => 350]
        );
    }

    public function widget($args, $instance)
    {
        $instance = wp_parse_args($instance, $this->default_instance);
        $title = apply_filters('widget_title', (string) $instance['title'], $instance, $this->id_base);
        // The text widget's class rides along so themes style both alike.
        echo str_replace('widget_custom_html', 'widget_text widget_custom_html', (string) ($args['before_widget'] ?? ''));
        if ($title !== '') {
            echo ($args['before_title'] ?? '') . $title . ($args['after_title'] ?? '');
        }
        echo '<div class="textwidget custom-html-widget">' . apply_filters('widget_custom_html_content', (string) $instance['content'], $instance, $this) . '</div>';
        echo $args['after_widget'] ?? '';
    }

    /** The settings form (Widgets\WidgetForms::customHtml). */
    public function form($instance)
    {
        echo WidgetForms::customHtml($this, (array) $instance);
    }

    public function update($new_instance, $old_instance)
    {
        return ['title' => (string) ($new_instance['title'] ?? ''), 'content' => (string) ($new_instance['content'] ?? '')];
    }
}
