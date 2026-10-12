<?php

use Minn\Query\MetaSql;
use Minn\Runtime\Runtime;

/** A meta query: the clauses as given, the SQL fragments per query and clause from Minn\Query\MetaSql (probes query-clauses, query-clause-internals). */
#[AllowDynamicProperties]
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
    /** @var MetaSql|null the builder whose aliases this query has handed out so far */
    private $builder;

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
        $this->__construct(\Minn\Runtime\Meta::clausesFromQueryVars((array) $qv));
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
        $this->builder = null;
        $sql = $this->builder()->build($this->queries);
        $this->remember();
        $this->has_or_relation = MetaSql::hasOr($this->queries);
        return apply_filters_ref_array('get_meta_sql', [$sql, $this->queries, $type, $primary_table, $primary_id_column, $context]);
    }

    public function get_clauses()
    {
        return $this->clauses;
    }

    protected function get_sql_clauses()
    {
        $queries = $this->queries;
        $sql = $this->builder()->sqlClauses($queries);
        $this->remember();
        return $sql;
    }

    protected function get_sql_for_query(&$query, $depth = 0)
    {
        $sql = $this->builder()->querySql($query, (int) $depth);
        $this->remember();
        return $sql;
    }

    public function get_sql_for_clause(&$clause, $parent_query, $clause_key = '')
    {
        $sql = $this->builder()->clauseSql($clause, (array) $parent_query, (string) $clause_key);
        $this->remember();
        return $sql;
    }

    protected function find_compatible_table_alias($clause, $parent_query)
    {
        return apply_filters('meta_query_find_compatible_table_alias', $this->builder()->compatibleAlias((array) $clause, (array) $parent_query), $clause, $parent_query, $this);
    }

    /** The builder for the tables this query reads, made once. */
    private function builder(): MetaSql
    {
        return $this->builder ??= (new MetaSql((string) $this->meta_table, (string) $this->meta_id_column, (string) $this->primary_table, (string) $this->primary_id_column))
            ->filterAliasesWith(fn ($alias, array $clause, array $parent) => apply_filters('meta_query_find_compatible_table_alias', $alias, $clause, $parent, $this));
    }

    /** The clauses and aliases the builder has settled so far. */
    private function remember(): void
    {
        $this->clauses = $this->builder()->clauses();
        $this->table_aliases = array_values(array_unique(array_map(static fn (array $c) => $c['alias'], $this->clauses)));
    }

    public function has_or_relation()
    {
        return MetaSql::hasOr($this->queries);
    }
}
