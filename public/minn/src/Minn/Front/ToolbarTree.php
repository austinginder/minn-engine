<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * WP_Admin_Bar's nodes bound into the tree they print as, as the reference
 * binds them (probe admin-bar): each gains its children and its type; an
 * item's children go into its "-default" group, made when the first one
 * arrives; a group inside a group sits next to it in a "-container", put
 * where the outer group was; a node whose parent is missing is left out.
 * The bar's own node accessors are used throughout, so a subclass that
 * overrides them still sees every node it is asked for.
 */
final class ToolbarTree
{
    /**
     * @param \Closure(string): ?object $get the bar's _get_node
     * @param \Closure(array<string, mixed>): void $set the bar's _set_node
     */
    private function __construct(private \Closure $get, private \Closure $set)
    {
    }

    /**
     * The root of the bound tree, for nodes (the bar's, a root among them)
     * reached through the bar's accessors.
     *
     * @param array<string, object> $nodes
     * @param \Closure(string): ?object $get
     * @param \Closure(array<string, mixed>): void $set
     */
    public static function bind(array $nodes, \Closure $get, \Closure $set): ?object
    {
        foreach ($nodes as $node) {
            $node->children = [];
            $node->type = $node->group ? 'group' : 'item';
            unset($node->group);
            $node->parent = $node->parent ?: 'root';
        }
        $tree = new self($get, $set);
        foreach ($nodes as $node) {
            $tree->place($node);
        }
        return ($get)('root');
    }

    /** A node into its parent's children, through a default group or a container when it needs one. */
    private function place(object $node): void
    {
        $parent = $node->id === 'root' ? null : ($this->get)($node->parent);
        if ($parent === null) {
            return;
        }
        $groupClass = $node->parent === 'root' ? 'ab-top-menu' : 'ab-submenu';
        if ($node->type === 'group') {
            $node->meta['class'] = trim(($node->meta['class'] ?? '') . ' ' . $groupClass);
        }
        if ($parent->type === 'item' && $node->type === 'item') {
            $parent = $this->defaultGroup($parent, $groupClass);
        } elseif ($parent->type === 'group' && $node->type === 'group') {
            $parent = $this->container($parent);
        }
        $node->parent = $parent->id;
        $parent->children[] = $node;
    }

    /** The group an item's child items go into, made the first time. */
    private function defaultGroup(object $parent, string $groupClass): object
    {
        $id = $parent->id . '-default';
        $group = ($this->get)($id);
        if ($group === null) {
            ($this->set)(['id' => $id, 'parent' => $parent->id, 'type' => 'group', 'children' => [], 'meta' => ['class' => $groupClass], 'title' => false, 'href' => false]);
            $group = ($this->get)($id);
            $parent->children[] = $group;
        }
        return $group;
    }

    /** The container a group shares with the groups inside it, put where the group was. */
    private function container(object $group): object
    {
        $id = $group->id . '-container';
        $container = ($this->get)($id);
        if ($container !== null) {
            return $container;
        }
        ($this->set)(['id' => $id, 'type' => 'container', 'children' => [$group], 'parent' => false, 'title' => false, 'href' => false, 'meta' => []]);
        $container = ($this->get)($id);
        $holder = ($this->get)((string) $group->parent);
        if ($holder !== null) {
            $container->parent = $holder->id;
            $at = array_search($group, $holder->children, true);
            if ($at === false) {
                $holder->children[] = $container;
            } else {
                $holder->children[$at] = $container;
            }
        }
        $group->parent = $container->id;
        return $container;
    }
}
