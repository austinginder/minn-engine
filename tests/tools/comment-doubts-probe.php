<?php
/**
 * The facade review's doubts about comment.php, each measured on both
 * stacks and matched (contracts/api/facade-review.json;
 * ~/Documents/minn-facade-review/doubts-2026-10-20). Same protocol as
 * api-probe.php; the probe's rows (zzcd) are removed after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
try {
    $post = $made['posts'][] = wp_insert_post(['post_title' => 'zzcd post', 'post_status' => 'publish']);
    $comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zzcd', 'comment_author' => 'Zz Author', 'comment_author_url' => 'http://www.zz.example/', 'comment_date' => '2024-01-01 10:00:00', 'comment_approved' => '1']);

    // get_comment_ID with no comment.
    unset($GLOBALS['comment']);
    $say('get_comment_ID with no comment', get_comment_ID());

    // get_comment_date: any empty format is the site's.
    $say('get_comment_date with empty formats', [get_comment_date('0', $comment), get_comment_date(null, $comment), get_comment_date(false, $comment), get_comment_date('', $comment)]);

    // get_comment_id_fields with no post.
    $GLOBALS['post'] = null;
    $say('get_comment_id_fields with no post', get_comment_id_fields(999999999));

    // get_comments_number_text translates its words.
    $words = static fn ($translation, $text) => 'ZZT ' . $text;
    $plural = static fn ($translation, $single, $plural, $number) => 'ZZN ' . ($number === 1 ? $single : $plural);
    add_filter('gettext', $words, 10, 2);
    add_filter('ngettext', $plural, 10, 4);
    $empty = $made['posts'][] = wp_insert_post(['post_title' => 'zzcd empty', 'post_status' => 'publish']);
    $say('get_comments_number_text translated', [get_comments_number_text(false, false, false, $empty), get_comments_number_text(false, false, false, $post)]);
    remove_filter('gettext', $words, 10);
    remove_filter('ngettext', $plural, 10);

    // get_comment_count counts from the table, wp_count_comments's filter not asked.
    $fake = static fn () => ['approved' => 99, 'moderated' => 98, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'all' => 197, 'total_comments' => 197];
    add_filter('wp_count_comments', $fake);
    $say('get_comment_count of the post, under a wp_count_comments filter', get_comment_count($post));
    remove_filter('wp_count_comments', $fake);

    // comment_form_title makes the comment replied to the global comment.
    $_GET['replytocom'] = (string) $comment;
    unset($GLOBALS['comment']);
    ob_start();
    comment_form_title(false, false, true, $post);
    $title = ob_get_clean();
    unset($_GET['replytocom']);
    $say('comment_form_title: printed, and the global comment', [preg_replace('/#comment-\\d+/', '#comment-{id}', $title), isset($GLOBALS['comment']) && (int) $GLOBALS['comment']->comment_ID === $comment]);

    // get_comment_author_url_link strips the text it is given too.
    $say('get_comment_author_url_link texts', [get_comment_author_url_link('http://www.zz.example/', '', '', $comment), get_comment_author_url_link('', '', '', $comment)]);

    // get_comment_author_link: rel ugc alone for an internal address.
    $internal = static fn (array $hosts): array => [...$hosts, 'zz-internal.example'];
    add_filter('wp_internal_hosts', $internal);
    $rels = [];
    foreach (['http://zz-internal.example/x', 'ftp://elsewhere.example/', 'https://elsewhere.example/'] as $url) {
        wp_update_comment(['comment_ID' => $comment, 'comment_author_url' => $url]);
        $rels[$url] = preg_match('/rel="([^"]*)"/', get_comment_author_link($comment), $m) ? $m[1] : null;
    }
    remove_filter('wp_internal_hosts', $internal);
    $say('get_comment_author_link rel by address', $rels);

    // _close_comments_for_old_post: a post never dated is not old.
    $on = static fn () => '1';
    $days = static fn () => '14';
    add_filter('pre_option_close_comments_for_old_posts', $on);
    add_filter('pre_option_close_comments_days_old', $days);
    $undated = $made['posts'][] = wp_insert_post(['post_title' => 'zzcd undated', 'post_status' => 'draft', 'comment_status' => 'open']);
    $old = $made['posts'][] = wp_insert_post(['post_title' => 'zzcd old', 'post_status' => 'publish', 'comment_status' => 'open', 'post_date' => '2001-01-01 00:00:00']);
    $say('comments open: an undated draft, an old post', [_close_comments_for_old_post(true, $undated), _close_comments_for_old_post(true, $old)]);
    remove_filter('pre_option_close_comments_for_old_posts', $on);
    remove_filter('pre_option_close_comments_days_old', $days);

    // get_comment_author_email_link: antispambot on comment_email, where a plugin may take it off.
    wp_update_comment(['comment_ID' => $comment, 'comment_author_email' => 'zzcd@example.com']);
    $hooked = get_comment_author_email_link('', '', '', $comment);
    remove_filter('comment_email', 'antispambot');
    $plain = get_comment_author_email_link('', '', '', $comment);
    add_filter('comment_email', 'antispambot');
    $say('get_comment_author_email_link: scrambled while hooked, plain without', [has_filter('comment_email', 'antispambot'), $hooked !== $plain, $plain]);

    // Comment page links by the rewrite's comments pagination base.
    global $wp_rewrite;
    $base = $wp_rewrite->comments_pagination_base;
    $wp_rewrite->comments_pagination_base = 'zzcp';
    $GLOBALS['post'] = get_post($post);
    $say('get_comments_pagenum_link with a pagination base of its own', (string) preg_replace('#^https?://[^/]+#', '', get_comments_pagenum_link(2, 3)));
    $wp_rewrite->comments_pagination_base = $base;

    // get_post_reply_link: signing in comes back to the post; the texts translated.
    $registration = static fn () => '1';
    add_filter('pre_option_comment_registration', $registration);
    wp_set_current_user(0);
    $say('get_post_reply_link when signing in is needed', (string) preg_replace(['#https?://[^/"&]+#', '/%2F%3Fp%3D\d+|redirect_to=[^"]*/'], ['{site}', 'redirect_to={post}'], (string) get_post_reply_link([], $post)));
    remove_filter('pre_option_comment_registration', $registration);

    // wp_check_comment_flood counts back from now, not from the new comment's date.
    $lastDate = gmdate('Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS);
    wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zzcd earlier', 'comment_author_IP' => '10.9.8.7', 'comment_author_email' => 'zzcd-flood@example.com', 'comment_date_gmt' => $lastDate, 'comment_date' => $lastDate, 'comment_approved' => '1']);
    $say('wp_check_comment_flood: a comment three hours old, the new one five seconds after it', wp_check_comment_flood(false, '10.9.8.7', 'zzcd-flood@example.com', gmdate('Y-m-d H:i:s', strtotime($lastDate . ' UTC') + 5), true));
} finally {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
}
echo json_encode($log);
