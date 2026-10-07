<?php
/**
 * A post type's archive address as the reference gives it (probe
 * archive-links): the posts' own (the home, or the posts page when the
 * front is a page), a type with an archive under its rewrite slug, under a
 * named archive, without rewrites, without the front, and one with no
 * archive or no type at all; each archive's feed, by default and by type;
 * and a plugin on post_type_archive_link and post_type_archive_feed_link,
 * heard with what it was handed. The types are the probe's own and are
 * unregistered at the end. Read only. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
register_post_type('zz_books', ['public' => true, 'has_archive' => true, 'rewrite' => ['slug' => 'zz-books']]);
register_post_type('zz_library', ['public' => true, 'has_archive' => 'zz-library', 'rewrite' => ['slug' => 'zz-library-item']]);
register_post_type('zz_plain', ['public' => true, 'has_archive' => true, 'rewrite' => false]);
register_post_type('zz_unfronted', ['public' => true, 'has_archive' => true, 'rewrite' => ['slug' => 'zz-unfronted', 'with_front' => false]]);
register_post_type('zz_none', ['public' => true, 'has_archive' => false]);
$types = ['post', 'page', 'zz_books', 'zz_library', 'zz_plain', 'zz_unfronted', 'zz_none', 'zz_missing'];

$links = [];
foreach ($types as $type) {
    $links[$type] = [get_post_type_archive_link($type), get_post_type_archive_feed_link($type), get_post_type_archive_feed_link($type, 'atom')];
}
$say('the archive links', $links);

$front = static fn () => 'page';
$postsPage = static fn () => 2;
add_filter('pre_option_show_on_front', $front);
add_filter('pre_option_page_for_posts', $postsPage);
$say('the posts with a page in front', [get_post_type_archive_link('post'), get_post_type_archive_feed_link('post')]);
remove_filter('pre_option_page_for_posts', $postsPage);
$say('a page in front and no posts page', [get_post_type_archive_link('post'), get_post_type_archive_feed_link('post')]);
remove_filter('pre_option_show_on_front', $front);

$heard = [];
$link = static function ($url, $type) use (&$heard) {
    $heard[] = ['post_type_archive_link', $url, $type];
    return $type === 'zz_books' ? $url . '#zz' : $url;
};
$feed = static function ($url, $kind) use (&$heard) {
    $heard[] = ['post_type_archive_feed_link', $url, $kind];
    return $url;
};
add_filter('post_type_archive_link', $link, 10, 2);
add_filter('post_type_archive_feed_link', $feed, 10, 2);
$say('with a plugin on them', [get_post_type_archive_link('zz_books'), get_post_type_archive_feed_link('zz_books'), get_post_type_archive_link('post'), get_post_type_archive_link('zz_none'), $heard]);
remove_filter('post_type_archive_link', $link, 10);
remove_filter('post_type_archive_feed_link', $feed, 10);

foreach (['zz_books', 'zz_library', 'zz_plain', 'zz_unfronted', 'zz_none'] as $type) {
    unregister_post_type($type);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
