<?php
/**
 * The template tags a feed is written with, as the reference answers them
 * inside a feed's loop (probe feed-tags): the site's details, a post's
 * title, link, content, excerpt, categories, comments links and
 * enclosures, a comment's guid, link, author and text, the build date,
 * and the default feed's handlers; each with the filters it consults and
 * what they are handed, and what a plugin's filter changes. Read only: the
 * oldest published post with an approved comment. Same protocol as
 * api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
$watch = '/^(term_name_rss|category_name_rss|post_tag_name_rss|bloginfo_rss|get_bloginfo_rss|get_wp_title_rss|wp_title_rss|the_title_rss|the_content_feed|the_excerpt_rss|the_permalink_rss|comments_link_feed|post_comments_feed_link|feed_link|category_feed_link|the_category_rss|rss_enclosure|atom_enclosure|comment_link|post_comments_feed_link_html|get_comment_author_rss|comment_author_rss|comment_text_rss|get_feed_build_date|feed_content_type|default_feed|get_the_guid|the_guid|the_author|self_link|html_type_rss)$/';
$describe = static function ($value) {
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    return is_string($value) && strlen($value) > 120 ? 'text:' . strlen($value) : $value;
};
add_filter('all', static function (string $hook, ...$args) use (&$heard, $watch, $describe): void {
    if (preg_match($watch, $hook)) {
        $heard[] = [$hook, array_map($describe, $args)];
    }
});
$call = static function (string $label, callable $fn) use ($say, &$heard): void {
    $heard = [];
    ob_start();
    $returned = $fn();
    $printed = ob_get_clean();
    $say($label, array_filter(['returned' => $returned, 'printed' => $printed, 'heard' => $heard], static fn ($v) => $v !== null && $v !== '' && $v !== []));
};

$post = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'has_password' => false]);
$postId = $post ? (int) $post[0]->ID : 0;
query_posts(['feed' => 'rss2', 'p' => $postId]);
$say('the query is a feed', [is_feed(), is_feed('rss2'), get_query_var('feed'), have_posts()]);
the_post();

foreach (['name', 'url', 'description', 'language', 'charset', 'rss2_url', 'html_type', 'version'] as $show) {
    $call("get_bloginfo_rss({$show})", static fn () => get_bloginfo_rss($show));
}
$call('bloginfo_rss(name)', static fn () => bloginfo_rss('name'));
$call('get_wp_title_rss', static fn () => get_wp_title_rss());
$call('wp_title_rss', static fn () => wp_title_rss());
$call('get_the_title_rss', static fn () => get_the_title_rss());
$call('the_title_rss', static fn () => the_title_rss());
$call('get_the_content_feed', static fn () => get_the_content_feed('rss2'));
$call('the_content_feed', static fn () => the_content_feed('atom'));
$call('the_excerpt_rss', static fn () => the_excerpt_rss());
$call('the_permalink_rss', static fn () => the_permalink_rss());
$call('comments_link_feed', static fn () => comments_link_feed());
$call('get_post_comments_feed_link', static fn () => get_post_comments_feed_link());
$call('get_post_comments_feed_link for atom', static fn () => get_post_comments_feed_link($postId, 'atom'));
$call('post_comments_feed_link', static fn () => post_comments_feed_link());
foreach (['rss2', 'atom', 'rdf', 'rss'] as $type) {
    $call("get_the_category_rss({$type})", static fn () => get_the_category_rss($type));
}
$call('the_category_rss', static fn () => the_category_rss());
$call('html_type_rss', static fn () => html_type_rss());
$call('rss_enclosure', static fn () => rss_enclosure());
$call('atom_enclosure', static fn () => atom_enclosure());
$call('the_guid', static fn () => the_guid());
$call('the_author', static fn () => the_author());
foreach (['r', 'U', 'Y-m-d\TH:i:s\Z'] as $format) {
    $call("get_feed_build_date({$format})", static fn () => get_feed_build_date($format));
}
foreach (['rss2', 'atom', 'rdf', 'rss', 'zz'] as $type) {
    $call("feed_content_type({$type})", static fn () => feed_content_type($type));
}
$call('get_default_feed', static fn () => get_default_feed());

$comments = get_comments(['post_id' => $postId, 'status' => 'approve', 'number' => 1, 'orderby' => 'comment_ID', 'order' => 'ASC']);
if ($comments) {
    $GLOBALS['comment'] = $comments[0];
    $call('get_comment_guid', static fn () => get_comment_guid());
    $call('comment_guid', static fn () => comment_guid());
    $call('comment_link', static fn () => comment_link());
    $call('get_comment_author_rss', static fn () => get_comment_author_rss());
    $call('comment_author_rss', static fn () => comment_author_rss());
    $call('comment_text_rss', static fn () => comment_text_rss());
}

// The probe's own post with enclosures (one well formed, one with its parts in another order, one short), removed at the end.
$enclosed = (int) wp_insert_post(['post_title' => 'Zz Feed Enclosure', 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => 'zz']);
add_post_meta($enclosed, 'enclosure', "https://zz.example/a.mp3\n1234\naudio/mpeg\n");
add_post_meta($enclosed, 'enclosure', "https://zz.example/b.mp4\nvideo/mp4\n5678 extra");
add_post_meta($enclosed, 'enclosure', "https://zz.example/c.ogg\n9");
query_posts(['feed' => 'rss2', 'p' => $enclosed]);
the_post();
$call('rss_enclosure with enclosures', static fn () => rss_enclosure());
$call('atom_enclosure with enclosures', static fn () => atom_enclosure());
add_filter('get_site_icon_url', $icon = static fn ($url, $size) => "https://zz.example/icon-{$size}.png", 10, 2);
$call('rss2_site_icon', static fn () => rss2_site_icon());
$call('atom_site_icon', static fn () => atom_site_icon());
remove_filter('get_site_icon_url', $icon, 10);
$call('rss2_site_icon without an icon', static fn () => rss2_site_icon());
wp_delete_post($enclosed, true);
wp_reset_query();
query_posts(['feed' => 'rss2', 'p' => $postId]);
the_post();


$defaults = [];
foreach (['the_title_rss', 'the_content_feed', 'the_content_rss', 'the_excerpt_rss', 'the_permalink_rss', 'comments_link_feed', 'the_category_rss', 'comment_author_rss', 'comment_text_rss', 'bloginfo_rss', 'get_bloginfo_rss', 'wp_title_rss', 'get_wp_title_rss', 'the_author', 'the_guid', 'term_name_rss', 'category_name_rss', 'rss_update_period', 'rss_update_frequency'] as $hook) {
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_string($callback['function'])) {
                $defaults[$hook][] = [$priority, $callback['function'], $callback['accepted_args']];
            }
        }
    }
}
$say('the filters a feed\'s text runs through', $defaults);

add_filter('the_title_rss', static fn ($t) => '[t]' . $t);
add_filter('the_excerpt_rss', static fn ($t) => '[e]' . $t);
add_filter('the_content_feed', static fn ($t, $type) => "[c:{$type}]", 10, 2);
add_filter('the_category_rss', static fn ($t, $type) => "[cat:{$type}]" . $t, 10, 2);
add_filter('the_permalink_rss', static fn ($t) => $t . '#zz');
add_filter('comments_link_feed', static fn ($t) => $t . '#zz');
add_filter('wp_title_rss', static fn ($t) => '[wt]' . $t);
add_filter('bloginfo_rss', static fn ($t, $show) => "[b:{$show}]" . $t, 10, 2);
$call('a plugin\'s filters on the title, excerpt, content, categories and links', static fn () => [get_the_title_rss(), get_the_content_feed('rss2'), get_the_category_rss('rss2')]);
$call('a plugin\'s filters, printed', static fn () => [the_title_rss(), the_excerpt_rss(), the_permalink_rss(), comments_link_feed(), wp_title_rss(), bloginfo_rss('name')]);
wp_reset_query();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
