<?php

declare(strict_types=1);

namespace Minn\Query;

use Closure;
use Minn\I18n\Gettext;

/**
 * The WHERE fragment of a date query in the reference's shape: before and
 * after bounds as full datetimes (inclusive bounds close the day), the
 * date parts as MySQL functions compared or listed, groups joined by
 * their relation, columns validated against the known date columns.
 */
final class DateSql
{
    /** @var (Closure(list<string>): array)|null */
    private ?Closure $validColumns = null;

    private const COLUMNS = [
        'posts' => ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'],
        'comments' => ['comment_date', 'comment_date_gmt'],
        'users' => ['user_registered'],
        'blogs' => ['registered', 'last_updated'],
    ];


    private const PART_ORDER = ['year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second'];

    /** The ranges a date part is checked against, in the order the reference reports them (week's top depends on the year). */
    private const RANGES = ['dayofyear' => [1, 366], 'dayofweek' => [1, 7], 'dayofweek_iso' => [1, 7], 'month' => [1, 12], 'week' => [1, 53], 'day' => [1, 31], 'hour' => [0, 23], 'minute' => [0, 59], 'second' => [0, 59]];

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

    /**
     * Whether a clause's date values are valid, with a notice for each part
     * out of range (week up to the given year's ISO weeks, 53 without one)
     * and for a month and day that make no date (in a leap year when no year
     * is given, and only once every part is in range); before and after given
     * as parts are checked too (probes
     * query-clauses, query-clause-internals). An empty clause is not valid.
     */
    public static function validate(array $clause): bool
    {
        if ($clause === [] || !function_exists('_doing_it_wrong')) {
            return $clause !== [];
        }
        $valid = true;
        foreach (['before', 'after'] as $bound) {
            if (is_array($clause[$bound] ?? null)) {
                $valid = self::validate($clause[$bound]) && $valid;
            }
        }
        $ranges = self::RANGES;
        $ranges['week'][1] = is_numeric($clause['year'] ?? null) ? (int) gmdate('W', (int) gmmktime(0, 0, 0, 12, 28, (int) $clause['year'])) : 53;
        foreach ($ranges as $key => [$min, $max]) {
            foreach ((array) ($clause[$key] ?? []) as $value) {
                if (is_numeric($value) && ((int) $value < $min || (int) $value > $max)) {
                    \_doing_it_wrong('WP_Date_Query', sprintf(Gettext::text('Invalid value %1$s for %2$s. Expected value should be between %3$s and %4$s.'), (string) $value, $key, $min, $max), '4.1.0');
                    $valid = false;
                }
            }
        }
        // A month and day are checked as a date only once every part is in range.
        return $valid && self::validDate($clause);
    }

    /** Whether a clause's month and day make a date, in its year or a leap year; a notice names them when they do not. */
    private static function validDate(array $clause): bool
    {
        $date = array_filter(['year' => $clause['year'] ?? null, 'month' => $clause['month'] ?? null, 'day' => $clause['day'] ?? null], static fn ($v) => is_numeric($v));
        if (!isset($date['month'], $date['day']) || checkdate((int) $date['month'], (int) $date['day'], (int) ($date['year'] ?? 2012))) {
            return true;
        }
        $message = isset($date['year'])
            ? sprintf(Gettext::text('The following values do not describe a valid date: year %1$s, month %2$s, day %3$s.'), $date['year'], $date['month'], $date['day'])
            : sprintf(Gettext::text('The following values do not describe a valid date: month %1$s, day %2$s.'), $date['month'], $date['day']);
        \_doing_it_wrong('WP_Date_Query', $message, '4.1.0');
        return false;
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
        // The columns a date may name on a single site, as the caller's filter leaves them (date_query_valid_columns).
        $valid = [...self::COLUMNS['posts'], ...self::COLUMNS['comments'], ...self::COLUMNS['users']];
        $valid = $this->validColumns === null ? $valid : (array) ($this->validColumns)($valid);
        if (!in_array($column, $valid, true)) {
            $column = $this->defaultColumn;
        }
        foreach (self::COLUMNS as $table => $columns) {
            if (in_array($column, $columns, true)) {
                return $this->tables[$table] . '.' . $column;
            }
        }
        return $column;
    }

    /** Has the valid columns pass through a filter (WP_Date_Query hands it date_query_valid_columns). */
    public function checkColumnsWith(Closure $validColumns): self
    {
        $this->validColumns = $validColumns;
        return $this;
    }

    /** The WHERE fragment for a date query. */
    public function build(array $queries): string
    {
        $where = $this->group($queries, 0);
        return $where === '' ? '' : ' AND ' . $where;
    }

    /** A query or group as the reference's get_sql_for_query returns it, indented for its depth. @return array{join: string, where: string} */
    public function forQuery(array $queries, int $depth): array
    {
        return ['join' => '', 'where' => $this->group($queries, $depth)];
    }

