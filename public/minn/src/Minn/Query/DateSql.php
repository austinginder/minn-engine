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


    private const PART_ORDER = ['year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second'];

    /** The ranges a date part is checked against, as the reference names them in its notice. */
    private const RANGES = ['month' => [1, 12], 'week' => [1, 53], 'dayofyear' => [1, 366], 'day' => [1, 31], 'dayofweek' => [1, 7], 'dayofweek_iso' => [1, 7], 'hour' => [0, 23], 'minute' => [0, 59], 'second' => [0, 59]];

    /** @param array<string, string> $tables table key to prefixed table name */
    public function __construct(private readonly array $tables, private readonly string $defaultColumn = 'post_date', private readonly int $startOfWeek = 1)
    {
    }

    /** The week of a column as the reference counts it from the site's first day of the week. */
    public static function week(string $column, int $startOfWeek): string
    {
        return match (true) {
            $startOfWeek === 1 => "WEEK( {$column}, 1 )",
            $startOfWeek >= 2 && $startOfWeek <= 6 => "WEEK( DATE_SUB( {$column}, INTERVAL {$startOfWeek} DAY ), 0 )",
            default => "WEEK( {$column}, 0 )",
        };
    }

    /** Notices for parts out of range and for a year, month and day that make no date (probe query-clauses). */
    public static function validate(array $clause): void
    {
        if (!function_exists('_doing_it_wrong')) {
            return;
        }
        foreach (self::RANGES as $key => [$min, $max]) {
            foreach ((array) ($clause[$key] ?? []) as $value) {
                if (is_numeric($value) && ((int) $value < $min || (int) $value > $max)) {
                    \_doing_it_wrong('WP_Date_Query', sprintf('Invalid value %1$s for %2$s. Expected value should be between %3$s and %4$s.', (string) $value, $key, $min, $max), '4.1.0');
                }
            }
        }
        $date = array_filter(['year' => $clause['year'] ?? null, 'month' => $clause['month'] ?? $clause['monthnum'] ?? null, 'day' => $clause['day'] ?? null], static fn ($v) => is_numeric($v));
        if (count($date) === 3 && !checkdate((int) $date['month'], (int) $date['day'], (int) $date['year'])) {
            $named = implode(', ', array_map(static fn ($key, $value) => "{$key} {$value}", array_keys($date), $date));
            \_doing_it_wrong('WP_Date_Query', sprintf('The following values do not describe a valid date: %s.', $named), '4.1.0');
        }
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
                self::validate($query);
                $clean[$key] = $query + $defaults;
            }
        }
        if ($clean === [] && !self::isFirstOrder($queries)) {
            return [];
        }
        if (self::isFirstOrder($queries)) {
            self::validate($queries);
            $clean[0] = array_diff_key($queries, ['column' => 1, 'compare' => 1, 'relation' => 1]) + $defaults;
        }
        $clean['column'] = $queries['column'] ?? $defaults['column'];
        $clean['compare'] = self::compare((string) ($queries['compare'] ?? $defaults['compare']));
        $clean['relation'] = strtoupper((string) ($queries['relation'] ?? $defaults['relation'])) === 'OR' ? 'OR' : 'AND';
        return $clean;
    }

    /** Whether a date query is one clause rather than a group of them. */
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

    /** The WHERE fragment for a date query. */
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
        return Sql::group(array_values(array_filter($parts, static fn (string $p) => $p !== '')), (string) $relation, $depth);
    }

    /**
     * One clause as the reference writes it: the bounds, the date parts in
     * order (a part whose value builds to nothing is left out), then the
     * time; more than one piece joined by AND inside parentheses.
     */
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
        $expressions = [
            'year' => "YEAR( {$column} )", 'month' => "MONTH( {$column} )", 'monthnum' => "MONTH( {$column} )",
            'week' => self::week($column, $this->startOfWeek), 'w' => self::week($column, $this->startOfWeek),
            'dayofyear' => "DAYOFYEAR( {$column} )", 'day' => "DAYOFMONTH( {$column} )", 'dayofweek' => "DAYOFWEEK( {$column} )", 'dayofweek_iso' => "WEEKDAY( {$column} ) + 1",
        ];
        // monthnum and w stand in for month and week only when those are not given.
        $alternates = ['monthnum' => 'month', 'w' => 'week'];
        foreach ($expressions as $key => $expression) {
            if (isset($alternates[$key]) && isset($clause[$alternates[$key]])) {
                continue;
            }
            $value = isset($clause[$key]) ? self::value($compare, $clause[$key]) : '';
            if ($value !== '' && $value !== '0') {
                $parts[] = "{$expression} {$compare} {$value}";
            }
        }
        $time = self::time($column, $compare, $clause);
        if ($time !== '') {
            $parts[] = $time;
        }
        return count($parts) > 1 ? '( ' . implode(' AND ', $parts) . ' )' : (string) ($parts[0] ?? '');
    }

    /** A part's value as the comparison wants it: a list, a range, or one number. */
    private static function value(string $compare, mixed $value): string
    {
        $values = array_map('intval', array_values((array) $value));
        if ($values === []) {
            return '';
        }
        return match ($compare) {
            'IN', 'NOT IN' => '(' . implode(',', $values) . ')',
            'BETWEEN', 'NOT BETWEEN' => count($values) >= 2 ? "{$values[0]} AND {$values[1]}" : "{$values[0]} AND {$values[0]}",
            default => (string) $values[0],
        };
    }

    /**
     * The time of day: listed or ranged parts each on their own; a single
     * part by its function; an hour with a second and no minute not at
     * all; otherwise the parts as one decimal, DATE_FORMAT( col, '%H.%i%s' ).
     */
    private static function time(string $column, string $compare, array $clause): string
    {
        $hour = isset($clause['hour']) && $clause['hour'] !== '' ? $clause['hour'] : null;
        $minute = isset($clause['minute']) && $clause['minute'] !== '' ? $clause['minute'] : null;
        $second = isset($clause['second']) && $clause['second'] !== '' ? $clause['second'] : null;
        if ($hour === null && $minute === null && $second === null) {
            return '';
        }
        if (in_array($compare, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true)) {
            $pieces = [];
            foreach (['HOUR' => $hour, 'MINUTE' => $minute, 'SECOND' => $second] as $function => $value) {
                if ($value !== null) {
                    $pieces[] = "{$function}( {$column} ) {$compare} " . self::value($compare, $value);
                }
            }
            return implode(' AND ', $pieces);
        }
        $set = array_filter(['HOUR' => $hour, 'MINUTE' => $minute, 'SECOND' => $second], static fn ($v) => $v !== null);
        if (count($set) === 1) {
            return array_key_first($set) . "( {$column} ) {$compare} " . (int) reset($set);
        }
        if ($minute === null) {
            return '';
        }
        $format = ($hour !== null ? '%H.' : '0.') . '%i' . ($second !== null ? '%s' : '');
        $time = ($hour !== null ? (int) $hour . '.' : '0.') . sprintf('%02d', (int) $minute) . ($second !== null ? sprintf('%02d', (int) $second) : '');
        return "DATE_FORMAT( {$column}, '{$format}' ) {$compare} " . sprintf('%F', (float) $time);
    }

    /**
     * A full datetime from a string or a parts array. A string as precise as
     * a year, a month, a day or a minute is read as those parts, so an end
     * bound reaches the end of its unit; any other string is read as a time
     * in the site's zone. Missing parts start (or end) the unit.
     */
    public static function datetime(mixed $value, bool $endOfUnit): string
    {
        if (!is_array($value)) {
            $value = (string) $value;
            $patterns = ['/^(\d{4})$/' => ['year'], '/^(\d{4})-(\d{2})$/' => ['year', 'month'], '/^(\d{4})-(\d{2})-(\d{2})$/' => ['year', 'month', 'day'], '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/' => ['year', 'month', 'day', 'hour', 'minute']];
            foreach ($patterns as $pattern => $keys) {
                if (preg_match($pattern, $value, $m) === 1) {
                    return self::datetime(array_combine($keys, array_map('intval', array_slice($m, 1))), $endOfUnit);
                }
            }
            $zone = function_exists('wp_timezone') ? \wp_timezone() : null;
            $date = date_create($value, $zone);
            return $date === false ? gmdate('Y-m-d H:i:s', 0) : ($zone === null ? $date : $date->setTimezone($zone))->format('Y-m-d H:i:s');
        }
        $value = array_map('absint', $value);
        $year = (int) ($value['year'] ?? (function_exists('current_time') ? \current_time('Y') : date('Y')));
        $month = (int) ($value['month'] ?? ($endOfUnit ? 12 : 1));
        $day = (int) ($value['day'] ?? ($endOfUnit ? (int) gmdate('t', (int) mktime(0, 0, 0, $month, 1, $year)) : 1));
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
