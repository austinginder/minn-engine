<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Front\Kind;
use Minn\Front\Permalinks;
use Minn\Front\Resolution;
use Minn\Html;
use Minn\I18n\Gettext;
use Minn\Runtime\Runtime;
use Minn\Support\Escape;
use Minn\Support\Lists;

/**
 * The engine's own pieces of a page's head: its block stylesheet link,
 * and for rest_output_link_wp_head the REST discovery link and the JSON
 * alternate for the queried object; the site's feed links as feed_links
 * prints them. Every theme's head prints them from the reference's
 * wp_head hooks.
 */
final readonly class HeadLinks
{
    public function __construct(private Permalinks $permalinks)
    {
    }

    /** The link to the engine's own block stylesheet. */
    public function engineStylesheet(): string
    {
        return '<link rel="stylesheet" id="minn-blocks-css" href="' . Html::attr($this->permalinks->url('/minn/assets/blocks.css')) . '" />' . "\n";
    }

    /**
     * The site's two feed links as feed_links prints them: the posts feed
     * and the comments feed (each unless its feed_links_show_* filter says
     * no), titled with the caller's separator and words, through
     * feed_links_args.
     *
     * @param array<string, mixed> $args
     */
    public static function siteFeeds(array $args): string
    {
        $args = (array) Runtime::hooks()->filter('feed_links_args', [Lists::args($args, ['separator' => Gettext::inContext('&raquo;', 'feed link'), 'feedtitle' => Gettext::text('%1$s %2$s Feed'), 'comstitle' => Gettext::text('%1$s %2$s Comments Feed')])]);
        $name = (string) \get_bloginfo('name');
        $link = static fn (string $title, string $href): string => sprintf('<link rel="alternate" type="%s" title="%s" href="%s" />' . "\n", \feed_content_type(), Escape::attr(sprintf($title, $name, $args['separator'])), \esc_url($href));
        $out = Runtime::hooks()->filter('feed_links_show_posts_feed', [true]) ? $link((string) $args['feedtitle'], (string) \get_feed_link()) : '';
        return $out . (Runtime::hooks()->filter('feed_links_show_comments_feed', [true]) ? $link((string) $args['comstitle'], (string) \get_feed_link('comments_' . \get_default_feed())) : '');
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
        return '<link rel="alternate" title="' . Html::attr(Gettext::inContext('JSON', 'REST API resource link name')) . '" type="application/json" href="' . Html::attr($this->permalinks->url('/wp-json' . $json)) . '" />';
    }
}
