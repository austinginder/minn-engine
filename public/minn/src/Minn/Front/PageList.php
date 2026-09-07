<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;

/**
 * The page hierarchy as wp_list_pages and wp_dropdown_pages draw it: nested
 * list items with the reference's page_item classes (has-children, current,
 * ancestor, parent), children indented one tab per level under a
 * <ul class='children'>, and the flat dropdown whose options carry a level
 * class and three non-breaking spaces per level. A page whose parent is not
 * in the set stands at the top, so include, exclude and child_of all nest
 * whatever remains.
 */
final readonly class PageList
{
    /** @var array<int, list<array{id: int, title: string, link: string}>> */
    private array $byParent;

    /**
     * @param list<array{id: int, parent: int, title: string, link: string}> $pages in display order
     * @param list<int> $currentTrail the queried page and its ancestors, the page first
     */
    public function __construct(array $pages, private array $currentTrail)
    {
        $ids = array_column($pages, 'id');
        $byParent = [];
        foreach ($pages as $page) {
            $parent = in_array($page['parent'], $ids, true) ? $page['parent'] : 0;
            $byParent[$parent][] = $page;
        }
        $this->byParent = $byParent;
    }

    /**
     * The list items under a page (0 for the whole tree), to a depth
     * (0 unlimited, -1 flat), with whitespace the way the reference keeps it.
     */
    public function items(int $childOf, int $depth, ListSpacing $spacing): string
    {
        if ($depth === -1) {
            return $this->flat($childOf, $spacing);
        }
        return $this->level($childOf, 0, $depth, $spacing);
    }

    /** Every page under one, flattened to a single level. */
    private function flat(int $childOf, ListSpacing $spacing): string
    {
        $out = '';
        foreach ($this->byParent[$childOf] ?? [] as $page) {
            $out .= $this->item($page, 0, $spacing) . '</li>' . $spacing->newline() . $this->flat($page['id'], $spacing);
        }
        return $out;
    }

    private function level(int $parent, int $level, int $depth, ListSpacing $spacing): string
    {
        $out = '';
        foreach ($this->byParent[$parent] ?? [] as $page) {
            $out .= $this->item($page, $level, $spacing);
            if (isset($this->byParent[$page['id']]) && ($depth === 0 || $level + 1 < $depth)) {
                $out .= $spacing->newline() . str_repeat($spacing->tab(), $level) . "<ul class='children'>" . $spacing->newline()
                    . $this->level($page['id'], $level + 1, $depth, $spacing)
                    . str_repeat($spacing->tab(), $level) . '</ul>' . $spacing->newline();
            }
            $out .= '</li>' . $spacing->newline();
        }
        return $out;
    }

    /** @param array{id: int, title: string, link: string} $page */
    private function item(array $page, int $level, ListSpacing $spacing): string
    {
        $classes = ['page_item', 'page-item-' . $page['id']];
        if (isset($this->byParent[$page['id']])) {
            $classes[] = 'page_item_has_children';
        }
        $current = ($this->currentTrail[0] ?? 0) === $page['id'];
        if ($current) {
            $classes[] = 'current_page_item';
        } elseif (in_array($page['id'], $this->currentTrail, true)) {
            $classes[] = 'current_page_ancestor';
            if (($this->currentTrail[1] ?? 0) === $page['id']) {
                $classes[] = 'current_page_parent';
            }
        }
        return str_repeat($spacing->tab(), $level) . '<li class="' . implode(' ', $classes) . '"><a href="' . $page['link'] . '"'
            . ($current ? ' aria-current="page"' : '') . '>' . $spacing->linkBefore . $page['title'] . $spacing->linkAfter . '</a>';
    }

    /** The dropdown options under a page, to a depth, with one selected. */
    public function options(int $childOf, int $depth, int $selected, Closure $value): string
    {
        return $this->optionLevel($childOf, 0, $depth, $selected, $value);
    }

    private function optionLevel(int $parent, int $level, int $depth, int $selected, Closure $value): string
    {
        $out = '';
        foreach ($this->byParent[$parent] ?? [] as $page) {
            $out .= "\t<option class=\"level-{$level}\" value=\"" . $value($page) . '"' . ($selected === $page['id'] ? ' selected="selected"' : '') . '>'
                . str_repeat('&nbsp;', $level * 3) . $page['title'] . "</option>\n";
            if ($depth === 0 || $level + 1 < $depth) {
                $out .= $this->optionLevel($page['id'], $level + 1, $depth, $selected, $value);
            }
        }
        return $out;
    }
}
