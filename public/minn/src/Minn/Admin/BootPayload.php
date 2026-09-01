<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Authenticated;
use Minn\Auth\Capabilities;
use Minn\Auth\Nonce;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Runtime\Runtime;
use stdClass;

/**
 * The window.MINN boot payload, assembled from the engine: the keys app.js
 * needs to boot and drive the wp/v2 surface, with capabilities from the
 * engine's own model.
 */
final readonly class BootPayload
{
    /** Boot keys the plugin computes that point the app at wp-admin pages the engine does not serve. */
    private const PLUGIN_KEYS_WITHHELD = ['notices'];

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
        $plugin = $this->pluginPayload();
        // WooCommerce runs as code on the runtime; the Commerce views key off
        // these, and wc/v3 answers through the plugin's own REST controllers.
        $wc = is_bool($plugin['wc'] ?? null) ? $plugin['wc'] : (Runtime::booted() && class_exists('WooCommerce', false));

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
                // A template id is "<owner>//<slug>". Comparing the owner
                // against these two is how the app tells the theme's own
                // templates from ones a plugin contributed, so both must be
                // here or every customized template reads as a plugin's.
                'stylesheet' => (string) ($this->site->option('stylesheet') ?? ''),
                'template' => (string) ($this->site->option('template') ?? ''),
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
                // The commerce caps mirror the plugin's own conditions: WC loaded,
                // the WC capability, and for coupons the store having them enabled.
                'orders' => $wc && $can('edit_shop_orders'),
                'products' => $wc && $can('edit_products'),
                'coupons' => $wc && (!function_exists('wc_coupons_enabled') || \wc_coupons_enabled()) && $can('edit_shop_coupons'),
                'customers' => $wc && ($can('manage_woocommerce') || $can('edit_shop_orders')),
                'subscriptions' => $wc && class_exists('WC_Subscriptions', false) && $can('edit_shop_orders'),
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
            'wc' => $wc,
            'ajaxUrl' => $this->permalinks->url('/wp-admin/admin-ajax.php'),
            // No admin-ajax plugin toggles: the app falls back to PUT wp/v2/plugins.
            'pluginAjax' => null,
            'comments' => is_bool($plugin['comments'] ?? null) ? $plugin['comments'] : true,
            'hidden' => $this->hidden->listFor($userId),
            'pretty' => $this->permalinks->isPretty(),
        ] + ($plugin === null ? $this->adapterSlices() : array_diff_key($plugin, array_flip(self::PLUGIN_KEYS_WITHHELD)));
        return $payload;
    }

    /**
     * What Minn Admin's own boot_payload() computes when the plugin runs as
     * code on the runtime, so the app boots with the same keys on both
     * stacks (connectors, discussion defaults, roles, post formats, the
     * adapter flags). Engine-owned keys win; the withheld ones would send
     * the app to a wp-admin page the engine does not have.
     */
    private function pluginPayload(): ?array
    {
        if (!Runtime::booted() || !class_exists('Minn_Admin', false) || !method_exists('Minn_Admin', 'boot_payload')) {
            return null;
        }
        try {
            $this->standHomeQuery();
            $payload = \Minn_Admin::boot_payload();
            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            error_log('Minn Engine: Minn Admin boot_payload failed on the runtime: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * The reference serves the app shell from the home query, so the global
     * post is the newest post while the payload is built; the comments
     * feature check reads it through the comments_open filter (a site that
     * closes comments on old posts reports the feature off when its newest
     * post is old).
     */
    private function standHomeQuery(): void
    {
        if (!function_exists('_minn_run_main_query')) {
            return;
        }
        \_minn_run_main_query([], 1, 10);
        $query = $GLOBALS['wp_query'] ?? null;
        if (is_object($query) && isset($query->post) && $query->post !== null) {
            $GLOBALS['post'] = $query->post;
            Runtime::current()->set('post', $query->post);
        }
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
