<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Blocks\Block;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Runtime\Refusal;
use Minn\Support\Html;
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
        private ?PostWriter $writer = null,
        private ?Site $site = null,
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

    public function idByName(string $name): ?int
    {
        return $this->terms->idByName($name, 'nav_menu');
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
    private function hydrate(array $row, ?int $knownMenuId): MenuItem
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
            $invalid = $post === null || $post->isTrashed();
            if ($post !== null) {
                $url = $this->permalinks->forPost($post);
                if ($title === '') {
                    $title = $post->title;
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

    public function findItem(int $id): ?MenuItem
    {
        $row = $this->db->row(
            "SELECT ID, post_title, post_content, post_excerpt, post_status, menu_order, post_parent
             FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = 'nav_menu_item' AND post_status <> 'trash' LIMIT 1",
            [$id],
        );
        return $row === null ? null : $this->hydrate($row, null);
    }

    /**
     * Whether a name may be given to a menu. A menu keeps its own name; any
     * other menu holding it refuses the write, and the refused id rides along
     * so a caller can point at the menu in the way.
     */
    public function refuseName(string $name, int $keeping = 0): ?Refusal
    {
        if ($name === '') {
            return new Refusal('empty_term_name', 'A name is required for this term.');
        }
        $existing = $this->idByName($name);
        if ($existing === null || $existing === $keeping) {
            return null;
        }
        return new Refusal(
            'menu_exists',
            'The menu name <strong>' . Html::esc($name) . '</strong> conflicts with another menu name. Please try another.',
            $existing,
        );
    }

    public function createMenu(string $name, string $description = ''): int
    {
        $slug = $this->terms->uniqueSlug($name, 'nav_menu');
        return $this->terms->create($name, $slug, 'nav_menu', $description, 0);
    }

    public function updateMenu(int $id, ?string $name, ?string $description): void
    {
        $row = $this->find($id);
        if ($row === null) {
            return;
        }
        if ($name !== null) {
            $slug = $this->terms->uniqueSlug($name, 'nav_menu', $id);
            $this->terms->rename($id, $name, $slug);
        }
        if ($description !== null) {
            $this->terms->describe($id, 'nav_menu', $description, 0);
        }
    }

    public function deleteMenu(int $id): void
    {
        foreach ($this->items($id) as $item) {
            $this->deleteItem($item->id);
        }
        $row = $this->find($id);
        if ($row !== null) {
            $this->terms->delete($row + ['taxonomy' => 'nav_menu'], false);
        }
    }

    /**
     * @param array{
     *   title: string,
     *   url: string,
     *   type: string,
     *   object: string,
     *   objectId: int,
     *   parent: int,
     *   menuOrder: int,
     *   target: string,
     *   status: string,
     *   menuId: int,
     *   attrTitle: string,
     *   description: string,
     *   authorId: int
     * } $fields
     */
    public function createItem(array $fields): int
    {
        $writer = $this->writer();
        $site = $this->site();
        $now = $site->localNow();
        $gmt = gmdate('Y-m-d H:i:s');
        $slug = $writer->uniqueSlug($fields['title'] !== '' ? $fields['title'] : 'menu-item', 0);
        $id = $writer->insert([
            'post_author' => $fields['authorId'],
            'post_date' => $now,
            'post_date_gmt' => $gmt,
            'post_content' => $fields['description'],
            'post_title' => $fields['title'],
            'post_excerpt' => '',
            'post_status' => $fields['status'],
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $slug,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $gmt,
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => '',
            'menu_order' => $fields['menuOrder'],
            'post_type' => 'nav_menu_item',
            'post_mime_type' => '',
            'comment_count' => 0,
        ]);
        $home = rtrim((string) ($site->option('home') ?? ''), '/');
        $writer->update($id, ['guid' => $home . '/' . $slug . '/']);
        $objectId = $fields['type'] === 'custom' ? $id : $fields['objectId'];
        $this->writeMeta($id, $fields['type'], $fields['object'], $objectId, $fields['parent'], $fields['url'], $fields['target'], $fields['attrTitle']);
        if ($fields['menuId'] > 0) {
            $writer->setTerms($id, 'nav_menu', [$fields['menuId']]);
        } else {
            $writer->setMeta($id, '_menu_item_orphaned', (string) time());
        }
        return $id;
    }

    /** @param array<string, mixed> $fields */
    public function updateItem(int $id, array $fields): void
    {
        $writer = $this->writer();
        $site = $this->site();
        $item = $this->findItem($id);
        if ($item === null) {
            return;
        }
        $columns = [
            'post_modified' => $site->localNow(),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
        ];
        if (isset($fields['title'])) {
            $columns['post_title'] = (string) $fields['title'];
        }
        if (isset($fields['description'])) {
            $columns['post_content'] = (string) $fields['description'];
        }
        if (isset($fields['status'])) {
            $columns['post_status'] = (string) $fields['status'];
        }
        if (isset($fields['menuOrder'])) {
            $columns['menu_order'] = (int) $fields['menuOrder'];
        }
        $writer->update($id, $columns);
        $type = (string) ($fields['type'] ?? $item->type);
        $object = (string) ($fields['object'] ?? $item->object);
        $objectId = (int) ($fields['objectId'] ?? $item->objectId);
        if ($type === 'custom') {
            $objectId = $id;
        }
        $parent = (int) ($fields['parent'] ?? $item->parent);
        $url = (string) ($fields['url'] ?? ($type === 'custom' ? $item->url : ''));
        if ($type !== 'custom') {
            $url = (string) ($fields['url'] ?? '');
        }
        $target = (string) ($fields['target'] ?? $item->target);
        $attrTitle = (string) ($fields['attrTitle'] ?? $item->attrTitle);
        $this->writeMeta($id, $type, $object, $objectId, $parent, $url, $target, $attrTitle);
        if (isset($fields['menuId'])) {
            $menuId = (int) $fields['menuId'];
            if ($menuId > 0) {
                $writer->setTerms($id, 'nav_menu', [$menuId]);
            }
        }
    }

    public function deleteItem(int $id): void
    {
        $this->writer()->destroy($id);
    }

    private function writeMeta(int $id, string $type, string $object, int $objectId, int $parent, string $url, string $target, string $attrTitle): void
    {
        $writer = $this->writer();
        $writer->setMeta($id, '_menu_item_type', $type);
        $writer->setMeta($id, '_menu_item_object', $object);
        $writer->setMeta($id, '_menu_item_object_id', (string) $objectId);
        $writer->setMeta($id, '_menu_item_menu_item_parent', (string) $parent);
        $writer->setMeta($id, '_menu_item_url', $url);
        $writer->setMeta($id, '_menu_item_target', $target);
        $writer->setMeta($id, '_menu_item_attr_title', $attrTitle);
        $writer->setMeta($id, '_menu_item_classes', Serialized::serializeStringList(['']));
        $writer->setMeta($id, '_menu_item_xfn', '');
    }

    private function writer(): PostWriter
    {
        if ($this->writer === null) {
            throw new \RuntimeException('Menu writes need a PostWriter.');
        }
        return $this->writer;
    }

    private function site(): Site
    {
        if ($this->site === null) {
            throw new \RuntimeException('Menu writes need a Site.');
        }
        return $this->site;
    }
}
