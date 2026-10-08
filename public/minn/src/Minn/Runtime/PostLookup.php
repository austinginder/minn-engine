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

    /**
     * The id of a post with this title among the types, or null.
     *
     * @param list<string> $types
     */
    public function idByTitle(string $title, array $types): ?int
    {
        if ($types === []) {
            return null;
        }
        $id = $this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE post_title = ? AND post_type IN (?) ORDER BY ID ASC LIMIT 1", [$title, $types]);
        return $id === null ? null : (int) $id;
    }

    /** Revision rows newest first. @return list<array<string, mixed>> */
    public function revisionsOf(int $postId): array
    {
        return $this->db->rows("SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' AND post_status = 'inherit' ORDER BY post_date DESC, ID DESC", [$postId]);
    }

    /**
     * How many posts of a type there are per status.
     *
     * @return array<string, int> status => count
     */
    public function countByStatus(string $type): array
    {
        $counts = [];
        foreach ($this->db->rows("SELECT post_status, COUNT(*) AS num_posts FROM {$this->db->table('posts')} WHERE post_type = ? GROUP BY post_status", [$type]) as $row) {
            $counts[(string) $row['post_status']] = (int) $row['num_posts'];
        }
        return $counts;
    }

    /**
     * How many attachments there are per mime type.
     *
     * @return array<string, int> mime type => count, plus 'trash'
     */
    public function countAttachments(): array
    {
        $counts = [];
        foreach ($this->db->rows("SELECT post_mime_type, COUNT(*) AS num_posts FROM {$this->db->table('posts')} WHERE post_type = 'attachment' AND post_status != 'trash' GROUP BY post_mime_type") as $row) {
            $counts[(string) $row['post_mime_type']] = (int) $row['num_posts'];
        }
        $counts['trash'] = (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_type = 'attachment' AND post_status = 'trash'");
        return $counts;
    }

    /**
     * How many posts an author has among the types and statuses.
     *
     * @param list<string> $types @param list<string> $statuses
     */
    public function countByAuthor(int $userId, array $types, array $statuses): int
    {
        if ($types === [] || $statuses === []) {
            return 0;
        }
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_author = ? AND post_type IN (?) AND post_status IN (?)", [$userId, $types, $statuses]);
    }

    /**
     * Post counts for several authors at once, as stored (strings), for
     * those that have any.
     *
     * @param list<int> $userIds
     * @param list<string> $types
     * @param list<string> $statuses
     * @return array<int, string>
     */
    public function countsByAuthors(array $userIds, array $types, array $statuses): array
    {
        if ($userIds === [] || $types === [] || $statuses === []) {
            return [];
        }
        $counts = [];
        foreach ($this->db->rows("SELECT post_author, COUNT(*) AS n FROM {$this->db->table('posts')} WHERE post_author IN (?) AND post_type IN (?) AND post_status IN (?) GROUP BY post_author", [$userIds, $types, $statuses]) as $row) {
            $counts[(int) $row['post_author']] = (string) $row['n'];
        }
        return $counts;
    }

    /** How many authors (up to a limit) have published posts of a type, for is_multi_author. */
    public function publishingAuthors(string $type, int $limit): int
    {
        return count($this->db->rows("SELECT DISTINCT post_author FROM {$this->db->table('posts')} WHERE post_type = ? AND post_status = 'publish' LIMIT {$limit}", [$type]));
    }

    /**
     * Post counts by author for wp_list_authors: the types' posts in the
     * statuses given, and the private ones of the viewer (an id, or -1 for
     * none) beside them.
     *
     * @param list<string> $types @param list<string> $statuses
     * @return array<int, int>
     */
    public function countsByAuthor(array $types, array $statuses, int $viewer): array
    {
        $counts = [];
        $rows = $this->db->rows(
            "SELECT post_author, COUNT(ID) AS count FROM {$this->db->table('posts')}
             WHERE post_type IN (?) AND (post_status IN (?) OR (post_status = 'private' AND post_author = ?)) GROUP BY post_author",
            [$types, $statuses, $viewer],
        );
        foreach ($rows as $row) {
            $counts[(int) $row['post_author']] = (int) $row['count'];
        }
        return $counts;
    }

    /**
     * Every post id of an author.
     *
     * @return list<int>
     */
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
}
