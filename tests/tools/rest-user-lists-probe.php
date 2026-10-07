<?php
/**
 * REST user collections as the reference serves them (probe
 * rest-user-lists): for each list request, the WP_User_Query arguments
 * rest_user_query is handed, the pieces pre_user_query sees and the
 * request, which user query filters fired, the ids that came back, the
 * totals, the Link header and the status. Paging, offsets, search (and its
 * columns), include and exclude, every orderby, slugs, what a visitor may
 * not ask for (roles, capabilities, the authors, an email or registration
 * order, the edit context), what an administrator sees, a HEAD request,
 * a plugin's rest_user_query and pre_get_users, and the collection
 * parameters the route declares. The reference's query results cache is
 * set aside (each case runs its query). Same protocol as api-probe.php;
 * dispatched in process; everything it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}

$ids = [];
$made = [];
$posts = [];
foreach ([
    'ann' => ['editor', 'Ann Zzr', 'https://ann.zz-rul.test', '2025-01-05 10:00:00', true],
    'bob' => ['author', 'Bob Zzr', '', '2025-02-05 10:00:00', false],
    'cyd' => ['subscriber', 'Cyd Zzr', '', '2025-03-05 10:00:00', false],
    'dee' => ['author', 'Dee Zzr', '', '2025-04-05 10:00:00', true],
] as $login => [$role, $name, $url, $registered, $writes]) {
    $id = (int) wp_insert_user(['user_login' => "zzr_{$login}", 'user_pass' => wp_generate_password(), 'user_email' => "{$login}@zz-rul.test", 'display_name' => $name, 'user_nicename' => "zzr-{$login}", 'user_url' => $url, 'role' => $role, 'user_registered' => $registered]);
    $ids[$login] = $id;
    $made[] = $id;
    if ($writes) {
        $posts[] = (int) wp_insert_post(['post_title' => "Zz Rul {$name}", 'post_name' => "zz-rul-{$login}", 'post_status' => 'publish', 'post_author' => $id]);
    }
}

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
    if (preg_match('/^(pre_get_users|pre_user_query|users_pre_query|user_search_columns|found_users_query|rest_user_query|rest_user_collection_params)/', $hook)) {
        $fired[] = $hook;
    }
});
$send = static function (string $route, array $query = [], string $method = 'GET') use (&$fired, $mask): array {
    $args = null;
    $pieces = [];
    $grabArgs = static function ($a) use (&$args) {
        $args ??= $a;
        return $a;
    };
    $grabPieces = static function ($q) use (&$pieces): void {
        $pieces[] = ['fields' => $q->query_fields, 'from' => $q->query_from, 'where' => $q->query_where, 'orderby' => $q->query_orderby, 'limit' => $q->query_limit];
    };
    add_filter('rest_user_query', $grabArgs);
    add_action('pre_user_query', $grabPieces);
    // Every case runs its query: a repeat of an earlier one would come from the reference's results cache.
    wp_cache_set_last_changed('users');
    $fired = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    remove_filter('rest_user_query', $grabArgs);
    remove_action('pre_user_query', $grabPieces);
    $data = rest_get_server()->response_to_data($response, false);
    $headers = $response->get_headers();
    return $mask([
        'status' => $response->get_status(),
        'args' => $args,
        'pieces' => $pieces,
        'filters' => array_values(array_unique($fired)),
        'ids' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => $item['id'] ?? $item, $data) : ($data['code'] ?? $data),
        'message' => is_array($data) && isset($data['code']) ? ($data['message'] ?? null) : null,
        'total' => [$headers['X-WP-Total'] ?? null, $headers['X-WP-TotalPages'] ?? null],
        'link' => $headers['Link'] ?? null,
    ]);
};

// The collection parameters the list takes, as its OPTIONS answer describes them.
$data = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('OPTIONS', '/wp/v2/users')), false);
foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
    if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
        $say('params', $endpoint['args'] ?? []);
    }
}

$ours = ['include' => [$ids['ann'], $ids['bob'], $ids['cyd'], $ids['dee']]];
$cases = [
    'a plain list' => [],
    'two per page, page two' => ['per_page' => 1, 'page' => 2],
    'a page past the end' => ['page' => 9],
    'an offset' => ['offset' => 1, 'per_page' => 1],
    'a search' => ['search' => 'dee'],
    'in the order included' => ['include' => [$ids['dee'], $ids['ann']], 'orderby' => 'include'],
    'excluded' => ['exclude' => [$ids['ann']]],
    'newest name first' => ['order' => 'desc'],
    'by id' => ['orderby' => 'id'],
    'by slug' => ['orderby' => 'slug'],
    'by url' => ['orderby' => 'url'],
    'by slug list' => ['slug' => ['zzr-dee', 'zzr-ann'], 'orderby' => 'include_slugs'],
    'by email, signed out' => ['orderby' => 'email'],
    'by registration, signed out' => ['orderby' => 'registered_date'],
    'a role, signed out' => ['roles' => ['author']],
    'a capability, signed out' => ['capabilities' => ['edit_posts']],
    'the authors, signed out' => ['who' => 'authors'],
    'who have published, signed out' => ['has_published_posts' => true],
    'edit context, signed out' => ['context' => 'edit'],
];
foreach ($cases as $label => $query) {
    $say($label, $send('/wp/v2/users', $query + $ours));
}
$say('a HEAD request', $send('/wp/v2/users', ['per_page' => 1] + $ours, 'HEAD'));

wp_set_current_user($ids['cyd']);
foreach (['a role' => ['roles' => ['author']], 'the authors' => ['who' => 'authors'], 'by email' => ['orderby' => 'email'], 'edit context' => ['context' => 'edit'], 'a search' => ['search' => 'dee']] as $label => $query) {
    $say("as a subscriber: {$label}", $send('/wp/v2/users', $query + $ours));
}
wp_set_current_user(1);
foreach ([
    'everyone' => [],
    'a search, every column' => ['search' => 'dee'],
    'a role' => ['roles' => ['author']],
    'two roles' => ['roles' => ['author', 'editor']],
    'a capability' => ['capabilities' => ['edit_others_posts']],
    'the authors' => ['who' => 'authors'],
    'who have published' => ['has_published_posts' => true],
    'who have published posts' => ['has_published_posts' => ['post']],
    'by email' => ['orderby' => 'email'],
    'by registration' => ['orderby' => 'registered_date', 'order' => 'desc'],
    'a search in emails' => ['search' => 'bob@zz', 'search_columns' => ['email']],
    'a search in names' => ['search' => 'Zzr', 'search_columns' => ['name']],
    'edit context' => ['context' => 'edit', 'per_page' => 2],
] as $label => $query) {
    $say("as an administrator: {$label}", $send('/wp/v2/users', $query + $ours));
}
wp_set_current_user(0);

// A plugin's say in the list: the REST query filter, pre_get_users.
$with = static function (string $label, string $hook, callable $callback, array $query = []) use ($say, $send, $ours): void {
    add_filter($hook, $callback);
    $say($label, $send('/wp/v2/users', $query + $ours));
    remove_filter($hook, $callback);
};
$with('rest_user_query limits the list', 'rest_user_query', static fn ($args) => ['number' => 1] + $args);
$with('pre_get_users turns the order', 'pre_get_users', static function ($q) {
    $q->set('order', 'DESC');
});

foreach ($posts as $post) {
    wp_delete_post($post, true);
}
if (!function_exists('wp_delete_user')) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
}
foreach ($made as $id) {
    wp_delete_user($id, 1);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
