<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * The SQL WP_Site_Query and WP_Network_Query build from their variables
 * (probe multisite-classes). A site query names only blog_id through its
 * table (blogs); every other column stands bare. A network query names each
 * column through its table (site). On one site both tables are unnamed, so
 * the column is written after a bare dot, as the reference writes it.
 */
final class NetworkSql
{
    /** The columns a site query orders by, as each is written; blog_id is the table's own. */
    private const SITE_ORDER = ['domain' => 'domain', 'last_updated' => 'last_updated', 'path' => 'path', 'registered' => 'registered', 'deleted' => 'deleted', 'spam' => 'spam', 'mature' => 'mature', 'archived' => 'archived', 'public' => 'public', 'network_id' => 'site_id', 'domain_length' => 'CHAR_LENGTH(domain)', 'path_length' => 'CHAR_LENGTH(path)'];

    /** The site flags as they are compared: archived as text, the rest as numbers, each followed by a space. */
    private const SITE_FLAGS = ['archived' => '%s', 'mature' => '%d', 'spam' => '%d', 'deleted' => '%d', 'public' => '%d', 'lang_id' => '%d'];

    public function __construct(private readonly object $wpdb)
    {
    }

    /**
     * A site query's conditions, in the reference's order: the ids, the
     * networks, domains, paths, the flags, the languages, then the search.
     *
     * @param array<string, mixed> $qv
     * @return list<string>
     */
    public function siteWhere(array $qv): array
    {
        $id = (string) $this->wpdb->blogs . '.blog_id';
        $where = [];
        if (!empty($qv['ID'])) {
            $where[] = $this->wpdb->prepare("{$id} = %d", $qv['ID']);
        }
        $where = [...$where, ...$this->ints($id, $qv['site__in'] ?? '', $qv['site__not_in'] ?? '')];
        if (!empty($qv['network_id'])) {
            $where[] = $this->wpdb->prepare('site_id = %d', $qv['network_id']);
        }
        $where = [...$where, ...$this->ints('site_id', $qv['network__in'] ?? '', $qv['network__not_in'] ?? '')];
        foreach (['domain', 'path'] as $column) {
            if (!empty($qv[$column])) {
                $where[] = $this->wpdb->prepare("{$column} = %s", $qv[$column]);
            }
            $where = [...$where, ...$this->strings($column, $qv["{$column}__in"] ?? '', $qv["{$column}__not_in"] ?? '')];
        }
        foreach (self::SITE_FLAGS as $flag => $format) {
            if (is_numeric($qv[$flag] ?? null)) {
                $where[] = $this->wpdb->prepare("{$flag} = {$format} ", abs((int) $qv[$flag]));
            }
        }
        $where = [...$where, ...$this->ints('lang_id', $qv['lang__in'] ?? '', $qv['lang__not_in'] ?? '')];
        if ((string) ($qv['search'] ?? '') !== '') {
            $columns = array_values(array_intersect((array) ($qv['search_columns'] ?? []), ['domain', 'path'])) ?: ['domain', 'path'];
            $where[] = $this->siteSearch((string) $qv['search'], $columns);
        }
        return $where;
    }

    /**
     * A network query's conditions: the ids, domains, paths, then the search.
     *
     * @param array<string, mixed> $qv
     * @return list<string>
     */
    public function networkWhere(array $qv): array
    {
        $table = (string) $this->wpdb->site;
        $where = $this->ints("{$table}.id", $qv['network__in'] ?? '', $qv['network__not_in'] ?? '');
        foreach (['domain', 'path'] as $column) {
            if (!empty($qv[$column])) {
                $where[] = $this->wpdb->prepare("{$table}.{$column} = %s", $qv[$column]);
            }
            $where = [...$where, ...$this->strings("{$table}.{$column}", $qv["{$column}__in"] ?? '', $qv["{$column}__not_in"] ?? '')];
        }
        if ((string) ($qv['search'] ?? '') !== '') {
            $where[] = $this->networkSearch((string) $qv['search'], ["{$table}.domain", "{$table}.path"]);
        }
        return $where;
    }

    /**
     * A site query's search across columns, each a LIKE on the term
     * anywhere, its stars wildcards.
     *
     * @param list<string> $columns
     */
    public function siteSearch(string $search, array $columns): string
    {
        return $this->likeAny(str_replace('*', '%', (string) $this->wpdb->esc_like($search)), $columns);
    }

    /**
     * A network query's search: the same LIKEs, its stars kept.
     *
     * @param list<string> $columns
     */
    public function networkSearch(string $search, array $columns): string
    {
        return $this->likeAny((string) $this->wpdb->esc_like($search), $columns);
    }

    /** @param list<string> $columns */
    private function likeAny(string $term, array $columns): string
    {
        $parts = array_map(fn (string $column): string => (string) $this->wpdb->prepare("{$column} LIKE %s", '%' . $term . '%'), $columns);
        return '(' . implode(' OR ', $parts) . ')';
    }

    /** A site query's ORDER BY, or '' (an unknown column is passed over). @param array<string, mixed> $qv */
    public function siteOrderby(array $qv): string
    {
        return self::orderby($qv, fn (string $by): ?string => $this->siteOrderColumn($by, $qv), (string) $this->wpdb->blogs . '.blog_id');
    }

