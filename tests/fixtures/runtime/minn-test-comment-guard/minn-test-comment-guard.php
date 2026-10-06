<?php
/**
 * Plugin Name: Minn test comment guard
 * Description: Fixture for the comment-form suite: judges comments the way spam plugins do (CleanTalk, Akismet), keyed by a word in the comment: zz-guard-spam is marked spam in pre_comment_approved, zz-guard-die is refused with wp_die in preprocess_comment, zz-guard-error is refused with an error from pre_comment_approved, zz-guard-rewrite has its text changed in preprocess_comment, zz-guard-dupe is named a duplicate, and zz-guard-redirect is sent elsewhere by comment_post_redirect.
 * Version: 1.0.0
 * License: MIT
 */

add_filter('preprocess_comment', static function (array $commentdata): array {
    $content = (string) ($commentdata['comment_content'] ?? '');
    if (str_contains($content, 'zz-guard-die')) {
        wp_die('Blocked by the comment guard.', 'Blocked', ['response' => 403]);
    }
    if (str_contains($content, 'zz-guard-rewrite')) {
        $commentdata['comment_content'] = str_replace('zz-guard-rewrite', 'rewritten by the guard', $content);
    }
    return $commentdata;
});

add_filter('pre_comment_approved', static function ($approved, array $commentdata) {
    $content = (string) ($commentdata['comment_content'] ?? '');
    if (str_contains($content, 'zz-guard-spam')) {
        return 'spam';
    }
    if (str_contains($content, 'zz-guard-error')) {
        return new WP_Error('minn_guard', 'Refused by the comment guard.', 409);
    }
    return $approved;
}, 10, 2);

add_filter('duplicate_comment_id', static function ($dupe, array $commentdata) {
    return str_contains((string) ($commentdata['comment_content'] ?? ''), 'zz-guard-dupe') ? 1 : $dupe;
}, 10, 2);

add_filter('comment_post_redirect', static function (string $location, $comment): string {
    return str_contains((string) $comment->comment_content, 'zz-guard-redirect') ? home_url('/guarded/') : $location;
}, 10, 2);
