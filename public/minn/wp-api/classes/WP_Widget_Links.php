<?php

use Minn\Widgets\WidgetForms;

/**
 * The Links widget: the blogroll of one link category, or of every one that
 * has links, through wp_list_bookmarks with the sidebar's wrappers (probe
 * widget-methods). The widget set registers it only while the link manager
 * is on.
 */
class WP_Widget_Links extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('links', 'Links', ['description' => 'Your blogroll', 'customize_selective_refresh' => true]);
    }

    public function widget($args, $instance)
    {
        // The wrapper's id becomes the list's own per category; rating sorts the best first.
        $orderby = $instance['orderby'] ?? 'name';
        wp_list_bookmarks(apply_filters('widget_links_args', [
            'title_before' => $args['before_title'] ?? '',
            'title_after' => $args['after_title'] ?? '',
            'category_before' => preg_replace('/id="[^"]*"/', 'id="%id"', (string) ($args['before_widget'] ?? '')),
            'category_after' => $args['after_widget'] ?? '',
            'show_images' => $instance['images'] ?? true,
            'show_description' => $instance['description'] ?? false,
            'show_name' => $instance['name'] ?? false,
            'show_rating' => $instance['rating'] ?? false,
            'category' => $instance['category'] ?? '',
            'class' => 'linkcat widget',
            'orderby' => $orderby,
            'order' => $orderby === 'rating' ? 'DESC' : 'ASC',
            'limit' => $instance['limit'] ?? -1,
        ], $instance));
    }

    /** A new instance from the form alone: a box sent at all is on, an order it does not know is by name, no limit is -1. */
    public function update($new_instance, $old_instance)
    {
        $new_instance = (array) $new_instance;
        $instance = [];
        foreach (['images', 'name', 'description', 'rating'] as $field) {
            $instance[$field] = isset($new_instance[$field]) ? 1 : 0;
        }
        $instance['orderby'] = in_array($new_instance['orderby'] ?? '', ['name', 'rating', 'id', 'rand'], true) ? $new_instance['orderby'] : 'name';
        $instance['category'] = intval($new_instance['category'] ?? 0);
        $instance['limit'] = empty($new_instance['limit']) ? -1 : intval($new_instance['limit']);
        return $instance;
    }

    /** The settings form (Widgets\WidgetForms::links); a limit of 0 or -1 shows as empty. */
    public function form($instance)
    {
        $instance = wp_parse_args((array) $instance, ['images' => true, 'name' => true, 'description' => false, 'rating' => false, 'category' => false, 'orderby' => 'name', 'limit' => -1]);
        $limit = intval($instance['limit']);
        $categories = array_map(static fn ($term) => [$term->term_id, $term->name], get_terms(['taxonomy' => 'link_category']));
        echo WidgetForms::links($this, $instance, $categories, in_array($limit, [0, -1], true) ? '' : (string) $limit);
    }
}
