<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\Runtime;
use Minn\I18n\Gettext;
use Minn\Support\Escape;

/**
 * The nodes WordPress puts on the toolbar itself, as the reference adds
 * them on the front end for whoever is signed in (probe admin-bar): the
 * account menu, the WordPress menu, the site menu with its appearance
 * group, the site editor and customizer links, updates, comments, new
 * content, the edit link for what the page shows, the shortlink, the
 * secondary groups, and the search box. Each is one admin_bar_menu
 * callback, so plugins can unhook any of them by name.
 */
final class ToolbarMenus
{
    /** WordPress's menus as add_menus hooks them: the callback, its priority. */
    public const MENUS = [
        ['wp_admin_bar_my_account_menu', 0],
        ['wp_admin_bar_my_account_item', 9991],
        ['wp_admin_bar_recovery_mode_menu', 9992],
        ['wp_admin_bar_search_menu', 9999],
        ['wp_admin_bar_sidebar_toggle', 0],
        ['wp_admin_bar_wp_menu', 10],
        ['wp_admin_bar_my_sites_menu', 20],
        ['wp_admin_bar_site_menu', 30],
        ['wp_admin_bar_edit_site_menu', 40],
        ['wp_admin_bar_customize_menu', 40],
        ['wp_admin_bar_updates_menu', 50],
        ['wp_admin_bar_command_palette_menu', 55],
        ['wp_admin_bar_comments_menu', 60],
        ['wp_admin_bar_new_content_menu', 70],
        ['wp_admin_bar_edit_menu', 80],
        ['wp_admin_bar_add_secondary_groups', 200],
    ];

    private const ICON = '<span class="ab-icon" aria-hidden="true"></span>';

    /** The profile link for the signed-in user, or false when they cannot read. */
    private static function profileUrl(int $userId): string|false
    {
        return \current_user_can('read') ? \get_edit_profile_url($userId) : false;
    }

    /** "Howdy" with the user's name and small avatar, at the right of the bar. */
    public static function myAccountItem(\WP_Admin_Bar $bar): void
    {
        $userId = \get_current_user_id();
        if (!$userId) {
            return;
        }
        $user = \wp_get_current_user();
        $profile = self::profileUrl($userId);
        $avatar = (string) \get_avatar($userId, 28);
        $bar->add_node([
            'id' => 'my-account',
            'parent' => 'top-secondary',
            'title' => sprintf(Gettext::text('Howdy, %s'), '<span class="display-name">' . $user->display_name . '</span>') . $avatar,
            'href' => $profile,
            'meta' => ['class' => $avatar === '' ? '' : 'with-avatar', 'menu_title' => sprintf(Gettext::text('Howdy, %s'), $user->display_name), 'tabindex' => $profile === false ? 0 : ''],
        ]);
    }

    /** The account menu: the user's card (larger avatar, name, login when it differs, the profile link) and Log Out. */
    public static function myAccountMenu(\WP_Admin_Bar $bar): void
    {
        $userId = \get_current_user_id();
        if (!$userId) {
            return;
        }
        $user = \wp_get_current_user();
        $profile = self::profileUrl($userId);
        $bar->add_group(['parent' => 'my-account', 'id' => 'user-actions']);
        $card = \get_avatar($userId, 64) . "<span class='display-name'>{$user->display_name}</span>"
            . ($user->display_name !== $user->user_login ? "<span class='username'>{$user->user_login}</span>" : '')
            . ($profile !== false ? "<span class='display-name edit-profile'>" . Gettext::text('Edit Profile') . '</span>' : '');
        $bar->add_node(['parent' => 'user-actions', 'id' => 'user-info', 'title' => $card, 'href' => $profile]);
        $bar->add_node(['parent' => 'user-actions', 'id' => 'logout', 'title' => Gettext::text('Log Out'), 'href' => \wp_logout_url()]);
    }

    /** The menu button wp-admin's narrow screens show; the front end has none. */
    public static function sidebarToggle(\WP_Admin_Bar $bar): void
    {
        if (Runtime::current()->isAdmin) {
            $bar->add_node(['id' => 'menu-toggle', 'title' => self::ICON . '<span class="screen-reader-text">' . Gettext::text('Menu') . '</span>', 'href' => '#']);
        }
    }

