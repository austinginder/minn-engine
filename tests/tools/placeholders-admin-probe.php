<?php
/**
 * Admin helpers plugins call that the engine held as placeholders, as the
 * reference answers them (probe placeholders-admin): _draft_or_post_title,
 * _post_states for each kind of post (and with display_post_states),
 * _admin_search_query, the meta boxes a screen hides and the classes a
 * closed box gets, register_importer and get_importers,
 * wp_add_privacy_policy_content outside admin_init, get_column_headers,
 * get_inline_data, and enqueue_comment_hotkeys_js. The reference loads its
 * admin includes first. Same protocol as api-probe.php; what it makes and
 * changes is put back.
 */

if (!function_exists('_draft_or_post_title')) {
    require_once ABSPATH . 'wp-admin/includes/admin.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
wp_set_current_user(1);
$made = [];
$new = static function (array $postarr) use (&$made): int {
    $id = (int) wp_insert_post($postarr + ['post_status' => 'publish', 'post_type' => 'post']);
    $made[] = $id;
    return $id;
};
$ids = [];
$mask = static function ($value) use (&$ids) {
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach ($ids as $name => $id) {
        $json = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $json);
    }
    return json_decode($json, true);
};

// Titles.
$ids['titled'] = $new(['post_title' => 'zz probe <b>bold</b> & "quoted"']);
$ids['untitled'] = $new(['post_title' => '', 'post_content' => 'zz probe body only']);
$say('_draft_or_post_title', [_draft_or_post_title($ids['titled']), _draft_or_post_title(get_post($ids['untitled'])), _draft_or_post_title(999999)]);

// Post states, kind by kind.
$kept = ['page_on_front' => get_option('page_on_front'), 'page_for_posts' => get_option('page_for_posts'), 'show_on_front' => get_option('show_on_front'), 'wp_page_for_privacy_policy' => get_option('wp_page_for_privacy_policy'), 'sticky_posts' => get_option('sticky_posts')];
$ids['draft'] = $new(['post_title' => 'zz probe draft', 'post_status' => 'draft']);
$ids['pending'] = $new(['post_title' => 'zz probe pending', 'post_status' => 'pending']);
$ids['private'] = $new(['post_title' => 'zz probe private', 'post_status' => 'private']);
$ids['future'] = $new(['post_title' => 'zz probe future', 'post_status' => 'future', 'post_date' => '2099-01-01 00:00:00']);
$ids['password'] = $new(['post_title' => 'zz probe password', 'post_password' => 'secret']);
$ids['sticky'] = $new(['post_title' => 'zz probe sticky']);
$ids['front'] = $new(['post_title' => 'zz probe front', 'post_type' => 'page']);
$ids['blog'] = $new(['post_title' => 'zz probe blog', 'post_type' => 'page']);
$ids['privacy'] = $new(['post_title' => 'zz probe privacy', 'post_type' => 'page', 'post_status' => 'draft']);
$ids['many'] = $new(['post_title' => 'zz probe many', 'post_status' => 'draft', 'post_password' => 'pw']);
$ids['private page'] = $new(['post_title' => 'zz probe private page', 'post_type' => 'page', 'post_status' => 'private', 'post_password' => 'pw']);
stick_post($ids['sticky']);
stick_post($ids['many']);
update_option('show_on_front', 'page');
update_option('page_on_front', $ids['front']);
update_option('page_for_posts', $ids['blog']);
update_option('wp_page_for_privacy_policy', $ids['privacy']);
$states = [];
update_option('page_for_posts', $ids['blog']);
foreach (['titled', 'draft', 'pending', 'private', 'future', 'password', 'sticky', 'front', 'blog', 'privacy', 'many', 'private page'] as $kind) {
    $states[$kind] = _post_states(get_post($ids[$kind]), false);
}
ob_start();
$returned = _post_states(get_post($ids['draft']));
$states['echoed'] = [$returned, ob_get_clean()];
add_filter('display_post_states', static fn ($list, $post) => $list + ['zz' => 'Zz ' . $post->post_type], 10, 2);
$states['filtered'] = _post_states(get_post($ids['pending']), false);
remove_all_filters('display_post_states');
$_REQUEST['post_status'] = 'draft';
$states['while listing drafts'] = _post_states(get_post($ids['draft']), false);
unset($_REQUEST['post_status']);
$say('_post_states', $mask($states));
unstick_post($ids['sticky']);
unstick_post($ids['many']);
foreach ($kept as $option => $value) {
    update_option($option, $value);
}

