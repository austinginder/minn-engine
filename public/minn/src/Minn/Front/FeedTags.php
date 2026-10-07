<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\Runtime;
use Minn\Support\Escape;

/**
 * The template tags a feed is written with that take more than a line, as
 * the reference answers them in a feed's loop (probe feed-tags): a post's
 * categories and tags in each feed's markup, its enclosures for RSS and
 * Atom, the link to its comments feed, the feed's build date, and the site
 * icon a feed carries.
 */
final class FeedTags
{
    /** A post's category and tag names (each once) in a feed type's markup, before the_category_rss. */
    public static function categories(string $type): string
    {
        $names = [];
        foreach ([['category', (array) \get_the_category()], ['post_tag', (array) (\get_the_tags() ?: [])]] as [$taxonomy, $terms]) {
            foreach ($terms as $term) {
                $names[] = $type === 'atom' ? (string) $term->name : (string) \sanitize_term_field('name', $term->name, $term->term_id, $taxonomy, 'rss');
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
        return (string) \apply_filters('the_category_rss', $out, $type);
    }

    /** @return list<list<string>> the current post's enclosures, each as its lines, unless its password is wanted */
    private static function enclosures(): array
    {
        if (\post_password_required()) {
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
            $out .= \apply_filters('rss_enclosure', '<enclosure url="' . \esc_url(trim($lines[0])) . '" length="' . abs((int) (trim($lines[1]))) . '" type="' . Escape::attr($type) . '" />' . "\n");
        }
        return $out;
    }

    /** The current post's enclosures as Atom links: the length a line that is a number, the type a line that is a known MIME type. */
    public static function atomEnclosures(): string
    {
        $out = '';
        $mimes = \get_allowed_mime_types();
        foreach (self::enclosures() as $lines) {
            $length = 0;
            $type = '';
            foreach ([1, 2] as $at) {
                if (!isset($lines[$at])) {
                    continue;
                }
                if (is_numeric($lines[$at])) {
                    $length = trim($lines[$at]);
                } elseif (in_array($lines[$at], $mimes, true)) {
                    $type = trim($lines[$at]);
                }
            }
            $out .= \apply_filters('atom_enclosure', sprintf("<link href=\"%s\" rel=\"enclosure\" length=\"%d\" type=\"%s\" />\n", \esc_url(trim($lines[0])), Escape::attr((string) $length), Escape::attr($type)));
        }
        return $out;
    }

    /** The address of a post's comments feed in a feed type (the default one bare), through post_comments_feed_link. */
    public static function postCommentsFeedLink(int $postId, string $feed): string
    {
        $postId = $postId ?: (int) \get_the_ID();
        $feed = $feed !== '' ? $feed : \get_default_feed();
        $post = \get_post($postId);
        if (!$post instanceof \WP_Post) {
            return '';
        }
        $unattached = $post->post_type === 'attachment' && (int) $post->post_parent === 0;
        if (Runtime::options()->filtered('permalink_structure')) {
            if ($unattached) {
                $url = \add_query_arg('attachment_id', $postId, \home_url('/feed/') . (\get_default_feed() !== $feed ? "{$feed}/" : ''));
            } else {
                $front = Runtime::options()->filtered('show_on_front') === 'page' && (int) Runtime::options()->filtered('page_on_front') === $postId;
                $base = \trailingslashit((string) ($front ? \_get_page_link($postId) : \get_permalink($postId))) . 'feed';
                $url = \user_trailingslashit($base . (\get_default_feed() !== $feed ? "/{$feed}" : ''), 'single_feed');
            }
        } else {
            $key = $unattached ? 'attachment_id' : ($post->post_type === 'page' ? 'page_id' : 'p');
            $url = \add_query_arg(['feed' => $feed, $key => $postId], \home_url('/'));
        }
        return (string) \apply_filters('post_comments_feed_link', $url);
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
        if ($query instanceof \WP_Query && $query->have_posts()) {
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
        return (string) \apply_filters('get_feed_build_date', $datetime->format($format), $format);
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
        return "\n<image>\n\t<url>" . \convert_chars($url) . "</url>\n\t<title>" . $title . "</title>\n\t<link>" . \get_bloginfo_rss('url')
            . "</link>\n\t<width>32</width>\n\t<height>32</height>\n</image> \n";
    }

    /** The site icon as an Atom icon, or '' without one. */
    public static function atomIcon(): string
    {
        $url = \get_site_icon_url(32);
        return $url ? '<icon>' . \convert_chars($url) . "</icon>\n" : '';
    }
}
