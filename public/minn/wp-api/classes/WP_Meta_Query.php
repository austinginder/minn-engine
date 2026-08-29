<?php

use Minn\Query\MetaSql;
use Minn\Runtime\Runtime;

/** A meta query: the clauses as given, the SQL fragments from Minn\Query\MetaSql. */
class WP_Meta_Query
{
    public $queries = [];
    public $relation;
    public $meta_table;
    public $meta_id_column;
    public $primary_table;
    public $primary_id_column;
    protected $table_aliases = [];
    protected $clauses = [];
    protected $has_or_relation = false;

    public function __construct($meta_query = false)
    {
        if (!$meta_query) {
            return;
        }
        $this->relation = isset($meta_query['relation']) && strtoupper((string) $meta_query['relation']) === 'OR' ? 'OR' : 'AND';
        $this->queries = $this->sanitize_query($meta_query);
    }

    public function sanitize_query($queries)
    {
        return MetaSql::sanitize((array) $queries);
    }

    protected function is_first_order_clause($query)
    {
        return MetaSql::isFirstOrder((array) $query);
    }

    /** The legacy meta_key / meta_value / meta_compare / meta_type vars as one clause. */
    public function parse_query_vars($qv)
    {
        $clause = [];
        foreach (['key' => 'meta_key', 'compare' => 'meta_compare', 'type' => 'meta_type', 'compare_key' => 'meta_compare_key', 'type_key' => 'meta_type_key'] as $to => $from) {
            if (isset($qv[$from]) && $qv[$from] !== '') {
                $clause[$to] = $qv[$from];
            }
        }
        if (isset($qv['meta_value']) && (!is_array($qv['meta_value']) || $qv['meta_value'] !== [])) {
            $clause['value'] = $qv['meta_value'];
        }
        $queries = [];
        if (isset($clause['key']) || isset($clause['value'])) {
            $queries[] = $clause;
        }
        if (!empty($qv['meta_query']) && is_array($qv['meta_query'])) {
            $queries = array_merge($queries, $qv['meta_query']);
        }
        $this->__construct($queries);
    }

    public function get_cast_for_type($type = '')
    {
        return MetaSql::cast((string) $type);
    }

    public function get_sql($type, $primary_table, $primary_id_column, $context = null)
    {
        $tables = ['post' => ['postmeta', 'post_id'], 'comment' => ['commentmeta', 'comment_id'], 'term' => ['termmeta', 'term_id'], 'user' => ['usermeta', 'user_id']];
        if (!isset($tables[$type])) {
            return false;
        }
        [$table, $column] = $tables[$type];
        $this->meta_table = Runtime::current()->db->table($table);
        $this->meta_id_column = $column;
        $this->primary_table = $primary_table;
        $this->primary_id_column = $primary_id_column;
        $builder = new MetaSql($this->meta_table, $column, (string) $primary_table, (string) $primary_id_column);
        $sql = $builder->build($this->queries);
        $this->clauses = $builder->clauses();
        $this->table_aliases = array_values(array_unique(array_map(static fn (array $c) => $c['alias'], $this->clauses)));
        $this->has_or_relation = MetaSql::hasOr($this->queries);
        return apply_filters_ref_array('get_meta_sql', [$sql, $this->queries, $type, $primary_table, $primary_id_column, $context]);
    }

    public function get_clauses()
    {
        return $this->clauses;
    }

    public function has_or_relation()
    {
        return MetaSql::hasOr($this->queries);
    }
}
