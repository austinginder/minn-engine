<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/** Revision rows read: the plain snapshots and the per-author autosave slots. */
final readonly class Revisions
{
    public function __construct(private Db $db)
    {
    }

    /**
     * The revisions, or the autosaves, of a post, newest first, as rows.
     *
     * @return list<array> newest first
     */
    public function revisionsOf(int $parentId): array
    {
        return $this->named($parentId, 'NOT LIKE');
    }

    /** Every revision of a post, its autosaves among them, newest first by date then id, as rows: what the revisions route lists (probe rest-latest-revision). */
    public function allOf(int $parentId): array
    {
        return $this->db->rows(
            "SELECT * FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'revision' ORDER BY post_date DESC, ID DESC",
            [$parentId],
        );
    }

    /** The autosaves of a post, newest first, as rows. */
    public function autosavesOf(int $parentId): array
    {
        return $this->named($parentId, 'LIKE');
    }

    private function named(int $parentId, string $comparison): array
    {
        return $this->db->rows(
            "SELECT * FROM {$this->db->table('posts')}
             WHERE post_parent = ? AND post_type = 'revision' AND post_name {$comparison} ?
             ORDER BY post_date DESC, ID DESC",
            [$parentId, $parentId . '-autosave%'],
        );
    }
}
