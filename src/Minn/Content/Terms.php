<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

final readonly class Terms
{
    public function __construct(private Db $db)
    {
    }

    /** A term joined with its taxonomy row: term_id, name, slug, term_taxonomy_id, parent, count. */
    public function findBySlug(string $taxonomy, string $slug): ?array
    {
        return $this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.slug = ? LIMIT 1",
            [$taxonomy, $slug],
        );
    }

    public function find(string $taxonomy, int $termId): ?array
    {
        return $this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.taxonomy, tt.parent, tt.count
             FROM {$this->db->table('terms')} t
             INNER JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.term_id = ? LIMIT 1",
            [$taxonomy, $termId],
        );
    }

    /** "parent/child" for hierarchical taxonomies, the bare slug otherwise. */
    public function pathOf(array $term): string
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
