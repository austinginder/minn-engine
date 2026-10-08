<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\I18n\Gettext;
use Minn\Runtime\Runtime;

/**
 * The feed templates do_feed_* loads, written from the reference's output
 * (suite feed-hooks, probes feed-templates): RSS 2.0, Atom, RDF and RSS
 * 0.92 for posts, RSS 2.0 and Atom for comments. Each is written through
 * the template tags, so every feed filter has its say, and fires each feed
 * action where the reference fires it; the whitespace between is the
 * reference's. A template is written once a request, as require_once
 * loads it, and sends its content type through the header callback first.
 */
final class FeedTemplates
{
    private const SYNDICATION = "\txmlns:sy=\"http://purl.org/rss/1.0/modules/syndication/\"\n";

    /** @var array<string, true> the templates written this request */
    private static array $loaded = [];

    /**
     * A feed template by its name (rss2, rss2-comments, atom, atom-comments,
     * rdf, rss), between wp_before_load_template and wp_after_load_template.
     *
     * @param Closure(string): void $send sends a header line (the Content-Type)
     */
    public static function load(string $name, Closure $send): string
    {
        $file = ABSPATH . WPINC . "/feed-{$name}.php";
        $w = (new FeedWriter())->act('wp_before_load_template', $file, true, []);
        if (!isset(self::$loaded[$name])) {
            self::$loaded[$name] = true;
            $GLOBALS['more'] = 1;
            $type = match ($name) {
                'rss2', 'rss2-comments' => 'rss2',
                'atom', 'atom-comments' => 'atom',
                default => $name,
            };
            $send('Content-Type: ' . \feed_content_type($type) . '; charset=' . Runtime::options()->filtered('blog_charset'));
            $w->put('<?xml version="1.0" encoding="' . Runtime::options()->filtered('blog_charset') . '"' . ($name === 'atom-comments' ? ' ' : '') . '?' . '>');
            match ($name) {
                'rss2' => self::rss2($w),
                'rss2-comments' => self::rss2Comments($w),
                'atom' => self::atom($w),
                'atom-comments' => self::atomComments($w),
                'rdf' => self::rdf($w),
                default => self::rss($w),
            };
        }
        return $w->act('wp_after_load_template', $file, true, [])->text();
    }

    /** What the period and frequency of updates are, as the syndication module says. */
    private static function syndication(FeedWriter $w): FeedWriter
    {
        return $w->put("\t<sy:updatePeriod>\n\t", (string) \apply_filters('rss_update_period', 'hourly'), "\t</sy:updatePeriod>\n\t<sy:updateFrequency>\n\t", (string) \apply_filters('rss_update_frequency', '1'), "\t</sy:updateFrequency>\n\t");
    }

    private static function rss2(FeedWriter $w): void
    {
        $w->act('rss_tag_pre', 'rss2')
            ->put("<rss version=\"2.0\"\n\txmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n\txmlns:wfw=\"http://wellformedweb.org/CommentAPI/\"\n\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n\txmlns:atom=\"http://www.w3.org/2005/Atom\"\n", self::SYNDICATION, "\txmlns:slash=\"http://purl.org/rss/1.0/modules/slash/\"\n\t")
            ->act('rss2_ns')
            ->put(">\n\n<channel>\n\t<title>")->tag('wp_title_rss')
            ->put("</title>\n\t<atom:link href=\"")->tag('self_link')
            ->put("\" rel=\"self\" type=\"application/rss+xml\" />\n\t<link>")->tag('bloginfo_rss', 'url')
            ->put("</link>\n\t<description>")->tag('bloginfo_rss', 'description')
            ->put("</description>\n\t<lastBuildDate>", FeedTags::buildDate('r'), "</lastBuildDate>\n\t<language>")->tag('bloginfo_rss', 'language')
            ->put("</language>\n");
        self::syndication($w)->act('rss2_head');
        while (\have_posts()) {
            \the_post();
            self::rss2Item($w);
        }
        $w->put("</channel>\n</rss>\n");
    }

