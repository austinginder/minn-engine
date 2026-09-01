<?php

class Walker_Page extends Walker
{
    public $tree_type = 'page';
    public $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        $indent = ($args['item_spacing'] ?? 'preserve') === 'preserve' ? "\n" . str_repeat("\t", (int) $depth) : '';
        $output .= $indent . "<ul class='children'>" . (($args['item_spacing'] ?? 'preserve') === 'preserve' ? "\n" : '');
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        $preserve = ($args['item_spacing'] ?? 'preserve') === 'preserve';
        $output .= ($preserve ? str_repeat("\t", (int) $depth) : '') . '</ul>' . ($preserve ? "\n" : '');
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $page = $data_object;
        $preserve = ($args['item_spacing'] ?? 'preserve') === 'preserve';
        $indent = $depth > 0 && $preserve ? str_repeat("\t", (int) $depth) : '';
        $classes = ['page_item', 'page-item-' . (int) $page->ID];
        if ($depth === 0 && !empty($args['pages_with_children'][$page->ID])) {
            $classes[] = 'page_item_has_children';
        }
        if ((int) $current_object_id !== 0 && (int) $page->ID === (int) $current_object_id) {
            $classes[] = 'current_page_item';
        }
        $classes = implode(' ', array_filter(array_unique((array) apply_filters('page_css_class', $classes, $page, $depth, $args, $current_object_id))));
        $title = apply_filters('the_title', (string) $page->post_title, (int) $page->ID);
        $output .= $indent . '<li class="' . esc_attr($classes) . '">'
            . '<a href="' . esc_url(get_permalink((int) $page->ID)) . '">'
            . ($args['link_before'] ?? '') . $title . ($args['link_after'] ?? '')
            . '</a>';
        if (!empty($args['show_date'])) {
            $field = ($args['show_date'] ?? '') === 'modified' ? 'post_modified' : 'post_date';
            $output .= ' ' . mysql2date((string) ($args['date_format'] ?? get_option('date_format')), (string) $page->$field);
        }
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
        $output .= '</li>' . (($args['item_spacing'] ?? 'preserve') === 'preserve' ? "\n" : '');
    }
}
