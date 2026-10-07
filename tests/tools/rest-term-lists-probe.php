<?php
/**
 * REST term collections as the reference serves them (probe
 * rest-term-lists): for each list request, the WP_Term_Query arguments
 * rest_{taxonomy}_query is handed, the clauses each term query ran (the
 * list, then the count), which term query filters fired, the ids that came
 * back, the totals, the Link header and the status. A plugin's
 * hierarchical taxonomy and a flat one; paging, offsets, search, include
 * and exclude, every orderby, empty terms, parents, a post's terms, slugs,
 * the edit context, a HEAD request, a plugin's rest_{taxonomy}_query and
 * terms_clauses, and the collection parameters each list declares
 * (categories and tags too). Each case starts after a bump of the terms
 * last_changed, so none is answered from the reference's query cache.
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
register_taxonomy('zz_rtl', ['post'], ['hierarchical' => true, 'show_in_rest' => true, 'rest_base' => 'zz-rtls']);
register_taxonomy('zz_rtf', ['post'], ['hierarchical' => false, 'show_in_rest' => true, 'rest_base' => 'zz-rtfs']);
if (did_action('rest_api_init')) {
    $GLOBALS['wp_rest_server'] = null;
}

$ids = [];
foreach (['red' => [0, 'warm', 2], 'pink' => ['red', 'soft', 0], 'blue' => [0, 'cool', 1], 'gray' => [0, '', 0]] as $slug => [$parent, $description, $group]) {
    $term = wp_insert_term('Zz Rtl ' . ucfirst($slug), 'zz_rtl', ['slug' => "zz-rtl-{$slug}", 'parent' => $parent === 0 ? 0 : $ids[$parent], 'description' => $description]);
    $ids[$slug] = (int) ($term['term_id'] ?? 0);
    if ($group > 0) {
        global $wpdb;
        $wpdb->update($wpdb->terms, ['term_group' => $group], ['term_id' => $ids[$slug]]);
        clean_term_cache($ids[$slug], 'zz_rtl');
    }
}
foreach (['one', 'two'] as $slug) {
    $term = wp_insert_term('Zz Rtf ' . ucfirst($slug), 'zz_rtf', ['slug' => "zz-rtf-{$slug}"]);
    $ids["flat {$slug}"] = (int) ($term['term_id'] ?? 0);
}
$ids['post'] = (int) wp_insert_post(['post_title' => 'Zz Rtl Post', 'post_name' => 'zz-rtl-post', 'post_status' => 'publish', 'post_author' => 1]);
$ids['other post'] = (int) wp_insert_post(['post_title' => 'Zz Rtl Other', 'post_name' => 'zz-rtl-other', 'post_status' => 'publish', 'post_author' => 1]);
wp_set_object_terms($ids['post'], [$ids['red'], $ids['pink']], 'zz_rtl');
wp_set_object_terms($ids['other post'], [$ids['red'], $ids['blue']], 'zz_rtl');
wp_set_object_terms($ids['post'], [$ids['flat one']], 'zz_rtf');

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
    if (preg_match('/^(get_terms_defaults|get_terms_args|parse_term_query|pre_get_terms|terms_pre_query|list_terms_exclusions|get_terms_orderby|terms_clauses|get_terms_fields|get_terms|wp_get_object_terms_args|get_object_terms|wp_get_object_terms|rest_zz_rt[lf]_query|rest_zz_rt[lf]_collection_params)/', $hook)) {
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
        $runs[] = array_intersect_key((array) $clauses, array_flip(['fields', 'join', 'where', 'orderby', 'order', 'limits']));
        return $clauses;
    };
    add_filter('rest_zz_rtl_query', $grabArgs);
    add_filter('rest_zz_rtf_query', $grabArgs);
    add_filter('terms_clauses', $grabRun);
    wp_cache_set_last_changed('terms');
    $fired = [];
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    $response = rest_do_request($request);
    remove_filter('rest_zz_rtl_query', $grabArgs);
    remove_filter('rest_zz_rtf_query', $grabArgs);
    remove_filter('terms_clauses', $grabRun);
    $data = rest_get_server()->response_to_data($response, false);
    $headers = $response->get_headers();
    return $mask([
        'status' => $response->get_status(),
        'args' => $args,
        'runs' => $runs,
        'filters' => array_values(array_unique($fired)),
        'ids' => is_array($data) && array_is_list($data) ? array_map(static fn ($item) => $item['id'] ?? $item, $data) : ($data['code'] ?? $data),
        'message' => is_array($data) && isset($data['code']) ? ($data['message'] ?? null) : null,
        'total' => [$headers['X-WP-Total'] ?? null, $headers['X-WP-TotalPages'] ?? null],
        'link' => $headers['Link'] ?? null,
    ]);
};

// The collection parameters each list takes, as its OPTIONS answer describes them.
foreach (['/wp/v2/zz-rtls', '/wp/v2/zz-rtfs', '/wp/v2/categories', '/wp/v2/tags'] as $route) {
    $data = rest_get_server()->response_to_data(rest_do_request(new WP_REST_Request('OPTIONS', $route)), false);
    foreach ((array) ($data['endpoints'] ?? []) as $endpoint) {
        if (in_array('GET', (array) ($endpoint['methods'] ?? []), true)) {
            $say("params: {$route}", $endpoint['args'] ?? []);
        }
    }
}

$cases = [
    'a plain list' => [],
    'two per page, page two' => ['per_page' => 2, 'page' => 2],
    'a page past the end' => ['page' => 9],
    'an offset' => ['offset' => 1, 'per_page' => 2],
    'a search' => ['search' => 'pin'],
    'in the order included' => ['include' => [$ids['blue'], $ids['red']], 'orderby' => 'include'],
    'excluded' => ['exclude' => [$ids['red']]],
    'newest name first' => ['order' => 'desc'],
    'by id' => ['orderby' => 'id'],
    'by slug' => ['orderby' => 'slug', 'order' => 'desc'],
    'by slug list' => ['slug' => ['zz-rtl-blue', 'zz-rtl-red'], 'orderby' => 'include_slugs'],
    'by group' => ['orderby' => 'term_group'],
    'by description' => ['orderby' => 'description'],
    'by count' => ['orderby' => 'count', 'order' => 'desc'],
    'not the empty ones' => ['hide_empty' => true],
    'top level' => ['parent' => 0],
    'children of red' => ['parent' => $ids['red']],
    'a post\'s' => ['post' => $ids['post']],
    'a post that is not there' => ['post' => 999999],
    'edit context, signed out' => ['context' => 'edit'],
];
foreach ($cases as $label => $query) {
    $say($label, $send('/wp/v2/zz-rtls', $query));
}
$say('flat: a plain list', $send('/wp/v2/zz-rtfs'));
$say('flat: a post\'s', $send('/wp/v2/zz-rtfs', ['post' => $ids['post']]));
$say('flat: a parent asked of a flat taxonomy', $send('/wp/v2/zz-rtfs', ['parent' => 0]));
$say('a HEAD request', $send('/wp/v2/zz-rtls', ['per_page' => 2], 'HEAD'));

wp_set_current_user(1);
$say('as an administrator: edit context', $send('/wp/v2/zz-rtls', ['context' => 'edit', 'per_page' => 2]));
wp_set_current_user(0);

// A plugin's say in the list: the REST query filter, the clauses.
$with = static function (string $label, string $hook, callable $callback, array $query = []) use ($say, $send): void {
    add_filter($hook, $callback);
    $say($label, $send('/wp/v2/zz-rtls', $query));
    remove_filter($hook, $callback);
};
$with('rest_zz_rtl_query limits the list', 'rest_zz_rtl_query', static fn ($args) => ['number' => 1] + $args);
$with('terms_clauses leaves one out', 'terms_clauses', static fn ($c) => ['where' => $c['where'] . " AND t.slug != 'zz-rtl-blue'"] + $c);

foreach (['post', 'other post'] as $name) {
    wp_delete_post($ids[$name], true);
}
foreach (['pink', 'red', 'blue', 'gray'] as $slug) {
    wp_delete_term($ids[$slug], 'zz_rtl');
}
foreach (['one', 'two'] as $slug) {
    wp_delete_term($ids["flat {$slug}"], 'zz_rtf');
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
