<?php
/**
 * The category list walker (probe plugin-queue4): a cat-item per category,
 * its link through category_list_link_attributes, its feed and count after,
 * the current category and its parent and ancestors marked; one line per
 * category with the separator when the style is not a list.
 */
class Walker_Category extends Walker
{
    public $tree_type = 'category';
    public $db_fields = ['parent' => 'parent', 'id' => 'term_id'];

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
        if (($args['style'] ?? '') === 'list') {
            $output .= str_repeat("\t", (int) $depth) . "<ul class='children'>\n";
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
        if (($args['style'] ?? '') === 'list') {
            $output .= str_repeat("\t", (int) $depth) . "</ul>\n";
        }
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
        $category = $data_object;
        $name = apply_filters('list_cats', esc_attr($category->name), $category);
        if ($name === '') {
            return;
        }
        $atts = ['href' => get_term_link($category)];
        if (!empty($args['use_desc_for_title']) && !empty($category->description)) {
            $atts['title'] = strip_tags(apply_filters('category_description', $category->description, $category));
        }
        $atts = apply_filters('category_list_link_attributes', $atts, $category, $depth, $args, $current_object_id);
        $link = '<a' . _minn_link_attributes((array) $atts) . '>' . $name . '</a>' . $this->feed($category, (array) $args, $name);
        if (!empty($args['show_count'])) {
            $link .= ' (' . number_format_i18n($category->count) . ')';
        }
        if (($args['style'] ?? '') === 'list') {
            $classes = $this->classes($category, (array) $args);
            $link = in_array('current-cat', $classes, true) ? str_replace('<a', '<a aria-current="page"', $link) : $link;
            $classes = implode(' ', (array) apply_filters('category_css_class', $classes, $category, $depth, $args));
            $output .= "\t<li" . ($classes !== '' ? ' class="' . esc_attr($classes) . '"' : '') . ">{$link}\n";
        } else {
            $output .= "\t{$link}" . ($args['separator'] ?? '<br />') . "\n";
        }
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
        if (($args['style'] ?? '') === 'list') {
            $output .= "</li>\n";
        }
    }

    /** The feed link after a category's link: its text in parentheses, or its image. */
    private function feed(object $category, array $args, string $name): string
    {
        if (empty($args['feed']) && empty($args['feed_image'])) {
            return '';
        }
        $href = '<a href="' . esc_url(get_term_feed_link($category, $category->taxonomy, $args['feed_type'] ?? '')) . '"' . (empty($args['feed']) ? '' : (string) ($args['title'] ?? '')) . '>';
        if (empty($args['feed_image'])) {
            return ' (' . $href . $args['feed'] . '</a>)';
        }
        $alt = empty($args['feed']) ? sprintf(__('Feed for all posts filed under %s'), $name) : $args['feed'];
        return ' ' . $href . "<img src='" . esc_url($args['feed_image']) . "' alt=\"{$alt}\" /></a>";
    }

    /** @return list<string> cat-item and its id, then current-cat, or -parent and -ancestor, for each current category */
    private function classes(object $category, array $args): array
    {
        $classes = ['cat-item', 'cat-item-' . $category->term_id];
        $current = empty($args['current_category']) ? [] : get_terms(['taxonomy' => $category->taxonomy, 'include' => $args['current_category'], 'hide_empty' => false]);
        foreach (is_array($current) ? $current : [] as $term) {
            if ((int) $category->term_id === (int) $term->term_id) {
                $classes[] = 'current-cat';
            } elseif ((int) $category->term_id === (int) $term->parent) {
                $classes[] = 'current-cat-parent';
            }
            if (in_array((int) $category->term_id, array_map('intval', get_ancestors((int) $term->term_id, $category->taxonomy, 'taxonomy')), true)) {
                $classes[] = 'current-cat-ancestor';
            }
        }
        return $classes;
    }
}
