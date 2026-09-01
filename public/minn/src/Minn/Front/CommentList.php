<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\CommentRecord;
use Closure;

/**
 * The classic threaded comment walk: top-level comments in order (or
 * reversed), replies nested to the depth allowed under a "children" list,
 * each item and its close rendered by the caller's closures.
 */
final class CommentList
{
    /**
     * @param list<array<string, mixed>> $comments rows with comment_ID and comment_parent
     * @param Closure(array, int): string $item the opening markup for a comment at a depth
     * @param Closure(array, int): string $close the closing markup
     */
    public static function render(array $comments, array $args, Closure $item, Closure $close): string
    {
        $maxDepth = (int) $args['max_depth'];
        $byParent = [];
        foreach ($comments as $comment) {
            $byParent[$maxDepth === -1 ? 0 : (int) ($comment['comment_parent'] ?? 0)][] = $comment;
        }
        // Rows from the engine's readers are records; the facade's wp_list_comments hands the runtime's arrays. Both read the same way here.
        $ids = array_map(static fn (array|CommentRecord $c) => (int) $c['comment_ID'], $comments);
        $roots = $byParent[0] ?? [];
        // A reply whose parent is not in the list stands at the top.
        foreach ($byParent as $parent => $rows) {
            if ($parent !== 0 && !in_array((int) $parent, $ids, true)) {
                array_push($roots, ...$rows);
            }
        }
        if (!empty($args['reverse_top_level'])) {
            $roots = array_reverse($roots);
        }
        $tag = match ((string) $args['style']) { 'div' => 'div', 'ol' => 'ol', default => 'ul' };
        return self::level($roots, $byParent, 1, $maxDepth, $tag, !empty($args['reverse_children']), $item, $close);
    }

    private static function level(array $rows, array $byParent, int $depth, int $maxDepth, string $tag, bool $reverseChildren, Closure $item, Closure $close): string
    {
        $out = '';
        foreach ($rows as $row) {
            $out .= $item($row, $depth);
            $children = $byParent[(int) $row['comment_ID']] ?? [];
            if ($children !== [] && ($maxDepth === 0 || $maxDepth === -1 || $depth < $maxDepth)) {
                if ($reverseChildren) {
                    $children = array_reverse($children);
                }
                $out .= '<' . $tag . ' class="children">' . "\n" . self::level($children, $byParent, $depth + 1, $maxDepth, $tag, $reverseChildren, $item, $close) . '</' . $tag . '><!-- .children -->' . "\n";
            } elseif ($children !== []) {
                // Past the depth limit, replies flatten onto their parent's level.
                $out .= self::level($children, $byParent, $depth, $maxDepth, $tag, $reverseChildren, $item, $close);
            }
            $out .= $close($row, $depth);
        }
        return $out;
    }
}
