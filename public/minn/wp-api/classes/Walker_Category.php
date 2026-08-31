<?php
/** The category list walker, printing the same cat-item markup the engine's own wp_list_categories builds. */
class Walker_Category extends Walker
{
    public $tree_type = 'category';
    public $db_fields = ['parent' => 'parent', 'id' => 'term_id'];

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        if (($args['style'] ?? 'list') !== 'list') {
            return;
        }
        $output .= str_repeat("\t", (int) $depth) . "<ul class='children'>\n";
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        if (($args['style'] ?? 'list') !== 'list') {
            return;
        }
        $output .= str_repeat("\t", (int) $depth) . "</ul>\n";
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $category = $data_object;
        $name = apply_filters('list_cats', esc_html((string) $category->name), $category);
        $anchor = '<a href="' . esc_url((string) get_term_link($category)) . '">' . $name . '</a>';
        if (!empty($args['show_count'])) {
            $anchor .= ' (' . (int) $category->count . ')';
        }
        if (($args['style'] ?? 'list') !== 'list') {
            $output .= "\t" . $anchor . ($args['separator'] ?? '<br />') . "\n";
            return;
        }
        $classes = 'cat-item cat-item-' . (int) $category->term_id;
        if ((int) ($args['current_category'] ?? 0) === (int) $category->term_id) {
            $classes .= ' current-cat';
        }
        $classes = implode(' ', apply_filters('category_css_class', explode(' ', $classes), $category, $depth, $args));
        $output .= "\t" . '<li class="' . $classes . '">' . $anchor . "\n";
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
        if (($args['style'] ?? 'list') !== 'list') {
            return;
        }
        $output .= "</li>\n";
    }
}
