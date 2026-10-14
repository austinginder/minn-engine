<?php

use Minn\Runtime\TermOrder;
use Minn\Runtime\TermQueryRunner;
use Minn\Runtime\TermQueryTree;

/** Term queries as the reference runs them: Minn\Runtime\TermQueryRunner writes the clauses, runs the filters and the request. */
#[AllowDynamicProperties]
class WP_Term_Query implements \Minn\Objects\TermsQuery
{
    public $request;
    public $meta_query = false;
    protected $meta_query_clauses;
    protected $sql_clauses = ['select' => '', 'from' => '', 'where' => [], 'orderby' => '', 'limits' => ''];
    public $query_vars;
    public $query_var_defaults;
    public $terms;

    public function __construct($query = '')
    {
        $this->query_var_defaults = ['taxonomy' => null, 'object_ids' => null, 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => true, 'include' => [], 'exclude' => [], 'exclude_tree' => [], 'number' => '', 'offset' => '', 'fields' => 'all', 'name' => '', 'slug' => '', 'term_taxonomy_id' => '', 'hierarchical' => true, 'search' => '', 'name__like' => '', 'description__like' => '', 'pad_counts' => false, 'get' => '', 'child_of' => 0, 'parent' => '', 'childless' => false, 'cache_domain' => 'core', 'cache_results' => true, 'update_term_meta_cache' => true, 'meta_query' => '', 'meta_key' => '', 'meta_value' => '', 'meta_type' => '', 'meta_compare' => ''];
        if (!empty($query)) {
            $this->query($query);
        }
    }

    public function parse_query($query = '')
    {
        if (empty($query)) {
            $query = $this->query_vars;
        }
        $taxonomies = isset($query['taxonomy']) ? (array) $query['taxonomy'] : null;
        $this->query_var_defaults = apply_filters('get_terms_defaults', $this->query_var_defaults, $taxonomies);
        $query = wp_parse_args($query, $this->query_var_defaults);
        $query['number'] = absint($query['number']);
        $query['offset'] = absint($query['offset']);
        if ((int) $query['parent'] > 0) {
            $query['child_of'] = false;
        }
        if ($query['get'] === 'all') {
            $query = array_replace($query, ['childless' => false, 'child_of' => 0, 'hide_empty' => 0, 'hierarchical' => false, 'pad_counts' => false]);
        }
        $query['taxonomy'] = $taxonomies;
        $this->query_vars = $query;
        do_action_ref_array('parse_term_query', [&$this]);
    }

    public function query($query)
    {
        $this->query_vars = wp_parse_args($query);
        return $this->get_terms();
    }

    public function get_terms()
    {
        global $wpdb;
        return (new TermQueryRunner())->run($this, $wpdb);
    }

    protected function parse_orderby($orderby_raw)
    {
        return TermOrder::clause($this, (string) $orderby_raw);
    }

    protected function parse_orderby_meta($orderby_raw)
    {
        return Minn\Runtime\TermOrder::meta($this, strtolower((string) $orderby_raw));
    }

    /**
     * The cache key for a request (probe query-compat): the query vars it
     * knows, in their own order, with the cache switches left out and every
     * fields value but count and all_with_object_id read as all, then the SQL.
     */
    protected function generate_cache_key(array $args, $sql)
    {
        $cache_args = array_intersect_key(array_replace(array_fill_keys(array_keys((array) $this->query_var_defaults), null), $args), $args);
        unset($cache_args['update_term_meta_cache'], $cache_args['cache_results']);
        if (array_key_exists('fields', $cache_args) && !in_array($cache_args['fields'], ['count', 'all_with_object_id'], true)) {
            $cache_args['fields'] = 'all';
        }
        return 'get_terms:' . md5(serialize($cache_args) . $sql);
    }

    protected function parse_order($order)
    {
        return TermOrder::direction($order);
    }

    protected function get_search_sql($search)
    {
        $like = '%' . $GLOBALS['wpdb']->esc_like((string) $search) . '%';
        return $GLOBALS['wpdb']->prepare('((t.name LIKE %s) OR (t.slug LIKE %s))', $like, $like);
    }

    protected function populate_terms($terms)
    {
        return TermQueryTree::populate(is_array($terms) ? $terms : []);
    }

    protected function format_terms($term_objects, $_fields)
    {
        return TermQueryTree::format((array) $term_objects, (string) $_fields);
    }
}
