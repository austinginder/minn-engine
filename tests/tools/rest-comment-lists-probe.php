<?php
/**
 * REST comment collections as the reference serves them (probe
 * rest-comment-lists): for each list request, the WP_Comment_Query
 * arguments rest_comment_query is handed, the clauses each comment query
 * ran (the list, then any count), which query filters fired, the ids that
 * came back with their links, the totals, the Link header and the status. Paging, offsets,
 * search, include and exclude, every orderby, parents, dates, the post
 * filter (a private post, a password-protected one with and without its
 * password), what a visitor may not ask for (statuses, authors, emails,
 * types, the edit context), what an administrator sees, a HEAD request,
 * a plugin's rest_comment_query, pre_get_comments and comments_clauses,
 * and the collection parameters the route declares. Each case starts
 * after a bump of the comment last_changed, so none is answered from the
 * reference's query cache. Same protocol as api-probe.php; dispatched in
 * process; everything it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}

$ids = [];
$ids['post'] = (int) wp_insert_post(['post_title' => 'Zz Rcl Post', 'post_name' => 'zz-rcl-post', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'open']);
$ids['private'] = (int) wp_insert_post(['post_title' => 'Zz Rcl Private', 'post_name' => 'zz-rcl-private', 'post_status' => 'private', 'post_author' => 1, 'comment_status' => 'open']);
$ids['locked'] = (int) wp_insert_post(['post_title' => 'Zz Rcl Locked', 'post_name' => 'zz-rcl-locked', 'post_status' => 'publish', 'post_password' => 'zz-pass', 'post_author' => 1, 'comment_status' => 'open']);
$made = [];
$add = static function (string $name, array $data) use (&$ids, &$made): void {
    $ids[$name] = (int) wp_insert_comment($data + ['comment_post_ID' => $ids['post'], 'comment_author' => 'Zz ' . ucfirst($name), 'comment_author_email' => "{$name}@zz-rcl.test", 'comment_content' => "zz {$name} words", 'comment_approved' => 1, 'comment_type' => 'comment']);
    $made[] = $ids[$name];
};
$add('ann', ['comment_date' => '2025-01-01 10:00:00', 'comment_date_gmt' => '2025-01-01 10:00:00']);
$add('bob', ['comment_date' => '2025-02-01 10:00:00', 'comment_date_gmt' => '2025-02-01 10:00:00', 'user_id' => 1]);
$add('reply', ['comment_date' => '2025-02-02 10:00:00', 'comment_date_gmt' => '2025-02-02 10:00:00', 'comment_parent' => $ids['ann']]);
$add('held', ['comment_date' => '2025-03-01 10:00:00', 'comment_date_gmt' => '2025-03-01 10:00:00', 'comment_approved' => 0]);
$add('spam', ['comment_date' => '2025-03-02 10:00:00', 'comment_date_gmt' => '2025-03-02 10:00:00', 'comment_approved' => 'spam']);
$add('ping', ['comment_date' => '2025-03-03 10:00:00', 'comment_date_gmt' => '2025-03-03 10:00:00', 'comment_type' => 'pingback']);
$add('binned', ['comment_date' => '2025-03-04 10:00:00', 'comment_date_gmt' => '2025-03-04 10:00:00', 'comment_approved' => 'trash']);
$add('secret', ['comment_date' => '2025-04-01 10:00:00', 'comment_date_gmt' => '2025-04-01 10:00:00', 'comment_post_ID' => $ids['private']]);
$add('hush', ['comment_date' => '2025-04-02 10:00:00', 'comment_date_gmt' => '2025-04-02 10:00:00', 'comment_post_ID' => $ids['locked']]);

$mask = static function ($value) use (&$ids) {
    $byId = [];
    foreach (array_filter($ids) as $name => $id) {
        $byId[$id] ??= $name;
    }
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
    if (preg_match('/^(parse_comment_query|pre_get_comments|comments_pre_query|comments_clauses|found_comments_query|the_comments|rest_comment_query|rest_comment_collection_params)/', $hook)) {
        $fired[] = $hook;
    }
});
$send = static function (string $route, array $query = [], string $method = 'GET') use (&$fired, $mask): array {
    $args = null;
    $runs = [];
    $grabArgs = static function ($a) use (&$args) {
        $args ??= $a;
        return $a;
    };
    $grabRun = static function ($clauses) use (&$runs) {
        $runs[] = array_intersect_key((array) $clauses, array_flip(['fields', 'join', 'where', 'orderby', 'limits']));
        return $clauses;
    };
    add_filter('rest_comment_query', $grabArgs);
    add_filter('comments_clauses', $grabRun);
    wp_cache_set_last_changed('comment');
    $fired = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    remove_filter('rest_comment_query', $grabArgs);
    remove_filter('comments_clauses', $grabRun);
    $data = rest_get_server()->response_to_data($response, false);
    $headers = $response->get_headers();
    return $mask([
        'status' => $response->get_status(),
        'args' => $args,
        'runs' => $runs,
        'filters' => array_values(array_unique($fired)),
        'ids' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => $item['id'] ?? $item, $data) : ($data['code'] ?? $data),
        'links' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => array_keys((array) ($item['_links'] ?? [])), $data) : null,
        'total' => [$headers['X-WP-Total'] ?? null, $headers['X-WP-TotalPages'] ?? null],
        'link' => $headers['Link'] ?? null,
    ]);
};

// The collection parameters the list takes, as its OPTIONS answer describes them.
$data = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('OPTIONS', '/wp/v2/comments')), false);
foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
    if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
        $say('params', $endpoint['args'] ?? []);
    }
}

$ours = ['post' => [$ids['post']]];
$cases = [
    'a plain list' => [],
    'two per page, page two' => ['per_page' => 2, 'page' => 2],
    'a page past the end' => ['page' => 9],
    'an offset' => ['offset' => 1, 'per_page' => 2],
    'a search' => ['search' => 'reply'],
    'included, in that order' => ['include' => [$ids['bob'], $ids['ann']], 'orderby' => 'include'],
    'excluded' => ['exclude' => [$ids['ann']]],
    'oldest first' => ['order' => 'asc'],
    'by id' => ['orderby' => 'id'],
    'by date gmt' => ['orderby' => 'date_gmt'],
    'by post' => ['orderby' => 'post'],
    'by parent' => ['orderby' => 'parent'],
    'by type' => ['orderby' => 'type'],
    'by date' => ['orderby' => 'date'],
    'between two dates' => ['after' => '2025-01-15T00:00:00', 'before' => '2025-02-01T12:00:00'],
    'replies to ann' => ['parent' => [$ids['ann']]],
    'not replies to ann' => ['parent_exclude' => [$ids['ann']]],
    'after a date' => ['after' => '2025-01-15T00:00:00'],
    'before a date' => ['before' => '2025-01-15T00:00:00'],
    'held, signed out' => ['status' => 'hold'],
    'by author, signed out' => ['author' => [1]],
    'not by an author, signed out' => ['author_exclude' => [1]],
    'by email, signed out' => ['author_email' => 'ann@zz-rcl.test'],
    'pings, signed out' => ['type' => 'pingback'],
    'edit context, signed out' => ['context' => 'edit'],
    'a private post\'s' => ['post' => [$ids['private']]],
    'a locked post\'s, no password' => ['post' => [$ids['locked']]],
    'a locked post\'s, with its password' => ['post' => [$ids['locked']], 'password' => 'zz-pass'],
    'across posts, by include' => ['post' => [], 'include' => [$ids['ann'], $ids['bob'], $ids['secret'], $ids['hush']]],
];
foreach ($cases as $label => $query) {
    $say($label, $send('/wp/v2/comments', $query + $ours));
}
$say('a HEAD request', $send('/wp/v2/comments', ['per_page' => 2] + $ours, 'HEAD'));

wp_set_current_user(1);
foreach ([
    'default' => [],
    'held' => ['status' => 'hold'],
    'spam' => ['status' => 'spam'],
    'trash' => ['status' => 'trash'],
    'every status' => ['status' => 'all'],
    'pings' => ['type' => 'pingback'],
    'by author' => ['author' => [1]],
    'not by an author' => ['author_exclude' => [1]],
    'by email' => ['author_email' => 'ann@zz-rcl.test'],
    'a private post\'s' => ['post' => [$ids['private']]],
    'a locked post\'s' => ['post' => [$ids['locked']]],
    'edit context' => ['context' => 'edit', 'per_page' => 2],
    'a reply\'s children link' => ['include' => [$ids['ann']], 'context' => 'edit'],
    'across posts, by include' => ['post' => [], 'include' => [$ids['ann'], $ids['bob'], $ids['secret'], $ids['hush']]],
] as $label => $query) {
    $say("as an administrator: {$label}", $send('/wp/v2/comments', $query + $ours));
}
$item = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('GET', "/wp/v2/comments/{$ids['ann']}")), false);
$say('a comment with replies, its links', $mask($item['_links'] ?? null));
wp_set_current_user(0);

// A plugin's say in the list: the REST query filter, pre_get_comments, the clauses.
$with = static function (string $label, string $hook, callable $callback, array $query = []) use ($say, $send, $ours): void {
    add_filter($hook, $callback);
    $say($label, $send('/wp/v2/comments', $query + $ours));
    remove_filter($hook, $callback);
};
$with('rest_comment_query limits the list', 'rest_comment_query', static fn ($args) => ['number' => 1] + $args);
$with('pre_get_comments turns the order', 'pre_get_comments', static function ($q) {
    $q->query_vars['order'] = 'ASC';
});
$with('comments_clauses leaves one out', 'comments_clauses', static fn ($c) => ['where' => $c['where'] . " AND comment_author != 'Zz Bob'"] + $c);

foreach ($made as $id) {
    wp_delete_comment($id, true);
}
foreach (['post', 'private', 'locked'] as $name) {
    wp_delete_post($ids[$name], true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
