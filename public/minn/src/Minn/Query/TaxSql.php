<?php

declare(strict_types=1);

namespace Minn\Query;

use Closure;
use Minn\I18n\Gettext;
use Minn\Runtime\Runtime;

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
        return $queries === [] ? ['join' => '', 'where' => ''] : $this->sqlClauses($queries);
    }

    /** The whole query's JOIN and WHERE (get_sql_clauses), each clause settled in place. @return array{join: string, where: string} */
    public function sqlClauses(array &$queries): array
    {
        $sql = $this->querySql($queries, 0);
        return ['join' => $sql['join'], 'where' => $sql['where'] === '' ? '' : ' AND ' . $sql['where']];
    }

    /** A group's JOIN and WHERE (get_sql_for_query), indented for its depth. @return array{join: string, where: string} */
    public function querySql(array &$queries, int $depth): array
    {
        $joins = [];
        $parts = [];
        foreach (array_keys($queries) as $key) {
            if ($key === 'relation' || !is_array($queries[$key])) {
                continue;
            }
            $sql = self::isFirstOrder($queries[$key]) ? $this->clauseSql($queries[$key], $queries) : $this->querySql($queries[$key], $depth + 1);
            $joins = [...$joins, ...(array) $sql['join']];
            $parts = [...$parts, ...array_filter((array) $sql['where'], static fn (string $p) => $p !== '')];
        }
        return ['join' => implode(' ', array_filter($joins, static fn (string $j) => $j !== '')), 'where' => Sql::group($parts, (string) ($queries['relation'] ?? 'AND'), $depth)];
    }

    /**
     * One clause's JOIN and WHERE pieces (get_sql_for_clause). Its terms are
     * turned into term_taxonomy_ids in place; an IN clause reads
     * term_relationships under an alias it shares with a compatible sibling
     * or joins anew, and keeps that alias. A term nobody has yields "0 = 1"
     * (nothing at all for NOT IN).
     *
     * @return array{where: list<string>, join: list<string>}
     */
    public function clauseSql(array &$clause, array $parent): array
    {
        $clause += ['taxonomy' => '', 'terms' => [], 'field' => 'term_id', 'operator' => 'IN', 'include_children' => true];
        // A clause on a taxonomy that is not registered (or on none, unless it names term_taxonomy_ids) is an
        // error, its message asked as the reference makes it, and matches nothing, whatever its operator (probe query-clauses).
        $taxonomy = (string) $clause['taxonomy'];
        if ($taxonomy === '' ? $clause['field'] !== 'term_taxonomy_id' : Runtime::registry()->taxonomy($taxonomy) === null) {
            Gettext::text('Invalid taxonomy.');
            return ['where' => ['0 = 1'], 'join' => []];
        }
        $operator = self::operator($clause);
        $object = "{$this->primaryTable}.{$this->primaryId}";
        if ($operator === 'EXISTS' || $operator === 'NOT EXISTS') {
            // The reference's own layout, tabs and all (probe query-clauses).
            return ['where' => [$operator . " (\n\t\t\t\t\tSELECT 1\n\t\t\t\t\tFROM {$this->relationships}\n\t\t\t\t\tINNER JOIN {$this->termTaxonomy}\n\t\t\t\t\tON {$this->termTaxonomy}.term_taxonomy_id = {$this->relationships}.term_taxonomy_id\n\t\t\t\t\tWHERE {$this->termTaxonomy}.taxonomy = " . Sql::quote((string) $clause['taxonomy']) . "\n\t\t\t\t\tAND {$this->relationships}.object_id = {$object}\n\t\t\t\t)"], 'join' => []];
        }
        $ids = ($this->termTaxonomyIds)((string) $clause['taxonomy'], (string) $clause['field'], array_values(array_unique((array) $clause['terms'])), (bool) $clause['include_children'] && $operator !== 'AND');
        sort($ids, SORT_NUMERIC);
        if ($clause['terms'] !== []) {
            $clause['terms'] = $ids;
            $clause['field'] = 'term_taxonomy_id';
        }
        if ($ids === []) {
            return ['where' => $operator === 'NOT IN' ? [] : ['0 = 1'], 'join' => []];
        }
        $list = implode(',', $ids);
        return match ($operator) {
            'NOT IN' => ['where' => ["{$object} NOT IN (\n\t\t\t\tSELECT object_id\n\t\t\t\tFROM {$this->relationships}\n\t\t\t\tWHERE term_taxonomy_id IN ({$list})\n\t\t\t)"], 'join' => []],
            'AND' => ['where' => ["(\n\t\t\t\tSELECT COUNT(1)\n\t\t\t\tFROM {$this->relationships}\n\t\t\t\tWHERE term_taxonomy_id IN ({$list})\n\t\t\t\tAND object_id = {$object}\n\t\t\t) = " . count($ids)], 'join' => []],
            default => $this->inClause($clause, $parent, $list),
        };
    }

    /** The alias of an IN sibling the clause can share under OR (find_compatible_table_alias), or false. */
    public function compatibleAlias(array $clause, array $parent): string|false
    {
        if (($parent['relation'] ?? 'AND') !== 'OR' || self::operator($clause) !== 'IN') {
            return false;
        }
        foreach ($parent as $key => $sibling) {
            if ($key !== 'relation' && is_array($sibling) && isset($sibling['alias']) && self::operator($sibling) === 'IN') {
                return (string) $sibling['alias'];
            }
        }
        return false;
    }

    private static function operator(array $clause): string
    {
        $operator = strtoupper((string) ($clause['operator'] ?? 'IN'));
        return in_array($operator, ['IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS'], true) ? $operator : 'IN';
    }

    /** @return array{where: list<string>, join: list<string>} */
    private function inClause(array &$clause, array $parent, string $list): array
    {
        $alias = $this->compatibleAlias($clause, $parent);
        $join = [];
        if ($alias === false) {
            $alias = $this->aliasCount === 0 ? $this->relationships : 'tt' . $this->aliasCount;
            $this->aliasCount++;
            $as = $alias === $this->relationships ? $this->relationships : "{$this->relationships} AS {$alias}";
            $join[] = " LEFT JOIN {$as} ON ({$this->primaryTable}.{$this->primaryId} = {$alias}.object_id)";
        }
        $clause['alias'] = $alias;
        return ['where' => ["{$alias}.term_taxonomy_id IN ({$list})"], 'join' => $join];
    }
}
