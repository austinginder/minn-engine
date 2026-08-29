<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/** The post reads plugin code asks for by shape: a page by title, revisions, counts. */
final readonly class PostLookup
{
    public function __construct(private Db $db)
    {
    }

    /** @param list<string> $types */
    public function idByTitle(string $title, array $types): ?int
    {
        if ($types === []) {
            return null;
        }
        $id = $this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE post_title = ? AND post_type IN (" . self::marks(count($types)) . ') ORDER BY ID ASC LIMIT 1', [$title, ...$types]);
        return $id === null ? null : (int) $id;
    }

    /** Revision rows newest first. @return list<array<string, mixed>> */
    public function revisionsOf(int $postId): array
    {
        return $this->db->rows("SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_status = 'inherit' ORDER BY post_date DESC, ID DESC", [$postId]);
    }

    /** @return array<string, int> status => count */
    public function countByStatus(string $type): array
    {
        $counts = [];
        foreach ($this->db->rows("SELECT post_status, COUNT(*) AS num_posts FROM {$this->db->table('posts')} WHERE post_type = ? GROUP BY post_status", [$type]) as $row) {
            $counts[(string) $row['post_status']] = (int) $row['num_posts'];
        }
        return $counts;
    }

    /** @return array<string, int> mime type => count, plus 'trash' */
    public function countAttachments(): array
    {
        $counts = [];
        foreach ($this->db->rows("SELECT post_mime_type, COUNT(*) AS num_posts FROM {$this->db->table('posts')} WHERE post_type = 'attachment' AND post_status != 'trash' GROUP BY post_mime_type") as $row) {
            $counts[(string) $row['post_mime_type']] = (int) $row['num_posts'];
        }
        $counts['trash'] = (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'attachment' AND post_status = 'trash'");
        return $counts;
    }

    /** @param list<string> $types @param list<string> $statuses */
    public function countByAuthor(int $userId, array $types, array $statuses): int
    {
        if ($types === [] || $statuses === []) {
            return 0;
        }
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_author = ? AND post_type IN (" . self::marks(count($types)) . ') AND post_status IN (' . self::marks(count($statuses)) . ')', [$userId, ...$types, ...$statuses]);
    }

    /** @return list<int> */
    public function idsByAuthor(int $userId): array
    {
        return array_map(static fn (array $r) => (int) $r['ID'], $this->db->rows("SELECT ID FROM {$this->db->table('posts')} WHERE post_author = ?", [$userId]));
    }

    /** The attachment whose stored file path is the given one. */
    public function attachmentIdByFile(string $path): ?int
    {
        $id = $this->db->value("SELECT post_id FROM {$this->db->table('postmeta')} WHERE meta_key = '_wp_attached_file' AND meta_value = ? ORDER BY post_id ASC LIMIT 1", [$path]);
        return $id === null ? null : (int) $id;
    }

    private static function marks(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }
}
