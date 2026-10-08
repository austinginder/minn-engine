<?php
/**
 * The category select's walker (probe plugin-queue4): one option per
 * category, a level class and three non-breaking spaces per level, the
 * value from the field asked for (the id when the category has no such
 * field), selected when it matches; the name through list_cats, unescaped
 * after, and the count when asked.
 */
class Walker_CategoryDropdown extends Walker
{
    public $tree_type = 'category';
    public $db_fields = ['parent' => 'parent', 'id' => 'term_id'];

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $category = $data_object;
        $field = (string) ($args['value_field'] ?? 'term_id');
        $value = isset($category->$field) ? $category->$field : $category->term_id;
        $selected = (string) $value === (string) ($args['selected'] ?? '') ? ' selected="selected"' : '';
        $output .= "\t<option class=\"level-{$depth}\" value=\"" . esc_attr($value) . "\"{$selected}>" . str_repeat('&nbsp;', $depth * 3) . apply_filters('list_cats', $category->name, $category);
        if (!empty($args['show_count'])) {
            $output .= '&nbsp;&nbsp;(' . number_format_i18n($category->count) . ')';
        }
        $output .= "</option>\n";
    }
}
