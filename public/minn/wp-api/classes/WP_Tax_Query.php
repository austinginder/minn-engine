<?php

use Minn\Query\TaxSql;
use Minn\Runtime\Runtime;

/** A taxonomy query: the clauses as given, the SQL fragments from Minn\Query\TaxSql. */
class WP_Tax_Query
{
    public $queries = [];
    public $relation;
    public $queried_terms = [];
    public $primary_table;
    public $primary_id_column;
    protected $table_aliases = [];

    public function __construct($tax_query)
    {
        $this->relation = isset($tax_query['relation']) && strtoupper((string) $tax_query['relation']) === 'OR' ? 'OR' : 'AND';
        $this->queries = $this->sanitize_query((array) $tax_query);
    }

    public function sanitize_query($queries)
    {
        return TaxSql::sanitize((array) $queries);
    }

    public function get_sql($primary_table, $primary_id_column)
    {
        $this->primary_table = $primary_table;
        $this->primary_id_column = $primary_id_column;
        $db = Runtime::current()->db;
        $builder = new TaxSql($db->table('term_relationships'), $db->table('term_taxonomy'), (string) $primary_table, (string) $primary_id_column, static fn (string $taxonomy, string $field, array $terms, bool $children): array => _minn_term_taxonomy_ids($taxonomy, $field, $terms, $children));
        $sql = $builder->build($this->queries);
        $this->queried_terms = $builder->queriedTerms();
        return $sql;
    }

    public function transform_query(&$query, $resulting_field)
    {
        if (empty($query['terms']) || $query['field'] === $resulting_field) {
            return;
        }
        $ids = _minn_term_taxonomy_ids((string) $query['taxonomy'], (string) $query['field'], (array) $query['terms'], false);
        if ($resulting_field === 'term_taxonomy_id') {
            $query['terms'] = $ids;
            $query['field'] = 'term_taxonomy_id';
            return;
        }
        $terms = [];
        foreach ($ids as $id) {
            $term = get_term_by('term_taxonomy_id', $id, (string) $query['taxonomy']);
            if ($term) {
                $terms[] = $term->{$resulting_field};
            }
        }
        $query['terms'] = $terms;
        $query['field'] = $resulting_field;
    }
}
