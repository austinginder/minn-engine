<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Media\FileTypes;
use Minn\Objects\Post;
use Minn\Objects\Query;
use Minn\Runtime\PostLinks;
use Minn\Runtime\PostTags;
use Minn\Runtime\QueryArgs;
use Minn\Runtime\Records;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
use Minn\Support\Escape;
use Minn\Support\Text;
use Minn\Support\Url;
use Minn\Runtime\TermFields;

/**
 * The template tags a feed is written with that take more than a line, as
 * the reference answers them in a feed's loop (probe feed-tags): a post's
 * categories and tags in each feed's markup, its enclosures for RSS and
 * Atom, the link to its comments feed, the feed's build date, and the site
 * icon a feed carries; and the small ones: the site's default feed, a feed's
 * content type and a post's title for a feed.
 */
final class FeedTags
{
    /** The feed the site serves by default (get_default_feed): default_feed's, "rss" read as rss2. */
    public static function defaultFeed(): mixed
    {
        $feed = Runtime::hooks()->filter('default_feed', ['rss2']);
        return $feed === 'rss' ? 'rss2' : $feed;
    }

    /** The content type a feed is served as (feed_content_type), the default feed's when none is named. */
    public static function contentType(mixed $type = ''): mixed
    {
        if ($type === '') {
            $type = self::defaultFeed();
        }
        $types = ['rss' => 'application/rss+xml', 'rss2' => 'application/rss+xml', 'rss-http' => 'text/xml', 'atom' => 'application/atom+xml', 'rdf' => 'application/rdf+xml'];
        return Runtime::hooks()->filter('feed_content_type', [$types[$type] ?? 'application/octet-stream', $type]);
    }

    /** A post's title for a feed (get_the_title_rss), as the_title_rss leaves it. */
    public static function titleRss(mixed $post = 0): mixed
    {
        return Runtime::hooks()->filter('the_title_rss', [PostTags::title($post)]);
    }

    /** A post's category and tag names (each once) in a feed type's markup, before the_category_rss. */
    public static function categories(string $type): string
    {
        $names = [];
        foreach ([['category', (array) \get_the_category()], ['post_tag', (array) (\get_the_tags() ?: [])]] as [$taxonomy, $terms]) {
            foreach ($terms as $term) {
                $names[] = $type === 'atom' ? (string) $term->name : (string) TermFields::field('name', $term->name, (int) $term->term_id, (string) $taxonomy, 'rss');
            }
        }
        $out = '';
        foreach (array_unique($names) as $name) {
            $out .= match ($type) {
                'rdf' => "\t\t<dc:subject><![CDATA[" . FeedWriter::insideCdata(html_entity_decode($name, ENT_COMPAT, (string) Runtime::options()->filtered('blog_charset'))) . "]]></dc:subject>\n",
                'atom' => sprintf('<category scheme="%1$s" term="%2$s" />', Escape::attr(\get_bloginfo_rss('url')), Escape::attr($name)),
                default => "\t\t<category><![CDATA[" . FeedWriter::insideCdata(html_entity_decode($name, ENT_COMPAT, (string) Runtime::options()->filtered('blog_charset'))) . "]]></category>\n",
            };
        }
        return (string) Runtime::hooks()->filter('the_category_rss', [$out, $type]);
    }

    /** @return list<list<string>> the current post's enclosures, each as its lines, unless its password is wanted */
    private static function enclosures(): array
    {
        if (PostTags::passwordRequired()) {
            return [];
        }
        $custom = (array) \get_post_custom();
        return array_map(static fn ($entry): array => explode("\n", (string) $entry), (array) ($custom['enclosure'] ?? []));
    }

    /** The current post's enclosures as RSS has them: address, length, and the type the third line starts with. */
    public static function rssEnclosures(): string
    {
        $out = '';
        foreach (self::enclosures() as $lines) {
            if (count($lines) < 3) {
                continue;
            }
            $type = preg_split('/[ \t]/', trim($lines[2]))[0] ?? '';
            $out .= Runtime::hooks()->filter('rss_enclosure', ['<enclosure url="' . Escape::url(trim($lines[0])) . '" length="' . abs((int) (trim($lines[1]))) . '" type="' . Escape::attr($type) . '" />' . "\n"]);
        }
        return $out;
    }

