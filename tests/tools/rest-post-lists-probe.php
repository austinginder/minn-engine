<?php
/**
 * REST post collections as the reference serves them (probe
 * rest-post-lists): for each list request, the WP_Query arguments
 * rest_{type}_query is handed, the request the query ran, which query
 * filters fired, the ids that came back, the totals, and the status. Posts,
 * pages and a plugin's type with its own taxonomy; paging, search, include
 * and exclude, authors, parents, slugs, statuses, every orderby, term
 * filters (lists and objects, with a relation), stickies, dates, search
 * columns and semantics, formats; a plugin's rest_post_query and
 * pre_get_posts changing the list; a HEAD request; and the collection
 * parameters each route declares. The reference's query results cache is
 * set aside (each case runs its query). Same protocol as
 * api-probe.php; dispatched in process as a visitor and an administrator;
 * everything it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_taxonomy('zz_lt', ['zz_list'], ['hierarchical' => true, 'show_in_rest' => true, 'rest_base' => 'zz-lts']);
register_post_type('zz_list', ['public' => true, 'show_in_rest' => true, 'rest_base' => 'zz-lists', 'hierarchical' => true, 'supports' => ['title', 'editor', 'page-attributes', 'author'], 'taxonomies' => ['zz_lt']]);
if (did_action('rest_api_init')) {
    $GLOBALS['wp_rest_server'] = null;
}

$ids = [];
$made = ['posts' => [], 'terms' => []];
foreach (['red' => 0, 'pink' => 'red', 'blue' => 0] as $slug => $parent) {
    $term = wp_insert_term("Zz Lt {$slug}", 'zz_lt', ['slug' => "zz-lt-{$slug}", 'parent' => $parent === 0 ? 0 : $ids["term {$parent}"]]);
    $ids["term {$slug}"] = (int) ($term['term_id'] ?? 0);
    $made['terms'][] = $ids["term {$slug}"];
}
$specs = [
    'one' => ['date' => '2025-02-01 09:00:00', 'menu' => 3, 'content' => 'alpha words here', 'status' => 'publish', 'terms' => ['zz-lt-red'], 'author' => 1],
    'two' => ['date' => '2025-06-01 09:00:00', 'menu' => 1, 'content' => 'beta words', 'status' => 'publish', 'terms' => ['zz-lt-pink'], 'author' => 1],
    'three' => ['date' => '2026-01-15 09:00:00', 'menu' => 2, 'content' => 'alpha and beta', 'status' => 'publish', 'terms' => ['zz-lt-blue'], 'author' => 1],
    'secret' => ['date' => '2026-03-01 09:00:00', 'menu' => 0, 'content' => 'private alpha', 'status' => 'private', 'terms' => [], 'author' => 1],
    'draft' => ['date' => '2026-04-01 09:00:00', 'menu' => 0, 'content' => 'draft alpha', 'status' => 'draft', 'terms' => ['zz-lt-red'], 'author' => 1],
];
foreach ($specs as $slug => $spec) {
    $id = (int) wp_insert_post(['post_type' => 'zz_list', 'post_title' => 'Zz List ' . ucfirst($slug), 'post_name' => "zz-list-{$slug}", 'post_status' => $spec['status'], 'post_date' => $spec['date'], 'post_date_gmt' => $spec['date'], 'post_modified' => $spec['date'], 'menu_order' => $spec['menu'], 'post_content' => $spec['content'], 'post_author' => $spec['author']]);
    $ids[$slug] = $id;
    $made['posts'][] = $id;
    wp_set_object_terms($id, $spec['terms'], 'zz_lt');
}
$ids['child'] = (int) wp_insert_post(['post_type' => 'zz_list', 'post_title' => 'Zz List Child', 'post_name' => 'zz-list-child', 'post_status' => 'publish', 'post_parent' => $ids['one'], 'post_date' => '2025-03-01 09:00:00', 'post_date_gmt' => '2025-03-01 09:00:00', 'post_author' => 1]);
$made['posts'][] = $ids['child'];

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
    if (preg_match('/^(pre_get_posts|posts_pre_query|found_posts|the_posts|rest_[a-z_]+_query|rest_query_var-)/', $hook)) {
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
    $sticky = 'unseen';
    $grabSticky = static function ($query) use (&$sticky): void {
        if ($sticky === 'unseen') {
            $sticky = $query->query_vars['ignore_sticky_posts'] ?? 'unset';
        }
    };
    add_action('pre_get_posts', $grabSticky, 1000);
    $grabSql = static function ($request) use (&$sql) {
        $sql ??= preg_replace('/\s+/', ' ', trim((string) $request));
        return $request;
    };
    foreach (['post', 'page', 'zz_list'] as $type) {
        add_filter("rest_{$type}_query", $grabArgs);
    }
    add_filter('posts_request', $grabSql);
    // Every case runs its query: a repeat of an earlier one would come from the reference's results cache.
    wp_cache_set_last_changed('posts');
    $fired = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    foreach (['post', 'page', 'zz_list'] as $type) {
        remove_filter("rest_{$type}_query", $grabArgs);
    }
    remove_filter('posts_request', $grabSql);
    remove_action('pre_get_posts', $grabSticky, 1000);
    $data = rest_get_server()->response_to_data($response, false);
    $headers = $response->get_headers();
    return $mask([
        'status' => $response->get_status(),
        'args' => $args,
        'sql' => $sql,
        'filters' => array_values(array_unique($fired)),
        'ids' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => $item['id'] ?? $item, $data) : ($data['code'] ?? $data),
        'total' => [$headers['X-WP-Total'] ?? null, $headers['X-WP-TotalPages'] ?? null],
        'ignore sticky' => $sticky,
    ]);
};

// The collection parameters each list takes, as its OPTIONS answer describes them.
$params = static function (string $route): array {
    $data = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('OPTIONS', $route)), false);
    foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
        if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
            return (array) ($endpoint['args'] ?? []);
        }
    }
    return [];
};
foreach (['/wp/v2/zz-lists', '/wp/v2/posts', '/wp/v2/pages'] as $route) {
    $say("params: {$route}", $params($route));
}

$cases = [
    'a plain list' => [],
    'two per page, page two' => ['per_page' => 2, 'page' => 2],
    'a page past the end' => ['page' => 9],
    'an offset' => ['offset' => 1, 'per_page' => 2],
    'a search' => ['search' => 'alpha'],
    'a search by relevance' => ['search' => 'alpha beta', 'orderby' => 'relevance'],
    'relevance without a search' => ['orderby' => 'relevance'],
    'included, in that order' => ['include' => [$ids['three'], $ids['one']], 'orderby' => 'include'],
    'include order without include' => ['orderby' => 'include'],
    'excluded' => ['exclude' => [$ids['one']]],
    'by author' => ['author' => [1]],
    'not by these authors' => ['author_exclude' => [2]],
    'by slug' => ['slug' => ['zz-list-two', 'zz-list-one']],
    'by slug, in that order' => ['slug' => ['zz-list-two', 'zz-list-one'], 'orderby' => 'include_slugs'],
    'by title, ascending' => ['orderby' => 'title', 'order' => 'asc'],
    'by id' => ['orderby' => 'id', 'order' => 'asc'],
    'by slug order' => ['orderby' => 'slug'],
    'by modified' => ['orderby' => 'modified'],
    'by parent' => ['orderby' => 'parent'],
    'by menu order' => ['orderby' => 'menu_order', 'order' => 'asc'],
    'children of one' => ['parent' => [$ids['one']]],
    'top level only' => ['parent' => [0]],
    'not under one' => ['parent_exclude' => [$ids['one']]],
    'a menu order' => ['menu_order' => 1],
    'in a term' => ['zz-lts' => [$ids['term red']]],
    'in a term with children' => ['zz-lts' => ['terms' => [$ids['term red']], 'include_children' => true]],
    'in all of two terms' => ['zz-lts' => ['terms' => [$ids['term red'], $ids['term pink']], 'operator' => 'AND']],
    'not in a term' => ['zz-lts_exclude' => [$ids['term red']]],
    'terms either way' => ['zz-lts' => [$ids['term blue']], 'zz-lts_exclude' => [$ids['term red']], 'tax_relation' => 'OR'],
    'after a date' => ['after' => '2025-05-01T00:00:00'],
    'before a date' => ['before' => '2025-05-01T00:00:00'],
    'modified after' => ['modified_after' => '2025-12-01T00:00:00'],
    'modified before' => ['modified_before' => '2025-12-01T00:00:00'],
    'a search in titles' => ['search' => 'one', 'search_columns' => ['post_title']],
    'an exact search' => ['search' => 'Zz List One', 'search_semantics' => 'exact'],
    'a draft, signed out' => ['status' => 'draft'],
];
foreach ($cases as $label => $query) {
    $say("list: {$label}", $send('/wp/v2/zz-lists', $query));
}
foreach (['posts' => [], 'posts, stickies' => ['sticky' => true], 'posts, no stickies' => ['sticky' => false], 'posts, sticky ones kept in place' => ['ignore_sticky' => false, 'per_page' => 3], 'posts, in a category' => ['categories' => [1], 'per_page' => 3], 'posts, standard format' => ['format' => ['standard'], 'per_page' => 3], 'posts, aside format' => ['format' => ['aside']], 'posts, two formats and a category' => ['format' => ['standard', 'aside'], 'categories' => [1], 'per_page' => 3], 'posts, formats and terms either way' => ['format' => ['gallery'], 'tags' => [999], 'tax_relation' => 'OR', 'per_page' => 3], 'pages' => ['per_page' => 3], 'pages by menu order' => ['orderby' => 'menu_order', 'order' => 'asc']] as $label => $query) {
    $say($label, $send(str_starts_with($label, 'pages') ? '/wp/v2/pages' : '/wp/v2/posts', $query));
}
$say('a HEAD request', $send('/wp/v2/zz-lists', ['per_page' => 2], 'HEAD'));
$say('posts, included', $send('/wp/v2/posts', ['include' => '11']));
$say('posts, included, stickies kept', $send('/wp/v2/posts', ['include' => [11], 'ignore_sticky' => false]));

wp_set_current_user(1);
foreach (['default' => [], 'drafts' => ['status' => 'draft'], 'any status' => ['status' => ['publish', 'private', 'draft']], 'edit context' => ['context' => 'edit', 'per_page' => 3], 'a search' => ['search' => 'alpha']] as $label => $query) {
    $say("as an administrator: {$label}", $send('/wp/v2/zz-lists', $query));
}
wp_set_current_user(0);

// A plugin's say in the list: its query filter, pre_get_posts, and a query var filter.
$with = static function (string $label, string $hook, callable $callback, array $query = []) use ($say, $send): void {
    add_filter($hook, $callback, 10, 2);
    $say($label, $send('/wp/v2/zz-lists', $query));
    remove_filter($hook, $callback, 10);
};
$with('rest query args changed', 'rest_zz_list_query', static fn ($args) => ['orderby' => 'title', 'order' => 'ASC', 'post_parent' => 0] + $args);
$with('pre_get_posts changes the page size', 'pre_get_posts', static function ($query) {
    $query->set('posts_per_page', 1);
});
$with('a query var filtered', 'rest_query_var-posts_per_page', static fn () => 2);
$with('the posts trimmed', 'the_posts', static fn ($posts) => array_slice($posts, 0, 1));

foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach (array_reverse($made['terms']) as $id) {
    wp_delete_term($id, 'zz_lt');
}
unregister_post_type('zz_list');
unregister_taxonomy('zz_lt');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
