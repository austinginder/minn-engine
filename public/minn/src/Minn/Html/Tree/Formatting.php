<?php

declare(strict_types=1);

namespace Minn\Html\Tree;

/**
 * The list of active formatting elements, with its markers (null): the
 * formatting elements opened since the last marker that may need
 * reopening, three of a kind at most (the "Noah's Ark" clause).
 */
final class Formatting
{
    /** @var list<Node|null> */
    private array $entries = [];

    /** Adds a formatting element, dropping the earliest of three identical ones since the last marker. */
    public function push(Node $node): void
    {
        $same = [];
        for ($i = count($this->entries) - 1; $i >= 0 && $this->entries[$i] !== null; $i--) {
            $entry = $this->entries[$i];
            if ($entry->name === $node->name && $entry->namespace === $node->namespace && self::sameAttributes($entry, $node)) {
                $same[] = $i;
            }
        }
        if (count($same) >= 3) {
            array_splice($this->entries, end($same), 1);
        }
        $this->entries[] = $node;
    }

    /** Adds a marker (a cell, caption, template, applet, marquee or object opened). */
    public function insertMarker(): void
    {
        $this->entries[] = null;
    }

    /** Drops entries back to and including the last marker. */
    public function clearToLastMarker(): void
    {
        while ($this->entries !== []) {
            if (array_pop($this->entries) === null) {
                return;
            }
        }
    }

    /** Drops one element from the list. */
    public function remove(Node $node): void
    {
        $at = array_search($node, $this->entries, true);
        if ($at !== false) {
            array_splice($this->entries, (int) $at, 1);
        }
    }

    /** Whether this very element is in the list. */
    public function contains(Node $node): bool
    {
        return in_array($node, $this->entries, true);
    }

    /** The last HTML element of the name since the last marker. */
    public function lastNamed(string $name): ?Node
    {
        for ($i = count($this->entries) - 1; $i >= 0 && $this->entries[$i] !== null; $i--) {
            if ($this->entries[$i]->isHtml($name)) {
                return $this->entries[$i];
            }
        }
        return null;
    }

    /** Whether reopening would be needed: the last entry is an element no longer open. */
    public function needsReconstruction(OpenElements $stack): bool
    {
        $last = $this->entries === [] ? null : $this->entries[count($this->entries) - 1];
        return $last !== null && !$stack->containsNode($last);
    }

    /**
     * The entries' names, markers left out.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_map(static fn (Node $node): string => $node->name, array_filter($this->entries)));
    }

    private static function sameAttributes(Node $a, Node $b): bool
    {
        $left = $a->attributes();
        $right = $b->attributes();
        ksort($left);
        ksort($right);
        return $left === $right;
    }
}
