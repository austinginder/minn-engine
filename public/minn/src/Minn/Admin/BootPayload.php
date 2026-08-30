<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Authenticated;
use Minn\Auth\Capabilities;
use Minn\Auth\Nonce;
use Minn\Content\Posts;
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
        private HiddenIntegrations $hidden,
        private Posts $posts,
        private bool $blockTheme = false,
        private ?Translations $translations = null,
    ) {
    }

    public function siteName(): string
    {
        return (string) ($this->site->option('blogname') ?? 'Site');
    }

    public function build(Authenticated $session): array
    {
        $user = $session->user;
        $userId = $session->id();
        $roles = $this->capabilities->rolesOf($userId);
        $role = $roles === [] ? '' : ($this->capabilities->roles()->all()[$roles[0]]['name'] ?? $roles[0]);
        $can = fn (string $capability): bool => $this->capabilities->can($userId, $capability);
        $locale = $this->translations?->localeOf($userId) ?? ($this->site->option('WPLANG') ?: 'en_US');
        [$i18n, $plural] = $this->translations?->catalog($locale) ?? [[], ''];

        $payload = [
            // The pretty REST base: the client appends "wp/v2/posts?context=edit&…",
            // so the route must live in the path or the first "?" folds the
            // query into a rest_route value.
            'restUrl' => $this->permalinks->url('/wp-json/'),
            'nonce' => Nonce::create($userId, $session->token),
            'appUrl' => $this->permalinks->url('/minn-admin/'),
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
                'icon' => $this->siteIcon(),
                'url' => $this->permalinks->url('/'),
                'adminUrl' => $this->permalinks->url('/minn-admin/'),
                'logout' => $this->permalinks->url('/minn-admin/login/logout'),
                'blockTheme' => $this->blockTheme,
                'hasSidebars' => false,
            ],
            'gmtOffset' => (float) ($this->site->option('gmt_offset') ?? 0),
            'locale' => $locale,
            'rtl' => Translations::isRtl($locale),
            'i18n' => $i18n === [] ? new stdClass() : $i18n,
            'i18nPlural' => $plural,
            'languages' => $this->translations?->installed() ?? [['', 'Site default'], ['en_US', 'English (United States)']],
            'caps' => [
                'plugins' => $can('activate_plugins'),
                'update' => $can('update_plugins'),
                'delete' => $can('delete_plugins'),
                'install' => $can('install_plugins'),
                'themes' => $can('switch_themes'),
                'deleteThemes' => $can('delete_themes'),
                'updateThemes' => $can('update_themes'),
                'updateLanguages' => false,
                'installThemes' => $can('install_themes'),
                'licenses' => function_exists('minn_admin_licenses_can_manage') ? (bool) \minn_admin_licenses_can_manage() : false,
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
            'hidden' => $this->hidden->listFor($userId),
            'pretty' => $this->permalinks->isPretty(),
        ] + $this->adapterSlices();
        return $payload;
    }

    /** The site icon as the app shows it in the sidebar: the attachment file behind the site_icon option. */
    private function siteIcon(): string
    {
        $icon = (int) ($this->site->option('site_icon') ?? 0);
        $file = $icon > 0 ? $this->posts->meta($icon, '_wp_attached_file') : null;
        return $file === null ? '' : $this->permalinks->url('/wp-content/uploads/' . $file);
    }

    /**
     * What Minn Admin's own adapters contribute when the plugin runs as code
     * on the runtime: plugin surfaces (Tools), editor panels, design
     * sources, editor commands, block forms. Absent, the app's fallbacks apply.
     */
    private function adapterSlices(): array
    {
        if (!class_exists('Minn_Admin_Surfaces', false) || !class_exists('Minn_Admin', false)) {
            return [];
        }
        try {
            $rawForms = \apply_filters('minn_admin_block_forms', []);
            return [
                'surfaces' => \Minn_Admin_Surfaces::for_current_user(),
                'editorPanels' => \Minn_Admin_Surfaces::editor_panels_for_current_user(),
                'designs' => \Minn_Admin::design_sources(),
                'editorCommands' => \Minn_Admin::editor_commands(),
                'blockForms' => \Minn_Admin::filter_block_forms($rawForms),
                'insertBlocks' => \Minn_Admin::insertable_blocks($rawForms),
            ];
        } catch (\Throwable $e) {
            error_log('Minn Engine: Minn Admin adapters failed while building the boot payload: ' . $e->getMessage());
            return [];
        }
    }
}