    /** The WordPress logo's menu: About and Get Involved for a reader, then the wordpress.org links beside them. */
    public static function wpMenu(\WP_Admin_Bar $bar): void
    {
        $reads = \current_user_can('read');
        $about = $reads ? \self_admin_url('about.php') : false;
        $bar->add_node([
            'id' => 'wp-logo',
            'title' => self::ICON . '<span class="screen-reader-text">' . Gettext::text('About WordPress') . '</span>',
            'href' => $about,
            'meta' => ['menu_title' => Gettext::text('About WordPress')] + ($about === false ? ['tabindex' => 0] : []),
        ]);
        if ($reads) {
            $bar->add_node(['parent' => 'wp-logo', 'id' => 'about', 'title' => Gettext::text('About WordPress'), 'href' => $about]);
            $bar->add_node(['parent' => 'wp-logo', 'id' => 'contribute', 'title' => Gettext::text('Get Involved'), 'href' => \self_admin_url('contribute.php')]);
        }
        $external = [
            'wporg' => [Gettext::text('WordPress.org'), Gettext::text('https://wordpress.org/')],
            'documentation' => [Gettext::text('Documentation'), Gettext::text('https://wordpress.org/documentation/')],
            'learn' => [Gettext::text('Learn WordPress'), \esc_url(Gettext::text('https://learn.wordpress.org/'))],
            'support-forums' => [Gettext::text('Support'), Gettext::text('https://wordpress.org/support/forums/')],
            'feedback' => [Gettext::text('Feedback'), Gettext::text('https://wordpress.org/support/forum/requests-and-feedback')],
        ];
        foreach ($external as $id => [$title, $href]) {
            $bar->add_node(['parent' => 'wp-logo-external', 'id' => $id, 'title' => $title, 'href' => $href]);
        }
    }

    /** The site's name (with its icon when it has one), then the dashboard, appearance and plugins below it. */
    public static function siteMenu(\WP_Admin_Bar $bar): void
    {
        if (!\is_user_logged_in() || (!\is_user_member_of_blog() && !\current_user_can('manage_network'))) {
            return;
        }
        $name = (string) \get_bloginfo('name');
        $name = $name !== '' ? $name : (string) preg_replace('#^(https?://)?(www\.)?#', '', \get_home_url());
        $title = \wp_html_excerpt($name, 40, '&hellip;');
        $meta = ['menu_title' => $title];
        if (\apply_filters('wp_admin_bar_show_site_icons', true) && \has_site_icon()) {
            $small = (string) \get_site_icon_url(32);
            $large = (string) \get_site_icon_url(64);
            $title = '<img class="site-icon" src="' . \esc_url($small) . '"' . ($large !== $small ? ' srcset="' . \esc_url($large) . ' 2x"' : '') . ' alt="" width="20" height="20" />' . $title;
            $meta['class'] = 'has-site-icon';
        }
        $bar->add_node(['id' => 'site-name', 'title' => $title, 'href' => Runtime::current()->isAdmin || !\current_user_can('read') ? \home_url('/') : \admin_url(), 'meta' => $meta]);
        if (Runtime::current()->isAdmin) {
            $bar->add_node(['parent' => 'site-name', 'id' => 'view-site', 'title' => Gettext::text('Visit Site'), 'href' => \home_url('/')]);
            return;
        }
        if (\current_user_can('read')) {
            $bar->add_node(['parent' => 'site-name', 'id' => 'dashboard', 'title' => Gettext::text('Dashboard'), 'href' => \admin_url()]);
        }
        \wp_admin_bar_appearance_menu($bar);
        if (\current_user_can('activate_plugins')) {
            $bar->add_node(['parent' => 'site-name', 'id' => 'plugins', 'title' => Gettext::text('Plugins'), 'href' => \admin_url('plugins.php')]);
        }
    }

    /** The appearance group under the site's name: themes, then what the theme supports for those who may edit it. */
    public static function appearanceMenu(\WP_Admin_Bar $bar): void
    {
        $bar->add_group(['parent' => 'site-name', 'id' => 'appearance']);
        if (\current_user_can('switch_themes')) {
            $bar->add_node(['parent' => 'appearance', 'id' => 'themes', 'title' => Gettext::text('Themes'), 'href' => \admin_url('themes.php')]);
        }
        if (!\current_user_can('edit_theme_options')) {
            return;
        }
        $widgets = \current_theme_supports('widgets');
        $links = [
            'widgets' => [$widgets, Gettext::text('Widgets'), 'widgets.php', []],
            'menus' => [$widgets || \current_theme_supports('menus'), Gettext::text('Menus'), 'nav-menus.php', []],
            'background' => [\current_theme_supports('custom-background'), Gettext::text('Background'), 'themes.php?page=custom-background', ['class' => 'hide-if-customize']],
            'header' => [\current_theme_supports('custom-header'), Gettext::text('Header'), 'themes.php?page=custom-header', ['class' => 'hide-if-customize']],
        ];
        foreach ($links as $id => [$supported, $title, $page, $meta]) {
            if ($supported) {
                $bar->add_node(['parent' => 'appearance', 'id' => $id, 'title' => $title, 'href' => \admin_url($page)] + ($meta === [] ? [] : ['meta' => $meta]));
            }
        }
    }

