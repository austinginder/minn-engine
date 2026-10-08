<?php

declare(strict_types=1);

namespace Minn\Support;

use Closure;

/**
 * Loops in a parent chain, found with two walkers: a slow one that takes one
 * step at a time and a fast one that takes two (probe functions-utils, the
 * hierarchy-loop finders). They meet only when the chain loops; it ends
 * when a parent is empty. Each walker reads parents through the closure it
 * is given, so a caller can override any node's parent.
 */
final class Hierarchy
{
    /**
     * The node where the slow walker meets the fast one, or null when the chain ends.
     *
     * @param Closure(mixed): mixed $parentOf a node's parent
     */
    public static function meeting(Closure $parentOf, mixed $start): mixed
    {
        return self::walk($parentOf, $start)[0];
    }

    /**
     * Every node either walker stepped on before they met, in the order first
     * reached (the slow walker's node, then the fast one's two steps), or null
     * when the chain ends without a loop.
     *
     * @param Closure(mixed): mixed $parentOf a node's parent
     * @return array<int|string, true>|null
     */
    public static function walked(Closure $parentOf, mixed $start): ?array
    {
        [$meeting, $walked] = self::walk($parentOf, $start);
        return $meeting === null ? null : $walked;
    }

    /** @return array{0: mixed, 1: array<int|string, true>} the meeting node (or null) and the nodes walked */
    private static function walk(Closure $parentOf, mixed $start): array
    {
        $walked = [];
        $slow = $start;
        $fast = $start;
        while ($slow) {
            $walked[$slow] = true;
            $step = $parentOf($fast);
            if (!$step) {
                break;
            }
            $walked[$step] = true;
            $fast = $parentOf($step);
            if (!$fast) {
                break;
            }
            $walked[$fast] = true;
            if ($slow === $step || $slow === $fast) {
                return [$slow, $walked];
            }
            $slow = $parentOf($slow);
        }
        return [null, $walked];
    }
}
