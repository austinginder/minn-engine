<?php

declare(strict_types=1);

namespace Minn\Query;

use Closure;

/**
 * The JOIN and WHERE fragments of a taxonomy query in the reference's
 * shape: IN clauses join term_relationships (the first under the table's
 * own name, then tt1, tt2, ..., shared between IN siblings under OR),
 * NOT IN and AND read subqueries, EXISTS checks the taxonomy, and a term
 * nobody has yields "0 = 1".
 */
final class TaxSql
{
    private int $aliasCount = 0;

    /** @var list<string> */
    private array $joins = [];

    /**
     * @param Closure(string, string, list<mixed>, bool): list<int> $termTaxonomyIds taxonomy, field, terms, include children
     */
    public function __construct(
        private readonly string $relationships,
        private readonly string $termTaxonomy,
        private readonly string $primaryTable,
        private readonly string $primaryId,
        private readonly Closure $termTaxonomyIds,
    ) {
    }

    /**
     * The query as the reference keeps it: each clause merged over the
     * defaults (taxonomy, terms as a list, field term_id, operator IN,
     * children included), a nested group given relation AND when it has
     * none, an empty group dropped. The top level keeps a relation only when
     * one was given.
     *
     * @param array<array-key, mixed> $queries
     * @return array<array-key, mixed>
     */
    public static function sanitize(array $queries): array
    {
        $clean = [];
        foreach ($queries as $key => $query) {
            if ($key === 'relation') {
                $clean['relation'] = strtoupper((string) $query) === 'OR' ? 'OR' : 'AND';
            } elseif (self::isFirstOrder($query)) {
                $clause = array_merge(['taxonomy' => '', 'terms' => [], 'field' => 'term_id', 'operator' => 'IN', 'include_children' => true], $query);
                $clause['terms'] = (array) $clause['terms'];
                $clean[] = $clause;
            } elseif (is_array($query)) {
                $group = self::sanitize($query);
                if ($group !== []) {
                    $clean[] = $group + ['relation' => 'AND'];
                }
            }
        }
        return $clean;
    }

    /** Whether a query part is a clause (it names a clause key, or is empty) rather than a group. */
    public static function isFirstOrder(mixed $query): bool
    {
        if (!is_array($query)) {
            return false;
        }
        return $query === [] || array_intersect(['terms', 'taxonomy', 'include_children', 'field', 'operator'], array_keys($query)) !== [];
    }

    /**
     * The terms a sanitized query asks for, by taxonomy: the first terms and
     * the first field each taxonomy's clauses give, NOT IN clauses aside.
     *
     * @param array<array-key, mixed> $queries
     * @return array<string, array{terms?: list<mixed>, field?: string}>
     */
    public static function queried(array $queries, array $queried = []): array
    {
        foreach ($queries as $key => $query) {
            if ($key === 'relation' || !is_array($query)) {
                continue;
            }
            if (!self::isFirstOrder($query)) {
                $queried = self::queried($query, $queried);
                continue;
            }
            if (empty($query['taxonomy']) || $query['operator'] === 'NOT IN') {
                continue;
            }
            $taxonomy = (string) $query['taxonomy'];
            $queried[$taxonomy] ??= [];
            if (!empty($query['terms']) && !isset($queried[$taxonomy]['terms'])) {
                $queried[$taxonomy]['terms'] = $query['terms'];
            }
            if (!empty($query['field']) && !isset($queried[$taxonomy]['field'])) {
                $queried[$taxonomy]['field'] = $query['field'];
            }
        }
        return $queried;
    }

    /**
     * The JOIN and WHERE fragments for a taxonomy query.
     *
     * @return array{join: string, where: string}
     */
    public function build(array $queries): array
    {
        if ($queries === []) {
            return ['join' => '', 'where' => ''];
        }
        $where = $this->group($queries, 0);
        return ['join' => implode(' ', $this->joins), 'where' => $where === '' ? '' : ' AND ' . $where];
    }

    private function group(array $queries, int $depth): string
    {
        $relation = $queries['relation'] ?? 'AND';
        $parts = [];
        $shared = null;
        foreach ($queries as $key => $query) {
            if ($key === 'relation' || !is_array($query)) {
                continue;
            }
            if (self::isFirstOrder($query)) {
                $parts[] = $this->clause($query, $relation, $shared);
            } else {
                $inner = $this->group($query, $depth + 1);
                if ($inner !== '') {
                    $parts[] = $inner;
                }
            }
        }
        return Sql::group(array_values(array_filter($parts, static fn (string $p) => $p !== '')), (string) $relation, $depth);
    }

    private function clause(array $clause, string $relation, ?string &$shared): string
    {
        $clause += ['taxonomy' => '', 'terms' => [], 'field' => 'term_id', 'operator' => 'IN', 'include_children' => true];
        $taxonomy = (string) $clause['taxonomy'];
        $operator = strtoupper((string) $clause['operator']);
        $operator = in_array($operator, ['IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS'], true) ? $operator : 'IN';
        $clause['terms'] = array_values(array_unique((array) $clause['terms']));
        $object = "{$this->primaryTable}.{$this->primaryId}";
        if ($operator === 'EXISTS' || $operator === 'NOT EXISTS') {
            // The reference's own layout, tabs and all (probe query-clauses).
            return ($operator === 'EXISTS' ? 'EXISTS' : 'NOT EXISTS') . " (\n\t\t\t\t\tSELECT 1\n\t\t\t\t\tFROM {$this->relationships}\n\t\t\t\t\tINNER JOIN {$this->termTaxonomy}\n\t\t\t\t\tON {$this->termTaxonomy}.term_taxonomy_id = {$this->relationships}.term_taxonomy_id\n\t\t\t\t\tWHERE {$this->termTaxonomy}.taxonomy = " . Sql::quote($taxonomy) . "\n\t\t\t\t\tAND {$this->relationships}.object_id = {$object}\n\t\t\t\t)";
        }
        $ids = ($this->termTaxonomyIds)($taxonomy, (string) $clause['field'], $clause['terms'], (bool) $clause['include_children'] && $operator !== 'AND');
        if ($ids === []) {
            return $operator === 'NOT IN' ? '' : '0 = 1';
        }
        sort($ids, SORT_NUMERIC);
        $list = implode(',', $ids);
        return match ($operator) {
            'NOT IN' => "{$object} NOT IN (\n\t\t\t\tSELECT object_id\n\t\t\t\tFROM {$this->relationships}\n\t\t\t\tWHERE term_taxonomy_id IN ({$list})\n\t\t\t)",
            'AND' => "(\n\t\t\t\tSELECT COUNT(1)\n\t\t\t\tFROM {$this->relationships}\n\t\t\t\tWHERE term_taxonomy_id IN ({$list})\n\t\t\t\tAND object_id = {$object}\n\t\t\t) = " . count($ids),
            default => $this->inClause($list, $relation, $shared),
        };
    }

    private function inClause(string $list, string $relation, ?string &$shared): string
    {
        if ($relation === 'OR' && $shared !== null) {
            $alias = $shared;
        } else {
            $alias = $this->aliasCount === 0 ? $this->relationships : 'tt' . $this->aliasCount;
            $this->aliasCount++;
            $as = $alias === $this->relationships ? $this->relationships : "{$this->relationships} AS {$alias}";
            $this->joins[] = " LEFT JOIN {$as} ON ({$this->primaryTable}.{$this->primaryId} = {$alias}.object_id)";
            $shared = $alias;
        }
        return "{$alias}.term_taxonomy_id IN ({$list})";
    }
}
