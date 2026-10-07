<?php
/**
 * The feed links of terms, authors and searches (probe feed-links):
 * get_term_feed_link (by id, by object, a tag, a post format, a missing
 * term, a feed type named, the default named) and a post format's own
 * link (its slug without the post-format- prefix), get_category_feed_link,
 * get_tag_feed_link, get_author_feed_link, get_search_feed_link and
 * get_search_comments_feed_link, under pretty and plain permalinks, and
 * what the filters on them are handed. Same protocol as api-probe.php; the
 * structure is put back.
 */

global $wp_rewrite;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = home_url();
$plain = static fn ($value) => is_string($value) ? str_replace($home, '{home}', $value) : $value;
$structure = (string) get_option('permalink_structure');
$heard = [];
foreach (['category_feed_link', 'tag_feed_link', 'taxonomy_feed_link', 'author_feed_link', 'search_feed_link'] as $filter) {
    add_filter($filter, static function (...$args) use (&$heard, $filter, $plain) {
        $heard[] = [$filter, array_map($plain, $args)];
        return $args[0];
    }, 10, 3);
}
try {
    foreach (['pretty' => '/%postname%/', 'plain' => ''] as $mode => $candidate) {
        $wp_rewrite->set_permalink_structure($candidate);
        $heard = [];
        $say("{$mode}: terms", array_map($plain, [
            get_term_feed_link(1),
            get_term_feed_link(1, 'category'),
            get_term_feed_link(1, 'category', 'atom'),
            get_term_feed_link(1, 'category', 'rss2'),
            get_term_feed_link(get_term(1, 'category')),
            get_term_feed_link(2, 'post_tag'),
            get_term_feed_link(2, 'post_tag', 'rdf'),
            get_term_feed_link(12, 'post_format'),
            get_term_feed_link(999999, 'category'),
            get_term_feed_link(1, 'post_tag'),
            get_term_feed_link(2),
            get_term_feed_link('engine', 'post_tag'),
        ]));
        $say("{$mode}: categories and tags", array_map($plain, [get_category_feed_link(1), get_category_feed_link(1, 'atom'), get_tag_feed_link(2), get_tag_feed_link(2, 'rss')]));
        $say("{$mode}: authors", array_map($plain, [get_author_feed_link(1), get_author_feed_link(1, 'atom'), get_author_feed_link(999999)]));
        $say("{$mode}: searches", array_map($plain, [get_search_feed_link('hello world'), get_search_feed_link('a&b', 'atom'), get_search_comments_feed_link('hello'), get_search_comments_feed_link('hello', 'atom')]));
        $say("{$mode}: a post format's own link", $plain(get_term_link(12, 'post_format')));
        $say("{$mode}: what the filters heard", $heard);
    }
} finally {
    $wp_rewrite->set_permalink_structure($structure);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
