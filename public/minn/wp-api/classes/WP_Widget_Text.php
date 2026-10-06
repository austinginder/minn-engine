<?php

use Minn\Widgets\WidgetForms;
/**
 * The text widget: arbitrary text, run through the content pipeline for a
 * visual instance and through wpautop alone for a legacy one that asked
 * for it.
 */
class WP_Widget_Text extends WP_Widget
{
    protected $registered = false;

    public function __construct()
    {
        parent::__construct('text', 'Text', ['classname' => 'widget_text', 'description' => 'Arbitrary text.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true], ['width' => 400, 'height' => 350]);
    }

    public function _register_one($number = -1)
    {
        parent::_register_one($number);
        $this->registered = true;
    }

    public function is_legacy_instance($instance)
    {
        if (isset($instance['visual'])) {
            return !$instance['visual'];
        }
        return ($instance['filter'] ?? '') !== 'content';
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        $text = !empty($instance['text']) ? $instance['text'] : '';
        $visual = !$this->is_legacy_instance($instance);
        if ($visual) {
            $instance['filter'] = true;
            $instance['visual'] = true;
        }
        $text = apply_filters('widget_text', $text, $instance, $this);
        if ($visual) {
            $text = apply_filters('widget_text_content', $text, $instance, $this);
        } elseif (!empty($instance['filter'])) {
            $text = wpautop($text);
        }
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        $text = preg_replace_callback('#<(video|iframe|object|embed)\s[^>]*>#i', [$this, 'inject_video_max_width_style'], $text);
        echo "\t\t\t<div class=\"textwidget\">" . $text . "</div>\n\t\t";
        echo $args['after_widget'];
    }

    public function inject_video_max_width_style($matches)
    {
        $html = preg_replace('/\sheight="\d+"/', '', $matches[0]);
        $html = preg_replace('/\swidth="\d+"/', '', $html);
        return preg_replace('/(?<=width:)\s*\d+px(?=;?)/', '100%', $html);
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $text = (string) ($new_instance['text'] ?? '');
        $instance['text'] = current_user_can('unfiltered_html') ? $text : wp_kses_post($text);
        $instance['filter'] = !empty($new_instance['filter']);
        if (isset($new_instance['visual'])) {
            $instance['visual'] = !empty($new_instance['visual']);
        }
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::text). */
    public function form($instance)
    {
        echo WidgetForms::text($this, (array) $instance);
    }
}