// The search box's query.
$_REQUEST['s'] = 'zz "probe" <b> & more\\\'s';
ob_start();
$echoedQuery = _admin_search_query();
$say('_admin_search_query', [$echoedQuery, ob_get_clean()]);
unset($_REQUEST['s']);
ob_start();
_admin_search_query();
$say('_admin_search_query without a search', ob_get_clean());

// Screens by hook name, the meta boxes a screen hides, and a closed box's classes.
register_post_type('zz_box', ['public' => true, 'show_ui' => true]);
register_taxonomy('zz_shelf', 'zz_box', ['public' => true, 'show_ui' => true]);
$screens = [];
foreach (['post', 'page', 'zz_box', 'edit-post', 'edit-zz_box', 'edit-category', 'edit-zz_shelf', 'dashboard', 'upload', 'attachment', 'zz_nope', 'edit-zz_nope'] as $hook) {
    $screen = convert_to_screen($hook);
    $screens[$hook] = [$screen->id, $screen->base, $screen->post_type, $screen->taxonomy];
}
unregister_taxonomy('zz_shelf');
$say('screens', $screens);
$keptHidden = get_user_option('metaboxhidden_post');
$keptClosed = get_user_option('closedpostboxes_post');
delete_user_option(1, 'metaboxhidden_post');
delete_user_option(1, 'closedpostboxes_post');
$hidden = static fn ($screen) => get_hidden_meta_boxes($screen);
$boxes = ['post' => $hidden('post'), 'page' => $hidden('page'), 'custom type' => $hidden('zz_box'), 'dashboard' => $hidden('dashboard'), 'screen object' => $hidden(convert_to_screen('post'))];
update_user_option(1, 'metaboxhidden_post', ['postexcerpt', 'zz-mine']);
$boxes['chosen'] = $hidden('post');
add_filter('default_hidden_meta_boxes', static fn ($list, $screen) => array_merge($list, ['zz-default-' . $screen->id]), 10, 2);
add_filter('hidden_meta_boxes', static fn ($list, $screen, $defaults) => array_merge($list, ['zz-' . ($defaults ? 'defaults' : 'chosen')]), 10, 3);
$boxes['chosen filtered'] = $hidden('post');
delete_user_option(1, 'metaboxhidden_post');
$boxes['defaults filtered'] = $hidden('post');
remove_all_filters('default_hidden_meta_boxes');
remove_all_filters('hidden_meta_boxes');
$classes = ['none closed' => postbox_classes('postexcerpt', 'post')];
update_user_option(1, 'closedpostboxes_post', ['postexcerpt']);
$classes['closed'] = postbox_classes('postexcerpt', 'post');
$classes['another'] = postbox_classes('authordiv', 'post');
add_filter('postbox_classes_post_postexcerpt', static fn ($list) => array_merge($list, ['zz-extra']));
$classes['filtered'] = postbox_classes('postexcerpt', 'post');
remove_all_filters('postbox_classes_post_postexcerpt');
$_GET['edit'] = 'postexcerpt';
$classes['being edited'] = postbox_classes('postexcerpt', 'post');
unset($_GET['edit']);
update_user_option(1, 'closedpostboxes_post', 'not a list');
$classes['not a list'] = postbox_classes('postexcerpt', 'post');
foreach (['metaboxhidden_post' => $keptHidden, 'closedpostboxes_post' => $keptClosed] as $option => $value) {
    $value === false ? delete_user_option(1, $option) : update_user_option(1, $option, $value);
}
unregister_post_type('zz_box');
$say('get_hidden_meta_boxes', $boxes);
$say('postbox_classes', $classes);

// Importers.
$keptImporters = $GLOBALS['wp_importers'] ?? null;
$GLOBALS['wp_importers'] = [];
$importers = [
    'registered' => register_importer('zz-b', 'Zz Bravo', 'Second', '__return_true'),
    'another' => register_importer('zz-a', 'Zz Alpha', 'First', '__return_false'),
    'not callable' => is_wp_error($bad = register_importer('zz-c', 'Zz Charlie', 'Bad', 'zz_no_such_function')) ? [$bad->get_error_code(), $bad->get_error_message()] : $bad,
];
$importers['list'] = get_importers();
$importers['global'] = $GLOBALS['wp_importers'];
$GLOBALS['wp_importers'] = $keptImporters;
if ($keptImporters === null) {
    unset($GLOBALS['wp_importers']);
}
$say('importers', $importers);

