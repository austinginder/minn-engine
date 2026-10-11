<?php

use Minn\Widgets\WidgetForms;
use Minn\Widgets\WidgetTemplates;

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
        // The text widget's class goes first in the wrapper's class, so themes style both alike.
        echo preg_replace('/(?<=\sclass=["\'])/', 'widget_text ', (string) ($args['before_widget'] ?? ''));
        if ($title !== '') {
            echo ($args['before_title'] ?? '') . $title . ($args['after_title'] ?? '');
        }
        // The text widget's filter first, then the Custom HTML widget's own.
        $content = apply_filters('widget_text', (string) $instance['content'], $instance, $this);
        echo '<div class="textwidget custom-html-widget">' . apply_filters('widget_custom_html_content', $content, $instance, $this) . '</div>';
        echo $args['after_widget'] ?? '';
    }

    /** As the text widget's: a gallery here belongs to no post (probe widget-methods). */
    public function _filter_gallery_shortcode_attrs($attrs)
    {
        $attrs['id'] ??= -1;
        return $attrs;
    }

    public function enqueue_admin_scripts()
    {
        \Minn\Runtime\PlaceholderTrace::deadEnd('WP_Widget_Custom_HTML::enqueue_admin_scripts');
    }

    /** The control template; the note on refused tags is for a user who may not post unfiltered HTML. */
    public static function render_control_template_scripts()
    {
        echo current_user_can('unfiltered_html') ? WidgetTemplates::customHtml() : WidgetTemplates::customHtmlRestricted();
    }

    public static function add_help_text()
    {
        \Minn\Runtime\PlaceholderTrace::deadEnd('WP_Widget_Custom_HTML::add_help_text');
    }

    /** The settings form (Widgets\WidgetForms::customHtml). */
    public function form($instance)
    {
        echo WidgetForms::customHtml($this, (array) $instance);
    }

    /** The old settings with a sanitized title and the content, kses'd for a user who may not post unfiltered HTML (probe widget-factory). */
    public function update($new_instance, $old_instance)
    {
        $instance = (array) $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $content = (string) ($new_instance['content'] ?? '');
        $instance['content'] = current_user_can('unfiltered_html') ? $content : wp_kses_post($content);
        return $instance;
    }
}
