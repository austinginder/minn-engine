<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Url;

/**
 * The classes and current flags the reference gives a menu's items for the
 * page in view (_wp_menu_item_classes_by_context, probe plugin-queue4), in
 * two passes over the items.
 *
 * First, each item: its own classes, then menu-item and its type and
 * object; menu-item-home and menu-item-privacy-policy for those pages. On a
 * single post of a flat type, the items of the post's terms in hierarchical
 * taxonomies become its menu parents. Otherwise the current item (the post
 * or term in view, the posts page on the blog, the post type archive in
 * view, or a custom address equal to the request's) is marked, with the
 * page compat tokens; its menu ancestors, its menu parent and its object's
 * parent are noted. A custom item pointing home is the home item on every
 * view. The posts page reads as a parent on any view but a page.
 *
 * Then each item again: an item for a post or term above the one in view
 * is its ancestor (current-{type}-ancestor), the noted items take
 * current-menu-ancestor and current-menu-parent, an item whose object is a
 * noted parent object current-{object}-parent, and a page item the compat
 * tokens of both.
 */
final class MenuItemMarks
{
    /**
     * The items, each given its classes and its current, current_item_parent
     * and current_item_ancestor flags for what the main query has in view.
     *
     * @param list<object> $items
     * @return list<object>
     */
    public static function apply(array $items): array
    {
        $view = self::view(\_minn_main_query());
        $noted = ['ancestors' => [], 'parentItems' => [], 'parentObjects' => [], 'object' => ''];
        $parents = [];
        foreach ($items as $item) {
            $parents[(int) $item->db_id] = (int) $item->menu_item_parent;
        }
        foreach ($items as $item) {
            self::own($item, $view, $noted, $parents);
        }
        $noted = array_map(static fn ($list) => is_array($list) ? array_values(array_filter(array_unique($list))) : $list, $noted);
        foreach ($items as $item) {
            self::related($item, $view, $noted);
        }
        return $items;
    }

    /**
     * The first pass for one item.
     *
     * @param array<string, mixed> $view
     * @param array{ancestors: list<int>, parentItems: list<int>, parentObjects: list<int>, object: string} $noted
     * @param array<int, int> $parents menu parent by item id
     */
    private static function own(object $item, array $view, array &$noted, array $parents): void
    {
        $type = (string) $item->type;
        $objectId = (int) $item->object_id;
        $classes = array_values(array_filter(array_map('strval', (array) ($item->classes ?? []))));
        array_push($classes, 'menu-item', 'menu-item-type-' . $type, 'menu-item-object-' . $item->object);
        foreach (['frontPageId' => 'menu-item-home', 'privacyPageId' => 'menu-item-privacy-policy'] as $page => $class) {
            if ($type === 'post_type' && $view[$page] > 0 && $objectId === $view[$page]) {
                $classes[] = $class;
            }
        }
        $item->current = false;
        $home = $type === 'custom' && Url::withoutTrailingSlash((string) $item->url) === $view['homeUrl'];
        if ($view['singular'] && $type === 'taxonomy' && in_array($objectId, $view['objectParents'], true)) {
            $noted['parentObjects'][] = $objectId;
            $noted['parentItems'][] = (int) $item->db_id;
            $noted['object'] = $view['postType'];
        } elseif (self::isCurrent($item, $view)) {
            $item->current = true;
            $classes[] = 'current-menu-item';
            for ($up = (int) $item->menu_item_parent; $up > 0 && !in_array($up, $noted['ancestors'], true); $up = $parents[$up] ?? 0) {
                $noted['ancestors'][] = $up;
            }
            if ($type === 'post_type' && (string) $item->object === 'page') {
                array_push($classes, 'page_item', 'page-item-' . $objectId, 'current_page_item');
            } elseif ($home) {
                $classes[] = 'current_page_item';
            }
            $noted['parentItems'][] = (int) $item->menu_item_parent;
            $noted['parentObjects'][] = (int) $item->post_parent;
            $noted['object'] = (string) $item->object;
        }
        if ($home) {
            $classes[] = 'menu-item-home';
        }
        if ($type === 'post_type' && $view['postsPageId'] > 0 && $objectId === $view['postsPageId'] && !$view['page'] && !$item->current) {
            $classes[] = 'current_page_parent';
        }
        $item->classes = $classes;
    }

