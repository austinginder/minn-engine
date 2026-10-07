<?php
/**
 * Post format terms read by their format's name (probe post-format-terms):
 * get_term, get_term_by, get_terms in each fields shape, wp_get_object_terms
 * and get_the_terms on a post with a format, and the three filters that do
 * the naming called with an unknown format, a term of another taxonomy and
 * a non-object. Same protocol as api-probe.php; the post's format is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$name = static fn ($term) => is_object($term) ? [$term->taxonomy ?? null, $term->slug ?? null, $term->name ?? null] : $term;
$aside = get_term_by('slug', 'post-format-aside', 'post_format');
$id = $aside ? (int) $aside->term_id : 0;
$say('get_term', $name(get_term($id, 'post_format')));
$say('get_term by object', $name(get_term($aside)));
$say('get_term_by slug', $name(get_term_by('slug', 'post-format-aside', 'post_format')));
$say('get_term_by name', $name(get_term_by('name', 'Aside', 'post_format')));
$say('get_term_by stored name', $name(get_term_by('name', 'post-format-aside', 'post_format')));
foreach (['all', 'names', 'id=>name', 'slugs', 'ids', 'id=>slug', 'count'] as $fields) {
    $terms = get_terms(['taxonomy' => 'post_format', 'hide_empty' => false, 'fields' => $fields]);
    $say("get_terms fields={$fields}", is_array($terms) ? array_values(array_map($name, $terms)) : $terms);
}
$mixed = get_terms(['taxonomy' => ['category', 'post_format'], 'hide_empty' => false, 'fields' => 'names']);
$say('get_terms two taxonomies, names', is_array($mixed) ? array_values($mixed) : $mixed);
$posts = get_posts(['numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$post = $posts[0] ?? null;
$before = $post ? get_post_format($post) : false;
try {
    if ($post) {
        set_post_format($post, 'aside');
        clean_object_term_cache($post->ID, 'post');
        $say('wp_get_object_terms', array_map($name, wp_get_object_terms($post->ID, 'post_format')));
        $say('wp_get_object_terms names', wp_get_object_terms($post->ID, 'post_format', ['fields' => 'names']));
        $say('wp_get_object_terms two taxonomies', array_map($name, wp_get_object_terms($post->ID, ['category', 'post_format'])));
        $say('get_the_terms', array_map($name, (array) get_the_terms($post->ID, 'post_format')));
        $say('get_post_format', get_post_format($post));
    }
} finally {
    if ($post) {
        set_post_format($post, $before ?: false);
        clean_object_term_cache($post->ID, 'post');
    }
}
$say('get_term filter, unknown format', $name(_post_format_get_term((object) ['taxonomy' => 'post_format', 'slug' => 'post-format-bogus', 'name' => 'Stored'])));
$say('get_term filter, standard', $name(_post_format_get_term((object) ['taxonomy' => 'post_format', 'slug' => 'post-format-standard', 'name' => 'Stored'])));
$say('get_term filter, no prefix', $name(_post_format_get_term((object) ['taxonomy' => 'post_format', 'slug' => 'aside', 'name' => 'Stored'])));
$say('get_term filter, other taxonomy', $name(_post_format_get_term((object) ['taxonomy' => 'category', 'slug' => 'post-format-aside', 'name' => 'Stored'])));
$say('get_terms filter, names of another taxonomy', _post_format_get_terms(['post-format-aside'], ['category'], ['fields' => 'names']));
$say('get_terms filter, names', _post_format_get_terms(['post-format-aside', 'Stored'], ['post_format'], ['fields' => 'names']));
$say('get_terms filter, no args', array_map($name, _post_format_get_terms([(object) ['taxonomy' => 'post_format', 'slug' => 'post-format-quote', 'name' => 'Stored']], 'post_format', [])));
$say('get_terms filter, string taxonomy', array_map($name, _post_format_get_terms([(object) ['taxonomy' => 'post_format', 'slug' => 'post-format-quote', 'name' => 'Stored']], 'post_format', ['fields' => 'all'])));
$say('object terms filter', array_map($name, _post_format_wp_get_object_terms([(object) ['taxonomy' => 'post_format', 'slug' => 'post-format-video', 'name' => 'Stored'], (object) ['taxonomy' => 'category', 'slug' => 'post-format-video', 'name' => 'Stored'], 'loose'])));
$say('hooked', [has_filter('get_post_format', '_post_format_get_term'), has_filter('get_terms', '_post_format_get_terms'), has_filter('wp_get_object_terms', '_post_format_wp_get_object_terms')]);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