    /**
     * One clause's pieces, before they are joined: the bounds, the date parts
     * in order (a part whose value builds to nothing, or to 0, is left out),
     * then the time.
     *
     * @return list<string>
     */
    public function clauseParts(array $clause): array
    {
        $column = $this->validateColumn((string) ($clause['column'] ?? $this->defaultColumn));
        $compare = self::compare((string) ($clause['compare'] ?? '='));
        $inclusive = !empty($clause['inclusive']);
        $parts = [];
        if (!empty($clause['after'])) {
            $parts[] = "{$column} " . ($inclusive ? '>=' : '>') . ' ' . Sql::quote(self::datetime($clause['after'], !$inclusive));
        }
        if (!empty($clause['before'])) {
            $parts[] = "{$column} " . ($inclusive ? '<=' : '<') . ' ' . Sql::quote(self::datetime($clause['before'], $inclusive));
        }
        foreach ($this->partExpressions($column, $clause) as $key => $expression) {
            $value = self::buildValue($compare, $clause[$key]);
            if ($value !== false && $value !== 0 && $value !== '0') {
                $parts[] = "{$expression} {$compare} {$value}";
            }
        }
        $time = self::buildTime($column, $compare, $clause['hour'] ?? null, $clause['minute'] ?? null, $clause['second'] ?? null);
        return $time === false || $time === '' ? $parts : [...$parts, $time];
    }

    /**
     * A value as the comparison wants it (build_value): a list of numbers
     * for IN, exactly two for BETWEEN, one number otherwise; false when the
     * value does not fit.
     */
    public static function buildValue(string $compare, mixed $value): string|int|false
    {
        if ($value === null || $value === '') {
            return false;
        }
        return match ($compare) {
            'IN', 'NOT IN' => array_filter((array) $value, static fn ($v) => !is_numeric($v)) === [] ? '(' . implode(',', array_map('intval', (array) $value)) . ')' : false,
            'BETWEEN', 'NOT BETWEEN' => is_array($value) && count($value) === 2 ? (int) reset($value) . ' AND ' . (int) next($value) : false,
            default => !is_array($value) && is_numeric($value) ? (int) $value : false,
        };
    }

    /**
     * The time of day (build_time_query): listed or ranged parts each on
     * their own; a single part by its function; an hour with a second and no
     * minute not at all; otherwise the parts as one decimal, DATE_FORMAT( col,
     * '%H.%i%s' ). False when no part is given.
     */
    public static function buildTime(string $column, string $compare, mixed $hour, mixed $minute, mixed $second): string|false
    {
        $set = array_filter(['HOUR' => $hour, 'MINUTE' => $minute, 'SECOND' => $second], static fn ($v) => $v !== null && $v !== '');
        if ($set === []) {
            return false;
        }
        if (in_array($compare, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true)) {
            $pieces = [];
            foreach ($set as $function => $value) {
                $built = self::buildValue($compare, $value);
                $pieces = $built === false ? $pieces : [...$pieces, "{$function}( {$column} ) {$compare} {$built}"];
            }
            return implode(' AND ', $pieces);
        }
        if (count($set) === 1) {
            return array_key_first($set) . "( {$column} ) {$compare} " . (int) reset($set);
        }
        if (!isset($set['MINUTE'])) {
            return '';
        }
        $format = (isset($set['HOUR']) ? '%H.' : '0.') . '%i' . (isset($set['SECOND']) ? '%s' : '');
        $time = (isset($set['HOUR']) ? (int) $set['HOUR'] . '.' : '0.') . sprintf('%02d', (int) $set['MINUTE']) . (isset($set['SECOND']) ? sprintf('%02d', (int) $set['SECOND']) : '');
        return "DATE_FORMAT( {$column}, '{$format}' ) {$compare} " . sprintf('%F', (float) $time);
    }

    /** The date parts a clause gives, each as its SQL expression; monthnum and w stand in for month and week only when those are not given. @return array<string, string> */
    private function partExpressions(string $column, array $clause): array
    {
        $week = self::week($column, $this->startOfWeek);
        $expressions = ['year' => "YEAR( {$column} )", 'month' => "MONTH( {$column} )", 'monthnum' => "MONTH( {$column} )", 'week' => $week, 'w' => $week, 'dayofyear' => "DAYOFYEAR( {$column} )", 'day' => "DAYOFMONTH( {$column} )", 'dayofweek' => "DAYOFWEEK( {$column} )", 'dayofweek_iso' => "WEEKDAY( {$column} ) + 1"];
        $alternates = ['monthnum' => 'month', 'w' => 'week'];
        return array_filter($expressions, static fn (string $key) => isset($clause[$key]) && !(isset($alternates[$key]) && isset($clause[$alternates[$key]])), ARRAY_FILTER_USE_KEY);
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

    /** One clause as the reference writes it: its pieces, more than one joined by AND inside parentheses. */
    private function clause(array $clause): string
    {
        $parts = $this->clauseParts($clause);
        return count($parts) > 1 ? '( ' . implode(' AND ', $parts) . ' )' : (string) ($parts[0] ?? '');
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

    /** A comparison upper-cased, = for anything the reference does not know. */
    public static function compare(string $compare): string
    {
        $compare = strtoupper($compare);
        return in_array($compare, ['=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true) ? $compare : '=';
    }
}
