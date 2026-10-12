<?php

declare(strict_types=1);

namespace Minn\Query;

use Closure;

/**
 * The JOIN and WHERE fragments of a meta query in the reference's shape:
 * one meta-table join per clause (the first under the table's own name,
 * then mt1, mt2, ...), INNER joins unless an OR relation or a NOT EXISTS
 * clause needs LEFT ones, and casts by the clause's declared type. Under
 * an OR relation a clause shares an equality-shaped sibling's join.
 */
final class MetaSql
{
    private const COMPATIBLE = ['=', 'IN', 'BETWEEN', 'LIKE', 'REGEXP', 'RLIKE', '>', '>=', '<', '<='];

    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE'];

    /** @var array<string, array{alias: string, cast: string}> */
    private array $clauses = [];

    private int $aliasCount = 0;

    /** @var (Closure(string|false, array, array): (string|false))|null */
    private ?Closure $aliasFilter = null;


    /** @param array<string, array{0: string, 1: string}> $tables meta type to [table, object column] */
    public function __construct(
        private readonly string $metaTable,
        private readonly string $objectColumn,
        private readonly string $primaryTable,
        private readonly string $primaryId,
    ) {
    }

    /**
     * Normalises a raw meta_query: clauses get key/value/compare/type
     * defaults, a nested group its relation, and a "relation" key sits on
     * every level. Named clauses keep their names.
     */
    public static function sanitize(array $queries): array
    {
        $clean = [];
        foreach ($queries as $key => $query) {
            if ($key === 'relation') {
                $clean['relation'] = strtoupper((string) $query) === 'OR' ? 'OR' : 'AND';
                continue;
            }
            if (!is_array($query)) {
                continue;
            }
            if (self::isFirstOrder($query)) {
                // A clause stays as given (an empty list value dropped); the builder settles its compare.
                if (array_key_exists('value', $query) && $query['value'] === []) {
                    unset($query['value']);
                }
                $clean[$key] = $query;
                continue;
            }
            $group = self::sanitize($query);
            if ($group !== []) {
                $clean[$key] = $group;
            }
        }
        if ($clean === []) {
            return [];
        }
        // One clause relates by OR, so key-only clauses combine (probe query-clauses); the relation comes last.
        $relation = count(array_diff_key($clean, ['relation' => true])) === 1 ? 'OR' : ($clean['relation'] ?? 'AND');
        unset($clean['relation']);
        $clean['relation'] = $relation;
        return $clean;
    }

    /** A clause with its compare and compare_key settled: upper case, IN for a list, = for anything unknown. @return array<string, mixed> */
    private static function normalized(array $clause): array
    {
        $compare = strtoupper((string) ($clause['compare'] ?? (isset($clause['value']) && is_array($clause['value']) ? 'IN' : '=')));
        $clause['compare'] = in_array($compare, self::OPERATORS, true) ? $compare : '=';
        $key = strtoupper((string) ($clause['compare_key'] ?? (isset($clause['key']) && is_array($clause['key']) ? 'IN' : '=')));
        $clause['compare_key'] = in_array($key, ['=', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'REGEXP', 'NOT REGEXP', 'RLIKE', 'EXISTS', 'NOT EXISTS'], true) ? $key : '=';
        return $clause;
    }

    /** A clause rather than a group: it names a key or a value. */
    public static function isFirstOrder(array $query): bool
    {
        return isset($query['key']) || isset($query['value']);
    }

    /**
     * The JOIN and WHERE fragments for a meta query.
     *
     * @return array{join: string, where: string}
     */
    public function build(array $queries): array
    {
        if ($queries === []) {
            return ['join' => '', 'where' => ''];
        }
        $sql = $this->sqlClauses($queries);
        // Once an OR group holds a NOT EXISTS, every join is a LEFT one (get_sql; the clauses alone keep theirs).
        return self::hasOrWithNotExists($queries) ? ['join' => str_replace('INNER JOIN', 'LEFT JOIN', $sql['join']), 'where' => $sql['where']] : $sql;
    }

    /** The whole query's JOIN and WHERE (get_sql_clauses), each clause given its alias in place. @return array{join: string, where: string} */
    public function sqlClauses(array &$queries): array
    {
        $sql = $this->querySql($queries, 0);
        return ['join' => $sql['join'], 'where' => $sql['where'] === '' ? '' : ' AND ' . $sql['where']];
    }

