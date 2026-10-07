<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\Sql;

/**
 * WP_User_Query as the reference runs it (probe wp-user-query-sql): the
 * variables filled and handed to pre_get_users; the fields (a column list,
 * ids, the count), published authors, nicenames and logins, the meta query
 * with the roles and capabilities joined in, the order (post counts joined
 * in, include and list orders kept), the limit, a search over the columns
 * its shape suggests (through user_search_columns), ids in or out, the date
 * query, then pre_user_query; the query (users_pre_query may answer it),
 * found_users_query, and the results shaped as the fields ask.
 */
final class UserQueryRunner
{
    private const COLUMNS = ['id', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name'];
    private const SEARCHABLE = ['ID', 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name'];
    private const EMAIL = 'user_email';
    private const URL = 'user_url';

    public function __construct(private readonly object $wpdb)
    {
    }

    /** Builds the query's pieces from its variables, as prepare_query does. */
    public function prepare(\WP_User_Query $query): void
    {
        \do_action_ref_array('pre_get_users', [&$query]);
        $qv = &$query->query_vars;
        $qv = \WP_User_Query::fill_query_vars($qv);
        $users = $this->wpdb->users;
        $query->query_fields = $this->fields($qv);
        $query->query_from = "FROM {$users}";
        $query->query_where = 'WHERE 1=1';
        $include = !empty($qv['include']) ? \wp_parse_id_list($qv['include']) : [];
        $blog = isset($qv['blog_id']) ? \absint($qv['blog_id']) : 0;
        $query->query_where .= $this->published($qv, $blog) . $this->names($qv);
        $this->meta($query, $qv, $blog);
        $query->query_orderby = $this->order($query, $qv);
        if (isset($qv['number']) && $qv['number'] > 0) {
            $start = $qv['offset'] ? (int) $qv['offset'] : (int) $qv['number'] * ((int) $qv['paged'] - 1);
            $query->query_limit = 'LIMIT ' . $start . ', ' . (int) $qv['number'];
        }
        $search = isset($qv['search']) ? trim((string) $qv['search']) : '';
        if ($search !== '') {
            $query->query_where .= $this->search($query, $search, (array) $qv['search_columns']);
        }
        if ($include !== []) {
            $query->query_where .= " AND {$users}.ID IN (" . implode(',', $include) . ')';
        } elseif (!empty($qv['exclude'])) {
            $query->query_where .= " AND {$users}.ID NOT IN (" . implode(',', \wp_parse_id_list($qv['exclude'])) . ')';
        }
        if (!empty($qv['date_query']) && is_array($qv['date_query'])) {
            $query->query_where .= (new \WP_Date_Query($qv['date_query'], 'user_registered'))->get_sql();
        }
        \do_action_ref_array('pre_user_query', [&$query]);
    }

    /** The SELECT's fields: named columns, ids, and the count's own. @param array<string, mixed> $qv */
    private function fields(array &$qv): string
    {
        $users = $this->wpdb->users;
        if (is_array($qv['fields'])) {
            $qv['fields'] = array_intersect(array_unique(array_map('strtolower', $qv['fields'])), self::COLUMNS) ?: ['id'];
            $fields = implode(',', array_map(static fn (string $f) => "{$users}." . ($f === 'id' ? 'ID' : \sanitize_key($f)), $qv['fields']));
        } elseif (in_array($qv['fields'], ['all', 'all_with_meta'], true) || !in_array($qv['fields'], self::COLUMNS, true)) {
            $fields = "{$users}.ID";
        } else {
            $fields = "{$users}." . (strtolower((string) $qv['fields']) === 'id' ? 'ID' : \sanitize_key((string) $qv['fields']));
        }
        return !empty($qv['count_total']) ? 'SQL_CALC_FOUND_ROWS ' . $fields : $fields;
    }

    /** Authors with published posts (of public types, or the ones named). @param array<string, mixed> $qv */
    private function published(array $qv, int $blog): string
    {
        if (!$qv['has_published_posts'] || !$blog) {
            return '';
        }
        $types = $qv['has_published_posts'] === true ? array_values(\get_post_types(['public' => true])) : (array) $qv['has_published_posts'];
        $posts = $this->wpdb->get_blog_prefix($blog) . 'posts';
        return " AND {$this->wpdb->users}.ID IN ( SELECT DISTINCT {$posts}.post_author FROM {$posts} WHERE {$posts}.post_status = 'publish' AND {$posts}.post_type IN ( " . implode(', ', array_map(static fn ($t) => Sql::quote((string) $t), $types)) . ' ) )';
    }

    /** One nicename or login, or lists of them, in or out. @param array<string, mixed> $qv */
    private function names(array $qv): string
    {
        $where = '';
        foreach (['nicename' => 'user_nicename', 'login' => 'user_login'] as $var => $column) {
            if ($qv[$var] !== '') {
                $where .= " AND {$column} = " . Sql::quote((string) $qv[$var]);
            }
            foreach (["{$var}__in" => 'IN', "{$var}__not_in" => 'NOT IN'] as $list => $operator) {
                if (!empty($qv[$list])) {
                    $where .= " AND {$column} {$operator} ( '" . implode("','", array_map('esc_sql', (array) $qv[$list])) . "' )";
                }
            }
        }
        return $where;
    }

    /** The meta query with roles and capabilities, joined and written in. @param array<string, mixed> $qv */
    private function meta(\WP_User_Query $query, array &$qv, int $blog): void
    {
        $query->meta_query = new \WP_Meta_Query();
        $query->meta_query->parse_query_vars($qv);
        // Only the caller's own meta query can ask for DISTINCT: the role clauses join in after it was read.
        $distinct = $query->meta_query->has_or_relation();
        $prefix = $this->wpdb->get_blog_prefix($blog);
        $queries = (array) $query->meta_query->queries;
        if ($blog && ($qv['who'] ?? '') === 'authors') {
            // The old shorthand: a user level above zero, and no roles asked after it.
            \_deprecated_argument('WP_User_Query', '5.9.0', '<code>who</code> is deprecated. Use <code>capability</code> instead.');
            $who = ['key' => $prefix . 'user_level', 'value' => 0, 'compare' => '!='];
            $queries = $queries === [] ? [$who] : ['relation' => 'AND', [$queries, $who]];
            [$qv['blog_id'], $blog] = [0, 0];
        }
        if ($blog) {
            $queries = (new UserQueryRoles($prefix . 'capabilities'))->clauses($qv, $queries, (array) (\wp_roles()->roles ?? []));
        }
        if ($queries !== (array) $query->meta_query->queries) {
            $query->meta_query = new \WP_Meta_Query($queries);
        }
        if (empty($query->meta_query->queries)) {
            return;
        }
        $sql = $query->meta_query->get_sql('user', $this->wpdb->users, 'ID', $query);
        $query->query_from .= $sql['join'] ?? '';
        $query->query_where .= $sql['where'] ?? '';
        if ($distinct) {
            $query->query_fields = 'DISTINCT ' . $query->query_fields;
        }
    }

    /** ORDER BY from a key, a list, or a map of keys to directions; user_login when none is usable. @param array<string, mixed> $qv */
    private function order(\WP_User_Query $query, array &$qv): string
    {
        $qv['order'] = isset($qv['order']) ? strtoupper((string) $qv['order']) : '';
        $order = UserOrder::direction($qv['order']);
        $orders = empty($qv['orderby']) ? ['user_login' => $order] : (is_array($qv['orderby']) ? $qv['orderby'] : preg_split('/[,\s]+/', (string) $qv['orderby']));
        $parts = [];
        foreach ((array) $orders as $key => $value) {
            if (!$value) {
                continue;
            }
            [$by, $dir] = is_int($key) ? [(string) $value, $order] : [(string) $key, $value];
            $parsed = UserOrder::clause($query, $by, $this->wpdb);
            if ($parsed !== '') {
                $parts[] = in_array($by, ['nicename__in', 'login__in'], true) ? $parsed : $parsed . ' ' . UserOrder::direction($dir);
            }
        }
        return 'ORDER BY ' . implode(', ', $parts ?: ["user_login {$order}"]);
    }

    /**
     * The search: wildcards at either end, the columns given (those the
     * reference searches) or those its shape suggests, through
     * user_search_columns; ID matched exactly, the rest by LIKE.
     *
     * @param list<string> $given
     */
    private function search(\WP_User_Query $query, string $search, array $given): string
    {
        $leading = ltrim($search, '*') !== $search;
        $trailing = rtrim($search, '*') !== $search;
        $search = $leading || $trailing ? trim($search, '*') : $search;
        $columns = $given !== [] ? array_intersect($given, self::SEARCHABLE) : [];
        if ($columns === []) {
            $columns = match (true) {
                str_contains($search, '@') => [self::EMAIL],
                is_numeric($search) => ['user_login', 'ID'],
                preg_match('|^https?://|', $search) === 1 && !(\is_multisite() && \wp_is_large_network('users')) => [self::URL],
                default => ['user_login', 'user_url', 'user_email', 'user_nicename', 'display_name'],
            };
        }
        $columns = (array) \apply_filters('user_search_columns', $columns, $search, $query);
        $like = Sql::quote(($leading ? '%' : '') . Sql::like($search) . ($trailing ? '%' : ''));
        $parts = array_map(static fn ($column) => $column === 'ID' ? "{$column} = " . Sql::quote($search) : "{$column} LIKE {$like}", $columns);
        return ' AND (' . implode(' OR ', $parts) . ')';
    }

    /** Runs the query: users_pre_query may answer it; the count follows; the results take the fields' shape. */
    public function query(\WP_User_Query $query): void
    {
        $qv = &$query->query_vars;
        if (is_array($qv['fields']) && count($qv['fields']) > 3) {
            $qv['cache_results'] = false;
        }
        $results = \apply_filters_ref_array('users_pre_query', [null, &$query]);
        if ($results === null) {
            $break = "\n\t\t\t\t ";
            $query->request = "SELECT {$query->query_fields}{$break}{$query->query_from}{$break}{$query->query_where}{$break}{$query->query_orderby}{$break}{$query->query_limit}";
            $results = is_array($qv['fields']) ? $this->wpdb->get_results($query->request) : $this->wpdb->get_col($query->request);
            if (!empty($qv['count_total'])) {
                $query->total_users = (int) $this->wpdb->get_var(\apply_filters('found_users_query', 'SELECT FOUND_ROWS()', $query));
            }
        }
        $query->results = $results;
        if (!$results) {
            return;
        }
        if (is_array($qv['fields']) && isset($results[0]->ID)) {
            foreach ($results as $row) {
                $row->id = $row->ID;
            }
        } elseif ($qv['fields'] === 'all' || $qv['fields'] === 'all_with_meta') {
            \cache_users($results);
            $users = [];
            foreach ($results as $user) {
                $object = new \WP_User($user, '', $qv['blog_id']);
                if ($qv['fields'] === 'all_with_meta') {
                    $users[is_scalar($user) ? $user : $object->ID] = $object;
                } else {
                    $users[] = $object;
                }
            }
            $query->results = $users;
        }
    }
}
