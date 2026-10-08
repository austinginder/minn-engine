<?php
/**
 * The page list walker (probe plugin-queue4): a page_item per page, marked
 * when it has children (pages_with_children), when it is the current page,
 * or its parent or ancestor (else the posts page reads as the current
 * page's parent); its classes through page_css_class, its link's
 * attributes through page_menu_link_attributes, the date after when asked.
 * "preserve" spacing keeps newlines and a tab per level, "discard" none.
 */
class Walker_Page extends Walker
{
    public $tree_type = 'page';
    public $db_fields = ['parent' => 'post_parent', 'id' => 'ID'];

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        [$t, $n] = self::spacing($args);
        $output .= "{$n}" . str_repeat($t, (int) $depth) . "<ul class='children'>{$n}";
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        [$t, $n] = self::spacing($args);
        $output .= str_repeat($t, (int) $depth) . "</ul>{$n}";
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $page = $data_object;
        [$t] = self::spacing($args);
        $classes = implode(' ', (array) apply_filters('page_css_class', $this->classes($page, (array) $args, (int) $current_object_id), $page, $depth, $args, $current_object_id));
        $title = $page->post_title === '' ? sprintf(__('#%d (no title)'), $page->ID) : $page->post_title;
        $atts = ['href' => get_permalink($page->ID), 'aria-current' => (int) $page->ID === (int) $current_object_id ? 'page' : ''];
        $atts = apply_filters('page_menu_link_attributes', $atts, $page, $depth, $args, $current_object_id);
        $output .= ($depth ? str_repeat($t, (int) $depth) : '') . '<li' . ($classes !== '' ? ' class="' . esc_attr($classes) . '"' : '') . '><a' . _minn_link_attributes((array) $atts) . '>'
            . ($args['link_before'] ?? '') . apply_filters('the_title', $title, $page->ID) . ($args['link_after'] ?? '') . '</a>';
        if (!empty($args['show_date'])) {
            $output .= ' ' . mysql2date((string) ($args['date_format'] ?? ''), $args['show_date'] === 'modified' ? $page->post_modified : $page->post_date);
        }
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
        $output .= '</li>' . self::spacing($args)[1];
    }

    /** @return list<string> page_item and its id, has-children, and where it stands to the current page */
    private function classes(object $page, array $args, int $current): array
    {
        $classes = ['page_item', 'page-item-' . $page->ID];
        if (isset($args['pages_with_children'][$page->ID])) {
            $classes[] = 'page_item_has_children';
        }
        $currentPage = $current > 0 ? get_post($current) : null;
        if ($currentPage === null) {
            return (int) get_option('page_for_posts') === (int) $page->ID ? [...$classes, 'current_page_parent'] : $classes;
        }
        if (in_array((int) $page->ID, array_map('intval', get_post_ancestors($currentPage)), true)) {
            $classes[] = 'current_page_ancestor';
        }
        if ((int) $page->ID === $current) {
            $classes[] = 'current_page_item';
        } elseif ((int) $page->ID === (int) $currentPage->post_parent) {
            $classes[] = 'current_page_parent';
        }
        return $classes;
    }

    /** @return array{string, string} the tab and the newline, both empty when spacing is discarded */
    private static function spacing(mixed $args): array
    {
        return (is_array($args) ? ($args['item_spacing'] ?? 'preserve') : 'preserve') === 'discard' ? ['', ''] : ["\t", "\n"];
    }
}
