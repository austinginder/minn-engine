<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;
use Minn\Support\Serialized;

/**
 * Every write to the posts table and its satellites: rows, meta, term
 * links with the published counts the reference trusts on read, sticky
 * and format side effects, and revision snapshots.
 */
final readonly class PostWriter
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Site $site,
    ) {
    }

    /** @param array<string, mixed> $columns */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $this->db->execute("INSERT INTO {$this->db->table('posts')} ({$names}) VALUES ({$placeholders})", array_values($columns));
        return $this->db->insertId();
    }

    /** @param array<string, mixed> $columns */
    public function update(int $id, array $columns): void
    {
        if ($columns === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $column) => "{$column} = ?", array_keys($columns)));
        $this->db->execute("UPDATE {$this->db->table('posts')} SET {$sets} WHERE ID = ?", [...array_values($columns), $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->update($id, ['post_status' => $status]);
    }

    /** One row per key: an existing value is replaced, not shadowed. */
    /** Moves a post to another type, leaving it alone when it is already there. */
    public function setType(int $id, string $type): bool
    {
        $current = $this->db->value("SELECT post_type FROM {$this->db->table('posts')} WHERE ID = ?", [$id]);
        if ($current === null || (string) $current === $type) {
            return false;
        }
        $this->db->execute("UPDATE {$this->db->table('posts')} SET post_type = ? WHERE ID = ?", [$type, $id]);
        return true;
    }

    public function setMeta(int $id, string $key, string $value): void
    {
        $table = $this->db->table('postmeta');
        $updated = $this->db->execute("UPDATE {$table} SET meta_value = ? WHERE post_id = ? AND meta_key = ?", [$value, $id, $key]);
        if ($updated === 0 && (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE post_id = ? AND meta_key = ?", [$id, $key]) === 0) {
            $this->db->execute("INSERT INTO {$table} (post_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
        }
    }

    public function deleteMeta(int $id, string $key): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ? AND meta_key = ?", [$id, $key]);
    }

    /** A slug unique within the posts table: base, -2, -3 on collision. */
    public function uniqueSlug(string $desired, int $excludeId): string
    {
        $base = Slug::sanitize($desired);
        if ($base === '') {
            return '';
        }
        $slug = $base;
        $n = 1;
        while ($this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE post_name = ? AND ID <> ? LIMIT 1", [$slug, $excludeId]) !== null) {
            $n++;
            $slug = "{$base}-{$n}";
        }
        return $slug;
    }

    /** Replaces a post's links in one taxonomy and refreshes that taxonomy's counts. */
    public function setTerms(int $id, string $taxonomy, array $termIds): void
    {
        $relationships = $this->db->table('term_relationships');
        $taxonomies = $this->db->table('term_taxonomy');
        $existing = $this->db->rows(
            "SELECT tt.term_taxonomy_id FROM {$taxonomies} tt
             JOIN {$relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tr.object_id = ? AND tt.taxonomy = ?",
            [$id, $taxonomy],
        );
        foreach ($existing as $row) {
            $this->db->execute("DELETE FROM {$relationships} WHERE object_id = ? AND term_taxonomy_id = ?", [$id, (int) $row['term_taxonomy_id']]);
        }
        foreach ($termIds as $termId) {
            $ttid = $this->db->value("SELECT term_taxonomy_id FROM {$taxonomies} WHERE term_id = ? AND taxonomy = ? LIMIT 1", [(int) $termId, $taxonomy]);
            if ($ttid === null) {
                continue;
            }
            $this->db->execute("INSERT IGNORE INTO {$relationships} (object_id, term_taxonomy_id) VALUES (?, ?)", [$id, (int) $ttid]);
        }
        $this->recount($taxonomy);
    }

    /** @return list<string> the distinct taxonomies a post has links in */
    public function taxonomiesOf(int $id): array
    {
        $rows = $this->db->rows(
            "SELECT DISTINCT tt.taxonomy FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = ?",
            [$id],
        );
        return array_map(static fn (array $row) => (string) $row['taxonomy'], $rows);
    }

    public function recountTaxonomiesOf(int $id): void
    {
        foreach ($this->taxonomiesOf($id) as $taxonomy) {
            $this->recount($taxonomy);
        }
    }

    /** term_taxonomy.count is stored and trusted on read, so every status change refreshes it. */
    public function recount(string $taxonomy): void
    {
        $this->db->execute(
            "UPDATE {$this->db->table('term_taxonomy')} tt SET tt.count = (
                SELECT COUNT(*) FROM {$this->db->table('term_relationships')} tr
                JOIN {$this->db->table('posts')} p ON p.ID = tr.object_id
                WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND p.post_status = 'publish'
            ) WHERE tt.taxonomy = ?",
            [$taxonomy],
        );
    }

    public function isSticky(int $id): bool
    {
        return in_array($id, Serialized::intList($this->site->option('sticky_posts')), true);
    }

    /** Rewrites the sticky_posts option with or without one id. */
    public function setSticky(int $id, bool $on): void
    {
        $ids = Serialized::intList($this->site->option('sticky_posts'));
        if ($on && !in_array($id, $ids, true)) {
            $ids[] = $id;
        } elseif (!$on) {
            $ids = array_values(array_diff($ids, [$id]));
        }
        $out = 'a:' . count($ids) . ':{';
        foreach (array_values($ids) as $index => $value) {
            $out .= "i:{$index};i:{$value};";
        }
        $this->site->setOption('sticky_posts', $out . '}');
    }

    /** Assigns (or clears) the post-format term, creating it on first use. */
    public function setFormat(int $id, string $format): void
    {
        if ($format === '' || $format === 'standard') {
            $this->setTerms($id, 'post_format', []);
            return;
        }
        $slug = 'post-format-' . $format;
        $termId = $this->db->value(
            "SELECT t.term_id FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE t.slug = ? AND tt.taxonomy = 'post_format' LIMIT 1",
            [$slug],
        );
        if ($termId === null) {
            $this->db->execute("INSERT INTO {$this->db->table('terms')} (name, slug, term_group) VALUES (?, ?, 0)", [$slug, $slug]);
            $termId = $this->db->insertId();
            $this->db->execute(
                "INSERT INTO {$this->db->table('term_taxonomy')} (term_id, taxonomy, description, parent, count) VALUES (?, 'post_format', '', 0, 0)",
                [$termId],
            );
        }
        $this->setTerms($id, 'post_format', [(int) $termId]);
    }

    /** The side effects shared by create and update: sticky, format, featured media, footnotes. */
    public function applyExtendedFields(int $id, array $body, string $type): void
    {
        if (isset($body['sticky']) && $type === 'post') {
            $this->setSticky($id, (bool) $body['sticky']);
        }
        if (isset($body['format']) && $type === 'post') {
            $this->setFormat($id, (string) $body['format']);
        }
        if (isset($body['featured_media'])) {
            $media = (int) $body['featured_media'];
            if ($media > 0) {
                $this->setMeta($id, '_thumbnail_id', (string) $media);
            } else {
                $this->deleteMeta($id, '_thumbnail_id');
            }
        }
        if (isset($body['meta']['footnotes'])) {
            $this->setMeta($id, 'footnotes', (string) $body['meta']['footnotes']);
        }
    }

    /** Assigns categories and tags from a write body, replacing existing links. */
    public function applyTerms(int $id, array $body): void
    {
        foreach (['categories' => 'category', 'tags' => 'post_tag'] as $field => $taxonomy) {
            if (array_key_exists($field, $body) && is_array($body[$field])) {
                $this->setTerms($id, $taxonomy, array_map(intval(...), $body[$field]));
            }
        }
    }

    /**
     * Snapshots the post's NEW state as a revision exactly when the
     * reference would: compared against the latest revision, identical
     * content-bearing fields add nothing, and the first update always
     * snapshots.
     */
    public function maybeSaveRevision(int $id, int $userId): void
    {
        $post = $this->posts->find($id);
        if ($post === null || !in_array($post['post_type'], ['post', 'page'], true)) {
            return;
        }
        $latest = $this->db->row(
            "SELECT post_title, post_content, post_excerpt FROM {$this->db->table('posts')}
             WHERE post_parent = ? AND post_type = 'revision' AND post_name NOT LIKE ?
             ORDER BY ID DESC LIMIT 1",
            [$id, $id . '-autosave%'],
        );
        if ($latest !== null
            && $latest['post_title'] === $post['post_title']
            && $latest['post_content'] === $post['post_content']
            && $latest['post_excerpt'] === $post['post_excerpt']) {
            return;
        }
        $revisionId = $this->insert([
            'post_author' => $userId,
            'post_date' => $post['post_modified'],
            'post_date_gmt' => $post['post_modified_gmt'],
            'post_content' => $post['post_content'],
            'post_title' => $post['post_title'],
            'post_excerpt' => $post['post_excerpt'],
            'post_status' => 'inherit',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $id . '-revision-v1',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $post['post_modified'],
            'post_modified_gmt' => $post['post_modified_gmt'],
            'post_content_filtered' => '',
            'post_parent' => $id,
            'guid' => '',
            'menu_order' => 0,
            'post_type' => 'revision',
            'post_mime_type' => '',
            'comment_count' => 0,
        ]);
        $this->update($revisionId, ['guid' => rtrim($this->site->option('home') ?? '', '/') . '/?p=' . $revisionId]);
    }

    /** Removes a post, its revisions, term links, and meta. */
    /** A deleted post's children (pages, and every attachment) move up to its parent. */
    public function reparentChildren(int $id, int $parent, bool $pages): void
    {
        if ($pages) {
            $this->db->execute("UPDATE {$this->db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = 'page'", [$parent, $id]);
        }
        $this->db->execute("UPDATE {$this->db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = 'attachment'", [$parent, $id]);
    }

    public function reassignAuthor(int $from, int $to): void
    {
        $this->db->execute("UPDATE {$this->db->table('posts')} SET post_author = ? WHERE post_author = ?", [$to, $from]);
    }

    public function destroy(int $id): void
    {
        $posts = $this->db->table('posts');
        $this->db->execute("DELETE FROM {$posts} WHERE post_parent = ? AND post_type = 'revision'", [$id]);
        $this->db->execute("DELETE FROM {$posts} WHERE ID = ?", [$id]);
        $taxonomies = $this->taxonomiesOf($id);
        $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ?", [$id]);
        $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ?", [$id]);
        foreach ($taxonomies as $taxonomy) {
            $this->recount($taxonomy);
        }
    }
}
