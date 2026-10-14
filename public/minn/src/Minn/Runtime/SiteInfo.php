<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Front\FeedTags;
use Minn\I18n\SiteLanguage;
use Minn\Support\Escape;

/**
 * What WordPress tells about the site (get_bloginfo): its name and tagline,
 * its addresses and feeds', the theme's stylesheet and folders, the admin
 * address, the charset and content type, the version, the language and
 * its direction; and a feed's address.
 */
final class SiteInfo
{
    /**
     * One fact about the site (get_bloginfo), its name when none is asked;
     * for display through bloginfo_url (an address) or bloginfo.
     */
    public static function get(mixed $show = '', mixed $filter = 'raw'): mixed
    {
        $name = (string) $show;
        $options = Runtime::options();
        $output = match ($name) {
            'url', 'home', 'siteurl' => SiteUrls::home(),
            'wpurl' => SiteUrls::site(),
            'description' => (string) $options->filtered('blogdescription'),
            'rdf_url' => self::feedLink('rdf'),
            'rss_url' => self::feedLink('rss'),
            'rss2_url' => self::feedLink('rss2'),
            'atom_url' => self::feedLink('atom'),
            'comments_atom_url' => self::feedLink('comments_atom'),
            'comments_rss2_url' => self::feedLink('comments_rss2'),
            'pingback_url' => SiteUrls::site('xmlrpc.php'),
            'stylesheet_url' => Themes::stylesheetUri(),
            'stylesheet_directory' => Themes::stylesheetDirectoryUri(),
            'template_directory', 'template_url' => Themes::templateDirectoryUri(),
            'admin_email' => (string) $options->filtered('admin_email'),
            'charset' => (string) ($options->filtered('blog_charset') ?: 'UTF-8'),
            'html_type' => (string) ($options->filtered('html_type') ?: 'text/html'),
            'version' => $GLOBALS['wp_version'],
            'language' => SiteLanguage::tag(),
            'text_direction' => SiteLanguage::isRtl() ? 'rtl' : 'ltr',
            default => (string) $options->filtered('blogname'),
        };
        if ($filter === 'display') {
            $address = str_contains($name, 'url') || str_contains($name, 'directory') || str_contains($name, 'home');
            $output = Runtime::hooks()->filter($address ? 'bloginfo_url' : 'bloginfo', [$output, $show]);
        }
        return $output;
    }

    /** One fact about the site for a feed (get_bloginfo_rss): escaped whole, tags included, through get_bloginfo_rss. */
    public static function rss(mixed $show = ''): mixed
    {
        return Runtime::hooks()->filter('get_bloginfo_rss', [Escape::html(self::get($show)), $show]);
    }

    /**
     * A feed's address (get_feed_link): the default feed bare, through
     * feed_link handed '' for it; its ?feed= form without pretty permalinks.
     */
    public static function feedLink(mixed $feed = ''): mixed
    {
        $feed = (string) $feed;
        if (!Runtime::options()->filtered('permalink_structure')) {
            $feed = str_replace('comments_', 'comments-', $feed !== '' ? $feed : FeedTags::defaultFeed());
            return Runtime::hooks()->filter('feed_link', [SiteUrls::home("?feed={$feed}"), $feed]);
        }
        $comments = str_contains($feed, 'comments_');
        $feed = str_replace('comments_', '', $feed);
        $feed = $feed === FeedTags::defaultFeed() ? '' : $feed;
        $path = preg_replace('#/+#', '/', '/' . ($comments ? 'comments/' : '') . 'feed/' . $feed);
        return Runtime::hooks()->filter('feed_link', [SiteUrls::home(SiteUrls::trailing(rtrim((string) $path, '/'), 'feed')), $feed]);
    }
}
