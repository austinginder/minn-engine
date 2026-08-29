<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Authenticated;
use Minn\Auth\Capabilities;
use Minn\Auth\Nonce;
use Minn\Content\Site;
use Minn\Front\Permalinks;
use stdClass;

/**
 * The window.MINN boot payload, assembled from the engine: the keys app.js
 * needs to boot and drive the wp/v2 surface, with capabilities from the
 * engine's own model.
 */
final readonly class BootPayload
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Capabilities $capabilities,
        private App $app,
        private string $engineVersion,
        private Appearance $appearance,
        private bool $blockTheme = false,
    ) {
    }

    public function build(Authenticated $session): array
    {
        $user = $session->user;
        $userId = $session->id();
        $roles = $this->capabilities->rolesOf($userId);
        $role = $roles === [] ? '' : ($this->capabilities->roles()->all()[$roles[0]]['name'] ?? $roles[0]);
        $can = fn (string $capability): bool => $this->capabilities->can($userId, $capability);

        return [
            // The pretty REST base: the client appends "wp/v2/posts?context=edit&…",
            // so the route must live in the path or the first "?" folds the
            // query into a rest_route value.
            'restUrl' => $this->permalinks->url('/wp-json/'),
            'nonce' => Nonce::create($userId, $session->token),
            'appUrl' => $this->permalinks->url('/minn-admin'),
            'version' => $this->app->version(),
            'engine' => 'Minn Engine/' . $this->engineVersion,
            'user' => [
                'id' => $userId,
                'login' => $user['user_login'],
                'name' => $user['display_name'],
                'role' => $role,
                'avatar' => 'https://secure.gravatar.com/avatar/' . hash('sha256', strtolower(trim((string) $user['user_email']))) . '?s=64&d=mm&r=g',
                'appearance' => $this->appearance->read($userId),
                'policy' => ['signin' => 'minn', 'toolbar' => 'minn'],
            ],
            'site' => [
                'name' => $this->site->option('blogname') ?? 'Site',
                'icon' => '',
                'url' => $this->permalinks->url('/'),
                'adminUrl' => $this->permalinks->url('/minn-admin/'),
                'logout' => $this->permalinks->url('/wp-login.php?action=logout'),
                'blockTheme' => $this->blockTheme,
                'hasSidebars' => false,
            ],
            'gmtOffset' => (float) ($this->site->option('gmt_offset') ?? 0),
            'locale' => $this->site->option('WPLANG') ?: 'en_US',
            'rtl' => false,
            'i18n' => new stdClass(),
            'i18nPlural' => 'nplurals=2; plural=(n != 1);',
            'languages' => [],
            'caps' => [
                'plugins' => $can('activate_plugins'),
                'update' => $can('update_plugins'),
                'delete' => $can('delete_plugins'),
                'install' => false,
                'themes' => $can('switch_themes'),
                'deleteThemes' => $can('delete_themes'),
                'updateThemes' => $can('update_themes'),
                'updateLanguages' => false,
                'installThemes' => false,
                'licenses' => false,
                'deleteUsers' => $can('delete_users'),
                'removeUsers' => false,
                'networkPlugins' => false,
                'networkThemes' => false,
                'orders' => false,
                'products' => false,
                'coupons' => false,
                'customers' => false,
                'subscriptions' => false,
                'settings' => $can('manage_options'),
                'moderate' => $can('moderate_comments'),
                'terms' => $can('manage_categories'),
                'upload' => $can('upload_files'),
                'users' => $can('list_users'),
                'readPrivate' => $can('read_private_posts'),
                'editPages' => $can('edit_pages'),
                'core' => $can('update_core'),
                'editUsers' => $can('edit_users'),
                'createUsers' => $can('create_users'),
                'promoteUsers' => $can('promote_users'),
                'themeOptions' => $can('edit_theme_options'),
                'editCss' => $can('edit_css'),
            ],
            'ownOnly' => [],
            'multisite' => false,
            'wc' => false,
            'ajaxUrl' => $this->permalinks->url('/wp-admin/admin-ajax.php'),
            // No admin-ajax plugin toggles: the app falls back to PUT wp/v2/plugins.
            'pluginAjax' => null,
            'comments' => true,
            'pretty' => $this->permalinks->isPretty(),
        ];
    }
}
