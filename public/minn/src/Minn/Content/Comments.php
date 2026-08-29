<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/** Reads and writes over the comments table. */
final readonly class Comments
{
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
     * @param list<string> $approvedTokens
     * @return array{comments: list<array>, total: int}
     */
    public function page(array $approvedTokens, int $page, int $perPage, bool $publicPostsOnly = false): array
    {
        $placeholders = implode(',', array_fill(0, count($approvedTokens), '?'));
        $from = "FROM {$this->db->table('comments')} c"
            . ($publicPostsOnly ? " INNER JOIN {$this->db->table('posts')} p ON p.ID = c.comment_post_ID AND p.post_status = 'publish' AND p.post_password = ''" : '')
            . " WHERE c.comment_approved IN ({$placeholders}) AND c.comment_type IN ('', 'comment')";
        $total = (int) $this->db->value("SELECT COUNT(*) {$from}", $approvedTokens);
        $rows = $this->db->rows(
            "SELECT c.* {$from} ORDER BY c.comment_date_gmt DESC LIMIT ? OFFSET ?",
            [...$approvedTokens, $perPage, ($page - 1) * $perPage],
        );
        return ['comments' => $rows, 'total' => $total];
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
    public function flooding(string $email, string $address, int $seconds): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);
        return $this->db->value(
            "SELECT comment_ID FROM {$this->db->table('comments')} WHERE comment_date_gmt > ? AND (comment_author_IP = ? OR (comment_author_email <> '' AND comment_author_email = ?)) LIMIT 1",
            [$since, $address, $email],
        ) !== null;
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
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $this->db->execute("INSERT INTO {$this->db->table('comments')} ({$names}) VALUES ({$placeholders})", array_values($columns));
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
}
