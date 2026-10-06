<?php
/**
 * How the reference builds a post's address and what it asks on the way:
 * get_permalink for a published, a draft, a scheduled and a private post,
 * a page, a child page and a draft page, an attachment with and without a
 * parent, and a plugin's post type, each plainly and with the slug left as
 * a placeholder ($leavename); then get_sample_permalink for each, the
 * address the editor shows with the slug to edit. Same protocol as
 * api-probe.php; the posts are its own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
register_post_type('zz_book', ['public' => true, 'rewrite' => ['slug' => 'books'], 'hierarchical' => false]);
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
foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
unregister_post_type('zz_book');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
