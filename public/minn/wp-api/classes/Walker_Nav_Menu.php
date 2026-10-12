<?php

use Minn\Runtime\Runtime;

/**
 * The default nav-menu list walker wp_nav_menu() renders through; themes
 * subclass it for their own markup. It notes the privacy policy address
 * when it is made, and a link to it gets rel privacy-policy; an item's id
 * attribute is printed once a request, whichever walker prints it (probe
 * loose-classes).
 */
class Walker_Nav_Menu extends Walker
{
    public $tree_type = ['post_type', 'taxonomy', 'custom'];
    public $db_fields = ['parent' => 'menu_item_parent', 'id' => 'db_id'];
    private $privacy_policy_url;

    public function __construct()
    {
        $this->privacy_policy_url = get_privacy_policy_url();
    }

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
        $atts = apply_filters('nav_menu_submenu_attributes', ['class' => $class_names], $args, $depth);
        $output .= "{$n}{$indent}<ul" . $this->build_atts((array) $atts) . ">{$n}";
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
        $id = $id !== '' && $this->first_print_of($item->ID) ? $id : '';
        $li = apply_filters('nav_menu_item_attributes', ['id' => $id, 'class' => $class_names], $item, $args, $depth);
        $output .= $indent . '<li' . $this->build_atts((array) $li) . '>';
        $rel = (string) ($item->xfn ?? '');
        if ((string) $this->privacy_policy_url !== '' && ($item->url ?? '') === $this->privacy_policy_url) {
            $rel = ltrim($rel . ' privacy-policy');
        }
        // In the reference's order; a new tab gets no rel of its own (probe core-objects).
        $atts = [
            'target' => (string) ($item->target ?? ''),
            'rel' => $rel,
            'href' => (string) ($item->url ?? ''),
            'aria-current' => !empty($item->current) ? 'page' : '',
            'title' => (string) ($item->attr_title ?? ''),
        ];
        $atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
        $title = apply_filters('the_title', (string) ($item->title ?? ''), $item->ID);
        $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);
        $item_output = (is_object($args) ? (string) ($args->before ?? '') : '')
            . '<a' . $this->build_atts((array) $atts) . '>'
            . (is_object($args) ? (string) ($args->link_before ?? '') : '') . $title . (is_object($args) ? (string) ($args->link_after ?? '') : '')
            . '</a>' . (is_object($args) ? (string) ($args->after ?? '') : '');
        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }

    /** The link's attributes as a string: each set one escaped, the address as a URL (probe loose-classes). */
    protected function build_atts($atts = [])
    {
        return _minn_link_attributes((array) $atts);
    }

    /** Whether this item's id attribute has not been printed yet this request; noting that it now is. */
    private function first_print_of($item_id): bool
    {
        $printed = Runtime::current()->get('nav_menu_item_ids_printed', []);
        if (isset($printed[$item_id])) {
            return false;
        }
        $printed[$item_id] = true;
        Runtime::current()->set('nav_menu_item_ids_printed', $printed);
        return true;
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = null)
    {
        [, $n] = $this->spacing($args);
        $output .= "</li>{$n}";
    }
}
