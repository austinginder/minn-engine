<?php

use Minn\Widgets\TextWidgets;
use Minn\Widgets\WidgetForms;
use Minn\Widgets\WidgetTemplates;
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

    /** Whether an instance is a legacy one the visual editor would change (Widgets\TextWidgets::isLegacy). */
    public function is_legacy_instance($instance)
    {
        return TextWidgets::isLegacy($instance);
    }

    /** The widget: a visual instance through the content filters, any other through wpautop when it asks and then its shortcodes (Widgets\TextWidgets::text). */
    public function widget($args, $instance)
    {
        echo TextWidgets::text($this, (array) $args, (array) $instance);
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
        return TextWidgets::settled((array) $instance, (array) $new_instance);
    }

    /** A gallery in the widget belongs to no post off a singular view: its id is -1 unless the shortcode named an id or images (probe widget-methods). */
    public function _filter_gallery_shortcode_attrs($attrs)
    {
        return TextWidgets::galleryAttributes((array) $attrs);
    }

    /** What the widget's preview in the Customizer needs for a playlist, its templates included. */
    public function enqueue_preview_scripts()
    {
        wp_playlist_scripts('audio');
    }

    public function enqueue_admin_scripts()
    {
        \Minn\Runtime\PlaceholderTrace::deadEnd('WP_Widget_Text::enqueue_admin_scripts');
    }

    public static function render_control_template_scripts()
    {
        echo WidgetTemplates::text();
    }

    /** The settings form (Widgets\WidgetForms::text). */
    public function form($instance)
    {
        // The text reaches its field through the_editor_content, as the HTML editor's, once the form's
        // first indent is out (probe hook-inputs-2).
        echo "\t\t\t\t\t";
        $instance = (array) $instance;
        $instance['text'] = apply_filters('the_editor_content', (string) ($instance['text'] ?? ''), 'html');
        echo WidgetForms::text($this, $instance);
    }
}
