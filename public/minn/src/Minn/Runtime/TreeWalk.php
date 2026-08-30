<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The Walker contract's traversal: elements keyed by the walker's
 * db_fields parent/id pair, displayed depth-first through the walker's
 * four element methods. The facade Walker delegates here; subclasses
 * override the element methods (or display_element itself) and the
 * dispatch stays virtual.
 */
final class TreeWalk
{
    /** @param list<object> $elements */
    public static function walk(object $walker, array $elements, int $maxDepth, array $args): string
    {
        $output = '';
        if ($maxDepth < -1 || $elements === []) {
            return $output;
        }
        $parentField = $walker->db_fields['parent'];
        if ($maxDepth === -1) {
            $empty = [];
            foreach ($elements as $element) {
                $walker->display_element($element, $empty, 1, 0, $args, $output);
            }
            return $output;
        }
        $top = [];
        $children = [];
        foreach ($elements as $element) {
            if (empty($element->$parentField)) {
                $top[] = $element;
            } else {
                $children[$element->$parentField][] = $element;
            }
        }
        if ($top === []) {
            $top = $elements;
            $children = [];
        }
        foreach ($top as $element) {
            $walker->display_element($element, $children, $maxDepth, 0, $args, $output);
        }
        // Elements whose parent never displayed are orphans; they print at the top level.
        foreach ($children as $orphans) {
            $empty = [];
            foreach ($orphans as $orphan) {
                $walker->display_element($orphan, $empty, 1, 0, $args, $output);
            }
        }
        return $output;
    }

    /** @param array<int|string, mixed> $children */
    public static function element(object $walker, mixed $element, array &$children, int $maxDepth, int $depth, array $args, string &$output): void
    {
        if (!$element) {
            return;
        }
        $id = $element->{$walker->db_fields['id']} ?? 0;
        $walker->has_children = !empty($children[$id]);
        if (isset($args[0]) && is_array($args[0])) {
            $args[0]['has_children'] = $walker->has_children;
        }
        $walker->start_el($output, $element, $depth, ...array_values($args));
        if ($walker->has_children && ($maxDepth === 0 || $maxDepth > $depth + 1)) {
            $walker->start_lvl($output, $depth, ...array_values($args));
            foreach ($children[$id] as $child) {
                $walker->display_element($child, $children, $maxDepth, $depth + 1, $args, $output);
            }
            $walker->end_lvl($output, $depth, ...array_values($args));
        }
        unset($children[$id]);
        $walker->end_el($output, $element, $depth, ...array_values($args));
    }
}
