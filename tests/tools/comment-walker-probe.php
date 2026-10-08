<?php
/**
 * The comment list as themes print it (probe comment-walker): a post with
 * threaded comments (three deep), a pingback and a comment held for
 * moderation, made at fixed times, listed by wp_list_comments with the
 * arguments themes pass (html5 and xhtml, ol/ul/div, depth limits,
 * reversed, paged, by type, short pings, avatar sizes, callbacks, a walker
 * subclass as Twenty Nineteen and Twenty Twenty bring), with threading on
 * and off, a list of replies alone, the held comment as its commenter sees
 * it, pages set by the discussion settings, and the edit links an editor
 * sees; Walker::paged_walk, get_number_of_root_elements and
 * unset_children called directly; separate_comments on a custom type; and
 * which comment types have avatars.
 * Ids are given as the labels the probe gave its comments. Everything made
 * is removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$options = ['thread_comments', 'thread_comments_depth', 'page_comments', 'comments_per_page', 'default_comments_page', 'comment_order', 'show_avatars'];
$saved = [];
foreach ($options as $option) {
    $saved[$option] = get_option($option);
}
$made = ['post' => 0, 'comments' => []];
register_shutdown_function(static function () use (&$made, $saved): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    if ($made['post']) {
        if ($made['post'] > 0) {
            wp_delete_post($made['post'], true);
        }
    }
    foreach ($saved as $option => $value) {
        update_option($option, $value);
    }
});
update_option('thread_comments', '1');
update_option('thread_comments_depth', '5');
update_option('page_comments', '0');
update_option('show_avatars', '1');

$post = wp_insert_post(['post_title' => 'ZZ Walker Post', 'post_status' => 'publish', 'post_content' => 'Body', 'comment_status' => 'open', 'post_date' => '2021-01-01 10:00:00']);
$made['post'] = $post;
$labels = [];
$add = static function (string $label, array $fields) use ($post, &$made, &$labels): int {
    $id = wp_insert_comment($fields + ['comment_post_ID' => $post, 'comment_approved' => 1, 'comment_author' => 'Reader ' . $label, 'comment_author_email' => strtolower($label) . '@example.com', 'comment_content' => "Comment {$label} -- with \"quotes\"."]);
    $made['comments'][] = $id;
    $labels[$id] = $label;
    return $id;
};
$a = $add('A', ['comment_date' => '2021-01-02 10:00:00', 'comment_date_gmt' => '2021-01-02 10:00:00', 'comment_author_url' => 'https://example.com/a']);
$a1 = $add('A1', ['comment_date' => '2021-01-02 11:00:00', 'comment_date_gmt' => '2021-01-02 11:00:00', 'comment_parent' => $a]);
$a1a = $add('A1a', ['comment_date' => '2021-01-02 12:00:00', 'comment_date_gmt' => '2021-01-02 12:00:00', 'comment_parent' => $a1]);
$b = $add('B', ['comment_date' => '2021-01-03 10:00:00', 'comment_date_gmt' => '2021-01-03 10:00:00']);
$p = $add('P', ['comment_date' => '2021-01-04 10:00:00', 'comment_date_gmt' => '2021-01-04 10:00:00', 'comment_type' => 'pingback', 'comment_author' => 'A Pinging Site', 'comment_author_url' => 'https://example.com/pinging', 'comment_content' => '[&#8230;] a pingback excerpt [&#8230;]']);
$c = $add('C', ['comment_date' => '2021-01-05 10:00:00', 'comment_date_gmt' => '2021-01-05 10:00:00']);
$held = $add('H', ['comment_date' => '2021-01-06 10:00:00', 'comment_date_gmt' => '2021-01-06 10:00:00', 'comment_approved' => 0, 'comment_parent' => $b, 'comment_author_url' => 'https://example.com/h']);

$GLOBALS['post'] = get_post($post);
$GLOBALS['wp_query']->post = $GLOBALS['post'];
$comments = get_comments(['post_id' => $post, 'status' => 'all', 'orderby' => 'comment_date_gmt', 'order' => 'ASC']);
$words = static function (string $html) use (&$labels, $post): string {
    foreach ($labels as $id => $label) {
        $html = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $html);
    }
    $html = (string) preg_replace('/(?<![0-9a-z])' . $post . '(?![0-9a-z])/i', '{post}', $html);
    return str_replace(home_url(), '{home}', $html);
};
$list = static function (string $label, array $args, ?array $list = null) use ($say, $words, &$comments): void {
    $say($label, $words((string) wp_list_comments($args + ['echo' => false], $list ?? $comments)));
};

$list('default', []);
$list('xhtml', ['format' => 'xhtml']);
$list('ul', ['style' => 'ul']);
$list('div', ['style' => 'div']);
$list('ol', ['style' => 'ol']);
$list('xhtml, div, two levels', ['format' => 'xhtml', 'style' => 'div', 'max_depth' => 2]);
$list('only replies', [], [get_comment($a1), get_comment($a1a)]);
$list('two levels', ['max_depth' => 2]);
$list('reversed', ['reverse_top_level' => true, 'reverse_children' => true]);
$list('two a page, page one', ['per_page' => 2, 'page' => 1]);
$list('two a page, page two', ['per_page' => 2, 'page' => 2]);
$say('two a page: pages', $GLOBALS['wp_query']->max_num_comment_pages ?? null);
$list('comments only', ['type' => 'comment']);
$list('pings only', ['type' => 'pings']);
$list('pings only, short', ['type' => 'pings', 'short_ping' => true]);
$list('no avatars', ['avatar_size' => 0]);
$list('larger avatars', ['avatar_size' => 64]);
$list('callbacks', [
    'callback' => static function ($comment, $args, $depth) use ($labels) {
        echo '<li data-zz="' . ($labels[(int) $comment->comment_ID] ?? '?') . ' at ' . $depth . ' of ' . $args['max_depth'] . '">';
    },
    'end-callback' => static function ($comment, $args, $depth) use ($labels) {
        echo '</li><!-- zz end ' . ($labels[(int) $comment->comment_ID] ?? '?') . ' -->';
    },
]);
if (!class_exists('ZZ_Walker_Comment')) {
    eval('class ZZ_Walker_Comment extends Walker_Comment {
        public function start_lvl(&$output, $depth = 0, $args = []) { $output .= "<ol class=\"zz-children depth-" . ($depth + 1) . "\">"; }
        protected function html5_comment($comment, $depth, $args) {
            $tag = ($args["style"] === "div") ? "div" : "li";
            echo "<" . $tag . " data-zz-depth=\"" . $depth . "\" " . comment_class($this->has_children ? "parent zz" : "zz", $comment, null, false) . ">" . get_comment_author($comment);
        }
    }');
}
$list('a walker subclass', ['walker' => new ZZ_Walker_Comment()]);
update_option('thread_comments', '0');
$list('threading off', []);
update_option('thread_comments', '1');
$cookie = 'comment_author_' . md5((string) get_option('siteurl'));
$_COOKIE[$cookie] = 'Reader H';
$list('held, as its commenter', [], [get_comment($held)]);
$list('held, as its commenter, xhtml', ['format' => 'xhtml'], [get_comment($held)]);
unset($_COOKIE[$cookie]);
$list('held, xhtml, alone', ['format' => 'xhtml'], [get_comment($held)]);
update_option('page_comments', '1');
update_option('comments_per_page', '2');
$list('paged by the settings', []);
set_query_var('comments_per_page', 2);
$list('paged by the settings, no page asked', []);
set_query_var('cpage', 2);
$list('paged by the settings, page two', []);
$list('paged by the settings, page one asked on page two', ['page' => 1]);
set_query_var('cpage', 1);
$list('paged by the settings, page one', []);
update_option('default_comments_page', 'oldest');
$list('paged by the settings, page one, oldest first', []);
update_option('default_comments_page', 'newest');
set_query_var('cpage', '');
$list('paged by the settings, page two asked', ['page' => 2]);
set_query_var('comments_per_page', '');
update_option('page_comments', '0');
$editor = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) ($editor[0] ?? 0));
$list('as an editor', ['max_depth' => 2], [get_comment($a), get_comment($a1), get_comment($p)]);
$list('as an editor, xhtml', ['format' => 'xhtml'], [get_comment($a), get_comment($p)]);
$list('as an editor, short pings', ['short_ping' => true], [get_comment($p)]);
wp_set_current_user(0);

// The walker itself.
$walker = new Walker_Comment();
$objects = array_map(static fn ($comment) => $comment, $comments);
$say('get_number_of_root_elements', $walker->get_number_of_root_elements($objects));
$say('paged_walk page 2 of 2 a page', $words($walker->paged_walk($objects, 5, 2, 2, ['style' => 'ol', 'format' => 'html5', 'avatar_size' => 0, 'short_ping' => false, 'max_depth' => 5])));
$say('paged_walk max_pages', $walker->max_pages);
$say('paged_walk flat, page 1 of 3 a page', $words($walker->paged_walk($objects, -1, 1, 3, ['style' => 'div', 'format' => 'html5', 'avatar_size' => 0, 'short_ping' => true, 'max_depth' => -1])));
$children = [];
foreach ($objects as $object) {
    if ((int) $object->comment_parent) {
        $children[(int) $object->comment_parent][] = $object;
    }
}
$walker->unset_children(get_comment($a), $children);
$say('unset_children of A', array_map(static fn ($id) => '{' . ($labels[$id] ?? $id) . '}', array_keys($children)));

$say('is_avatar_comment_type', [is_avatar_comment_type('comment'), is_avatar_comment_type('pingback'), is_avatar_comment_type('')]);
$reviews = static fn (array $types): array => [...$types, 'review'];
add_filter('get_avatar_comment_types', $reviews);
$say('is_avatar_comment_type, reviews added', is_avatar_comment_type('review'));
remove_filter('get_avatar_comment_types', $reviews);
$say('get_avatar_data for a pingback', array_intersect_key(get_avatar_data(get_comment($p)), ['url' => 1, 'found_avatar' => 1]));
$typed = array_map(static fn ($type) => (object) ['comment_type' => $type], ['review', '', 'pingback', 'trackback', 'comment']);
$say('separate_comments by type', array_map('count', separate_comments($typed)));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
