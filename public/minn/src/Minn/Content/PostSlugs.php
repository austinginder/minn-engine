<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;
use Minn\Db;
use Minn\Runtime\Runtime;

/**
 * Which slug a live post may take beside the others, as the reference
 * settles it (probe insert-defaults). A flat type's slugs are its own: a
 * post, a page and a plugin's type may share one. A hierarchical type's
 * are its own and its attachments' under the same parent. An attachment's
 * are every post's. Never free: the feed names and embed; for a
 * hierarchical type, a number or a page number (2, page2); for posts, a
 * number a date archive would answer to: any number when the permalink
 * structure begins with the post name, a month (below 13) when the post
 * name follows the year, a day (below 32) when it follows the month. A post
 * keeps a number it already has. A taken slug gets the first free -2, -3
 * form, cut (and stripped of a trailing hyphen) to fit the column's 200
 * characters. The slug is taken as given, not sanitized.
 */
final readonly class PostSlugs
{
    private const LENGTH = 200;
    private const FEEDS = ['feed', 'rdf', 'rss', 'rss2', 'atom'];

    public function __construct(private Db $db, private Site $site)
    {
    }

    /**
     * The slug, or its first free numbered form.
     *
     * @param Closure(string): bool|null $bad a plugin's say over the slug asked for (the bad-slug filters), asked last
     */
    public function unique(string $slug, int $excludeId, string $type, int $parent, ?Closure $bad = null): string
    {
        if ($slug === '' || (!$this->taken($slug, $excludeId, $type, $parent) && !$this->reserved($slug, $excludeId, $type) && ($bad === null || !$bad($slug)))) {
            return $slug;
        }
        for ($n = 2; ; $n++) {
            $candidate = rtrim(substr($slug, 0, self::LENGTH - strlen("-{$n}")), '-') . "-{$n}";
            if (!$this->taken($candidate, $excludeId, $type, $parent)) {
                return $candidate;
            }
        }
    }

    /** Whether another post in the slug's scope has it. */
    private function taken(string $slug, int $excludeId, string $type, int $parent): bool
    {
        $posts = $this->db->table('posts');
        if ($type === 'attachment') {
            return $this->db->value("SELECT ID FROM {$posts} WHERE post_name = ? AND ID <> ? LIMIT 1", [$slug, $excludeId]) !== null;
        }
        if ($this->hierarchical($type)) {
            return $this->db->value("SELECT ID FROM {$posts} WHERE post_name = ? AND post_type IN (?, 'attachment') AND ID <> ? AND post_parent = ? LIMIT 1", [$slug, $type, $excludeId, $parent]) !== null;
        }
        return $this->db->value("SELECT ID FROM {$posts} WHERE post_name = ? AND post_type = ? AND ID <> ? LIMIT 1", [$slug, $type, $excludeId]) !== null;
    }

    /** Whether the slug names something else: a feed, embeds, a page number, a date archive. */
    private function reserved(string $slug, int $excludeId, string $type): bool
    {
        $rewrite = Runtime::booted() ? ($GLOBALS['wp_rewrite'] ?? null) : null;
        if (in_array($slug, (array) ($rewrite->feeds ?? self::FEEDS), true) || $slug === 'embed') {
            return true;
        }
        if ($type === 'attachment') {
            return false;
        }
        if ($this->hierarchical($type)) {
            return preg_match('/^(' . preg_quote((string) ($rewrite->pagination_base ?? 'page'), '/') . ')?\d+$/', $slug) === 1;
        }
        if ($type !== 'post' || !preg_match('/^\d+$/', $slug) || $slug === '0') {
            return false;
        }
        if ($excludeId > 0 && $this->db->value("SELECT post_name FROM {$this->db->table('posts')} WHERE ID = ?", [$excludeId]) === $slug) {
            return false;
        }
        $segments = array_values(array_filter(explode('/', (string) ($this->site->option('permalink_structure') ?? ''))));
        $at = array_search('%postname%', $segments, true);
        return match (true) {
            $at === 0 => true,
            $at === false => false,
            $segments[$at - 1] === '%year%' => (int) $slug < 13,
            $segments[$at - 1] === '%monthnum%' => (int) $slug < 32,
            default => false,
        };
    }

    private function hierarchical(string $type): bool
    {
        return Runtime::booted() && \function_exists('is_post_type_hierarchical') ? \is_post_type_hierarchical($type) : $type === 'page';
    }
}
