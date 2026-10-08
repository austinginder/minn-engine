<?php
/**
 * The term and comment helpers importers call (probe admin-terms):
 * category_exists, tag_exists, wp_create_category(ies), wp_insert_category,
 * wp_update_category, wp_create_term, wp_create_tag, get_tags_to_edit,
 * get_terms_to_edit and comment_exists, on throwaway zz-terms-* terms, a
 * draft post and a comment the probe makes. An id in an answer is given as
 * what it names (its term's name, or the post's title), so the answers mean
 * the same on both stacks. Everything made is removed at the end. Same
 * protocol as api-probe.php.
 */

foreach (['wp-admin/includes/taxonomy.php', 'wp-admin/includes/comment.php', 'wp-admin/includes/post.php'] as $admin) {
    if (is_file(ABSPATH . $admin)) {
        require_once ABSPATH . $admin;
    }
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['terms' => [], 'posts' => [], 'comments' => []];
$sweep = static function () use (&$made): void {
    foreach (['category', 'post_tag'] as $taxonomy) {
        foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => 'ZZ Terms']) ?: [] as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }
    foreach ($made['comments'] as $id) {
        wp_delete_comment($id, true);
    }
    foreach ($made['posts'] as $id) {
        wp_delete_post($id, true);
    }
};
$sweep();
register_shutdown_function($sweep);

// An id described by what it names.
$named = static function ($value) {
    if (is_array($value) && isset($value['term_id'])) {
        $term = get_term((int) $value['term_id']);
        return ['term_id of' => $term instanceof WP_Term ? $term->name : null, 'term_taxonomy_id of' => (int) $value['term_taxonomy_id'] === (int) ($term->term_taxonomy_id ?? -1) ? 'the same term' : 'another'];
    }
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code() . ':' . $value->get_error_message();
    }
    if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
        $term = get_term((int) $value);
        if ($term instanceof WP_Term && str_starts_with($term->name, 'ZZ Terms')) {
            return ['id of ' . $term->taxonomy => $term->name, 'type' => get_debug_type($value)];
        }
        $post = get_post((int) $value);
        if ($post instanceof WP_Post && str_starts_with($post->post_title, 'ZZ Terms')) {
            return ['post' => $post->post_title, 'type' => get_debug_type($value)];
        }
        if ((int) $value === (int) get_option('default_category')) {
            return ['the default category', 'type' => get_debug_type($value)];
        }
    }
    return $value;
};
$ask = static function (string $label, callable $call) use ($say, $named): void {
    $answer = $call();
    $say($label, is_array($answer) && array_is_list($answer) ? array_map($named, $answer) : $named($answer));
};

