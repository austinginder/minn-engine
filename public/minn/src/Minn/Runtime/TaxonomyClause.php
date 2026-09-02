<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/**
 * The taxonomy side of a post query: every query var the reference reads
 * (cat, category_name, tag, the __in and __and pairs, each taxonomy's own
 * var) and an explicit tax_query, resolved to term_taxonomy ids with
 * children included where the taxonomy is hierarchical, as one WHERE
 * fragment on p.ID.
 */
final class TaxonomyClause
{
    /** @var list<mixed> */
    private array $params = [];

    public function __construct(private readonly Db $db, private readonly Registry $registry)
    {
    }

    /**
     * The WHERE fragment and its parameters for a query's taxonomy conditions,
     * or null when the query has none.
     *
     * @return array{string, list<mixed>}|null
     */
    public function where(array $q): ?array
    {
        $this->params = [];
        [$relation, $clauses] = self::taxonomyClauses($q, $this->registry->taxonomies());
        if ($clauses === []) {
            return null;
        }
        $parts = array_map(fn (array $clause) => $this->taxonomySql($clause), $clauses);
        return ['(' . implode(" {$relation} ", $parts) . ')', $this->params];
    }

    /**
     * The tax_query the query vars amount to: cat, category_name, the __in /
     * __not_in / __and pairs, tag and its slug forms, every registered
     * taxonomy's own query var, and finally an explicit tax_query.
     *
     * @param array<string, array<string, mixed>> $taxonomies the registry's taxonomies
     * @return array{string, list<array<string, mixed>>} relation, clauses
     */
    private static function taxonomyClauses(array $q, array $taxonomies): array
    {
        $clauses = [];
        $add = static function (string $taxonomy, string $field, array $terms, string $operator = 'IN', bool $children = true) use (&$clauses): void {
            $clauses[] = ['taxonomy' => $taxonomy, 'field' => $field, 'terms' => $terms, 'operator' => $operator, 'include_children' => $children];
        };
        if (!empty($q['cat'])) {
            $in = [];
            $out = [];
            foreach (preg_split('/[\s,]+/', (string) $q['cat'], -1, PREG_SPLIT_NO_EMPTY) as $id) {
                (int) $id < 0 ? $out[] = abs((int) $id) : $in[] = (int) $id;
            }
            if ($in !== []) {
                $add('category', 'term_id', $in);
            }
            if ($out !== []) {
                $add('category', 'term_id', $out, 'NOT IN');
            }
        }
        if (!empty($q['category_name'])) {
            $slugs = explode(',', (string) $q['category_name']);
            $add('category', 'slug', array_map(static fn ($s) => trim(basename((string) $s)), $slugs));
        }
        foreach (['category__in' => ['category', 'IN'], 'category__not_in' => ['category', 'NOT IN'], 'category__and' => ['category', 'AND'], 'tag__in' => ['post_tag', 'IN'], 'tag__not_in' => ['post_tag', 'NOT IN'], 'tag__and' => ['post_tag', 'AND']] as $var => [$taxonomy, $operator]) {
            if (!empty($q[$var])) {
                $add($taxonomy, 'term_id', array_map('intval', (array) $q[$var]), $operator, $var === 'category__in');
            }
        }
        foreach (['tag_slug__in' => 'IN', 'tag_slug__and' => 'AND'] as $var => $operator) {
            if (!empty($q[$var])) {
                $add('post_tag', 'slug', array_map('strval', (array) $q[$var]), $operator);
            }
        }
        if (!empty($q['tag'])) {
            $tag = (string) $q['tag'];
            if (str_contains($tag, ',')) {
                $add('post_tag', 'slug', array_map('trim', explode(',', $tag)));
            } elseif (str_contains($tag, '+')) {
                $add('post_tag', 'slug', array_map('trim', explode('+', $tag)), 'AND');
            } else {
                $add('post_tag', 'slug', [$tag]);
            }
        }
        if (!empty($q['tag_id'])) {
            $add('post_tag', 'term_id', [(int) $q['tag_id']]);
        }
        foreach ($taxonomies as $name => $taxonomy) {
            $var = $taxonomy['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !in_array($var, ['category_name', 'tag'], true) && !empty($q[$var])) {
                $add($name, 'slug', array_map('trim', explode(',', (string) $q[$var])));
            }
        }
        $relation = 'AND';
        $raw = $q['tax_query'] ?? [];
        if (is_array($raw)) {
            if (isset($raw['relation'])) {
                $relation = strtoupper((string) $raw['relation']) === 'OR' ? 'OR' : 'AND';
                unset($raw['relation']);
            }
            foreach ($raw as $clause) {
                if (is_array($clause) && isset($clause['taxonomy'])) {
                    $add((string) $clause['taxonomy'], (string) ($clause['field'] ?? 'term_id'), array_values((array) ($clause['terms'] ?? [])), strtoupper((string) ($clause['operator'] ?? 'IN')), (bool) ($clause['include_children'] ?? true));
                }
            }
        }
        return [$relation, $clauses];
    }

