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
    /** The statuses whose post may have a floating date (no GMT date yet). */
    public const FLOATING = ['draft', 'pending', 'auto-draft'];
    public const ZERO_DATE = '0000-00-00 00:00:00';

    public function __construct(
        private Db $db,
        private Posts $posts,
        private Site $site,
    ) {
    }

    /**
     * Inserts a posts row from column => value pairs and returns the new id.
     *
     * @param array<string, mixed> $columns
     */
    public function insert(array $columns): int
    {
        $names = implode(', ', array_keys($columns));
        $this->db->execute("INSERT INTO {$this->db->table('posts')} ({$names}) VALUES (?)", [array_values($columns)]);
        return $this->db->insertId();
    }

    /**
     * Sets the given columns on one post; nothing happens for none.
     *
     * @param array<string, mixed> $columns
     */
    public function update(int $id, array $columns): void
    {
        if ($columns === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $column) => "{$column} = ?", array_keys($columns)));
        $this->db->execute("UPDATE {$this->db->table('posts')} SET {$sets} WHERE ID = ?", [...array_values($columns), $id]);
    }

    /** Changes one post's status. */
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

    /** Sets one meta value, inserting the row when the key is new. */
    public function setMeta(int $id, string $key, string $value): void
    {
        $table = $this->db->table('postmeta');
        $updated = $this->db->execute("UPDATE {$table} SET meta_value = ? WHERE post_id = ? AND meta_key = ?", [$value, $id, $key]);
        if ($updated === 0 && (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE post_id = ? AND meta_key = ?", [$id, $key]) === 0) {
            $this->db->execute("INSERT INTO {$table} (post_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
        }
    }

    /** Whether a post's date floats: never given one, it is still a draft or pending with no GMT date. */
    public static function floating(PostRecord $post): bool
    {
        return in_array($post->status, self::FLOATING, true) && in_array($post->dateGmt, [self::ZERO_DATE, ''], true);
    }

    /** Adds a meta row even when the key already has one (a non-unique key). */
    public function addMeta(int $id, string $key, string $value): void
    {
        $this->db->execute("INSERT INTO {$this->db->table('postmeta')} (post_id, meta_key, meta_value) VALUES (?, ?, ?)", [$id, $key, $value]);
    }

    /**
     * Keeps a published post's previous slug or date on record
     * (_wp_old_slug, _wp_old_date) so its old address still finds it: the
     * value it moves to stops being an old one, the value it leaves becomes
     * one, once.
     */
    public function rememberOld(int $id, string $key, string $was, string $now): void
    {
        if ($was === '' || $was === $now) {
            return;
        }
        $table = $this->db->table('postmeta');
        $this->db->execute("DELETE FROM {$table} WHERE post_id = ? AND meta_key = ? AND meta_value = ?", [$id, $key, $now]);
        if ($this->db->value("SELECT meta_id FROM {$table} WHERE post_id = ? AND meta_key = ? AND meta_value = ? LIMIT 1", [$id, $key, $was]) === null) {
            $this->addMeta($id, $key, $was);
        }
    }

    /** Gives a post the site's default category when it has none, as every save of a post does in the reference. */
    public function ensureCategory(int $id): void
    {
        $has = $this->db->value(
            "SELECT tr.object_id FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'category' LIMIT 1",
            [$id],
        );
        if ($has === null) {
            $this->setTerms($id, 'category', [(int) ($this->site->option('default_category') ?? 1)]);
        }
    }

    /**
     * The row of a post moving to the trash, as the reference writes it: the
     * slug gains __trashed, the modified time moves, a floating draft's date
     * settles, a post keeps a category, and the published counts follow. What
     * the trash keeps for the way back (the status, the time, the slug it
     * had) is meta the caller adds, and the revision comes from the save.
     */
    public function trash(PostRecord $post): void
    {
        $id = $post->id;
        $local = $this->site->localNow();
        $gmt = gmdate('Y-m-d H:i:s');
        // A floating draft's date settles on now as it leaves for the trash.
        $settled = self::floating($post) ? ['post_date' => $local, 'post_date_gmt' => $gmt] : [];
        $this->update($id, ['post_status' => 'trash', 'post_name' => $post->slug . '__trashed'] + $settled + ['post_modified' => $local, 'post_modified_gmt' => $gmt]);
        if ($post->type === 'post') {
            $this->ensureCategory($id);
        }
        $this->recountTaxonomiesOf($id);
    }

    /** Removes every meta row with this key from a post. */
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

    /**
     * The taxonomies a post has terms in.
     *
     * @return list<string> the distinct taxonomies a post has links in
     */
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

    /** Recounts every term the post is in, after a status change. */
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

    /** Whether the post is in the sticky_posts option. */
    public function isSticky(int $id): bool
    {
        return in_array($id, Serialized::intList($this->site->option('sticky_posts')), true);
    }

    /** Rewrites the sticky_posts option with or without one id. */
    public function stick(int $id): void
    {
        $ids = Serialized::intList($this->site->option('sticky_posts'));
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
        $this->saveSticky($ids);
    }

    /** Takes a post off the sticky list. */
    public function unstick(int $id): void
    {
        $this->saveSticky(array_values(array_diff(Serialized::intList($this->site->option('sticky_posts')), [$id])));
    }

    /** @param list<int> $ids */
    private function saveSticky(array $ids): void
    {
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
            $body['sticky'] ? $this->stick($id) : $this->unstick($id);
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
        if ($type === 'wp_block' && isset($body['meta']['wp_pattern_sync_status'])) {
            $this->setMeta($id, 'wp_pattern_sync_status', (string) $body['meta']['wp_pattern_sync_status']);
        }
    }

    /** Assigns categories, tags, and pattern categories from a write body, replacing existing links. */
    public function applyTerms(int $id, array $body): void
    {
        foreach (self::requestedTerms($body) as $taxonomy => $termIds) {
            $this->setTerms($id, $taxonomy, $termIds);
        }
    }

    /**
     * The terms a REST body names, by taxonomy: categories, tags, and pattern
     * categories, each only when the body has the field.
     *
     * @param array<string, mixed> $body
     * @return array<string, list<int>>
     */
    public static function requestedTerms(array $body): array
    {
        $out = [];
        foreach (['categories' => 'category', 'tags' => 'post_tag', 'wp_pattern_category' => 'wp_pattern_category'] as $field => $taxonomy) {
            if (array_key_exists($field, $body) && is_array($body[$field])) {
                $out[$taxonomy] = array_map(intval(...), $body[$field]);
            }
        }
        return $out;
    }

    /**
     * Snapshots the post's NEW state as a revision exactly when the
     * reference would: compared against the latest revision, identical
     * content-bearing fields add nothing, and the first update always
     * snapshots.
     */
    public function maybeSaveRevision(int $id, int $userId): void
    {
        $columns = $this->revisionColumns($id, $userId);
        if ($columns !== null) {
            $this->insertRevision($columns);
        }
    }

    /**
     * The row of the revision a post's current state calls for, or null
     * when it calls for none (an unrevisioned type, or nothing changed
     * since the latest revision).
     *
     * @return array<string, mixed>|null
     */
    public function revisionColumns(int $id, int $userId): ?array
    {
        $post = $this->posts->find($id);
        if ($post === null || !in_array($post['post_type'], ['post', 'page', 'wp_global_styles'], true)) {
            return null;
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
            return null;
        }
        return [
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
        ];
    }

    /** Writes a revision row and gives it its guid; returns its id. @param array<string, mixed> $columns */
    public function insertRevision(array $columns): int
    {
        $revisionId = $this->insert($columns);
        $this->update($revisionId, ['guid' => rtrim($this->site->option('home') ?? '', '/') . '/?p=' . $revisionId]);
        return $revisionId;
    }

    /** Removes a post, its revisions, term links, and meta. */
    /** Moves a deleted post's children of the given types to another parent, the way the reference keeps pages and attachments attached. @param list<string> $types */
    public function reparentChildren(int $id, int $parent, array $types): void
    {
        foreach ($types as $type) {
            $this->db->execute("UPDATE {$this->db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = ?", [$parent, $id, $type]);
        }
    }

    /** Moves every post of one author to another. */
    public function reassignAuthor(int $from, int $to): void
    {
        $this->db->execute("UPDATE {$this->db->table('posts')} SET post_author = ? WHERE post_author = ?", [$to, $from]);
    }

    /** The database door this writer writes through, for a caller wrapping several of its verbs in one transaction. */
    public function db(): \Minn\Db
    {
        return $this->db;
    }

    /** Hard-deletes a post with its revisions and its meta. */
    public function destroy(int $id): void
    {
        $taxonomies = $this->db->transaction(function () use ($id): array {
            $posts = $this->db->table('posts');
            $this->db->execute("DELETE FROM {$posts} WHERE post_parent = ? AND post_type = 'revision'", [$id]);
            $this->db->execute("DELETE FROM {$posts} WHERE ID = ?", [$id]);
            $taxonomies = $this->taxonomiesOf($id);
            $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ?", [$id]);
            $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ?", [$id]);
            return $taxonomies;
        });
        // The counts are read back from what the delete left, so they are settled after it commits.
        foreach ($taxonomies as $taxonomy) {
            $this->recount($taxonomy);
        }
    }
}