    /**
     * A group's JOIN and WHERE (get_sql_for_query), indented for its depth;
     * each clause is given its alias in place, so its siblings can share it.
     *
     * @return array{join: string, where: string}
     */
    public function querySql(array &$queries, int $depth): array
    {
        $joins = [];
        $parts = [];
        foreach (array_keys($queries) as $key) {
            if ($key === 'relation' || !is_array($queries[$key])) {
                continue;
            }
            $sql = self::isFirstOrder($queries[$key]) ? $this->clauseSql($queries[$key], $queries, is_string($key) ? $key : '') : $this->querySql($queries[$key], $depth + 1);
            $joins = [...$joins, ...(array) $sql['join']];
            $parts = [...$parts, ...array_filter((array) $sql['where'], static fn (string $p) => $p !== '')];
        }
        return ['join' => implode(' ', array_filter($joins, static fn (string $j) => $j !== '')), 'where' => Sql::group($parts, (string) ($queries['relation'] ?? 'AND'), $depth)];
    }

    /**
     * One clause's JOIN and WHERE pieces (get_sql_for_clause): the clause is
     * settled in place (compare, compare_key, then its alias and cast), its
     * alias shared with a compatible sibling or a new join.
     *
     * @return array{where: list<string>, join: list<string>}
     */
    public function clauseSql(array &$clause, array $parent, string $key): array
    {
        $clause = self::normalized($clause);
        $alias = $this->compatibleAlias($clause, $parent);
        if ($this->aliasFilter !== null) {
            $alias = ($this->aliasFilter)($alias, $clause, $parent);
        }
        $join = [];
        if ($alias === false) {
            [$alias, $join] = $this->newAlias($clause);
        }
        $clause['alias'] = $alias;
        $clause['cast'] = self::cast((string) ($clause['type'] ?? ''));
        $this->clauses[$key !== '' ? $key : $alias] = $clause;
        $where = $this->clause($clause);
        return ['where' => $where === '' ? [] : [$where], 'join' => $join];
    }

    /** Has every clause's shared alias (or false) pass through a filter (WP_Meta_Query hands it meta_query_find_compatible_table_alias). */
    public function filterAliasesWith(Closure $filter): self
    {
        $this->aliasFilter = $filter;
        return $this;
    }

    /**
     * The alias of a sibling the clause can share (find_compatible_table_alias):
     * under OR, the first sibling already given an alias whose comparison,
     * like the clause's, reads the same join; false otherwise.
     */
    public function compatibleAlias(array $clause, array $parent): string|false
    {
        if (($parent['relation'] ?? 'AND') !== 'OR' || !in_array(self::normalized($clause)['compare'], self::COMPATIBLE, true)) {
            return false;
        }
        foreach ($parent as $key => $sibling) {
            if ($key !== 'relation' && is_array($sibling) && isset($sibling['alias']) && in_array(self::normalized($sibling)['compare'], self::COMPATIBLE, true)) {
                return (string) $sibling['alias'];
            }
        }
        return false;
    }

    /**
     * The clauses the last build resolved, by name.
     *
     * @return array<string, array{alias: string, cast: string}> clauses seen while building, by name or alias
     */
    public function clauses(): array
    {
        return $this->clauses;
    }

    /** An OR group holding a NOT EXISTS clause makes every join a LEFT one. */
    private static function hasOrWithNotExists(array $queries): bool
    {
        $or = ($queries['relation'] ?? 'AND') === 'OR';
        foreach ($queries as $key => $query) {
            if ($key === 'relation' || !is_array($query)) {
                continue;
            }
            if (self::isFirstOrder($query)) {
                if ($or && ($query['compare'] ?? '=') === 'NOT EXISTS') {
                    return true;
                }
            } elseif (self::hasOrWithNotExists($query)) {
                return true;
            }
        }
        return false;
    }

    /** Whether any level of the query relates two or more clauses by OR. */
    public static function hasOr(array $queries): bool
    {
        if (($queries['relation'] ?? '') === 'OR' && count(array_diff_key($queries, ['relation' => true])) > 1) {
            return true;
        }
        foreach ($queries as $key => $query) {
            if (is_array($query) && !self::isFirstOrder($query) && self::hasOr($query)) {
                return true;
            }
        }
        return false;
    }