    private static function rss2Item(FeedWriter $w): void
    {
        $discussed = \get_comments_number() || \comments_open();
        $w->put("\t<item>\n\t\t<title>")->tag('the_title_rss')->put("</title>\n\t\t<link>")->tag('the_permalink_rss')->put("</link>\n");
        if ($discussed) {
            $w->put("\t\t\t\t\t<comments>")->tag('comments_link_feed')->put("</comments>\n");
        }
        $w->put("\t\t\n\t\t<dc:creator><![CDATA[")->cdata('the_author')
            ->put("]]></dc:creator>\n\t\t<pubDate>", (string) \mysql2date('D, d M Y H:i:s +0000', \get_post_time('Y-m-d H:i:s', true), false), "</pubDate>\n\t\t")
            ->tag('the_category_rss', 'rss2')
            ->put("\t\t<guid isPermaLink=\"false\">")->tag('the_guid')->put("</guid>\n\n")
            ->put("\t\t\t\t\t<description><![CDATA[")->cdata('the_excerpt_rss')->put("]]></description>\n");
        if (Runtime::options()->filtered('rss_use_excerpt')) {
            $w->put("\t\t\n");
        } else {
            $content = \get_the_content_feed('rss2');
            $w->put("\t\t\t\t\t\t\t\t\t\t<content:encoded><![CDATA[");
            strlen($content) > 0 ? $w->put($content) : $w->cdata('the_excerpt_rss');
            $w->put("]]></content:encoded>\n\t\t\t\t\t\n");
        }
        if ($discussed) {
            $w->put("\t\t\t\t\t<wfw:commentRss>", \esc_url(\get_post_comments_feed_link(0, 'rss2')), "</wfw:commentRss>\n\t\t\t<slash:comments>", (string) \get_comments_number(), "</slash:comments>\n");
        }
        $w->put("\t\t\n\t\t")->tag('rss_enclosure')->put("\n\t\t")->act('rss2_item')->put("\t</item>\n\t");
    }

    private static function atom(FeedWriter $w): void
    {
        $w->act('rss_tag_pre', 'atom')
            ->put("<feed\n\txmlns=\"http://www.w3.org/2005/Atom\"\n\txmlns:thr=\"http://purl.org/syndication/thread/1.0\"\n\txml:lang=\"")->tag('bloginfo_rss', 'language')
            ->put("\"\n\t")->act('atom_ns')
            ->put(">\n\t<title type=\"text\">")->tag('wp_title_rss')
            ->put("</title>\n\t<subtitle type=\"text\">")->tag('bloginfo_rss', 'description')
            ->put("</subtitle>\n\n\t<updated>", FeedTags::buildDate('Y-m-d\TH:i:s\Z'), "</updated>\n\n\t<link rel=\"alternate\" type=\"")->tag('bloginfo_rss', 'html_type')
            ->put('" href="')->tag('bloginfo_rss', 'url')
            ->put("\" />\n\t<id>")->tag('bloginfo', 'atom_url')
            ->put("</id>\n\t<link rel=\"self\" type=\"application/atom+xml\" href=\"")->tag('self_link')
            ->put("\" />\n\n\t")->act('atom_head');
        while (\have_posts()) {
            \the_post();
            self::atomEntry($w);
        }
        $w->put("</feed>\n");
    }

