<?php
/**
 * The meta core registers to show in REST, and the footnotes meta a type
 * gets (probe meta-registry): the keys registered for each object type
 * and built-in post type, with their arguments, and which plugin types
 * register_block_core_footnotes_post_meta (on init, at 20) gives footnotes
 * to, by what they support. Same protocol as api-probe.php; the types it
 * registers go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// The arguments as REST reads them; callbacks named, not run.
$shape = static function (array $keys): array {
    $out = [];
    foreach ($keys as $key => $args) {
        if (str_starts_with((string) $key, 'zz') || empty($args['show_in_rest'])) {
            continue;
        }
        foreach (['sanitize_callback', 'auth_callback'] as $callback) {
            $args[$callback] = isset($args[$callback]) ? (is_string($args[$callback]) ? $args[$callback] : gettype($args[$callback])) : null;
        }
        if (is_array($args['show_in_rest']) && isset($args['show_in_rest']['prepare_callback']) && !is_string($args['show_in_rest']['prepare_callback'])) {
            $args['show_in_rest']['prepare_callback'] = gettype($args['show_in_rest']['prepare_callback']);
        }
        ksort($args);
        $out[$key] = $args;
    }
    ksort($out);
    return $out;
};
foreach (['post', 'comment', 'term', 'user'] as $objectType) {
    $say("{$objectType} meta for every subtype", $shape(get_registered_meta_keys($objectType)));
}
foreach (['post', 'page', 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'nav_menu_item'] as $type) {
    $say("post meta for {$type}", $shape(get_registered_meta_keys('post', $type)));
}
foreach (['category', 'post_tag', 'nav_menu', 'wp_pattern_category'] as $taxonomy) {
    $say("term meta for {$taxonomy}", array_keys($shape(get_registered_meta_keys('term', $taxonomy))));
}
foreach (['comment', 'note'] as $commentType) {
    $say("comment meta for {$commentType}", array_keys($shape(get_registered_meta_keys('comment', $commentType))));
}

// The callbacks core and minn-admin registered, by what they answer.
$byRole = [];
foreach (['administrator', 'editor', 'author', 'contributor', 'subscriber'] as $role) {
    $byRole[$role] = (int) (get_users(['role' => $role, 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
}
$authorId = $byRole['author'];
$adminPost = (int) wp_insert_post(['post_title' => 'Zz Registry Admin Post', 'post_status' => 'publish', 'post_author' => 1]);
$authorPost = (int) wp_insert_post(['post_title' => 'Zz Registry Author Post', 'post_status' => 'publish', 'post_author' => $authorId]);
$onAdmin = (int) wp_insert_comment(['comment_post_ID' => $adminPost, 'comment_content' => 'Zz registry comment', 'comment_approved' => 1]);
$onAuthor = (int) wp_insert_comment(['comment_post_ID' => $authorPost, 'comment_content' => 'Zz registry comment', 'comment_approved' => 1]);
$note = (int) wp_insert_comment(['comment_post_ID' => $authorPost, 'comment_content' => 'Zz registry note', 'comment_approved' => 0, 'comment_type' => 'note', 'user_id' => $authorId]);
$answers = [];
foreach (array_filter($byRole) as $role => $user) {
    // The callbacks ask about whoever is signed in, as a request would.
    wp_set_current_user($user);
    $answers[$role] = [
        'note status on a comment of the admin post' => user_can($user, 'edit_comment_meta', $onAdmin, '_wp_note_status'),
        'note status on a comment of their own post' => user_can($user, 'edit_comment_meta', $onAuthor, '_wp_note_status'),
        'note status on a note' => user_can($user, 'edit_comment_meta', $note, '_wp_note_status'),
        'toolbar of their own' => user_can($user, 'edit_user_meta', $user, 'show_admin_bar_front'),
        'toolbar of the admin' => user_can($user, 'edit_user_meta', 1, 'show_admin_bar_front'),
        'preferences of their own' => user_can($user, 'edit_user_meta', $user, $GLOBALS['wpdb']->get_blog_prefix() . 'persisted_preferences'),
        'pattern sync status' => user_can($user, 'edit_post_meta', $adminPost, 'wp_pattern_sync_status'),
    ];
}
wp_set_current_user(0);
$say('callbacks by role', $answers);
$say('sanitized', [
    'toolbar' => array_map(static fn ($v) => sanitize_meta('show_admin_bar_front', $v, 'user', 'user'), ['true', 'false', 'yes', '', 'FALSE', 0]),
    'pattern sync' => sanitize_meta('wp_pattern_sync_status', ' <b>partial</b> ', 'post', 'wp_block'),
    'note status' => sanitize_meta('_wp_note_status', ' <b>x</b> ', 'comment', 'comment'),
]);
foreach ([$note, $onAuthor, $onAdmin] as $id) {
    wp_delete_comment($id, true);
}
wp_delete_post($authorPost, true);
wp_delete_post($adminPost, true);

// Plugin types by what they support, then the init callback that gives footnotes.
$types = [
    'zz_fa' => ['show_in_rest' => true, 'supports' => ['title', 'editor']],
    'zz_fb' => ['show_in_rest' => true, 'supports' => ['title', 'editor', 'custom-fields']],
    'zz_fc' => ['show_in_rest' => true, 'supports' => ['title', 'editor', 'revisions']],
    'zz_fd' => ['show_in_rest' => true, 'supports' => ['title', 'editor', 'custom-fields', 'revisions']],
    'zz_fe' => ['show_in_rest' => true, 'supports' => ['title', 'custom-fields', 'revisions']],
    'zz_ff' => ['show_in_rest' => false, 'supports' => ['title', 'editor', 'custom-fields', 'revisions']],
    'zz_fg' => ['show_in_rest' => true, 'public' => false, 'supports' => ['editor', 'custom-fields', 'revisions']],
];
foreach ($types as $type => $args) {
    register_post_type($type, $args);
}
$say('footnotes init callback', [function_exists('register_block_core_footnotes_post_meta'), has_action('init', 'register_block_core_footnotes_post_meta')]);
if (function_exists('register_block_core_footnotes_post_meta')) {
    register_block_core_footnotes_post_meta();
}
$given = [];
foreach (array_keys($types) as $type) {
    $given[$type] = array_key_exists('footnotes', get_registered_meta_keys('post', $type));
}
$say('footnotes by supports', $given);
$say('footnotes arguments', $shape(['footnotes' => get_registered_meta_keys('post', 'zz_fd')['footnotes'] ?? []]));
foreach (array_keys($types) as $type) {
    unregister_meta_key('post', 'footnotes', $type);
    unregister_post_type($type);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
