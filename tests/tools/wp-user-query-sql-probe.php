<?php
/**
 * WP_User_Query's SQL as plugin code meets it, as the reference builds it
 * (probe wp-user-query-sql): for each set of arguments, the pieces
 * pre_user_query is handed (fields, from, where, orderby, limit), the
 * request, the user query filters that run in their order, and what comes
 * back (logins, ids, records) with the total. Then filters that change the
 * query, answer before it, or replace the count. The probe's own users,
 * removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$ids = [];
$made = [];
foreach ([
    'ann' => ['editor', 'Ann Zz', 'ann@zz-users.test', 'https://ann.zz-users.test', '2025-01-05 10:00:00'],
    'bob' => ['author', 'Bob Zz', 'bob@zz-users.test', '', '2025-02-05 10:00:00'],
    'cyd' => ['subscriber', 'Cyd Zz', 'cyd@zz-users.test', '', '2025-03-05 10:00:00'],
    'dee' => ['author', 'Dee Zz', 'dee@zz-users.test', '', '2025-04-05 10:00:00'],
] as $login => [$role, $name, $email, $url, $registered]) {
    $id = (int) wp_insert_user(['user_login' => "zzu_{$login}", 'user_pass' => wp_generate_password(), 'user_email' => $email, 'display_name' => $name, 'user_nicename' => "zzu-{$login}", 'user_url' => $url, 'role' => $role, 'user_registered' => $registered]);
    $ids[$login] = $id;
    $made[] = $id;
}
update_user_meta($ids['ann'], 'zz_score', 7);
update_user_meta($ids['bob'], 'zz_score', 3);
update_user_meta($ids['dee'], 'zz_score', 11);
$post = (int) wp_insert_post(['post_title' => 'Zz Users Post', 'post_status' => 'publish', 'post_author' => $ids['bob']]);

$mask = static function ($value) use (&$ids) {
    $byId = array_flip(array_filter($ids));
    $walk = static function ($v) use (&$walk, $byId) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $item) {
                $out[is_int($k) && isset($byId[$k]) ? '{' . $byId[$k] . '}' : $k] = $walk($item);
            }
            return $out;
        }
        if (is_int($v) && isset($byId[$v])) {
            return '{' . $byId[$v] . '}';
        }
        if (!is_string($v)) {
            return $v;
        }
        $v = (string) preg_replace('/\{[0-9a-f]{64}\}/', '%', $v);
        foreach ($byId as $id => $name) {
            $v = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $v);
        }
        return $v;
    };
    return $walk($value);
};
$fired = [];
add_action('all', static function (string $hook) use (&$fired): void {
    if (preg_match('/^(pre_get_users|pre_user_query|users_pre_query|user_search_columns|found_users_query|get_meta_sql|users_list_table_query_args)/', $hook)) {
        $fired[] = $hook;
    }
});
$shape = static function ($result) {
    return array_map(static function ($user) {
        if ($user instanceof WP_User) {
            return $user->user_login;
        }
        return is_object($user) ? (array) $user : $user;
    }, (array) $result);
};
$query = static function (array $args) use (&$fired, $mask, $shape): array {
    $pieces = null;
    $grab = static function ($q) use (&$pieces): void {
        $pieces ??= ['fields' => $q->query_fields, 'from' => $q->query_from, 'where' => $q->query_where, 'orderby' => $q->query_orderby, 'limit' => $q->query_limit];
    };
    add_action('pre_user_query', $grab);
    $fired = [];
    $q = new WP_User_Query($args + ['cache_results' => false]);
    remove_action('pre_user_query', $grab);
    return $mask([
        'pieces' => $pieces,
        'request' => preg_replace('/\s+/', ' ', trim((string) $q->request)),
        'filters' => array_values(array_unique($fired)),
        'users' => array_values(array_filter($shape($q->get_results()), static fn ($u) => !is_string($u) || str_starts_with($u, 'zzu_') || ctype_digit($u))),
        'total' => $q->get_total(),
    ]);
};
$ours = ['search' => '*zz-users.test', 'search_columns' => ['user_email']];
$cases = [
    'ours, by login' => [],
    'by display name, descending' => ['orderby' => 'display_name', 'order' => 'DESC'],
    'by registration' => ['orderby' => 'registered'],
    'by email' => ['orderby' => 'email'],
    'by url' => ['orderby' => 'url'],
    'by nicename' => ['orderby' => 'nicename'],
    'by id' => ['orderby' => 'ID'],
    'by post count' => ['orderby' => 'post_count', 'order' => 'DESC'],
    'by include' => ['orderby' => 'include', 'include' => [$ids['dee'], $ids['ann']]],
    'by a meta value' => ['meta_key' => 'zz_score', 'orderby' => 'meta_value_num'],
    'by two orders' => ['orderby' => ['display_name' => 'DESC', 'ID' => 'ASC']],
    'an author' => ['role' => 'author'],
    'authors or editors' => ['role__in' => ['author', 'editor']],
    'not authors' => ['role__not_in' => ['author']],
    'two roles at once' => ['role' => ['author', 'editor']],
    'a capability' => ['capability' => 'edit_posts'],
    'capabilities in' => ['capability__in' => ['edit_others_posts', 'read']],
    'included' => ['include' => [$ids['ann'], $ids['cyd']]],
    'excluded' => ['exclude' => [$ids['ann']]],
    'by nicename list' => ['nicename__in' => ['zzu-bob', 'zzu-ann']],
    'by login list' => ['login__in' => ['zzu_cyd', 'zzu_ann']],
    'not these logins' => ['login__not_in' => ['zzu_cyd']],
    'one login' => ['login' => 'zzu_dee'],
    'two at a time' => ['number' => 2],
    'page two' => ['number' => 2, 'paged' => 2],
    'an offset' => ['number' => 2, 'offset' => 1],
    'uncounted' => ['number' => 1, 'count_total' => false],
    'ids' => ['fields' => 'ID'],
    'some columns' => ['fields' => ['ID', 'user_login', 'display_name']],
    'with meta' => ['fields' => 'all_with_meta', 'number' => 1],
    'who wrote' => ['who' => 'authors'],
    'have published' => ['has_published_posts' => true],
    'have published posts' => ['has_published_posts' => ['post']],
    'a meta query' => ['meta_query' => [['key' => 'zz_score', 'value' => 5, 'compare' => '>', 'type' => 'NUMERIC']]],
    'a meta query either way' => ['meta_query' => ['relation' => 'OR', ['key' => 'zz_score', 'value' => 10, 'compare' => '>', 'type' => 'NUMERIC'], ['key' => 'zz_score', 'compare' => 'NOT EXISTS']], 'role' => 'author'],
    'registered in a range' => ['date_query' => [['after' => '2025-02-01', 'before' => '2025-04-01']]],
];
$raw = new WP_User_Query($ours + ['number' => 2, 'cache_results' => false]);
$say('the request as written', $mask($raw->request));
foreach ($cases as $label => $args) {
    $say($label, $query($args + $ours));
}
$searches = [
    'a search' => ['search' => 'Ann'],
    'a leading wildcard' => ['search' => '*ann'],
    'a trailing wildcard' => ['search' => 'zzu_b*'],
    'both wildcards' => ['search' => '*zzu_c*'],
    'an email search' => ['search' => 'dee@zz-users.test'],
    'a numeric search' => ['search' => (string) $ids['cyd']],
    'a url search' => ['search' => 'https://ann.zz-users.test'],
    'search columns given' => ['search' => '*Zz*', 'search_columns' => ['display_name']],
];
foreach ($searches as $label => $args) {
    $say("search: {$label}", $query($args));
}

$with = static function (string $label, string $hook, callable $callback, array $args = [], int $accepted = 1) use ($say, $query, $ours): void {
    add_filter($hook, $callback, 10, $accepted);
    $say($label, $query($args + $ours));
    remove_filter($hook, $callback, 10);
};
$with('pre_get_users changes the order', 'pre_get_users', static function ($q) {
    $q->set('orderby', 'display_name');
    $q->set('order', 'DESC');
});
$with('pre_user_query adds a clause', 'pre_user_query', static function ($q) {
    $q->query_where .= " AND user_login != 'zzu_bob'";
});
$with('answered before the query', 'users_pre_query', static fn () => [get_userdata(1)]);
$with('search columns filtered', 'user_search_columns', static fn () => ['user_login'], ['search' => '*zzu_a*', 'search_columns' => []]);
$with('the count query replaced', 'found_users_query', static fn () => 'SELECT 42', ['number' => 1]);

wp_delete_post($post, true);
if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
foreach ($made as $id) {
    wp_delete_user($id, 1);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