    private static function atomEntry(FeedWriter $w): void
    {
        $w->put("\t<entry>\n\t\t<author>\n\t\t\t<name>")->tag('the_author')->put("</name>\n\t\t\t");
        if (!empty(\get_the_author_meta('url'))) {
            $w->put("\t\t\t\t<uri>")->tag('the_author_meta', 'url')->put("</uri>\n\t\t\t\t");
        }
        $w->act('atom_author')
            ->put("\t\t</author>\n\n\t\t<title type=\"html\"><![CDATA[")->cdata('the_title_rss')
            ->put("]]></title>\n\t\t<link rel=\"alternate\" type=\"")->tag('bloginfo_rss', 'html_type')
            ->put('" href="')->tag('the_permalink_rss')
            ->put("\" />\n\n\t\t<id>")->tag('the_guid')
            ->put("</id>\n\t\t<updated>", (string) \get_post_modified_time('Y-m-d\TH:i:s\Z', true), "</updated>\n\t\t<published>", (string) \get_post_time('Y-m-d\TH:i:s\Z', true), "</published>\n\t\t")
            ->tag('the_category_rss', 'atom')
            ->put("\n\t\t<summary type=\"")->tag('html_type_rss')->put('"><![CDATA[')->cdata('the_excerpt_rss')->put("]]></summary>\n\n");
        if (!Runtime::options()->filtered('rss_use_excerpt')) {
            $w->put("\t\t\t\t\t<content type=\"")->tag('html_type_rss')->put('" xml:base="')->tag('the_permalink_rss')
                ->put('"><![CDATA[')->cdata('the_content_feed', 'atom')->put("]]></content>\n");
        }
        $w->put("\t\t\n\t\t")->tag('atom_enclosure')->act('atom_entry');
        if (\get_comments_number() || \comments_open()) {
            $count = (string) \get_comments_number();
            $w->put("\t\t\t<link rel=\"replies\" type=\"")->tag('bloginfo_rss', 'html_type')->put('" href="')->tag('the_permalink_rss')
                ->put("#comments\" thr:count=\"{$count}\" />\n\t\t\t<link rel=\"replies\" type=\"application/atom+xml\" href=\"", \esc_url(\get_post_comments_feed_link(0, 'atom')), "\" thr:count=\"{$count}\" />\n\t\t\t<thr:total>{$count}</thr:total>\n\t\t");
        }
        $w->put("\t</entry>\n\t");
    }

    private static function rdf(FeedWriter $w): void
    {
        $w->act('rss_tag_pre', 'rdf')
            ->put("<rdf:RDF\n\txmlns=\"http://purl.org/rss/1.0/\"\n\txmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n", self::SYNDICATION, "\txmlns:admin=\"http://webns.net/mvcb/\"\n\txmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n\t")
            ->act('rdf_ns')
            ->put(">\n<channel rdf:about=\"")->tag('bloginfo_rss', 'url')
            ->put("\">\n\t<title>")->tag('wp_title_rss')
            ->put("</title>\n\t<link>")->tag('bloginfo_rss', 'url')
            ->put("</link>\n\t<description>")->tag('bloginfo_rss', 'description')
            ->put("</description>\n\t<dc:date>", FeedTags::buildDate('Y-m-d\TH:i:s\Z'), "\t</dc:date>\n");
        self::syndication($w)->put("<sy:updateBase>2000-01-01T12:00+00:00</sy:updateBase>\n\t")->act('rdf_header')->put("\t<items>\n\t\t<rdf:Seq>\n");
        while (\have_posts()) {
            \the_post();
            $w->put("\t\t\t\t\t<rdf:li rdf:resource=\"")->tag('the_permalink_rss')->put("\"/>\n");
        }
        $w->put("\t\t\t\t</rdf:Seq>\n\t</items>\n</channel>\n");
        \rewind_posts();
        while (\have_posts()) {
            \the_post();
            self::rdfItem($w);
        }
        $w->put("</rdf:RDF>\n");
    }

    private static function rdfItem(FeedWriter $w): void
    {
        $w->put('<item rdf:about="')->tag('the_permalink_rss')
            ->put("\">\n\t<title>")->tag('the_title_rss')
            ->put("</title>\n\t<link>")->tag('the_permalink_rss')
            ->put("</link>\n\n\t<dc:creator><![CDATA[")->cdata('the_author')
            ->put("]]></dc:creator>\n\t<dc:date>", (string) \mysql2date('Y-m-d\TH:i:s\Z', (string) \get_post()->post_date_gmt, false), "</dc:date>\n\t")
            ->tag('the_category_rss', 'rdf')
            ->put("\n\t\t\t<description><![CDATA[")->cdata('the_excerpt_rss')->put("]]></description>\n");
        if (!Runtime::options()->filtered('rss_use_excerpt')) {
            $w->put("\t\t<content:encoded><![CDATA[")->cdata('the_content_feed', 'rdf')->put("]]></content:encoded>\n");
        }
        $w->put("\t\n\t")->act('rdf_item')->put("</item>\n");
    }

