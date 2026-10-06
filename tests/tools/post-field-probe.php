<?php
/**
 * get_post_field in each context (probe post-field): a post's columns
 * through sanitize_post_field for display, raw, edit, attribute, js and
 * db; text with quotes, tags and ampersands, a guid with a query, the
 * integer columns, a column that is not there, a post that is not there,
 * and a post object handed in. Same protocol as api-probe.php; the post
 * goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
global $wpdb;
$id = (int) wp_insert_post(['post_title' => 'zz probe "field" <b>bold</b> & more', 'post_content' => "<p>Body & 'quotes' <script>x</script></p>", 'post_excerpt' => 'Ex & <i>cerpt</i>', 'post_status' => 'publish', 'menu_order' => 3]);
$wpdb->update($wpdb->posts, ['guid' => home_url('/?post_type=zz&p=' . $id), 'post_mime_type' => 'text/x-zz'], ['ID' => $id]);
clean_post_cache($id);
$mask = static fn ($v) => is_string($v) ? str_replace([(string) $id, home_url()], ['{id}', '{home}'], $v) : ($v === $id ? '{id}' : $v);
foreach (['display', 'raw', 'edit', 'attribute', 'js', 'db', 'rss'] as $context) {
    $row = [];
    foreach (['post_title', 'post_content', 'post_excerpt', 'guid', 'post_name', 'post_status', 'ID', 'post_parent', 'menu_order', 'comment_count', 'post_mime_type', 'nope'] as $field) {
        $value = get_post_field($field, $id, $context);
        $row[$field] = [gettype($value), $mask($value)];
    }
    $say("context {$context}", $row);
}
$say('missing post', [get_post_field('post_title', 999999), get_post_field('post_title', 999999, 'raw')]);
$say('post object', $mask(get_post_field('guid', get_post($id))));
$say('default context', $mask(get_post_field('post_title', $id)));
wp_delete_post($id, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
