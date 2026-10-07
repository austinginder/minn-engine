<?php
/**
 * Plugin Name: Minn test feed
 * Description: Fixture for the feed-hooks suite, loaded by the engine and the reference alike. A request that carries X-Minn-Feed naming a run the suite opened (wp-content/minn-feed/<run>.open exists) gets a plugin on every feed seam: each feed action prints a marker where it fires (with what it was handed), each feed filter marks what it returns, a custom feed answers ?feed=zzfeed, and X-Minn-Feed-Mode can ask for the RSS 2.0 handler replaced or the feed redirected from template_redirect. Without such a run the header does nothing.
 * License: MIT
 */

$minnFeedRun = (string) ($_SERVER['HTTP_X_MINN_FEED'] ?? '');
$minnFeedDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-feed';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnFeedRun) !== 1 || !is_file("{$minnFeedDir}/{$minnFeedRun}.open")) {
    return;
}
$minnFeedMode = (string) ($_SERVER['HTTP_X_MINN_FEED_MODE'] ?? 'markers');

/** An argument in words both stacks share: ids and objects by kind. */
function minn_test_feed_describe($value): string
{
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value) && $value !== '')) {
        return 'int';
    }
    if (is_object($value)) {
        return get_class($value);
    }
    return is_string($value) ? $value : gettype($value);
}

foreach (['rss_tag_pre', 'rss2_ns', 'rss2_head', 'rss2_item', 'atom_ns', 'atom_head', 'atom_entry', 'atom_author', 'rdf_ns', 'rdf_header', 'rdf_item', 'rss_head', 'rss_item', 'rss2_comments_ns', 'commentsrss2_head', 'commentrss2_item', 'atom_comments_ns', 'comments_atom_head', 'comment_atom_entry'] as $minnFeedHook) {
    add_action($minnFeedHook, static function (...$args) use ($minnFeedHook): void {
        echo '<!--zz:' . $minnFeedHook . '(' . implode(',', array_map('minn_test_feed_describe', $args)) . ')-->';
    }, 10, 3);
}
foreach (['the_title_rss', 'the_content_feed', 'the_excerpt_rss', 'the_permalink_rss', 'comments_link_feed', 'the_category_rss', 'the_guid', 'the_author', 'bloginfo_rss', 'get_bloginfo_rss', 'wp_title_rss', 'get_wp_title_rss', 'comment_author_rss', 'comment_text_rss', 'comment_link', 'rss_update_period', 'rss_update_frequency', 'self_link', 'get_feed_build_date', 'the_generator', 'rss_enclosure', 'atom_enclosure', 'post_comments_feed_link', 'feed_content_type'] as $minnFeedFilter) {
    add_filter($minnFeedFilter, static function ($value, ...$args) use ($minnFeedFilter) {
        return $value . '[zz:' . $minnFeedFilter . (array_filter($args, 'is_scalar') !== [] ? ':' . implode(',', array_map('minn_test_feed_describe', array_filter($args, 'is_scalar'))) : '') . ']';
    }, 10, 3);
}
add_action('init', static function (): void {
    add_feed('zzfeed', static function ($forComments, $feed): void {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'zz custom feed ' . $feed . ' ' . var_export($forComments, true) . ' ' . (is_feed() ? 'is a feed' : 'is no feed');
    });
});
if ($minnFeedMode === 'replace') {
    add_action('init', static function (): void {
        remove_all_actions('do_feed_rss2');
        add_action('do_feed_rss2', static function ($forComments, $feed): void {
            echo 'zz replaced ' . $feed . ' ' . var_export($forComments, true);
        }, 10, 2);
    });
}
if ($minnFeedMode === 'redirect') {
    add_action('template_redirect', static function (): void {
        if (is_feed()) {
            wp_redirect(home_url('/zz-elsewhere/'), 302);
            exit;
        }
    });
}
