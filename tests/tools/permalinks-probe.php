<?php
/**
 * How the reference builds a post's address and what it asks on the way:
 * get_permalink for a published, a draft, a scheduled and a private post,
 * a page, a child page and a draft page, an attachment with and without a
 * parent, a plugin's post type, one not public and one without rewrites,
 * and a revision, each plainly and with the slug left as a placeholder
 * ($leavename); then get_sample_permalink for each, the address the editor
 * shows with the slug to edit; and posts by status (private, trashed, a
 * plugin's) as the owner, an editor and a visitor see them. Same protocol as
 * api-probe.php; the posts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
register_post_type('zz_book', ['public' => true, 'rewrite' => ['slug' => 'books'], 'hierarchical' => false]);
register_post_type('zz_hidden', ['public' => false]);
register_post_type('zz_plain', ['public' => false, 'rewrite' => false, 'query_var' => false]);
register_post_type('zz_asked', ['public' => true, 'rewrite' => false]);
$made = [];
$new = static function (array $postarr) use (&$made): int {
    $id = (int) wp_insert_post($postarr + ['post_status' => 'publish', 'post_type' => 'post']);
    $made[] = $id;
    return $id;
};
$posts = [];
$posts['published post'] = $new(['post_title' => 'zz link published']);
$posts['draft post'] = $new(['post_title' => 'zz link draft', 'post_status' => 'draft']);
$posts['scheduled post'] = $new(['post_title' => 'zz link scheduled', 'post_status' => 'future', 'post_date' => '2099-01-02 03:04:05']);
$posts['private post'] = $new(['post_title' => 'zz link private', 'post_status' => 'private']);
$posts['page'] = $parent = $new(['post_title' => 'zz link page', 'post_type' => 'page']);
$posts['child page'] = $new(['post_title' => 'zz link child', 'post_type' => 'page', 'post_parent' => $parent]);
$posts['draft page'] = $new(['post_title' => 'zz link draft page', 'post_type' => 'page', 'post_status' => 'draft']);
$posts['attachment on a post'] = $new(['post_title' => 'zz link file', 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_parent' => $posts['published post']]);
$posts['attachment alone'] = $new(['post_title' => 'zz link loose file', 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg']);
$posts['book'] = $new(['post_title' => 'zz link book', 'post_type' => 'zz_book']);
$posts['hidden type'] = $new(['post_title' => 'zz link hidden', 'post_type' => 'zz_hidden']);
$posts['type without rewrites'] = $new(['post_title' => 'zz link plain', 'post_type' => 'zz_plain']);
$posts['type with only a query var'] = $new(['post_title' => 'zz link asked', 'post_type' => 'zz_asked']);
$posts['revision'] = (int) _wp_put_post_revision(get_post($posts['published post']));
$made[] = $posts['revision'];
$watch = ['pre_post_link', 'post_link', '_get_page_link', 'page_link', 'post_type_link', 'attachment_link', 'get_page_uri', 'editable_slug', 'pre_wp_unique_post_slug', 'wp_unique_post_slug', 'get_sample_permalink', 'post_link_category'];
$seen = [];
$recorder = static function (string $hook) use (&$seen, $watch): void {
    if (in_array($hook, $watch, true)) {
        $seen[] = $hook . ' ' . json_encode(array_map(static fn ($a) => is_object($a) ? get_class($a) : (is_array($a) ? 'array(' . count($a) . ')' : $a), array_slice(func_get_args(), 1)), JSON_UNESCAPED_SLASHES);
    }
};
$home = home_url();
$mask = static function (string $s) use (&$made, $home): string {
    foreach ($made as $n => $id) {
        $s = (string) preg_replace('/\b' . $id . '\b/', '{post' . $n . '}', $s);
    }
    return str_replace([$home, addcslashes($home, '/')], '{home}', $s);
};
$watching = static function (callable $call) use (&$seen, $recorder, $mask): array {
    $seen = [];
    add_action('all', $recorder);
    $out = $call();
    remove_action('all', $recorder);
    return ['out' => json_decode($mask((string) json_encode($out, JSON_UNESCAPED_SLASHES)), true), 'hooks' => array_map($mask, $seen)];
};
foreach ($posts as $label => $id) {
    $say("{$label}: get_permalink", $watching(static fn () => get_permalink($id)));
    $say("{$label}: leaving the name", $watching(static fn () => get_permalink($id, true)));
    $say("{$label}: get_sample_permalink", $watching(static fn () => get_sample_permalink($id)));
}
$say('a draft named outright', $watching(static fn () => get_sample_permalink($posts['draft post'], 'Another Title', 'a-chosen-name')));
// By status, as the site's owner and as a visitor see them: private ones, trashed, and statuses a plugin registers.
register_post_status('zz_hold', ['protected' => true]);
register_post_status('zz_secret', ['private' => true]);
register_post_status('zz_open', ['public' => true]);
register_post_status('zz_bare', []);
$statuses = [
    'private post' => $posts['private post'],
    'private page' => $new(['post_title' => 'zz link private page', 'post_type' => 'page', 'post_status' => 'private']),
    'private book' => $new(['post_title' => 'zz link private book', 'post_type' => 'zz_book', 'post_status' => 'private']),
    'trashed post' => $new(['post_title' => 'zz link trashed']),
    'unregistered status' => $new(['post_title' => 'zz link nowhere', 'post_name' => 'zz-link-nowhere', 'post_status' => 'zz_nowhere']),
    'draft with only a query var' => $new(['post_title' => 'zz link asked draft', 'post_type' => 'zz_asked', 'post_status' => 'draft']),
];
wp_trash_post($statuses['trashed post']);
foreach (['zz_hold', 'zz_secret', 'zz_open', 'zz_bare'] as $status) {
    $statuses[$status] = $new(['post_title' => "zz link {$status}", 'post_name' => "zz-link-{$status}", 'post_status' => $status]);
    $statuses["{$status} page"] = $new(['post_title' => "zz link {$status} page", 'post_name' => "zz-link-{$status}-page", 'post_type' => 'page', 'post_status' => $status]);
}
foreach (['the owner' => 1, 'an editor' => (int) (get_users(['role' => 'editor', 'number' => 1, 'fields' => 'ID'])[0] ?? 0), 'a visitor' => 0] as $who => $userId) {
    wp_set_current_user($userId);
    $say("by status as {$who}", $watching(static fn () => array_map(static fn (int $id) => get_permalink($id), $statuses))['out']);
}
wp_set_current_user(1);
$sampled = static function (int $id): WP_Post {
    $post = clone get_post($id);
    $post->filter = 'sample';
    return $post;
};
$say('samples by status', $watching(static fn () => [
    'trashed' => get_sample_permalink($statuses['trashed post']),
    'held' => get_sample_permalink($statuses['zz_hold']),
    'draft as a sample' => get_permalink($sampled($posts['draft post'])),
    'held as a sample' => get_permalink($sampled($statuses['zz_hold'])),
    'held page as a sample' => get_permalink($sampled($statuses['zz_hold page'])),
    'trashed as a sample' => get_permalink($sampled($statuses['trashed post'])),
])['out']);
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
foreach (['zz_hold', 'zz_secret', 'zz_open', 'zz_bare'] as $status) {
    unset($GLOBALS['wp_post_statuses'][$status]);
}
foreach (['zz_book', 'zz_hidden', 'zz_plain', 'zz_asked'] as $type) {
    unregister_post_type($type);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
