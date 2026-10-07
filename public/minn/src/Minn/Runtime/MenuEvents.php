<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Menus;
use Minn\Content\Posts;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;
use Minn\RestError;
use Minn\I18n\Gettext;

/**
 * Menus and their items saved as the reference saves them, telling
 * plugins what it tells them (probe rest-menu-save). A menu is a nav_menu
 * term: made or changed through wp_insert_term / wp_update_term (the term
 * actions), then wp_create_nav_menu or wp_update_nav_menu; deleted, its
 * items go through wp_delete_post, the term through wp_delete_term, then
 * wp_delete_nav_menu. An item is written by the engine, with the post
 * save actions around it, then wp_add_nav_menu_item (a new one) and
 * wp_update_nav_menu_item, handed the reference's menu-item-* arguments.
 */
final readonly class MenuEvents
{
    /** The arguments an item is saved with, and their defaults, as the reference lists them. */
    private const ITEM_DEFAULTS = [
        'menu-item-db-id' => 0, 'menu-item-object-id' => 0, 'menu-item-object' => '', 'menu-item-parent-id' => 0, 'menu-item-position' => 0,
        'menu-item-type' => 'custom', 'menu-item-title' => '', 'menu-item-url' => '', 'menu-item-description' => '', 'menu-item-attr-title' => '',
        'menu-item-target' => '', 'menu-item-classes' => '', 'menu-item-xfn' => '', 'menu-item-status' => '', 'menu-item-post-date' => '', 'menu-item-post-date-gmt' => '',
    ];

    public function __construct(
        private Menus $menus,
        private Posts $posts,
    ) {
    }

    /**
     * wp_update_nav_menu_object: a menu made (menu id 0) or changed, its id
     * or the refusal. The name is checked first, as the reference checks it.
     *
     * @param array<string, mixed> $data menu-name, description
     */
    public function saveMenu(int $menuId, array $data): int|\WP_Error
    {
        $named = array_key_exists('menu-name', $data);
        $name = trim((string) ($data['menu-name'] ?? ''));
        if ($named || $menuId === 0) {
            $refusal = $this->menus->refuseName($name, $menuId);
            if ($refusal !== null) {
                return new \WP_Error($refusal->code, $refusal->message, $refusal->data);
            }
        }
        $args = array_filter(['name' => $named ? $name : null, 'description' => array_key_exists('description', $data) ? (string) $data['description'] : null], static fn ($v) => $v !== null);
        $created = $menuId === 0;
        $saved = $created ? \wp_insert_term($name, 'nav_menu', $args) : \wp_update_term($menuId, 'nav_menu', $args);
        if ($saved instanceof \WP_Error) {
            return $saved;
        }
        $menuId = (int) $saved['term_id'];
        \do_action($created ? 'wp_create_nav_menu' : 'wp_update_nav_menu', $menuId, $data);
        return $menuId;
    }

    /** wp_delete_nav_menu: the items through wp_delete_post, the term through wp_delete_term, the locations let go, then wp_delete_nav_menu. */
    public function deleteMenu(int $menuId): void
    {
        foreach ($this->menus->items($menuId) as $item) {
            \wp_delete_post($item->id, true);
        }
        \wp_delete_term($menuId, 'nav_menu');
        $locations = \get_nav_menu_locations();
        $kept = array_filter($locations, static fn ($assigned) => (int) $assigned !== $menuId);
        if (count($kept) !== count($locations)) {
            \set_theme_mod('nav_menu_locations', $kept);
        }
        \do_action('wp_delete_nav_menu', $menuId);
    }

    /**
     * wp_update_nav_menu_item: an item made (id 0) or changed in a menu, its
     * id or the refusal; the post save actions around the write, then the
     * item actions, and wp_after_insert_post "now" or (a REST save, which
     * fires it after its own actions) "later".
     *
     * @param array<string, mixed> $data menu-item-* arguments
     */
    public function saveItem(int $menuId, int $itemId, array $data, string $afterInsert = 'now'): int|\WP_Error
    {
        if ($menuId > 0 && $this->menus->find($menuId) === null) {
            return new \WP_Error('invalid_menu_id', Gettext::text('Invalid menu ID.'));
        }
        $before = $itemId > 0 ? $this->posts->find($itemId) : null;
        if ($itemId > 0 && ($before === null || $before->type !== 'nav_menu_item')) {
            return new \WP_Error('update_nav_menu_item_failed', Gettext::text('The given object ID is not that of a menu item.'));
        }
        $args = array_merge(self::ITEM_DEFAULTS, $data, ['menu-item-db-id' => $itemId]);
        if ($itemId === 0 && $menuId > 0 && (int) $args['menu-item-position'] === 0) {
            // A new item with no place goes after every item the menu has, as the reference places it.
            $args['menu-item-position'] = count($this->menus->items($menuId)) + 1;
        }
        $fields = self::fields($args, $menuId, (int) \get_current_user_id());
        $id = $itemId === 0 ? $this->menus->createItem($fields) : $itemId;
        if ($itemId > 0) {
            $this->menus->updateItem($itemId, $fields);
        }
        $events = new PostEvents();
        $events->saved($id, $before);
        if ($itemId === 0) {
            \do_action('wp_add_nav_menu_item', $menuId, $id, $args);
        }
        \do_action('wp_update_nav_menu_item', $menuId, $id, $args);
        if ($afterInsert === 'now') {
            $events->afterInsert($id, $before);
        }
        return $id;
    }

    /**
     * A menu saved over REST, as the reference's menus controller saves one:
     * the request's fields (name, description, those given) through
     * rest_pre_insert_nav_menu, then saveMenu(), then rest_insert_nav_menu
     * and rest_after_insert_nav_menu.
     *
     * @param array<string, mixed> $fields name, description
     */
    public function restMenu(int $menuId, array $fields, Request $request): int
    {
        $prepared = self::prepared(\apply_filters('rest_pre_insert_nav_menu', (object) $fields, RuntimeRoutes::wpRequest($request)));
        $data = array_filter(['menu-name' => $prepared['name'] ?? null, 'description' => $prepared['description'] ?? null], static fn ($v) => $v !== null);
        $saved = $this->saveMenu($menuId, $data);
        if ($saved instanceof \WP_Error) {
            throw new RestError((string) $saved->get_error_code(), (string) $saved->get_error_message(), 400);
        }
        (new TermEvents())->restSaved($saved, 'nav_menu', $request, $menuId === 0 ? 'create' : 'update');
        return $saved;
    }

    /** A menu deleted over REST: deleteMenu(), then rest_delete_nav_menu with the menu as it was and the response. @param array<string, mixed> $previous */
    public function restDeleteMenu(int $menuId, array $previous, Request $request): void
    {
        $term = \get_term($menuId, 'nav_menu');
        $this->deleteMenu($menuId);
        \do_action('rest_delete_nav_menu', $term, new \WP_REST_Response(['deleted' => true, 'previous' => $previous], 200), RuntimeRoutes::wpRequest($request));
    }

    /**
     * An item saved over REST, as the reference's menu-items controller saves
     * one: its menu-id and menu-item-* arguments through
     * rest_pre_insert_nav_menu_item, then saveItem(), then
     * rest_insert_nav_menu_item, rest_after_insert_nav_menu_item and
     * wp_after_insert_post.
     *
     * @param array<string, mixed> $args menu-id and menu-item-* arguments, the item's own filled in for an update
     */
    public function restItem(int $itemId, array $args, Request $request): int
    {
        $before = $itemId > 0 ? $this->posts->find($itemId) : null;
        $args = self::prepared(\apply_filters('rest_pre_insert_nav_menu_item', (object) $args, RuntimeRoutes::wpRequest($request)));
        $menuId = (int) ($args['menu-id'] ?? 0);
        unset($args['menu-id']);
        $saved = $this->saveItem($menuId, $itemId, $args, 'later');
        if ($saved instanceof \WP_Error) {
            throw new RestError((string) $saved->get_error_code(), (string) $saved->get_error_message(), 400);
        }
        $events = new PostEvents();
        $events->restInserted($saved, $request, $before);
        $events->restAfterInsert($saved, $request, $before);
        $events->afterInsert($saved, $before);
        return $saved;
    }

    /** What a rest_pre_insert filter handed back, as arguments; its refusal as the request's. @return array<string, mixed> */
    private static function prepared(mixed $prepared): array
    {
        if ($prepared instanceof \WP_Error) {
            $data = $prepared->get_error_data();
            throw new RestError((string) $prepared->get_error_code(), (string) $prepared->get_error_message(), (int) (is_array($data) ? ($data['status'] ?? 400) : 400));
        }
        return is_object($prepared) ? get_object_vars($prepared) : (array) $prepared;
    }

    /** An item deleted over REST: wp_delete_post, then rest_delete_nav_menu_item with the item as it was and the response. @param array<string, mixed> $previous */
    public function restDeleteItem(int $itemId, array $previous, Request $request): void
    {
        $post = $this->posts->find($itemId);
        \wp_delete_post($itemId, true);
        if ($post !== null) {
            (new PostEvents())->restDeleted($post, ['deleted' => true, 'previous' => $previous], $request);
        }
    }

    /**
     * An item's own saved values as menu-item-* arguments, which a REST
     * update starts from: the reference's save resets to its default any
     * argument it isn't handed.
     *
     * @return array<string, mixed>
     */
    public function savedArgs(int $itemId): array
    {
        $post = $this->posts->find($itemId);
        if ($post === null) {
            return [];
        }
        $meta = static fn (string $key): mixed => \get_post_meta($itemId, '_menu_item_' . $key, true);
        return [
            'menu-item-db-id' => $itemId, 'menu-item-object-id' => (string) $meta('object_id'), 'menu-item-object' => (string) $meta('object'), 'menu-item-parent-id' => (string) $meta('menu_item_parent'),
            'menu-item-position' => $post->menuOrder, 'menu-item-type' => (string) $meta('type'), 'menu-item-title' => $post->title, 'menu-item-url' => (string) $meta('url'),
            'menu-item-description' => $post->content, 'menu-item-attr-title' => (string) $meta('attr_title'), 'menu-item-target' => (string) $meta('target'),
            'menu-item-classes' => implode(' ', (array) $meta('classes')), 'menu-item-xfn' => implode(' ', (array) $meta('xfn')), 'menu-item-status' => $post->status,
        ];
    }

    /**
     * The menu-item-* arguments as the engine's item fields.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private static function fields(array $args, int $menuId, int $authorId): array
    {
        $status = (string) $args['menu-item-status'];
        return [
            'title' => (string) $args['menu-item-title'],
            'url' => (string) $args['menu-item-url'],
            'type' => (string) $args['menu-item-type'],
            'object' => (string) ($args['menu-item-object'] !== '' ? $args['menu-item-object'] : ($args['menu-item-type'] === 'custom' ? 'custom' : '')),
            'objectId' => (int) $args['menu-item-object-id'],
            'parent' => (int) $args['menu-item-parent-id'],
            'menuOrder' => (int) $args['menu-item-position'],
            'target' => (string) $args['menu-item-target'],
            'status' => $status === '' ? 'draft' : $status,
            'menuId' => $menuId,
            'attrTitle' => (string) $args['menu-item-attr-title'],
            'description' => (string) $args['menu-item-description'],
            'authorId' => $authorId,
            'classes' => explode(' ', is_array($args['menu-item-classes']) ? implode(' ', $args['menu-item-classes']) : (string) $args['menu-item-classes']),
            'xfn' => is_array($args['menu-item-xfn']) ? implode(' ', $args['menu-item-xfn']) : (string) $args['menu-item-xfn'],
        ];
    }
}
