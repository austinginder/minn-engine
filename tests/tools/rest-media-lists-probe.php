<?php
/**
 * REST media collections as the reference serves them (probe
 * rest-media-lists): for each list request, the WP_Query arguments
 * rest_attachment_query is handed, the request the query ran, which query
 * filters fired, the ids that came back, the totals, the Link header and
 * the status. Paging, offsets, search, include and exclude, every orderby,
 * media and mime types, parents, authors, dates, slugs, statuses, the edit
 * context, a HEAD request, a plugin's rest_attachment_query and
 * pre_get_posts, and the collection parameters the route declares. The
 * reference's query results cache is set aside (each case runs its query).
 * Same protocol as api-probe.php; dispatched in process; everything it
 * makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}

$ids = [];
$ids['post'] = (int) wp_insert_post(['post_title' => 'Zz Rml Post', 'post_name' => 'zz-rml-post', 'post_status' => 'publish', 'post_author' => 1]);
foreach ([
    'one' => ['image/jpeg', '2025-01-01 10:00:00', 'post', 'Zz Rml One', 'inherit'],
    'two' => ['image/png', '2025-02-01 10:00:00', 'post', 'Zz Rml Two', 'inherit'],
    'doc' => ['application/pdf', '2025-03-01 10:00:00', 0, 'Zz Rml Doc', 'inherit'],
    'song' => ['audio/mpeg', '2025-04-01 10:00:00', 0, 'Zz Rml Song', 'inherit'],
    'hidden' => ['image/gif', '2025-05-01 10:00:00', 0, 'Zz Rml Hidden', 'private'],
] as $slug => [$mime, $date, $parent, $title, $status]) {
    $ids[$slug] = (int) wp_insert_attachment(['post_title' => $title, 'post_name' => "zz-rml-{$slug}", 'post_mime_type' => $mime, 'post_status' => $status, 'post_date' => $date, 'post_date_gmt' => $date, 'post_author' => 1, 'post_content' => "zz {$slug} words", 'guid' => "https://zz-rml.test/{$slug}"], false, $parent === 'post' ? $ids['post'] : 0);
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
    if (preg_match('/^(pre_get_posts|posts_pre_query|found_posts|the_posts|rest_attachment_query|rest_attachment_collection_params|rest_query_var-)/', $hook)) {
        $fired[] = $hook;
    }
});
$send = static function (string $route, array $query = [], string $method = 'GET') use (&$fired, $mask): array {
    $args = null;
    $sql = null;
    $grabArgs = static function ($a) use (&$args) {
        $args ??= $a;
        return $a;
    };
    $grabSql = static function ($request) use (&$sql) {
        $sql ??= preg_replace('/\s+/', ' ', trim((string) $request));
        return $request;
    };
    add_filter('rest_attachment_query', $grabArgs);
    add_filter('posts_request', $grabSql);
    wp_cache_set_last_changed('posts');
    $fired = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    remove_filter('rest_attachment_query', $grabArgs);
    remove_filter('posts_request', $grabSql);
    $data = rest_get_server()->response_to_data($response, false);
    $headers = $response->get_headers();
    return $mask([
        'status' => $response->get_status(),
        'args' => $args,
        'sql' => $sql,
        'filters' => array_values(array_unique($fired)),
        'ids' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => $item['id'] ?? $item, $data) : ($data['code'] ?? $data),
        'message' => is_array($data) && isset($data['code']) ? ($data['message'] ?? null) : null,
        'total' => [$headers['X-WP-Total'] ?? null, $headers['X-WP-TotalPages'] ?? null],
        'link' => $headers['Link'] ?? null,
    ]);
};

// The collection parameters the list takes, as its OPTIONS answer describes them.
$data = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('OPTIONS', '/wp/v2/media')), false);
foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
    if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
        $say('params', $endpoint['args'] ?? []);
    }
}

$ours = ['include' => [$ids['one'], $ids['two'], $ids['doc'], $ids['song'], $ids['hidden']]];
$cases = [
    'a plain list' => [],
    'two per page, page two' => ['per_page' => 2, 'page' => 2],
    'a page past the end' => ['page' => 9],
    'an offset' => ['offset' => 1, 'per_page' => 2],
    'a search' => ['search' => 'Two'],
    'in the order included' => ['include' => [$ids['doc'], $ids['one']], 'orderby' => 'include'],
    'excluded' => ['exclude' => [$ids['one']]],
    'oldest first' => ['order' => 'asc'],
    'by title' => ['orderby' => 'title', 'order' => 'asc'],
    'by id' => ['orderby' => 'id'],
    'by slug' => ['orderby' => 'slug'],
    'by modified' => ['orderby' => 'modified'],
    'images' => ['media_type' => 'image'],
    'documents' => ['media_type' => 'application'],
    'audio' => ['media_type' => 'audio'],
    'a mime type' => ['mime_type' => 'image/png'],
    'attached to the post' => ['parent' => [$ids['post']]],
    'not attached' => ['parent' => [0]],
    'not attached to the post' => ['parent_exclude' => [$ids['post']]],
    'by author' => ['author' => [1]],
    'after a date' => ['after' => '2025-02-15T00:00:00'],
    'before a date' => ['before' => '2025-02-15T00:00:00'],
    'by slug list' => ['slug' => ['zz-rml-two', 'zz-rml-one']],
    'private, signed out' => ['status' => 'private'],
    'edit context, signed out' => ['context' => 'edit'],
];
foreach ($cases as $label => $query) {
    $say($label, $send('/wp/v2/media', $query + $ours));
}
$say('a HEAD request', $send('/wp/v2/media', ['per_page' => 2] + $ours, 'HEAD'));

wp_set_current_user(1);
foreach ([
    'default' => [],
    'private' => ['status' => 'private'],
    'inherit and private' => ['status' => ['inherit', 'private']],
    'edit context' => ['context' => 'edit', 'per_page' => 2],
] as $label => $query) {
    $say("as an administrator: {$label}", $send('/wp/v2/media', $query + $ours));
}
wp_set_current_user(0);

// A plugin's say in the list: the REST query filter, pre_get_posts.
$with = static function (string $label, string $hook, callable $callback, array $query = []) use ($say, $send, $ours): void {
    add_filter($hook, $callback);
    $say($label, $send('/wp/v2/media', $query + $ours));
    remove_filter($hook, $callback);
};
$with('rest_attachment_query limits the list', 'rest_attachment_query', static fn ($args) => ['posts_per_page' => 1] + $args);
$with('pre_get_posts turns the order', 'pre_get_posts', static function ($q) {
    $q->set('order', 'ASC');
});

foreach (['one', 'two', 'doc', 'song', 'hidden'] as $slug) {
    wp_delete_attachment($ids[$slug], true);
}
wp_delete_post($ids['post'], true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
