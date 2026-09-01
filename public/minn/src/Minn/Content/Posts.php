<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;
use Minn\Content\Reader;

/**
 * Reads over the posts table. A single post comes back as a PostRecord and
 * a listing as a Page of them; rendering and escaping happen elsewhere.
 */
final readonly class Posts
{
    public function __construct(private Db $db)
    {
    }

    /** @param array<string, mixed>|null $row */
    private static function record(?array $row): ?PostRecord
    {
        return $row === null ? null : PostRecord::fromRow($row);
    }

    public function find(int $id): ?PostRecord
    {
        return self::record($this->db->row("SELECT * FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$id]));
    }

    /** @param list<string> $types */
    public function findByName(string $name, array $types, bool $publishedOnly = true): ?PostRecord
    {
        $status = $publishedOnly ? "AND post_status = 'publish'" : "AND post_status <> 'trash'";
        return self::record($this->db->row(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_name = ? AND post_type IN (?) {$status}
             ORDER BY post_date DESC LIMIT 1",
            [$name, $types],
        ));
    }

    /**
     * The post that once answered to the slug: every former slug stays in
     * `_wp_old_slug` meta, and the reference redirects it to the current
     * link whatever the post's status (a draft or trashed post goes to
     * its `?p=` form).
     *
     * @param list<string> $types
     */
    public function byOldSlug(string $slug, array $types): ?PostRecord
    {
        return self::record($this->db->row(
            "SELECT p.* FROM {$this->db->table('posts')} p
             INNER JOIN {$this->db->table('postmeta')} m ON m.post_id = p.ID
             WHERE m.meta_key = '_wp_old_slug' AND m.meta_value = ? AND p.post_type IN (?)
             ORDER BY p.ID LIMIT 1",
            [$slug, $types],
        ));
    }

    /**
     * Walks a page hierarchy: ["sample-page", "docs"] finds the page named
     * docs whose parent is named sample-page at the root.
     *
     * @param list<string> $segments
     */
    public function pageByPath(array $segments, bool $publishedOnly = true): ?PostRecord
    {
        $parent = 0;
        $page = null;
        $status = $publishedOnly ? "AND post_status = 'publish'" : "AND post_status <> 'trash'";
        foreach ($segments as $segment) {
            $page = self::record($this->db->row(
                "SELECT * FROM {$this->db->table('posts')}
                 WHERE post_name = ? AND post_type = 'page' AND post_parent = ? {$status} LIMIT 1",
                [$segment, $parent],
            ));
            if ($page === null) {
                return null;
            }
            $parent = $page->id;
        }
        return $page;
    }

    /** The slash-joined ancestry of a page: "sample-page/docs". */
    public function pathOf(PostRecord $page): string
    {
        $parts = [$page->slug];
        $parentId = $page->parentId;
        while ($parentId > 0) {
            $parent = $this->db->row(
                "SELECT ID, post_name, post_parent FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1",
                [$parentId],
            );
            if ($parent === null) {
                break;
            }
            array_unshift($parts, $parent['post_name']);
            $parentId = (int) $parent['post_parent'];
        }
        return implode('/', $parts);
    }

    /**
     * The closest published post or page whose name starts with the given
     * text: pages first, then posts, newest first within each. This is the
     * order the reference follows when it guesses a destination for a
     * missing URL.
     */
    public function guess(string $prefix): ?PostRecord
    {
        foreach (['page', 'post'] as $type) {
            $exact = $this->findByName($prefix, [$type]);
            if ($exact !== null) {
                return $exact;
            }
            $like = $this->db->row(
                "SELECT * FROM {$this->db->table('posts')}
                 WHERE post_name LIKE ? AND post_type = ? AND post_status = 'publish'
                 ORDER BY post_date DESC LIMIT 1",
                [addcslashes($prefix, '%_\\') . '%', $type],
            );
            if ($like !== null) {
                return self::record($like);
            }
        }
        return null;
    }

    /**
     * A page of published posts for an archive.
     *
     * @param array{term?: int, author?: int, from?: string, to?: string, search?: string, types?: list<string>} $filter
     * The sticky posts first, then the page, as the reference fills page one.
     */
    /** The published posts of one type, newest first: the everyday listing. */
    public function published(string $type = 'post', int $page = 1, int $perPage = 10): Page
    {
        return $this->archive(PostFilter::types($type), $page, $perPage);
    }

    /** How many posts a filter reaches, without fetching any. */
    public function count(PostFilter $filter): int
    {
        [$from, $params] = $this->scope($filter);
        return (int) $this->db->value("SELECT COUNT(DISTINCT p.ID) {$from}", $params);
    }

    public function archive(PostFilter $filter, int $page, int $perPage): Page
    {
        [$from, $params] = $this->scope($filter);
        $total = (int) $this->db->value("SELECT COUNT(DISTINCT p.ID) {$from}", $params);
        // Search results rank title matches first, as the reference does.
        $order = 'p.post_date DESC, p.ID DESC';
        if ($filter->search !== null) {
            $order = '(p.post_title LIKE ?) DESC, ' . $order;
            $params[] = self::like($filter->search);
        }
        $rows = $this->db->rows(
            "SELECT DISTINCT p.* {$from} ORDER BY {$order} LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage],
        );
        return new Page(PostRecord::fromRows($rows), $total);
    }

    /**
     * The FROM ... WHERE half of a listing query and its parameters: what
     * the reader may see, narrowed by the filter.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function scope(PostFilter $filter): array
    {
        $where = ['p.post_type IN (?)', 'p.post_status IN (?)'];
        $params = [$filter->types, Reader::current()->listableStatuses('post')];
        $join = '';
        if ($filter->term !== null) {
            $join = "INNER JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID";
            $where[] = 'tr.term_taxonomy_id = ?';
            $params[] = $filter->term;
        }
        if ($filter->author !== null) {
            $where[] = 'p.post_author = ?';
            $params[] = $filter->author;
        }
        if ($filter->hasDates()) {
            $where[] = 'p.post_date >= ? AND p.post_date < ?';
            array_push($params, $filter->from, $filter->to);
        }
        if ($filter->search !== null) {
            $needle = self::like($filter->search);
            $where[] = '(p.post_title LIKE ? OR p.post_content LIKE ? OR p.post_excerpt LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        return ["FROM {$this->db->table('posts')} p {$join} WHERE " . implode(' AND ', $where), $params];
    }

    private static function like(string $needle): string
    {
        return '%' . addcslashes($needle, '%_\\') . '%';
    }

    public function meta(int $postId, string $key): ?string
    {
        $value = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('postmeta')} WHERE post_id = ? AND meta_key = ? LIMIT 1",
            [$postId, $key],
        );
        return $value === null ? null : (string) $value;
    }

    /** @return list<array{0: int, 1: string}> term id and slug pairs, by name */
    public function terms(int $postId, string $taxonomy): array
    {
        $rows = $this->db->rows(
            "SELECT t.term_id, t.slug FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = ?
             ORDER BY t.name ASC",
            [$postId, $taxonomy],
        );
        return array_map(static fn (array $row) => [(int) $row['term_id'], (string) $row['slug']], $rows);
    }

    public function revisionCount(int $postId): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'revision' AND post_parent = ?",
            [$postId],
        );
    }

    /** The latest plain (non-autosave) revision id, or 0. */
    public function latestRevisionId(int $postId): int
    {
        return (int) ($this->db->value(
            "SELECT ID FROM {$this->db->table('posts')}
             WHERE post_type = 'revision' AND post_parent = ? AND post_name NOT LIKE ?
             ORDER BY ID DESC LIMIT 1",
            [$postId, $postId . '-autosave%'],
        ) ?? 0);
    }

    /** Whether a live post carries an autosave newer than its saved state. */
    public function hasNewerAutosave(int $postId, string $modifiedGmt): bool
    {
        return $this->db->value(
            "SELECT 1 FROM {$this->db->table('posts')}
             WHERE post_parent = ? AND post_type = 'revision' AND post_name LIKE ? AND post_modified_gmt > ? LIMIT 1",
            [$postId, $postId . '-autosave%', $modifiedGmt],
        ) !== null;
    }

    /** The adjacent published post by date; previous = older, next = newer. */
    public function adjacent(PostRecord $post, bool $next): ?PostRecord
    {
        $operator = $next ? '>' : '<';
        $order = $next ? 'ASC' : 'DESC';
        return self::record($this->db->row(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_type = ? AND post_status = 'publish'
               AND (post_date {$operator} ? OR (post_date = ? AND ID {$operator} ?))
             ORDER BY post_date {$order}, ID {$order} LIMIT 1",
            [$post->type, $post->date, $post->date, $post->id],
        ));
    }

    /** Published pages as a parent => children map, ordered by menu_order then title. */
    public function pageTree(): array
    {
        $tree = [];
        $rows = $this->db->rows(
            "SELECT ID, post_title, post_name, post_parent FROM {$this->db->table('posts')}
             WHERE post_type = 'page' AND post_status = 'publish' ORDER BY menu_order ASC, post_title ASC",
        );
        foreach ($rows as $row) {
            $tree[(int) $row['post_parent']][] = $row;
        }
        return $tree;
    }

    /**
     * The main query for a listing: sticky posts lead the first page of the
     * blog index, followed by the rest by date, and are excluded from later
     * pages.
     *
     * @param list<int> $stickyIds
     * @return array{posts: list<array>, total: int}
     */
    public function listing(PostFilter $filter, int $page, int $perPage, array $stickyIds = [], bool $stickyExtra = false): Page
    {
        if ($stickyIds === []) {
            return $this->archive($filter, $page, $perPage);
        }
        $sticky = PostRecord::fromRows($this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE ID IN (?) AND post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC",
            [$stickyIds],
        ));
        $result = $this->archive($filter, $page, $perPage);
        if ($page > 1) {
            return $result;
        }
        $stickySet = array_flip(array_map(static fn (PostRecord $p) => $p->id, $sticky));
        $notSticky = static fn (PostRecord $p): bool => !isset($stickySet[$p->id]);
        if ($stickyExtra) {
            // A query block keeps its full page of posts and adds the sticky ones on top.
            $others = $this->archive($filter, 1, $perPage + count($sticky));
            $rest = array_slice(array_values(array_filter($others->posts, $notSticky)), 0, $perPage);
            return $others->withPosts([...$sticky, ...$rest]);
        }
        $rest = array_values(array_filter($result->posts, $notSticky));
        return $result->withPosts(array_slice([...$sticky, ...$rest], 0, $perPage));
    }

    /**
     * The newest modification time among published posts, for
     * get_lastpostmodified: one type or all of them, blog or GMT column.
     */
    public function lastModified(?string $type, bool $gmt): ?string
    {
        $column = $gmt ? 'post_modified_gmt' : 'post_modified';
        $sql = "SELECT MAX({$column}) FROM {$this->db->table('posts')} WHERE post_status = 'publish'";
        $params = [];
        if ($type !== null && $type !== 'any') {
            $sql .= ' AND post_type = ?';
            $params[] = $type;
        } else {
            $sql .= " AND post_type IN ('post', 'page', 'attachment')";
        }
        $value = $this->db->value($sql, $params);
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** The newest autosave of a post by one author, or null. */
    public function newestAutosave(int $postId, int $userId): ?PostRecord
    {
        return self::record($this->db->row(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_name LIKE ? AND post_author = ?
             ORDER BY post_modified DESC, ID DESC LIMIT 1",
            [$postId, $postId . '-autosave%', $userId],
        ));
    }

    /** Reusable blocks (wp_block rows) in one status, newest first, capped at 100. */
    public function blocks(string $status): array
    {
        return $this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_type = 'wp_block' AND post_status = ?
             ORDER BY post_date DESC LIMIT 100",
            [$status],
        );
    }

    public function firstCategorySlug(int $postId): ?string
    {
        $slug = $this->db->value(
            "SELECT t.slug FROM {$this->db->table('term_relationships')} tr
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'category'
             ORDER BY t.name ASC LIMIT 1",
            [$postId],
        );
        return $slug === null ? null : (string) $slug;
    }
}
