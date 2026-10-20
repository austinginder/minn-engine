<?php
/**
 * Writes the database door refuses for their text (bytes that are not
 * UTF-8), as the API reports them: a post's insert and update errors name
 * the field and fire no save hooks; a comment's insert is false and its
 * update an error; a term whose name sanitizes to nothing is an invalid
 * name, one whose description is refused leaves its term row; meta writes
 * say false and fire no "added" or "updated" action. Same protocol as
 * api-probe.php; the probe's rows (zzrefused) are removed after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$quiet = $wpdb->suppress_errors(true);
$heard = [];
foreach (['wp_insert_post_data', 'pre_post_update', 'save_post', 'wp_insert_post', 'add_post_meta', 'added_post_meta', 'update_post_meta', 'updated_post_meta', 'wp_insert_comment', 'wp_insert_term_data', 'create_term'] as $hook) {
    add_filter($hook, static function ($value = null) use ($hook, &$heard) {
        $heard[] = $hook;
        return $value;
    });
}
$error = static fn ($result) => is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message(), $result->get_error_data()] : $result;
$bad = "x\xFFy";
$heard = [];
$say('a post whose content is refused: the error', $error(wp_insert_post(['post_title' => 'zzrefused', 'post_content' => $bad, 'post_status' => 'draft'], true)));
$say('the hooks heard', $heard);
$say('without the error asked for', wp_insert_post(['post_title' => 'zzrefused', 'post_content' => $bad, 'post_status' => 'draft']));
$post = wp_insert_post(['post_title' => 'zzrefused', 'post_status' => 'draft']);
$term = null;
try {
    $heard = [];
    $say('an update whose content is refused', $error(wp_update_post(['ID' => $post, 'post_content' => $bad], true)));
    $say('the update\'s hooks', $heard);
    $heard = [];
    $say('a meta value refused: add_post_meta', add_post_meta($post, 'zzrefused', $bad));
    $mid = add_post_meta($post, 'zzrefused', 'ok');
    $say('update_post_meta to it', update_post_meta($post, 'zzrefused', $bad));
    $say('update_metadata_by_mid to it', update_metadata_by_mid('post', $mid, $bad));
    $say('their hooks, and what is stored', [$heard, get_post_meta($post, 'zzrefused')]);
    $heard = [];
    $say('a comment whose content is refused', wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => $bad]));
    $say('the comment\'s hooks', $heard);
    $comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'fine']);
    $say('a comment updated to it', [$error(wp_update_comment(['comment_ID' => $comment, 'comment_content' => $bad], true)), wp_update_comment(['comment_ID' => $comment, 'comment_content' => $bad])]);
    foreach (["zz\xFFrefused", '<i></i>', '<b> </b>'] as $name) {
        $say('a term named ' . json_encode(mb_convert_encoding($name, 'UTF-8', 'UTF-8')), $error(wp_insert_term($name, 'category')));
    }
    $heard = [];
    $say('a term whose description is refused', $error(wp_insert_term('zzrefused term', 'category', ['description' => $bad])));
    $say('the term\'s hooks, and the term row left', [$heard, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.name = 'zzrefused term' AND tt.term_id IS NULL")]);
} finally {
    if (is_int($post) && $post > 0) {
        wp_delete_post($post, true);
    }
    $wpdb->query("DELETE t FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.name = 'zzrefused term' AND tt.term_id IS NULL");
    // That term row took an id the taxonomy table never gave: the two counters are put level again, as the suites' id masks expect.
    $next = (int) $wpdb->get_var($wpdb->prepare('SELECT MAX(AUTO_INCREMENT) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)', $wpdb->terms, $wpdb->term_taxonomy));
    foreach ([$wpdb->terms, $wpdb->term_taxonomy] as $table) {
        $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = {$next}");
    }
}
$wpdb->suppress_errors($quiet);
echo json_encode($log);
