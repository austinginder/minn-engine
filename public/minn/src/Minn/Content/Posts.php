<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/**
 * Reads over the posts table. A post comes back as a PostRecord; a listing
 * is the main query's (WP_Query); rendering and escaping happen elsewhere.
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

    /** The post with this id, or null. */
    public function find(int $id): ?PostRecord
    {
        return RowCache::remember('posts', $id, fn (): ?PostRecord => self::record($this->db->row("SELECT * FROM {$this->db->table('posts')} WHERE ID = ? LIMIT 1", [$id])));
    }

    /**
     * Reads a list's posts, their meta and their terms in the given
     * taxonomies in one query each, as update_post_caches does, so the
     * list's rendering reads none of them again.
     *
     * @param list<int> $ids
     * @param list<string> $taxonomies
     */
    public function prime(array $ids, array $taxonomies = []): void
    {
        $this->primeRows($ids);
        $this->primeMeta($ids);
        $this->primeTerms($ids, $taxonomies);
    }

    /** Reads the rows of the posts not read yet, in one query. @param list<int> $ids */
    public function primeRows(array $ids): void
    {
        $unread = array_values(array_filter(self::ids($ids), static fn (int $id): bool => !RowCache::has('posts', $id)));
        if ($unread === []) {
            return;
        }
        $found = [];
        foreach ($this->db->rows("SELECT * FROM {$this->db->table('posts')} WHERE ID IN (?)", [$unread]) as $row) {
            $found[(int) $row['ID']] = self::record($row);
        }
        foreach ($unread as $id) {
            RowCache::put('posts', $id, $found[$id] ?? null);
        }
    }

    /** Reads the meta of the posts whose meta is not read yet, in one query (update_post_meta_cache). @param list<int> $ids */
    public function primeMeta(array $ids): void
    {
        $unread = array_values(array_filter(self::ids($ids), static fn (int $id): bool => !RowCache::has('post_meta', $id)));
        if ($unread === []) {
            return;
        }
        $grouped = array_fill_keys($unread, []);
        foreach ($this->db->rows("SELECT post_id, meta_key, meta_value FROM {$this->db->table('postmeta')} WHERE post_id IN (?) ORDER BY meta_id ASC", [$unread]) as $row) {
            $grouped[(int) $row['post_id']][(string) $row['meta_key']][] = (string) $row['meta_value'];
        }
        foreach ($grouped as $id => $values) {
            RowCache::put('post_meta', $id, $values);
        }
    }

    /**
     * Reads the posts' terms in the given taxonomies, in one query, for the
     * posts some of whose taxonomies are not read yet.
     *
     * @param list<int> $ids
     * @param list<string> $taxonomies
     */
    public function primeTerms(array $ids, array $taxonomies): void
    {
        $ids = array_values(array_filter(self::ids($ids), static function (int $id) use ($taxonomies): bool {
            foreach ($taxonomies as $taxonomy) {
                if (!RowCache::has('post_terms', "{$id}:{$taxonomy}")) {
                    return true;
                }
            }
            return false;
        }));
        if ($ids === []) {
            return;
        }
        $terms = [];
        foreach ($this->db->rows(
            "SELECT tr.object_id, tt.taxonomy, t.term_id, t.slug FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE tr.object_id IN (?) AND tt.taxonomy IN (?)
             ORDER BY t.name ASC",
            [$ids, array_values($taxonomies)],
        ) as $row) {
            $terms[(int) $row['object_id']][(string) $row['taxonomy']][] = [(int) $row['term_id'], (string) $row['slug']];
        }
        foreach ($ids as $id) {
            foreach ($taxonomies as $taxonomy) {
                RowCache::put('post_terms', "{$id}:{$taxonomy}", $terms[$id][$taxonomy] ?? []);
            }
        }
    }

    /** @param list<int> $ids @return list<int> the positive ids, once each */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * The post with this slug among the given types; published only unless asked otherwise.
     *
     * @param list<string> $types
     */
    public function findByName(string $name, array $types): ?PostRecord
    {
        return $this->byName($name, $types, "AND post_status = 'publish'");
    }

    /** The post with this slug among the given types in any status but trash, or null. @param list<string> $types */
    public function findByNameAnyStatus(string $name, array $types): ?PostRecord
    {
        return $this->byName($name, $types, "AND post_status <> 'trash'");
    }

    /** @param list<string> $types */
    private function byName(string $name, array $types, string $status): ?PostRecord
    {
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
    public function pageByPath(array $segments): ?PostRecord
    {
        return $this->byPath($segments, "AND post_status = 'publish'");
    }

    /** The page at a slug path in any status but trash, or null. @param list<string> $segments */
    public function pageByPathAnyStatus(array $segments): ?PostRecord
    {
        return $this->byPath($segments, "AND post_status <> 'trash'");
    }

    /**
     * The post at a slug path among the given types (each segment's parent
     * the one before it), in any status but trash; at the last segment the
     * first type wins over the others, as the reference prefers the type it
     * was asked for over the attachment it also looks at.
     *
     * @param list<string> $segments
     * @param list<string> $types
     */
    public function byTypedPath(array $segments, array $types): ?PostRecord
    {
        $parent = 0;
        $found = null;
        foreach ($segments as $segment) {
            $found = self::record($this->db->row(
                "SELECT * FROM {$this->db->table('posts')}
                 WHERE post_name = ? AND post_type IN (?) AND post_parent = ? AND post_status <> 'trash'
                 ORDER BY post_type = ? DESC LIMIT 1",
                [$segment, $types, $parent, $types[0] ?? ''],
            ));
            if ($found === null) {
                return null;
            }
            $parent = $found->id;
        }
        return $found;
    }

    /** @param list<string> $segments */
    private function byPath(array $segments, string $status): ?PostRecord
    {
        $parent = 0;
        $page = null;
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
            $parent = $this->find($parentId);
            if ($parent === null) {
                break;
            }
            array_unshift($parts, $parent->slug);
            $parentId = $parent->parentId;
        }
        return implode('/', $parts);
    }

    /**
     * The published post whose name starts with the given text, among the
     * given types, as the reference guesses a destination for a missing
     * URL: one prefix query and no order of its own, so the database's
     * plan for it decides which match comes first (on a small table its
     * name index, alphabetical; on a large one its type and date index,
     * oldest first), the same on both stacks over one database.
     *
     * @param list<string> $types
     */
    public function guess(string $prefix, array $types): ?PostRecord
    {
        $id = $this->db->value(
            "SELECT ID FROM {$this->db->table('posts')} WHERE post_name LIKE ? AND post_type IN (?) AND post_status IN ('publish')",
            [addcslashes($prefix, '%_\\') . '%', $types],
        );
        return $id === null ? null : $this->find((int) $id);
    }

    /** Whether any post of a type is published. */
    public function hasPublished(string $type): bool
    {
        return $this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' LIMIT 1", [$type]) !== null;
    }

    /** Every post id of a type, whatever its status, as stored (strings), in the table's order. @return list<string> */
    public function idsOfType(string $type): array
    {
        return array_map(static fn (array $row): string => (string) $row['ID'], $this->db->rows("SELECT ID FROM {$this->db->table('posts')} WHERE post_type = ?", [$type]));
    }

    /** One meta value of a post, or null when it has none. */
    public function meta(int $postId, string $key): ?string
    {
        $all = RowCache::remember('post_meta', $postId, function () use ($postId): array {
            $grouped = [];
            foreach ($this->db->rows("SELECT meta_key, meta_value FROM {$this->db->table('postmeta')} WHERE post_id = ? ORDER BY meta_id ASC", [$postId]) as $row) {
                $grouped[(string) $row['meta_key']][] = (string) $row['meta_value'];
            }
            return $grouped;
        });
        return isset($all[$key][0]) ? $all[$key][0] : null;
    }

    /**
     * The post's terms in one taxonomy, as [term_id, slug] pairs.
     *
     * @return list<array{0: int, 1: string}> term id and slug pairs, by name
     */
    public function terms(int $postId, string $taxonomy): array
    {
        return RowCache::remember('post_terms', "{$postId}:{$taxonomy}", fn (): array => $this->readTerms($postId, $taxonomy));
    }

    /** @return list<array{0: int, 1: string}> */
    private function readTerms(int $postId, string $taxonomy): array
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

    /** How many revisions the post has. */
    public function revisionCount(int $postId): int
    {
        return $this->revisions($postId)[0];
    }

    /**
     * Reads many posts' revision counts and latest revisions in one query,
     * for a list that links to each post's history (a REST list).
     *
     * @param list<int> $ids
     */
    public function primeRevisions(array $ids): void
    {
        $unread = array_values(array_filter(array_unique(array_map('intval', $ids)), static fn (int $id): bool => $id > 0 && !RowCache::has('revisions', $id)));
        if ($unread !== []) {
            foreach ($this->readRevisions($unread) as $id => $pair) {
                RowCache::put('revisions', $id, $pair);
            }
        }
    }

    /** @return array{0: int, 1: int} how many revisions a post has, and the latest one's id (newest by date then id), or 0 */
    private function revisions(int $postId): array
    {
        return RowCache::remember('revisions', $postId, fn (): array => $this->readRevisions([$postId])[$postId] ?? [0, 0]);
    }

    /**
     * Revision counts and latest ids for many posts in one query.
     *
     * @param list<int> $postIds
     * @return array<int, array{0: int, 1: int}>
     */
    private function readRevisions(array $postIds): array
    {
        $out = array_fill_keys($postIds, [0, 0]);
        foreach ($this->db->rows(
            "SELECT ID, post_parent FROM {$this->db->table('posts')} WHERE post_type = 'revision' AND post_parent IN (?) ORDER BY post_date DESC, ID DESC",
            [$postIds],
        ) as $row) {
            $parent = (int) $row['post_parent'];
            $out[$parent] = [$out[$parent][0] + 1, $out[$parent][1] === 0 ? (int) $row['ID'] : $out[$parent][1]];
        }
        return $out;
    }

    /** The latest revision id, an autosave counting as one, newest by date then id (probe rest-latest-revision), or 0. */
    public function latestRevisionId(int $postId): int
    {
        return $this->revisions($postId)[1];
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

    /** The post of the same type after this one, by date then id, among those the scope allows (published only by default), or null. */
    public function next(PostRecord $post, AdjacentScope $scope = new AdjacentScope()): ?PostRecord
    {
        return $this->neighbour($post, '>', 'ASC', $scope);
    }

    /** The post of the same type before this one, by date then id, among those the scope allows, or null. */
    public function previous(PostRecord $post, AdjacentScope $scope = new AdjacentScope()): ?PostRecord
    {
        return $this->neighbour($post, '<', 'DESC', $scope);
    }

    private function neighbour(PostRecord $post, string $operator, string $order, AdjacentScope $scope): ?PostRecord
    {
        $where = '';
        $params = [$post->type, $post->date, $post->date, $post->id];
        $where .= match ($scope->private) {
            'all' => " AND p.post_status IN ('publish', 'private')",
            'own' => " AND (p.post_status = 'publish' OR (p.post_status = 'private' AND p.post_author = ?))",
            default => " AND p.post_status = 'publish'",
        };
        if ($scope->private === 'own') {
            $params[] = $scope->reader;
        }
        $inTerms = "SELECT tr.object_id FROM {$this->db->table('term_relationships')} tr
            JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = ? AND tt.term_id IN (?)";
        if ($scope->sameTerms !== []) {
            $where .= " AND p.ID IN ({$inTerms})";
            array_push($params, $scope->taxonomy, $scope->sameTerms);
        }
        if ($scope->excludedTerms !== []) {
            $where .= " AND p.ID NOT IN ({$inTerms})";
            array_push($params, $scope->taxonomy, $scope->excludedTerms);
        }
        return self::record($this->db->row(
            "SELECT p.* FROM {$this->db->table('posts')} p
             WHERE p.post_type = ?
               AND (p.post_date {$operator} ? OR (p.post_date = ? AND p.ID {$operator} ?)){$where}
             ORDER BY p.post_date {$order}, p.ID {$order} LIMIT 1",
            $params,
        ));
    }

    /**
     * The newest value of a date column (post_date, post_modified, or their
     * GMT twins) among published posts of the types given, for
     * get_lastpostdate and get_lastpostmodified.
     *
     * @param list<string> $types
     */
    public function newest(array $types, string $column): ?string
    {
        if ($types === [] || !in_array($column, ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'], true)) {
            return null;
        }
        $value = $this->db->value("SELECT MAX({$column}) FROM {$this->db->table('posts')} WHERE post_status = 'publish' AND post_type IN (?)", [array_values($types)]);
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * The days of one month a published post of a type was dated on, ascending.
     *
     * @return list<int>
     */
    public function daysWithPosts(int $year, int $month, string $type): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $rows = $this->db->rows(
            "SELECT DISTINCT DAYOFMONTH(post_date) AS d FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' AND post_date BETWEEN ? AND ? ORDER BY d",
            [$type, $first->format('Y-m-d 00:00:00'), $first->format('Y-m-t 23:59:59')],
        );
        return array_map(static fn (array $row): int => (int) $row['d'], $rows);
    }

    /**
     * The periods holding a published post of a type, newest first by
     * default: one row per month, year, day, or week, with the count and the
     * earliest post date inside it.
     *
     * @return list<array{year: int, month: int, day: int, week: int, count: int, first: string}>
     */
    public function archiveBuckets(string $granularity, string $type, string $order, int $limit): array
    {
        $direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $group = match ($granularity) {
            'yearly' => 'YEAR(post_date)',
            'daily' => 'YEAR(post_date), MONTH(post_date), DAYOFMONTH(post_date)',
            'weekly' => 'YEAR(post_date), WEEK(post_date, ' . $this->weekMode() . ')',
            default => 'YEAR(post_date), MONTH(post_date)',
        };
        $rows = $this->db->rows(
            "SELECT YEAR(post_date) AS y, MONTH(post_date) AS m, DAYOFMONTH(post_date) AS d, WEEK(post_date, " . $this->weekMode() . ") AS w, COUNT(ID) AS c, MIN(post_date) AS first
             FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish'
             GROUP BY {$group} ORDER BY MIN(post_date) {$direction}" . ($limit > 0 ? ' LIMIT ' . $limit : ''),
            [$type],
        );
        return array_map(static fn (array $row): array => ['year' => (int) $row['y'], 'month' => (int) $row['m'], 'day' => (int) $row['d'], 'week' => (int) $row['w'], 'count' => (int) $row['c'], 'first' => (string) $row['first']], $rows);
    }

    /** MySQL's WEEK() mode for the site's first day of the week: 0 for Sunday, 1 for Monday. */
    private function weekMode(): int
    {
        return (int) ($this->db->option('start_of_week') ?? '0') === 1 ? 1 : 0;
    }

    /**
     * Published posts of a type for a post-by-post archive, by date or by title.
     *
     * @return list<PostRecord>
     */
    public function archiveList(string $type, string $orderBy, string $order, int $limit): array
    {
        $direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $column = $orderBy === 'title' ? 'post_title' : 'post_date';
        return PostRecord::fromRows($this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' ORDER BY {$column} {$direction}" . ($limit > 0 ? ' LIMIT ' . $limit : ''),
            [$type],
        ));
    }

    /** The nearest month before one with a published post of a type, as [year, month], or null. */
    public function monthBefore(int $year, int $month, string $type): ?array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        return $this->monthBeside($type, 'post_date < ? ORDER BY post_date DESC', $first->format('Y-m-d 00:00:00'));
    }

    /** The nearest month after one with a published post of a type, as [year, month], or null. */
    public function monthAfter(int $year, int $month, string $type): ?array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        return $this->monthBeside($type, 'post_date > ? ORDER BY post_date ASC', $first->format('Y-m-t 23:59:59'));
    }

    /** @return array{int, int}|null */
    private function monthBeside(string $type, string $clause, string $edge): ?array
    {
        $row = $this->db->row(
            "SELECT YEAR(post_date) AS y, MONTH(post_date) AS m FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' AND {$clause} LIMIT 1",
            [$type, $edge],
        );
        return $row === null ? null : [(int) $row['y'], (int) $row['m']];
    }

    /** The newest autosave of a post (by date, then id), by one author or, given 0, any (probe revision-more); null when there is none. */
    public function newestAutosave(int $postId, int $userId = 0): ?PostRecord
    {
        $byAuthor = $userId > 0 ? ' AND post_author = ?' : '';
        return self::record($this->db->row(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_name LIKE ?{$byAuthor}
             ORDER BY post_date DESC, ID DESC LIMIT 1",
            $userId > 0 ? [$postId, $postId . '-autosave%', $userId] : [$postId, $postId . '-autosave%'],
        ));
    }

    /** The slug of the post's first category, or null. */
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
