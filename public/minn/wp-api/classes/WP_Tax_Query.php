<?php

use Minn\Query\TaxSql;
use Minn\Runtime\Runtime;

/** A taxonomy query: the clauses as given, the SQL fragments per query and clause from Minn\Query\TaxSql (probes query-clauses, query-clause-internals). */
#[AllowDynamicProperties]
class WP_Tax_Query
{
    public $queries = [];
    public $relation;
    public $queried_terms = [];
    public $primary_table;
    public $primary_id_column;
    protected $table_aliases = [];
    /** @var TaxSql|null the builder whose aliases this query has handed out so far */
    private $builder;

    public function __construct($tax_query)
    {
        $this->relation = isset($tax_query['relation']) && strtoupper((string) $tax_query['relation']) === 'OR' ? 'OR' : 'AND';
        $this->queries = $this->sanitize_query((array) $tax_query);
        $this->queried_terms = TaxSql::queried($this->queries);
    }

    public function sanitize_query($queries)
    {
        return TaxSql::sanitize((array) $queries);
    }

    public function get_sql($primary_table, $primary_id_column)
    {
        $this->primary_table = $primary_table;
        $this->primary_id_column = $primary_id_column;
        $this->builder = null;
        return $this->builder()->build($this->queries);
    }

    public function sanitize_relation($relation)
    {
        return strtoupper((string) $relation) === 'OR' ? 'OR' : 'AND';
    }

    protected static function is_first_order_clause($query)
    {
        return TaxSql::isFirstOrder($query);
    }

    protected function get_sql_clauses()
    {
        $queries = $this->queries;
        return $this->builder()->sqlClauses($queries);
    }

    protected function get_sql_for_query(&$query, $depth = 0)
    {
        return $this->builder()->querySql($query, (int) $depth);
    }

    public function get_sql_for_clause(&$clause, $parent_query)
    {
        return $this->builder()->clauseSql($clause, (array) $parent_query);
    }

    protected function find_compatible_table_alias($clause, $parent_query)
    {
        return $this->builder()->compatibleAlias((array) $clause, (array) $parent_query);
    }

    /** The builder for the tables this query reads, made once per get_sql. */
    private function builder(): TaxSql
    {
        $db = Runtime::current()->db;
        return $this->builder ??= new TaxSql($db->table('term_relationships'), $db->table('term_taxonomy'), (string) $this->primary_table, (string) $this->primary_id_column, static fn (array $clause, string $operator): ?array => Minn\Runtime\TaxTerms::ids($clause, $operator));
    }

    public function transform_query(&$query, $resulting_field)
    {
        Minn\Runtime\TaxTerms::transform($query, $resulting_field);
    }
}
