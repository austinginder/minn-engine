<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\TermRecord;
use Closure;
use Minn\Content\PostWriter;
use Minn\Content\Terms;
use Minn\Db;

/**
 * The decisions behind wp_delete_term and the object-term relationships:
 * which relationships to add and remove (saves are Runtime\TermSave). The rows themselves come
 * from Content\Terms; the lifecycle actions fire from here in the
 * reference's order. Behaviour pinned by contracts/fixtures/api/content.json.
 */
final readonly class TermWriter
{
    public function __construct(private Db $db, private Terms $terms, private TermQuery $query, private PostWriter $posts)
    {
    }

    /**
     * Deletes a term; objects left without a category fall back to the
     * default one. Returns the object ids the term was attached to.
     *
     * @param array<string, mixed> $row
     * @return list<int>
     */
    public function delete(array $row, string $taxonomy, bool $hierarchical, int $default): array
    {
        $objects = $this->query->objectsIn([(int) $row['term_id']], [$taxonomy], 'ASC');
        Runtime::hooks()->action('delete_term_taxonomy', [(int) $row['term_taxonomy_id']]);
        $this->terms->delete(TermRecord::fromRow($row), $hierarchical);
        if ($default > 0 && $taxonomy === 'category') {
            foreach ($objects as $objectId) {
                $left = $this->query->rows($this->query->normalise(['object_ids' => [$objectId], 'hide_empty' => false]), ['category']);
                if ($left === []) {
                    $this->relate($objectId, [$this->ttIdOf($default, 'category')], [], 'category');
                }
            }
        }
        $this->posts->recount($taxonomy);
        return $objects;
    }

    /**
     * Makes an object's relationships in a taxonomy exactly $keep (or $old
     * plus $keep when appending), in the reference's order: each new
     * relationship between add_term_relationship and
     * added_term_relationship, the counts of those terms, then the ones
     * that went, between delete_term_relationships and
     * deleted_term_relationships, and their counts. $count recounts a list
     * of term_taxonomy ids and tells plugins (wp_update_term_count); without
     * it the taxonomy is recounted quietly.
     *
     * @param list<int> $keep term_taxonomy ids
     * @param list<int> $old the object's current term_taxonomy ids in the taxonomy
     * @param (Closure(list<int>): void)|null $count
     */
    public function relate(int $objectId, array $keep, array $old, string $taxonomy, ?Closure $count = null): void
    {
        $count ??= fn (array $ttIds) => $this->posts->recount($taxonomy);
        $hooks = Runtime::hooks();
        $added = [];
        foreach ($keep as $ttId) {
            if (in_array($ttId, $old, true) || in_array($ttId, $added, true)) {
                continue;
            }
            $hooks->action('add_term_relationship', [$objectId, $ttId, $taxonomy]);
            $this->db->execute("INSERT IGNORE INTO {$this->db->table('term_relationships')} (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)", [$objectId, $ttId]);
            $hooks->action('added_term_relationship', [$objectId, $ttId, $taxonomy]);
            $added[] = $ttId;
        }
        if ($added !== []) {
            $count($added);
        }
        $this->unrelate($objectId, array_values(array_diff($old, $keep)), $taxonomy, $count);
    }

    /**
     * Removes the given relationships between delete_term_relationships and
     * deleted_term_relationships, then recounts those terms; true when any
     * row went.
     *
     * @param list<int> $ttIds
     * @param (Closure(list<int>): void)|null $count
     */
    public function unrelate(int $objectId, array $ttIds, string $taxonomy, ?Closure $count = null): bool
    {
        if ($ttIds === []) {
            return false;
        }
        $count ??= fn (array $ids) => $this->posts->recount($taxonomy);
        $hooks = Runtime::hooks();
        $hooks->action('delete_term_relationships', [$objectId, $ttIds, $taxonomy]);
        $removed = $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ? AND term_taxonomy_id IN (" . implode(',', array_map('intval', $ttIds)) . ')', [$objectId]);
        $hooks->action('deleted_term_relationships', [$objectId, $ttIds, $taxonomy]);
        $count($ttIds);
        return $removed > 0;
    }

    /** How many published objects a term holds, as the stored count keeps it. */
    public function publishedCount(int $ttId): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('term_relationships')} tr
             JOIN {$this->db->table('posts')} p ON p.ID = tr.object_id
             WHERE tr.term_taxonomy_id = ? AND p.post_status = 'publish'",
            [$ttId],
        );
    }

    /** Every object filed under a term, whatever it is: the count a taxonomy of links or other non-posts keeps. */
    public function relationshipCount(int $ttId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('term_relationships')} WHERE term_taxonomy_id = ?", [$ttId]);
    }

    /** Stores a term's count. */
    public function storeCount(int $ttId, int $count): void
    {
        $this->db->execute("UPDATE {$this->db->table('term_taxonomy')} SET count = ? WHERE term_taxonomy_id = ?", [$count, $ttId]);
    }

    /** The term ids behind term_taxonomy ids, as stored (strings), in the order given. @param list<int> $ttIds @return list<string> */
    public function termIdsOf(array $ttIds): array
    {
        if ($ttIds === []) {
            return [];
        }
        $rows = $this->db->rows("SELECT term_taxonomy_id, term_id FROM {$this->db->table('term_taxonomy')} WHERE term_taxonomy_id IN (" . implode(',', array_map('intval', $ttIds)) . ')');
        $byTt = array_column($rows, 'term_id', 'term_taxonomy_id');
        return array_values(array_map('strval', array_filter(array_map(static fn (int $tt) => $byTt[$tt] ?? null, $ttIds), static fn ($v) => $v !== null)));
    }

    private function ttIdOf(int $termId, string $taxonomy): int
    {
        return (int) ($this->query->row($termId, $taxonomy)['term_taxonomy_id'] ?? 0);
    }
}
