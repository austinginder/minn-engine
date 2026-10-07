<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A threaded or flat comment query's descendants as the reference fills
 * them (probe wp-comment-query-sql): one more comment query a level, with
 * the query's own variables but the level's parents, no parent, no limit
 * and no count; a threaded query hangs each reply under its parent, marks
 * every comment's children as known and keeps the top level by id, a flat
 * one lists the top level and then each level in its query's order.
 */
final class CommentThreads
{
    private const LEVEL = ['parent' => '', 'hierarchical' => false, 'number' => 0, 'offset' => 0, 'no_found_rows' => true];

    /**
     * The query's top-level comments with their descendants filled in.
     *
     * @param list<\WP_Comment> $top
     * @return array<int, \WP_Comment>
     */
    public function fill(\WP_Comment_Query $query, array $top): array
    {
        $threaded = ($query->query_vars['hierarchical'] ?? '') === 'threaded';
        $byId = [];
        foreach ($top as $comment) {
            $byId[(int) $comment->comment_ID] = $comment;
        }
        $all = $top;
        $parents = array_keys($byId);
        while ($parents !== []) {
            $children = (new \WP_Comment_Query())->query(['parent__in' => $parents] + self::LEVEL + $query->query_vars);
            $parents = [];
            foreach (is_array($children) ? $children : [] as $child) {
                $id = $child instanceof \WP_Comment ? (int) $child->comment_ID : 0;
                // A comment seen already ends its branch, so a parent loop in the data cannot run on.
                if ($id === 0 || isset($byId[$id])) {
                    continue;
                }
                $byId[$id] = $child;
                $all[] = $child;
                $parents[] = $id;
                if ($threaded && isset($byId[(int) $child->comment_parent])) {
                    $byId[(int) $child->comment_parent]->add_child($child);
                }
            }
        }
        if (!$threaded) {
            return $all;
        }
        foreach ($byId as $comment) {
            $comment->populated_children(true);
        }
        $keyed = [];
        foreach ($top as $comment) {
            $keyed[(int) $comment->comment_ID] = $comment;
        }
        return $keyed;
    }

    /**
     * A comment's children, then each one's own, depth first, as a list.
     *
     * @param array<array-key, mixed> $children
     * @param array<string, mixed> $args get_children()'s arguments, handed down
     * @return list<\WP_Comment>
     */
    public static function flatten(array $children, array $args): array
    {
        $flat = [];
        foreach ($children as $child) {
            if ($child instanceof \WP_Comment) {
                $flat[] = $child;
                array_push($flat, ...array_values((array) $child->get_children($args)));
            }
        }
        return $flat;
    }
}
