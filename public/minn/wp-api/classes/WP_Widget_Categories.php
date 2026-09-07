<?php
/** The categories widget: the category list, with counts and hierarchy, or a dropdown that submits on change. */
class WP_Widget_Categories extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('categories', 'Categories', ['classname' => 'widget_categories', 'description' => 'A list or dropdown of categories.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        static $first = true;
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Categories' : $instance['title'], $instance, $this->id_base);
        $count = !empty($instance['count']);
        $hierarchical = !empty($instance['hierarchical']);
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        if (!empty($instance['dropdown'])) {
            $id = $first ? 'cat' : "{$this->id_base}-dropdown-{$this->number}";
            $first = false;
            echo '<form action="' . esc_url(home_url()) . '" method="get"><label class="screen-reader-text" for="' . esc_attr($id) . '">' . $title . '</label>';
            echo wp_dropdown_categories(apply_filters('widget_categories_dropdown_args', ['show_option_none' => 'Select Category', 'show_count' => $count, 'orderby' => 'name', 'hierarchical' => $hierarchical, 'id' => $id, 'echo' => 0], $instance));
            echo '</form>';
            wp_print_inline_script_tag(_minn_dropdown_script($id, "if ( dropdown.value && parseInt( dropdown.value ) > 0 && dropdown instanceof HTMLSelectElement ) {\n\t\t\t\tdropdown.parentElement.submit();\n\t\t\t}") . _minn_source_url(rawurlencode('WP_Widget_Categories::widget')));
        } else {
            $list = wp_list_categories(apply_filters('widget_categories_args', ['orderby' => 'name', 'show_count' => $count, 'hierarchical' => $hierarchical, 'title_li' => '', 'echo' => 0], $instance));
            echo "\n\t\t\t<ul>\n\t\t\t\t" . $list . "\t\t\t</ul>\n\n\t\t\t";
        }
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['count'] = !empty($new_instance['count']) ? 1 : 0;
        $instance['hierarchical'] = !empty($new_instance['hierarchical']) ? 1 : 0;
        $instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;
        return $instance;
    }
}
