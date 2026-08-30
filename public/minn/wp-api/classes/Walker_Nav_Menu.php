<?php
/** The default nav-menu list walker wp_nav_menu() renders through; themes subclass it for their own markup. */
class Walker_Nav_Menu extends Walker
{
    public $tree_type = ['post_type', 'taxonomy', 'custom'];
    public $db_fields = ['parent' => 'menu_item_parent', 'id' => 'db_id'];

    private function spacing($args)
    {
        $discard = is_object($args) && ($args->item_spacing ?? 'preserve') === 'discard';
        return $discard ? ['', ''] : ["\t", "\n"];
    }

    public function start_lvl(&$output, $depth = 0, $args = null)
    {
        [$t, $n] = $this->spacing($args);
        $indent = str_repeat($t, $depth);
        $classes = apply_filters('nav_menu_submenu_css_class', ['sub-menu'], $args, $depth);
        $class_names = implode(' ', array_filter(array_map('strval', (array) $classes)));
        $output .= "{$n}{$indent}<ul class=\"" . esc_attr($class_names) . "\">{$n}";
    }

    public function end_lvl(&$output, $depth = 0, $args = null)
    {
        [$t, $n] = $this->spacing($args);
        $output .= str_repeat($t, $depth) . "</ul>{$n}";
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0)
    {
        $item = $data_object;
        [$t] = $this->spacing($args);
        $indent = $depth ? str_repeat($t, $depth) : '';
        $args = apply_filters('nav_menu_item_args', $args, $item, $depth);
        $classes = array_filter(array_map('strval', (array) ($item->classes ?? [])));
        $classes[] = 'menu-item-' . $item->ID;
        $class_names = implode(' ', array_filter((array) apply_filters('nav_menu_css_class', $classes, $item, $args, $depth)));
        $id = (string) apply_filters('nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth);
        $output .= $indent . '<li' . ($id !== '' ? ' id="' . esc_attr($id) . '"' : '') . ($class_names !== '' ? ' class="' . esc_attr($class_names) . '"' : '') . '>';
        $atts = [
            'title' => (string) ($item->attr_title ?? ''),
            'target' => (string) ($item->target ?? ''),
            'rel' => ($item->target ?? '') === '_blank' && empty($item->xfn) ? 'noopener' : (string) ($item->xfn ?? ''),
            'href' => (string) ($item->url ?? ''),
            'aria-current' => !empty($item->current) ? 'page' : '',
        ];
        $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
        $attributes = '';
        foreach ((array) $atts as $attr => $value) {
            if (is_scalar($value) && (string) $value !== '' && $value !== false) {
                $attributes .= ' ' . $attr . '="' . ($attr === 'href' ? esc_url((string) $value) : esc_attr((string) $value)) . '"';
            }
        }
        $title = apply_filters('the_title', (string) ($item->title ?? ''), $item->ID);
        $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);
        $item_output = (is_object($args) ? (string) ($args->before ?? '') : '')
            . '<a' . $attributes . '>'
            . (is_object($args) ? (string) ($args->link_before ?? '') : '') . $title . (is_object($args) ? (string) ($args->link_after ?? '') : '')
            . '</a>' . (is_object($args) ? (string) ($args->after ?? '') : '');
        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = null)
    {
        [, $n] = $this->spacing($args);
        $output .= "</li>{$n}";
    }
}
