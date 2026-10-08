<?php
/**
 * comment.php's remaining helpers (probe comment-helpers): the comment
 * cookies made safe, the comments' last-changed stamp, a comment's approval
 * judged on its data (the post's author, a moderator, a stranger held or
 * passed, the disallowed words, pre_comment_approved), old posts closed to
 * comments as the_posts hands them over, the comment-type update run in
 * batches by cron and scheduled from admin_init, inline note markers taken
 * out of rendered blocks, and where each is hooked. The probe's posts,
 * comments and user are removed, and the options and cron event it touches
 * are put back. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => [], 'users' => []];
$options = [];
foreach (['close_comments_for_old_posts', 'close_comments_days_old', 'disallowed_keys', 'finished_updating_comment_type', 'comment_previously_approved', 'wp_notes_notify'] as $name) {
    $options[$name] = get_option($name, null);
}
$batchEvent = wp_next_scheduled('wp_update_comment_type_batch');
register_shutdown_function(static function () use (&$made, $options, $batchEvent): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    if ($batchEvent === false) {
        wp_clear_scheduled_hook('wp_update_comment_type_batch');
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};

$say('hooked', [
    has_filter('the_posts', '_close_comments_for_old_posts'),
    has_action('wp_update_comment_type_batch', '_wp_batch_update_comment_type'),
    has_action('admin_init', '_wp_check_for_scheduled_update_comment_type'),
    has_filter('render_block', 'wp_strip_inline_note_markers'),
]);

// The comment cookies, as a returning commenter's browser sends them.
$cookies = static function (array $set): array {
    $keys = ['comment_author_' . COOKIEHASH, 'comment_author_email_' . COOKIEHASH, 'comment_author_url_' . COOKIEHASH];
    $saved = $_COOKIE;
    foreach ($keys as $i => $key) {
        unset($_COOKIE[$key]);
        if (array_key_exists($i, $set)) {
            $_COOKIE[$key] = $set[$i];
        }
    }
    sanitize_comment_cookies();
    $out = array_map(static fn (string $key) => array_key_exists($key, $_COOKIE) ? $_COOKIE[$key] : '<unset>', $keys);
    $_COOKIE = $saved;
    return $out;
};
$say('comment cookies', [
    $cookies([' <b>Zed</b> & Co ', ' Zed@Example.TEST ', ' https://zz.example/a b ']),
    $cookies(["O\\'Brien", 'not an email', 'javascript:alert(1)']),
    $cookies(['', '', '']),
    $cookies([]),
]);
$heard = [];
foreach (['pre_comment_author_name', 'pre_comment_author_email', 'pre_comment_author_url'] as $hook) {
    add_filter($hook, $listener = static function ($value) use ($hook, &$heard) {
        $heard[] = [$hook, $value];
        return $value . '+';
    });
    $listeners[$hook] = $listener;
}
$say('comment cookies through the pre_comment filters', [$cookies(['Zed', 'zed@example.test', 'https://zz.example/']), $heard]);
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener);
}

// The last-changed stamp.
$before = wp_cache_get('last_changed', 'comment');
usleep(2000);
wp_cache_set_comments_last_changed();
$after = wp_cache_get('last_changed', 'comment');
$say('comments last changed', [is_string($after), $after !== $before, preg_match('/^0\.\d+ \d+$/', (string) $after) === 1]);

// A comment's approval, judged on its data.
$author = wp_insert_user(['user_login' => 'zzcommentauthor', 'user_email' => 'zz-comment-author@example.test', 'user_pass' => wp_generate_password(), 'role' => 'author']);
$made['users'][] = $author;
$ids[$author] = 'author';
$post = wp_insert_post(['post_title' => 'zz comment helpers', 'post_status' => 'publish', 'post_author' => $author, 'post_content' => 'x']);
$made['posts'][] = $post;
$ids[$post] = 'post';
$data = static fn (array $over = []): array => $over + [
    'comment_post_ID' => $post,
    'comment_author' => 'Zed',
    'comment_author_email' => 'zz-commenter@example.test',
    'comment_author_url' => '',
    'comment_author_IP' => '203.0.113.7',
    'comment_agent' => 'ProbeAgent/1.0',
    'comment_content' => 'A plain comment.',
    'comment_type' => 'comment',
    'user_id' => 0,
];
update_option('comment_previously_approved', 1);
$approvals = [
    'the post author' => wp_check_comment_data($data(['user_id' => $author])),
    'a moderator' => wp_check_comment_data($data(['user_id' => 1])),
    'a stranger, never approved before' => wp_check_comment_data($data()),
    'too many links' => wp_check_comment_data($data(['comment_content' => '<a href="https://a.example/">a</a> <a href="https://b.example/">b</a> <a href="https://c.example/">c</a>'])),
];
update_option('comment_previously_approved', 0);
$approvals['a stranger, no approval history asked'] = wp_check_comment_data($data());
update_option('disallowed_keys', "zzforbidden\nzz-banned@example.test");
$approvals['a disallowed word'] = wp_check_comment_data($data(['comment_content' => 'This is zzforbidden here.']));
$approvals['a disallowed address'] = wp_check_comment_data($data(['comment_author_email' => 'zz-banned@example.test']));
$approvals['a disallowed word from the post author'] = wp_check_comment_data($data(['user_id' => $author, 'comment_content' => 'zzforbidden']));
update_option('disallowed_keys', $options['disallowed_keys'] ?? '');
$seen = [];
$filter = static function ($approved, $commentdata) use (&$seen) {
    $seen[] = [$approved, array_keys($commentdata)];
    return 'spam';
};
add_filter('pre_comment_approved', $filter, 10, 2);
$approvals['pre_comment_approved has the last word'] = [wp_check_comment_data($data()), $seen];
remove_filter('pre_comment_approved', $filter, 10);
$approvals['slashed data'] = wp_check_comment_data(wp_slash($data(['comment_author' => "O'Brien", 'comment_content' => 'It\'s "quoted".'])));
$say('comment data checked', $mask($approvals));

// Old posts closed to comments as the_posts hands them over.
$old = wp_insert_post(['post_title' => 'zz old post', 'post_status' => 'publish', 'post_date' => '2020-01-01 10:00:00', 'post_content' => 'x', 'comment_status' => 'open', 'ping_status' => 'open']);
$fresh = wp_insert_post(['post_title' => 'zz fresh post', 'post_status' => 'publish', 'post_content' => 'x', 'comment_status' => 'open', 'ping_status' => 'open']);
$oldPage = wp_insert_post(['post_title' => 'zz old page', 'post_type' => 'page', 'post_status' => 'publish', 'post_date' => '2020-01-01 10:00:00', 'post_content' => 'x', 'comment_status' => 'open', 'ping_status' => 'open']);
foreach (['old' => $old, 'fresh' => $fresh, 'oldPage' => $oldPage] as $label => $id) {
    $made['posts'][] = $id;
    $ids[$id] = $label;
}
$closing = static function (array $postIds, array $queryArgs): array {
    $query = new WP_Query($queryArgs);
    $posts = array_map(static fn (int $id) => clone get_post($id), $postIds);
    $out = _close_comments_for_old_posts($posts, $query);
    return array_map(static fn ($p) => [$p->comment_status, $p->ping_status], (array) $out);
};
update_option('close_comments_for_old_posts', 0);
$closed = ['option off' => $closing([$old], ['p' => $old])];
update_option('close_comments_for_old_posts', 1);
update_option('close_comments_days_old', 14);
$closed['a single old post'] = $closing([$old], ['p' => $old]);
$closed['a single fresh post'] = $closing([$fresh], ['p' => $fresh]);
$closed['an old page'] = $closing([$oldPage], ['page_id' => $oldPage]);
$closed['a listing'] = $closing([$old, $fresh], ['post__in' => [$old, $fresh]]);
$closed['nothing'] = $closing([], ['p' => $old]);
$closed['two posts on a single query'] = $closing([$old, $fresh], ['p' => $old]);
update_option('close_comments_days_old', 0);
$closed['zero days'] = $closing([$old], ['p' => $old]);
update_option('close_comments_days_old', 14);
$types = static fn () => ['post', 'page'];
add_filter('close_comments_for_post_types', $types);
$closed['pages too, by filter'] = $closing([$oldPage], ['page_id' => $oldPage]);
remove_filter('close_comments_for_post_types', $types);
$closed['the stored post is untouched'] = [get_post($old)->comment_status, get_post($old)->ping_status];
$say('old posts closed', $closed);

// The comment-type update: comments stored with an empty type become "comment", in batches.
global $wpdb;
$typeless = [];
$untype = static function (string $label) use ($post, &$made, &$ids, &$typeless, $wpdb): void {
    $id = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'zz typeless ' . $label, 'comment_content' => 'Typeless.', 'comment_approved' => 1]);
    $made['comments'][] = $id;
    $ids[$id] = 'typeless-' . $label;
    $typeless[] = $id;
    $wpdb->update($wpdb->comments, ['comment_type' => ''], ['comment_ID' => $id]);
    clean_comment_cache($id);
};
$state = static function () use (&$typeless, $wpdb): array {
    return [
        array_map(static fn (int $id) => ($wpdb->get_row($wpdb->prepare("SELECT comment_type FROM {$wpdb->comments} WHERE comment_ID = %d", $id), ARRAY_A) ?? [])['comment_type'] ?? '<gone>', $typeless),
        get_option('finished_updating_comment_type', '<unset>'),
        wp_next_scheduled('wp_update_comment_type_batch') !== false,
    ];
};
$untype('one');
$untype('two');
$untype('three');
wp_clear_scheduled_hook('wp_update_comment_type_batch');
delete_option('finished_updating_comment_type');
$runs = ['three typeless, unfinished' => $state()];
_wp_check_for_scheduled_update_comment_type();
$runs['the check schedules the batch'] = $state();
_wp_check_for_scheduled_update_comment_type();
$runs['once'] = count(array_filter((array) _get_cron_array(), static fn ($hooks) => isset($hooks['wp_update_comment_type_batch'])));
$size = static fn () => 2;
add_filter('wp_update_comment_type_batch_size', $size);
wp_clear_scheduled_hook('wp_update_comment_type_batch');
$runs['a batch of two'] = [_wp_batch_update_comment_type(), $state()];
remove_filter('wp_update_comment_type_batch_size', $size);
wp_clear_scheduled_hook('wp_update_comment_type_batch');
$runs['the rest'] = [_wp_batch_update_comment_type(), $state()];
wp_clear_scheduled_hook('wp_update_comment_type_batch');
$runs['nothing left'] = [_wp_batch_update_comment_type(), $state()];
wp_clear_scheduled_hook('wp_update_comment_type_batch');
$runs['finished: the check schedules nothing'] = [_wp_check_for_scheduled_update_comment_type(), $state()];
$untype('four');
$runs['finished, yet a batch still converts'] = [_wp_batch_update_comment_type(), $state()];
wp_clear_scheduled_hook('wp_update_comment_type_batch');
$say('comment type batches', $mask($runs));

// Notes (comments of type note): the post's author told of a new one made over
// REST, and a mentioned user told by mail; caught at pre_wp_mail.
$mail = [];
$catch = static function ($return, $atts) use (&$mail) {
    $mail[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $catch, 10, 2);
$regular = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'zz commenter', 'comment_author_email' => 'zz-commenter@example.test', 'comment_content' => 'A regular comment.', 'comment_approved' => 1]);
$note = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'editor', 'comment_author_email' => 'editor@minn-engine.localhost', 'comment_content' => 'A note on this block.', 'comment_approved' => 1, 'comment_type' => 'note', 'user_id' => 2]);
$ownNote = (int) wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'zzcommentauthor', 'comment_author_email' => 'zz-comment-author@example.test', 'comment_content' => 'My own note.', 'comment_approved' => 1, 'comment_type' => 'note', 'user_id' => $author]);
foreach (['regular' => $regular, 'note' => $note, 'ownNote' => $ownNote] as $label => $id) {
    $made['comments'][] = $id;
    $ids[$id] = $label;
}
// The post-author notice is worded by each stack (Minn writes its own notices): who it
// goes to, its subject and that it carries the note are compared.
$brief = static fn (array $sent): array => array_map(static fn (array $atts): array => [$atts['to'], $atts['subject'], str_contains((string) $atts['message'], 'A note on this block.')], $sent);
$told = static function (int $id) use (&$mail, $brief): array {
    $mail = [];
    $result = wp_new_comment_via_rest_notify_postauthor(get_comment($id));
    return [$result, $brief($mail)];
};
$notes = ['a regular comment' => $told($regular), 'a note' => $told($note), 'a note by the post author' => $told($ownNote), 'a note by its id' => (static function () use ($note, &$mail, $brief): array {
    $mail = [];
    return [wp_new_comment_via_rest_notify_postauthor($note), $brief($mail)];
})()];
update_option('wp_notes_notify', 0);
$notes['notes switched off'] = $told($note);
update_option('wp_notes_notify', 1);
$mail = [];
$notes['a mention'] = [wp_send_note_notification(get_userdata($author), get_comment($note), get_post($post)), $mail];
$mail = [];
$notes['a mention of the note author'] = [wp_send_note_notification(get_userdata(2), get_comment($note), get_post($post)), $mail];
$mail = [];
$notes['mentions found in a note'] = [wp_get_note_mentioned_user_ids('plain @admin text'), wp_notify_note_mentions(get_comment($note), null, true), $mail];
remove_filter('pre_wp_mail', $catch, 10);
$say('notes told', $mask($notes));

// Inline note markers, taken out of rendered block markup.
$say('note markers stripped', array_map(static fn (string $html): string => wp_strip_inline_note_markers($html), [
    '<p>a <mark class="wp-note" data-note-id="5">b</mark> c</p>',
    '<mark class="wp-note">b</mark>',
    '<mark data-note-id="5">b</mark>',
    '<mark class="wp-note other" data-note-id="5">b <em>i</em></mark>',
    '<mark class="other wp-note">b</mark>',
    '<mark class="wp-notes">b</mark>',
    '<span class="wp-note" data-note-id="5">b</span>',
    'x <mark class="wp-note" data-note-id="1">a</mark> y <mark class="wp-note" data-note-id="2">b</mark>',
    '<mark class="wp-note" data-note-id="1">a <mark class="wp-note" data-note-id="2">b</mark></mark>',
    '<MARK CLASS="wp-note">b</MARK>',
    '<mark class=\'wp-note\'>b</mark>',
    '<mark class="has-inline-color" style="color:red">b</mark>',
    '<mark class="wp-note">unclosed',
    'no markers at all',
    '',
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
