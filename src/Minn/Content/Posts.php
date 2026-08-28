<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

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
        $where = ["p.post_type = 'post'", "p.post_status = 'publish'"];
        $params = [];
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
        $rows = $this->db->rows(
            "SELECT DISTINCT p.* FROM {$posts} p {$join} WHERE {$clause}
             ORDER BY p.post_date DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage],
        );
        return ['posts' => $rows, 'total' => $total];
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
