<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostRecord;
use Minn\Content\SiteIcon;
use Minn\Content\Site;
use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Support\Html;

/**
 * The links the reference puts in every head: the site and comments
 * feeds (plus the archive's own feed), the REST discovery link, the
 * JSON alternate for the queried object, and the site icon set. The
 * block path prints them as one run; the classic path prints the same
 * pieces from the reference's wp_head hooks.
 */
final readonly class HeadLinks
{
    public function __construct(
        private Site $site,
        private SiteIcon $icon,
        private Permalinks $permalinks,
    ) {
    }

    /** The link to the engine's own block stylesheet. */
    public function engineStylesheet(): string
    {
        return '<link rel="stylesheet" id="minn-blocks-css" href="' . Html::attr($this->permalinks->url('/minn/assets/blocks.css')) . '" />' . "\n";
    }

    /** Every head link for a resolution. */
    public function all(Resolution $resolution): string
    {
        return $this->feedLinks() . $this->extraFeedLink($resolution) . $this->restLink() . $this->jsonAlternate($resolution) . "\n" . $this->icons();
    }

    /** The site and comments feed links. */
    public function feedLinks(): string
    {
        $site = Html::esc((string) ($this->site->option('blogname') ?? ''));
        return '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Feed" href="' . Html::attr($this->permalinks->url('/feed/')) . '" />' . "\n"
            . '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Comments Feed" href="' . Html::attr($this->permalinks->url('/comments/feed/')) . '" />' . "\n";
    }

    /** The feed link a single or an archive adds. */
    public function extraFeedLink(Resolution $resolution): string
    {
        $site = Html::esc((string) ($this->site->option('blogname') ?? ''));
        $record = $resolution->record ?? [];
        switch ($resolution->kind) {
            case Kind::Category:
            case Kind::Tag:
                $label = $resolution->kind === Kind::Category ? 'Category' : 'Tag';
                return '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; ' . Html::esc((string) $record['name']) . ' ' . $label . ' Feed" href="' . Html::attr($this->permalinks->forTerm($record) . 'feed/') . '" />' . "\n";
            case Kind::Search:
                return '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; Search Results for &#8220;' . Html::esc((string) $resolution->search) . '&#8221; Feed" href="' . Html::attr($this->permalinks->url('/search/' . rawurlencode((string) $resolution->search) . '/feed/rss2/')) . '" />' . "\n";
            case Kind::Single:
            case Kind::Page:
                // A singular view announces its own comments feed while comments or
                // pings are open on it (even with none yet), or once it has comments:
                // the reference prints it for a page with closed comments, open pings,
                // and a zero count.
                if (!$record instanceof PostRecord) {
                    return '';
                }
                $open = ($record['comment_status'] ?? '') === 'open' || ($record['ping_status'] ?? '') === 'open';
                if ((int) ($record['comment_count'] ?? 0) > 0 || $open) {
                    $own = $record['post_type'] === 'page' ? $this->permalinks->pagePath($record) : $this->permalinks->forPost($record);
                    return '<link rel="alternate" type="application/rss+xml" title="' . $site . ' &raquo; ' . Html::esc((string) $record['post_title']) . ' Comments Feed" href="' . Html::attr($own . 'feed/') . '" />' . "\n";
                }
                return '';
        }
        return '';
    }

    /** No trailing newline: the reference prints the JSON alternate and the RSD link on the same physical line. */
    public function restLink(): string
    {
        return '<link rel="https://api.w.org/" href="' . Html::attr($this->permalinks->url('/wp-json/')) . '" />';
    }

    /** The wp/v2 alternate link for the resolution. */
    public function jsonAlternate(Resolution $resolution): string
    {
        $record = $resolution->record ?? [];
        $json = match ($resolution->kind) {
            Kind::Category, Kind::Tag => '/wp/v2/' . ($resolution->kind === Kind::Category ? 'categories' : 'tags') . '/' . (int) $record['term_id'],
            Kind::Single, Kind::Page => '/wp/v2/' . ($record['post_type'] === 'page' ? 'pages' : 'posts') . '/' . (int) $record['ID'],
            Kind::Author => $resolution->record === null ? null : '/wp/v2/users/' . $resolution->id(),
            default => null,
        };
        if ($json === null) {
            return '';
        }
        return '<link rel="alternate" title="JSON" type="application/json" href="' . Html::attr($this->permalinks->url('/wp-json' . $json)) . '" />';
    }

    /** The RSD link. */
    public function rsdLink(): string
    {
        return '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="' . Html::attr($this->permalinks->url('/xmlrpc.php?rsd')) . '" />' . "\n";
    }

    /** Singular views only; posts and pages alike shortlink as ?p={id}, in the reference's single quotes. */
    public function shortlink(Resolution $resolution): string
    {
        if ($resolution->kind !== Kind::Single && $resolution->kind !== Kind::Page) {
            return '';
        }
        $id = (int) (($resolution->record ?? [])['ID'] ?? 0);
        if ($id < 1) {
            return '';
        }
        return "<link rel='shortlink' href='" . Html::attr($this->permalinks->url('/?p=' . $id)) . "' />" . "\n";
    }

    /** The site icon links: the 32 and 192 pixel icons, the Apple touch icon, and the tile image. */
    public function icons(): string
    {
        if ($this->icon->file() === null) {
            return '';
        }
        $url = fn (int $size): string => Html::attr($this->icon->urlAt($size));
        return '<link rel="icon" href="' . $url(32) . '" sizes="32x32" />' . "\n"
            . '<link rel="icon" href="' . $url(192) . '" sizes="192x192" />' . "\n"
            . '<link rel="apple-touch-icon" href="' . $url(180) . '" />' . "\n"
            . '<meta name="msapplication-TileImage" content="' . $url(270) . '" />' . "\n";
    }
}
