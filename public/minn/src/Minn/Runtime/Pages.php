<?php

declare(strict_types=1);

namespace Minn\Runtime;

/** get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result. */
final class Pages
{
    public const DEFAULTS = ['child_of' => 0, 'sort_order' => 'ASC', 'sort_column' => 'post_title', 'hierarchical' => 1, 'exclude' => [], 'include' => [], 'meta_key' => '', 'meta_value' => '', 'authors' => '', 'parent' => -1, 'exclude_tree' => [], 'number' => '', 'offset' => 0, 'post_type' => 'page', 'post_status' => 'publish'];

    private const COLUMNS = ['post_title' => 'title', 'menu_order' => 'menu_order', 'post_date' => 'date', 'post_modified' => 'modified', 'ID' => 'ID', 'post_author' => 'author', 'post_name' => 'name', 'post_parent' => 'parent'];

    /** The post query arguments the get_pages() arguments amount to. @param list<int> $include @param list<int> $exclude */
    public static function queryArgs(array $parsed, array $include, array $exclude): array
    {
        $query = ['post_type' => $parsed['post_type'], 'post_status' => $parsed['post_status'], 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'ignore_sticky_posts' => true, 'no_found_rows' => true];
        $orderby = [];
        foreach (preg_split('/[\s,]+/', trim((string) $parsed['sort_column']), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $column) {
            $key = self::COLUMNS[$column] ?? self::COLUMNS['post_' . $column] ?? null;
            if ($key !== null) {
                $orderby[$key] = strtoupper((string) $parsed['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
            }
        }
        if ($orderby !== []) {
            $query['orderby'] = $orderby;
        }
        if ((int) $parsed['parent'] >= 0) {
            $query['post_parent'] = (int) $parsed['parent'];
        }
        if ($include !== []) {
            $query['post__in'] = $include;
        }
        if ($exclude !== []) {
            $query['post__not_in'] = $exclude;
        }
        if ($parsed['meta_key'] !== '') {
            $query['meta_key'] = $parsed['meta_key'];
            $query['meta_value'] = $parsed['meta_value'];
        }
        if ($parsed['authors'] !== '') {
            $query['author'] = $parsed['authors'];
        }
        return $query;
    }

    /**
     * Parents first, each followed by its own subtree, then the child_of,
     * exclude_tree, and offset/number cuts, over objects with ID and post_parent.
     *
     * @param list<object> $pages
     * @return list<object>
     */
    public static function arrange(array $pages, array $parsed): array
    {
        if ($parsed['hierarchical'] && (int) $parsed['parent'] < 0) {
            $pages = self::treeOrder($pages);
        }
        if ((int) $parsed['child_of'] > 0) {
            $pages = self::descendants($pages, (int) $parsed['child_of']);
        }
        foreach ((array) $parsed['exclude_tree'] as $tree) {
            $excluded = array_map(static fn (object $p) => (int) $p->ID, self::descendants($pages, (int) $tree));
            $excluded[] = (int) $tree;
            $pages = array_values(array_filter($pages, static fn (object $p) => !in_array((int) $p->ID, $excluded, true)));
        }
        if ((int) $parsed['offset'] > 0 || $parsed['number'] !== '') {
            $pages = array_slice($pages, (int) $parsed['offset'], $parsed['number'] === '' ? null : (int) $parsed['number']);
        }
        return array_values($pages);
    }

    /** @param list<object> $pages @return list<object> */
    private static function treeOrder(array $pages): array
    {
        $byParent = [];
        foreach ($pages as $page) {
            $byParent[(int) $page->post_parent][] = $page;
        }
        $known = array_map(static fn (object $p) => (int) $p->ID, $pages);
        $walk = static function (int $parent) use (&$walk, &$byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $page) {
                $out[] = $page;
                array_push($out, ...$walk((int) $page->ID));
            }
            return $out;
        };
        $roots = [];
        foreach ($pages as $page) {
            if (!in_array((int) $page->post_parent, $known, true)) {
                $roots[] = (int) $page->post_parent;
            }
        }
        $ordered = [];
        foreach (array_unique($roots) as $root) {
            array_push($ordered, ...$walk($root));
        }
        return $ordered;
    }

    /** Every page under one ancestor, in list order. @param list<object> $pages @return list<object> */
    public static function descendants(array $pages, int $parent): array
    {
        $out = [];
        $wanted = [$parent];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($pages as $page) {
                if (in_array((int) $page->post_parent, $wanted, true) && !in_array($page, $out, true)) {
                    $out[] = $page;
                    $wanted[] = (int) $page->ID;
                    $changed = true;
                }
            }
        }
        return $out;
    }
}
