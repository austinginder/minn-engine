<?php
/**
 * The tree walker plugins and themes subclass: elements keyed by the
 * db_fields parent/id pair, displayed depth-first through the four
 * element methods subclasses override. Traversal lives in Minn\Runtime\TreeWalk.
 */
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
}