    /** One column a site query orders by, as written; null for one it does not. @param array<string, mixed> $qv */
    public function siteOrderColumn(string $by, array $qv): ?string
    {
        $table = (string) $this->wpdb->blogs;
        return match ($by) {
            'id' => "{$table}.blog_id",
            'site__in' => $this->field("{$table}.blog_id", $qv['site__in'] ?? []),
            'network__in' => $this->field("{$table}.site_id", $qv['network__in'] ?? []),
            default => self::SITE_ORDER[$by] ?? null,
        };
    }

    /** A network query's ORDER BY, or ''. @param array<string, mixed> $qv */
    public function networkOrderby(array $qv): string
    {
        return self::orderby($qv, fn (string $by): ?string => $this->networkOrderColumn($by, $qv), (string) $this->wpdb->site . '.id');
    }

    /** One column a network query orders by, as written; null for one it does not. @param array<string, mixed> $qv */
    public function networkOrderColumn(string $by, array $qv): ?string
    {
        $table = (string) $this->wpdb->site;
        return match ($by) {
            'id', 'domain', 'path' => "{$table}.{$by}",
            'domain_length', 'path_length' => 'CHAR_LENGTH(' . $table . '.' . substr($by, 0, -7) . ')',
            'network__in' => $this->field("{$table}.id", $qv['network__in'] ?? []),
            default => null,
        };
    }

    /** ASC when asked for, in any case; anything else is DESC. */
    public static function direction(mixed $order): string
    {
        return is_string($order) && strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /** The LIMIT for a number and an offset; none without a number. @param array<string, mixed> $qv */
    public static function limits(array $qv): string
    {
        $number = abs((int) ($qv['number'] ?? 0));
        $offset = abs((int) ($qv['offset'] ?? 0));
        if ($number === 0) {
            return '';
        }
        return $offset > 0 ? "LIMIT {$offset},{$number}" : "LIMIT {$number}";
    }

    /**
     * The request from its clauses, joined as the reference joins them.
     *
     * @param array<string, string> $clauses fields, join, where, orderby, limits, groupby
     * @return array<string, string> the pieces kept on the query
     */
    public static function pieces(array $clauses, string $table, string $found): array
    {
        return [
            'select' => "SELECT {$found} {$clauses['fields']}",
            'from' => "FROM {$table} {$clauses['join']}",
            'where' => $clauses['where'] === '' ? '' : "WHERE {$clauses['where']}",
            'groupby' => $clauses['groupby'] === '' ? '' : "GROUP BY {$clauses['groupby']}",
            'orderby' => $clauses['orderby'] === '' ? '' : "ORDER BY {$clauses['orderby']}",
            'limits' => $clauses['limits'],
        ];
    }

    /** The ids a request finds. @return list<int> */
    public function ids(string $request): array
    {
        return array_map('intval', (array) $this->wpdb->get_col($request));
    }

    /** The count a counting request reads. */
    public function count(string $request): int
    {
        return (int) $this->wpdb->get_var($request);
    }

    /** A table's row by its id, or null when there is none. */
    public function rowById(string $table, string $column, int $id): ?object
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$table} WHERE {$column} = %d LIMIT 1", $id));
        return is_object($row) ? $row : null;
    }

    /** The rows a counted request found. */
    public function found(string $sql): int
    {
        return (int) $this->wpdb->get_var($sql);
    }

    /** @param \Closure(string): ?string $column @param array<string, mixed> $qv */
    private static function orderby(array $qv, \Closure $column, string $default): string
    {
        $orderby = $qv['orderby'] ?? '';
        $order = self::direction($qv['order'] ?? 'ASC');
        if ($orderby === 'none') {
            return '';
        }
        if ($orderby === '' || $orderby === []) {
            return "{$default} {$order}";
        }
        $pairs = is_array($orderby) ? $orderby : array_fill_keys(preg_split('/[,\s]+/', (string) $orderby, -1, PREG_SPLIT_NO_EMPTY) ?: [], $order);
        $parts = [];
        foreach ($pairs as $by => $direction) {
            $written = $column((string) $by);
            if ($written !== null) {
                // A list's own order (FIELD) takes no direction.
                $parts[] = str_ends_with((string) $by, '__in') ? $written : $written . ' ' . (is_array($orderby) ? self::direction($direction) : $order);
            }
        }
        return implode(', ', $parts);
    }

    /** FIELD() over a list of ids, in the order given. @param mixed $ids */
    private function field(string $column, mixed $ids): string
    {
        return "FIELD( {$column}, " . implode(',', array_map('intval', self::list($ids))) . ' )';
    }

    /** @return list<string> */
    private function ints(string $column, mixed $in, mixed $notIn): array
    {
        $out = [];
        foreach (['IN' => $in, 'NOT IN' => $notIn] as $operator => $ids) {
            if (!empty($ids)) {
                $out[] = "{$column} {$operator} ( " . implode(',', array_map('intval', self::list($ids))) . ' )';
            }
        }
        return $out;
    }

    /** @return list<string> */
    private function strings(string $column, mixed $in, mixed $notIn): array
    {
        $out = [];
        foreach (['IN' => $in, 'NOT IN' => $notIn] as $operator => $values) {
            if (!empty($values)) {
                $out[] = "{$column} {$operator} ( '" . implode("', '", array_map(fn ($v): string => (string) $this->wpdb->_escape((string) $v), self::list($values))) . "' )";
            }
        }
        return $out;
    }

    /** A list given as an array or a comma-separated string. @return list<mixed> */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : (preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
