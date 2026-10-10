<?php
/**
 * The REST search handlers (probe rest-search-handlers): the post, term and
 * post-format handlers' type and subtypes, the ids and total each finds for
 * a search (a word only the probe's content holds, subtypes narrowed,
 * paged, include and exclude), each item prepared with chosen fields, its
 * links, and the post handler's protected title format and item route. The
 * probe's posts and terms are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[is_int($key) && isset($ids[$key]) ? '{' . $ids[$key] . '}' : $key] = $mask($item);
        }
        return $out;
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code()];
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x']);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "rest-search-handlers: {$title} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $result = wp_insert_term($name, $taxonomy);
    if (!is_array($result)) {
        fwrite(STDERR, "rest-search-handlers: {$name} could not be made\n");
        exit(1);
    }
    $id = (int) $result['term_id'];
    $made['terms'][] = [$id, $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
// A section that throws is recorded, not fatal: a fatal under WP-CLI skips the cleanup.
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (array $params) {
    $request = new WP_REST_Request('GET', '/wp/v2/search');
    foreach ($params + ['page' => 1, 'per_page' => 10, 'subtype' => ['any']] as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};

$older = $post('zzsrchprobe older', ['post_date' => '1977-01-01 10:00:00']);
$newer = $post('zzsrchprobe newer', ['post_date' => '1977-02-01 10:00:00']);
$page = $post('zzsrchprobe page', ['post_type' => 'page', 'post_date' => '1977-03-01 10:00:00']);
$locked = $post('zzsrchprobe locked', ['post_password' => 'zz', 'post_date' => '1977-04-01 10:00:00']);
$post('zzsrchprobe draft', ['post_status' => 'draft']);
$cat = $term('zzsrchprobe cat', 'category');
$tag = $term('zzsrchprobe tag', 'post_tag');

$handlers = ['post' => new WP_REST_Post_Search_Handler(), 'term' => new WP_REST_Term_Search_Handler(), 'post-format' => new WP_REST_Post_Format_Search_Handler()];
$handlers = $try(static fn () => ['post' => new WP_REST_Post_Search_Handler(), 'term' => new WP_REST_Term_Search_Handler(), 'post-format' => new WP_REST_Post_Format_Search_Handler()]);
if (isset($handlers['threw'])) {
    $say('the handlers', $handlers);
    echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    return;
}
$say('types and subtypes', $try(static fn () => array_map(static fn ($handler) => [$handler->get_type(), $handler->get_subtypes()], $handlers)));
$say('the result keys', [WP_REST_Search_Handler::RESULT_IDS, WP_REST_Search_Handler::RESULT_TOTAL]);

$found = [];
foreach ([
    'posts by a word' => ['post', ['search' => 'zzsrchprobe']],
    'posts, pages only' => ['post', ['search' => 'zzsrchprobe', 'subtype' => ['page']]],
    'posts, paged' => ['post', ['search' => 'zzsrchprobe', 'per_page' => 2, 'page' => 2]],
    'posts, one excluded' => ['post', ['search' => 'zzsrchprobe', 'exclude' => [$newer]]],
    'posts, one included' => ['post', ['search' => 'zzsrchprobe', 'include' => [$older]]],
    'terms by a word' => ['term', ['search' => 'zzsrchprobe']],
    'terms, tags only' => ['term', ['search' => 'zzsrchprobe', 'subtype' => ['post_tag']]],
    'terms, one excluded' => ['term', ['search' => 'zzsrchprobe', 'exclude' => [$cat]]],
    'post formats by a word' => ['post-format', ['search' => 'gal']],
    'post formats, all' => ['post-format', ['search' => '']],
    'post formats, paged' => ['post-format', ['search' => '', 'per_page' => 3, 'page' => 2]],
] as $label => [$which, $params]) {
    $found[$label] = $mask($try(static fn () => $handlers[$which]->search_items($request($params))));
}
$say('ids and totals found', $found);

$fields = ['id', 'title', 'url', 'type', 'subtype'];
$say('items prepared', $mask($try(static fn () => [
    $handlers['post']->prepare_item($newer, $fields), $handlers['post']->prepare_item($page, ['id', 'title']), $handlers['post']->prepare_item($locked, $fields), $handlers['post']->prepare_item($newer, []),
    $handlers['term']->prepare_item($cat, $fields), $handlers['term']->prepare_item($tag, ['title', 'url']),
    $handlers['post-format']->prepare_item('gallery', $fields), $handlers['post-format']->prepare_item('aside', ['title']),
])));
$say('item links', $mask($try(static fn () => [
    $handlers['post']->prepare_item_links($newer), $handlers['post']->prepare_item_links($page),
    $handlers['term']->prepare_item_links($cat), $handlers['term']->prepare_item_links($tag),
    $handlers['post-format']->prepare_item_links('gallery'),
])));

$protected = Closure::bind(fn ($post) => $this->detect_rest_item_route($post), $handlers['post'], WP_REST_Post_Search_Handler::class);
$say('the post handler helpers', $mask($try(static fn () => [$handlers['post']->protected_title_format(), $protected(get_post($newer)), $protected(get_post($page))])));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
