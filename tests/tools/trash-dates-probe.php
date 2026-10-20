<?php
/**
 * The dates a post keeps on its way to the trash (probe trash-dates): a
 * floating draft (no GMT date) settles on now; a post published by
 * wp_publish_post (which changes only the status, the zero GMT date kept)
 * takes its own date's GMT on the way, its date kept; the modified time
 * moves for both. Dates are recorded as kept, moved or zero. The probe's
 * pages are removed at the end. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
});
$old = '2020-01-02 03:04:05';
$row = static function (int $id) use ($wpdb, $old): array {
    $r = (array) $wpdb->get_row($wpdb->prepare("SELECT post_status, post_name, post_date, post_date_gmt, post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A);
    foreach (['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'] as $column) {
        $r[$column] = match ($r[$column]) {
            $old => 'kept',
            '0000-00-00 00:00:00' => 'zero',
            default => (string) get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Y-m-d') === substr($r[$column], 0, 10) || gmdate('Y-m-d') === substr($r[$column], 0, 10) ? 'today' : 'other',
        };
    }
    return $r;
};
foreach (['a draft' => false, 'a draft published by wp_publish_post' => true] as $label => $publish) {
    $id = wp_insert_post(['post_title' => 'Zz trash dates', 'post_status' => 'draft', 'post_type' => 'page']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "trash-dates: the page could not be made\n");
        exit(1);
    }
    $made[] = $id;
    $wpdb->update($wpdb->posts, ['post_date' => $old, 'post_date_gmt' => '0000-00-00 00:00:00', 'post_modified' => $old, 'post_modified_gmt' => '0000-00-00 00:00:00'], ['ID' => $id]);
    clean_post_cache($id);
    if ($publish) {
        wp_publish_post($id);
        $say("{$label}: published", $row($id));
    }
    wp_trash_post($id);
    $say("{$label}: trashed", $row($id));
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
