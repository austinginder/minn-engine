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
        // A number (and an offset into it) limits the query itself; the hierarchy is worked out from what it returns.
        if (!empty($parsed['number'])) {
            $query['posts_per_page'] = (int) $parsed['number'];
            if (!empty($parsed['offset'])) {
                $query['offset'] = (int) $parsed['offset'];
            }
        }
        return $query;
    }

    /**
     * The pages as the reference leaves them: with a hierarchy (unless a
     * parent is named or pages are picked by id) or a child_of, only what
     * descends from that page (the root by default), each parent followed
     * by its subtree; then any branch left out, its places left empty.
     *
     * @param list<object> $pages objects with ID and post_parent
     * @return array<int, object>
     */
    public static function arrange(array $pages, array $parsed): array
    {
        $picked = !empty($parsed['include']);
        $childOf = $picked ? 0 : (int) $parsed['child_of'];
        if ($childOf > 0 || ($parsed['hierarchical'] && (int) $parsed['parent'] < 0 && !$picked)) {
            $pages = self::children($pages, $childOf);
        }
        $pages = array_values($pages);
        foreach (\wp_parse_id_list($parsed['exclude_tree']) as $tree) {
            $excluded = array_map(static fn (object $p) => (int) $p->ID, self::children($pages, $tree));
            $excluded[] = $tree;
            $pages = array_filter($pages, static fn (object $p) => !in_array((int) $p->ID, $excluded, true));
        }
        return $pages;
    }

    /**
     * What descends from a page within the list, depth first, siblings in
     * list order (get_page_children); a page whose parent is not reached is
     * left out.
     *
     * @param list<object> $pages
     * @return list<object>
     */
    public static function children(array $pages, int $parent): array
    {
        $byParent = [];
        foreach ($pages as $page) {
            $byParent[(int) $page->post_parent][] = $page;
        }
        $out = [];
        $stack = array_reverse($byParent[$parent] ?? []);
        while ($stack !== []) {
            $page = array_pop($stack);
            $out[] = $page;
            foreach (array_reverse($byParent[(int) $page->ID] ?? []) as $child) {
                $stack[] = $child;
            }
        }
        return $out;
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
