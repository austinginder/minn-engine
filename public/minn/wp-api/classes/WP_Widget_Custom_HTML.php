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

    /** The stored markup through widget_text (as a text instance) and widget_custom_html_content, the text widget's class first in the wrapper (Widgets\TextWidgets::customHtml). */
    public function widget($args, $instance)
    {
        echo Minn\Widgets\TextWidgets::customHtml($this, (array) $args, (array) $instance);
    }

    /** As the text widget's: a gallery here belongs to no post off a singular view (probe widget-methods). */
    public function _filter_gallery_shortcode_attrs($attrs)
    {
        return Minn\Widgets\TextWidgets::galleryAttributes((array) $attrs);
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