    /** A settled clause's WHERE piece, read from its alias. */
    private function clause(array $clause): string
    {
        $alias = (string) $clause['alias'];
        $compare = (string) $clause['compare'];
        if ($compare === 'NOT EXISTS') {
            return "{$alias}.{$this->objectColumn} IS NULL";
        }
        $parts = [];
        if (array_key_exists('key', $clause) && $clause['key'] !== '') {
            $parts[] = $this->keyClause($alias, $clause);
        }
        if (array_key_exists('value', $clause) && $compare !== 'EXISTS') {
            $column = $clause['cast'] === 'CHAR' ? "{$alias}.meta_value" : "CAST({$alias}.meta_value AS {$clause['cast']})";
            $parts[] = $column . ' ' . self::valueClause($compare, $clause['value']);
        }
        return count($parts) > 1 ? '( ' . implode(' AND ', $parts) . ' )' : (string) ($parts[0] ?? '');
    }

    private function keyClause(string $alias, array $clause): string
    {
        $keys = (array) $clause['key'];
        return match ((string) $clause['compare_key']) {
            'IN', 'NOT IN' => "{$alias}.meta_key {$clause['compare_key']} (" . Sql::list($keys) . ')',
            'LIKE', 'NOT LIKE' => "{$alias}.meta_key {$clause['compare_key']} " . Sql::quote('%' . Sql::like((string) $keys[0]) . '%'),
            'REGEXP', 'NOT REGEXP', 'RLIKE' => "{$alias}.meta_key {$clause['compare_key']} " . Sql::quote((string) $keys[0]),
            '!=', 'NOT EXISTS' => "{$alias}.meta_key != " . Sql::quote((string) $keys[0]),
            default => "{$alias}.meta_key = " . Sql::quote((string) $keys[0]),
        };
    }

    private static function valueClause(string $compare, mixed $value): string
    {
        return match ($compare) {
            'IN', 'NOT IN' => "{$compare} (" . Sql::list(self::values($value)) . ')',
            'BETWEEN', 'NOT BETWEEN' => "{$compare} " . Sql::list([self::values($value)[0] ?? '']) . ' AND ' . Sql::list([self::values($value)[1] ?? '']),
            'LIKE', 'NOT LIKE' => "{$compare} " . Sql::quote('%' . Sql::like((string) (is_array($value) ? reset($value) : $value)) . '%'),
            'EXISTS' => '',
            default => "{$compare} " . Sql::quote((string) (is_array($value) ? reset($value) : $value)),
        };
    }

    /** @return list<string> a list, or a comma-separated string split */
    private static function values(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }
        return preg_split('/[,\s]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** A new alias for a clause, and the join that reads it: LEFT for NOT EXISTS (with the key), INNER otherwise. @return array{0: string, 1: list<string>} */
    private function newAlias(array $clause): array
    {
        $alias = $this->aliasCount === 0 ? $this->metaTable : 'mt' . $this->aliasCount;
        $this->aliasCount++;
        $compare = (string) $clause['compare'];
        $as = $alias === $this->metaTable ? $this->metaTable : "{$this->metaTable} AS {$alias}";
        $on = "{$this->primaryTable}.{$this->primaryId} = {$alias}.{$this->objectColumn}" . ($compare === 'NOT EXISTS' ? ' AND ' . $this->keyClause($alias, $clause) : '');
        return [$alias, [' ' . ($compare === 'NOT EXISTS' ? 'LEFT JOIN' : 'INNER JOIN') . " {$as} ON ( {$on} )"]];
    }

    /** The CAST target for a clause type; CHAR means no cast. */
    public static function cast(string $type): string
    {
        $type = strtoupper($type);
        if (preg_match('/^DECIMAL(\(\d+(?:,\s*\d+)?\))?$/', $type)) {
            return $type;
        }
        return match ($type) {
            'NUMERIC' => 'SIGNED',
            'BINARY', 'DATE', 'DATETIME', 'SIGNED', 'TIME', 'UNSIGNED' => $type,
            default => 'CHAR',
        };
    }
}
