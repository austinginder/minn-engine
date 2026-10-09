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

    /** The old fields, results and total_users, read as they were; any other undeclared name is deprecated and null (probe query-compat). */
    public function __get($name)
    {
        if (in_array($name, ['results', 'total_users'], true)) {
            return $this->{$name};
        }
        wp_trigger_error(__METHOD__, "The property `{$name}` is not declared. Getting a dynamic property is deprecated since version 6.4.0! Instead, declare the property on the class.", E_USER_DEPRECATED);
        return null;
    }

    public function __set($name, $value)
    {
        if (in_array($name, ['results', 'total_users'], true)) {
            $this->{$name} = $value;
            return;
        }
        wp_trigger_error(__METHOD__, "The property `{$name}` is not declared. Setting a dynamic property is deprecated since version 6.4.0! Instead, declare the property on the class.", E_USER_DEPRECATED);
    }

    public function __isset($name)
    {
        if (in_array($name, ['results', 'total_users'], true)) {
            return isset($this->{$name});
        }
        wp_trigger_error(__METHOD__, "The property `{$name}` is not declared. Checking `isset()` on a dynamic property is deprecated since version 6.4.0! Instead, declare the property on the class.", E_USER_DEPRECATED);
        return false;
    }

    public function __unset($name)
    {
        if (in_array($name, ['results', 'total_users'], true)) {
            unset($this->{$name});
            return;
        }
        wp_trigger_error(__METHOD__, "A property `{$name}` is not declared. Unsetting a dynamic property is deprecated since version 6.4.0! Instead, declare the property on the class.", E_USER_DEPRECATED);
    }

    /** The old protected get_search_sql, callable as it was; anything else answers false. */
    public function __call($name, $arguments)
    {
        return $name === 'get_search_sql' ? $this->get_search_sql(...$arguments) : false;
    }

    protected function get_search_sql($search, $columns, $wild = false)
    {
        return Minn\Runtime\UserQueryRunner::searchSql((string) $search, (array) $columns, in_array($wild, ['leading', 'trailing', 'both'], true) ? $wild : false);
    }

    /** The cache key for a request: its SQL alone decides it. */
    protected function generate_cache_key(array $deprecated, $sql)
    {
        return 'get_users:' . md5((string) $sql);
    }

    /** The last-changed stamps a query's cache depends on: the users', and the posts' when it orders by post count or asks for published posts. */
    protected function get_cache_last_changed(array $args)
    {
        $posts = !empty($args['has_published_posts']) || in_array('post_count', (array) ($args['orderby'] ?? []), true);
        return [wp_cache_get_last_changed('users'), ...($posts ? [wp_cache_get_last_changed('posts')] : [])];
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
