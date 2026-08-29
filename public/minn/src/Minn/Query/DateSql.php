<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * The WHERE fragment of a date query in the reference's shape: before and
 * after bounds as full datetimes (inclusive bounds close the day), the
 * date parts as MySQL functions compared or listed, groups joined by
 * their relation, columns validated against the known date columns.
 */
final class DateSql
{
    private const COLUMNS = [
        'posts' => ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'],
        'comments' => ['comment_date', 'comment_date_gmt'],
        'users' => ['user_registered'],
        'blogs' => ['registered', 'last_updated'],
    ];

    private const PARTS = [
        'year' => 'YEAR', 'month' => 'MONTH', 'monthnum' => 'MONTH', 'week' => 'WEEK', 'w' => 'WEEK', 'dayofyear' => 'DAYOFYEAR',
        'day' => 'DAYOFMONTH', 'dayofweek' => 'DAYOFWEEK', 'dayofweek_iso' => 'WEEKDAY', 'hour' => 'HOUR', 'minute' => 'MINUTE', 'second' => 'SECOND',
    ];

    private const PART_ORDER = ['year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second'];

    /** @param array<string, string> $tables table key to prefixed table name */
    public function __construct(private readonly array $tables, private readonly string $defaultColumn = 'post_date')
    {
    }

    /** Sanitises to the reference's shape: every level carries column, compare and relation. */
    public function sanitize(array $queries, array $defaults): array
    {
        $clean = [];
        foreach ($queries as $key => $query) {
            if (is_array($query) && !self::isFirstOrder($query)) {
                $inner = $this->sanitize($query, $defaults);
                if ($inner !== []) {
                    $clean[$key] = $inner;
                }
            } elseif (is_array($query)) {
                $clean[$key] = $query + $defaults;
            }
        }
        if ($clean === [] && !self::isFirstOrder($queries)) {
            return [];
        }
        if (self::isFirstOrder($queries)) {
            $clean[0] = array_diff_key($queries, ['column' => 1, 'compare' => 1, 'relation' => 1]) + $defaults;
        }
        $clean['column'] = $queries['column'] ?? $defaults['column'];
        $clean['compare'] = self::compare((string) ($queries['compare'] ?? $defaults['compare']));
        $clean['relation'] = strtoupper((string) ($queries['relation'] ?? $defaults['relation'])) === 'OR' ? 'OR' : 'AND';
        return $clean;
    }

    public static function isFirstOrder(array $query): bool
    {
        foreach (array_merge(['after', 'before', 'column', 'compare'], self::PART_ORDER) as $key) {
            if (array_key_exists($key, $query) && $key !== 'column' && $key !== 'compare') {
                return true;
            }
        }
        return false;
    }

    /** A column name as "table.column", the default for anything unknown. */
    public function validateColumn(string $column): string
    {
        $column = (string) preg_replace('/[^a-zA-Z0-9_$.]/', '', $column);
        if (str_contains($column, '.')) {
            return $column;
        }
        foreach (self::COLUMNS as $table => $columns) {
            if (in_array($column, $columns, true)) {
                return $this->tables[$table] . '.' . $column;
            }
        }
        return $this->tables['posts'] . '.' . $this->defaultColumn;
    }

    public function build(array $queries): string
    {
        $where = $this->group($queries, 0);
        return $where === '' ? '' : ' AND ' . $where;
    }

    private function group(array $queries, int $depth): string
    {
        $relation = $queries['relation'] ?? 'AND';
        $parts = [];
        foreach ($queries as $key => $query) {
            if (!is_array($query)) {
                continue;
            }
            $parts[] = self::isFirstOrder($query) ? $this->clause($query) : $this->group($query, $depth + 1);
        }
        $parts = array_values(array_filter($parts, static fn (string $p) => $p !== ''));
        if ($parts === []) {
            return '';
        }
        $sql = implode(" {$relation} ", $parts);
        return count($parts) > 1 || $depth === 0 ? "( {$sql} )" : $sql;
    }

    private function clause(array $clause): string
    {
        $column = $this->validateColumn((string) ($clause['column'] ?? $this->defaultColumn));
        $compare = self::compare((string) ($clause['compare'] ?? '='));
        $parts = [];
        $inclusive = !empty($clause['inclusive']);
        if (!empty($clause['after'])) {
            $parts[] = "{$column} " . ($inclusive ? '>=' : '>') . ' ' . Sql::quote(self::datetime($clause['after'], !$inclusive));
        }
        if (!empty($clause['before'])) {
            $parts[] = "{$column} " . ($inclusive ? '<=' : '<') . ' ' . Sql::quote(self::datetime($clause['before'], $inclusive));
        }
        foreach (self::PART_ORDER as $key) {
            if (!isset($clause[$key]) || $clause[$key] === '' || $clause[$key] === []) {
                continue;
            }
            $part = self::part(self::PARTS[$key] . "( {$column} )", $compare, $clause[$key]);
            if ($part !== '') {
                $parts[] = $part;
            }
        }
        return count($parts) > 1 ? '( ' . implode(' AND ', $parts) . ' )' : (string) ($parts[0] ?? '');
    }

    private static function part(string $expression, string $compare, mixed $value): string
    {
        $values = array_map('intval', array_values((array) $value));
        if ($values === []) {
            return '';
        }
        return match ($compare) {
            'IN', 'NOT IN' => "{$expression} {$compare} (" . implode(',', $values) . ')',
            'BETWEEN', 'NOT BETWEEN' => count($values) >= 2 ? "{$expression} {$compare} {$values[0]} AND {$values[1]}" : "{$expression} {$compare} {$values[0]} AND {$values[0]}",
            default => "{$expression} {$compare} {$values[0]}",
        };
    }

    /** A full datetime from a string or a parts array; a parts array rounds up to the end of its unit when asked. */
    public static function datetime(mixed $value, bool $endOfUnit): string
    {
        if (!is_array($value)) {
            $time = strtotime((string) $value);
            return $time === false ? gmdate('Y-m-d H:i:s', 0) : date('Y-m-d H:i:s', $time);
        }
        $year = (int) ($value['year'] ?? date('Y'));
        $month = (int) ($value['month'] ?? ($endOfUnit ? 12 : 1));
        $day = (int) ($value['day'] ?? ($endOfUnit ? (int) date('t', mktime(0, 0, 0, $month, 1, $year)) : 1));
        $hour = (int) ($value['hour'] ?? ($endOfUnit ? 23 : 0));
        $minute = (int) ($value['minute'] ?? ($endOfUnit ? 59 : 0));
        $second = (int) ($value['second'] ?? ($endOfUnit ? 59 : 0));
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    private static function compare(string $compare): string
    {
        $compare = strtoupper($compare);
        return in_array($compare, ['=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true) ? $compare : '=';
    }
}
