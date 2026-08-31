<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Auth\Authenticated;
use Minn\Auth\Capabilities;
use Minn\Auth\Nonce;
use Minn\Content\Site;
use Minn\Support\Html;

/**
 * The Minn bar on the public site: the same server-rendered chrome the
 * app ships (assets/js/bar.js and assets/css/bar.css from the bundle),
 * built here for a signed-in reader who can edit. There is no other bar
 * and no other admin on the engine, so it is on for everyone who passes
 * the edit_posts gate; the app's per-person opt-in does not apply.
 */
final readonly class AdminBar
{
    public function __construct(
        private Authenticated $session,
        private Capabilities $capabilities,
        private Site $site,
        private Permalinks $permalinks,
        private App $app,
        private Appearance $appearance,
        private AdminTypes $types,
    ) {
    }

    public static function forReader(?Authenticated $session, Capabilities $capabilities, Site $site, Permalinks $permalinks, App $app, Appearance $appearance, AdminTypes $types): ?self
    {
        if (!$session instanceof Authenticated || !$capabilities->can($session->id(), 'edit_posts') || !$app->installed()) {
            return null;
        }
        return new self($session, $capabilities, $site, $permalinks, $app, $appearance, $types);
    }

    /** The stylesheet link for the head. */
    public function head(): string
    {
        return '<link rel="stylesheet" id="minn-admin-bar-css" href="' . Html::attr($this->assetUrl('assets/css/bar.css')) . '" media="all" />' . "\n";
    }

    /** The bar markup, its config, and the script, for the end of the body. */
    public function render(Resolution $resolution): string
    {
        [$editUrl, $editLabel, $editHint] = $this->editTarget($resolution);
        $status = $this->status();
        $config = [
            'rest' => $this->permalinks->url('/wp-json/'),
            'nonce' => Nonce::create($this->session->id(), $this->session->token),
            'app' => $this->appUrl(),
            'editorBase' => $this->appPath('editor'),
            'fix' => $status['fix'] ?? null,
            'commands' => $this->commands($editUrl, $editLabel, $editHint),
            'purge' => [],
            'types' => $this->searchTypes(),
            'emptyNotifs' => 'All caught up.',
            'i18n' => [
                'placeholder' => 'Go anywhere or run a command…',
                'content' => 'Your content',
                'empty' => 'No matches. Try “content” or “settings”.',
                'navigate' => 'navigate',
                'open' => 'open',
                'purging' => 'Clearing cache…',
                'purged' => 'Cache cleared (%s)',
                'purgeFail' => 'Cache cleared (%1$s); failed: %2$s',
            ],
        ];
        return $this->markup($status, $editUrl, $editLabel)
            . '<script id="minn-admin-bar-js-before">window.MINN_BAR = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>' . "\n"
            . '<script src="' . Html::attr($this->assetUrl('assets/js/bar.js')) . '" id="minn-admin-bar-js"></script>' . "\n";
    }

    private function markup(?array $status, string $editUrl, string $editLabel): string
    {
        $user = $this->session->user;
        $appearance = $this->appearance->read($this->session->id());
        $scheme = $appearance['scheme'];
        $siteName = html_entity_decode((string) ($this->site->option('blogname') ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $avatar = 'https://secure.gravatar.com/avatar/' . hash('sha256', strtolower(trim((string) $user['user_email']))) . '?s=104&d=mm&r=g';
        $can = fn (string $cap): bool => $this->capabilities->can($this->session->id(), $cap);

        $out = '<div id="minn-cornerbar"' . ($status === null ? ' class="minn-bar-ghost"' : '') . '>'
            . '<div id="minn-bar-root" data-minn-theme="dark" data-minn-scheme="' . Html::attr($scheme) . '">';
        if ($scheme === 'custom') {
            $out .= $this->customSchemeStyle($appearance);
        }
        $out .= '<script>(function(){try{var t=localStorage.getItem("minn-theme");if(t!=="dark"&&t!=="light"){t=window.matchMedia&&matchMedia("(prefers-color-scheme: light)").matches?"light":"dark";}document.getElementById("minn-bar-root").setAttribute("data-minn-theme",t);}catch(e){}})();</script>'
            . '<script>(function(){try{var c=document.getElementById("minn-cornerbar");if(!c||!c.classList.contains("minn-bar-ghost")){return;}var ts=0;try{ts=parseInt(sessionStorage.getItem("minn-bar-corner")||"0",10);sessionStorage.removeItem("minn-bar-corner");}catch(e){}if(ts&&Date.now()-ts<60000){c.classList.add("minn-bar-peek");}}catch(e){}})();</script>';

        $out .= '<header id="minn-bar" aria-label="Minn Admin Bar">';
        $out .= '<div class="minn-bar-zone minn-bar-left">'
            . '<a class="minn-bar-btn minn-bar-markbtn" href="' . Html::attr($this->appUrl()) . '" title="Open Minn Admin" aria-expanded="false"><span class="minn-bar-mark">m</span></a>'
            . '<button type="button" class="minn-bar-btn minn-bar-site" data-barmenu="minn-bar-menu-site" aria-haspopup="menu" aria-expanded="false">'
            . '<span class="minn-bar-sitename">' . Html::esc($siteName) . '</span>'
            . '<svg class="minn-bar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 10 4 4 4-4"/></svg>'
            . '</button>';
        if ($status !== null) {
            $out .= '<span class="minn-bar-divider"></span>'
                . '<button type="button" class="minn-bar-btn minn-bar-status" data-tone="' . Html::attr($status['tone']) . '" data-barmenu="minn-bar-menu-status" aria-haspopup="menu" aria-expanded="false" aria-label="' . Html::attr($status['title']) . '">'
                . '<span class="minn-bar-status-dot"></span><span class="minn-bar-status-text">' . Html::esc($status['label']) . '</span></button>';
        }
        $out .= '</div>';

        $out .= '<div class="minn-bar-zone minn-bar-right">'
            . '<button type="button" class="minn-bar-btn minn-bar-iconbtn" id="minn-bar-search" title="Search Minn Admin" aria-label="Search Minn Admin">' . self::icon('<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>') . '</button>'
            . '<button type="button" class="minn-bar-btn minn-bar-iconbtn" data-barmenu="minn-bar-menu-new" aria-haspopup="menu" aria-expanded="false" aria-label="Create new">' . self::icon('<path d="M12 5v14M5 12h14"/>') . '</button>';
        if ($editUrl !== '') {
            $out .= '<a class="minn-bar-btn minn-bar-edit" href="' . Html::attr($editUrl) . '">' . self::icon('<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>') . '<span>' . Html::esc($editLabel) . '</span></a>';
        }
        $out .= '<span class="minn-bar-divider"></span>'
            . '<span class="minn-bar-bellwrap"><button type="button" class="minn-bar-btn minn-bar-iconbtn" data-barmenu="minn-bar-menu-notif" aria-haspopup="menu" aria-expanded="false" aria-label="Notifications">'
            . self::icon('<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>')
            . '</button><span class="minn-bar-notif-dot" id="minn-bar-notif-dot" hidden></span></span>'
            . '<button type="button" class="minn-bar-btn minn-bar-iconbtn minn-bar-avatarbtn" data-barmenu="minn-bar-menu-user" aria-haspopup="menu" aria-expanded="false" aria-label="Account menu"><span class="minn-bar-avatar">'
            . '<img class="minn-bar-avatar-img" loading="lazy" src="' . Html::attr($avatar) . '" width="52" height="52" alt="" /></span></button>'
            . '</div>';
        $out .= '<a class="minn-bar-mobile-admin" href="' . Html::attr($this->appUrl()) . '">' . self::gridIcon() . '<span>Open Minn Admin</span></a>';
        $out .= '</header>';

        $out .= '<div class="minn-bar-menu" id="minn-bar-menu-site" role="menu" hidden>'
            . '<div class="minn-bar-menu-label">Minn Admin</div>'
            . self::menuItem($this->appUrl(), '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>', 'Overview')
            . self::menuItem($this->appPath('content'), '<path d="M6 3h9l3 3v15H6z"/><path d="M9 11h6M9 15h6"/>', 'Content');
        if ($can('upload_files')) {
            $out .= self::menuItem($this->appPath('media'), '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m5 17 5-5 3 3 2-2 4 4"/>', 'Media');
        }
        if ($can('manage_options')) {
            $out .= self::menuItem($this->appPath('settings'), '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>', 'Settings');
        }
        $out .= '</div>';

        if ($status !== null) {
            $out .= '<div class="minn-bar-menu" id="minn-bar-menu-status" role="menu" hidden>'
                . '<div class="minn-bar-menu-label">Site status</div>'
                . '<div class="minn-bar-menu-item minn-bar-menu-static"><span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">' . Html::esc($status['title']) . '</span><span class="minn-bar-menu-sub minn-bar-menu-wrap">' . Html::esc($status['sub']) . '</span></span></div>';
            if ($status['fix'] !== null || $can('manage_options')) {
                $out .= '<div class="minn-bar-menu-rule"></div>';
            }
            if ($status['fix'] !== null) {
                $out .= '<button type="button" class="minn-bar-menu-item" role="menuitem" id="minn-bar-status-fix">' . self::icon('<path d="M18.4 5.6a9 9 0 1 0 .8 8.4"/><path d="M19 3v5h-5"/>') . '<span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">' . Html::esc($status['fix']['label']) . '</span></span></button>';
            }
            if ($can('manage_options')) {
                $out .= self::menuItem($this->appPath('settings'), '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>', 'Visibility settings');
            }
            $out .= '</div>';
        }

        $out .= '<div class="minn-bar-menu" id="minn-bar-menu-new" role="menu" hidden>'
            . '<div class="minn-bar-menu-label">Create</div>'
            . '<button type="button" class="minn-bar-menu-item" role="menuitem" data-barintent="new:posts">' . self::icon('<path d="M6 3h9l3 3v15H6z"/><path d="M9 11h6M9 15h6"/>') . '<span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">Post</span></span></button>';
        if ($can('edit_pages')) {
            $out .= '<button type="button" class="minn-bar-menu-item" role="menuitem" data-barintent="new:pages">' . self::icon('<path d="M6 3h9l3 3v15H6z"/><path d="M15 3v4h4"/>') . '<span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">Page</span></span></button>';
        }
        $out .= '</div>';

        $out .= '<div class="minn-bar-menu" id="minn-bar-menu-notif" role="menu" hidden>'
            . '<div class="minn-bar-menu-label">Notifications</div>'
            . '<div id="minn-bar-notif-items"><div class="minn-bar-menu-item minn-bar-menu-static"><span class="minn-bar-menu-copy"><span class="minn-bar-menu-sub">Loading…</span></span></div></div>'
            . '<div class="minn-bar-menu-rule"></div>'
            . '<button type="button" class="minn-bar-menu-item" role="menuitem" data-barintent="notifications">' . self::icon('<path d="M5 12h14M13 6l6 6-6 6"/>') . '<span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">Open notifications</span></span></button>'
            . '</div>';

        $out .= '<div class="minn-bar-menu" id="minn-bar-menu-user" role="menu" hidden>'
            . '<div class="minn-bar-menu-label">' . Html::esc(html_entity_decode((string) $user['display_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</div>'
            . self::menuItem($this->appPath('profile'), '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>', 'Your profile')
            . '<div class="minn-bar-menu-rule"></div>'
            . self::menuItem($this->permalinks->url('/minn-admin/login/logout'), '<path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"/>', 'Sign out')
            . '</div>';

        return $out . '</div></div>' . "\n";
    }

    /**
     * The status chip: the one thing the engine can say about site
     * visibility is whether search engines are asked to stay away.
     *
     * @return array{tone: string, label: string, title: string, sub: string, fix: ?array}|null
     */
    private function status(): ?array
    {
        if ((string) ($this->site->option('blog_public') ?? '1') !== '0') {
            return null;
        }
        return [
            'tone' => 'blue',
            'label' => 'Hidden from search',
            'title' => 'Search engines are discouraged',
            'sub' => 'The site asks search engines not to index it. Visitors can still browse normally.',
            'fix' => $this->capabilities->can($this->session->id(), 'manage_options')
                ? ['label' => 'Allow search engines', 'kind' => 'settings', 'body' => ['blog_public' => 1]]
                : null,
        ];
    }

    /** @return array{0: string, 1: string, 2: string} url (or ''), label, hint */
    private function editTarget(Resolution $resolution): array
    {
        $record = $resolution->record;
        if (!in_array($resolution->kind, [Kind::Single, Kind::Page], true) || $record === null) {
            return ['', 'Edit', 'Open this page in the Minn editor'];
        }
        $id = (int) $record['ID'];
        if (!$this->capabilities->can($this->session->id(), 'edit_post', $id)) {
            return ['', 'Edit', 'Open this page in the Minn editor'];
        }
        $type = (string) $record['post_type'];
        $base = $this->types->restBaseOf($type);
        $singular = $this->types->singularOf($type);
        return [$this->appPath('editor/' . rawurlencode($base) . '/' . $id), $singular === '' ? 'Edit' : "Edit {$singular}", 'Open this page in the Minn editor'];
    }

    private function commands(string $editUrl, string $editLabel, string $editHint): array
    {
        $can = fn (string $cap): bool => $this->capabilities->can($this->session->id(), $cap);
        $commands = [
            ['group' => 'Go to', 'icon' => 'grid', 'title' => 'Overview', 'hint' => 'Minn Admin home', 'kind' => 'url', 'value' => $this->appUrl()],
            ['group' => 'Go to', 'icon' => 'doc', 'title' => 'Content', 'hint' => 'Posts, pages, and custom types', 'kind' => 'url', 'value' => $this->appPath('content')],
        ];
        if ($can('upload_files')) {
            $commands[] = ['group' => 'Go to', 'icon' => 'image', 'title' => 'Media', 'hint' => 'Library and uploads', 'kind' => 'url', 'value' => $this->appPath('media')];
        }
        if ($can('manage_options')) {
            $commands[] = ['group' => 'Go to', 'icon' => 'gear', 'title' => 'Settings', 'hint' => 'Site settings in Minn', 'kind' => 'url', 'value' => $this->appPath('settings')];
        }
        if ($editUrl !== '') {
            $commands[] = ['group' => 'Actions', 'icon' => 'pencil', 'title' => $editLabel, 'hint' => $editHint, 'kind' => 'url', 'value' => $editUrl];
        }
        $commands[] = ['group' => 'Actions', 'icon' => 'plus', 'title' => 'Create a post', 'hint' => 'Start a new draft', 'kind' => 'intent', 'value' => 'new:posts'];
        if ($can('edit_pages')) {
            $commands[] = ['group' => 'Actions', 'icon' => 'plus', 'title' => 'Create a page', 'hint' => 'Start a new page', 'kind' => 'intent', 'value' => 'new:pages'];
        }
        $commands[] = ['group' => 'Actions', 'icon' => 'moon', 'title' => 'Toggle appearance', 'hint' => 'Switch light or dark', 'kind' => 'theme', 'value' => ''];
        return $commands;
    }

    /** post type slug => editor route base, for the palette's content results. */
    private function searchTypes(): array
    {
        $out = [];
        foreach ($this->types->editable() as $slug => $base) {
            $out[$slug] = $base;
        }
        return $out;
    }

    private function customSchemeStyle(array $appearance): string
    {
        $vars = ['bg' => '--bg', 'bg2' => '--bg2', 'panel' => '--panel', 'panel2' => '--panel2', 'hover' => '--hover', 'border' => '--border', 'border2' => '--border2', 'text' => '--text', 'text2' => '--text2', 'text3' => '--text3', 'accent' => '--accent', 'accent2' => '--accent2', 'accentFg' => '--accent-fg'];
        $css = '';
        foreach (['dark', 'light'] as $mode) {
            $declarations = '';
            foreach ($vars as $slot => $var) {
                $hex = Appearance::hex((string) ($appearance['custom'][$mode][$slot] ?? ''));
                if ($hex !== '') {
                    $declarations .= $var . ':' . $hex . ';';
                }
            }
            if ($declarations !== '') {
                $css .= '#minn-bar-root[data-minn-theme="' . $mode . '"]{' . $declarations . '}';
            }
        }
        return $css === '' ? '' : '<style id="minn-bar-custom-css">' . $css . '</style>';
    }

    private function appUrl(): string
    {
        return $this->permalinks->url('/minn-admin/');
    }

    private function appPath(string $path): string
    {
        return $this->permalinks->isPretty() ? $this->appUrl() . $path : $this->appUrl() . '#/' . $path;
    }

    private function assetUrl(string $relative): string
    {
        return $this->permalinks->url('/minn/admin/' . $relative) . '?ver=' . rawurlencode($this->app->assetVersion($relative));
    }

    private static function icon(string $paths): string
    {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }

    private static function gridIcon(): string
    {
        return self::icon('<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>');
    }

    private static function menuItem(string $href, string $icon, string $title, string $sub = '', string $meta = ''): string
    {
        return '<a class="minn-bar-menu-item" role="menuitem" href="' . Html::attr($href) . '">'
            . ($icon !== '' ? self::icon($icon) : '')
            . '<span class="minn-bar-menu-copy"><span class="minn-bar-menu-title">' . Html::esc($title) . '</span>'
            . ($sub !== '' ? '<span class="minn-bar-menu-sub">' . Html::esc($sub) . '</span>' : '') . '</span>'
            . ($meta !== '' ? '<span class="minn-bar-menu-meta">' . Html::esc($meta) . '</span>' : '')
            . '</a>';
    }
}