// Privacy policy text outside admin_init.
$wrong = [];
$catch = static function ($function, $message, $version) use (&$wrong): void {
    $wrong[] = [$function, $message, $version];
};
add_action('doing_it_wrong_run', $catch, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$privacy = wp_add_privacy_policy_content('Zz Plugin', '<p>zz probe policy</p>');
remove_action('doing_it_wrong_run', $catch, 10);
remove_filter('doing_it_wrong_trigger_error', '__return_false');
$say('wp_add_privacy_policy_content', [$privacy, $wrong]);

// Column headers.
add_filter('manage_edit-zz_cols_columns', static fn ($columns) => $columns + ['cb' => '<input type="checkbox" />', 'title' => 'Title', 'zz' => 'Zz']);
$say('get_column_headers', ['filtered screen' => get_column_headers('edit-zz_cols'), 'again (kept)' => get_column_headers(convert_to_screen('edit-zz_cols')), 'plain screen' => get_column_headers('edit-zz_plain')]);
remove_all_filters('manage_edit-zz_cols_columns');

// The quick edit's hidden data.
$term = wp_insert_term('zz probe tag', 'post_tag');
$ids['tag'] = is_wp_error($term) ? 0 : (int) $term['term_id'];
$ids['inline'] = $new(['post_title' => 'zz probe inline & "data"', 'post_name' => 'zz-probe-inline', 'post_date' => '2026-03-04 05:06:07', 'menu_order' => 3, 'post_password' => 'pw', 'comment_status' => 'closed', 'tags_input' => ['zz probe tag']]);
$ids['inline page'] = $new(['post_title' => 'zz probe inline page', 'post_type' => 'page', 'post_parent' => $ids['front'], 'menu_order' => 2, 'post_date' => '2026-01-02 03:04:05']);
$inline = static function (int $id): string {
    ob_start();
    get_inline_data(get_post($id));
    return (string) ob_get_clean();
};
$asEditor = $inline($ids['inline']);
$asPage = $inline($ids['inline page']);
wp_set_current_user(0);
$asVisitor = $inline($ids['inline']);
wp_set_current_user(1);
$say('get_inline_data', $mask(['post' => $asEditor, 'page' => $asPage, 'visitor' => $asVisitor]));

// The comment screen's keyboard shortcuts.
// The engine ships no copy of the admin's hotkeys script, so a stand-in is registered where it is missing.
$standIn = !wp_script_is('jquery-table-hotkeys', 'registered') && wp_register_script('jquery-table-hotkeys', '/zz-probe-hotkeys.js');
$keptShortcuts = get_user_meta(1, 'comment_shortcuts', true);
$hotkeys = [];
foreach (['false', 'true'] as $setting) {
    update_user_meta(1, 'comment_shortcuts', $setting);
    wp_dequeue_script('jquery-table-hotkeys');
    enqueue_comment_hotkeys_js();
    $hotkeys[$setting] = wp_script_is('jquery-table-hotkeys', 'enqueued');
}
wp_dequeue_script('jquery-table-hotkeys');
if ($standIn) {
    wp_deregister_script('jquery-table-hotkeys');
}
update_user_meta(1, 'comment_shortcuts', $keptShortcuts);
$say('enqueue_comment_hotkeys_js', $hotkeys);
// A script asked for before it is registered waits, and joins the queue once it is.
wp_enqueue_script('zz-probe-early');
$early = ['asked' => [wp_script_is('zz-probe-early', 'enqueued'), wp_script_is('zz-probe-early', 'registered'), in_array('zz-probe-early', wp_scripts()->queue, true)]];
wp_register_script('zz-probe-early', '/zz-probe-early.js');
$early['registered'] = [wp_script_is('zz-probe-early', 'enqueued'), in_array('zz-probe-early', wp_scripts()->queue, true)];
wp_dequeue_script('zz-probe-early');
wp_deregister_script('zz-probe-early');
wp_enqueue_style('zz-probe-early-style');
$early['style asked'] = wp_style_is('zz-probe-early-style', 'enqueued');
wp_register_style('zz-probe-early-style', '/zz-probe-early.css');
$early['style registered'] = wp_style_is('zz-probe-early-style', 'enqueued');
wp_dequeue_style('zz-probe-early-style');
wp_deregister_style('zz-probe-early-style');
$say('enqueued before registered', $early);

wp_set_current_user(0);
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
if ($ids['tag'] > 0) {
    wp_delete_term($ids['tag'], 'post_tag');
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
