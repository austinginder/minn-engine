<?php
/**
 * link-template.php's remaining template tags (probe link-template-tags):
 * the adjacent post links and their rel links in the head (all, in the same
 * category, with a category left out), the edit links for a tag and a
 * bookmark, the permalink anchor, a feed link, the short link, and which
 * posts keep a plain permalink. Run from a single post's query, as a theme
 * runs them. The probe's posts, terms and bookmark are removed at the end.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'links' => []];
$linkManager = get_option('link_manager_enabled', null);
register_shutdown_function(static function () use (&$made, $linkManager): void {
    global $wpdb;
    $linkManager === null ? delete_option('link_manager_enabled') : update_option('link_manager_enabled', $linkManager);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach ($made['links'] as $id) {
        if (is_int($id) && $id > 0) {
            $wpdb->delete($wpdb->links, ['link_id' => $id]);
        }
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = str_replace([home_url(), admin_url()], ['{home}', '{admin}'], $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
wp_set_current_user(1);

$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$catA = $term('zz adj a', 'category');
$catB = $term('zz adj b', 'category');
$tag = $term('zz adj tag', 'post_tag');
$post = static function (string $title, string $date, array $extra = []) use (&$made, &$ids): int {
    $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_date' => $date, 'post_content' => 'x']);
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
// Long before the site's own posts (2026 on), so nothing else sits between or before them.
$first = $post('zz adj first', '1990-03-01 10:00:00', ['post_category' => [$catA]]);
$other = $post('zz adj other "q" & co', '1990-03-02 10:00:00', ['post_category' => [$catB]]);
$middle = $post('zz adj middle "quoted" & more', '1990-03-03 10:00:00', ['post_category' => [$catA, $catB], 'tags_input' => ['zz adj tag']]);
$last = $post('zz adj last', '1990-03-04 10:00:00', ['post_category' => [$catA]]);
$private = $post('zz adj private', '1990-03-10 10:00:00', ['post_status' => 'private', 'post_category' => [$catB]]);
$draft = $post('zz adj draft', '1990-03-05 10:00:00', ['post_status' => 'draft']);
$pending = $post('zz adj pending', '1990-03-05 11:00:00', ['post_status' => 'pending']);
$future = $post('zz adj future', '2031-03-05 12:00:00', ['post_status' => 'future']);
$page = $post('zz adj page', '1990-03-05 14:00:00', ['post_type' => 'page']);
$draftPage = $post('zz adj draft page', '1990-03-05 15:00:00', ['post_type' => 'page', 'post_status' => 'draft']);

$single = static function (int $id, string $var = 'p'): void {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query([$var => $id]);
    $wp_query->the_post();
};
$single($middle);
$say('adjacent post links', $mask([
    $echoed(static fn () => adjacent_post_link('%link &raquo;', '%title', false, '', true)),
    $echoed(static fn () => adjacent_post_link('&laquo; %link', 'Go: %title on %date', false, '', false)),
    $echoed(static fn () => adjacent_post_link('%link', '%title', true, '', true)),
    $echoed(static fn () => adjacent_post_link('%link', '%title', true, '', false)),
    $echoed(static fn () => adjacent_post_link('%link', '%title', false, (string) $catB, true)),
    $echoed(static fn () => adjacent_post_link('[%link]', '%title', true, '', true, 'post_tag')),
]));
$say('adjacent rel links', $mask([
    get_adjacent_post_rel_link('%title', false, '', true),
    get_adjacent_post_rel_link('%title', false, '', false),
    get_adjacent_post_rel_link('Back to %title', true, '', true),
    get_adjacent_post_rel_link('%title', false, [$catB], true),
    get_adjacent_post_rel_link('%title', true, '', true, 'post_tag'),
    $echoed(static fn () => adjacent_posts_rel_link()),
    $echoed(static fn () => adjacent_posts_rel_link('Post: %title', true)),
    $echoed(static fn () => next_post_rel_link()),
    $echoed(static fn () => prev_post_rel_link('%title (older)')),
    $echoed(static fn () => adjacent_posts_rel_link_wp_head()),
]));
$heard = [];
foreach (['previous_post_rel_link', 'next_post_rel_link'] as $hook) {
    add_filter($hook, $listeners[$hook] = static function ($link) use ($hook, &$heard) {
        $heard[] = $hook;
        return str_replace(' />', ' data-x="1" />', $link);
    });
}
$say('adjacent rel links, filtered', $mask([$echoed(static fn () => adjacent_posts_rel_link()), $heard]));
foreach ($listeners as $hook => $listener) {
    remove_filter($hook, $listener);
}
$single($last);
$say('the last post\'s next is the private one, and in its category there is none', $mask([get_adjacent_post_rel_link('%title', false, '', false), get_adjacent_post_rel_link('%title', true, '', false)]));
$single($first);
$say('the first post has no previous', $mask([get_adjacent_post_rel_link('%title', false, '', true), $echoed(static fn () => adjacent_post_link('%link', '%title', false, '', true))]));
$say('permalink anchors', $mask([$echoed(static fn () => permalink_anchor()), $echoed(static fn () => permalink_anchor('title')), $echoed(static fn () => permalink_anchor('ID')), $echoed(static fn () => permalink_anchor('other'))]));
$say('short links', $mask([
    $echoed(static fn () => the_shortlink()),
    $echoed(static fn () => the_shortlink('Short', 'A title', '<p>', '</p>')),
    $echoed(static fn () => the_shortlink('', '', '[', ']')),
]));
$say('feed links', $mask([$echoed(static fn () => the_feed_link('RSS')), $echoed(static fn () => the_feed_link('Atom <b>feed</b>', 'atom')), $echoed(static fn () => the_feed_link('', 'rss2'))]));
$feedLink = static fn ($link, $feed) => $link . '#' . $feed;
add_filter('the_feed_link', $feedLink, 10, 2);
$say('feed link, filtered', $mask($echoed(static fn () => the_feed_link('RSS'))));
remove_filter('the_feed_link', $feedLink, 10);

$single($page, 'page_id');
$say('a page has no adjacent rel links in the head', $mask($echoed(static fn () => adjacent_posts_rel_link_wp_head())));
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['posts_per_page' => 1]);
$say('a listing has none either', $echoed(static fn () => adjacent_posts_rel_link_wp_head()));

$say('edit tag links', $mask([
    $echoed(static fn () => edit_tag_link('', '', '', $tag)),
    $echoed(static fn () => edit_tag_link('Change it', '<b>', '</b>', get_term($tag))),
    $echoed(static fn () => edit_tag_link('Change category', '[', ']', $catA)),
]));
global $wpdb;
// The bookmark row leaves out link_notes (a text column with no default): WordPress's session
// drops strict mode, so the insert succeeds; set_sql_mode keeps the incompatible modes off.
$session = [$wpdb->get_var('SELECT @@SESSION.sql_mode')];
$wpdb->set_sql_mode(['STRICT_ALL_TABLES', 'ANSI', 'NO_ENGINE_SUBSTITUTION']);
$session[] = $wpdb->get_var('SELECT @@SESSION.sql_mode');
$noFilter = static fn ($modes) => array_diff((array) $modes, ['STRICT_ALL_TABLES']);
add_filter('incompatible_sql_modes', $noFilter);
$wpdb->set_sql_mode(['STRICT_ALL_TABLES', 'NO_ENGINE_SUBSTITUTION']);
remove_filter('incompatible_sql_modes', $noFilter);
$session[] = $wpdb->get_var('SELECT @@SESSION.sql_mode');
$wpdb->set_sql_mode();
$session[] = $wpdb->get_var('SELECT @@SESSION.sql_mode');
$say('the database session', $session);
update_option('link_manager_enabled', 1);
$wpdb->insert($wpdb->links, ['link_url' => 'https://zz-bookmark.example/', 'link_name' => 'zz bookmark', 'link_visible' => 'Y', 'link_owner' => 1]);
$bookmark = (int) $wpdb->insert_id;
$made['links'][] = $bookmark;
$ids[$bookmark] = 'bookmark';
$say('bookmark edit links', $mask([
    get_edit_bookmark_link($bookmark),
    get_edit_bookmark_link(get_bookmark($bookmark)),
    $echoed(static fn () => edit_bookmark_link('', '', '', $bookmark)),
    $echoed(static fn () => edit_bookmark_link('Fix it', '<i>', '</i>', $bookmark)),
]));
$bookmarkLink = static fn ($location, $id) => $location . '&from=probe&id=' . $id;
add_filter('get_edit_bookmark_link', $bookmarkLink, 10, 2);
$say('bookmark edit link, filtered', $mask(get_edit_bookmark_link($bookmark)));
remove_filter('get_edit_bookmark_link', $bookmarkLink, 10);
wp_set_current_user(0);
$say('edit links for a visitor', $mask([get_edit_bookmark_link($bookmark), $echoed(static fn () => edit_bookmark_link('', '', '', $bookmark)), $echoed(static fn () => edit_tag_link('', '', '', $tag))]));
wp_set_current_user(1);

$say('plain permalinks kept', $mask(array_map(static fn ($row) => [$ids[$row[0]] ?? $row[0], wp_force_plain_post_permalink($row[0], $row[1] ?? null)], [
    [$middle], [$draft], [$pending], [$future], [$private], [$page], [$draftPage],
    [$draft, true], [$draft, false], [$middle, true], [$future, true],
])));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
