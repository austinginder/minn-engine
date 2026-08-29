<?php

/** Term queries as an object; the work is get_terms(), so both agree by construction. */
class WP_Term_Query
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
        $query = wp_parse_args((array) $query, $this->query_var_defaults);
        $query['number'] = absint($query['number']);
        $query['offset'] = absint($query['offset']);
        if ($query['taxonomy'] !== null) {
            $query['taxonomy'] = array_values(array_map('strval', (array) $query['taxonomy']));
        }
        if ($query['object_ids'] !== null) {
            $query['object_ids'] = array_map('intval', (array) $query['object_ids']);
        }
        foreach (['include', 'exclude', 'exclude_tree', 'term_taxonomy_id'] as $key) {
            $query[$key] = $query[$key] === '' || $query[$key] === null ? [] : wp_parse_id_list($query[$key]);
        }
        if (is_array($query['term_taxonomy_id']) && $query['term_taxonomy_id'] === []) {
            $query['term_taxonomy_id'] = '';
        }
        foreach (['name', 'slug'] as $key) {
            $query[$key] = $query[$key] === '' || $query[$key] === null ? [] : array_values(array_map('strval', (array) $query[$key]));
        }
        if (is_string($query['hide_empty'])) {
            $query['hide_empty'] = $query['hide_empty'] === '1' || $query['hide_empty'] === 'true';
        }
        $query['hide_empty'] = (bool) $query['hide_empty'];
        $query['hierarchical'] = (bool) $query['hierarchical'];
        $query['childless'] = (bool) $query['childless'];
        $query['pad_counts'] = (bool) $query['pad_counts'];
        $query['cache_results'] = (bool) $query['cache_results'];
        $query['update_term_meta_cache'] = (bool) $query['update_term_meta_cache'];
        $query['child_of'] = (int) $query['child_of'];
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
        $this->parse_query($this->query_vars);
        $args = &$this->query_vars;
        $taxonomies = $args['taxonomy'];
        $where = $taxonomies === null ? '1=1' : "tt.taxonomy IN ('" . implode("', '", array_map('esc_sql', $taxonomies)) . "')";
        $this->request = "SELECT t.*, tt.* FROM {$GLOBALS['wpdb']->terms} AS t INNER JOIN {$GLOBALS['wpdb']->term_taxonomy} AS tt ON t.term_id = tt.term_id WHERE {$where}";
        $this->request = apply_filters('terms_pre_query', null, $this) === null ? $this->request : $this->request;
        $result = get_terms($args);
        if (is_wp_error($result)) {
            $this->terms = [];
            return $this->terms;
        }
        $this->terms = $result;
        return $this->terms;
    }
}
