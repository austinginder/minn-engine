<?php

declare(strict_types=1);

namespace Minn\Query;

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

    /** @var list<string> */
    private array $joins = [];

    private bool $orRelation = false;

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
        $this->orRelation = self::hasOrWithNotExists($queries);
        $where = $this->group($queries, 0);
        return ['join' => implode(' ', $this->joins), 'where' => $where === '' ? '' : ' AND ' . $where];
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

    private function group(array $queries, int $depth): string
    {
        $relation = $queries['relation'] ?? 'AND';
        $parts = [];
        $siblings = [];
        foreach ($queries as $key => $query) {
            if ($key === 'relation' || !is_array($query)) {
                continue;
            }
            if (self::isFirstOrder($query)) {
                $parts[] = $this->clause($query, is_string($key) ? $key : null, $relation, $siblings);
            } else {
                $inner = $this->group($query, $depth + 1);
                if ($inner !== '') {
                    $parts[] = $inner;
                }
            }
        }
        return Sql::group(array_values(array_filter($parts, static fn (string $p) => $p !== '')), (string) $relation, $depth);
    }

    /** @param list<array{compare: string, alias: string}> $siblings earlier clauses of the same group */
    private function clause(array $clause, ?string $name, string $relation, array &$siblings): string
    {
        $clause = self::normalized($clause);
        $compare = (string) $clause['compare'];
        $alias = $this->alias($clause, $relation, $siblings);
        $siblings[] = ['compare' => $compare, 'alias' => $alias];
        $cast = self::cast((string) ($clause['type'] ?? ''));
        // The clause as the builder settled it, then where it reads from.
        $this->clauses[$name ?? $alias] = $clause + ['alias' => $alias, 'cast' => $cast];
        if ($compare === 'NOT EXISTS') {
            return "{$alias}.{$this->objectColumn} IS NULL";
        }
        $parts = [];
        if (array_key_exists('key', $clause) && $clause['key'] !== '') {
            $parts[] = $this->keyClause($alias, $clause);
        }
        if (array_key_exists('value', $clause) && $compare !== 'EXISTS') {
            $column = $cast === 'CHAR' ? "{$alias}.meta_value" : "CAST({$alias}.meta_value AS {$cast})";
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

    /** @param list<array{compare: string, alias: string}> $siblings */
    private function alias(array $clause, string $relation, array $siblings): string
    {
        $compare = (string) $clause['compare'];
        if ($relation === 'OR' && in_array($compare, self::COMPATIBLE, true)) {
            foreach ($siblings as $sibling) {
                if (in_array($sibling['compare'], self::COMPATIBLE, true)) {
                    return $sibling['alias'];
                }
            }
        }
        $alias = $this->aliasCount === 0 ? $this->metaTable : 'mt' . $this->aliasCount;
        $this->aliasCount++;
        $joinType = $compare === 'NOT EXISTS' || $this->orRelation ? 'LEFT JOIN' : 'INNER JOIN';
        $as = $alias === $this->metaTable ? $this->metaTable : "{$this->metaTable} AS {$alias}";
        $on = "{$this->primaryTable}.{$this->primaryId} = {$alias}.{$this->objectColumn}";
        if ($compare === 'NOT EXISTS') {
            $on .= ' AND ' . $this->keyClause($alias, $clause);
        }
        $this->joins[] = " {$joinType} {$as} ON ( {$on} )";
        return $alias;
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
