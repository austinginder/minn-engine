<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Content\Blocks;
use Minn\Content\Comments;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\Site;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;
use Minn\Support\Html;
use Minn\Content\PasswordGate;
use Minn\Extension\Extensions;
use Minn\Runtime\Runtime;

/**
 * The syndication feeds, byte for byte in the reference's shape: RSS 2.0
 * for the site, its archives, and comments; Atom and RDF for the site.
 * The whitespace inside each item is part of the captured output and is
 * reproduced as-is.
 */
final readonly class Feeds
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Posts $posts,
        private Comments $comments,
        private Users $users,
        private Permalinks $permalinks,
        private string $generatorVersion,
    ) {
    }

    public static function contentType(string $kind): string
    {
        return match ($kind) {
            'atom' => 'application/atom+xml; charset=UTF-8',
            'rdf' => 'application/rdf+xml; charset=UTF-8',
            default => 'application/rss+xml; charset=UTF-8',
        };
    }

    /** The site feed, or an archive's, in the chosen kind. @param list<array> $posts */
    public function posts(array $posts, string $kind, string $selfUrl, string $title): string
    {
        return match ($kind) {
            'atom' => $this->atom($posts, $selfUrl, $title),
            'rdf' => $this->rdf($posts, $title),
            default => $this->rss2($posts, $selfUrl, $title),
        };
    }

    private function rss2(array $posts, string $selfUrl, string $title): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"' . "\n"
            . "\t" . 'xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n"
            . "\t" . 'xmlns:wfw="http://wellformedweb.org/CommentAPI/"' . "\n"
            . "\t" . 'xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n"
            . "\t" . 'xmlns:atom="http://www.w3.org/2005/Atom"' . "\n"
            . "\t" . 'xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"' . "\n"
            . "\t" . 'xmlns:slash="http://purl.org/rss/1.0/modules/slash/"' . "\n"
            . "\t>\n\n<channel>\n"
            . "\t<title>" . $title . "</title>\n"
            . "\t" . '<atom:link href="' . Html::attr($selfUrl) . '" rel="self" type="application/rss+xml" />' . "\n"
            . "\t<link>" . $this->permalinks->url('') . "</link>\n"
            . "\t<description>" . Html::esc((string) ($this->site->option('blogdescription') ?? '')) . "</description>\n"
            . "\t<lastBuildDate>" . self::rfc2822($this->latestModified($posts)) . "</lastBuildDate>\n"
            . "\t<language>" . $this->language() . "</language>\n"
            . "\t<sy:updatePeriod>\n\thourly\t</sy:updatePeriod>\n"
            . "\t<sy:updateFrequency>\n\t1\t</sy:updateFrequency>\n"
            . "\t<generator>https://wordpress.org/?v=" . $this->generatorVersion . "</generator>\n";
        foreach ($posts as $post) {
            $out .= "\t<item>\n" . $this->rssItem($post) . "\n\t\t\t</item>\n\t";
        }
        return $out . "</channel>\n</rss>\n";
    }

    private function rssItem(array|PostRecord $post): string
    {
        $link = $this->permalinks->forPost($post);
        $count = $this->commentCount((int) $post['ID']);
        $lines = "\t\t<title>" . self::title(PasswordGate::title($post)) . "</title>\n"
            . "\t\t<link>" . $link . "</link>\n";
        if ($post['comment_status'] === 'open' || $count > 0) {
            $lines .= "\t\t\t\t\t<comments>" . $link . ($count > 0 ? '#comments' : '#respond') . "</comments>\n";
        }
        $lines .= "\t\t\n"
            . "\t\t<dc:creator><![CDATA[" . self::cdata($this->authorName($post)) . "]]></dc:creator>\n"
            . "\t\t<pubDate>" . self::rfc2822((string) $post['post_date_gmt']) . "</pubDate>\n";
        $first = true;
        foreach ($this->termNames($post) as $name) {
            $lines .= ($first ? "\t\t\t\t" : "\t\t") . '<category><![CDATA[' . self::cdata($name) . "]]></category>\n";
            $first = false;
        }
        $lines .= "\t\t" . '<guid isPermaLink="false">' . $post['guid'] . "</guid>\n\n"
            . "\t\t\t\t\t<description><![CDATA[" . self::cdata($this->plainExcerpt($post)) . "]]></description>\n"
            . "\t\t\t\t\t\t\t\t\t\t<content:encoded><![CDATA[" . self::cdata($this->content($post)) . "]]></content:encoded>\n"
            . "\t\t\t\t\t\n"
            . "\t\t\t\t\t<wfw:commentRss>" . $link . "feed/</wfw:commentRss>\n"
            . "\t\t\t<slash:comments>" . $count . "</slash:comments>\n"
            . "\t\t\n\t\t";
        return $lines;
    }

    private function atom(array $posts, string $selfUrl, string $title): string
    {
        $home = $this->permalinks->url('');
        $out = '<?xml version="1.0" encoding="UTF-8"?><feed' . "\n"
            . "\t" . 'xmlns="http://www.w3.org/2005/Atom"' . "\n"
            . "\t" . 'xmlns:thr="http://purl.org/syndication/thread/1.0"' . "\n"
            . "\t" . 'xml:lang="' . $this->language() . '"' . "\n"
            . "\t>\n"
            . "\t" . '<title type="text">' . $title . "</title>\n"
            . "\t" . '<subtitle type="text">' . Html::esc((string) ($this->site->option('blogdescription') ?? '')) . "</subtitle>\n\n"
            . "\t<updated>" . self::isoZ($this->latestModified($posts)) . "</updated>\n\n"
            . "\t" . '<link rel="alternate" type="text/html" href="' . $home . '" />' . "\n"
            . "\t<id>" . Html::esc($selfUrl) . "</id>\n"
            . "\t" . '<link rel="self" type="application/atom+xml" href="' . Html::attr($selfUrl) . '" />' . "\n\n"
            . "\t" . '<generator uri="https://wordpress.org/" version="' . $this->generatorVersion . '">WordPress</generator>' . "\n";
        foreach ($posts as $post) {
            $link = $this->permalinks->forPost($post);
            $count = $this->commentCount((int) $post['ID']);
            $user = $this->users->find((int) $post['post_author']);
            $out .= "\t<entry>\n\t\t<author>\n\t\t\t<name>" . Html::esc((string) ($user['display_name'] ?? '')) . "</name>\n";
            $out .= ($user !== null && (string) $user['user_url'] !== '')
                ? "\t\t\t\t\t\t\t<uri>" . Html::esc((string) $user['user_url']) . "</uri>\n\t\t\t\t\t\t</author>\n\n"
                : "\t\t\t\t\t</author>\n\n";
            $out .= "\t\t" . '<title type="html"><![CDATA[' . Texturize::text((string) $post['post_title']) . "]]></title>\n"
                . "\t\t" . '<link rel="alternate" type="text/html" href="' . $link . '" />' . "\n\n"
                . "\t\t<id>" . $post['guid'] . "</id>\n"
                . "\t\t<updated>" . self::isoZ((string) $post['post_modified_gmt']) . "</updated>\n"
                . "\t\t<published>" . self::isoZ((string) $post['post_date_gmt']) . "</published>\n";
            $terms = $this->termNames($post);
            if ($terms !== []) {
                // Atom categories share one line.
                $out .= "\t\t" . implode('', array_map(static fn (string $name) => '<category scheme="' . $home . '" term="' . Html::attr($name) . '" />', $terms)) . "\n";
            }
            $out .= "\t\t" . '<summary type="html"><![CDATA[' . $this->plainExcerpt($post) . "]]></summary>\n\n"
                . "\t\t\t\t\t" . '<content type="html" xml:base="' . $link . '"><![CDATA[' . $this->content($post) . "]]></content>\n"
                . "\t\t\n"
                . "\t\t\t\t\t" . '<link rel="replies" type="text/html" href="' . $link . '#comments" thr:count="' . $count . '" />' . "\n"
                . "\t\t\t" . '<link rel="replies" type="application/atom+xml" href="' . $link . 'feed/atom/" thr:count="' . $count . '" />' . "\n"
                . "\t\t\t<thr:total>" . $count . "</thr:total>\n"
                . "\t\t\t</entry>\n\t";
        }
        return $out . "</feed>\n";
    }

    private function rdf(array $posts, string $title): string
    {
        $home = $this->permalinks->url('');
        $out = '<?xml version="1.0" encoding="UTF-8"?><rdf:RDF' . "\n"
            . "\t" . 'xmlns="http://purl.org/rss/1.0/"' . "\n"
            . "\t" . 'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"' . "\n"
            . "\t" . 'xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n"
            . "\t" . 'xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"' . "\n"
            . "\t" . 'xmlns:admin="http://webns.net/mvcb/"' . "\n"
            . "\t" . 'xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n"
            . "\t>\n"
            . '<channel rdf:about="' . $home . '">' . "\n"
            . "\t<title>" . $title . "</title>\n"
            . "\t<link>" . $home . "</link>\n"
            . "\t<description>" . Html::esc((string) ($this->site->option('blogdescription') ?? '')) . "</description>\n"
            . "\t<dc:date>" . self::isoZ($this->latestModified($posts)) . "\t</dc:date>\n"
            . "\t<sy:updatePeriod>\n\thourly\t</sy:updatePeriod>\n"
            . "\t<sy:updateFrequency>\n\t1\t</sy:updateFrequency>\n"
            . "\t<sy:updateBase>2000-01-01T12:00+00:00</sy:updateBase>\n"
            . "\t" . '<admin:generatorAgent rdf:resource="https://wordpress.org/?v=' . $this->generatorVersion . '" />' . "\n"
            . "\t<items>\n\t\t<rdf:Seq>\n";
        foreach ($posts as $post) {
            $out .= "\t\t\t\t\t" . '<rdf:li rdf:resource="' . $this->permalinks->forPost($post) . '"/>' . "\n";
        }
        $out .= "\t\t\t\t</rdf:Seq>\n\t</items>\n</channel>\n";
        foreach ($posts as $post) {
            $link = $this->permalinks->forPost($post);
            $out .= '<item rdf:about="' . $link . '">' . "\n"
                . "\t<title>" . self::title(PasswordGate::title($post)) . "</title>\n"
                . "\t<link>" . $link . "</link>\n\n"
                . "\t<dc:creator><![CDATA[" . self::cdata($this->authorName($post)) . "]]></dc:creator>\n"
                . "\t<dc:date>" . self::isoZ((string) $post['post_date_gmt']) . "</dc:date>\n";
            $first = true;
            foreach ($this->termNames($post) as $name) {
                $out .= ($first ? "\t\t\t" : "\t\t") . '<dc:subject><![CDATA[' . self::cdata($name) . "]]></dc:subject>\n";
                $first = false;
            }
            $out .= "\n\t\t\t<description><![CDATA[" . self::cdata($this->plainExcerpt($post)) . "]]></description>\n"
                . "\t\t<content:encoded><![CDATA[" . self::cdata($this->content($post)) . "]]></content:encoded>\n"
                . "\t\n\t</item>\n";
        }
        return $out . "</rdf:RDF>\n";
    }

    /** The site's or one post's comments as RSS 2.0. */
    public function comments(array|PostRecord|null $post, string $selfUrl): string
    {
        $comments = $post === null
            ? $this->db->rows("SELECT c.* FROM {$this->db->table('comments')} c INNER JOIN {$this->db->table('posts')} p ON p.ID = c.comment_post_ID AND p.post_status = 'publish' AND p.post_password = '' WHERE c.comment_approved = '1' AND c.comment_type IN ('', 'comment') ORDER BY c.comment_date_gmt DESC LIMIT ?", [$this->perFeed()])
            : $this->db->rows("SELECT * FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_approved = '1' AND comment_type IN ('', 'comment') ORDER BY comment_date_gmt ASC", [(int) $post['ID']]);
        $siteName = Html::esc((string) ($this->site->option('blogname') ?? ''));
        $title = $post === null ? 'Comments for ' . $siteName : 'Comments on: ' . self::title(PasswordGate::title($post));
        $latest = '';
        foreach ($comments as $comment) {
            $latest = max($latest, (string) $comment['comment_date_gmt']);
        }
        $out = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"' . "\n"
            . "\t" . 'xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n"
            . "\t" . 'xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n"
            . "\t" . 'xmlns:atom="http://www.w3.org/2005/Atom"' . "\n"
            . "\t" . 'xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"' . "\n"
            . "\t\n\t>\n<channel>\n"
            . "\t<title>\n\t" . $title . "\t</title>\n"
            . "\t" . '<atom:link href="' . Html::attr($selfUrl) . '" rel="self" type="application/rss+xml" />' . "\n"
            . "\t<link>" . ($post === null ? $this->permalinks->url('') : $this->permalinks->forPost($post)) . "</link>\n"
            . "\t<description>" . Html::esc((string) ($this->site->option('blogdescription') ?? '')) . "</description>\n"
            . "\t<lastBuildDate>" . self::rfc2822($latest !== '' ? $latest : gmdate('Y-m-d H:i:s')) . "</lastBuildDate>\n"
            . "\t<sy:updatePeriod>\n\thourly\t</sy:updatePeriod>\n"
            . "\t<sy:updateFrequency>\n\t1\t</sy:updateFrequency>\n"
            . "\t<generator>https://wordpress.org/?v=" . $this->generatorVersion . "</generator>\n";
        foreach ($comments as $comment) {
            $target = $post ?? $this->posts->find((int) $comment['comment_post_ID']);
            $author = Html::esc((string) $comment['comment_author']);
            $heading = $post === null
                ? 'Comment on ' . self::title((string) ($target['post_title'] ?? '')) . ' by ' . $author
                : 'By: ' . $author;
            $link = ($target === null ? '' : $this->permalinks->forPost($target)) . '#comment-' . (int) $comment['comment_ID'];
            $out .= "\t<item>\n"
                . "\t\t<title>\n\t\t" . $heading . "\t\t</title>\n"
                . "\t\t<link>" . $link . "</link>\n\n"
                . "\t\t<dc:creator><![CDATA[" . self::cdata($comment['comment_author']) . "]]></dc:creator>\n"
                . "\t\t<pubDate>" . self::rfc2822((string) $comment['comment_date_gmt']) . "</pubDate>\n"
                . "\t\t" . '<guid isPermaLink="false">' . ($target['guid'] ?? '') . '#comment-' . (int) $comment['comment_ID'] . "</guid>\n\n"
                . "\t\t\t\t\t<description><![CDATA[" . self::cdata(Html::esc((string) $comment['comment_content'])) . "]]></description>\n"
                . "\t\t\t<content:encoded><![CDATA[" . self::cdata(Blocks::paragraphs((string) $comment['comment_content'])) . "]]></content:encoded>\n"
                . "\t\t\n"
                . "\t\t\t</item>\n\t";
        }
        return $out . "</channel>\n</rss>\n";
    }

    /** Post content as a feed carries it: whole, with the more tag as its anchor. */
    private function content(array|PostRecord $post): string
    {
        if (PasswordGate::is($post)) {
            return PasswordGate::form($post, $this->permalinks->url(''), $this->permalinks->forPost($post));
        }
        $content = Blocks::render(str_replace('<!--more-->', '<span id="more-' . (int) $post['ID'] . '"></span>', (string) $post['post_content']));
        $seams = Extensions::runner();
        if ($seams !== null) {
            $content = $seams->filterContent($content, $post);
        }
        if (Runtime::booted()) {
            $content = Runtime::shortcodes()->apply($content);
            $content = (string) Runtime::hooks()->filter('the_content', [$content]);
        }
        return $content;
    }

    public function perFeed(): int
    {
        return max(1, (int) ($this->site->option('posts_per_rss') ?? 10));
    }

    private function latestModified(array $posts): string
    {
        $latest = '';
        foreach ($posts as $post) {
            $latest = max($latest, (string) $post['post_modified_gmt']);
        }
        return $latest !== '' ? $latest : gmdate('Y-m-d H:i:s');
    }

    private function commentCount(int $postId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_approved = '1'", [$postId]);
    }

    private function authorName(array|PostRecord $post): string
    {
        return (string) ($this->users->find((int) $post['post_author'])['display_name'] ?? '');
    }

    /** Category names then tag names. @return list<string> */
    private function termNames(array|PostRecord $post): array
    {
        $names = [];
        foreach (['category', 'post_tag'] as $taxonomy) {
            foreach ($this->posts->terms((int) $post['ID'], $taxonomy) as [$termId]) {
                $name = $this->db->value("SELECT name FROM {$this->db->table('terms')} WHERE term_id = ? LIMIT 1", [$termId]);
                if ($name !== null) {
                    $names[] = (string) $name;
                }
            }
        }
        return $names;
    }

    /** Text inside a CDATA section: its own terminator split so the section cannot be closed early. */
    private static function cdata(string $text): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $text);
    }

    private function plainExcerpt(array|PostRecord $post): string
    {
        if (PasswordGate::is($post)) {
            return PasswordGate::EXCERPT;
        }
        return trim(strip_tags(Excerpt::render($post, stopAtMore: false, forFeed: true)));
    }

    private function language(): string
    {
        $locale = (string) ($this->site->option('WPLANG') ?: 'en_US');
        return str_replace('_', '-', $locale);
    }

    /** A title as feeds carry it: texturized, then escaped without touching the entities that made. */
    private static function title(string $raw): string
    {
        return htmlspecialchars(Texturize::text($raw), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    public static function rfc2822(string $gmt): string
    {
        return gmdate('D, d M Y H:i:s', (int) strtotime($gmt . ' UTC')) . ' +0000';
    }

    public static function isoZ(string $gmt): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($gmt . ' UTC'));
    }
}
