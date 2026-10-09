<?php
/**
 * The rest of comment.php (probe comment-remaining): comment meta, counts per
 * post, default comment statuses, the moderation and disallowed checks, the
 * flood checks, the current commenter from cookies and from a held comment's
 * link, comment cookies, new comments (approval, refusals, notifications),
 * comment form submissions, status changes and their transitions, comment
 * counts (deferred and not), the last modified date and its cache, and the
 * initial comment meta. Mail is caught before it is sent. The options it
 * changes are put back, and its posts and comments are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => []];
$options = [];
foreach (['comment_moderation', 'moderation_keys', 'disallowed_keys', 'comment_previously_approved', 'comments_notify', 'moderation_notify', 'require_name_email', 'comment_max_links', 'default_comment_status', 'default_ping_status', 'comment_registration'] as $name) {
    $options[$name] = get_option($name, null);
}
$server = $_SERVER;
$cookies = $_COOKIE;
$get = $_GET;
register_shutdown_function(static function () use (&$made, $options, $server, $cookies, $get): void {
    $_SERVER = $server;
    $_COOKIE = $cookies;
    $_GET = $get;
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach (get_comments(['search' => 'zz cr', 'status' => 'all', 'fields' => 'ids']) as $id) {
        $made['comments'][] = (int) $id;
    }
    foreach (array_unique($made['comments']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Comment) {
        return $mask(['comment' => $value->comment_ID, 'approved' => $value->comment_approved, 'author' => $value->comment_author, 'content' => $value->comment_content, 'type' => $value->comment_type, 'parent' => $value->comment_parent, 'user' => $value->user_id]);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code(), 'message' => $value->get_error_message(), 'data' => $value->get_error_data()];
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value) && !is_int($value)) {
        return $value;
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    $value = str_replace($host, '{host}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return (string) preg_replace('/moderation-hash=[0-9a-f]+/', 'moderation-hash={hash}', $value);
};
$mail = [];
add_filter('pre_wp_mail', static function ($null, $atts) use (&$mail) {
    // The engine words its notices itself (they point to Minn Admin), so only who hears and the subject are compared.
    $mail[] = [$atts['to'], $atts['subject'], $atts['message'] !== '' ? '(a message)' : ''];
    return true;
}, 10, 2);
wp_set_current_user(1);

$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$comment = static function (int $postId, string $content, array $extra = []) use (&$made, &$ids): int {
    $made['comments'][] = $id = (int) wp_insert_comment($extra + ['comment_post_ID' => $postId, 'comment_content' => $content, 'comment_approved' => 1, 'comment_author' => 'Zz', 'comment_author_email' => 'zz@zz.example', 'comment_date' => '1987-01-01 10:00:00', 'comment_date_gmt' => '1987-01-01 10:00:00']);
    $ids[$id] = str_replace(' ', '-', $content);
    return $id;
};
$discussed = $post('zz cr discussed');
$approved = $comment($discussed, 'zz cr approved');
$held = $comment($discussed, 'zz cr held', ['comment_approved' => 0, 'comment_author_email' => 'held@zz.example']);
$comment($discussed, 'zz cr spam', ['comment_approved' => 'spam']);
$comment($discussed, 'zz cr trash', ['comment_approved' => 'trash']);
$comment($discussed, 'zz cr pinged', ['comment_type' => 'pingback']);

update_comment_meta($approved, 'zz_cr_one', 'a');
$meta = [get_comment_meta($approved, 'zz_cr_one', true), update_comment_meta($approved, 'zz_cr_one', 'b', 'zz-wrong'), get_comment_meta($approved, 'zz_cr_one', true), update_comment_meta($approved, 'zz_cr_one', 'b', 'a'), update_comment_meta($approved, 'zz_cr_one', 'b')];
add_comment_meta($approved, 'zz_cr_many', 'x');
add_comment_meta($approved, 'zz_cr_many', 'y');
$meta[] = [delete_comment_meta($approved, 'zz_cr_many', 'x'), get_comment_meta($approved, 'zz_cr_many'), delete_comment_meta($approved, 'zz_cr_many'), get_comment_meta($approved, 'zz_cr_many'), delete_comment_meta($approved, 'zz_cr_missing'), delete_comment_meta(999999, 'zz_cr_one')];
$say('comment meta', array_map(static fn ($value) => is_int($value) ? 'int' : $value, $meta));

$say('comment counts per post', [get_comment_count($discussed), get_comment_count($post('zz cr quiet'))]);

register_post_type('zz_cr_plain', ['public' => true, 'supports' => ['title']]);
register_post_type('zz_cr_talk', ['public' => true, 'supports' => ['title', 'comments', 'trackbacks']]);
$defaults = static fn () => array_map(static fn ($pair) => get_default_comment_status(...$pair), [['post', 'comment'], ['page', 'comment'], ['post', 'pingback'], ['post', 'trackback'], ['attachment', 'comment'], ['zz_cr_plain', 'comment'], ['zz_cr_talk', 'pingback'], ['zz_cr_missing', 'comment'], ['post', 'zz_note']]);
$statusDefaults = [$defaults()];
update_option('default_comment_status', 'closed');
update_option('default_ping_status', 'closed');
$statusDefaults[] = $defaults();
$say('default comment statuses', $statusDefaults);

$check = static fn () => array_map(static fn ($args) => check_comment(...$args), [
    ['Zz', 'zz@zz.example', 'https://zz.example', 'plain words', '127.0.0.1', 'agent', 'comment'],
    ['Zz', 'zz@zz.example', '', 'see https://a.example and https://b.example', '127.0.0.1', 'agent', 'comment'],
    ['Zz', 'zz@zz.example', '', '<a href="https://a.example">a</a> <a href="https://b.example">b</a>', '127.0.0.1', 'agent', 'comment'],
    ['Zz', 'zz@zz.example', '', 'mentions zzbadword here', '127.0.0.1', 'agent', 'comment'],
    ['New', 'new@zz.example', '', 'first time', '127.0.0.1', 'agent', 'comment'],
    ['Held', 'held@zz.example', '', 'held before', '127.0.0.1', 'agent', 'comment'],
    ['Zz', 'zz@zz.example', '', 'a pingback', '127.0.0.1', 'agent', 'pingback'],
    ['Zz', 'zz@zz.example', '', 'from 10.9.8.7', '10.9.8.7', 'agent', 'comment'],
]);
$checks = [$check()];
update_option('moderation_keys', "zzbadword\n10.9.8.7\n\n");
$checks[] = $check();
update_option('comment_max_links', '0');
$checks[] = $check();
update_option('comment_previously_approved', '0');
$checks[] = $check();
update_option('comment_moderation', '1');
$checks[] = $check();
foreach (['comment_moderation', 'moderation_keys', 'comment_previously_approved', 'comment_max_links'] as $name) {
    update_option($name, $options[$name]);
}
$say('moderation checks', $checks);

$disallowed = static fn () => array_map(static fn ($args) => wp_check_comment_disallowed_list(...$args), [
    ['Zz', 'zz@zz.example', 'https://zz.example', 'plain', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', 'https://zz.example', 'has SPAMWORD inside', '127.0.0.1', 'agent'],
    ['Zz', 'spam@bad.example', '', 'plain', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', '', 'plain', '10.1.2.3', 'agent'],
    ['Zz', 'zz@zz.example', '', 'plain', '127.0.0.1', 'Bad-Agent/1.0'],
    ['Spammy Name', 'zz@zz.example', '', 'plain', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', '', 'the words sp<b>am</b>word split', '127.0.0.1', 'agent'],
    ['Spa<b>mmy</b>', 'zz@zz.example', '', 'plain', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', '', 'spam&#119;ord as an entity', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', '', 'SPAMWORD shouted', '127.0.0.1', 'agent'],
    ['Zz', 'zz@zz.example', 'https://bad.example/x', 'plain', '127.0.0.1', 'agent'],
]);
$lists = [$disallowed()];
update_option('disallowed_keys', "spamword\nbad.example\n10.1.2\nBad-Agent\nSpammy\n#\n");
$lists[] = $disallowed();
update_option('disallowed_keys', $options['disallowed_keys']);
$say('disallowed checks', $lists);

$say('flood checks', [
    wp_throttle_comment_flood(false, 1000, 1010), wp_throttle_comment_flood(false, 1000, 1015), wp_throttle_comment_flood(false, 1000, 1016), wp_throttle_comment_flood(true, 1000, 2000), wp_throttle_comment_flood(false, '1000', '1005'),
    has_filter('wp_is_comment_flood', 'wp_check_comment_flood'), (static function () {
        remove_filter('wp_is_comment_flood', 'wp_check_comment_flood', 10);
        $before = has_filter('wp_is_comment_flood', 'wp_check_comment_flood');
        check_comment_flood_db();
        return [$before, has_filter('wp_is_comment_flood', 'wp_check_comment_flood')];
    })(),
    wp_check_comment_flood(true, '127.0.0.1', 'zz@zz.example', '2026-10-09 10:00:00', true), wp_check_comment_flood(false, '203.0.113.99', 'nobody@zz.example', '2026-10-09 10:00:00', true),
]);

$hash = COOKIEHASH;
$commenters = [];
$_COOKIE = [];
$commenters[] = wp_get_current_commenter();
$_COOKIE = ["comment_author_{$hash}" => 'Remy \\"R\\" Reader', "comment_author_email_{$hash}" => 'remy@zz.example', "comment_author_url_{$hash}" => 'https://remy.example'];
$commenters[] = wp_get_current_commenter();
$_COOKIE = ["comment_author_{$hash}" => '<b>Remy</b>', "comment_author_email_{$hash}" => 'not an email', "comment_author_url_{$hash}" => 'javascript:alert(1)'];
$commenters[] = wp_get_current_commenter();
$_COOKIE = [];
$unapproved = [wp_get_unapproved_comment_author_email()];
$_GET = ['unapproved' => (string) $held, 'moderation-hash' => wp_hash(get_comment($held)->comment_date_gmt)];
$unapproved[] = wp_get_unapproved_comment_author_email();
$recent = $comment($discussed, 'zz cr held recently', ['comment_approved' => 0, 'comment_author_email' => 'recent@zz.example', 'comment_date' => current_time('mysql'), 'comment_date_gmt' => current_time('mysql', true)]);
$_GET = ['unapproved' => (string) $recent, 'moderation-hash' => wp_hash(get_comment($recent)->comment_date_gmt)];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = ['unapproved' => (string) $recent, 'moderation-hash' => 'zz'];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = ['unapproved' => (string) $approved, 'moderation-hash' => wp_hash(get_comment($approved)->comment_date_gmt)];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = [];
$_COOKIE = ["comment_author_email_{$hash}" => 'cookie@zz.example'];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = ['unapproved' => (string) $recent, 'moderation-hash' => 'zz'];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = ['unapproved' => (string) $recent];
$unapproved[] = wp_get_unapproved_comment_author_email();
$_GET = [];
$_COOKIE = [];
$say('the current commenter', $mask([$commenters, $unapproved]));

$heard = [];
add_action('set_comment_cookies', $cookieListener = static function ($comment, $user, $consent) use (&$heard) {
    $heard[] = ['set_comment_cookies', (int) $comment->comment_ID, $user instanceof WP_User ? $user->ID : get_class($user), $consent];
}, 10, 3);
add_filter('comment_cookie_lifetime', $lifetimeListener = static function ($lifetime) use (&$heard) {
    $heard[] = ['comment_cookie_lifetime', $lifetime];
    return $lifetime;
});
wp_set_comment_cookies(get_comment($approved), wp_get_current_user());
wp_set_comment_cookies(get_comment($approved), new WP_User(0));
wp_set_comment_cookies(get_comment($approved), new WP_User(0), false);
remove_action('set_comment_cookies', $cookieListener, 10);
remove_filter('comment_cookie_lifetime', $lifetimeListener);
$say('comment cookies', $mask($heard));

// New comments: flood blocking is off here, so the stacks see the same thing whatever ran before.
add_filter('wp_is_comment_flood', '__return_false', 99);
$fresh = $post('zz cr fresh', ['post_author' => 1]);
$guestPost = $post('zz cr guests', ['post_author' => 0]);
$closed = $post('zz cr closed', ['comment_status' => 'closed']);
wp_set_current_user(0);
$news = [];
foreach ([
    ['comment_post_ID' => $fresh, 'comment_content' => 'zz cr new approved', 'comment_author' => 'Ann', 'comment_author_email' => 'zz@zz.example', 'comment_author_url' => '', 'comment_author_IP' => '198.51.100.1'],
    ['comment_post_ID' => $fresh, 'comment_content' => 'zz cr new held https://a.example https://b.example https://c.example', 'comment_author' => 'Bob', 'comment_author_email' => 'bob@zz.example', 'comment_author_url' => '', 'comment_author_IP' => '198.51.100.2'],
    ['comment_post_ID' => $fresh, 'comment_content' => 'zz cr new held https://a.example https://b.example https://c.example', 'comment_author' => 'Bob', 'comment_author_email' => 'bob@zz.example', 'comment_author_url' => '', 'comment_author_IP' => '198.51.100.2'],
    ['comment_post_ID' => $fresh, 'comment_content' => 'zz cr new reply', 'comment_author' => 'Cat', 'comment_author_email' => 'cat@zz.example', 'comment_author_url' => 'https://cat.example', 'comment_parent' => $approved, 'comment_author_IP' => '198.51.100.3'],
    ['comment_post_ID' => $guestPost, 'comment_content' => 'zz cr new on a guest post', 'comment_author' => 'Dee', 'comment_author_email' => 'zz@zz.example', 'comment_author_url' => '', 'comment_author_IP' => '198.51.100.4'],
] as $data) {
    $mail = [];
    $result = wp_new_comment($data, true);
    if (is_int($result) && $result > 0) {
        $made['comments'][] = $result;
        $ids[$result] = str_replace(' ', '-', substr($data['comment_content'], 0, 18));
        $result = get_comment($result);
    }
    $news[] = [$mask($result), $mask($mail)];
}
update_option('disallowed_keys', 'zzforbidden');
$mail = [];
$result = wp_new_comment(['comment_post_ID' => $fresh, 'comment_content' => 'zz cr new zzforbidden', 'comment_author' => 'Eve', 'comment_author_email' => 'eve@zz.example', 'comment_author_url' => '', 'comment_author_IP' => '198.51.100.5'], true);
if (is_int($result) && $result > 0) {
    $made['comments'][] = $result;
    $ids[$result] = 'new-forbidden';
    $result = get_comment($result);
}
$news[] = [$mask($result), $mask($mail)];
update_option('disallowed_keys', $options['disallowed_keys']);
$say('new comments', $news);

$notified = [];
foreach (['comments_notify' => ['1', '0'], 'moderation_notify' => ['1', '0']] as $name => $values) {
    foreach ($values as $value) {
        update_option($name, $value);
        $mail = [];
        $notified[] = [$name, $value, $name === 'comments_notify' ? wp_new_comment_notify_postauthor($approved) : wp_new_comment_notify_moderator($held), $mask($mail)];
    }
    update_option($name, $options[$name]);
}
$mail = [];
$notified[] = ['spam to the author', wp_new_comment_notify_postauthor($comment($discussed, 'zz cr spam two', ['comment_approved' => 'spam'])), wp_new_comment_notify_moderator($approved), $mask($mail)];
$say('new comment notifications', $notified);

$submissions = [];
foreach ([
    ['comment_post_ID' => $fresh, 'author' => 'Fay', 'email' => 'fay@zz.example', 'url' => 'https://fay.example', 'comment' => 'zz cr submitted <b>bold</b> <script>x</script>', 'comment_parent' => 0],
    ['comment_post_ID' => $fresh, 'author' => '', 'email' => '', 'comment' => 'zz cr anonymous'],
    ['comment_post_ID' => $fresh, 'author' => 'Gil', 'email' => 'not-an-email', 'comment' => 'zz cr bad email'],
    ['comment_post_ID' => $fresh, 'author' => 'Hal', 'email' => 'hal@zz.example', 'comment' => ''],
    ['comment_post_ID' => $closed, 'author' => 'Ivy', 'email' => 'ivy@zz.example', 'comment' => 'zz cr on a closed post'],
    ['comment_post_ID' => 999999, 'author' => 'Jo', 'email' => 'jo@zz.example', 'comment' => 'zz cr on nothing'],
    ['comment_post_ID' => $fresh, 'author' => 'Kim', 'email' => 'kim@zz.example', 'comment' => 'zz cr too long ' . str_repeat('x', 65530)],
    ['comment_post_ID' => $fresh, 'author' => str_repeat('n', 250), 'email' => 'long@zz.example', 'comment' => 'zz cr long name'],
] as $data) {
    $mail = [];
    $result = wp_handle_comment_submission($data);
    if ($result instanceof WP_Comment) {
        $made['comments'][] = (int) $result->comment_ID;
        $ids[(int) $result->comment_ID] = 'submitted-' . count($submissions);
    }
    $submissions[] = [$mask($result), count($mail)];
}
wp_set_current_user(1);
$mail = [];
$result = wp_handle_comment_submission(['comment_post_ID' => $fresh, 'comment' => 'zz cr from the admin <b>bold</b> <script>x</script>', 'author' => 'ignored', 'email' => 'ignored@zz.example']);
if ($result instanceof WP_Comment) {
    $made['comments'][] = (int) $result->comment_ID;
    $ids[(int) $result->comment_ID] = 'submitted-admin';
}
$submissions[] = [$mask($result), count($mail)];
update_option('comment_registration', '1');
wp_set_current_user(0);
$submissions[] = [$mask(wp_handle_comment_submission(['comment_post_ID' => $fresh, 'author' => 'Lou', 'email' => 'lou@zz.example', 'comment' => 'zz cr needs a sign-in'])), 0];
update_option('comment_registration', $options['comment_registration']);
wp_set_current_user(1);
remove_filter('wp_is_comment_flood', '__return_false', 99);
$say('comment submissions', $submissions);

$transitions = [];
foreach (['transition_comment_status', 'comment_approved_to_unapproved', 'comment_unapproved_to_approved', 'comment_approved_to_spam', 'comment_spam_to_approved', 'comment_approved_to_trash', 'comment_trash_to_approved', 'comment_unapproved_comment', 'comment_approved_comment', 'comment_spam_comment', 'comment_trash_comment', 'wp_set_comment_status', 'spammed_comment', 'trashed_comment', 'untrashed_comment', 'unspammed_comment'] as $hook) {
    add_action($hook, static function (...$args) use ($hook, &$transitions, &$ids) {
        $transitions[] = [$hook, ...array_map(static fn ($arg) => $arg instanceof WP_Comment ? 'comment' : (is_int($arg) || is_numeric($arg) ? (isset($ids[(int) $arg]) ? '{' . $ids[(int) $arg] . '}' : $arg) : $arg), $args)];
    }, 10, 3);
}
$statusChanges = [];
$target = $comment($discussed, 'zz cr moving');
foreach (['hold', 'approve', 'spam', 'approve', 'trash', '1', '0', 'zz-nonsense', 'approve'] as $status) {
    $transitions = [];
    $statusChanges[] = [$status, $mask(wp_set_comment_status($target, $status, true)), get_comment($target)->comment_approved, $transitions];
}
$transitions = [];
wp_transition_comment_status('approved', 'unapproved', get_comment($target));
wp_transition_comment_status('approved', 'approved', get_comment($target));
wp_transition_comment_status('spam', 'zz', get_comment($target));
$statusChanges[] = ['transition by hand', $transitions];
$say('comment status changes', $statusChanges);

$counted = $post('zz cr counted');
$comment($counted, 'zz cr counted one');
$comment($counted, 'zz cr counted two');
$comment($counted, 'zz cr counted held', ['comment_approved' => 0]);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['comment_count' => 9], ['ID' => $counted]);
clean_post_cache($counted);
$commentCount = static fn () => (int) get_post($counted)->comment_count;
$counts = [$commentCount(), wp_update_comment_count_now($counted), $commentCount()];
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['comment_count' => 9], ['ID' => $counted]);
clean_post_cache($counted);
wp_defer_comment_counting(true);
$counts[] = wp_update_comment_count($counted);
$counts[] = $commentCount();
$counts[] = wp_update_comment_count(null, true);
$counts[] = $commentCount();
wp_defer_comment_counting(false);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['comment_count' => 9], ['ID' => $counted]);
clean_post_cache($counted);
$counts[] = wp_update_comment_count($counted);
$counts[] = $commentCount();
$counts[] = [wp_update_comment_count_now(999999), wp_update_comment_count_now(0)];
add_filter('pre_wp_update_comment_count_now', $preset = static fn ($new, $old, $id) => 42, 10, 3);
$counts[] = [wp_update_comment_count_now($counted), $commentCount()];
remove_filter('pre_wp_update_comment_count_now', $preset, 10);
$say('comment counts', $counts);

// The last comment date: cached until a status change clears it.
$latest = $post('zz cr latest');
$future = $comment($latest, 'zz cr far future', ['comment_date' => '2040-05-05 12:00:00', 'comment_date_gmt' => '2040-05-05 16:00:00']);
wp_cache_delete('lastcommentmodified:server', 'timeinfo');
wp_cache_delete('lastcommentmodified:gmt', 'timeinfo');
wp_cache_delete('lastcommentmodified:blog', 'timeinfo');
$modified = [get_lastcommentmodified(), get_lastcommentmodified('gmt'), get_lastcommentmodified('blog'), get_lastcommentmodified('server')];
$later = $comment($latest, 'zz cr further future', ['comment_date' => '2041-06-06 12:00:00', 'comment_date_gmt' => '2041-06-06 16:00:00']);
$modified[] = get_lastcommentmodified('gmt');
_clear_modified_cache_on_transition_comment_status('approved', 'approved');
$modified[] = get_lastcommentmodified('gmt');
_clear_modified_cache_on_transition_comment_status('unapproved', 'approved');
$modified[] = get_lastcommentmodified('gmt');
wp_delete_comment($later, true);
wp_delete_comment($future, true);
wp_cache_delete('lastcommentmodified:gmt', 'timeinfo');
$say('the last comment date', $modified);

$commentKeys = static fn () => array_keys(get_registered_meta_keys('comment', ''));
$initial = [$commentKeys()];
foreach ($initial[0] as $key) {
    unregister_meta_key('comment', $key);
}
$initial[] = $commentKeys();
wp_create_initial_comment_meta();
$initial[] = $commentKeys();
$initial[] = array_map(static fn ($args) => array_intersect_key($args, array_flip(['type', 'single', 'show_in_rest', 'default', 'sanitize_callback'])), get_registered_meta_keys('comment', ''));
$say('the initial comment meta', $initial);

unregister_post_type('zz_cr_plain');
unregister_post_type('zz_cr_talk');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
