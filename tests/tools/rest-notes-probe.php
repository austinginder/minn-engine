<?php
/**
 * Notes and comments created over REST (POST /wp/v2/comments), as the
 * reference makes them: a note needs edit_post on its post and is made
 * whether comments are closed or the post a draft, typed "note", approved,
 * left out of default lists and the post's count; an ordinary comment on a
 * draft is refused whoever asks, on another post not yet public by who may
 * not read it. Same protocol as api-probe.php; the probe's rows (zzrn) are
 * removed after.
 */

require_once ABSPATH . 'wp-admin/includes/user.php';
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => []];
$create = static function (int $post, ?string $type, string $content): array {
    $request = new WP_REST_Request('POST', '/wp/v2/comments');
    $request->set_body_params(['post' => $post, 'content' => $content] + ($type === null ? [] : ['type' => $type]));
    $response = rest_do_request($request);
    $data = $response->get_data();
    return [$response->get_status(), $data['code'] ?? ($data['type'] ?? null), $data['id'] ?? null];
};
try {
    $subscriber = $made['users'][] = wp_insert_user(['user_login' => 'zzrn_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'zzrn_sub@example.com', 'role' => 'subscriber']);
    $author = $made['users'][] = wp_insert_user(['user_login' => 'zzrn_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zzrn_author@example.com', 'role' => 'author']);
    $open = $made['posts'][] = wp_insert_post(['post_title' => 'zzrn open', 'post_status' => 'publish', 'post_author' => 1, 'comment_status' => 'open']);
    $ownClosed = $made['posts'][] = wp_insert_post(['post_title' => 'zzrn own closed', 'post_status' => 'publish', 'post_author' => $author, 'comment_status' => 'closed']);
    $draft = $made['posts'][] = wp_insert_post(['post_title' => 'zzrn draft', 'post_status' => 'draft', 'post_author' => 1, 'comment_status' => 'closed']);

    $notes = [];
    foreach (['a subscriber on an open post' => [$subscriber, $open], 'an author on their own closed post' => [$author, $ownClosed], 'an author on another\'s post' => [$author, $open], 'an administrator on a closed draft' => [1, $draft]] as $label => [$user, $post]) {
        wp_set_current_user($user);
        $notes[$label] = array_slice($create($post, 'note', "zzrn note {$label}"), 0, 2);
    }
    $say('notes, by who and where', $notes);

    wp_set_current_user(1);
    [, , $id] = $create($draft, 'note', 'zzrn kept note');
    $stored = get_comment((int) $id);
    $listed = rest_do_request(new WP_REST_Request('GET', '/wp/v2/comments'));
    $asNotes = new WP_REST_Request('GET', '/wp/v2/comments');
    $asNotes->set_query_params(['type' => 'note', 'post' => $draft]);
    $say('a note as stored and listed', [$stored->comment_type, $stored->comment_approved, (int) get_post($draft)->comment_count, in_array((int) $id, array_column($listed->get_data(), 'id'), true), count(rest_do_request($asNotes)->get_data())]);

    $statuses = [];
    foreach (['pending', 'private', 'future', 'draft'] as $status) {
        $made['posts'][] = $post = wp_insert_post(['post_title' => 'zzrn ' . $status, 'post_status' => $status, 'post_author' => 1, 'comment_status' => 'open', 'post_date' => $status === 'future' ? '2035-01-01 00:00:00' : '']);
        foreach (['an administrator' => 1, 'a subscriber' => $subscriber] as $who => $user) {
            wp_set_current_user($user);
            $statuses["{$who} on a {$status} post"] = array_slice($create($post, null, "zzrn {$who} {$status}"), 0, 2);
        }
    }
    $say('ordinary comments on posts not yet public', $statuses);
} finally {
    wp_set_current_user(0);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 3) {
            wp_delete_user($id);
        }
    }
}
echo json_encode($log);