    private static function rss(FeedWriter $w): void
    {
        $w->put("<rss version=\"0.92\">\n<channel>\n\t<title>")->tag('wp_title_rss')
            ->put("</title>\n\t<link>")->tag('bloginfo_rss', 'url')
            ->put("</link>\n\t<description>")->tag('bloginfo_rss', 'description')
            ->put("</description>\n\t<lastBuildDate>", FeedTags::buildDate('D, d M Y H:i:s +0000'), "</lastBuildDate>\n\t<docs>http://backend.userland.com/rss092</docs>\n\t<language>")->tag('bloginfo_rss', 'language')
            ->put("</language>\n\t")->act('rss_head')->put("\n");
        while (\have_posts()) {
            \the_post();
            $w->put("\t<item>\n\t\t<title>")->tag('the_title_rss')
                ->put("</title>\n\t\t<description><![CDATA[")->cdata('the_excerpt_rss')
                ->put("]]></description>\n\t\t<link>")->tag('the_permalink_rss')
                ->put("</link>\n\t\t")->act('rss_item')->put("\t</item>\n");
        }
        $w->put("</channel>\n</rss>\n");
    }

    /** The heading of a comments feed: the post's, the search's, or the site's. */
    private static function commentsTitle(string $single): string
    {
        if (\is_singular()) {
            return sprintf(\ent2ncr(Gettext::text($single)), \get_the_title_rss());
        }
        if (\is_search()) {
            return sprintf(\ent2ncr(Gettext::text('Comments for %1$s searching on %2$s')), \get_bloginfo_rss('name'), \get_search_query());
        }
        return sprintf(\ent2ncr(Gettext::text('Comments for %s')), \get_wp_title_rss());
    }

    /** A comment's heading in a comments feed: by whom, and on what when the feed is not a single post's. */
    private static function commentTitle(\WP_Post $post): string
    {
        if (\is_singular()) {
            return sprintf(\ent2ncr(Gettext::text('By: %s')), \get_comment_author_rss());
        }
        return sprintf(\ent2ncr(Gettext::text('Comment on %1$s by %2$s')), \apply_filters('the_title_rss', \get_the_title($post->ID)), \get_comment_author_rss());
    }

    /** The comment the loop is on, with its post made the current one. @return array{0: object, 1: \WP_Post} */
    private static function current(): array
    {
        $comment = $GLOBALS['comment'];
        $GLOBALS['post'] = \get_post((int) $comment->comment_post_ID);
        return [$comment, $GLOBALS['post']];
    }

    private static function rss2Comments(FeedWriter $w): void
    {
        $w->act('rss_tag_pre', 'rss2-comments')
            ->put("<rss version=\"2.0\"\n\txmlns:content=\"http://purl.org/rss/1.0/modules/content/\"\n\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n\txmlns:atom=\"http://www.w3.org/2005/Atom\"\n", self::SYNDICATION, "\t")
            ->act('rss2_ns')->put("\n\t")->act('rss2_comments_ns')
            ->put(">\n<channel>\n\t<title>\n\t", self::commentsTitle('Comments on: %s'), "\t</title>\n\t<atom:link href=\"")->tag('self_link')
            ->put("\" rel=\"self\" type=\"application/rss+xml\" />\n\t<link>");
        \is_singular() ? $w->tag('the_permalink_rss') : $w->tag('bloginfo_rss', 'url');
        $w->put("</link>\n\t<description>")->tag('bloginfo_rss', 'description')
            ->put("</description>\n\t<lastBuildDate>", FeedTags::buildDate('r'), "</lastBuildDate>\n");
        self::syndication($w)->act('commentsrss2_head');
        while (\have_comments()) {
            \the_comment();
            self::rss2Comment($w);
        }
        $w->put("</channel>\n</rss>\n");
    }

    private static function rss2Comment(FeedWriter $w): void
    {
        [$comment, $post] = self::current();
        $w->put("\t<item>\n\t\t<title>\n\t\t", self::commentTitle($post), "\t\t</title>\n\t\t<link>")->tag('comment_link')
            ->put("</link>\n\n\t\t<dc:creator><![CDATA[", FeedWriter::insideCdata((string) \get_comment_author_rss()), "]]></dc:creator>\n\t\t<pubDate>", (string) \mysql2date('D, d M Y H:i:s +0000', \get_comment_time('Y-m-d H:i:s', true, false), false), "</pubDate>\n\t\t<guid isPermaLink=\"false\">")->tag('comment_guid')
            ->put("</guid>\n\n");
        if (\post_password_required($post)) {
            $w->put("\t\t\t\t\t<description>", \ent2ncr(Gettext::text('Protected Comments: Please enter your password to view comments.')), "</description>\n\t\t\t<content:encoded><![CDATA[", \get_the_password_form(), "]]></content:encoded>\n");
        } else {
            $w->put("\t\t\t\t\t<description><![CDATA[")->cdata('comment_text_rss')->put("]]></description>\n\t\t\t<content:encoded><![CDATA[")->cdata('comment_text')->put("]]></content:encoded>\n");
        }
        $w->put("\t\t\n\t\t")->act('commentrss2_item', $comment->comment_ID, $post->ID)->put("\t</item>\n\t");
    }

