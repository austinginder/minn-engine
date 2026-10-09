<?php
/**
 * template.php's template getters (probe template-getters): for each query a
 * theme can be asked to render (404, archives, attachments, authors,
 * categories, dates, embeds, the front and posts pages, pages, a post type
 * archive, the privacy policy page, search, singles, tags and terms), the
 * template hierarchy each getter offers, what it answers (the block template
 * canvas, a theme file, or nothing), and the block template it picks. The
 * canvas lives in a different folder on each stack, so it is named rather
 * than located. The probe's content is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
$privacy = get_option('wp_page_for_privacy_policy', null);
// set_post_format makes the format's term when it is new; other probes count those terms.
$galleryTerm = term_exists('post-format-gallery', 'post_format');
register_shutdown_function(static function () use (&$made, $privacy, $galleryTerm): void {
    $privacy === null ? delete_option('wp_page_for_privacy_policy') : update_option('wp_page_for_privacy_policy', $privacy);
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
    $gallery = $galleryTerm ? null : get_term_by('slug', 'post-format-gallery', 'post_format');
    if ($gallery instanceof WP_Term && $gallery->term_id > 0) {
        wp_delete_term($gallery->term_id, 'post_format');
    }
});
$ids = [];
$label = static function ($value) use (&$label, &$ids) {
    if (is_array($value)) {
        return array_map($label, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $name . '}', $value);
    }
    return $value;
};
$answer = static function (string $template): string {
    if ($template === '') {
        return '';
    }
    if (str_ends_with($template, '/template-canvas.php')) {
        return 'canvas';
    }
    $themes = wp_normalize_path(get_theme_root());
    $template = wp_normalize_path($template);
    return str_starts_with($template, $themes) ? 'theme:' . substr($template, strlen($themes) + 1) : 'elsewhere:' . basename($template);
};

register_taxonomy('zz_tg_genre', 'post', ['public' => true, 'rewrite' => false]);
register_post_type('zz_tg_book', ['public' => true, 'has_archive' => true, 'rewrite' => false]);
$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x']);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$category = $term('zz tg café', 'category');
$tag = $term('zz tg tag', 'post_tag');
$genre = $term('zz tg mystery', 'zz_tg_genre');
$single = $post('zz tg single', ['post_category' => [$category], 'tags_input' => ['zz tg tag']]);
wp_set_object_terms($single, [$genre], 'zz_tg_genre');
set_post_format($single, 'gallery');
$page = $post('zz tg page', ['post_type' => 'page']);
$templated = $post('zz tg templated', ['post_type' => 'page', 'page_template' => 'zz-custom.php']);
$book = $post('zz tg book', ['post_type' => 'zz_tg_book']);
$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz tg image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], '2026/10/zz-tg-image.png', $single);
$ids[$image] = 'image';
$made['posts'][] = $pdf = (int) wp_insert_attachment(['post_title' => 'zz tg pdf', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'], '2026/10/zz-tg-doc.pdf', 0);
$ids[$pdf] = 'pdf';
$ids[1] = 'admin';
update_option('wp_page_for_privacy_policy', $page);

$types = ['404', 'archive', 'attachment', 'author', 'category', 'date', 'embed', 'frontpage', 'home', 'index', 'page', 'paged', 'privacypolicy', 'search', 'single', 'singular', 'tag', 'taxonomy'];
$offered = [];
foreach ($types as $type) {
    add_filter("{$type}_template_hierarchy", static function ($templates) use ($type, &$offered) {
        $offered[] = [$type, $templates];
        return $templates;
    });
}
$getters = ['get_404_template' => static fn () => get_404_template(), 'get_archive_template' => static fn () => get_archive_template(), 'get_attachment_template' => static fn () => get_attachment_template(), 'get_author_template' => static fn () => get_author_template(), 'get_category_template' => static fn () => get_category_template(), 'get_date_template' => static fn () => get_date_template(), 'get_embed_template' => static fn () => get_embed_template(), 'get_front_page_template' => static fn () => get_front_page_template(), 'get_home_template' => static fn () => get_home_template(), 'get_index_template' => static fn () => get_index_template(), 'get_page_template' => static fn () => get_page_template(), 'get_post_type_archive_template' => static fn () => get_post_type_archive_template(), 'get_privacy_policy_template' => static fn () => get_privacy_policy_template(), 'get_search_template' => static fn () => get_search_template(), 'get_single_template' => static fn () => get_single_template(), 'get_singular_template' => static fn () => get_singular_template(), 'get_tag_template' => static fn () => get_tag_template(), 'get_taxonomy_template' => static fn () => get_taxonomy_template()];
$query = static function (array $args): void {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query($args);
    $post = $wp_query->post ?? null;
};
$ask = static function (string $name) use ($getters, &$offered, $answer, $label): array {
    $offered = [];
    $GLOBALS['_wp_current_template_id'] = null;
    $template = (string) $getters[$name]();
    return [$name, $label($offered), $answer($template), $label($GLOBALS['_wp_current_template_id'] ?? null)];
};

$contexts = [
    'a single post' => [['p' => $single], ['get_single_template', 'get_singular_template', 'get_embed_template', 'get_index_template']],
    'a page' => [['page_id' => $page], ['get_page_template', 'get_singular_template', 'get_privacy_policy_template', 'get_front_page_template']],
    'a page with its own template' => [['page_id' => $templated], ['get_page_template']],
    'a book' => [['p' => $book, 'post_type' => 'zz_tg_book'], ['get_single_template', 'get_embed_template']],
    'an image' => [['p' => $image, 'post_type' => 'attachment', 'post_status' => 'inherit'], ['get_attachment_template', 'get_single_template', 'get_embed_template']],
    'a PDF' => [['p' => $pdf, 'post_type' => 'attachment', 'post_status' => 'inherit'], ['get_attachment_template']],
    'a category' => [['cat' => $category], ['get_category_template', 'get_archive_template']],
    'a tag' => [['tag_id' => $tag], ['get_tag_template']],
    'a term' => [['zz_tg_genre' => 'zz-tg-mystery'], ['get_taxonomy_template']],
    'an author' => [['author' => 1], ['get_author_template']],
    'a date' => [['year' => 1990, 'monthnum' => 1], ['get_date_template', 'get_archive_template']],
    'the books archive' => [['post_type' => 'zz_tg_book'], ['get_post_type_archive_template', 'get_archive_template']],
    'search' => [['s' => 'zz tg nothing'], ['get_search_template']],
    'nothing found' => [['p' => 999999], ['get_404_template', 'get_home_template']],
];
foreach ($contexts as $context => [$args, $names]) {
    $query($args);
    $say($context, array_map($ask, $names));
}

// Page templates given on insert: a theme's own is kept, anything else refused.
$inserted = [];
$heard = [];
foreach (['save_post', 'wp_insert_post', 'wp_after_insert_post'] as $hook) {
    add_action($hook, $listeners[$hook] = static function () use ($hook, &$heard) {
        $heard[] = $hook;
    });
}
$pageTemplates = wp_get_theme()->get_page_templates(null, 'page');
$validTemplate = (string) array_key_first($pageTemplates);
foreach ([['zz-custom.php', true], ['zz-custom.php', false], [$validTemplate, true], ['default', true], ['', true]] as $i => [$template, $error]) {
    $heard = [];
    $result = wp_insert_post(['post_title' => "zz tg insert {$i}", 'post_type' => 'page', 'post_status' => 'publish', 'page_template' => $template, 'meta_input' => ['zz_tg_meta' => 'kept']], $error);
    $found = get_posts(['post_type' => 'page', 'post_status' => 'any', 'title' => "zz tg insert {$i}", 'fields' => 'ids', 'suppress_filters' => false]);
    foreach ($found as $id) {
        $made['posts'][] = (int) $id;
    }
    $id = (int) ($found[0] ?? 0);
    $inserted[] = [
        $template === $validTemplate ? '(a theme template)' : $template, $error,
        is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message()] : (is_int($result) && $result > 0 ? ($result === $id ? 'the post' : 'another id') : $result),
        count($found), $id ? get_post_status($id) : null, $id ? (get_post_meta($id, '_wp_page_template', true) === $validTemplate && $validTemplate !== '' ? '(a theme template)' : get_post_meta($id, '_wp_page_template', true)) : null, $id ? get_post_meta($id, 'zz_tg_meta', true) : null, $heard,
    ];
}
foreach ($listeners as $hook => $listener) {
    remove_action($hook, $listener);
}
$say('page templates on insert', [$validTemplate !== '', $inserted]);

// A theme filter's own hierarchy, and the template filter's answer.
add_filter('single_template_hierarchy', $extra = static fn ($templates) => array_merge(['zz-first.php'], $templates));
add_filter('single_template', $chosen = static fn ($template, $type, $templates) => $template . '|' . $type . '|' . implode(',', $templates), 10, 3);
$query(['p' => $single]);
$filtered = (string) get_single_template();
remove_filter('single_template_hierarchy', $extra);
remove_filter('single_template', $chosen, 10);
[$file, $type, $list] = explode('|', $filtered);
$say('filtered single template', $label([$answer($file), $type, $list]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
