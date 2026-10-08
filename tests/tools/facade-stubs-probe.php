<?php
/**
 * Facade functions that answered with a constant (probe facade-stubs), as
 * the reference answers them: whether the site has more than one author
 * (and after a second one publishes), whether a post in the loop starts a
 * new day, a theme's files by type and depth, balanceTags with and without
 * force or the setting, the taxonomies attachments carry, the columns a
 * query returned and a column's length, the rewrite rules for Apache, a
 * widget's description, the ID3 keys, a scrambled mail subject, the
 * revisions screen URL, wp_targeted_link_rel, and the oEmbed cache post
 * for a URL. The probe's own posts, user, taxonomy and widget, removed at
 * the end. Same protocol as api-probe.php.
 */

global $wpdb, $wp_rewrite;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        wp_delete_user($id);
    }
    delete_transient('is_multi_author');
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);

// Authors.
delete_transient('is_multi_author');
$say('is_multi_author', is_multi_author());
$say('is_multi_author transient', get_transient('is_multi_author'));
$author = (int) wp_insert_user(['user_login' => 'zz_stub_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-stub@example.com', 'role' => 'author']);
$made['users'][] = $author;
$made['posts'][] = (int) wp_insert_post(['post_title' => 'Zz stub by another', 'post_status' => 'publish', 'post_author' => $author]);
$say('is_multi_author after another author publishes', is_multi_author());
$say('is_multi_author transient after', get_transient('is_multi_author'));
$filtered = static fn () => true;
add_filter('is_multi_author', $filtered);
$say('is_multi_author filtered', is_multi_author());
remove_filter('is_multi_author', $filtered);

// A new day in the loop.
$days = [];
foreach (['2001-01-01 09:00:00', '2001-01-01 18:00:00', '2001-01-02 09:00:00'] as $n => $date) {
    $id = (int) wp_insert_post(['post_title' => "Zz stub day {$n}", 'post_status' => 'publish', 'post_date' => $date]);
    $made['posts'][] = $id;
    $days[] = $id;
}
$GLOBALS['previousday'] = '';
$GLOBALS['currentday'] = '';
$seen = [];
foreach ($days as $id) {
    $GLOBALS['post'] = get_post($id);
    setup_postdata($GLOBALS['post']);
    $seen[] = [is_new_day(), $GLOBALS['currentday'], $GLOBALS['previousday']];
    ob_start();
    the_date('Y-m-d', '<h2>', '</h2>');
    $seen[] = ob_get_clean();
}
$say('is_new_day and the_date through the loop', $seen);
wp_reset_postdata();

// A theme's files.
$theme = wp_get_theme();
$files = $theme->get_files('php', 1);
$say('get_files php, one level', [count($files) > 0, array_keys($files) === array_values(array_filter(array_keys($files), 'is_string')), isset($files['functions.php']), substr((string) ($files['functions.php'] ?? ''), -14)]);
$all = $theme->get_files(null, -1);
ksort($all);
$say('get_files every type, every depth', [count($all) > count($files), isset($all['style.css']), isset($all['theme.json']), count(array_filter(array_keys($all), static fn ($f) => str_contains($f, '/'))) > 0]);
$say('get_files html, templates', array_keys(array_filter($theme->get_files('html', 1), static fn ($p, $f) => str_starts_with($f, 'templates/'), ARRAY_FILTER_USE_BOTH)) === [] ? 'none at depth one' : 'some');
$html = $theme->get_files('html', 2);
ksort($html);
$say('get_files html, two levels', array_slice(array_keys($html), 0, 4));
$say('get_files search_parent on a theme with no parent', count($theme->get_files('css', 0, true)) === count($theme->get_files('css', 0)));

// Tags.
$say('balanceTags', [balanceTags('<b>open'), balanceTags('<b>open', true)]);
$on = static fn () => '1';
add_filter('pre_option_use_balanceTags', $on);
$say('balanceTags with the setting on', balanceTags('<i>open <b>more'));
remove_filter('pre_option_use_balanceTags', $on);

// The taxonomies of attachments.
register_taxonomy('zz_media_tax', ['attachment:image'], ['public' => true]);
register_taxonomy('zz_all_media_tax', ['attachment'], ['public' => true]);
$image = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'fields' => 'ids'])[0] ?? 0);
$say('get_attachment_taxonomies', [get_attachment_taxonomies($image), get_attachment_taxonomies(get_post($image)), array_map(static fn ($t) => $t->name, get_attachment_taxonomies($image, 'objects')), get_attachment_taxonomies(['ID' => 0, 'post_mime_type' => 'application/pdf', 'guid' => 'x.pdf'])]);
unregister_taxonomy('zz_media_tax');
unregister_taxonomy('zz_all_media_tax');

// The columns a query returned, and a column's length.
$wpdb->get_results("SELECT ID, post_title AS t, post_status FROM {$wpdb->posts} LIMIT 1");
$say('get_col_info name', $wpdb->get_col_info());
$say('get_col_info name, one column', $wpdb->get_col_info('name', 1));
$say('get_col_info table', $wpdb->get_col_info('table'));
$say('get_col_length', [$wpdb->get_col_length($wpdb->posts, 'post_title'), $wpdb->get_col_length($wpdb->posts, 'post_name'), $wpdb->get_col_length($wpdb->comments, 'comment_author_email'), $wpdb->get_col_length($wpdb->posts, 'ID'), $wpdb->get_col_length($wpdb->posts, 'nope')]);

// Apache's rewrite rules.
$say('mod_rewrite_rules', str_replace(wp_parse_url(home_url(), PHP_URL_PATH) ?? '', '{base}', $wp_rewrite->mod_rewrite_rules()));

// A widget's description (widgets register at init, which a probe run may not have reached).
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
$say('wp_widget_description', [wp_widget_description('search-1') ?? null, wp_widget_description('nope-9')]);
$say('wp_get_attachment_id3_keys', [wp_get_attachment_id3_keys(get_post($image)), wp_get_attachment_id3_keys(get_post($image), 'display')]);
// The descrambled bytes keep the subject's own charset, so they are recorded as hex.
$say('wp_iso_descrambler', array_map('bin2hex', [wp_iso_descrambler('=?iso-8859-1?q?Caf=E9_au_lait?='), wp_iso_descrambler('plain subject'), wp_iso_descrambler('=?utf-8?Q?a_b?=')]));
$revised = (int) wp_insert_post(['post_title' => 'Zz stub revised', 'post_status' => 'draft']);
$made['posts'][] = $revised;
$say('wp_get_post_revisions_url without revisions', wp_get_post_revisions_url($revised));
wp_update_post(['ID' => $revised, 'post_title' => 'Zz stub revised twice']);
$editor = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
$say('wp_get_post_revisions_url anonymous', wp_get_post_revisions_url($revised));
wp_set_current_user($editor);
$url = wp_get_post_revisions_url($revised);
$say('wp_get_post_revisions_url', is_string($url) ? (string) preg_replace('/revision=\d+/', 'revision={latest}', str_replace(admin_url(), '{admin}', $url)) : $url);
wp_set_current_user(0);
$say('wp_targeted_link_rel', wp_targeted_link_rel('<a href="https://x.example" target="_blank">x</a> <a href="/y">y</a>'));
$say('find_oembed_post_id', [$GLOBALS['wp_embed']->find_oembed_post_id('zz-no-such-key')]);
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
