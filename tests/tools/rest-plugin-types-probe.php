<?php
/**
 * A plugin's post types and taxonomies over REST, as the reference serves
 * them with its default controllers (probe rest-plugin-types): a type with
 * two taxonomies (one hierarchical under its own REST base, one flat) and
 * registered meta; a hierarchical type without an editor; a type kept out
 * of REST and one in its own namespace (not served under wp/v2; the engine
 * does not serve other namespaces' type routes yet). Lists, single posts in each
 * context, a create with terms and meta, an update, filtering by term,
 * trashing and deleting; the taxonomies' terms the same way. Same protocol
 * as api-probe.php; dispatched in process as an administrator (and a
 * visitor); everything it makes goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_taxonomy('zz_genre', ['zz_book'], ['hierarchical' => true, 'show_in_rest' => true, 'rest_base' => 'zz-genres', 'labels' => ['name' => 'Zz Genres', 'singular_name' => 'Zz Genre']]);
register_taxonomy('zz_mood', ['zz_book'], ['show_in_rest' => true, 'labels' => ['name' => 'Zz Moods']]);
register_post_type('zz_book', [
    'public' => true,
    'show_in_rest' => true,
    'rest_base' => 'zz-books',
    'label' => 'Zz Books',
    'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'comments', 'author', 'revisions'],
    'taxonomies' => ['zz_genre', 'zz_mood'],
]);
register_post_meta('zz_book', 'zz_rating', ['show_in_rest' => true, 'single' => true, 'type' => 'integer', 'default' => 0]);
register_post_type('zz_note', ['public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'label' => 'Zz Notes', 'supports' => ['title', 'page-attributes']]);
register_post_type('zz_hidden', ['public' => true, 'show_in_rest' => false, 'label' => 'Zz Hidden']);
register_post_type('zz_elsewhere', ['public' => true, 'show_in_rest' => true, 'rest_namespace' => 'zz/v1', 'label' => 'Zz Elsewhere']);
if (did_action('rest_api_init')) {
    // Routes for types registered after the server started come with the next server.
    $GLOBALS['wp_rest_server'] = null;
}

$made = ['posts' => [], 'terms' => []];
$ids = [];
$home = home_url();
$mask = static function ($value) use (&$ids, $home) {
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $json = str_replace([$home, addcslashes($home, '/')], '{home}', $json);
    $json = (string) preg_replace('/"(date|date_gmt|modified|modified_gmt)":"[0-9T:-]+"/', '"$1":"{date}"', $json);
    // Revisions' ids differ between installs.
    $json = (string) preg_replace(['#/revisions/\d+#', '/"predecessor-version":\[\{"id":\d+/'], ['/revisions/{revision}', '"predecessor-version":[{"id":"{revision}"'], $json);
    foreach ($ids as $name => $id) {
        if ($id > 0) {
            // A bare number becomes a string; inside a string (an address) the id is replaced where it stands.
            $json = (string) preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{' . $name . '}"', $json);
            $json = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $json);
        }
    }
    return json_decode($json, true) ?? ['undecodable' => substr($json, 0, 400)];
};
$send = static function (string $method, string $route, array $query = [], ?array $body = null) use ($mask): array {
    $request = new WP_REST_Request($method, $route);
    $request->set_query_params($query);
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    return $mask(['status' => $response->get_status(), 'data' => rest_get_server()->response_to_data($response, false)]);
};
wp_set_current_user(1);

// Terms first, through their routes.
$genre = $send('POST', '/wp/v2/zz-genres', [], ['name' => 'Zz Fiction', 'description' => 'Stories']);
$ids['genre'] = (int) ($genre['data']['id'] ?? 0);
$made['terms'][] = [$ids['genre'], 'zz_genre'];
$child = $send('POST', '/wp/v2/zz-genres', [], ['name' => 'Zz Mystery', 'parent' => $ids['genre']]);
$ids['child'] = (int) ($child['data']['id'] ?? 0);
$made['terms'][] = [$ids['child'], 'zz_genre'];
$mood = $send('POST', '/wp/v2/zz_mood', [], ['name' => 'Zz Calm']);
$ids['mood'] = (int) ($mood['data']['id'] ?? 0);
$made['terms'][] = [$ids['mood'], 'zz_mood'];
$say('create genre', $mask($genre));
$say('create child genre', $mask($child));
$say('create mood', $mask($mood));

// A post of the type, with its terms and meta.
$created = $send('POST', '/wp/v2/zz-books', [], ['title' => 'Zz Book One', 'content' => '<p>Zz body</p>', 'excerpt' => 'Zz short', 'status' => 'publish', 'zz-genres' => [$ids['child']], 'zz_mood' => [$ids['mood']], 'meta' => ['zz_rating' => 4], 'comment_status' => 'closed']);
$ids['book'] = (int) ($created['data']['id'] ?? 0);
$made['posts'][] = $ids['book'];
$say('create book', $mask($created));
$say('list books', $send('GET', '/wp/v2/zz-books'));
$say('single view', $send('GET', '/wp/v2/zz-books/' . $ids['book']));
$say('single edit', $send('GET', '/wp/v2/zz-books/' . $ids['book'], ['context' => 'edit']));
$say('single embed', $send('GET', '/wp/v2/zz-books/' . $ids['book'], ['context' => 'embed']));
$say('update book', $send('POST', '/wp/v2/zz-books/' . $ids['book'], [], ['title' => 'Zz Book Renamed', 'zz_mood' => [], 'meta' => ['zz_rating' => 5]]));
$say('filter by genre', array_column($send('GET', '/wp/v2/zz-books', ['zz-genres' => $ids['child']])['data'] ?? [], 'id'));
$say('filter by another genre', $send('GET', '/wp/v2/zz-books', ['zz-genres' => $ids['genre']])['data']);
$say('terms of the book', $send('GET', '/wp/v2/zz-genres', ['post' => $ids['book']]));

// The taxonomies' own routes.
$say('genres', $send('GET', '/wp/v2/zz-genres'));
$say('one genre edit', $send('GET', '/wp/v2/zz-genres/' . $ids['child'], ['context' => 'edit']));
$say('update genre', $send('POST', '/wp/v2/zz-genres/' . $ids['child'], [], ['description' => 'Whodunits']));
$say('moods', $send('GET', '/wp/v2/zz_mood'));

// A hierarchical type without an editor; types kept out or elsewhere.
$note = $send('POST', '/wp/v2/zz_note', [], ['title' => 'Zz Note', 'status' => 'publish', 'menu_order' => 2, 'content' => 'ignored']);
$ids['note'] = (int) ($note['data']['id'] ?? 0);
$made['posts'][] = $ids['note'];
$say('create note', $mask($note));
$say('hidden type', $send('GET', '/wp/v2/zz_hidden'));
$say('elsewhere under wp/v2', $send('GET', '/wp/v2/zz_elsewhere'));

// A visitor, and the way out.
wp_set_current_user(0);
$say('visitor list', array_column($send('GET', '/wp/v2/zz-books')['data'] ?? [], 'id'));
$say('visitor create', $send('POST', '/wp/v2/zz-books', [], ['title' => 'Zz Nope']));
wp_set_current_user(1);
$say('trash book', $send('DELETE', '/wp/v2/zz-books/' . $ids['book']));
$say('delete book', $send('DELETE', '/wp/v2/zz-books/' . $ids['book'], ['force' => 'true']));
$say('delete genre', $send('DELETE', '/wp/v2/zz-genres/' . $ids['child'], ['force' => 'true']));
$say('delete genre without force', $send('DELETE', '/wp/v2/zz-genres/' . $ids['genre']));

wp_set_current_user(0);
foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
    wp_delete_term($id, $taxonomy);
}
foreach (['zz_book', 'zz_note', 'zz_hidden', 'zz_elsewhere'] as $type) {
    unregister_post_type($type);
}
foreach (['zz_genre', 'zz_mood'] as $taxonomy) {
    unregister_taxonomy($taxonomy);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
