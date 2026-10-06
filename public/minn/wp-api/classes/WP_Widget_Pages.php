<?php

use Minn\Widgets\WidgetForms;
/** The pages widget: the site's page tree as a list, sorted as asked, with exclusions. */
class WP_Widget_Pages extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('pages', 'Pages', ['classname' => 'widget_pages', 'description' => 'A list of your site&#8217;s Pages.', 'customize_selective_refresh' => true, 'show_instance_in_rest' => true]);
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', empty($instance['title']) ? 'Pages' : $instance['title'], $instance, $this->id_base);
        $sortby = empty($instance['sortby']) ? 'menu_order' : $instance['sortby'];
        if ($sortby === 'menu_order') {
            $sortby = 'menu_order, post_title';
        }
        $out = wp_list_pages(apply_filters('widget_pages_args', ['title_li' => '', 'echo' => 0, 'sort_column' => $sortby, 'exclude' => empty($instance['exclude']) ? '' : $instance['exclude']], $instance));
        if (empty($out)) {
            return;
        }
        echo $args['before_widget'];
        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo "\n\t\t\t<ul>\n\t\t\t\t" . $out . "\t\t\t</ul>\n\n\t\t\t";
        echo $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        $instance = $old_instance;
        $instance['title'] = sanitize_text_field((string) ($new_instance['title'] ?? ''));
        $instance['sortby'] = in_array($new_instance['sortby'] ?? '', ['post_title', 'menu_order', 'ID'], true) ? $new_instance['sortby'] : 'menu_order';
        $instance['exclude'] = sanitize_text_field((string) ($new_instance['exclude'] ?? ''));
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::pages). */
    public function form($instance)
    {
        echo WidgetForms::pages($this, (array) $instance);
    }
}
