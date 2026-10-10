<?php
/**
 * The remaining template tags for comments, terms and authors (probe
 * template-tags-remaining): comment form id fields and the unfiltered HTML
 * nonce, reply links, comment counts as text, comment dates, times and text,
 * pings open; term descriptions, parents lists, term lists, category checks,
 * the category and tag lists, a tag cloud from given tags; author meta and
 * the multi-author cache. The probe's posts, comments and terms are removed
 * at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'comments' => []];
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $pings): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['error' => $value->get_error_code()];
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    $value = (string) preg_replace('/(_wp_unfiltered_html_comment(?:_disabled)?" value=")[0-9a-f]{10}/', '$1{nonce}', $value);
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
$term = static function (string $name, string $taxonomy, int $parent = 0, string $description = '') use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy, ['parent' => $parent, 'description' => $description])['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$single = static function (int $id) {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query(['p' => $id, 'post_type' => 'any']);
    $wp_query->the_post();
};

$root = $term('zz tt root', 'category', 0, 'Root <b>description</b> & more');
$child = $term('zz tt child', 'category', $root, '');
$grand = $term('zz tt grand', 'category', $child, "Line one\n\nLine two");
$tagA = $term('zz tt alpha', 'post_tag', 0, 'Alpha tag');
$tagB = $term('zz tt beta', 'post_tag');
$open = $post('zz tt open', ['post_category' => [$grand, $root], 'tags_input' => ['zz tt alpha', 'zz tt beta'], 'comment_status' => 'open', 'ping_status' => 'open', 'post_date' => '1983-02-02 10:00:00']);
$closed = $post('zz tt closed', ['comment_status' => 'closed', 'ping_status' => 'closed']);
$comments = [];
foreach ([['zz tt first', '1983-02-03 11:12:13'], ['zz tt second <b>bold</b> & co', '1983-02-04 21:22:23']] as [$text, $date]) {
    $made['comments'][] = $comments[] = $id = (int) wp_insert_comment(['comment_post_ID' => $open, 'comment_content' => $text, 'comment_approved' => 1, 'comment_date' => $date, 'comment_date_gmt' => $date, 'comment_author' => 'Zz']);
    $ids[$id] = str_replace(' ', '-', substr($text, 0, 9));
}
wp_set_current_user(1);
$single($open);

$say('comment form fields', $mask([
    $echoed(static fn () => comment_id_fields()), $echoed(static fn () => comment_id_fields($closed)), (static function () use ($echoed, $comments) {
        $_GET['replytocom'] = (string) $comments[0];
        $fields = $echoed(static fn () => comment_id_fields());
        unset($_GET['replytocom']);
        return $fields;
    })(),
    $echoed(static fn () => wp_comment_form_unfiltered_html_nonce()), (static function () use ($echoed) {
        wp_set_current_user(0);
        $nonce = $echoed(static fn () => wp_comment_form_unfiltered_html_nonce());
        wp_set_current_user(1);
        return $nonce;
    })(),
]));

$say('reply links', $mask([
    $echoed(static fn () => comment_reply_link(['depth' => 1, 'max_depth' => 3], $comments[0])), $echoed(static fn () => comment_reply_link(['depth' => 3, 'max_depth' => 3], $comments[0])),
    $echoed(static fn () => comment_reply_link(['depth' => 1, 'max_depth' => 2, 'reply_text' => 'Answer <i>it</i>', 'before' => '<span>', 'after' => '</span>', 'respond_id' => 'zz-respond'], $comments[1], $open)),
    $echoed(static fn () => comment_reply_link(['depth' => 1, 'max_depth' => 2], $comments[0], $closed)),
]));

$single($open);
$counts = [get_comments_number_text(), get_comments_number_text('None', 'One', '% comments'), get_comments_number_text(false, false, false, $closed), get_comments_number_text('None', 'One', 'Many %', $closed), $echoed(static fn () => comments_number()), $echoed(static fn () => comments_number('No', 'One', '% here', $closed))];
$say('comment counts as text', $counts);

$GLOBALS['comment'] = get_comment($comments[1]);
$say('comment dates, times and text', $mask([
    get_comment_date(), get_comment_date('Y/m/d', $comments[0]), get_comment_date('U', $comments[0]),
    get_comment_time(), get_comment_time('H:i:s', true, true, $comments[0]), get_comment_time('U', false, false, $comments[0]), get_comment_time('g:i a', false, true, $comments[1]),
    get_comment_text(), get_comment_text($comments[0]), get_comment_text($comments[1], ['zz' => 1]),
]));
unset($GLOBALS['comment']);
$say('pings open', $mask([pings_open($open), pings_open($closed), pings_open(999999), pings_open()]));

$single($open);
$say('term descriptions', $mask([
    category_description($root), category_description($child), category_description($grand), category_description(999999),
    term_description($tagA), term_description($tagB), term_description($root), (static function () use ($root) {
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(['cat' => $root]);
        return [term_description(), category_description()];
    })(),
]));
$single($open);
$say('term parents lists', $mask([
    get_term_parents_list($grand, 'category'), get_term_parents_list($grand, 'category', ['separator' => ' &gt; ', 'link' => false, 'inclusive' => false]),
    get_term_parents_list($grand, 'category', ['format' => 'slug']), get_term_parents_list($root, 'category'), get_term_parents_list(999999, 'category'), get_term_parents_list($grand, 'zz_missing'),
]));
$say('term lists', $mask([
    get_the_term_list($open, 'category'), get_the_term_list($open, 'post_tag', 'Tags: ', ', ', '.'), get_the_term_list($closed, 'post_tag', 'x'), get_the_term_list($open, 'zz_missing'),
]));
$say('category checks', $mask([in_category($grand), in_category($child), in_category('zz-tt-root'), in_category('zz tt grand'), in_category([$child, $root]), in_category($root, $closed), in_category(999999)]));
$single($open);
$say('the category and tag lists', $mask([
    $echoed(static fn () => the_category()), $echoed(static fn () => the_category(', ')), $echoed(static fn () => the_category(' / ', 'multiple')), $echoed(static fn () => the_category(', ', 'single')),
    $echoed(static fn () => the_category(', ', '', $closed)), $echoed(static fn () => the_category('', 'multiple')), $echoed(static fn () => the_category('', 'single')),
    get_the_category_list(' > ', 'multiple', $open), $echoed(static fn () => the_tags()), $echoed(static fn () => the_tags('<p>', ' | ', '</p>')),
]));

$cloudTags = [];
foreach ([[$tagA, 'zz tt alpha', 5], [$tagB, 'zz tt beta', 1], [$tagB + 1000, 'zz gamma', 12]] as [$id, $name, $count]) {
    $cloudTags[] = (object) ['term_id' => $id, 'name' => $name, 'slug' => sanitize_title($name), 'count' => $count, 'taxonomy' => 'post_tag', 'link' => 'https://zz.example/' . sanitize_title($name)];
}
$plain = static fn (string $name, int $count, array $extra = []) => (object) ($extra + ['name' => $name, 'slug' => sanitize_title($name), 'count' => $count, 'link' => 'https://zz.example/?tag=' . sanitize_title($name) . '&zz=1']);
$three = [$plain('zz b', 3), $plain('zz a', 1, ['id' => 77]), 'zz' => $plain('zz c', 2)];
$say('a tag cloud', $mask([
    wp_generate_tag_cloud($cloudTags, ['orderby' => 'name']), wp_generate_tag_cloud($cloudTags, ['format' => 'array', 'orderby' => 'count', 'order' => 'DESC']), wp_generate_tag_cloud($cloudTags, ['format' => 'list', 'smallest' => 10, 'largest' => 20, 'unit' => 'px', 'show_count' => true, 'orderby' => 'name']),
    wp_generate_tag_cloud($cloudTags, ['number' => 2, 'orderby' => 'count', 'order' => 'ASC', 'separator' => ' * ']), wp_generate_tag_cloud([], []), wp_generate_tag_cloud([], ['format' => 'array']), wp_generate_tag_cloud($cloudTags, ['smallest' => 12, 'largest' => 12, 'orderby' => 'name']),
    wp_generate_tag_cloud($cloudTags, ['smallest' => 12, 'largest' => 12, 'show_count' => 1]), wp_generate_tag_cloud($cloudTags, ['smallest' => 13, 'largest' => 12, 'unit' => 'em']),
    wp_generate_tag_cloud($three, []), wp_generate_tag_cloud($three, ['orderby' => 'none', 'order' => 'DESC']), wp_generate_tag_cloud($three, ['orderby' => 'NAME', 'order' => 'desc']), wp_generate_tag_cloud($three, ['orderby' => 'name', 'order' => 'DESC', 'number' => 2]),
    wp_generate_tag_cloud($three, ['topic_count_scale_callback' => static fn ($count) => $count * $count, 'topic_count_text_callback' => static fn ($count, $tag) => "C{$count}-{$tag->name}"]),
    wp_generate_tag_cloud($three, ['topic_count_text' => _n_noop('%s thing', '%s things')]), wp_generate_tag_cloud([$plain('Q "x" & <b>', 1234)], ['show_count' => 1]), wp_generate_tag_cloud([$plain('zz one', 2)], []),
]));

$single($open);
update_user_meta(1, 'zz_tt_meta', 'meta <b>value</b>');
$say('author meta', $mask([
    $echoed(static fn () => the_author_meta('display_name')), $echoed(static fn () => the_author_meta('user_login', 1)), $echoed(static fn () => the_author_meta('zz_tt_meta')), $echoed(static fn () => the_author_meta('ID')),
    $echoed(static fn () => the_author_meta('user_email')), $echoed(static fn () => the_author_meta('login')), $echoed(static fn () => the_author_meta('')), $echoed(static fn () => the_author_meta('display_name', 999999)),
]));
delete_user_meta(1, 'zz_tt_meta');

$before = is_multi_author();
$say('the multi-author cache', [$before, __clear_multi_author_cache(), is_multi_author() === $before]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
