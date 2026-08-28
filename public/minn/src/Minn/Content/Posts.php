<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;
use Minn\Content\Reader;

/**
 * Reads over the posts table for the public front end. Every method
 * returns raw rows; rendering and escaping happen elsewhere.
 */
final readonly class Posts
{
    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$id]);
    }

    /** @param list<string> $types */
    public function findByName(string $name, array $types, bool $publishedOnly = true): ?array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $status = $publishedOnly ? "AND post_status = 'publish'" : "AND post_status <> 'trash'";
        return $this->db->row(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_name = ? AND post_type IN ({$placeholders}) {$status}
             ORDER BY post_date DESC LIMIT 1",
            [$name, ...$types],
        );
    }

    /**
     * Walks a page hierarchy: ["sample-page", "docs"] finds the page named
     * docs whose parent is named sample-page at the root.
     *
     * @param list<string> $segments
     */
    public function pageByPath(array $segments, bool $publishedOnly = true): ?array
    {
        $parent = 0;
        $page = null;
        $status = $publishedOnly ? "AND post_status = 'publish'" : "AND post_status <> 'trash'";
        foreach ($segments as $segment) {
            $page = $this->db->row(
                "SELECT * FROM {$this->db->table('posts')}
                 WHERE post_name = ? AND post_type = 'page' AND post_parent = ? {$status} LIMIT 1",
                [$segment, $parent],
            );
            if ($page === null) {
                return null;
            }
            $parent = (int) $page['ID'];
        }
        return $page;
    }

    /** The slash-joined ancestry of a page: "sample-page/docs". */
    public function pathOf(array $page): string
    {
        $parts = [$page['post_name']];
        $parentId = (int) $page['post_parent'];
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
    public function guess(string $prefix): ?array
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
                return $like;
            }
        }
        return null;
    }

    /**
     * A page of published posts for an archive.
     *
     * @param array{term?: int, author?: int, from?: string, to?: string, search?: string} $filter
     * @return array{posts: list<array>, total: int}
     */
    public function archive(array $filter, int $page, int $perPage): array
    {
        $posts = $this->db->table('posts');
        $statuses = Reader::current()->listableStatuses('post');
        $where = ["p.post_type = 'post'", 'p.post_status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')'];
        $params = $statuses;
        $join = '';
        if (isset($filter['term'])) {
            $join = "INNER JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID";
            $where[] = 'tr.term_taxonomy_id = ?';
            $params[] = $filter['term'];
        }
        if (isset($filter['author'])) {
            $where[] = 'p.post_author = ?';
            $params[] = $filter['author'];
        }
        if (isset($filter['from'], $filter['to'])) {
            $where[] = 'p.post_date >= ? AND p.post_date < ?';
            $params[] = $filter['from'];
            $params[] = $filter['to'];
        }
        if (isset($filter['search'])) {
            $needle = '%' . addcslashes($filter['search'], '%_\\') . '%';
            $where[] = '(p.post_title LIKE ? OR p.post_content LIKE ? OR p.post_excerpt LIKE ?)';
            array_push($params, $needle, $needle, $needle);
        }
        $clause = implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(DISTINCT p.ID) FROM {$posts} p {$join} WHERE {$clause}", $params);
        // Search results rank title matches first, as the reference does.
        $order = 'p.post_date DESC, p.ID DESC';
        $orderParams = [];
        if (isset($filter['search'])) {
            $order = '(p.post_title LIKE ?) DESC, ' . $order;
            $orderParams[] = '%' . addcslashes($filter['search'], '%_\\') . '%';
        }
        $rows = $this->db->rows(
            "SELECT DISTINCT p.* FROM {$posts} p {$join} WHERE {$clause}
             ORDER BY {$order} LIMIT ? OFFSET ?",
            [...$params, ...$orderParams, $perPage, ($page - 1) * $perPage],
        );
        return ['posts' => $rows, 'total' => $total];
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
    public function adjacent(array $post, bool $next): ?array
    {
        $operator = $next ? '>' : '<';
        $order = $next ? 'ASC' : 'DESC';
        return $this->db->row(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_type = ? AND post_status = 'publish'
               AND (post_date {$operator} ? OR (post_date = ? AND ID {$operator} ?))
             ORDER BY post_date {$order}, ID {$order} LIMIT 1",
            [$post['post_type'], $post['post_date'], $post['post_date'], (int) $post['ID']],
        );
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
    public function listing(array $filter, int $page, int $perPage, array $stickyIds = [], bool $stickyExtra = false): array
    {
        if ($stickyIds === []) {
            return $this->archive($filter, $page, $perPage);
        }
        $placeholders = implode(',', array_fill(0, count($stickyIds), '?'));
        $sticky = $this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE ID IN ({$placeholders}) AND post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC",
            $stickyIds,
        );
        $result = $this->archive($filter, $page, $perPage);
        if ($page > 1) {
            return $result;
        }
        $stickySet = array_flip(array_map(static fn (array $p) => (int) $p['ID'], $sticky));
        if ($stickyExtra) {
            // A query block keeps its full page of posts and adds the sticky ones on top.
            $others = $this->archive($filter, 1, $perPage + count($sticky));
            $rest = array_slice(array_values(array_filter($others['posts'], static fn (array $p) => !isset($stickySet[(int) $p['ID']]))), 0, $perPage);
            return ['posts' => [...$sticky, ...$rest], 'total' => $others['total']];
        }
        $rest = array_values(array_filter($result['posts'], static fn (array $p) => !isset($stickySet[(int) $p['ID']])));
        return ['posts' => array_slice([...$sticky, ...$rest], 0, $perPage), 'total' => $result['total']];
    }

    /** The newest autosave of a post by one author, or null. */
    public function newestAutosave(int $postId, int $userId): ?array
    {
        return $this->db->row(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_name LIKE ? AND post_author = ?
             ORDER BY post_modified DESC, ID DESC LIMIT 1",
            [$postId, $postId . '-autosave%', $userId],
        );
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
