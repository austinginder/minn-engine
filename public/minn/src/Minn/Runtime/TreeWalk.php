<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The Walker contract's traversal: elements keyed by the walker's
 * db_fields parent/id pair, displayed depth-first through the walker's
 * four element methods, whole or a page of top-level elements at a time
 * (probe comment-walker). The facade Walker delegates here; subclasses
 * override the element methods (or display_element itself, as
 * Walker_Comment does to keep replies past the depth limit at their
 * parent's level) and the dispatch stays virtual.
 */
final class TreeWalk
{
    /**
     * Walks a tree with a Walker the way the reference does.
     *
     * @param list<object> $elements
     */
    public static function walk(object $walker, array $elements, int $maxDepth, array $args): string
    {
        $output = '';
        if ($maxDepth < -1 || $elements === []) {
            return $output;
        }
        if ($maxDepth === -1) {
            foreach ($elements as $element) {
                self::alone($walker, $element, $args, $output);
            }
            return $output;
        }
        [$top, $children] = self::buckets($walker, $elements);
        if ($top === []) {
            // With no element at the top, the first one's siblings are.
            [$top, $children] = self::buckets($walker, $elements, $elements[0]->{$walker->db_fields['parent']});
        }
        foreach ($top as $element) {
            $walker->display_element($element, $children, $maxDepth, 0, $args, $output);
        }
        // With every level shown, elements whose parent never displayed are orphans; they print at the top level.
        if ($maxDepth === 0) {
            self::orphans($walker, $children, $args, $output);
        }
        return $output;
    }

    /**
     * One page of top-level elements, each with its children (paged_walk);
     * no paging when the page is below one or the size negative. Sets the
     * walker's max_pages. Elements whose parent is not in the list are
     * orphans, printed at the top level after the last page's elements;
     * the replies of earlier pages' elements are not orphans.
     *
     * @param list<object> $elements
     */
    public static function page(object $walker, array $elements, int $maxDepth, int $page, int $perPage, array $args): string
    {
        $output = '';
        if ($maxDepth < -1 || $elements === []) {
            return $output;
        }
        $flat = $maxDepth === -1;
        [$top, $children] = $flat ? [$elements, []] : self::buckets($walker, $elements);
        $total = count($top);
        $paging = $page >= 1 && $perPage >= 0;
        $start = $paging ? ($page - 1) * $perPage : 0;
        $end = $paging ? $start + $perPage : $total;
        $walker->max_pages = $paging && $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $options = isset($args[0]) && is_array($args[0]) ? $args[0] : [];
        if (!empty($options['reverse_top_level'])) {
            $top = array_reverse($top);
            [$start, $end] = [$total - $end, $total - $start];
        }
        if (!empty($options['reverse_children'])) {
            $children = array_map('array_reverse', $children);
        }
        $last = $end >= $total;
        foreach ($top as $index => $element) {
            if ($index >= $end) {
                break;
            }
            if ($index < $start) {
                // On the last page, an earlier page's element takes its replies with it: they are not orphans.
                if ($last && !$flat) {
                    $walker->unset_children($element, $children);
                }
            } elseif ($flat) {
                self::alone($walker, $element, $args, $output);
            } else {
                $walker->display_element($element, $children, $maxDepth, 0, $args, $output);
            }
        }
        if ($last) {
            self::orphans($walker, $children, $args, $output);
        }
        return $output;
    }

    /** How many of the elements have no parent. @param list<object> $elements */
    public static function roots(object $walker, array $elements): int
    {
        $parentField = $walker->db_fields['parent'];
        return count(array_filter($elements, static fn ($element) => empty($element->$parentField)));
    }

    /**
     * An element's children, and theirs through the walker's unset_children,
     * taken out of the children list.
     *
     * @param array<int|string, list<object>> $children
     */
    public static function forget(object $walker, mixed $element, array &$children): void
    {
        if (!$element || !$children) {
            return;
        }
        $id = $element->{$walker->db_fields['id']};
        foreach ($children[$id] ?? [] as $child) {
            $walker->unset_children($child, $children);
        }
        unset($children[$id]);
    }

    /**
     * Walks one element and its children; replies past the depth limit stay
     * in the list for the walker (or the orphans) to place.
     *
     * @param array<int|string, mixed> $children
     */
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
            unset($children[$id]);
            $walker->end_lvl($output, $depth, ...array_values($args));
        }
        $walker->end_el($output, $element, $depth, ...array_values($args));
    }

    /**
     * The top-level elements (no parent, or the parent given) and the
     * others by parent.
     *
     * @param list<object> $elements
     * @return array{0: list<object>, 1: array<int|string, list<object>>}
     */
    private static function buckets(object $walker, array $elements, mixed $root = null): array
    {
        $parentField = $walker->db_fields['parent'];
        $top = [];
        $children = [];
        foreach ($elements as $element) {
            $parent = $element->$parentField;
            if ($root === null ? empty($parent) : $parent === $root) {
                $top[] = $element;
            } else {
                $children[$parent][] = $element;
            }
        }
        return [$top, $children];
    }

    /** One element on its own at the top level: no children, one level shown. */
    private static function alone(object $walker, mixed $element, array $args, string &$output): void
    {
        $empty = [];
        $walker->display_element($element, $empty, 1, 0, $args, $output);
    }

    /** @param array<int|string, list<object>> $children */
    private static function orphans(object $walker, array $children, array $args, string &$output): void
    {
        foreach ($children as $orphans) {
            foreach ($orphans as $orphan) {
                self::alone($walker, $orphan, $args, $output);
            }
        }
    }
}