    /** The current post's enclosures as Atom links: the length a line that is a number, the type a line that is a known MIME type. */
    public static function atomEnclosures(): string
    {
        $out = '';
        $mimes = FileTypes::allowed();
        foreach (self::enclosures() as $lines) {
            [$length, $type] = [0, ''];
            // The lines after the address hold the length and the type, in either order.
            foreach (array_slice($lines, 1, 2) as $line) {
                if (is_numeric($line)) {
                    $length = trim($line);
                } elseif (in_array($line, $mimes, true)) {
                    $type = trim($line);
                }
            }
            $out .= Runtime::hooks()->filter('atom_enclosure', [sprintf("<link href=\"%s\" rel=\"enclosure\" length=\"%d\" type=\"%s\" />\n", Escape::url(trim($lines[0])), Escape::attr((string) $length), Escape::attr($type))]);
        }
        return $out;
    }

    /** The address of a post's comments feed in a feed type (the default one bare), through post_comments_feed_link. */
    public static function postCommentsFeedLink(int $postId, string $feed): string
    {
        $postId = $postId ?: (int) PostTags::id();
        $feed = $feed !== '' ? $feed : self::defaultFeed();
        $post = Records::post($postId);
        if (!$post instanceof Post) {
            return '';
        }
        $unattached = $post->post_type === 'attachment' && (int) $post->post_parent === 0;
        if (Runtime::options()->filtered('permalink_structure')) {
            if ($unattached) {
                $url = QueryArgs::add('attachment_id', $postId, SiteUrls::home('/feed/') . (self::defaultFeed() !== $feed ? "{$feed}/" : ''));
            } else {
                $front = Runtime::options()->filtered('show_on_front') === 'page' && (int) Runtime::options()->filtered('page_on_front') === $postId;
                $base = Url::withTrailingSlash((string) ($front ? PostLinks::pageAddress($postId) : PostLinks::permalink($postId))) . 'feed';
                $url = SiteUrls::trailing($base . (self::defaultFeed() !== $feed ? "/{$feed}" : ''), 'single_feed');
            }
        } else {
            $key = $unattached ? 'attachment_id' : ($post->post_type === 'page' ? 'page_id' : 'p');
            $url = QueryArgs::add(['feed' => $feed, $key => $postId], SiteUrls::home('/'));
        }
        return (string) Runtime::hooks()->filter('post_comments_feed_link', [$url]);
    }

    /**
     * When the feed last changed, in a format: the newest of its posts'
     * modifications (and comments, for a comments feed); once the loop has
     * run out, the site's last modification; failing both, now.
     */
    public static function buildDate(string $format): string
    {
        $query = $GLOBALS['wp_query'] ?? null;
        $utc = new \DateTimeZone('UTC');
        $datetime = false;
        if ($query instanceof Query && $query->have_posts()) {
            $times = array_map(static fn ($post) => (string) $post->post_modified_gmt, $query->posts);
            if ($query->is_comment_feed() && $query->comment_count) {
                $times = array_merge($times, array_map(static fn ($comment) => (string) $comment->comment_date_gmt, (array) $query->comments));
            }
            $datetime = date_create_immutable_from_format('Y-m-d H:i:s', max($times), $utc);
        }
        if ($datetime === false) {
            $datetime = date_create_immutable_from_format('Y-m-d H:i:s', (string) \get_lastpostmodified('GMT'), $utc);
        }
        if ($datetime === false) {
            $datetime = date_create_immutable('now', $utc);
        }
        return (string) Runtime::hooks()->filter('get_feed_build_date', [$datetime->format($format), $format]);
    }

    /** The site icon as an RSS 2.0 image (titled with the feed's title), or '' without one. */
    public static function rss2Icon(): string
    {
        $title = \get_wp_title_rss();
        $title = $title !== '' ? $title : \get_bloginfo_rss('name');
        $url = \get_site_icon_url(32);
        if (!$url) {
            return '';
        }
        return "\n<image>\n\t<url>" . Text::encodeAmpersands($url) . "</url>\n\t<title>" . $title . "</title>\n\t<link>" . \get_bloginfo_rss('url')
            . "</link>\n\t<width>32</width>\n\t<height>32</height>\n</image> \n";
    }

    /** The site icon as an Atom icon, or '' without one. */
    public static function atomIcon(): string
    {
        $url = \get_site_icon_url(32);
        return $url ? '<icon>' . Text::encodeAmpersands($url) . "</icon>\n" : '';
    }
}