    /**
     * The second pass for one item.
     *
     * @param array<string, mixed> $view
     * @param array{ancestors: list<int>, parentItems: list<int>, parentObjects: list<int>, object: string} $noted
     */
    private static function related(object $item, array $view, array $noted): void
    {
        $type = (string) $item->type;
        $objectId = (int) $item->object_id;
        $classes = (array) $item->classes;
        if (($type === 'post_type' && in_array($objectId, $view['postAncestors'], true))
            || ($type === 'taxonomy' && in_array($objectId, $view['termAncestors'][(string) $item->object] ?? [], true) && $objectId !== $view['termId'])) {
            $classes[] = 'current-' . $view['ancestorType'] . '-ancestor';
        }
        $item->current_item_ancestor = in_array((int) $item->db_id, $noted['ancestors'], true);
        $item->current_item_parent = in_array((int) $item->db_id, $noted['parentItems'], true);
        if ($item->current_item_ancestor) {
            $classes[] = 'current-menu-ancestor';
        }
        if ($item->current_item_parent) {
            $classes[] = 'current-menu-parent';
        }
        if (in_array($objectId, $noted['parentObjects'], true)) {
            $classes[] = 'current-' . $noted['object'] . '-parent';
        }
        if ($type === 'post_type' && (string) $item->object === 'page') {
            if ($item->current_item_parent) {
                $classes[] = 'current_page_parent';
            }
            if ($item->current_item_ancestor) {
                $classes[] = 'current_page_ancestor';
            }
        }
        $item->classes = array_values(array_unique($classes));
    }

    /** @param array<string, mixed> $view */
    private static function isCurrent(object $item, array $view): bool
    {
        $objectId = (int) $item->object_id;
        return match ((string) $item->type) {
            'post_type' => $objectId > 0 && $objectId === $view['queriedId'] && ($view['singular'] || ($view['blog'] && $objectId === $view['postsPageId'])),
            'taxonomy' => $view['termArchive'] && $objectId > 0 && $objectId === $view['queriedId'] && (string) $item->object === $view['taxonomy'],
            'post_type_archive' => in_array((string) $item->object, $view['archiveTypes'], true),
            // Exact request-URL equality only: the home item is not current on /page/2/ or ?s= views.
            'custom' => ($url = Url::withoutTrailingSlash((string) $item->url)) !== '' && $url === Url::withoutTrailingSlash(self::currentUrl()),
            default => false,
        };
    }

    /**
     * What the main query has in view, as the two passes read it.
     *
     * @return array<string, mixed>
     */
    private static function view(\WP_Query $query): array
    {
        $queried = $query->get_queried_object();
        $post = $queried instanceof \WP_Post ? $queried : null;
        $term = $queried instanceof \WP_Term ? $queried : null;
        $registry = Runtime::registry();
        $nested = $post !== null && !empty($registry->postType((string) $post->post_type)['hierarchical']);
        [$objectParents, $termAncestors] = $query->is_singular() && $post !== null && !$nested ? self::postTerms($post) : [[], []];
        if ($term !== null && !empty($registry->taxonomy((string) $term->taxonomy)['hierarchical'])) {
            $termAncestors[(string) $term->taxonomy] = [(int) $term->term_id, ...self::ancestors((int) $term->term_id, (string) $term->taxonomy, 'taxonomy')];
        }
        $options = Runtime::options();
        return [
            'queriedId' => (int) $query->get_queried_object_id(),
            'singular' => (bool) $query->is_singular(),
            'page' => (bool) $query->is_page(),
            'blog' => (bool) $query->is_home(),
            'termArchive' => $query->is_category() || $query->is_tag() || $query->is_tax(),
            'archiveTypes' => $query->is_post_type_archive() ? array_map('strval', (array) $query->get('post_type')) : [],
            'taxonomy' => (string) ($term->taxonomy ?? ''),
            'termId' => (int) ($term->term_id ?? 0),
            'postType' => (string) ($post->post_type ?? ''),
            'ancestorType' => (string) ($term->taxonomy ?? $post->post_type ?? ''),
            'objectParents' => $objectParents,
            'termAncestors' => $termAncestors,
            'postAncestors' => $nested ? self::ancestors((int) $post->ID, (string) $post->post_type, 'post_type') : [],
            'frontPageId' => (int) $options->filtered('page_on_front'),
            'postsPageId' => (int) $options->filtered('page_for_posts'),
            'privacyPageId' => (int) $options->filtered('wp_page_for_privacy_policy'),
            'homeUrl' => Url::withoutTrailingSlash((string) \home_url()),
        ];
    }

    /**
     * A post's terms in its hierarchical taxonomies (the items that read as
     * its menu parents) and, per taxonomy, those terms with their ancestors.
     *
     * @return array{list<int>, array<string, list<int>>}
     */
    private static function postTerms(\WP_Post $post): array
    {
        $terms = [];
        $ancestors = [];
        foreach ((array) \get_object_taxonomies((string) $post->post_type) as $taxonomy) {
            if (empty(Runtime::registry()->taxonomy((string) $taxonomy)['hierarchical'])) {
                continue;
            }
            $ids = \wp_get_object_terms([(int) $post->ID], [(string) $taxonomy], ['fields' => 'ids']);
            foreach (is_array($ids) ? $ids : [] as $id) {
                $terms[] = (int) $id;
                $ancestors[(string) $taxonomy] = [...$ancestors[(string) $taxonomy] ?? [], (int) $id, ...self::ancestors((int) $id, (string) $taxonomy, 'taxonomy')];
            }
        }
        return [$terms, $ancestors];
    }

    /** @return list<int> */
    private static function ancestors(int $id, string $type, string $kind): array
    {
        return array_map('intval', (array) \get_ancestors($id, $type, $kind));
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
