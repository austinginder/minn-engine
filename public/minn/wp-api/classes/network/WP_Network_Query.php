<?php

use Minn\Query\NetworkSql;

/**
 * The network query (probe multisite-classes): its variables
 * (parse_network_query, pre_get_networks), an answer networks_pre_query may
 * give, the clauses through networks_clauses written as its request by
 * Minn\Query\NetworkSql, and the ids found kept in the object cache
 * (network-queries) until a network changes.
 */
#[AllowDynamicProperties]
class WP_Network_Query
{
    public $request;
    protected $sql_clauses = ['select' => '', 'from' => '', 'where' => [], 'groupby' => '', 'orderby' => '', 'limits' => ''];
    public $query_vars;
    public $query_var_defaults;
    public $networks;
    public $found_networks = 0;
    public $max_num_pages = 0;

    public function __construct($query = '')
    {
        $this->query_var_defaults = ['network__in' => '', 'network__not_in' => '', 'count' => false, 'fields' => '', 'number' => '', 'offset' => '', 'no_found_rows' => true, 'orderby' => 'id', 'order' => 'ASC', 'domain' => '', 'domain__in' => '', 'domain__not_in' => '', 'path' => '', 'path__in' => '', 'path__not_in' => '', 'search' => '', 'update_network_cache' => true];
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
        do_action_ref_array('parse_network_query', [&$this]);
    }

    public function query($query)
    {
        $this->query_vars = wp_parse_args($query);
        return $this->get_networks();
    }

    /** The networks, their ids, or their count for the variables. */
    public function get_networks()
    {
        $this->parse_query();
        do_action_ref_array('pre_get_networks', [&$this]);
        $answer = apply_filters_ref_array('networks_pre_query', [null, &$this]);
        if ($answer !== null) {
            return $this->networks = $answer;
        }
        $ids = $this->minn_cached_ids();
        if (!empty($this->query_vars['count'])) {
            return (int) $ids;
        }
        $ids = array_map('intval', (array) $ids);
        return $this->networks = $this->query_vars['fields'] === 'ids' ? $ids : $this->minn_networks($ids);
    }

    /** The ids (or count) found for these variables, read from the cache when the same query ran since a network last changed. */
    private function minn_cached_ids()
    {
        $args = array_diff_key(wp_array_slice_assoc($this->query_vars, array_keys($this->query_var_defaults)), array_flip(['fields', 'update_network_cache']));
        $key = 'get_network_ids:' . md5(serialize($args)) . ':' . wp_cache_get_last_changed('networks');
        $cached = wp_cache_get($key, 'network-queries');
        if (is_array($cached)) {
            $this->found_networks = (int) $cached['found_networks'];
            $this->max_num_pages = $this->query_vars['number'] ? (int) ceil($this->found_networks / (int) $this->query_vars['number']) : 0;
            return $cached['network_ids'];
        }
        $ids = $this->get_network_ids();
        if ($ids) {
            $this->set_found_networks();
        }
        wp_cache_add($key, ['network_ids' => $ids, 'found_networks' => $this->found_networks], 'network-queries');
        return $ids;
    }

    /** @param list<int> $ids */
    private function minn_networks(array $ids)
    {
        if (!empty($this->query_vars['update_network_cache']) && function_exists('_prime_network_caches')) {
            _prime_network_caches($ids);
        }
        $networks = array_values(array_filter(array_map(static fn (int $id) => function_exists('get_network') ? get_network($id) : WP_Network::get_instance($id), $ids)));
        return (array) apply_filters_ref_array('the_networks', [$networks, &$this]);
    }

    protected function get_network_ids()
    {
        global $wpdb;
        $sql = new NetworkSql($wpdb);
        $qv = $this->query_vars;
        $counted = !empty($qv['count']);
        $clauses = (array) apply_filters_ref_array('networks_clauses', [['fields' => $counted ? 'COUNT(*)' : $wpdb->site . '.id', 'join' => '', 'where' => implode(' AND ', $sql->networkWhere($qv)), 'orderby' => $sql->networkOrderby($qv), 'limits' => NetworkSql::limits($qv), 'groupby' => ''], &$this]);
        $clauses = array_map('strval', $clauses + array_fill_keys(['fields', 'join', 'where', 'orderby', 'limits', 'groupby'], ''));
        $this->sql_clauses = NetworkSql::pieces($clauses, (string) $wpdb->site, !$counted && empty($qv['no_found_rows']) ? 'SQL_CALC_FOUND_ROWS' : '');
        $this->request = implode("\n\t\t\t ", $this->sql_clauses);
        return $counted ? $sql->count($this->request) : $sql->ids($this->request);
    }

    private function set_found_networks()
    {
        global $wpdb;
        if ($this->query_vars['number'] && empty($this->query_vars['no_found_rows'])) {
            $this->found_networks = (new NetworkSql($wpdb))->found((string) apply_filters('found_networks_query', 'SELECT FOUND_ROWS()', $this));
            $this->max_num_pages = (int) ceil($this->found_networks / (int) $this->query_vars['number']);
        }
    }

    protected function get_search_sql($search, $columns)
    {
        global $wpdb;
        return (new NetworkSql($wpdb))->networkSearch((string) $search, array_values((array) $columns));
    }

    protected function parse_orderby($orderby)
    {
        global $wpdb;
        return (new NetworkSql($wpdb))->networkOrderColumn((string) $orderby, (array) $this->query_vars) ?? false;
    }

    protected function parse_order($order)
    {
        return NetworkSql::direction($order);
    }
}
