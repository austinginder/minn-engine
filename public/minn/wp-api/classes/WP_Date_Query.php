<?php

use Minn\Query\DateSql;
use Minn\Runtime\Runtime;

/** A date query: the clauses as given, the WHERE fragment from Minn\Query\DateSql. */
class WP_Date_Query
{
    public $queries = [];
    public $relation = 'AND';
    public $column = 'post_date';
    public $compare = '=';
    public $time_keys = ['after', 'before', 'year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second'];

    public function __construct($date_query, $default_column = 'post_date')
    {
        if (!is_array($date_query)) {
            return;
        }
        $this->relation = isset($date_query['relation']) && strtoupper((string) $date_query['relation']) === 'OR' ? 'OR' : 'AND';
        $this->column = isset($date_query['column']) ? esc_sql((string) $date_query['column']) : (string) $default_column;
        $this->column = $this->validate_column($this->column);
        $this->compare = $this->get_compare($date_query);
        $this->queries = $this->sanitize_query($date_query);
    }

    private function builder(): DateSql
    {
        $db = Runtime::current()->db;
        return new DateSql(['posts' => $db->table('posts'), 'comments' => $db->table('comments'), 'users' => $db->table('users'), 'blogs' => $db->table('blogs')], 'post_date');
    }

    public function sanitize_query($queries, $parent_query = null)
    {
        $defaults = ['column' => $this->column, 'compare' => $this->compare, 'relation' => $this->relation];
        return $this->builder()->sanitize((array) $queries, $defaults);
    }

    public function is_first_order_clause($query)
    {
        return DateSql::isFirstOrder((array) $query);
    }

    public function get_compare($query)
    {
        $compare = strtoupper((string) ($query['compare'] ?? $this->compare));
        return in_array($compare, ['=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], true) ? $compare : $this->compare;
    }

    public function validate_column($column)
    {
        return $this->builder()->validateColumn((string) $column);
    }

    public function get_sql()
    {
        $where = $this->builder()->build($this->queries);
        return apply_filters('get_date_sql', $where, $this);
    }

    public function build_mysql_datetime($datetime, $default_to_max = false)
    {
        return DateSql::datetime($datetime, (bool) $default_to_max);
    }
}