    private static function atomComments(FeedWriter $w): void
    {
        $w->act('rss_tag_pre', 'atom-comments')
            ->put("<feed\n\txmlns=\"http://www.w3.org/2005/Atom\"\n\txml:lang=\"")->tag('bloginfo_rss', 'language')
            ->put("\"\n\txmlns:thr=\"http://purl.org/syndication/thread/1.0\"\n\t")->act('atom_ns')->act('atom_comments_ns')
            ->put(">\n\t<title type=\"text\">\n\t", self::commentsTitle('Comments on %s'), "\t</title>\n\t<subtitle type=\"text\">")->tag('bloginfo_rss', 'description')
            ->put("</subtitle>\n\n\t<updated>", FeedTags::buildDate('Y-m-d\TH:i:s\Z'), "</updated>\n\n\t<link rel=\"alternate\" type=\"")->tag('bloginfo_rss', 'html_type')->put('" href="');
        if (\is_singular()) {
            $self = \esc_url(\get_post_comments_feed_link('', 'atom'));
            $w->tag('comments_link_feed')->put("\" />\n\t<link rel=\"self\" type=\"application/atom+xml\" href=\"{$self}\" />\n\t<id>{$self}</id>\n");
        } else {
            $w->tag('bloginfo_rss', 'url')->put("\" />\n\t<link rel=\"self\" type=\"application/atom+xml\" href=\"")->tag('bloginfo_rss', 'comments_atom_url')
                ->put("\" />\n\t<id>")->tag('bloginfo_rss', 'comments_atom_url')->put("</id>\n");
        }
        $w->act('comments_atom_head');
        while (\have_comments()) {
            \the_comment();
            self::atomComment($w);
        }
        $w->put("</feed>\n");
    }

    private static function atomComment(FeedWriter $w): void
    {
        [$comment, $post] = self::current();
        $when = (string) \mysql2date('Y-m-d\TH:i:s\Z', \get_comment_time('Y-m-d H:i:s', true, false), false);
        $url = \get_comment_author_url();
        $w->put("\t<entry>\n\t\t<title>\n\t\t", self::commentTitle($post), "\t\t</title>\n\t\t<link rel=\"alternate\" href=\"")->tag('comment_link')
            ->put('" type="')->tag('bloginfo_rss', 'html_type')
            ->put("\" />\n\n\t\t<author>\n\t\t\t<name>")->tag('comment_author_rss')
            ->put("</name>\n\t\t\t", $url ? '<uri>' . \esc_url($url) . '</uri>' : '', "\n\t\t</author>\n\n\t\t<id>")->tag('comment_guid')
            ->put("</id>\n\t\t<updated>{$when}</updated>\n\t\t<published>{$when}</published>\n\n\t\t\t\t\t<content type=\"html\" xml:base=\"")->tag('comment_link')->put('"><![CDATA[');
        \post_password_required($post) ? $w->put(FeedWriter::insideCdata(\get_the_password_form())) : $w->cdata('comment_text');
        $w->put("]]></content>\n\t\t\n\t\t\t\t\t<thr:in-reply-to ref=\"");
        if ((int) $comment->comment_parent === 0) {
            $w->tag('the_guid')->put('" href="')->tag('the_permalink_rss');
        } else {
            $w->tag('comment_guid', $comment->comment_parent)->put('" href="', \esc_url(\get_comment_link($comment->comment_parent)));
        }
        $w->put('" type="')->tag('bloginfo_rss', 'html_type')->put("\" />\n\t\t\t")->act('comment_atom_entry', $comment->comment_ID, $post->ID)->put("\t</entry>\n\t");
    }
}