    /** One clause as SQL on p.ID, its parameters pushed. @param array<string, mixed> $clause */
    private function taxonomySql(array $clause): string
    {
        $parts = [];
            $ttids = $this->termTaxonomyIds($clause);
            $relationships = $this->db->table('term_relationships');
            switch ($clause['operator']) {
                case 'NOT IN':
                    if ($ttids === []) {
                        $parts[] = '1=1';
                        break;
                    }
                    $parts[] = 'p.ID NOT IN (SELECT object_id FROM ' . $relationships . ' WHERE term_taxonomy_id IN (?))';
                    $this->params[] = $ttids;
                    break;
                case 'AND':
                    if ($ttids === []) {
                        $parts[] = '1=0';
                        break;
                    }
                    $parts[] = '(SELECT COUNT(DISTINCT term_taxonomy_id) FROM ' . $relationships . ' WHERE object_id = p.ID AND term_taxonomy_id IN (?)) = ' . count($ttids);
                    $this->params[] = $ttids;
                    break;
                case 'EXISTS':
                case 'NOT EXISTS':
                    $exists = 'EXISTS (SELECT 1 FROM ' . $relationships . ' tr JOIN ' . $this->db->table('term_taxonomy') . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = ?)';
                    $parts[] = ($clause['operator'] === 'EXISTS' ? '' : 'NOT ') . $exists;
                    $this->params[] = $clause['taxonomy'];
                    break;
                default:
                    if ($ttids === []) {
                        $parts[] = '1=0';
                        break;
                    }
                    $parts[] = 'p.ID IN (SELECT object_id FROM ' . $relationships . ' WHERE term_taxonomy_id IN (?))';
                    $this->params[] = $ttids;
            }
        return $parts[0];
    }

    /** @return list<int> */
    private function termTaxonomyIds(array $clause): array
    {
        $taxonomy = $clause['taxonomy'];
        $terms = $clause['terms'];
        if ($terms === []) {
            return [];
        }
        $column = match ($clause['field']) {
            'slug' => 't.slug',
            'name' => 't.name',
            'term_taxonomy_id' => 'tt.term_taxonomy_id',
            default => 't.term_id',
        };
        $rows = $this->db->rows(
            "SELECT tt.term_taxonomy_id, tt.term_id FROM {$this->db->table('term_taxonomy')} tt JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id WHERE tt.taxonomy = ? AND {$column} IN (?)",
            [$taxonomy, array_values($terms)],
        );
        $ids = array_map(static fn (array $r) => (int) $r['term_taxonomy_id'], $rows);
        $hierarchical = (bool) ($this->registry->taxonomy($taxonomy)['hierarchical'] ?? false);
        if ($hierarchical && $clause['include_children'] && $clause['operator'] !== 'AND') {
            $parents = array_map(static fn (array $r) => (int) $r['term_id'], $rows);
            while ($parents !== []) {
                $children = $this->db->rows("SELECT term_taxonomy_id, term_id FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ? AND parent IN (?)", [$taxonomy, $parents]);
                $parents = [];
                foreach ($children as $child) {
                    if (!in_array((int) $child['term_taxonomy_id'], $ids, true)) {
                        $ids[] = (int) $child['term_taxonomy_id'];
                        $parents[] = (int) $child['term_id'];
                    }
                }
            }
        }
        return $ids;
    }
}
