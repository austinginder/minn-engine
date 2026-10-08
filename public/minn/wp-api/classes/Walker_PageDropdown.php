<?php
/**
 * The page select's walker (probe plugin-queue4): one option per page, a
 * level class and three non-breaking spaces per level, the value from the
 * field asked for (the id when the page has no such field), selected when
 * the page's id is the one chosen; the title (or "#id (no title)") through
 * list_pages, then escaped.
 */
class Walker_PageDropdown extends Walker
{
    public $tree_type = 'page';
    public $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $page = $data_object;
        $field = (string) ($args['value_field'] ?? 'ID');
        $value = isset($page->$field) ? $page->$field : $page->ID;
        $selected = (string) $page->ID === (string) ($args['selected'] ?? '') ? ' selected="selected"' : '';
        $title = $page->post_title === '' ? sprintf(__('#%d (no title)'), $page->ID) : $page->post_title;
        $output .= "\t<option class=\"level-{$depth}\" value=\"" . esc_attr($value) . "\"{$selected}>" . str_repeat('&nbsp;', $depth * 3) . esc_html(apply_filters('list_pages', $title, $page)) . "</option>\n";
    }
}
