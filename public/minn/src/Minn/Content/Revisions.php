<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/** Revision rows: the plain snapshots and the per-author autosave slots. */
final readonly class Revisions
{
    public function __construct(
        private Db $db,
        private PostWriter $writer,
        private Site $site,
    ) {
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

    /** One autosave slot per author: updated in place when it exists. */
    public function saveAutosave(int $parentId, int $userId, string $title, string $content, string $excerpt): int
    {
        $slug = $parentId . '-autosave-v1';
        $now = $this->site->localNow();
        $nowGmt = gmdate('Y-m-d H:i:s');
        $existing = $this->db->value(
            "SELECT ID FROM {$this->db->table('posts')}
             WHERE post_parent = ? AND post_type = 'revision' AND post_name = ? AND post_author = ? LIMIT 1",
            [$parentId, $slug, $userId],
        );
        if ($existing !== null) {
            $this->writer->update((int) $existing, [
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_date' => $now,
                'post_date_gmt' => $nowGmt,
                'post_modified' => $now,
                'post_modified_gmt' => $nowGmt,
            ]);
            return (int) $existing;
        }
        $id = $this->writer->insert([
            'post_author' => $userId,
            'post_date' => $now,
            'post_date_gmt' => $nowGmt,
            'post_content' => $content,
            'post_title' => $title,
            'post_excerpt' => $excerpt,
            'post_status' => 'inherit',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $slug,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $nowGmt,
            'post_content_filtered' => '',
            'post_parent' => $parentId,
            'guid' => '',
            'menu_order' => 0,
            'post_type' => 'revision',
            'post_mime_type' => '',
            'comment_count' => 0,
        ]);
        $this->writer->update($id, ['guid' => rtrim($this->site->option('home') ?? '', '/') . '/?p=' . $id]);
        return $id;
    }
}
