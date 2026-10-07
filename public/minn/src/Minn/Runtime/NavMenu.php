<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Escape;

/**
 * Nav-menu item decoration for wp_nav_menu(): the reference's class tokens
 * (menu-item, the type and object tokens, menu-item-home) and the current
 * markers (current-menu-item with its page compat tokens, the parent and
 * ancestor chain). The facade's wp_nav_menu() fetches and sorts the items;
 * this marks them against the standing main query.
 */
final class NavMenu
{
    /** @var list<string> the ul ids already used on this page, so a repeated menu counts up */
    private static array $usedIds = [];

    /**
     * The whole menu for wp_nav_menu(): resolve, fetch, decorate, walk,
     * wrap, filter. Null when there is no menu (the caller's fallback
     * runs); false when the items filtered away to nothing.
     */
    public static function build(object $args): string|false|null
    {
        $menu = self::menuForArgs($args);
        $items = $menu ? \wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]) : false;
        if (!$menu || !$items) {
            return null;
        }
        $items = self::decorate($items);
        if ((int) $args->depth !== 1) {
            $parents = array_map(static fn ($item) => (int) $item->menu_item_parent, $items);
            foreach ($items as $item) {
                if (in_array((int) $item->db_id, $parents, true)) {
                    $item->classes[] = 'menu-item-has-children';
                }
            }
        }
        $items = \apply_filters('wp_nav_menu_objects', $items, $args);
        $markup = (string) \walk_nav_menu_tree($items, (int) $args->depth, $args);
        $markup = (string) \apply_filters('wp_nav_menu_items', $markup, $args);
        $markup = (string) \apply_filters("wp_nav_menu_{$menu->slug}_items", $markup, $args);
        if ($markup === '') {
            return false;
        }
        $nav = sprintf((string) $args->items_wrap, Escape::attr(self::wrapId($menu, $args)), Escape::attr((string) $args->menu_class), $markup);
        return self::container($nav, $args, (string) $menu->slug);
    }

    /** The menu the args name: by menu, by assigned location, else the first menu that has items. */
    private static function menuForArgs(object $args): object|false
    {
        $menu = \wp_get_nav_menu_object($args->menu);
        if (!$menu && $args->theme_location) {
            $locations = \get_nav_menu_locations();
            return isset($locations[$args->theme_location]) ? \wp_get_nav_menu_object($locations[$args->theme_location]) : false;
        }
        if (!$menu && !$args->theme_location) {
            foreach (\wp_get_nav_menus() as $candidate) {
                if (\wp_get_nav_menu_items($candidate->term_id, ['update_post_term_cache' => false])) {
                    return $candidate;
                }
            }
        }
        return $menu;
    }

    /** The ul id: the caller's, else menu-{slug}, counted up when the same menu renders twice on a page. */
    private static function wrapId(object $menu, object $args): string
    {
        if (!empty($args->menu_id)) {
            return (string) $args->menu_id;
        }
        $id = 'menu-' . $menu->slug;
        while (in_array($id, self::$usedIds, true)) {
            $count = preg_match('/-(\d+)$/', $id, $m) ? (int) $m[1] + 1 : 1;
            $id = preg_replace('/-\d+$/', '', $id) . '-' . $count;
        }
        self::$usedIds[] = $id;
        return $id;
    }

    /** The container wrap, when the args ask for one. */
    private static function container(string $nav, object $args, string $slug): string
    {
        if (!$args->container) {
            return $nav;
        }
        $allowed = \apply_filters('wp_nav_menu_container_allowedtags', ['div', 'nav']);
        if (!in_array($args->container, (array) $allowed, true)) {
            return $nav;
        }
        $attributes = $args->container_id ? ' id="' . Escape::attr((string) $args->container_id) . '"' : '';
        $attributes .= ' class="' . Escape::attr((string) ($args->container_class ?: 'menu-' . $slug . '-container')) . '"';
        if ($args->container === 'nav' && $args->container_aria_label) {
            $attributes .= ' aria-label="' . Escape::attr((string) $args->container_aria_label) . '"';
        }
        return '<' . $args->container . $attributes . '>' . $nav . '</' . $args->container . '>';
    }

    /**
     * Menu items with the classes and flags the reference adds for the current page.
     *
     * @param list<object> $items
     * @return list<object>
     */
    public static function decorate(array $items): array
    {
        $frontPageId = (int) Runtime::options()->filtered('page_on_front');
        $postsPageId = (int) Runtime::options()->filtered('page_for_posts');
        $queriedId = (int) \get_queried_object_id();
        $home = \untrailingslashit((string) \home_url());
        $context = self::singularContext();
        $currentIds = $parentItemIds = $ancestorItemIds = [];
        foreach ($items as $item) {
            $classes = array_values(array_filter(array_map('strval', (array) ($item->classes ?? []))));
            array_unshift($classes, 'menu-item', 'menu-item-type-' . (string) $item->type, 'menu-item-object-' . (string) $item->object);
            $item->current_item_ancestor = false;
            $item->current_item_parent = false;
            if ((string) $item->object === 'page' && (int) $item->object_id === $frontPageId && $frontPageId > 0) {
                $classes[] = 'menu-item-home';
            }
            if ($context !== null) {
                self::markQueriedAncestry($item, $classes, $context, $parentItemIds, $ancestorItemIds);
            }
            $item->current = self::isCurrent($item, $queriedId, $frontPageId, $postsPageId);
            if ($item->current) {
                $classes[] = 'current-menu-item';
                if ((string) $item->object === 'page') {
                    $classes[] = 'page_item';
                    $classes[] = 'page-item-' . (int) $item->object_id;
                    $classes[] = 'current_page_item';
                } elseif ((string) $item->type === 'custom' && \untrailingslashit((string) $item->url) === $home) {
                    $classes[] = 'current_page_item';
                }
                $currentIds[] = (int) $item->db_id;
            }
            // A custom item pointing at home is the home item on EVERY view, seated after the current tokens.
            if ((string) $item->type === 'custom' && \untrailingslashit((string) $item->url) === $home) {
                $classes[] = 'menu-item-home';
            }
            $item->classes = $classes;
        }
        return self::markAncestors($items, $currentIds, $postsPageId, $context, $parentItemIds, $ancestorItemIds);
    }

    /**
     * What the queried singular object is related to, for the
     * current-{object}-parent/-ancestor family: the post's terms (their
     * items read as the post's ancestors in the menu sense) and, on a
     * hierarchical type, the post's own ancestor chain.
     *
     * @return array{type: string, termIds: list<int>, termAncestorIds: list<int>, parentId: int, postAncestorIds: list<int>}|null
     */
    private static function singularContext(): ?array
    {
        if (!\is_singular()) {
            return null;
        }
        $post = \get_queried_object();
        if (!$post instanceof \WP_Post) {
            return null;
        }
        $termIds = [];
        $termAncestorIds = [];
        foreach ((array) \get_object_taxonomies((string) $post->post_type) as $taxonomy) {
            foreach ((array) \wp_get_object_terms([(int) $post->ID], [(string) $taxonomy], ['fields' => 'ids']) as $termId) {
                if (!is_numeric($termId)) {
                    continue;
                }
                $termIds[] = (int) $termId;
                foreach ((array) \get_ancestors((int) $termId, (string) $taxonomy, 'taxonomy') as $ancestor) {
                    $termAncestorIds[] = (int) $ancestor;
                }
            }
        }
        $postAncestorIds = array_map('intval', (array) \get_ancestors((int) $post->ID, (string) $post->post_type, 'post_type'));
        return [
            'type' => (string) $post->post_type,
            'termIds' => $termIds,
            'termAncestorIds' => array_values(array_diff($termAncestorIds, $termIds)),
            'parentId' => (int) $post->post_parent,
            'postAncestorIds' => $postAncestorIds,
        ];
    }

    /**
     * The typed ancestor marks: a taxonomy item holding one of the post's
     * terms is an ancestor (its db id feeds the menu-parent pass); a
     * post_type item on the queried post's ancestor chain is an ancestor,
     * the direct parent also a parent, with no menu-parent mark.
     *
     * @param list<string> $classes
     * @param array{type: string, termIds: list<int>, termAncestorIds: list<int>, parentId: int, postAncestorIds: list<int>} $context
     * @param list<int> $parentItemIds
     * @param list<int> $ancestorItemIds
     */
    private static function markQueriedAncestry(object $item, array &$classes, array $context, array &$parentItemIds, array &$ancestorItemIds): void
    {
        $objectId = (int) $item->object_id;
        if ((string) $item->type === 'taxonomy') {
            if (in_array($objectId, $context['termIds'], true)) {
                $classes[] = 'current-' . $context['type'] . '-ancestor';
                $parentItemIds[] = (int) $item->db_id;
            } elseif (in_array($objectId, $context['termAncestorIds'], true)) {
                $classes[] = 'current-' . $context['type'] . '-ancestor';
                $ancestorItemIds[] = (int) $item->db_id;
            }
        } elseif ((string) $item->type === 'post_type' && (string) $item->object === $context['type']) {
            if (in_array($objectId, $context['postAncestorIds'], true)) {
                $classes[] = 'current-' . $context['type'] . '-ancestor';
                $item->current_item_ancestor = true;
                if ($objectId === $context['parentId']) {
                    $classes[] = 'current-' . $context['type'] . '-parent';
                    $item->current_item_parent = true;
                }
            }
        }
    }

    private static function isCurrent(object $item, int $queriedId, int $frontPageId, int $postsPageId): bool
    {
        $type = (string) $item->type;
        $objectId = (int) $item->object_id;
        if ($type === 'post_type') {
            if (\is_front_page() && $objectId === $frontPageId && $frontPageId > 0) {
                return true;
            }
            if (\is_home() && $objectId === $postsPageId && $postsPageId > 0) {
                return true;
            }
            return \is_singular() && $objectId === $queriedId && $queriedId > 0;
        }
        if ($type === 'taxonomy') {
            return (\is_category() || \is_tag() || \is_tax()) && $objectId === $queriedId && $queriedId > 0;
        }
        if ($type === 'custom') {
            // Exact request-URL equality only: the home item is NOT current on /page/2/ or ?s= views.
            $url = \untrailingslashit((string) $item->url);
            return $url !== '' && $url === \untrailingslashit(self::currentUrl());
        }
        return false;
    }

    /**
     * The menu chain above the current items: the direct parent gets both
     * parent and ancestor markers, everything higher gets ancestor. The
     * posts-page item is the page parent while a single post is on view.
     *
     * @param list<object> $items
     * @param list<int> $currentIds
     * @param array{type: string, termIds: list<int>, termAncestorIds: list<int>, parentId: int, postAncestorIds: list<int>}|null $context
     * @param list<int> $parentItemIds
     * @param list<int> $ancestorItemIds
     * @return list<object>
     */
    private static function markAncestors(array $items, array $currentIds, int $postsPageId, ?array $context, array $parentItemIds, array $ancestorItemIds): array
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item->db_id] = $item;
        }
        foreach ($currentIds as $id) {
            $parentId = (int) ($byId[$id]->menu_item_parent ?? 0);
            $depth = 0;
            while ($parentId > 0 && isset($byId[$parentId]) && $depth < 50) {
                $parent = $byId[$parentId];
                $parent->classes[] = 'current-menu-ancestor';
                $parent->current_item_ancestor = true;
                if ($depth === 0) {
                    $parent->classes[] = 'current-menu-parent';
                    $parent->current_item_parent = true;
                }
                $parentId = (int) $parent->menu_item_parent;
                $depth++;
            }
        }
        // The term items read as the current object's menu parents; the object-id
        // pass compares raw ids, so a post item whose id collides with a term id
        // picks up current-{object}-parent too, exactly as the reference does.
        foreach ($items as $item) {
            if (in_array((int) $item->db_id, $ancestorItemIds, true)) {
                $item->classes[] = 'current-menu-ancestor';
                $item->current_item_ancestor = true;
            }
            if (in_array((int) $item->db_id, $parentItemIds, true)) {
                $item->classes[] = 'current-menu-parent';
                $item->current_item_parent = true;
            }
            if ($context !== null && $context['termIds'] !== [] && in_array((int) $item->object_id, $context['termIds'], true)) {
                $item->classes[] = 'current-' . $context['type'] . '-parent';
            }
        }
        if (\is_singular('post') && $postsPageId > 0) {
            foreach ($items as $item) {
                if ((string) $item->object === 'page' && (int) $item->object_id === $postsPageId) {
                    $item->classes[] = 'current_page_parent';
                }
            }
        }
        foreach ($items as $item) {
            $item->classes = array_values(array_unique($item->classes));
        }
        return $items;
    }

    private static function currentUrl(): string
    {
        $request = Runtime::current()->request;
        if ($request === null) {
            return '';
        }
        return ($request->secure ? 'https' : 'http') . '://' . $request->host . $request->path . $request->queryStringWithout();
    }
}
