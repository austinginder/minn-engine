<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\Users;
use Minn\Db;

/**
 * The capability engine: a user's roles from {prefix}capabilities usermeta,
 * the primitives those roles grant, and the meta-capability mapping for
 * edit_post, delete_post, and read_post.
 */
final readonly class Capabilities
{
    public function __construct(
        private Db $db,
        private Users $users,
        private Roles $roles,
    ) {
    }

    public static function fromDb(Db $db): self
    {
        return new self($db, new Users($db), new Roles($db));
    }

    public function roles(): Roles
    {
        return $this->roles;
    }

    /** @return list<string> role slugs */
    public function rolesOf(int $userId): array
    {
        $blob = $this->users->meta($userId, $this->db->prefix() . 'capabilities');
        if ($blob === null || $blob === '') {
            return [];
        }
        // A role a plugin granted but never registered does not count, as on the reference.
        $registered = $this->roles->all();
        return preg_match_all('/s:\d+:"([^"]+)";b:1;/', $blob, $m)
            ? array_values(array_filter($m[1], static fn (string $role) => isset($registered[$role])))
            : [];
    }

    /** @return array<string, true> the union of primitives the user's roles grant */
    public function primitivesOf(int $userId): array
    {
        $all = $this->roles->all();
        $held = [];
        foreach ($this->rolesOf($userId) as $role) {
            foreach ($all[$role]['capabilities'] ?? [] as $cap => $granted) {
                if ($granted) {
                    $held[$cap] = true;
                }
            }
        }
        return $held;
    }

    /** Does the user hold every primitive the (possibly meta) capability maps to? */
    public function can(int $userId, string $capability, ?int $postId = null): bool
    {
        if ($userId === 0) {
            return false;
        }
        $required = $this->map($capability, $userId, $postId);
        $held = $this->primitivesOf($userId);
        foreach ($required as $primitive) {
            if ($primitive === 'do_not_allow' || empty($held[$primitive])) {
                return false;
            }
        }
        return $required !== [];
    }

    /**
     * The primitives a capability requires, all of which must be held.
     *
     * @return list<string>
     */
    public function map(string $capability, int $userId, ?int $postId = null): array
    {
        return match ($capability) {
            'edit_post', 'delete_post', 'read_post' => $postId === null
                ? ['do_not_allow']
                : $this->mapPostCapability($capability, $userId, $postId),
            'edit_page' => $this->mapPostCapability('edit_post', $userId, (int) $postId),
            'delete_page' => $this->mapPostCapability('delete_post', $userId, (int) $postId),
            'edit_categories', 'delete_categories', 'manage_post_tags', 'edit_post_tags', 'delete_post_tags' => ['manage_categories'],
            'assign_categories', 'assign_post_tags' => ['edit_posts'],
            'edit_term', 'delete_term' => ['manage_categories'],
            'edit_user' => $postId !== null && $postId === $userId ? [] : ['edit_users'],
            'edit_css' => ['unfiltered_html'],
            'update_languages' => ['update_core'],
            'assign_term' => ['edit_posts'],
            default => [$capability],
        };
    }

    /** @return list<string> */
    private function mapPostCapability(string $capability, int $userId, int $postId): array
    {
        $post = $this->db->row(
            "SELECT post_author, post_status, post_type FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1",
            [$postId],
        );
        if ($post === null) {
            return ['do_not_allow'];
        }
        $isAuthor = (int) $post['post_author'] === $userId;
        $status = (string) $post['post_status'];
        if ($status === 'trash') {
            // A trashed post keeps the rules of the status it was trashed from.
            $status = $this->trashedFrom($postId);
        }
        $type = (string) $post['post_type'];
        $plural = TypeCapabilities::plural($type);
        $published = in_array($status, ['publish', 'future', 'private'], true);

        if ($capability === 'read_post') {
            return $status === 'publish' || $isAuthor ? ['read'] : [TypeCapabilities::of($type, "read_private_{$plural}")];
        }

        $verb = $capability === 'edit_post' ? 'edit' : 'delete';
        $required = [];
        if ($isAuthor) {
            $required[] = "{$verb}_{$plural}";
            if ($published) {
                $required[] = "{$verb}_published_{$plural}";
            } elseif ($status === 'private') {
                $required[] = "{$verb}_private_{$plural}";
            }
            return self::fold($type, $required);
        }
        $required[] = "{$verb}_others_{$plural}";
        if ($published) {
            $required[] = "{$verb}_published_{$plural}";
        }
        if ($status === 'private') {
            $required[] = "{$verb}_private_{$plural}";
        }
        return self::fold($type, $required);
    }

    /**
     * @param list<string> $required
     * @return list<string>
     */
    private static function fold(string $type, array $required): array
    {
        return array_map(static fn (string $cap) => TypeCapabilities::of($type, $cap), $required);
    }

    private function trashedFrom(int $postId): string
    {
        $status = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('postmeta')} WHERE post_id = ? AND meta_key = '_wp_trash_meta_status' LIMIT 1",
            [$postId],
        );
        return is_string($status) && $status !== '' ? $status : 'draft';
    }
}