    /** Edit Site under a block theme, opening the template this page was built from when there is one. */
    public static function editSiteMenu(\WP_Admin_Bar $bar): void
    {
        if (!(bool) Runtime::current()->get('block_theme', true) || !\current_user_can('edit_theme_options') || Runtime::current()->isAdmin) {
            return;
        }
        $template = $GLOBALS['_wp_current_template_id'] ?? null;
        $bar->add_node([
            'id' => 'site-editor',
            'title' => Gettext::text('Edit Site'),
            'href' => \add_query_arg(['postType' => 'wp_template', 'postId' => $template ?: null, 'canvas' => 'edit'], \admin_url('site-editor.php')),
        ]);
    }

    /** Customize, for a theme the customizer serves (a block theme only once something registers with it). */
    public static function customizeMenu(\WP_Admin_Bar $bar): void
    {
        if (!\current_user_can('customize') || Runtime::current()->isAdmin || ((bool) Runtime::current()->get('block_theme', true) && !Runtime::hooks()->has('customize_register'))) {
            return;
        }
        $request = Runtime::current()->request;
        $here = (\is_ssl() ? 'https://' : 'http://') . ($request === null ? '' : $request->host . $request->path . $request->queryStringWithout());
        $bar->add_node(['id' => 'customize', 'title' => Gettext::text('Customize'), 'href' => \add_query_arg('url', urlencode($here), \wp_customize_url()), 'meta' => ['class' => 'hide-if-no-customize']]);
        Runtime::hooks()->add('wp_before_admin_bar_render', 'wp_customize_support_script');
    }

    /** The count of updates waiting, when there are any. */
    public static function updatesMenu(\WP_Admin_Bar $bar): void
    {
        $total = (int) (\wp_get_update_data()['counts']['total'] ?? 0);
        if (!$total) {
            return;
        }
        $count = \number_format_i18n($total);
        $title = self::ICON . '<span class="ab-label" aria-hidden="true">' . $count . '</span>'
            . '<span class="screen-reader-text updates-available-text">' . sprintf(Gettext::plural('%s update available', '%s updates available', $total), $count) . '</span>';
        $bar->add_node(['id' => 'updates', 'title' => $title, 'href' => \network_admin_url('update-core.php')]);
    }

    /** The comments awaiting moderation, for those who edit posts. */
    public static function commentsMenu(\WP_Admin_Bar $bar): void
    {
        if (!\current_user_can('edit_posts')) {
            return;
        }
        $waiting = (int) \wp_count_comments()->moderated;
        $count = \number_format_i18n($waiting);
        $title = self::ICON . '<span class="ab-label awaiting-mod pending-count count-' . $waiting . '" aria-hidden="true">' . $count . '</span>'
            . '<span class="screen-reader-text comments-in-moderation-text">' . sprintf(Gettext::plural('%s Comment in moderation', '%s Comments in moderation', $waiting), $count) . '</span>';
        $bar->add_node(['id' => 'comments', 'title' => $title, 'href' => \admin_url('edit-comments.php')]);
    }

    /** New: a post, media, a link, a page, each other type shown on the bar, and a user, as far as the user may create them. */
    public static function newContentMenu(\WP_Admin_Bar $bar): void
    {
        $actions = self::creatable();
        if ($actions === []) {
            return;
        }
        $bar->add_node([
            'id' => 'new-content',
            'title' => self::ICON . '<span class="ab-label">' . Gettext::inContext('New', 'admin bar menu group label') . '</span>',
            'href' => \admin_url((string) array_key_first($actions)),
            'meta' => ['menu_title' => Gettext::inContext('New', 'admin bar menu group label')],
        ]);
        foreach ($actions as $page => [$title, $id]) {
            $bar->add_node(['parent' => 'new-content', 'id' => $id, 'title' => $title, 'href' => \admin_url($page)]);
        }
    }

    /** @return array<string, array{0: string, 1: string}> the admin page => [the label, the node id], for what the user may create */
    private static function creatable(): array
    {
        $types = (array) \get_post_types(['show_in_admin_bar' => true], 'objects');
        $can = static fn (string $name): bool => isset($types[$name]) && \current_user_can($types[$name]->cap->create_posts);
        $actions = [];
        if ($can('post')) {
            $actions['post-new.php'] = [$types['post']->labels->name_admin_bar, 'new-post'];
        }
        if (isset($types['attachment']) && \current_user_can('upload_files')) {
            $actions['media-new.php'] = [$types['attachment']->labels->name_admin_bar, 'new-media'];
        }
        if (\current_user_can('manage_links')) {
            $actions['link-add.php'] = [Gettext::inContext('Link', 'add new from admin bar'), 'new-link'];
        }
        if ($can('page')) {
            $actions['post-new.php?post_type=page'] = [$types['page']->labels->name_admin_bar, 'new-page'];
        }
        foreach (array_diff_key($types, array_flip(['post', 'attachment', 'page'])) as $name => $type) {
            if (\current_user_can($type->cap->create_posts)) {
                $actions['post-new.php?post_type=' . $name] = [$type->labels->name_admin_bar, $name === 'user' ? 'new-user-content' : 'new-' . $name];
            }
        }
        if (\current_user_can('create_users') || (\is_multisite() && \current_user_can('promote_users'))) {
            $actions['user-new.php'] = [Gettext::inContext('User', 'add new from admin bar'), 'new-user'];
        }
        return $actions;
    }

