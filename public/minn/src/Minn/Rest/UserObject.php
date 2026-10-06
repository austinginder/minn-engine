<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Runtime\Runtime;

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


    /** A user as the view context shows one, through rest_prepare_user when a plugin hooks it. */
    public function view(UserRecord $u): array
    {
        return RuntimePrepare::item('rest_prepare_user', $this->viewFields($u), static fn () => \get_userdata($u->id));
    }

    /** A user as the edit context shows one, through rest_prepare_user when a plugin hooks it. */
    public function edit(UserRecord $u): array
    {
        return RuntimePrepare::item('rest_prepare_user', $this->editFields($u), static fn () => \get_userdata($u->id));
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

    /** The wp/v2 user shape in the view context. */
    private function viewFields(UserRecord $u): array
    {
        $id = $u->id;
        $isSelf = $this->caller->id() === $id;
        return [
            'id' => $id,
            'name' => $u->displayName,
            'url' => $u->url,
            'description' => $this->users->meta($id, 'description') ?? '',
            'link' => $this->permalinks->forAuthor($u),
            'slug' => $u->nicename,
            'avatar_urls' => self::avatarUrls($u->email),
            'meta' => Runtime::booted() ? RestMeta::read('user', $id, 'user', 'view') : ['show_admin_bar_front' => $this->users->meta($id, 'show_admin_bar_front') ?? 'true'],
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
     * capabilities map is everything the user holds (allcaps), the extra
     * ones what is stored on the user, exactly as the reference emits.
     */
    private function editFields(UserRecord $u): array
    {
        $id = $u->id;
        $view = $this->viewFields($u);
        $view['_links']['self'][0]['targetHints']['allow'] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $capabilities = $this->caller->capabilities();
        $roles = $capabilities->rolesOf($id);
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
            'capabilities' => (object) $capabilities->allcapsOf($id),
            'extra_capabilities' => (object) $capabilities->capsOf($id),
            'avatar_urls' => $view['avatar_urls'],
            'meta' => Runtime::booted() ? RestMeta::read('user', $id, 'user', 'edit') : [
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
