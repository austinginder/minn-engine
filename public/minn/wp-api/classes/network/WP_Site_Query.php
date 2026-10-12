<?php

use Minn\Query\NetworkSql;

/**
 * The site query (probe multisite-classes): its variables (parse_site_query,
 * pre_get_sites), an answer sites_pre_query may give, the clauses through
 * sites_clauses written as its request by Minn\Query\NetworkSql, and the ids
 * found kept in the object cache (site-queries) until a site changes, so the
 * same query asked again reads no SQL. On one site the network's tables are
 * unnamed and the meta query is not kept.
 */
#[AllowDynamicProperties]
class WP_Site_Query
{
    public $request;
    protected $sql_clauses = ['select' => '', 'from' => '', 'where' => [], 'groupby' => '', 'orderby' => '', 'limits' => ''];
    public $meta_query = false;
    protected $meta_query_clauses;
    public $date_query = false;
    public $query_vars;
    public $query_var_defaults;
    public $sites;
    public $found_sites = 0;
    public $max_num_pages = 0;

    public function __construct($query = '')
    {
        $this->query_var_defaults = ['fields' => '', 'ID' => '', 'site__in' => '', 'site__not_in' => '', 'number' => 100, 'offset' => '', 'no_found_rows' => true, 'orderby' => 'id', 'order' => 'ASC', 'network_id' => 0, 'network__in' => '', 'network__not_in' => '', 'domain' => '', 'domain__in' => '', 'domain__not_in' => '', 'path' => '', 'path__in' => '', 'path__not_in' => '', 'public' => null, 'archived' => null, 'mature' => null, 'spam' => null, 'deleted' => null, 'lang_id' => null, 'lang__in' => '', 'lang__not_in' => '', 'search' => '', 'search_columns' => [], 'count' => false, 'date_query' => null, 'update_site_cache' => true, 'update_site_meta_cache' => true, 'meta_query' => '', 'meta_key' => '', 'meta_value' => '', 'meta_type' => '', 'meta_compare' => ''];
        if (!empty($query)) {
            $this->query($query);
        }
    }

    public function parse_query($query = '')
    {
        if (empty($query)) {
            $query = $this->query_vars;
        }
        $this->query_vars = wp_parse_args($query, $this->query_var_defaults);
        do_action_ref_array('parse_site_query', [&$this]);
    }

    public function query($query)
    {
        $this->query_vars = wp_parse_args($query);
        return $this->get_sites();
    }

    /** The sites, their ids, or their count for the variables. */
    public function get_sites()
    {
        $this->parse_query();
        do_action_ref_array('pre_get_sites', [&$this]);
        $answer = apply_filters_ref_array('sites_pre_query', [null, &$this]);
        if ($answer !== null) {
            return $this->sites = $answer;
        }
        $ids = $this->minn_cached_ids();
        if (!empty($this->query_vars['count'])) {
            return (int) $ids;
        }
        $ids = array_map('intval', (array) $ids);
        if (!empty($this->query_vars['update_site_meta_cache']) && function_exists('wp_lazyload_site_meta')) {
            wp_lazyload_site_meta($ids);
        }
        return $this->sites = $this->query_vars['fields'] === 'ids' ? $ids : $this->minn_sites($ids);
    }

    /** The ids (or count) found for these variables, read from the cache when the same query ran since a site last changed. */
    private function minn_cached_ids()
    {
        $args = array_diff_key(wp_array_slice_assoc($this->query_vars, array_keys($this->query_var_defaults)), array_flip(['fields', 'update_site_cache', 'update_site_meta_cache']));
        $key = 'get_sites:' . md5(serialize($args)) . ':' . wp_cache_get_last_changed('sites');
        $cached = wp_cache_get($key, 'site-queries');
        if (is_array($cached)) {
            $this->found_sites = (int) $cached['found_sites'];
            $this->max_num_pages = $this->query_vars['number'] ? (int) ceil($this->found_sites / (int) $this->query_vars['number']) : 0;
            return $cached['site_ids'];
        }
        $ids = $this->get_site_ids();
        if ($ids) {
            $this->set_found_sites();
        }
        wp_cache_add($key, ['site_ids' => $ids, 'found_sites' => $this->found_sites], 'site-queries');
        return $ids;
    }

    /** @param list<int> $ids */
    private function minn_sites(array $ids)
    {
        if (!empty($this->query_vars['update_site_cache']) && function_exists('_prime_site_caches')) {
            _prime_site_caches($ids, !empty($this->query_vars['update_site_meta_cache']));
        }
        $sites = array_values(array_filter(array_map(static fn (int $id) => function_exists('get_site') ? get_site($id) : WP_Site::get_instance($id), $ids)));
        return (array) apply_filters_ref_array('the_sites', [$sites, &$this]);
    }

    protected function get_site_ids()
    {
        global $wpdb;
        $sql = new NetworkSql($wpdb);
        $qv = $this->query_vars;
        $where = $sql->siteWhere($qv, fn (array $columns): array => array_values((array) apply_filters('site_search_columns', $columns, $qv['search'], $this)));
        if (!empty($qv['date_query']) && is_array($qv['date_query'])) {
            $this->date_query = new WP_Date_Query($qv['date_query']);
            $where[] = (string) preg_replace('/^\s*AND\s*/', '', (string) $this->date_query->get_sql());
        }
        $counted = !empty($qv['count']);
        $clauses = (array) apply_filters_ref_array('sites_clauses', [['fields' => $counted ? 'COUNT(*)' : $wpdb->blogs . '.blog_id', 'join' => '', 'where' => implode(' AND ', $where), 'orderby' => $sql->siteOrderby($qv), 'limits' => NetworkSql::limits($qv), 'groupby' => ''], &$this]);
        $clauses = array_map('strval', $clauses + array_fill_keys(['fields', 'join', 'where', 'orderby', 'limits', 'groupby'], ''));
        $this->sql_clauses = NetworkSql::pieces($clauses, (string) $wpdb->blogs, !$counted && empty($qv['no_found_rows']) ? 'SQL_CALC_FOUND_ROWS' : '');
        $this->request = implode("\n\t\t\t ", $this->sql_clauses);
        return $counted ? $sql->count($this->request) : $sql->ids($this->request);
    }

    private function set_found_sites()
    {
        global $wpdb;
        if ($this->query_vars['number'] && empty($this->query_vars['no_found_rows'])) {
            $this->found_sites = (new NetworkSql($wpdb))->found((string) apply_filters('found_sites_query', 'SELECT FOUND_ROWS()', $this));
            $this->max_num_pages = (int) ceil($this->found_sites / (int) $this->query_vars['number']);
        }
    }

    protected function get_search_sql($search, $columns)
    {
        global $wpdb;
        return (new NetworkSql($wpdb))->siteSearch((string) $search, array_values((array) $columns));
    }

    protected function parse_orderby($orderby)
    {
        global $wpdb;
        return (new NetworkSql($wpdb))->siteOrderColumn((string) $orderby, (array) $this->query_vars) ?? false;
    }

    protected function parse_order($order)
    {
        return NetworkSql::direction($order);
    }
}
