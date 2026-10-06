<?php

use Minn\Widgets\WidgetForms;
/** The archives widget: the months with posts as a list, or a dropdown that navigates on change. */
class WP_Widget_Archives extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('archives', 'Archives', ['classname' => 'widget_archive', 'description' => 'A monthly archive of your site&#8217;s Posts.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Archives' : $instance['title'], $instance, $this->id_base);
        $count = !empty($instance['count']);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        if (!empty($instance['dropdown'])) {
            $this->dropdown($instance, $title, $count);
        } else {
            echo "\n\t\t\t<ul>\n\t\t\t\t";
            wp_get_archives(apply_filters('widget_archives_args', ['type' => 'monthly', 'show_post_count' => $count], $instance));
            echo "\t\t\t</ul>\n\n\t\t\t";
        }
        echo $args['after_widget'];
    }

    private function dropdown(array $instance, string $title, bool $count): void
    {
        $id = "{$this->id_base}-dropdown-{$this->number}";
        $dropdownArgs = apply_filters('widget_archives_dropdown_args', ['type' => 'monthly', 'format' => 'option', 'show_post_count' => $count], $instance);
        $dropdownArgs['echo'] = 0;
        $label = match ($dropdownArgs['type']) {
            'yearly' => 'Select Year',
            'daily' => 'Select Day',
            'weekly' => 'Select Week',
            'monthly' => 'Select Month',
            default => 'Select Post',
        };
        echo "\t\t<label class=\"screen-reader-text\" for=\"{$id}\">{$title}</label>\n\t\t<select id=\"{$id}\" name=\"archive-dropdown\">\n\t\t\t\n\t\t\t<option value=\"\">{$label}</option>\n\t\t\t"
            . wp_get_archives($dropdownArgs) . "\n\t\t</select>\n\n\t\t\t";
        wp_print_inline_script_tag(_minn_dropdown_script($id, "if ( dropdown.value ) {\n\t\t\t\tdocument.location.href = dropdown.value;\n\t\t\t}") . _minn_source_url(rawurlencode('WP_Widget_Archives::widget')));
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['count'] = !empty($new_instance['count']) ? 1 : 0;
        $instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::archives). */
    public function form($instance)
    {
        echo WidgetForms::archives($this, (array) $instance);
    }
}
