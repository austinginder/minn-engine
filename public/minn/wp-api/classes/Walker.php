<?php
/**
 * The tree walker plugins and themes subclass: elements keyed by the
 * db_fields parent/id pair, displayed depth-first through the four
 * element methods subclasses override, whole or a page of top-level
 * elements at a time. Traversal lives in Minn\Runtime\TreeWalk.
 */
#[AllowDynamicProperties]
class Walker
{
    public $tree_type;
    public $db_fields;
    public $max_pages = 1;
    public $has_children;

    public function start_lvl(&$output, $depth = 0, $args = [])
    {
    }

    public function end_lvl(&$output, $depth = 0, $args = [])
    {
    }

    public function start_el(&$output, $data_object, $depth = 0, $args = [], $current_object_id = 0)
    {
    }

    public function end_el(&$output, $data_object, $depth = 0, $args = [])
    {
    }

    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output)
    {
        $output = (string) $output;
        \Minn\Runtime\TreeWalk::element($this, $element, $children_elements, (int) $max_depth, (int) $depth, (array) $args, $output);
    }

    public function walk($elements, $max_depth, ...$args)
    {
        return \Minn\Runtime\TreeWalk::walk($this, (array) $elements, (int) $max_depth, $args);
    }

    public function paged_walk($elements, $max_depth, $page_num, $per_page, ...$args)
    {
        return \Minn\Runtime\TreeWalk::page($this, (array) $elements, (int) $max_depth, (int) $page_num, (int) $per_page, $args);
    }

    public function get_number_of_root_elements($elements)
    {
        return \Minn\Runtime\TreeWalk::roots($this, (array) $elements);
    }

    public function unset_children($element, &$children_elements)
    {
        \Minn\Runtime\TreeWalk::forget($this, $element, $children_elements);
    }
}
