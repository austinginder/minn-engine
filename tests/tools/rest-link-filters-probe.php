<?php
/**
 * REST link fields through the link filters (probe rest-link-filters, audit
 * 2026-10-12): a plugin that filters post_link, page_link or post_type_link
 * (a translation plugin adding its language, say) sees its links in the
 * REST answers too, as get_permalink() gives them: a post, a page, a
 * plugin's type, a list, and a comment's link on its post. The rows the
 * probe makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = rtrim(home_url(), '/');
$mask = static fn ($value) => is_string($value) ? str_replace($home, '{home}', (string) preg_replace(['/([?&](?:p|page_id|zz_link_type)=)\d+/', '/#comment-\d+/'], ['$1{id}', '#comment-{id}'], $value)) : $value;

register_post_type('zz_link_type', ['public' => true, 'show_in_rest' => true, 'label' => 'ZZ Link', 'rewrite' => ['slug' => 'zz-link']]);
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as [$kind, $id]) {
        if (is_int($id) && $id > 1) {
            $kind === 'comment' ? wp_delete_comment($id, true) : wp_delete_post($id, true);
        }
    }
});
$page = wp_insert_post(['post_type' => 'page', 'post_title' => 'ZZ link page', 'post_name' => 'zz-link-page', 'post_status' => 'publish']);
$typed = wp_insert_post(['post_type' => 'zz_link_type', 'post_title' => 'ZZ link typed', 'post_name' => 'zz-link-typed', 'post_status' => 'publish']);
$comment = wp_insert_comment(['comment_post_ID' => 1, 'comment_content' => 'zz link comment', 'comment_approved' => '1', 'comment_author' => 'zz', 'comment_author_email' => 'zz-link@example.com']);
foreach ([['post', $page], ['post', $typed], ['comment', $comment]] as [$kind, $id]) {
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "could not make the probe's {$kind}\n");
        exit(1);
    }
    $made[] = [$kind, $id];
}

$get = static function (string $route, array $query = []) use ($mask): array {
    $request = new WP_REST_Request('GET', $route);
    $request->set_query_params($query);
    $data = rest_do_request($request)->get_data();
    $rows = is_array($data) && array_is_list($data) ? $data : [$data];
    return array_map(static fn ($row) => $mask(is_array($row) ? ($row['link'] ?? null) : null), $rows);
};
$read = static fn () => [
    'post' => $get('/wp/v2/posts/1'),
    'page' => $get('/wp/v2/pages/' . $page),
    'typed' => $get('/wp/v2/zz_link_type/' . $typed),
    'list' => $get('/wp/v2/posts', ['include' => [1]]),
    'comment' => $get('/wp/v2/comments/' . $comment),
];

$say('without filters', $read());
add_filter('post_link', static fn ($link) => add_query_arg('lang', 'fr', $link));
add_filter('page_link', static fn ($link) => add_query_arg('lang', 'de', $link));
add_filter('post_type_link', static fn ($link, $post) => $post->post_type === 'zz_link_type' ? add_query_arg('lang', 'es', $link) : $link, 10, 2);
$say('with the link filters', $read());

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
