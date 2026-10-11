<?php
/**
 * The trash emptied on schedule (probe scheduled-delete): posts and
 * comments the probe trashes long ago and lately, a live post and comment
 * left with a stale trash time, a trashed post with none, and one whose
 * deleting a filter refuses, then wp_scheduled_delete() run. What is gone,
 * what is kept, and which meta keys are left. Whatever the probe made and is still there is removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$made = ['posts' => [], 'comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 1 && get_comment($id)) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1 && get_post($id)) {
            wp_delete_post($id, true);
        }
    }
});
$old = time() - 31 * DAY_IN_SECONDS;
$recent = time() - DAY_IN_SECONDS;
$post = static function (string $title, string $status, ?int $trashed) use (&$made) {
    $id = wp_insert_post(['post_title' => $title, 'post_status' => $status, 'post_content' => 'zz']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['posts'][] = $id;
    if ($trashed !== null) {
        update_post_meta($id, '_wp_trash_meta_time', $trashed);
        update_post_meta($id, '_wp_trash_meta_status', 'publish');
    }
    return $id;
};
$posts = [
    'trashed long ago' => $post('ZZ sd old', 'trash', $old),
    'trashed lately' => $post('ZZ sd recent', 'trash', $recent),
    'live, a stale trash time' => $post('ZZ sd live', 'publish', $old),
    'trashed, no trash time' => $post('ZZ sd untimed', 'trash', null),
    'trashed long ago, deleting refused' => $post('ZZ sd refused', 'trash', $old),
];
update_post_meta($posts['live, a stale trash time'], '_wp_trash_meta_comments_status', ['1' => '1']);
update_post_meta($posts['live, a stale trash time'], '_wp_desired_post_slug', 'zz-sd-live');
update_post_meta($posts['live, a stale trash time'], 'zz_sd_kept', 'yes');
$host = $posts['live, a stale trash time'];
$comment = static function (string $text, string $approved, ?int $trashed) use (&$made, $host) {
    $id = wp_insert_comment(['comment_post_ID' => $host, 'comment_content' => $text, 'comment_author' => 'ZZ sd', 'comment_approved' => $approved]);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['comments'][] = $id;
    if ($trashed !== null) {
        update_comment_meta($id, '_wp_trash_meta_time', $trashed);
        update_comment_meta($id, '_wp_trash_meta_status', '1');
    }
    return $id;
};
$comments = [
    'trashed long ago' => $comment('ZZ sd old comment', 'trash', $old),
    'trashed lately' => $comment('ZZ sd recent comment', 'trash', $recent),
    'approved, a stale trash time' => $comment('ZZ sd live comment', '1', $old),
];
$heard = [];
$listener = static function ($id) use (&$heard) {
    $heard[] = current_filter();
};
$refused = $posts['trashed long ago, deleting refused'];
$refuse = static fn ($delete, $post) => $post->ID === $refused ? false : $delete;
add_action('before_delete_post', $listener);
add_action('delete_comment', $listener);
add_filter('pre_delete_post', $refuse, 10, 2);
$returned = wp_scheduled_delete();
remove_filter('pre_delete_post', $refuse, 10);
remove_action('before_delete_post', $listener);
remove_action('delete_comment', $listener);
$state = [];
// The ping queue a publish leaves (_encloseme, _pingme) is left out: Minn sends no pings (an xmlrpc dead end).
foreach ($posts as $label => $id) {
    $row = get_post($id);
    $keys = array_values(array_diff(array_keys((array) get_post_meta($id)), ['_encloseme', '_pingme']));
    sort($keys);
    $state['posts'][$label] = $row ? [$row->post_status, $keys] : 'gone';
}
foreach ($comments as $label => $id) {
    $row = get_comment($id);
    $keys = array_keys((array) get_comment_meta($id));
    sort($keys);
    $state['comments'][$label] = $row ? [$row->comment_approved, $keys] : 'gone';
}
$say('scheduled delete', ['returned' => $returned, 'heard' => array_count_values($heard), 'after' => $state]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
