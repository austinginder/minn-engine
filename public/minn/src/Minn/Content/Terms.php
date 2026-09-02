<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

final readonly class Terms
{
    public function __construct(private Db $db)
    {
    }

    /** @param array<string, mixed>|null $row */
    private static function record(?array $row): ?TermRecord
    {
        return $row === null ? null : TermRecord::fromRow($row);
    }

    /** A term joined with its taxonomy row: term_id, name, slug, term_taxonomy_id, parent, count. */
    public function findBySlug(string $taxonomy, string $slug): ?TermRecord
    {
        return self::record($this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.slug = ? LIMIT 1",
            [$taxonomy, $slug],
        ));
    }

    /** One term in a taxonomy, or null. */
    public function find(string $taxonomy, int $termId): ?TermRecord
    {
        return self::record($this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.term_id = ? LIMIT 1",
            [$taxonomy, $termId],
        ));
    }

    /** The row shape the term controllers work with, including term_taxonomy_id. */
    public function row(int $termId, string $taxonomy): ?TermRecord
    {
        return self::record($this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.description, tt.count, tt.parent
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE t.term_id = ? AND tt.taxonomy = ? LIMIT 1",
            [$termId, $taxonomy],
        ));
    }

    /** The id of the term with exactly this name in a taxonomy, or null. */
    public function idByName(string $name, string $taxonomy): ?int
    {
        $id = $this->db->value(
            "SELECT t.term_id FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE t.name = ? AND tt.taxonomy = ? LIMIT 1",
            [$name, $taxonomy],
        );
        return $id === null ? null : (int) $id;
    }

    /** A slug unique within the taxonomy: base, -2, -3 on collision. */
    public function uniqueSlug(string $base, string $taxonomy, int $skipId = 0): string
    {
        $slug = Slug::sanitize($base);
        $try = $slug;
        $n = 1;
        while ($this->db->value(
            "SELECT t.term_id FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE t.slug = ? AND tt.taxonomy = ? AND t.term_id != ? LIMIT 1",
            [$try, $taxonomy, $skipId],
        ) !== null) {
            $n++;
            $try = "{$slug}-{$n}";
        }
        return $try;
    }

    /** Inserts a term and its taxonomy row and returns the term id. */
    public function create(string $name, string $slug, string $taxonomy, string $description, int $parent): int
    {
        return $this->db->transaction(function () use ($name, $slug, $taxonomy, $description, $parent): int {
            $this->db->execute("INSERT INTO {$this->db->table('terms')} (name, slug, term_group) VALUES (?, ?, 0)", [$name, $slug]);
            $termId = $this->db->insertId();
            $this->db->execute(
                "INSERT INTO {$this->db->table('term_taxonomy')} (term_id, taxonomy, description, parent, count) VALUES (?, ?, ?, ?, 0)",
                [$termId, $taxonomy, $description, $parent],
            );
            return $termId;
        });
    }

    /** Changes a term's name and slug. */
    public function rename(int $termId, string $name, string $slug): void
    {
        $this->db->execute("UPDATE {$this->db->table('terms')} SET name = ?, slug = ? WHERE term_id = ?", [$name, $slug, $termId]);
    }

    /** Changes a term's description and parent within one taxonomy. */
    public function describe(int $termId, string $taxonomy, string $description, int $parent): void
    {
        $this->db->execute(
            "UPDATE {$this->db->table('term_taxonomy')} SET description = ?, parent = ? WHERE term_id = ? AND taxonomy = ?",
            [$description, $parent, $termId, $taxonomy],
        );
    }

    /** Reparents children to the grandparent, detaches relationships, drops the rows. */
    public function delete(TermRecord $term, bool $hierarchical): void
    {
        $termId = (int) $term['term_id'];
        $ttid = (int) $term['term_taxonomy_id'];
        $this->db->transaction(function () use ($term, $hierarchical, $termId, $ttid): void {
            if ($hierarchical) {
                $this->db->execute(
                    "UPDATE {$this->db->table('term_taxonomy')} SET parent = ? WHERE parent = ? AND taxonomy = ?",
                    [(int) $term['parent'], $termId, (string) $term['taxonomy']],
                );
            }
            $this->db->execute("DELETE FROM {$this->db->table('term_relationships')} WHERE term_taxonomy_id = ?", [$ttid]);
            $this->db->execute("DELETE FROM {$this->db->table('term_taxonomy')} WHERE term_taxonomy_id = ?", [$ttid]);
            $this->db->execute("DELETE FROM {$this->db->table('terms')} WHERE term_id = ?", [$termId]);
        });
    }

    /** "parent/child" for hierarchical taxonomies, the bare slug otherwise. */
    public function pathOf(TermRecord $term): string
    {
        $parts = [$term['slug']];
        $parentId = (int) ($term['parent'] ?? 0);
        while ($parentId > 0) {
            $parent = $this->find($term['taxonomy'], $parentId);
            if ($parent === null) {
                break;
            }
            array_unshift($parts, $parent['slug']);
            $parentId = (int) $parent['parent'];
        }
        return implode('/', $parts);
    }
}
