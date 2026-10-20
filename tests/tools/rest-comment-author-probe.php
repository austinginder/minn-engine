<?php
/**
 * Who a REST comment is by, and what its arguments are refused for:
 * an author named by id (their name, address and site, unless the author
 * fields say otherwise), one who does not exist or is 0, the name and
 * address required while require_name_email is on, the same on an edit;
 * content that is not text; the type and format refusals, which come in
 * one round, the author's address after them in a round of its own, and a
 * meta that is not an object refused bare. Dispatched in process as an
 * administrator, then as the author user and signed out. Same protocol as
 * api-probe.php; the comments it makes go at once, its post at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$sweep = static function (): void {
    foreach (get_posts(['post_type' => 'post', 'post_status' => 'any', 'title' => 'zz rest comment author post', 'fields' => 'ids', 'posts_per_page' => -1]) as $id) {
        wp_delete_post((int) $id, true);
    }
};
$sweep();
register_shutdown_function($sweep);
wp_set_current_user(1);
$postId = (int) wp_insert_post(['post_title' => 'zz rest comment author post', 'post_content' => 'Body', 'post_status' => 'publish', 'comment_status' => 'open']);
$serial = 0;
$ask = static function (array $params, string $route = '/wp/v2/comments') use (&$serial, $postId): array {
    $request = new WP_REST_Request('POST', $route);
    $params = array_map(static fn ($value) => $value === '{post}' ? $postId : $value, $params);
    if (($params['content'] ?? null) === '{unique}') {
        $params['content'] = 'zz rest comment author ' . ++$serial;
    }
    $request->set_body_params($params);
    $response = rest_do_request($request);
    $data = $response->get_data();
    if ($response->get_status() >= 300) {
        return [$response->get_status(), $data['code'] ?? null, $data['message'] ?? null, $data['data']['params'] ?? null, $data['data']['details'] ?? null];
    }
    $comment = get_comment((int) $data['id']);
    $shown = [$response->get_status(), $comment->comment_author, $comment->comment_author_email, $comment->comment_author_url, (int) $comment->user_id];
    if (!str_starts_with($route, '/wp/v2/comments/')) {
        wp_delete_comment((int) $comment->comment_ID, true);
    }
    return $shown;
};

$say('an author named by id: their name, address and site', $ask(['post' => '{post}', 'author' => 2, 'content' => '{unique}']));
$say('an author named by id, with author fields of its own', $ask(['post' => '{post}', 'author' => 2, 'author_name' => 'Given', 'author_email' => 'given@example.com', 'author_url' => 'https://given.example', 'content' => '{unique}']));
$say('an author named by id, with only an address of its own', $ask(['post' => '{post}', 'author' => 3, 'author_email' => 'x@example.com', 'content' => '{unique}']));
$say('an author who does not exist', $ask(['post' => '{post}', 'author' => 999999, 'content' => '{unique}']));
$say('author 0', $ask(['post' => '{post}', 'author' => 0, 'author_name' => 'Anon', 'author_email' => 'anon@example.com', 'content' => '{unique}']));
$say('an author who does not exist, on a post that does not either', $ask(['post' => 999999, 'author' => 999999, 'content' => '{unique}']));
$say('an author who does not exist, with no content', $ask(['post' => '{post}', 'author' => 999999, 'content' => '']));
$say('no author: the user signed in', $ask(['post' => '{post}', 'content' => '{unique}']));
$say('a name and no address', $ask(['post' => '{post}', 'author_name' => 'Named', 'content' => '{unique}']));
$say('an address and no name', $ask(['post' => '{post}', 'author_email' => 'only@example.com', 'content' => '{unique}']));
$say('a site and nothing else', $ask(['post' => '{post}', 'author_url' => 'https://u.example', 'content' => '{unique}']));
$say('a name and an address', $ask(['post' => '{post}', 'author_name' => 'Named', 'author_email' => 'named@example.com', 'content' => '{unique}']));
$say('an empty address', $ask(['post' => '{post}', 'author_email' => '', 'author_name' => 'Named', 'content' => '{unique}']));
$say('a name and no address, with no content', $ask(['post' => '{post}', 'author_name' => 'Named', 'content' => '']));
$required = get_option('require_name_email');
update_option('require_name_email', '');
$say('a name and no address, while the site asks for neither', $ask(['post' => '{post}', 'author_name' => 'Named', 'content' => '{unique}']));
update_option('require_name_email', $required);
$say('content given raw', $ask(['post' => '{post}', 'content' => ['raw' => 'zz rest comment author raw']]));
$say('content that is a number', $ask(['post' => '{post}', 'content' => 5]));

$say('refused: an address that is not one', $ask(['post' => '{post}', 'author_name' => 'N', 'author_email' => 'nope', 'content' => '{unique}']));
$say('refused: an author that is not a number', $ask(['post' => '{post}', 'author' => 'abc', 'content' => '{unique}']));
$say('refused: a date that is not one', $ask(['post' => '{post}', 'date' => 'x', 'content' => '{unique}']));
$say('refused: an IP that is not one', $ask(['post' => '{post}', 'author_ip' => 'x', 'content' => '{unique}']));
$say('refused: a site that is not one (no refusal; the address is still asked for)', $ask(['post' => '{post}', 'author_url' => 'not a url', 'content' => '{unique}']));
$say('refused: a meta that is not an object', $ask(['post' => '{post}', 'meta' => 'x', 'content' => '{unique}']));
$say('refused: a parent that is not a number', $ask(['post' => '{post}', 'parent' => 'abc', 'content' => '{unique}']));
$say('refused: a status that is not text', $ask(['post' => '{post}', 'status' => ['x'], 'content' => '{unique}']));
$say('refused: a post that is not a number and an address that is not one', $ask(['post' => 'p', 'author_email' => 'nope', 'content' => '{unique}']));
$say('refused: an IP and an address', $ask(['post' => '{post}', 'author_ip' => 'x', 'author_email' => 'nope']));
$say('refused: an IP and a post', $ask(['author_ip' => 'x', 'post' => 'p']));
$say('refused: a meta and a post', $ask(['post' => 'p', 'meta' => 'x', 'content' => '{unique}']));
$say('refused: a meta and an address', $ask(['post' => '{post}', 'meta' => 'x', 'author_email' => 'nope', 'content' => '{unique}']));

$commentId = (int) wp_insert_comment(['comment_post_ID' => $postId, 'comment_content' => 'zz rest comment author edited', 'comment_approved' => '1', 'user_id' => 1, 'comment_author' => 'admin', 'comment_author_email' => 'admin@example.com']);
$say('an edit naming an author by id', $ask(['author' => 2], "/wp/v2/comments/{$commentId}"));
$say('an edit naming an author who does not exist', $ask(['author' => 999999], "/wp/v2/comments/{$commentId}"));
$say('an edit naming author 0', $ask(['author' => 0], "/wp/v2/comments/{$commentId}"));
$say('an edit naming only a name', $ask(['author_name' => 'Renamed'], "/wp/v2/comments/{$commentId}"));
$say('an edit naming an address that is not one', $ask(['author_email' => 'nope'], "/wp/v2/comments/{$commentId}"));
wp_delete_comment($commentId, true);

wp_set_current_user(3);
$say('the author user: a name and no address', $ask(['post' => '{post}', 'author_name' => 'Named', 'content' => '{unique}']));
$say('the author user: naming themself', $ask(['post' => '{post}', 'author' => 3, 'content' => '{unique}']));
wp_set_current_user(0);
$say('signed out: an address that is not one', $ask(['post' => '{post}', 'author_email' => 'nope', 'content' => '{unique}']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
