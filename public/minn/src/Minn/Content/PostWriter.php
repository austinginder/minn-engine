<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;
use Minn\Runtime\Handoff;
use Minn\Runtime\MetaData;
use Minn\Runtime\Options;
use Minn\Runtime\Runtime;
use Minn\Support\Serialized;
use Minn\Support\Slashes;

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

    /** The posts table's short text columns, cut to fit as the reference's database layer cuts them. */
    private const LENGTHS = ['post_status' => 20, 'comment_status' => 20, 'ping_status' => 20, 'post_password' => 255, 'post_name' => 200, 'guid' => 255, 'post_type' => 20, 'post_mime_type' => 100];

    public function __construct(
        private Db $db,
        private Posts $posts,
        private Site $site,
    ) {
    }

    /** Whether a term of the taxonomy has the id. */
    public function hasTerm(string $taxonomy, int $termId): bool
    {
        return $this->db->value("SELECT 1 FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ? AND term_id = ? LIMIT 1", [$taxonomy, $termId]) !== null;
    }

    /**
     * Inserts a posts row from column => value pairs and returns the new id.
     *
     * @param array<string, mixed> $columns
     */
    public function insert(array $columns): int
    {
        $columns = self::fit($columns);
        $names = implode(', ', array_keys($columns));
        $this->db->execute("INSERT INTO {$this->db->table('posts')} ({$names}) VALUES (?)", [array_values($columns)]);
        return $this->db->insertId();
    }

    /**
     * Sets the given columns on one post; nothing happens for none.
     *
     * @param array<string, mixed> $columns
     */
    public function update(int $id, array $columns): bool
    {
        if ($columns === []) {
            return true;
        }
        $columns = self::fit($columns);
        $sets = implode(', ', array_map(static fn (string $column) => "{$column} = ?", array_keys($columns)));
        $this->db->execute("UPDATE {$this->db->table('posts')} SET {$sets} WHERE ID = ?", [...array_values($columns), $id]);
        return !$this->db->refused();
    }

    /** Changes one post's status. */
    public function setStatus(int $id, string $status): void
    {
        $this->update($id, ['post_status' => $status]);
    }

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
        // Once the runtime is up, as update_post_meta writes it (Users::setMeta).
        if (Handoff::bound('cacheDelete')) {
            MetaData::updatePost($id, $key, Slashes::add(Options::fromStorage($value)));
            return;
        }
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
     * slug gains __trashed (unless it has it already) and is made unique, as
     * a trashed post's slug is (a second __trashed becomes __trashed-2;
     * tests/tools/side-effects.php), the modified time moves, a floating
     * draft's date settles (any other post's missing GMT date is its own
     * date's), a post keeps a category, and the published
     * counts follow. What the trash keeps for the way back (the status, the
     * time, the slug it had) is meta the caller adds, and the revision comes
     * from the save.
     */
    public function trash(PostRecord $post): void
    {
        $id = $post->id;
        $local = $this->site->localNow();
        $gmt = gmdate('Y-m-d H:i:s');
        // A floating draft's date settles on now as it leaves for the trash; any other post without a GMT date takes its own date's.
        $settled = match (true) {
            self::floating($post) => ['post_date' => $local, 'post_date_gmt' => $gmt],
            in_array($post->dateGmt, [self::ZERO_DATE, ''], true) => ['post_date_gmt' => $this->site->localDate($post->date)[1]],
            default => [],
        };
        $this->update($id, ['post_status' => 'trash', 'post_name' => $this->trashedSlug($post)] + $settled + ['post_modified' => $local, 'post_modified_gmt' => $gmt]);
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

    /** The slug a post takes in the trash: its own with __trashed (once), made unique. */
    public function trashedSlug(PostRecord $post): string
    {
        $name = str_ends_with($post->slug, '__trashed') ? $post->slug : $post->slug . '__trashed';
        return $this->uniqueSlug($name, $post->id, $post->type, $post->parentId);
    }

    /** A free slug for the desired text, sanitized first, in the type's scope (see PostSlugs). */
    public function uniqueSlug(string $desired, int $excludeId, string $type = 'post', int $parent = 0): string
    {
        return $this->slugs()->unique(Slug::sanitize($desired), $excludeId, $type, $parent);
    }

    /** The rules a live post's slug is settled by. */
    public function slugs(): PostSlugs
    {
        return new PostSlugs($this->db, $this->site);
    }

    /** @param array<string, mixed> $columns @return array<string, mixed> */
    private static function fit(array $columns): array
    {
        foreach (array_intersect_key(self::LENGTHS, $columns) as $column => $length) {
            if (is_string($columns[$column]) && mb_strlen($columns[$column]) > $length) {
                $columns[$column] = mb_substr($columns[$column], 0, $length);
            }
        }
        return $columns;
    }

    /** Replaces a post's links in one taxonomy and refreshes the counts of the terms it left and joined. */
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
        $touched = [];
        foreach ($existing as $row) {
            $touched[] = (int) $row['term_taxonomy_id'];
            $this->db->execute("DELETE FROM {$relationships} WHERE object_id = ? AND term_taxonomy_id = ?", [$id, (int) $row['term_taxonomy_id']]);
        }
        foreach ($termIds as $termId) {
            $ttid = $this->db->value("SELECT term_taxonomy_id FROM {$taxonomies} WHERE term_id = ? AND taxonomy = ? LIMIT 1", [(int) $termId, $taxonomy]);
            if ($ttid === null) {
                continue;
            }
            $touched[] = (int) $ttid;
            $this->db->execute("INSERT IGNORE INTO {$relationships} (object_id, term_taxonomy_id) VALUES (?, ?)", [$id, (int) $ttid]);
        }
        $this->recountTerms($touched);
    }

    /** Recounts every term the post is in, after a status change. */
    public function recountTaxonomiesOf(int $id): void
    {
        $this->recountTerms($this->termTaxonomyIdsOf($id));
    }

    /** The term_taxonomy ids a post is linked to. @return list<int> */
    public function termTaxonomyIdsOf(int $id): array
    {
        return array_map('intval', array_column($this->db->rows("SELECT term_taxonomy_id FROM {$this->db->table('term_relationships')} WHERE object_id = ?", [$id]), 'term_taxonomy_id'));
    }

    /** term_taxonomy.count is stored and trusted on read, so a write that touched terms (term_taxonomy ids) refreshes theirs. @param list<int> $ttIds */
    public function recountTerms(array $ttIds): void
    {
        $ttIds = array_values(array_unique(array_map('intval', $ttIds)));
        if ($ttIds === []) {
            return;
        }
        $this->db->execute($this->countSql() . ' WHERE tt.term_taxonomy_id IN (' . implode(',', array_fill(0, count($ttIds), '?')) . ')', $ttIds);
    }

    /** The update that sets each term's count to its published posts. */
    private function countSql(): string
    {
        return "UPDATE {$this->db->table('term_taxonomy')} tt SET tt.count = (
                SELECT COUNT(*) FROM {$this->db->table('term_relationships')} tr
                JOIN {$this->db->table('posts')} p ON p.ID = tr.object_id
                WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND p.post_status = 'publish'
            )";
    }

    /** Whether the post is in the sticky_posts option. */
    public function isSticky(int $id): bool
    {
        return in_array($id, Serialized::intList($this->site->option('sticky_posts')), true);
    }

    /** Puts a post on the sticky list, then post_stuck when it was not on it (probe rest-sticky). */
    public function stick(int $id): void
    {
        $ids = Serialized::intList($this->site->option('sticky_posts'));
        if (in_array($id, $ids, true)) {
            return;
        }
        $ids[] = $id;
        $this->saveSticky($ids);
        $this->announceSticky('post_stuck', $id);
    }

    /** Takes a post off the sticky list, then post_unstuck when it was on it (probe rest-sticky). */
    public function unstick(int $id): void
    {
        $ids = Serialized::intList($this->site->option('sticky_posts'));
        if (!in_array($id, $ids, true)) {
            return;
        }
        $this->saveSticky(array_values(array_diff($ids, [$id])));
        $this->announceSticky('post_unstuck', $id);
    }

    /** Inside a request: fires post_stuck or post_unstuck. */
    private function announceSticky(string $hook, int $id): void
    {
        if (Runtime::booted()) {
            Runtime::hooks()->action((string) $hook, [$id]);
        }
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

    /** The side effects shared by create and update: sticky, format, featured media. */
    public function applyExtendedFields(int $id, array $body, string $type): void
    {
        if (isset($body['sticky']) && $type === 'post') {
            $body['sticky'] ? $this->stick($id) : $this->unstick($id);
        }
        if (isset($body['format']) && $type === 'post') {
            $this->setFormat($id, (string) $body['format']);
        }
        // The custom template a REST write names, '' to clear it (PostsWriteController checked the name).
        if (array_key_exists('template', $body) && is_string($body['template'])) {
            $this->setMeta($id, '_wp_page_template', $body['template']);
        }
        if (isset($body['featured_media'])) {
            $media = (int) $body['featured_media'];
            if ($media > 0) {
                $this->setMeta($id, '_thumbnail_id', (string) $media);
            } else {
                $this->deleteMeta($id, '_thumbnail_id');
            }
        }
    }

    /** The meta core registers, written directly when no plugins are loaded: footnotes, and a pattern's sync status. */
    public function applyCoreMeta(int $id, array $body, string $type): void
    {
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
     * @param array<string, string>|null $map REST field => taxonomy; the core taxonomies when null
     * @return array<string, list<int>>
     */
    public static function requestedTerms(array $body, ?array $map = null): array
    {
        $out = [];
        foreach ($map ?? ['categories' => 'category', 'tags' => 'post_tag', 'wp_pattern_category' => 'wp_pattern_category'] as $field => $taxonomy) {
            if (array_key_exists($field, $body) && is_array($body[$field])) {
                $out[$taxonomy] = array_map(intval(...), $body[$field]);
            }
        }
        return $out;
    }

    /** Moves a deleted post's children of the given types to another parent, the way the reference keeps pages and attachments attached. @param list<string> $types @return list<int> the children moved */
    public function reparentChildren(int $id, int $parent, array $types): array
    {
        $moved = [];
        foreach ($types as $type) {
            $children = array_map('intval', array_column($this->db->rows("SELECT ID FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = ?", [$id, $type]), 'ID'));
            if ($children !== []) {
                $this->db->execute("UPDATE {$this->db->table('posts')} SET post_parent = ? WHERE post_parent = ? AND post_type = ?", [$parent, $id, $type]);
                $moved = [...$moved, ...$children];
            }
        }
        return $moved;
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

    /**
     * Deletes a post's row and nothing else: wp_delete_post has taken away
     * what goes with it first, and a link in a taxonomy its type does not
     * have stays, as on the reference (tests/tools/side-effects.php,
     * plugin-symbols2).
     */
    public function deleteRow(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('posts')} WHERE ID = ?", [$id]);
    }

    /** Hard-deletes a post with its revisions and its meta. */
    public function destroy(int $id): void
    {
        $taxonomies = $this->db->transaction(function () use ($id): array {
            $posts = $this->db->table('posts');
            $this->db->execute("DELETE FROM {$posts} WHERE post_parent = ? AND post_type = 'revision'", [$id]);
            $this->db->execute("DELETE FROM {$posts} WHERE ID = ?", [$id]);
            $terms = $this->termTaxonomyIdsOf($id);
            $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ?", [$id]);
            $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ?", [$id]);
            return $terms;
        });
        // The counts are read back from what the delete left, so they are settled after it commits.
        $this->recountTerms($taxonomies);
    }
}
