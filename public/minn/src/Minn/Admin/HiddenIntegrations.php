<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Capabilities;
use Minn\Content\Users;
use Minn\Support\Serialized;

/**
 * What a person hid from their own Minn Admin: the app's per-user map
 * (user meta minn_admin_hidden_integrations, "kind:id" => hidden-at) read
 * and written in the app's own shape so a choice made under WordPress
 * survives the swap and vice versa. The engine registers the core views
 * as hideable; plugin surfaces, editor panels, design sources, and slash
 * namespaces have no registry here, so hiding one is refused as
 * unregistered and a stored hide of one is simply not listed.
 */
final readonly class HiddenIntegrations
{
    public const META = 'minn_admin_hidden_integrations';

    /** Core view id => [label, the capability that shows the view]. */
    public const CORE = [
        'content' => ['Content', 'edit_posts'],
        'media' => ['Media', 'upload_files'],
        'comments' => ['Comments', 'moderate_comments'],
        'orders' => ['Orders', 'edit_shop_orders'],
        'subscriptions' => ['Subscriptions', 'edit_shop_orders'],
        'products' => ['Products', 'edit_products'],
        'coupons' => ['Coupons', 'edit_shop_coupons'],
        'customers' => ['Customers', 'list_users'],
        'users' => ['Users', 'list_users'],
        'terms' => ['Terms', 'manage_categories'],
        'menus' => ['Menus', 'edit_theme_options'],
        'widgets' => ['Widgets', 'edit_theme_options'],
        'posttypes' => ['Structure', 'manage_options'],
        'extensions' => ['Extensions', 'activate_plugins'],
        'database' => ['Database', 'manage_options'],
        'system' => ['System', 'manage_options'],
        'settings' => ['Settings', 'manage_options'],
    ];

    /** Newest hides kept when the map is capped. */
    private const CAP = 100;

    public function __construct(private Users $users, private Capabilities $capabilities)
    {
    }

    /** The id as the app sends it: lower-case, only word characters, colon, and dash. */
    public static function sanitize(string $id): string
    {
        return (string) preg_replace('/[^a-z0-9_:\-]/', '', strtolower($id));
    }

    /**
     * The views a user has hidden, by id.
     *
     * @return array<string, int> id => hidden-at
     */
    public function map(int $userId): array
    {
        $stored = Serialized::decode((string) ($this->users->meta($userId, self::META) ?? ''));
        $map = [];
        foreach (is_array($stored) ? $stored : [] as $id => $at) {
            $map[(string) $id] = (int) $at;
        }
        return $map;
    }

    /** The restore list: every hidden core view the person can still see. @return list<array{id: string, kind: string, label: string, sub: string}> */
    public function listFor(int $userId): array
    {
        $hidden = $this->map($userId);
        $out = [];
        foreach (self::CORE as $id => [$label, $cap]) {
            if (isset($hidden['core:' . $id]) && $this->capabilities->can($userId, $cap)) {
                $out[] = ['id' => 'core:' . $id, 'kind' => 'core', 'label' => $label, 'sub' => ''];
            }
        }
        return $out;
    }

    /** False when the id names nothing this person could hide. */
    public function hide(int $userId, string $id): bool
    {
        if (!preg_match('/^core:([a-z0-9_-]+)$/', $id, $m) || !isset(self::CORE[$m[1]]) || !$this->capabilities->can($userId, self::CORE[$m[1]][1])) {
            return false;
        }
        $map = $this->map($userId);
        $map[$id] = time();
        if (count($map) > self::CAP) {
            arsort($map);
            $map = array_slice($map, 0, self::CAP, true);
        }
        $this->users->setMeta($userId, self::META, Serialized::encode($map));
        return true;
    }

    /** Shows a hidden view again. */
    public function unhide(int $userId, string $id): void
    {
        $map = $this->map($userId);
        unset($map[$id]);
        if ($map === []) {
            $this->users->deleteMeta($userId, self::META);
            return;
        }
        $this->users->setMeta($userId, self::META, Serialized::encode($map));
    }
}
