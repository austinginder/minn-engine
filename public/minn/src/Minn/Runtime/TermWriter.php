<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\TermRecord;
use Closure;
use Minn\Content\PostWriter;
use Minn\Content\Terms;
use Minn\Db;

/**
 * The decisions behind wp_insert_term, wp_update_term, wp_delete_term, and
 * the object-term relationships: duplicate rules, slug uniqueness, parent
 * checks, which relationships to add and remove. The rows themselves come
 * from Content\Terms; the lifecycle actions fire from here in the
 * reference's order. Behaviour pinned by contracts/fixtures/api/content.json.
 */
final readonly class TermWriter
{
    /** @param Closure(string): string $slug the slug sanitiser, so the reference's filters apply */
    public function __construct(private Db $db, private Terms $terms, private TermQuery $query, private PostWriter $posts, private Closure $slug)
    {
    }

    /**
     * Inserts a term, or the refusal.
     *
     * @param array{slug?: string, description?: string, parent?: int|string, alias_of?: string} $args
     * @return array{term_id: int, term_taxonomy_id: int}|Refusal
     */
    public function insert(string $name, string $taxonomy, array $args, bool $hierarchical): array|Refusal
    {
        if (trim($name) === '') {
            return new Refusal('empty_term_name', 'A name is required for this term.');
        }
        $parent = (int) ($args['parent'] ?? 0);
        if ($parent > 0 && $this->query->exists($parent, $taxonomy, null) === null) {
            return new Refusal('missing_parent', 'Parent term does not exist.');
        }
        $wantedSlug = (string) ($args['slug'] ?? '');
        $existing = $this->query->find('name', $name, $taxonomy);
        if ($existing !== null) {
            $row = $this->query->row($existing['term_id'], $taxonomy) ?? [];
            if (!$hierarchical || (int) ($row['parent'] ?? 0) === $parent) {
                $slugMatch = $wantedSlug === '' || ($this->slug)($wantedSlug) === ($row['slug'] ?? '');
                if ($slugMatch || !$hierarchical) {
                    return new Refusal('term_exists', 'A term with the name provided already exists in this taxonomy.', $existing['term_id']);
                }
            }
        }
        $base = ($this->slug)($wantedSlug !== '' ? $wantedSlug : $name);
        $slug = $this->terms->uniqueSlug($base, $taxonomy);
        if ($wantedSlug !== '' && $slug !== $base && $this->query->find('slug', $base, $taxonomy) !== null) {
            return new Refusal('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $base));
        }
        $termId = $this->terms->create($name, $slug, $taxonomy, (string) ($args['description'] ?? ''), $parent);
        $row = $this->query->row($termId, $taxonomy);
        return ['term_id' => $termId, 'term_taxonomy_id' => (int) ($row['term_taxonomy_id'] ?? 0)];
    }

    /**
     * Updates a term, or the refusal.
     *
     * @param array<string, mixed> $current the term's row
     * @param array<string, mixed> $args
     * @return array{name: string, slug: string, description: string, parent: int}|Refusal what to write
     */
    public function update(array $current, string $taxonomy, array $args): array|Refusal
    {
        $termId = (int) $current['term_id'];
        $name = isset($args['name']) ? trim((string) $args['name']) : (string) $current['name'];
        if ($name === '') {
            return new Refusal('empty_term_name', 'A name is required for this term.');
        }
        $parent = isset($args['parent']) ? (int) $args['parent'] : (int) $current['parent'];
        if ($parent > 0 && $this->query->exists($parent, $taxonomy, null) === null) {
            return new Refusal('missing_parent', 'Parent term does not exist.');
        }
        $slug = isset($args['slug']) && $args['slug'] !== '' ? ($this->slug)((string) $args['slug']) : (string) $current['slug'];
        if ($slug !== (string) $current['slug'] || (isset($args['name']) && !isset($args['slug']) && $slug === '')) {
            $slug = $this->terms->uniqueSlug($slug === '' ? $name : $slug, $taxonomy, $termId);
        }
        $duplicate = $this->query->find('slug', $slug, $taxonomy);
        if ($duplicate !== null && $duplicate['term_id'] !== $termId) {
            return new Refusal('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $slug));
        }
        return ['name' => $name, 'slug' => $slug, 'description' => isset($args['description']) ? (string) $args['description'] : (string) $current['description'], 'parent' => $parent];
    }

    /** Writes an update() decision. @param array{name: string, slug: string, description: string, parent: int} $change */
    public function apply(int $termId, string $taxonomy, array $change): void
    {
        $this->terms->rename($termId, $change['name'], $change['slug']);
        $this->terms->describe($termId, $taxonomy, $change['description'], $change['parent']);
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
     * plus $keep when appending), firing the relationship actions the way the
     * reference does, and recounts.
     *
     * @param list<int> $keep term_taxonomy ids
     * @param list<int> $old the object's current term_taxonomy ids in the taxonomy
     */
    public function relate(int $objectId, array $keep, array $old, string $taxonomy): void
    {
        $hooks = Runtime::hooks();
        foreach (array_diff($old, $keep) as $ttId) {
            $hooks->action('delete_term_relationships', [$objectId, [$ttId], $taxonomy]);
            $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ? AND term_taxonomy_id = ?", [$objectId, $ttId]);
            $hooks->action('deleted_term_relationships', [$objectId, [$ttId], $taxonomy]);
        }
        foreach ($keep as $ttId) {
            if (in_array($ttId, $old, true)) {
                continue;
            }
            $hooks->action('add_term_relationship', [$objectId, $ttId, $taxonomy]);
            $this->db->execute("INSERT IGNORE INTO {$this->db->table('term_relationships')} (object_id, term_taxonomy_id, term_order) VALUES (?, ?, 0)", [$objectId, $ttId]);
            $hooks->action('added_term_relationship', [$objectId, $ttId, $taxonomy]);
        }
        $this->posts->recount($taxonomy);
    }

    /** Removes the given relationships; true when any row went. @param list<int> $ttIds */
    public function unrelate(int $objectId, array $ttIds, string $taxonomy): bool
    {
        $removed = 0;
        foreach ($ttIds as $ttId) {
            $removed += $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE object_id = ? AND term_taxonomy_id = ?", [$objectId, $ttId]);
        }
        $this->posts->recount($taxonomy);
        return $removed > 0;
    }

    private function ttIdOf(int $termId, string $taxonomy): int
    {
        return (int) ($this->query->row($termId, $taxonomy)['term_taxonomy_id'] ?? 0);
    }
}
