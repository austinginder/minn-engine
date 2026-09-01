<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/** Reads and writes over the comments table. */
final readonly class Comments
{
    private const UPDATABLE = ['comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id'];

    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->row("SELECT * FROM {$this->db->table('comments')} WHERE comment_ID = ? LIMIT 1", [$id]);
    }

    public function meta(int $id, string $key): ?string
    {
        $value = $this->db->value(
            "SELECT meta_value FROM {$this->db->table('commentmeta')} WHERE comment_id = ? AND meta_key = ? LIMIT 1",
            [$id, $key],
        );
        return $value === null ? null : (string) $value;
    }

    public function addMeta(int $id, string $key, string $value): void
    {
        $this->db->execute("INSERT INTO {$this->db->table('commentmeta')} (comment_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
    }

    /**
     * A page of plain comments in the given approval states, newest first.
     *
     * $filters keys, all optional: post, include, exclude, parent,
     * parentExclude, author, authorExclude (id lists; 0 is kept),
     * authorEmail, search, after, before, type.
     *
     * @param list<string> $approvedTokens
     * @param array<string, mixed> $filters
     * @return array{comments: list<array>, total: int}
     */
    public function page(array $approvedTokens, int $page, int $perPage, bool $publicPostsOnly = false, array $filters = []): array
    {
        $params = [$approvedTokens];
        $from = "FROM {$this->db->table('comments')} c"
            . ($publicPostsOnly ? " INNER JOIN {$this->db->table('posts')} p ON p.ID = c.comment_post_ID AND p.post_status = 'publish' AND p.post_password = ''" : '')
            . ' WHERE c.comment_approved IN (?)';
        $type = (string) ($filters['type'] ?? 'comment');
        if ($type === '' || $type === 'comment') {
            $from .= " AND c.comment_type IN ('', 'comment')";
        } else {
            $from .= ' AND c.comment_type = ?';
            $params[] = $type;
        }
        $this->idFilter($from, $params, 'c.comment_post_ID', $filters['post'] ?? []);
        $this->idFilter($from, $params, 'c.comment_ID', $filters['include'] ?? []);
        $this->idFilter($from, $params, 'c.comment_ID', $filters['exclude'] ?? [], true);
        $this->idFilter($from, $params, 'c.comment_parent', $filters['parent'] ?? []);
        $this->idFilter($from, $params, 'c.comment_parent', $filters['parentExclude'] ?? [], true);
        $this->idFilter($from, $params, 'c.user_id', $filters['author'] ?? []);
        $this->idFilter($from, $params, 'c.user_id', $filters['authorExclude'] ?? [], true);
        if (($filters['authorEmail'] ?? '') !== '') {
            $from .= ' AND LOWER(c.comment_author_email) = ?';
            $params[] = strtolower((string) $filters['authorEmail']);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $from .= ' AND (c.comment_content LIKE ? OR c.comment_author LIKE ? OR c.comment_author_email LIKE ?)';
            $params = [...$params, $like, $like, $like];
        }
        if (($filters['after'] ?? '') !== '') {
            $from .= ' AND c.comment_date > ?';
            $params[] = $filters['after'];
        }
        if (($filters['before'] ?? '') !== '') {
            $from .= ' AND c.comment_date < ?';
            $params[] = $filters['before'];
        }
        $total = (int) $this->db->value("SELECT COUNT(*) {$from}", $params);
        $rows = $this->db->rows(
            "SELECT c.* {$from} ORDER BY c.comment_date_gmt DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage],
        );
        return ['comments' => $rows, 'total' => $total];
    }

    /** @param list<int> $ids */
    private function idFilter(string &$from, array &$params, string $column, array $ids, bool $not = false): void
    {
        if ($ids === []) {
            return;
        }
        $from .= ' AND ' . $column . ($not ? ' NOT' : '') . ' IN (?)';
        $params[] = $ids;
    }

    /** The same words on the same post from the same person, in any status but trash or spam. */
    public function duplicate(int $postId, string $author, string $email, string $content, int $userId): bool
    {
        $table = $this->db->table('comments');
        $who = $userId > 0 ? 'user_id = ?' : '(comment_author = ? AND comment_author_email = ?)';
        $params = $userId > 0 ? [$postId, $userId, $content] : [$postId, $author, $email, $content];
        return $this->db->value(
            "SELECT comment_ID FROM {$table} WHERE comment_post_ID = ? AND comment_approved IN ('0', '1') AND {$who} AND comment_content = ? LIMIT 1",
            $params,
        ) !== null;
    }

    /** A comment from the same address or email within the window. */
    /** Whether this author email has any approved comment, the previously-approved gate check_comment applies. */
    public function hasApprovedByEmail(string $email): bool
    {
        if ($email === '') {
            return false;
        }
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_author_email = ? AND comment_approved = '1' LIMIT 1",
            [$email],
        ) > 0;
    }

    public function flooding(string $email, string $address, int $seconds): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);
        return $this->db->value(
            "SELECT comment_ID FROM {$this->db->table('comments')} WHERE comment_date_gmt > ? AND (comment_author_IP = ? OR (comment_author_email <> '' AND comment_author_email = ?)) LIMIT 1",
            [$since, $address, $email],
        ) !== null;
    }

    /** Held comments per post, for the ids given (a post without any reads 0). */
    public function pendingCounts(array $postIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $postIds)));
        $counts = array_fill_keys($ids, 0);
        if ($ids === []) {
            return $counts;
        }
        foreach ($this->db->rows("SELECT comment_post_ID, COUNT(*) AS n FROM {$this->db->table('comments')} WHERE comment_approved = '0' AND comment_post_ID IN (?) GROUP BY comment_post_ID", [$ids]) as $row) {
            $counts[(int) $row['comment_post_ID']] = (int) $row['n'];
        }
        return $counts;
    }

    /** True when this name and email already have an approved comment. */
    public function previouslyApproved(string $author, string $email): bool
    {
        if ($email === '') {
            return false;
        }
        return $this->db->value(
            "SELECT comment_ID FROM {$this->db->table('comments')} WHERE comment_author = ? AND comment_author_email = ? AND comment_approved = '1' LIMIT 1",
            [$author, $email],
        ) !== null;
    }

    /** @param array<string, mixed> $columns */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $this->db->execute("INSERT INTO {$this->db->table('comments')} ({$names}) VALUES (?)", [array_values($columns)]);
        return $this->db->insertId();
    }

    /** @param array<string, mixed> $columns */
    public function update(int $id, array $columns): void
    {
        foreach ($columns as $column => $value) {
            $this->db->execute("UPDATE {$this->db->table('comments')} SET {$column} = ? WHERE comment_ID = ?", [$value, $id]);
        }
    }

    /** A deleted comment's replies move up to its parent. */
    public function orphanReplies(int $id, int $parent): void
    {
        $this->db->execute("UPDATE {$this->db->table('comments')} SET comment_parent = ? WHERE comment_parent = ?", [$parent, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('comments')} WHERE comment_ID = ?", [$id]);
        $this->db->execute("DELETE FROM {$this->db->table('commentmeta')} WHERE comment_id = ?", [$id]);
    }

    /** Recomputes a post's stored comment_count (approved comments only). */
    public function recount(int $postId): void
    {
        $this->db->execute(
            "UPDATE {$this->db->table('posts')} SET comment_count =
             (SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_post_ID = ? AND comment_approved = '1')
             WHERE ID = ?",
            [$postId, $postId],
        );
    }

    /** DB approval token to the wp/v2 status string. */
    public static function statusOf(string $approved): string
    {
        return match ($approved) {
            '1' => 'approved',
            '0' => 'hold',
            default => $approved,
        };
    }

    /**
     * wp/v2 status parameter to DB token(s); null for an unknown status.
     *
     * @return list<string>|null
     */
    public static function tokensFor(string $status): ?array
    {
        return match ($status) {
            'approve', 'approved' => ['1'],
            'hold' => ['0'],
            'all' => ['1', '0'],
            'spam', 'trash' => [$status],
            default => null,
        };
    }

    /**
     * The columns an update really changes, compared as strings against the
     * stored row; the approval shorthands hold/approve become the stored 0/1.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $current
     * @return array<string, mixed>
     */
    public static function changedColumns(array $data, array $current): array
    {
        $columns = [];
        foreach (self::UPDATABLE as $key) {
            if (array_key_exists($key, $data) && (string) $data[$key] !== (string) ($current[$key] ?? '')) {
                $columns[$key] = $data[$key];
            }
        }
        if (isset($columns['comment_approved'])) {
            $columns['comment_approved'] = match ((string) $columns['comment_approved']) {
                'hold' => '0',
                'approve' => '1',
                default => (string) $columns['comment_approved'],
            };
        }
        return $columns;
    }
}
