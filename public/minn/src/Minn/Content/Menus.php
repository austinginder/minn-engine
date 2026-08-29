<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Blocks\Block;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Serialized;

/**
 * Classic nav_menu terms and nav_menu_item posts. The front uses these
 * when a navigation block has no inner blocks and no wp_navigation post;
 * REST serves the same rows as wp/v2/menus and menu-items.
 */
final readonly class Menus
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Terms $terms,
        private Permalinks $permalinks,
    ) {
    }

    /** @return list<array<string, mixed>> term rows keyed as Terms::row */
    public function all(): array
    {
        return $this->db->rows(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.description, tt.count, tt.parent
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'nav_menu' ORDER BY t.name ASC, t.term_id ASC",
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->terms->row($id, 'nav_menu');
    }

    /**
     * @return list<MenuItem>
     */
    public function items(?int $menuId = null): array
    {
        $sql = "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_status, p.menu_order, p.post_parent
                FROM {$this->db->table('posts')} p";
        $params = [];
        if ($menuId !== null) {
            $sql .= " JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID
                      JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                      WHERE p.post_type = 'nav_menu_item' AND p.post_status <> 'trash' AND tt.taxonomy = 'nav_menu' AND tt.term_id = ?";
            $params[] = $menuId;
        } else {
            $sql .= " WHERE p.post_type = 'nav_menu_item' AND p.post_status <> 'trash'";
        }
        $sql .= ' ORDER BY p.menu_order ASC, p.ID ASC';
        $rows = $this->db->rows($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $item = $this->hydrate($row, $menuId);
            if ($item !== null) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * Navigation-link blocks for the first classic menu. Empty when the
     * site has no nav_menu terms with items.
     *
     * @return list<Block>
     */
    public function fallbackBlocks(): array
    {
        $menus = $this->all();
        if ($menus === []) {
            return [];
        }
        return array_map(
            $this->toBlock(...),
            $this->items((int) $menus[0]['term_id']),
        );
    }

    public function toBlock(MenuItem $item): Block
    {
        $kind = match ($item->type) {
            'taxonomy' => 'taxonomy',
            'post_type' => 'post-type',
            default => 'custom',
        };
        return new Block('core/navigation-link', [
            'label' => $item->title,
            'url' => $item->url,
            'type' => $item->object,
            'kind' => $kind,
            'id' => $item->objectId,
            'opensInNewTab' => $item->target === '_blank',
            'fromMenu' => true,
            'menuType' => $item->type,
            'object' => $item->object,
            'attrTitle' => $item->attrTitle,
            'className' => ' menu-item menu-item-type-' . $item->type . ' menu-item-object-' . $item->object,
        ], [], '', []);
    }

    public function autoAdd(int $menuId): bool
    {
        $blob = $this->db->option('nav_menu_options');
        $data = Serialized::decode((string) $blob);
        if (!is_array($data) || !isset($data['auto_add']) || !is_array($data['auto_add'])) {
            return false;
        }
        foreach ($data['auto_add'] as $id) {
            if ((int) $id === $menuId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Location slugs from the active theme's theme_mods that point at this menu.
     *
     * @return list<string>
     */
    public function locationsFor(int $menuId): array
    {
        $out = [];
        foreach ($this->themeLocations() as $location => $id) {
            if ((int) $id === $menuId) {
                $out[] = (string) $location;
            }
        }
        return $out;
    }

    /**
     * @return array<string, int> location => menu term id
     */
    public function themeLocations(): array
    {
        $stylesheet = (string) ($this->db->option('stylesheet') ?? '');
        if ($stylesheet === '') {
            return [];
        }
        $mods = Serialized::decode((string) ($this->db->option('theme_mods_' . $stylesheet) ?? ''));
        if (!is_array($mods) || !isset($mods['nav_menu_locations']) || !is_array($mods['nav_menu_locations'])) {
            return [];
        }
        $out = [];
        foreach ($mods['nav_menu_locations'] as $location => $id) {
            $out[(string) $location] = (int) $id;
        }
        return $out;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row, ?int $knownMenuId): ?MenuItem
    {
        $id = (int) $row['ID'];
        $meta = $this->meta($id);
        $type = $meta['_menu_item_type'] ?? 'custom';
        $object = $meta['_menu_item_object'] ?? 'custom';
        $objectId = (int) ($meta['_menu_item_object_id'] ?? 0);
        $storedUrl = $meta['_menu_item_url'] ?? '';
        $title = trim((string) $row['post_title']);
        $url = $storedUrl;
        $invalid = false;
        if ($type === 'post_type') {
            $post = $this->posts->find($objectId);
            $invalid = $post === null || $post['post_status'] === 'trash';
            if ($post !== null) {
                $url = $this->permalinks->forPost($post);
                if ($title === '') {
                    $title = (string) $post['post_title'];
                }
            }
        } elseif ($type === 'taxonomy') {
            $term = $this->terms->find($object, $objectId);
            $invalid = $term === null;
            if ($term !== null) {
                $url = $this->permalinks->forTerm($term);
                if ($title === '') {
                    $title = (string) $term['name'];
                }
            }
        } else {
            $objectId = $objectId > 0 ? $objectId : $id;
            if ($title === '') {
                $title = $storedUrl;
            }
        }
        $menuId = $knownMenuId ?? $this->menuIdOf($id);
        if ($menuId === 0) {
            return null;
        }
        return new MenuItem(
            $id,
            $title,
            $url,
            $type,
            $object,
            $objectId,
            (int) ($meta['_menu_item_menu_item_parent'] ?? $row['post_parent'] ?? 0),
            (int) $row['menu_order'],
            $meta['_menu_item_target'] ?? '',
            $this->classList($meta['_menu_item_classes'] ?? null),
            $this->xfnList($meta['_menu_item_xfn'] ?? null),
            $meta['_menu_item_attr_title'] ?? '',
            trim((string) $row['post_content']),
            (string) $row['post_status'],
            $menuId,
            $invalid,
        );
    }

    /** @return array<string, string> */
    private function meta(int $postId): array
    {
        $rows = $this->db->rows(
            "SELECT meta_key, meta_value FROM {$this->db->table('postmeta')} WHERE post_id = ? AND meta_key LIKE '_menu_item%'",
            [$postId],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['meta_key']] = (string) $row['meta_value'];
        }
        return $out;
    }

    private function menuIdOf(int $itemId): int
    {
        $id = $this->db->value(
            "SELECT tt.term_id FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'nav_menu' LIMIT 1",
            [$itemId],
        );
        return (int) $id;
    }

    /** @return list<string> */
    private static function classList(?string $blob): array
    {
        if ($blob === null || $blob === '') {
            return [''];
        }
        $list = Serialized::stringList($blob);
        return $list === [] ? [''] : $list;
    }

    /** @return list<string> */
    private static function xfnList(?string $blob): array
    {
        if ($blob === null || $blob === '') {
            return [''];
        }
        if (str_starts_with($blob, 'a:')) {
            $list = Serialized::stringList($blob);
            return $list === [] ? [''] : $list;
        }
        return explode(' ', $blob);
    }
}
