<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Permalinks;

/** The wp/v2 user objects: the public view shape and the edit-context shape. */
final readonly class UserObject
{
    public function __construct(
        private Db $db,
        private Users $users,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** Gravatar URLs in the sizes the reference emits (sha256 of the email). */
    public static function avatarUrls(string $email): array
    {
        $hash = hash('sha256', strtolower(trim($email)));
        $urls = [];
        foreach ([24, 48, 96] as $size) {
            $urls[(string) $size] = "https://secure.gravatar.com/avatar/{$hash}?s={$size}&d=mm&r=g";
        }
        return $urls;
    }

    public function view(UserRecord $u, bool $isSelf = false): array
    {
        $id = $u->id;
        return [
            'id' => $id,
            'name' => $u->displayName,
            'url' => $u->url,
            'description' => $this->users->meta($id, 'description') ?? '',
            'link' => $this->permalinks->forAuthor($u),
            'slug' => $u->nicename,
            'avatar_urls' => self::avatarUrls($u->email),
            'meta' => ['show_admin_bar_front' => $this->users->meta($id, 'show_admin_bar_front') ?? 'true'],
            '_links' => [
                'self' => [[
                    'href' => $this->url->to('/wp/v2/users/' . $id),
                    // A caller who may edit the record (their own, or any with edit_users) sees the write verbs.
                    'targetHints' => ['allow' => $isSelf || $this->caller->can('edit_user', $id) ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
                ]],
                'collection' => [['href' => $this->url->to('/wp/v2/users')]],
            ],
        ];
    }

    /**
     * The private fields a caller who can edit the user sees. The
     * capabilities map is the union of role primitives plus each role name
     * as a pseudo-capability, exactly as the reference emits.
     */
    public function edit(UserRecord $u): array
    {
        $id = $u->id;
        $view = $this->view($u, true);
        $capabilities = $this->caller->capabilities();
        $roles = $capabilities->rolesOf($id);
        $all = $capabilities->primitivesOf($id);
        foreach ($roles as $role) {
            $all[$role] = true;
        }
        $locale = $this->users->meta($id, 'locale');
        // Everyone who reaches an edit-context object may write it; DELETE needs delete_users.
        if (!$this->caller->can('delete_users')) {
            $view['_links']['self'][0]['targetHints']['allow'] = ['GET', 'POST', 'PUT', 'PATCH'];
        }
        return [
            'id' => $id,
            'username' => $u->login,
            'name' => $u->displayName,
            'first_name' => $this->users->meta($id, 'first_name') ?? '',
            'last_name' => $this->users->meta($id, 'last_name') ?? '',
            'email' => $u->email,
            'url' => $u->url,
            'description' => $this->users->meta($id, 'description') ?? '',
            'link' => $view['link'],
            'locale' => ($locale === null || $locale === '') ? ($this->db->option('WPLANG') ?: 'en_US') : $locale,
            'nickname' => $this->users->meta($id, 'nickname') ?? $u->login,
            'slug' => $u->nicename,
            'roles' => array_values($roles),
            'registered_date' => PostObject::date($u->registered) . '+00:00',
            'capabilities' => (object) $all,
            'extra_capabilities' => (object) array_fill_keys($roles, true),
            'avatar_urls' => $view['avatar_urls'],
            'meta' => [
                'persisted_preferences' => [],
                'show_admin_bar_front' => $this->users->meta($id, 'show_admin_bar_front') ?? 'true',
            ],
            '_links' => [
                'self' => $view['_links']['self'],
                'collection' => $view['_links']['collection'],
            ],
        ];
    }
}
