<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\PostRecord;
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

    /** The capability engine over the shared database door. */
    public static function fromDb(Db $db): self
    {
        return new self($db, new Users($db), new Roles($db));
    }

    /** The role definitions. */
    public function roles(): Roles
    {
        return $this->roles;
    }

    /**
     * The role slugs a user holds.
     *
     * @return list<string> role slugs
     */
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

    /**
     * Every primitive capability a user holds through their roles.
     *
     * @return array<string, true> the union of primitives the user's roles grant
     */
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
        // A meta capability that maps to no primitive (edit_user on oneself) asks nothing further.
        return true;
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
            'assign_term' => ['edit_posts'],
            default => $this->mapMore($capability, $userId, $postId),
        };
    }

    /**
     * The rest of the reference's meta capabilities (captured with
     * map_meta_cap for an administrator, an editor and an author): each
     * names the primitive that decides it, refuses outright, or maps
     * through the object it is about. A name it does not know is its own
     * primitive.
     *
     * @return list<string>
     */
    private function mapMore(string $capability, int $userId, ?int $objectId): array
    {
        return match ($capability) {
            // Only a site that defines ALLOW_UNFILTERED_UPLOADS lets anyone upload any file type.
            'unfiltered_upload' => \defined('ALLOW_UNFILTERED_UPLOADS') && \constant('ALLOW_UNFILTERED_UPLOADS') ? ['unfiltered_upload'] : ['do_not_allow'],
            'upload_plugins' => ['install_plugins'],
            'upload_themes' => ['install_themes'],
            'update_languages' => ['install_languages'],
            'deactivate_plugins', 'activate_plugin', 'deactivate_plugin' => ['activate_plugins'],
            'resume_plugin' => ['resume_plugins'],
            'resume_theme' => ['resume_themes'],
            'delete_user' => ['delete_users'],
            'promote_user', 'add_users' => ['promote_users'],
            'remove_user' => ['remove_users'],
            'export_others_personal_data', 'erase_others_personal_data', 'manage_privacy_options', 'setup_network' => ['manage_options'],
            'customize' => ['edit_theme_options'],
            'update_https' => ['manage_options', 'update_core'],
            'delete_site', 'edit_block_binding' => ['do_not_allow'],
            'manage_links' => ($this->db->option('link_manager_enabled') ?? '0') === '1' ? ['manage_links'] : ['do_not_allow'],
            // One's own application passwords need nothing more; anyone else's, editing that user.
            'create_app_password', 'list_app_passwords', 'read_app_password', 'edit_app_password', 'delete_app_passwords', 'delete_app_password' => $objectId !== null && $objectId === $userId ? [] : ['edit_users'],
            'edit_comment' => $this->mapCommentCapability($userId, (int) $objectId),
            'edit_post_meta', 'delete_post_meta', 'add_post_meta' => $objectId === null ? ['do_not_allow'] : $this->mapPostCapability('edit_post', $userId, $objectId),
            default => [$capability],
        };
    }

    /** A comment is edited as its post is (as editing posts, when the post is gone); a comment that does not exist, by no one. @return list<string> */
    private function mapCommentCapability(int $userId, int $commentId): array
    {
        $postId = $this->db->value("SELECT comment_post_ID FROM {$this->db->table('comments')} WHERE comment_ID = ? LIMIT 1", [$commentId]);
        if ($postId === null) {
            return ['do_not_allow'];
        }
        $exists = $this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [(int) $postId]) !== null;
        return $exists ? $this->mapPostCapability('edit_post', $userId, (int) $postId) : ['edit_posts'];
    }

    /**
     * A post's meta capability as the reference maps it (probe post-caps):
     * read_post needs read for a published post or one's own, the private
     * read for a private one, and what editing needs for anything else
     * (future, draft, pending, trash). Editing or deleting one's own post
     * needs the published capability while it is published or scheduled (or
     * was, before the trash), the plain one otherwise; someone else's, the
     * others capability, with the published one while it is published or
     * scheduled and the private one while it is private. The privacy policy
     * page needs manage_options as well.
     *
     * @return list<string>
     */
    private function mapPostCapability(string $capability, int $userId, int $postId): array
    {
        $post = $this->db->row(
            "SELECT post_author, post_status, post_type FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1",
            [$postId],
        );
        if ($post === null) {
            return ['do_not_allow'];
        }
        $record = PostRecord::fromRow($post + ['ID' => $postId]);
        $isAuthor = $record->authorId > 0 && $record->authorId === $userId;
        $plural = TypeCapabilities::plural($record->type);
        if ($capability === 'read_post') {
            if ($record->status === 'publish' || $isAuthor) {
                return ['read'];
            }
            if ($record->status === 'private') {
                return [TypeCapabilities::of($record->type, "read_private_{$plural}")];
            }
            $capability = 'edit_post';
        }
        $verb = $capability === 'edit_post' ? 'edit' : 'delete';
        $live = ['publish', 'future'];
        if ($isAuthor) {
            $was = $record->status === 'trash' ? $this->trashedFrom($postId) : $record->status;
            $required = [in_array($was, $live, true) ? "{$verb}_published_{$plural}" : "{$verb}_{$plural}"];
        } else {
            $required = ["{$verb}_others_{$plural}"];
            if (in_array($record->status, $live, true)) {
                $required[] = "{$verb}_published_{$plural}";
            } elseif ($record->status === 'private') {
                $required[] = "{$verb}_private_{$plural}";
            }
        }
        $required = self::fold($record->type, $required);
        // The privacy policy page is the privacy settings' too.
        return (int) ($this->db->option('wp_page_for_privacy_policy') ?? 0) === $postId ? [...$required, 'manage_options'] : $required;
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
