<?php
/**
 * What wp_insert_post fills in when the caller leaves it to it, the round
 * trips' findings (probe insert-defaults): the comment and ping status of a
 * post, a page, an attachment, a revision and a plugin's types with and
 * without comment support; a revision's slug left as it is when another
 * revision has it; and the guid of a plugin type's post with and without
 * public addresses. Same protocol as api-probe.php; the posts it makes go
 * at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
register_post_type('zz_talky', ['public' => true, 'supports' => ['title', 'comments', 'trackbacks']]);
register_post_type('zz_quiet', ['public' => true, 'supports' => ['title']]);
register_post_type('zz_hidden', ['public' => false, 'supports' => ['title', 'comments']]);
register_post_type('zz_plain', ['public' => false, 'rewrite' => false, 'query_var' => false, 'supports' => ['title']]);
register_post_type('zz_tree', ['public' => true, 'hierarchical' => true, 'supports' => ['title']]);
$made = [];
$discussion = static function (int $id) use (&$made): array {
    $made[] = $id;
    $post = get_post($id);
    return [$post->post_type, $post->comment_status, $post->ping_status];
};
$options = [get_option('default_comment_status'), get_option('default_ping_status')];
$rows = [];
foreach (['open', 'closed'] as $setting) {
    update_option('default_comment_status', $setting);
    update_option('default_ping_status', $setting);
    $parent = (int) wp_insert_post(['post_title' => "zz probe defaults {$setting}", 'post_status' => 'draft']);
    $rows[$setting] = [
        $discussion($parent),
        $discussion((int) wp_insert_post(['post_title' => 'zz probe page', 'post_type' => 'page', 'post_status' => 'draft'])),
        $discussion((int) wp_insert_attachment(['post_title' => 'zz probe file', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], '2026/10/zz-probe-defaults.png', $parent)),
        $discussion((int) wp_insert_post(['post_title' => 'zz probe talky', 'post_type' => 'zz_talky', 'post_status' => 'draft'])),
        $discussion((int) wp_insert_post(['post_title' => 'zz probe quiet', 'post_type' => 'zz_quiet', 'post_status' => 'draft'])),
        $discussion((int) _wp_put_post_revision(get_post($parent))),
    ];
}
update_option('default_comment_status', $options[0]);
update_option('default_ping_status', $options[1]);
$say('discussion', $rows);
$parent = (int) wp_insert_post(['post_title' => 'zz probe revised', 'post_status' => 'publish']);
$made[] = $parent;
$first = (int) _wp_put_post_revision(get_post($parent));
$second = (int) _wp_put_post_revision(get_post($parent));
$made[] = $first;
$made[] = $second;
$slug = static fn (int $id) => str_replace((string) $parent, '{parent}', get_post($id)->post_name);
$say('revision slugs', [$slug($first), $slug($second)]);
global $wpdb;
$stored = static fn (int $id): string => str_replace([(string) $id, home_url()], ['{id}', '{home}'], (string) $wpdb->get_var($wpdb->prepare("SELECT guid FROM {$wpdb->posts} WHERE ID = %d", $id)));
$guid = static function (string $type, string $status = 'publish') use (&$made, $stored): string {
    $id = (int) wp_insert_post(['post_title' => 'zz probe guid', 'post_type' => $type, 'post_status' => $status]);
    $made[] = $id;
    return $stored($id);
};
$say('guids', [
    'post' => $guid('post'),
    'page' => $guid('page'),
    'public type' => $guid('zz_talky'),
    'hidden type' => $guid('zz_hidden'),
    'type without rewrite' => $guid('zz_plain'),
    'draft post' => $guid('post', 'draft'),
    'draft page' => $guid('page', 'draft'),
    'private post' => $guid('post', 'private'),
    'revision' => $stored($first),
]);
$plain = (int) wp_insert_post(['post_title' => 'zz probe plain guid', 'post_type' => 'zz_plain', 'post_status' => 'publish']);
$made[] = $plain;
wp_update_post(['ID' => $plain, 'post_title' => 'zz probe plain guid again']);
$afterUpdate = $stored($plain);
wp_update_post(get_post($plain, ARRAY_A));
$afterWhole = $stored($plain);
wp_update_post(['ID' => $plain, 'guid' => 'https://example.com/zz-new?a=1&b=2']);
$afterGiven = $stored($plain);
$say('plain guid through updates', [$afterUpdate, $afterWhole, $afterGiven, str_replace((string) $plain, '{id}', get_post_field('guid', $plain)), str_replace((string) $plain, '{id}', get_post_field('guid', $plain, 'raw'))]);
$given = (int) wp_insert_post(['post_title' => 'zz probe given guid', 'post_status' => 'publish', 'guid' => 'https://example.com/zz?a=1&b=2']);
$made[] = $given;
$say('given guid', $stored($given));
// Which slugs stand beside one another: a post and a page, two pages under different parents, a plugin's type.
$slugOf = static function (array $postarr) use (&$made): string {
    $id = (int) wp_insert_post($postarr + ['post_status' => 'publish']);
    $made[] = $id;
    return get_post($id)->post_name;
};
$top = (int) wp_insert_post(['post_title' => 'zz probe top', 'post_type' => 'page', 'post_status' => 'publish']);
$made[] = $top;
$say('slug scopes', [
    'post' => $slugOf(['post_title' => 'zz probe same']),
    'second post' => $slugOf(['post_title' => 'zz probe same']),
    'page' => $slugOf(['post_title' => 'zz probe same', 'post_type' => 'page']),
    'child page' => $slugOf(['post_title' => 'zz probe same', 'post_type' => 'page', 'post_parent' => $top]),
    'second child page' => $slugOf(['post_title' => 'zz probe same', 'post_type' => 'page', 'post_parent' => $top]),
    'plugin type' => $slugOf(['post_title' => 'zz probe same', 'post_type' => 'zz_talky']),
    'plugin tree' => $slugOf(['post_title' => 'zz probe same', 'post_type' => 'zz_tree']),
    'draft given' => $slugOf(['post_title' => 'zz probe same', 'post_name' => 'zz-probe-same', 'post_status' => 'draft']),
    'pending given' => $slugOf(['post_title' => 'zz probe same', 'post_name' => 'zz-probe-same', 'post_status' => 'pending']),
    'private' => $slugOf(['post_title' => 'zz probe same', 'post_status' => 'private']),
    'page named like top' => $slugOf(['post_title' => 'zz probe top', 'post_type' => 'page', 'post_parent' => $top]),
]);
// Attachments share every type's slugs; a trashed post gives its slug up; numbers and feed words are reserved.
$attachment = static function (string $title, int $parent = 0) use (&$made): string {
    $id = (int) wp_insert_attachment(['post_title' => $title, 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], '2026/10/zz-probe-slug.png', $parent);
    $made[] = $id;
    return get_post($id)->post_name;
};
$trashed = (int) wp_insert_post(['post_title' => 'zz probe gone', 'post_status' => 'publish']);
$made[] = $trashed;
wp_trash_post($trashed);
$say('slug neighbours', [
    'attachment like a post' => $attachment('zz probe same'),
    'attachment like a page child' => $attachment('zz probe same', $top),
    'post like an attachment' => $slugOf(['post_title' => 'zz probe pic']) . ' / ' . $attachment('zz probe pic') . ' / ' . $slugOf(['post_title' => 'zz probe pic', 'post_type' => 'page']),
    'page like an attachment' => $attachment('zz probe pix') . ' / ' . $slugOf(['post_title' => 'zz probe pix', 'post_type' => 'page']) . ' / ' . $slugOf(['post_title' => 'zz probe pix', 'post_type' => 'zz_talky']),
    'post like a trashed one' => $slugOf(['post_title' => 'zz probe gone']),
    'trashed one after' => get_post($trashed)->post_name,
    'numeric post' => $slugOf(['post_title' => '2026']),
    'small numeric post' => $slugOf(['post_title' => '12']),
    'numeric page' => $slugOf(['post_title' => '2026', 'post_type' => 'page']),
    'post named feed' => $slugOf(['post_title' => 'feed']),
    'post named embed' => $slugOf(['post_title' => 'embed']),
    'page named feed' => $slugOf(['post_title' => 'feed', 'post_type' => 'page']),
    'page named embed' => $slugOf(['post_title' => 'embed', 'post_type' => 'page']),
    'child named page' => $slugOf(['post_title' => 'page', 'post_type' => 'page', 'post_parent' => $top]),
    'attachment named feed' => $attachment('feed'),
    'post named attachment' => $slugOf(['post_title' => 'attachment']),
]);
$say('slug more neighbours', [
    'page child like an attachment child' => $attachment('zz probe kid', $top) . ' / ' . $slugOf(['post_title' => 'zz probe kid', 'post_type' => 'page', 'post_parent' => $top]),
    'second top page' => $slugOf(['post_title' => 'zz probe top', 'post_type' => 'page']),
    'numeric plugin type' => $slugOf(['post_title' => '2026', 'post_type' => 'zz_talky']),
    'numeric plugin tree' => $slugOf(['post_title' => '2026', 'post_type' => 'zz_tree']),
    'page named page' => $slugOf(['post_title' => 'page', 'post_type' => 'page']),
    'page named page2' => $slugOf(['post_title' => 'page2', 'post_type' => 'page']),
    'child named page2' => $slugOf(['post_title' => 'page2', 'post_type' => 'page', 'post_parent' => $top]),
    'menu items' => $slugOf(['post_title' => 'zz probe link', 'post_type' => 'nav_menu_item']) . ' / ' . $slugOf(['post_title' => 'zz probe link', 'post_type' => 'nav_menu_item']),
    'post after an attachment' => $attachment('zz probe att') . ' / ' . $slugOf(['post_title' => 'zz probe att']) . ' / ' . $slugOf(['post_title' => 'zz probe att', 'post_type' => 'zz_talky']),
    'top page like an attachment child' => $slugOf(['post_title' => 'zz probe kid', 'post_type' => 'page']),
    'tree like a page' => $slugOf(['post_title' => 'zz probe tree', 'post_type' => 'page']) . ' / ' . $slugOf(['post_title' => 'zz probe tree', 'post_type' => 'zz_tree']),
    'child named feed' => $slugOf(['post_title' => 'feed', 'post_type' => 'page', 'post_parent' => $top]),
    'feed words' => implode(' ', array_map(static fn (string $word): string => $slugOf(['post_title' => $word]), ['rss2', 'rss', 'rdf', 'atom', 'comments', 'trackback', 'page', 'wp-json'])),
    'attachment numbers' => $attachment('2027') . ' ' . $attachment('embed') . ' ' . $attachment('page3'),
]);
$asked = [];
foreach ([['zz-probe-same', 'publish', 'post'], ['zz-probe-same', 'future', 'post'], ['zz-probe-same', 'private', 'page'], ['zz-probe-same', 'inherit', 'revision'], ['zz-probe-same', 'publish', 'revision'], ['zz-probe-same', 'publish', 'user_request'], ['zz-probe-same', 'publish', 'nav_menu_item'], ['zz-probe-same', 'inherit', 'attachment'], ['ZZ Probe Same', 'publish', 'post'], ['zz-probe-same', 'draft', 'post'], ['zz-probe-same', 'trash', 'post']] as [$wanted, $status, $type]) {
    $asked["{$wanted} {$status} {$type}"] = wp_unique_post_slug($wanted, 0, $status, $type, 0);
}
$say('slug asked', $asked);
// Numbers beside the date archives, by permalink structure.
global $wp_rewrite;
$structure = (string) get_option('permalink_structure');
$numbers = [];
try {
    foreach (['/%postname%/', '/%monthnum%/%postname%/', '/%day%/%postname%/', '/blog/%postname%/', '/%year%/%postname%/', '/%year%/%monthnum%/%postname%/', '/%year%/%monthnum%/%day%/%postname%/', '/archives/%post_id%', '/%category%/%postname%/', '/%postname%-%year%/'] as $candidate) {
        $wp_rewrite->set_permalink_structure($candidate);
        foreach (['0', '12', '13', '31', '32', '2026', '20261', '012'] as $number) {
            $numbers[$candidate][$number] = wp_unique_post_slug($number, 0, 'publish', 'post', 0);
        }
        $numbers[$candidate]['page 13'] = wp_unique_post_slug('13', 0, 'publish', 'page', 0);
        $numbers[$candidate]['talky 13'] = wp_unique_post_slug('13', 0, 'publish', 'zz_talky', 0);
    }
} finally {
    $wp_rewrite->set_permalink_structure($structure);
}
$say('slug numbers', $numbers);
$kept = (int) wp_insert_post(['post_title' => 'zz probe kept number', 'post_status' => 'publish']);
$made[] = $kept;
$wpdb->update($wpdb->posts, ['post_name' => '7'], ['ID' => $kept]);
clean_post_cache($kept);
wp_update_post(['ID' => $kept, 'post_title' => 'zz probe kept number again']);
$long = str_repeat('zz-probe-long-', 15);
$say('slug edges', [
    'update keeps a number' => get_post($kept)->post_name,
    'asked for its own number' => wp_unique_post_slug('7', $kept, 'publish', 'post', 0),
    'another asking that number' => wp_unique_post_slug('7', 0, 'publish', 'post', 0),
    'long' => [strlen($slugOf(['post_title' => $long])), $slugOf(['post_title' => $long]), $slugOf(['post_title' => $long])],
    'long cut at a hyphen' => [$slugOf(['post_title' => 'ab' . $long]), $slugOf(['post_title' => 'ab' . $long])],
    'suffixed already' => $slugOf(['post_title' => 'zz probe same 2']) . ' / ' . $slugOf(['post_title' => 'zz probe same 2']),
]);
wp_set_current_user(1);
$say('slug as admin', [
    'pending given' => $slugOf(['post_title' => 'zz probe same', 'post_name' => 'zz-probe-same', 'post_status' => 'pending']),
    'pending untitled slug' => $slugOf(['post_title' => 'zz probe same', 'post_status' => 'pending']),
]);
$pending = (int) wp_insert_post(['post_title' => 'zz probe pend', 'post_name' => 'zz-probe-pend', 'post_status' => 'pending']);
$made[] = $pending;
wp_set_current_user(0);
wp_update_post(['ID' => $pending, 'post_title' => 'zz probe pend again']);
$say('pending updated by a visitor', get_post($pending)->post_name);
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
foreach (['zz_talky', 'zz_quiet', 'zz_hidden', 'zz_plain', 'zz_tree'] as $type) {
    unregister_post_type($type);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
