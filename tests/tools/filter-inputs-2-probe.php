<?php
/**
 * What the post, link and comment template tags hand their filters (probe
 * filter-inputs-2), recorded as probe filter-inputs records them: thumbnails,
 * sticky posts, post classes, the password form, the time, category and
 * author links, posts pagination, the generator, attachment links, the
 * users list, comment reply and author links, the comments popup link, page
 * and term queries, term links and attachment counts. The rows the probe
 * makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = rtrim(home_url(), '/');
$host = (string) parse_url($home, PHP_URL_HOST);
$ids = [];
$describe = static function ($value) use (&$describe, $host, &$ids) {
    if ($value instanceof WP_Post) {
        return "post:{$value->post_type}:{$value->post_name}";
    }
    if ($value instanceof WP_Term) {
        return "term:{$value->taxonomy}:{$value->slug}";
    }
    if ($value instanceof WP_Comment) {
        return 'comment:' . (array_search((int) $value->comment_ID, $ids, true) ?: 'other');
    }
    if ($value instanceof WP_User) {
        return 'user:' . $value->user_login;
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_array($value)) {
        return array_map($describe, $value);
    }
    if (is_int($value) && in_array($value, $ids, true)) {
        return '{' . array_search($value, $ids, true) . '}';
    }
    if (is_string($value)) {
        $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{home}', $value);
        foreach ($ids as $name => $id) {
            $value = (string) preg_replace('/\b' . $id . '\b/', '{' . $name . '}', $value);
        }
        return $value;
    }
    return $value;
};
$heard = [];
$listen = static function (array $hooks) use (&$heard, $describe): void {
    foreach ($hooks as $hook) {
        add_filter($hook, static function (...$args) use ($hook, &$heard, $describe) {
            $heard[] = [$hook, array_map($describe, $args)];
            return $args[0] ?? null;
        }, 10, 9);
    }
};
$run = static function (string $label, callable $call) use (&$heard, $say): void {
    $heard = [];
    $call();
    $say($label, $heard);
};

$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as [$kind, $id]) {
        if (is_int($id) && $id > 1) {
            $kind === 'comment' ? wp_delete_comment($id, true) : wp_delete_post($id, true);
        }
    }
});
foreach (['zz-fi2-one' => '2002-03-04 10:00:00', 'zz-fi2-two' => '2002-03-05 10:00:00'] as $slug => $date) {
    $id = wp_insert_post(['post_title' => $slug, 'post_name' => $slug, 'post_status' => 'publish', 'post_date' => $date, 'post_content' => 'zz', 'comment_status' => 'open']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "could not make the probe's posts\n");
        exit(1);
    }
    $made[] = ['post', $id];
    $ids[$slug] = $id;
}
$attachment = wp_insert_attachment(['post_title' => 'zz-fi2-file', 'post_name' => 'zz-fi2-file', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], 'zz-fi2.png', $ids['zz-fi2-one']);
if (is_int($attachment) && $attachment > 1) {
    $made[] = ['post', $attachment];
    $ids['zz-fi2-file'] = $attachment;
}
$comment = wp_insert_comment(['comment_post_ID' => $ids['zz-fi2-one'], 'comment_content' => 'zz fi2', 'comment_approved' => '1', 'comment_author' => 'zz fi2', 'comment_author_email' => 'zz-fi2@example.com', 'comment_author_url' => 'https://example.com/zz']);
if (is_int($comment) && $comment > 1) {
    $made[] = ['comment', $comment];
    $ids['zz-fi2-comment'] = $comment;
}
$GLOBALS['post'] = get_post($ids['zz-fi2-one']);
setup_postdata($GLOBALS['post']);

$listen(['has_post_thumbnail', 'post_thumbnail_id', 'is_sticky', 'post_class_taxonomies', 'the_password_form_incorrect_password', 'the_time', 'the_category_list', 'the_author_posts_link', 'wp_generator_type', 'wp_get_attachment_link_attributes']);
$run('post template tags', static function () use ($ids): void {
    has_post_thumbnail($ids['zz-fi2-one']);
    get_post_thumbnail_id($ids['zz-fi2-one']);
    is_sticky($ids['zz-fi2-one']);
    get_post_class('', $ids['zz-fi2-one']);
    get_the_password_form($ids['zz-fi2-one']);
    ob_start();
    the_time('Y');
    ob_end_clean();
    get_the_category_list(', ', '', $ids['zz-fi2-one']);
    get_the_author_posts_link();
    ob_start();
    wp_generator();
    ob_end_clean();
    wp_get_attachment_link($ids['zz-fi2-file'] ?? 0);
});

$listen(['the_posts_pagination_args']);
$run('posts pagination', static function (): void {
    $saved = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query(['posts_per_page' => 1, 'post_type' => 'post']);
    get_the_posts_pagination();
    $GLOBALS['wp_query'] = $saved;
});

$listen(['wp_list_users_args']);
$run('users list', static function (): void {
    wp_list_users(['echo' => false]);
});

$listen(['comment_reply_link_args', 'comment_author_link_rel', 'comments_popup_link_attributes', 'respond_link']);
$run('comment template tags', static function () use ($ids): void {
    get_comment_reply_link(['depth' => 1, 'max_depth' => 3], $ids['zz-fi2-comment'] ?? 0, $ids['zz-fi2-one']);
    get_comment_author_link($ids['zz-fi2-comment'] ?? 0);
    $GLOBALS['post'] = get_post($ids['zz-fi2-two']);
    setup_postdata($GLOBALS['post']);
    ob_start();
    comments_popup_link();
    ob_end_clean();
    $GLOBALS['post'] = get_post($ids['zz-fi2-one']);
    setup_postdata($GLOBALS['post']);
    ob_start();
    comments_popup_link();
    ob_end_clean();
});

$listen(['get_pages_query_args', 'term_exists_default_query_args', 'pre_term_link', 'wp_count_attachments']);
$run('page queries', static function () use ($ids): void {
    get_pages(['include' => [2], 'sort_column' => 'menu_order, post_title']);
    get_pages(['exclude' => '2', 'parent' => 0, 'authors' => '1', 'meta_key' => 'zz', 'meta_value' => 'v', 'offset' => 1, 'number' => 3, 'post_status' => 'publish,private', 'sort_order' => 'DESC']);
    get_pages(['child_of' => 2, 'hierarchical' => 0, 'exclude_tree' => '9']);
});
$run('queries and links', static function (): void {
    get_pages(['number' => 2]);
    term_exists('zz-fi2-nothing', 'category');
    get_term_link(get_term(1, 'category'));
    wp_count_attachments();
});

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
