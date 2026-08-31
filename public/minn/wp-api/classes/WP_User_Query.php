<?php
/**
 * The user query plugin code runs. Querying lives in Minn\Runtime\UserQuery;
 * this shapes results the probed way: WP_User objects for 'all', STRING ids
 * for 'ID', stdClass records holding just the named columns for an array.
 */
class WP_User_Query
{
    public $query_vars = [];
    public $results = [];
    public $total_users = 0;

    public function __construct($query = null)
    {
        if ($query !== null) {
            $this->prepare_query((array) $query);
            $this->query();
        }
    }

    public function prepare_query($query = [])
    {
        $this->query_vars = wp_parse_args($query, ['fields' => 'all', 'role' => '', 'number' => 0, 'offset' => 0, 'orderby' => 'user_login', 'order' => 'ASC', 'search' => '', 'count_total' => true]);
    }

    public function query()
    {
        $result = (new \Minn\Runtime\UserQuery(\Minn\Runtime\Runtime::current()->db))->run($this->query_vars);
        $this->total_users = $result['total'];
        $this->results = array_map(fn (array $row) => $this->shape($row), $result['rows']);
    }

    public function get_results()
    {
        return $this->results;
    }

    public function get_total()
    {
        return $this->total_users;
    }

    public function get($query_var)
    {
        return $this->query_vars[$query_var] ?? null;
    }

    public function set($query_var, $value)
    {
        $this->query_vars[$query_var] = $value;
    }

    /** @param array<string, mixed> $row */
    protected function shape(array $row)
    {
        $fields = $this->query_vars['fields'];
        if (is_array($fields)) {
            $record = new stdClass();
            foreach ($fields as $column) {
                if (array_key_exists((string) $column, $row)) {
                    $record->{$column} = (string) $row[$column];
                }
            }
            return $record;
        }
        if ($fields === 'ID' || $fields === 'id') {
            return (string) $row['ID'];
        }
        return new WP_User((object) $row);
    }
}
