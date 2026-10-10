<?php
/**
 * The rest of revision.php (probe revision-remaining): the revisioned fields
 * and the data a revision or an autosave is made from, whether revisions are
 * enabled (by type and by the keep filter), revisions saved by hand (a
 * change, no change, the first one on insert), the latest revision and the
 * count, a revision deleted, and meta registered as revisioned: whether it
 * changed, and copied onto a revision. The probe's posts and meta are removed
 * at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            // Only real revision ids: a cast of an object is 1, and post 1 is shared fixture content.
            foreach (wp_get_post_revisions($id) as $revision) {
                $revision = $revision instanceof WP_Post ? $revision->ID : $revision;
                if (is_int($revision) && $revision > 0 && get_post_type($revision) === 'revision') {
                    wp_delete_post($revision, true);
                }
            }
            wp_delete_post($id, true);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code()];
    }
    if ($value instanceof WP_Post) {
        return ['post' => $mask($value->ID), 'type' => $value->post_type, 'parent' => $mask($value->post_parent)];
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('/20\d\d-\d\d-\d\d \d\d:\d\d:\d\d/', '{now}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
// The newest revision of a post gets a label the first time it is seen.
$label = static function (int $post, string $name) use (&$ids): ?int {
    $revisions = wp_get_post_revisions($post, ['orderby' => 'ID', 'order' => 'DESC']);
    $first = $revisions === [] ? null : reset($revisions);
    $latest = $first instanceof WP_Post ? $first->ID : null;
    if ($latest !== null && !isset($ids[$latest])) {
        $ids[$latest] = $name;
    }
    return $latest;
};

$say('the revisioned fields', [
    _wp_post_revision_fields(), _wp_post_revision_fields(['post_title' => 'x']), _wp_post_revision_fields([], true),
    (static function () {
        add_filter('_wp_post_revision_fields', $filter = static fn ($fields, $post) => $fields + ['zz_field' => 'ZZ ' . (is_array($post) ? count($post) : gettype($post))], 10, 2);
        $fields = _wp_post_revision_fields(['post_title' => 't', 'post_content' => 'c']);
        remove_filter('_wp_post_revision_fields', $filter, 10);
        return $fields;
    })(),
]);

$made[] = $post = (int) wp_insert_post(['post_title' => 'zz rv post', 'post_content' => 'First body.', 'post_excerpt' => 'First excerpt.', 'post_status' => 'publish', 'post_date' => '1988-07-08 09:10:11', 'post_author' => 1]);
$ids[$post] = 'post';
$made[] = $page = (int) wp_insert_post(['post_title' => 'zz rv page', 'post_type' => 'page', 'post_status' => 'publish']);
$ids[$page] = 'page';
$made[] = $draft = (int) wp_insert_post(['post_title' => 'zz rv draft', 'post_content' => 'Draft body.', 'post_status' => 'draft']);
$ids[$draft] = 'draft';
$onInsert = $label($post, 'rev-on-insert');

$data = static function ($post, bool $autosave) use ($mask) {
    $row = _wp_post_revision_data($post, $autosave);
    ksort($row);
    return $mask($row);
};
$say('the data a revision is made from', [
    $data(get_post($post, ARRAY_A), false), $data(get_post($post, ARRAY_A), true), $data(get_post($post), false),
    $data(['ID' => $post, 'post_title' => 'Given title', 'post_content' => 'Given', 'post_excerpt' => '', 'post_date' => '1988-01-01 00:00:00', 'post_date_gmt' => '1988-01-01 00:00:00'], false),
]);

register_post_type('zz_rv_plain', ['public' => true, 'supports' => ['title', 'editor']]);
$made[] = $plain = (int) wp_insert_post(['post_title' => 'zz rv plain', 'post_type' => 'zz_rv_plain', 'post_status' => 'publish']);
$ids[$plain] = 'plain';
$enabled = [wp_revisions_enabled(get_post($post)), wp_revisions_enabled(get_post($page)), wp_revisions_enabled(get_post($plain)), wp_revisions_enabled(get_post($draft))];
add_filter('wp_revisions_to_keep', $none = static fn () => 0);
$enabled[] = wp_revisions_enabled(get_post($post));
remove_filter('wp_revisions_to_keep', $none, 10);
add_filter('wp_zz_rv_plain_revisions_to_keep', $some = static fn () => 3);
$enabled[] = wp_revisions_enabled(get_post($plain));
remove_filter('wp_zz_rv_plain_revisions_to_keep', $some, 10);
$say('revisions enabled', [$onInsert === null ? 'none on insert' : '{rev-on-insert}', $enabled]);

$saved = [];
$saved[] = wp_save_post_revision($post);
$label($post, 'rev-a');
global $wpdb;
$wpdb->update($wpdb->posts, ['post_content' => 'Second body.'], ['ID' => $post]);
clean_post_cache($post);
$saved[] = wp_save_post_revision($post);
$label($post, 'rev-b');
$saved[] = wp_save_post_revision($post);
$saved[] = wp_save_post_revision($plain);
$saved[] = wp_save_post_revision($draft);
$label($draft, 'rev-draft');
$saved[] = wp_save_post_revision(999999);
$saved[] = wp_get_post_revisions($post, ['fields' => 'ids']);
$saved[] = wp_get_post_revisions($post, ['fields' => 'ids', 'order' => 'ASC']);
$saved[] = array_keys(wp_get_post_revisions($post));
$saved = $mask($saved);
$saved[] = $mask(wp_get_latest_revision_id_and_total_count($post));
$saved[] = $mask(wp_get_latest_revision_id_and_total_count($plain));
$saved[] = $mask(wp_get_latest_revision_id_and_total_count(999999));
$saved[] = $mask(array_map(static fn ($revision) => [$revision->post_name, $revision->post_content, $revision->post_status], array_values(wp_get_post_revisions($post, ['orderby' => 'ID', 'order' => 'ASC']))));
$say('revisions saved', $saved);

$made[] = $fresh = (int) wp_insert_post(['post_title' => 'zz rv fresh', 'post_content' => 'Fresh body.', 'post_status' => 'publish']);
$ids[$fresh] = 'fresh';
$before = count(wp_get_post_revisions($fresh));
wp_save_post_revision_on_insert($fresh, get_post($fresh), true);
$afterUpdate = count(wp_get_post_revisions($fresh));
wp_save_post_revision_on_insert($fresh, get_post($fresh), false);
$afterInsert = count(wp_get_post_revisions($fresh));
$label($fresh, 'rev-fresh');
$say('a revision on insert', [$before, $afterUpdate, $afterInsert]);

$deleting = $label($post, 'rev-b');
$heard = [];
add_action('wp_delete_post_revision', $listener = static function ($id, $revision) use (&$heard) {
    $heard[] = [$id, $revision->post_type];
}, 10, 2);
$deleted = [$mask(wp_delete_post_revision($deleting)), $mask(wp_delete_post_revision($post)), $mask(wp_delete_post_revision(999999)), $mask($heard), count(wp_get_post_revisions($post))];
remove_action('wp_delete_post_revision', $listener, 10);
$say('a revision deleted', $deleted);

register_post_meta('post', 'zz_rv_meta', ['type' => 'string', 'single' => true, 'revisions_enabled' => true, 'show_in_rest' => true]);
update_post_meta($post, 'zz_rv_meta', 'meta one');
$latest = wp_get_post_revisions($post, ['orderby' => 'ID', 'order' => 'DESC']);
$last = reset($latest) ?: null;
$changed = [wp_check_revisioned_meta_fields_have_changed(false, $last, get_post($post)), wp_check_revisioned_meta_fields_have_changed(true, $last, get_post($post))];
$newest = (int) wp_save_post_revision($post);
if ($newest > 0) {
    $ids[$newest] = 'rev-meta';
}
$changed[] = $mask($newest);
$changed[] = get_metadata('post', $newest, 'zz_rv_meta', true);
update_post_meta($post, 'zz_rv_meta', 'meta two');
$made[] = $copy = (int) wp_insert_post(['post_title' => 'zz rv copy target', 'post_status' => 'draft']);
$ids[$copy] = 'copy';
wp_save_revisioned_meta_fields($copy, $post);
$changed[] = get_metadata('post', $copy, 'zz_rv_meta', true);
$latest = wp_get_post_revisions($post, ['orderby' => 'ID', 'order' => 'DESC']);
$changed[] = wp_check_revisioned_meta_fields_have_changed(false, reset($latest) ?: null, get_post($post));
delete_post_meta($post, 'zz_rv_meta');
delete_post_meta($copy, 'zz_rv_meta');
unregister_post_meta('post', 'zz_rv_meta');
$say('revisioned meta', $changed);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
