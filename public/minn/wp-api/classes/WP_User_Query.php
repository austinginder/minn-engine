<?php

use Minn\Runtime\UserOrder;
use Minn\Runtime\UserQueryRunner;

/**
 * The user query plugin code runs, as the reference runs it:
 * Minn\Runtime\UserQueryRunner builds the pieces, fires pre_get_users and
 * pre_user_query, writes the request and shapes the results.
 */
#[AllowDynamicProperties]
class WP_User_Query
{
    public $query_vars = [];
    public $results = [];
    public $total_users = 0;
    public $meta_query = false;
    public $request;
    public $query_fields;
    public $query_from;
    public $query_where;
    public $query_orderby;
    public $query_limit;

    public function __construct($query = null)
    {
        if (!empty($query)) {
            $this->prepare_query($query);
            $this->query();
        }
    }

    public static function fill_query_vars($args)
    {
        $defaults = ['blog_id' => get_current_blog_id(), 'role' => '', 'role__in' => [], 'role__not_in' => [], 'capability' => '', 'capability__in' => [], 'capability__not_in' => [], 'meta_key' => '', 'meta_value' => '', 'meta_compare' => '', 'include' => [], 'exclude' => [], 'search' => '', 'search_columns' => [], 'orderby' => 'login', 'order' => 'ASC', 'offset' => '', 'number' => '', 'paged' => 1, 'count_total' => true, 'fields' => 'all', 'who' => '', 'has_published_posts' => null, 'nicename' => '', 'nicename__in' => [], 'nicename__not_in' => [], 'login' => '', 'login__in' => [], 'login__not_in' => [], 'cache_results' => true];
        return wp_parse_args($args, $defaults);
    }

    public function prepare_query($query = [])
    {
        if (empty($this->query_vars) || !empty($query)) {
            $this->query_limit = null;
            $this->query_vars = self::fill_query_vars($query);
        }
        global $wpdb;
        (new UserQueryRunner($wpdb))->prepare($this);
    }

    public function query()
    {
        global $wpdb;
        (new UserQueryRunner($wpdb))->query($this);
    }

    public function get($query_var)
    {
        return $this->query_vars[$query_var] ?? null;
    }

    public function set($query_var, $value)
    {
        $this->query_vars[$query_var] = $value;
    }

    public function get_results()
    {
        return $this->results;
    }

    public function get_total()
    {
        return $this->total_users;
    }

    protected function parse_orderby($orderby)
    {
        global $wpdb;
        return UserOrder::clause($this, (string) $orderby, $wpdb);
    }

    protected function parse_order($order)
    {
        return UserOrder::direction($order);
    }
}