$ask('category_exists, the default category by name', static fn () => category_exists(get_cat_name((int) get_option('default_category'))));
$ask('category_exists, one not there', static fn () => category_exists('ZZ Terms Nowhere'));
$ask('wp_create_category, new', static fn () => wp_create_category('ZZ Terms One'));
$ask('wp_create_category, again', static fn () => wp_create_category('ZZ Terms One'));
$parent = (int) category_exists('ZZ Terms One');
$ask('wp_create_category, under a parent', static fn () => wp_create_category('ZZ Terms Child', $parent));
$ask('category_exists, by name', static fn () => category_exists('ZZ Terms One'));
$ask('category_exists, by slug', static fn () => category_exists('zz-terms-one'));
$ask('category_exists, under the right parent', static fn () => category_exists('ZZ Terms Child', $parent));
$ask('category_exists, under another parent', static fn () => category_exists('ZZ Terms Child', 0));
$ask('wp_insert_category, new with everything', static fn () => wp_insert_category(['cat_name' => 'ZZ Terms Three', 'category_description' => 'A <b>described</b> category.', 'category_nicename' => 'zz-terms-third', 'category_parent' => $parent]));
$three = get_term_by('slug', 'zz-terms-third', 'category');
$say('wp_insert_category, what it made', $three instanceof WP_Term ? [$three->name, $three->slug, $three->description, get_term($three->parent)->name ?? null] : null);
$ask('wp_insert_category, a duplicate', static fn () => wp_insert_category(['cat_name' => 'ZZ Terms Three', 'category_parent' => $parent]));
$ask('wp_insert_category, a duplicate, asking for the error', static fn () => wp_insert_category(['cat_name' => 'ZZ Terms Three', 'category_parent' => $parent], true));
$ask('wp_insert_category, no name', static fn () => wp_insert_category(['cat_name' => '']));
$ask('wp_insert_category, no name, asking for the error', static fn () => wp_insert_category(['cat_name' => ''], true));
$ask('wp_insert_category, updating by cat_ID', static fn () => wp_insert_category(['cat_ID' => $three->term_id, 'cat_name' => 'ZZ Terms Three Renamed']));
$ask('wp_insert_category, as a tag', static fn () => wp_insert_category(['cat_name' => 'ZZ Terms Tagged', 'taxonomy' => 'post_tag']));
$ask('wp_update_category', static fn () => wp_update_category(['cat_ID' => $three->term_id, 'cat_name' => 'ZZ Terms Three Again', 'category_description' => 'Changed.']));
$again = get_term($three->term_id);
$say('wp_update_category, what it left', $again instanceof WP_Term ? [$again->name, $again->slug, $again->description, get_term($again->parent)->name ?? null] : null);
$ask('wp_update_category, a parent of itself', static fn () => wp_update_category(['cat_ID' => $three->term_id, 'category_parent' => $three->term_id]));
$ask('wp_create_term, new tag', static fn () => wp_create_term('ZZ Terms Tag', 'post_tag'));
$ask('wp_create_term, again', static fn () => wp_create_term('ZZ Terms Tag', 'post_tag'));
$ask('wp_create_tag', static fn () => wp_create_tag('ZZ Terms Tag Two'));
$ask('tag_exists, by name', static fn () => tag_exists('ZZ Terms Tag'));
$ask('tag_exists, one not there', static fn () => tag_exists('ZZ Terms No Tag'));

$post = wp_insert_post(['post_title' => 'ZZ Terms Post', 'post_status' => 'draft', 'post_content' => '']);
$made['posts'][] = $post;
$ask('wp_create_categories, onto a post', static fn () => wp_create_categories(['ZZ Terms One', 'ZZ Terms Four'], $post));
$say('the post\'s categories after', array_map(static fn ($t) => $t->name, wp_get_post_categories($post, ['fields' => 'all'])));
wp_set_post_tags($post, ['ZZ Terms Tag', 'ZZ Terms, with a comma', 'ZZ Terms Tag Two']);
$ask('get_tags_to_edit', static fn () => get_tags_to_edit($post));
$ask('get_terms_to_edit, categories', static fn () => get_terms_to_edit($post, 'category'));
$ask('get_terms_to_edit, a post with none', static fn () => get_terms_to_edit($post, 'post_format'));
$ask('get_terms_to_edit, a taxonomy not there', static fn () => get_terms_to_edit($post, 'zz_no_taxonomy'));

$comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'ZZ Terms Reader', 'comment_date' => '2020-02-03 04:05:06', 'comment_date_gmt' => '2020-02-03 09:05:06', 'comment_content' => 'Hello.', 'comment_approved' => 1]);
$made['comments'][] = $comment;
$ask('comment_exists, by local time', static fn () => comment_exists('ZZ Terms Reader', '2020-02-03 04:05:06'));
$ask('comment_exists, by GMT', static fn () => comment_exists('ZZ Terms Reader', '2020-02-03 09:05:06', 'gmt'));
$ask('comment_exists, the wrong time', static fn () => comment_exists('ZZ Terms Reader', '2020-02-03 09:05:06'));
$ask('comment_exists, author slashed', static fn () => comment_exists(wp_slash('ZZ Terms Reader'), '2020-02-03 04:05:06'));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
