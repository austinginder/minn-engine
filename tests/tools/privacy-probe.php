<?php
/**
 * Personal data export, erasure and clean-up (probe privacy): the exporters
 * and the eraser registered on their filters, the request statuses, what the
 * comment, media and user exporters hand back for a person (a user with
 * comments, an upload, a profile and a session) and for a stranger, the
 * comment eraser's anonymising (the comments read back after, one kept by
 * a filter), the two clean-up jobs scheduled and run (an old export file
 * removed, a stale request marked failed), and the erasure-fulfilled mail
 * (caught at pre_wp_mail). The probe's posts, comments, user and files are
 * removed at the end, and the two cron events are put back as they were
 * found. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => [], 'users' => [], 'files' => []];
$cron = [
    'wp_privacy_delete_old_export_files' => [wp_next_scheduled('wp_privacy_delete_old_export_files'), wp_get_schedule('wp_privacy_delete_old_export_files')],
    'wp_privacy_personal_data_cleanup_requests' => [wp_next_scheduled('wp_privacy_personal_data_cleanup_requests'), wp_get_schedule('wp_privacy_personal_data_cleanup_requests')],
];
$exportsDir = wp_privacy_exports_dir();
$hadExportsDir = is_dir($exportsDir);
$policyPage = get_option('wp_page_for_privacy_policy');
$zone = get_option('timezone_string');
register_shutdown_function(static function () use (&$made, $cron, $exportsDir, $hadExportsDir, $policyPage, $zone): void {
    update_option('wp_page_for_privacy_policy', $policyPage);
    update_option('timezone_string', $zone);
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
    foreach ($made['files'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (!$hadExportsDir && is_dir($exportsDir)) {
        foreach (glob($exportsDir . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($exportsDir);
    }
    foreach ($cron as $hook => [$next, $schedule]) {
        wp_clear_scheduled_hook($hook);
        if ($next !== false && $schedule !== false) {
            wp_schedule_event($next, $schedule, $hook);
        }
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
    $value = str_replace([home_url(), site_url()], ['{home}', '{site}'], $value);
    // Only the dates the probe did not choose (a user's registration) are masked.
    $value = (string) preg_replace('/(?!2026-01-02 )\d{4}-\d\d-\d\d \d\d:\d\d:\d\d/', '{date}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};

$say('registered', [
    wp_register_comment_personal_data_exporter(['other' => ['keep']]),
    wp_register_comment_personal_data_eraser(['other' => ['keep']]),
    wp_register_media_personal_data_exporter([]),
    wp_register_user_personal_data_exporter([]),
]);
$say('statuses', _wp_privacy_statuses());

// A person: a user with a profile, a session, an upload and three comments
// (one signed in, two as a guest), and a stranger's comment beside them.
$email = 'zz-privacy@example.test';
$user = wp_insert_user([
    'user_login' => 'zzprivacy',
    'user_email' => $email,
    'user_pass' => wp_generate_password(),
    'user_url' => 'https://zz-privacy.example/',
    'first_name' => 'Zed',
    'last_name' => 'Privacy',
    'nickname' => 'zed',
    'display_name' => 'Zed Privacy',
    'description' => 'About Zed.',
]);
$made['users'][] = $user;
$ids[$user] = 'user';
update_user_meta($user, 'session_tokens', ['zzhash' => ['expiration' => 2000000000, 'ip' => '203.0.113.9', 'ua' => 'ProbeAgent/1.0', 'login' => 1900000000]]);
update_user_meta($user, 'community-events-location', ['description' => 'Zed Town', 'latitude' => '40.1', 'longitude' => '-75.2', 'country' => 'US', 'ip' => '203.0.113.0']);
$post = wp_insert_post(['post_title' => 'zz privacy post', 'post_status' => 'publish', 'post_content' => 'x']);
$made['posts'][] = $post;
$ids[$post] = 'post';
$upload = wp_insert_attachment(['post_title' => 'zz privacy photo', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_author' => $user], '2026/10/zz-privacy-photo.jpg');
$made['posts'][] = $upload;
$ids[$upload] = 'upload';
$comment = static function (array $fields) use ($post, &$made, &$ids): int {
    $id = (int) wp_insert_comment($fields + [
        'comment_post_ID' => $post,
        'comment_approved' => 1,
        'comment_date' => '2026-01-02 03:04:05',
        'comment_date_gmt' => '2026-01-02 08:04:05',
    ]);
    $made['comments'][] = $id;
    $ids[$id] = 'comment' . count($made['comments']);
    return $id;
};
$signedIn = $comment(['comment_author' => 'Zed Privacy', 'comment_author_email' => $email, 'comment_author_url' => 'https://zz-privacy.example/', 'comment_author_IP' => '203.0.113.7', 'comment_agent' => 'ProbeAgent/1.0', 'comment_content' => 'Signed in.', 'user_id' => $user]);
$guest = $comment(['comment_author' => 'Zed', 'comment_author_email' => 'ZZ-Privacy@Example.test', 'comment_author_url' => '', 'comment_author_IP' => '2001:db8::1234', 'comment_agent' => '', 'comment_content' => 'As a guest.']);
$kept = $comment(['comment_author' => 'Zed again', 'comment_author_email' => $email, 'comment_author_url' => 'https://zz-privacy.example/b', 'comment_author_IP' => '203.0.113.8', 'comment_agent' => 'Other/2', 'comment_content' => 'Keep me.']);
$held = $comment(['comment_author' => 'Zed held', 'comment_author_email' => $email, 'comment_author_IP' => '203.0.113.11', 'comment_content' => 'Held for moderation.', 'comment_approved' => 0]);
$stranger = $comment(['comment_author' => 'Somebody', 'comment_author_email' => 'zz-stranger@example.test', 'comment_author_IP' => '203.0.113.50', 'comment_content' => 'Not Zed.']);

$say('comments exported', array_map($mask, [
    wp_comments_personal_data_exporter($email),
    wp_comments_personal_data_exporter($email, 2),
    wp_comments_personal_data_exporter('ZZ-PRIVACY@EXAMPLE.TEST'),
    wp_comments_personal_data_exporter('nobody@example.test'),
    wp_comments_personal_data_exporter($email, 0),
]));
$say('media exported', array_map($mask, [
    wp_media_personal_data_exporter($email),
    wp_media_personal_data_exporter($email, 2),
    wp_media_personal_data_exporter('nobody@example.test'),
]));
$bare = wp_insert_user(['user_login' => 'zzprivacybare', 'user_email' => 'zz-bare@example.test', 'user_pass' => wp_generate_password()]);
$made['users'][] = $bare;
$ids[$bare] = 'bare';
$say('user exported', array_map($mask, [
    wp_user_personal_data_exporter($email),
    wp_user_personal_data_exporter('zz-bare@example.test'),
    wp_user_personal_data_exporter('nobody@example.test'),
]));
$given = null;
$extra = static function ($data, $user, $reserved) use (&$given) {
    $given = [$data, $user instanceof WP_User, $reserved];
    return array_merge((array) $data, [['name' => 'Shoe size', 'value' => '42'], ['name' => 'ID', 'value' => 'clash'], ['name' => 'User Email', 'value' => 'reserved'], ['name' => 'No value']]);
};
add_filter('wp_privacy_additional_user_profile_data', $extra, 10, 3);
$notices = [];
$hear = static function (...$args) use (&$notices) { $notices[] = $args[0]; };
add_action('doing_it_wrong_run', $hear);
$say('user exported, with additional profile data', $mask([wp_user_personal_data_exporter($email)['data'][0], $given, $notices]));
remove_action('doing_it_wrong_run', $hear);
remove_filter('wp_privacy_additional_user_profile_data', $extra, 10);
update_option('timezone_string', 'America/New_York');
$say('session dates in the site timezone', $mask(wp_user_personal_data_exporter($email)['data'][2] ?? null));
update_option('timezone_string', $zone);

// The eraser: one comment kept by the filter, the rest anonymised.
$keep = static fn ($anonymize, $comment, $anonymized) => match ((int) $comment->comment_ID) {
    $kept => 'Kept for the records.',
    $guest => false,
    default => $anonymize,
};
add_filter('wp_anonymize_comment', $keep, 10, 3);
$edits = [];
$edited = static function ($id, $data = null) use (&$edits) { $edits[] = is_array($data) ? array_keys($data) : $id; };
add_action('edit_comment', $edited, 10, 2);
$erased = wp_comments_personal_data_eraser($email);
remove_action('edit_comment', $edited, 10);
remove_filter('wp_anonymize_comment', $keep, 10);
$read = static function (int $id): array {
    $c = get_comment($id);
    return [$c->comment_author, $c->comment_author_email, $c->comment_author_url, $c->comment_author_IP, $c->comment_agent, (int) $c->user_id, $c->comment_content];
};
clean_comment_cache([$signedIn, $guest, $kept, $held, $stranger]);
$say('comments erased', $mask([$erased, $edits, $read($signedIn), $read($guest), $read($kept), $read($held), $read($stranger)]));
$say('comments erased again', $mask([wp_comments_personal_data_eraser($email), wp_comments_personal_data_eraser($email, 2), wp_comments_personal_data_eraser('')]));

// The clean-up jobs, scheduled from nothing and then run.
wp_clear_scheduled_hook('wp_privacy_delete_old_export_files');
wp_clear_scheduled_hook('wp_privacy_personal_data_cleanup_requests');
wp_schedule_delete_old_privacy_export_files();
wp_schedule_personal_data_cleanup_requests();
wp_schedule_delete_old_privacy_export_files();
wp_schedule_personal_data_cleanup_requests();
$events = static function (string $hook): array {
    $found = [];
    foreach ((array) _get_cron_array() as $time => $hooks) {
        foreach ((array) ($hooks[$hook] ?? []) as $event) {
            $found[] = [$time - time() <= 60 && $time - time() >= -60, $event['schedule'] ?? false, $event['args'] ?? null];
        }
    }
    return $found;
};
$say('jobs scheduled', [$events('wp_privacy_delete_old_export_files'), $events('wp_privacy_personal_data_cleanup_requests')]);

$stale = wp_create_user_request('zz-stale@example.test', 'export_personal_data');
$fresh = wp_create_user_request('zz-fresh@example.test', 'export_personal_data');
$confirmed = wp_create_user_request('zz-done@example.test', 'export_personal_data', [], 'confirmed');
foreach ([$stale, $fresh, $confirmed] as $i => $id) {
    if (is_int($id) && $id > 0) {
        $made['posts'][] = $id;
        $ids[$id] = ['stale', 'fresh', 'confirmed'][$i];
    }
}
global $wpdb;
$old = gmdate('Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS);
foreach ([$stale, $confirmed] as $id) {
    $wpdb->update($wpdb->posts, ['post_modified_gmt' => $old, 'post_modified' => $old], ['ID' => $id]);
    clean_post_cache($id);
}
wp_privacy_personal_data_cleanup_requests();
$say('stale requests failed', array_map(static fn ($id) => get_post_status($id), [$stale, $fresh, $confirmed]));
$wpdb->update($wpdb->posts, ['post_status' => 'request-pending', 'post_modified_gmt' => $old, 'post_modified' => $old], ['ID' => $fresh]);
clean_post_cache($fresh);
$week = static fn () => WEEK_IN_SECONDS;
add_filter('user_request_key_expiration', $week);
wp_privacy_personal_data_cleanup_requests();
remove_filter('user_request_key_expiration', $week);
$say('a longer key life keeps the request', get_post_status($fresh));

wp_mkdir_p($exportsDir);
$files = ['zz-old.zip' => time() - 4 * DAY_IN_SECONDS, 'zz-new.zip' => time() - 60, 'zz-old.html' => time() - 4 * DAY_IN_SECONDS, 'zz-old.txt' => time() - 4 * DAY_IN_SECONDS, 'zz-old.json' => time() - 4 * DAY_IN_SECONDS, 'zz-old' => time() - 4 * DAY_IN_SECONDS];
if (!file_exists($exportsDir . 'index.php')) {
    $files['index.php'] = time() - 4 * DAY_IN_SECONDS;
}
foreach ($files as $name => $when) {
    file_put_contents($exportsDir . $name, 'x');
    touch($exportsDir . $name, $when);
    $made['files'][] = $exportsDir . $name;
}
wp_privacy_delete_old_export_files();
$say('old export files removed', array_map(static fn ($name) => [$name, is_file($exportsDir . $name)], array_keys($files)));
$day = static fn () => DAY_IN_SECONDS * 5;
touch($exportsDir . 'zz-new.zip', time() - 4 * DAY_IN_SECONDS);
add_filter('wp_privacy_export_expiration', $day);
wp_privacy_delete_old_export_files();
remove_filter('wp_privacy_export_expiration', $day);
$say('a longer export life keeps the file', is_file($exportsDir . 'zz-new.zip'));

// The erasure-fulfilled notice.
$mail = [];
$catch = static function ($return, $atts) use (&$mail) {
    $mail[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $catch, 10, 2);
$erasure = wp_create_user_request($email, 'remove_personal_data', [], 'confirmed');
$unfinished = wp_create_user_request('zz-unfinished@example.test', 'remove_personal_data', [], 'confirmed');
$export = wp_create_user_request('zz-exporter@example.test', 'export_personal_data', [], 'confirmed');
$visitor = wp_create_user_request('zz-visitor@example.test', 'remove_personal_data', [], 'confirmed');
foreach (['erasure' => $erasure, 'unfinished' => $unfinished, 'export' => $export, 'visitor' => $visitor] as $label => $id) {
    if (is_int($id) && $id > 0) {
        $made['posts'][] = $id;
        $ids[$id] = $label;
    }
}
foreach ([$erasure, $export, $visitor] as $id) {
    wp_update_post(['ID' => $id, 'post_status' => 'request-completed']);
}
$notified = [];
foreach ([$erasure, $unfinished, $export, $visitor, 999999999] as $id) {
    $mail = [];
    $result = _wp_privacy_send_erasure_fulfillment_notification($id);
    $notified[] = [$result, $mail, $id === 999999999 ? null : get_post_meta($id, '_wp_user_notified', true)];
}
$mail = [];
_wp_privacy_send_erasure_fulfillment_notification($erasure);
$notified[] = ['again', $mail];

// With a published privacy policy, and with the mail's four filters heard.
$policy = wp_insert_post(['post_title' => 'zz privacy policy', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'x']);
$made['posts'][] = $policy;
$ids[$policy] = 'policy';
update_option('wp_page_for_privacy_policy', $policy);
$heard = [];
$hooks = ['user_erasure_fulfillment_email_to', 'user_erasure_fulfillment_email_subject', 'user_erasure_fulfillment_email_content', 'user_erasure_fulfillment_email_headers'];
$listeners = [];
foreach ($hooks as $hook) {
    $listeners[$hook] = static function (...$args) use ($hook, &$heard) {
        $heard[] = [$hook, count($args), is_string($args[0]) ? $args[0] : gettype($args[0]), is_array($args[2] ?? null) ? array_keys($args[2]) : null];
        return match ($hook) {
            'user_erasure_fulfillment_email_subject' => 'Subject from a filter',
            'user_erasure_fulfillment_email_headers' => 'X-Probe: 1',
            default => $args[0],
        };
    };
    add_filter($hook, $listeners[$hook], 10, 5);
}
$policied = wp_create_user_request('zz-policy@example.test', 'remove_personal_data', [], 'confirmed');
if (is_int($policied) && $policied > 0) {
    $made['posts'][] = $policied;
    $ids[$policied] = 'policied';
    wp_update_post(['ID' => $policied, 'post_status' => 'request-completed']);
}
$mail = [];
_wp_privacy_send_erasure_fulfillment_notification($policied);
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener, 10);
}
update_option('wp_page_for_privacy_policy', $policyPage);
$notified[] = ['with a policy and filters', $mail, $heard];
remove_filter('pre_wp_mail', $catch, 10);
$say('erasure fulfilled notice', $mask($notified));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