    /** Edit, for the post, term or user the page shows, when the user may edit it. */
    public static function editMenu(\WP_Admin_Bar $bar): void
    {
        $shown = Runtime::current()->isAdmin ? null : ($GLOBALS['wp_the_query'] ?? null)?->get_queried_object();
        if (empty($shown)) {
            return;
        }
        [$title, $href] = match (true) {
            !empty($shown->post_type) => self::editPost($shown),
            !empty($shown->taxonomy) => self::editTerm($shown),
            $shown instanceof \WP_User && \current_user_can('edit_user', $shown->ID) => [Gettext::text('Edit User'), \get_edit_user_link($shown->ID)],
            default => [null, null],
        };
        if ($title !== null && $href) {
            $bar->add_node(['id' => 'edit', 'title' => $title, 'href' => $href]);
        }
    }

    /** @return array{0: ?string, 1: ?string} the edit label and link for a post, or nulls */
    private static function editPost(object $post): array
    {
        $type = \get_post_type_object($post->post_type);
        if (!$type || !$type->show_in_admin_bar || !\current_user_can('edit_post', $post->ID)) {
            return [null, null];
        }
        return [$type->labels->edit_item, \get_edit_post_link($post->ID)];
    }

    /** @return array{0: ?string, 1: ?string} the edit label and link for a term, or nulls */
    private static function editTerm(object $term): array
    {
        $taxonomy = \get_taxonomy($term->taxonomy);
        if (!$taxonomy || !\current_user_can('edit_term', $term->term_id)) {
            return [null, null];
        }
        return [$taxonomy->labels->edit_item, \get_edit_term_link($term)];
    }

    /** The page's shortlink, with a box to copy it from. */
    public static function shortlinkMenu(\WP_Admin_Bar $bar): void
    {
        $short = \wp_get_shortlink(0, 'query');
        if (empty($short)) {
            return;
        }
        $box = '<input class="shortlink-input" type="text" readonly="readonly" value="' . Escape::attr($short) . '" aria-label="' . Gettext::text('Shortlink') . '" />';
        $bar->add_node(['id' => 'get-shortlink', 'title' => Gettext::text('Shortlink'), 'href' => $short, 'meta' => ['html' => $box]]);
    }

    /** The groups for the bar's right side and the logo menu's outside links. */
    public static function secondaryGroups(\WP_Admin_Bar $bar): void
    {
        $bar->add_group(['id' => 'top-secondary', 'meta' => ['class' => 'ab-top-secondary']]);
        $bar->add_group(['parent' => 'wp-logo', 'id' => 'wp-logo-external', 'meta' => ['class' => 'ab-sub-secondary']]);
    }

    /** The way out of recovery mode, while the site is in it. */
    public static function recoveryModeMenu(\WP_Admin_Bar $bar): void
    {
        if (!\wp_is_recovery_mode()) {
            return;
        }
        $bar->add_node([
            'parent' => 'top-secondary',
            'id' => 'recovery-mode',
            'title' => Gettext::text('Exit Recovery Mode'),
            'href' => \wp_nonce_url(\add_query_arg('action', 'exit_recovery_mode', \wp_login_url()), 'exit_recovery_mode'),
        ]);
    }

    /** The search box, on the front end. */
    public static function searchMenu(\WP_Admin_Bar $bar): void
    {
        if (Runtime::current()->isAdmin) {
            return;
        }
        $form = '<form action="' . \esc_url(\home_url('/')) . '" method="get" id="adminbarsearch">'
            . '<input class="adminbar-input" name="s" id="adminbar-search" type="text" value="" maxlength="150" />'
            . '<label for="adminbar-search" class="screen-reader-text">' . Gettext::text('Search') . '</label>'
            . '<input type="submit" class="adminbar-button" value="' . Gettext::text('Search') . '" />'
            . '</form>';
        $bar->add_node(['parent' => 'top-secondary', 'id' => 'search', 'title' => $form, 'meta' => ['class' => 'admin-bar-search', 'tabindex' => -1]]);
    }
}
