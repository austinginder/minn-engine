<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\Site;
use Minn\Db;

/**
 * The sitemap index and its providers (posts, pages, categories, tags,
 * authors), in the reference's shape: one file per provider and page, 2000
 * URLs a page, lastmod on content only.
 */
final readonly class Sitemaps
{
    private const PER_PAGE = 2000;

    public function __construct(
        private Db $db,
        private Site $site,
        private Permalinks $permalinks,
    ) {
    }

    public function index(): string
    {
        $entries = '';
        foreach ($this->providers() as $provider) {
            for ($page = 1; $page <= $provider['pages']; $page++) {
                $entries .= '<sitemap><loc>' . $this->permalinks->url("/wp-sitemap-{$provider['slug']}-{$page}.xml") . '</loc></sitemap>';
            }
        }
        return $this->document('wp-sitemap-index.xsl', '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $entries . '</sitemapindex>');
    }

    /** One provider page, or null when the name or page does not exist. */
    public function page(string $type, string $subtype, int $page): ?string
    {
        $urls = match ($type) {
            'posts' => $this->contentUrls($subtype, $page),
            'taxonomies' => $this->termUrls($subtype, $page),
            'users' => $subtype === '' ? $this->userUrls($page) : null,
            default => null,
        };
        if ($urls === null || $urls === []) {
            return null;
        }
        $entries = '';
        foreach ($urls as [$loc, $lastmod]) {
            $entries .= '<url><loc>' . $loc . '</loc>' . ($lastmod === null ? '' : '<lastmod>' . $lastmod . '</lastmod>') . '</url>';
        }
        return $this->document('wp-sitemap.xsl', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $entries . '</urlset>');
    }

    /** @return list<array{slug: string, pages: int}> */
    private function providers(): array
    {
        $providers = [];
        foreach (['post', 'page'] as $type) {
            $count = (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' AND post_password = ''", [$type]);
            if ($type === 'page') {
                $count++;
            }
            if ($count > 0) {
                $providers[] = ['slug' => "posts-{$type}", 'pages' => (int) ceil($count / self::PER_PAGE)];
            }
        }
        foreach (['category', 'post_tag'] as $taxonomy) {
            $count = (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ? AND count > 0", [$taxonomy]);
            if ($count > 0) {
                $providers[] = ['slug' => "taxonomies-{$taxonomy}", 'pages' => (int) ceil($count / self::PER_PAGE)];
            }
        }
        $authors = count($this->authors());
        if ($authors > 0) {
            $providers[] = ['slug' => 'users', 'pages' => (int) ceil($authors / self::PER_PAGE)];
        }
        return $providers;
    }

    /** @return list<array{0: string, 1: ?string}>|null */
    private function contentUrls(string $type, int $page): ?array
    {
        if (!in_array($type, ['post', 'page'], true)) {
            return null;
        }
        $rows = $this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' AND post_password = ''
             ORDER BY post_date ASC, ID ASC LIMIT ? OFFSET ?",
            [$type, self::PER_PAGE, ($page - 1) * self::PER_PAGE],
        );
        $urls = [];
        if ($type === 'page' && $page === 1 && ($this->site->option('show_on_front') ?? 'posts') === 'posts') {
            // The blog front page leads the pages provider, dated by its newest post.
            $latest = (string) ($this->db->value("SELECT MAX(post_modified_gmt) FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish'") ?? '');
            $urls[] = [$this->permalinks->url('/'), $latest === '' ? null : self::iso($latest)];
        }
        foreach ($rows as $post) {
            $urls[] = [$this->permalinks->forPost($post), self::iso((string) $post['post_modified_gmt'])];
        }
        return $urls;
    }

    /** @return list<array{0: string, 1: ?string}>|null */
    private function termUrls(string $taxonomy, int $page): ?array
    {
        if (!in_array($taxonomy, ['category', 'post_tag'], true)) {
            return null;
        }
        $rows = $this->db->rows(
            "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND tt.count > 0 ORDER BY t.term_id ASC LIMIT ? OFFSET ?",
            [$taxonomy, self::PER_PAGE, ($page - 1) * self::PER_PAGE],
        );
        return array_map(fn (array $term) => [$this->permalinks->forTerm($term), null], $rows);
    }

    /** @return list<array{0: string, 1: ?string}> */
    private function userUrls(int $page): array
    {
        $authors = array_slice($this->authors(), ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        return array_map(fn (array $user) => [$this->permalinks->forAuthor($user), null], $authors);
    }

    /** Users with published posts, by id. @return list<array> */
    private function authors(): array
    {
        return $this->db->rows(
            "SELECT u.ID, u.user_nicename FROM {$this->db->table('users')} u
             WHERE u.ID IN (SELECT post_author FROM {$this->db->table('posts')} WHERE post_type = 'post' AND post_status = 'publish')
             ORDER BY u.ID ASC",
        );
    }

    private function document(string $stylesheet, string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?xml-stylesheet type="text/xsl" href="' . $this->permalinks->url('/' . $stylesheet) . '" ?>' . "\n"
            . $body . "\n";
    }

    /** The engine's own stylesheet for browsers that open a sitemap. */
    public static function stylesheet(bool $index): string
    {
        $rows = $index
            ? '<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap"><tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td></tr></xsl:for-each>'
            : '<xsl:for-each select="sitemap:urlset/sitemap:url"><tr><td><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc"/></a></td><td><xsl:value-of select="sitemap:lastmod"/></td></tr></xsl:for-each>';
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" exclude-result-prefixes="sitemap">' . "\n"
            . '<xsl:output method="html" encoding="UTF-8" indent="yes"/>' . "\n"
            . '<xsl:template match="/"><html><head><title>XML Sitemap</title>'
            . '<style>body{font:15px/1.5 sans-serif;margin:2em}table{border-collapse:collapse}td{padding:.35em 1em .35em 0;border-bottom:1px solid #ddd}</style>'
            . '</head><body><h1>XML Sitemap</h1><table>' . $rows . '</table></body></html></xsl:template>' . "\n"
            . '</xsl:stylesheet>' . "\n";
    }

    private static function iso(string $gmt): string
    {
        return gmdate('Y-m-d\TH:i:s', (int) strtotime($gmt . ' UTC')) . '+00:00';
    }
}
