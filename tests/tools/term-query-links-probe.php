<?php
/**
 * Term links once a taxonomy's link structure is gone (probe
 * term-query-links): a category, a tag and a post format linked through
 * their permastructs, then by query once remove_permastruct() drops them at
 * runtime (a category by its id, a tag and a format by their query vars),
 * and get_post_format_link() following. Nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = home_url();
$rel = static fn ($link) => is_string($link) ? str_replace($home, '{home}', $link) : (is_wp_error($link) ? $link->get_error_code() : $link);
$tags = get_terms(['taxonomy' => 'post_tag', 'number' => 1, 'hide_empty' => false, 'orderby' => 'term_id']);
$terms = ['a category' => get_term(1, 'category'), 'a post format' => get_term_by('slug', 'post-format-aside', 'post_format') ?: null, 'a tag' => $tags[0] ?? null];
$links = static fn (): array => array_map(static fn ($term) => $term instanceof WP_Term ? $rel(get_term_link($term)) : null, $terms);

$say('linked through their permastructs', $links());
$say('a format link through its permastruct', $rel(get_post_format_link('aside')));
$say('the permastructs', array_keys(array_intersect_key($GLOBALS['wp_rewrite']->extra_permastructs, array_flip(['category', 'post_tag', 'post_format']))));
remove_permastruct('post_format');
$say('a format linked once its permastruct is removed', [$rel(get_term_link($terms['a post format'])), $rel(get_post_format_link('aside'))]);
remove_permastruct('category');
remove_permastruct('post_tag');
$say('every term linked by query once the permastructs are removed', $links());
$say('a structure pre_term_link gives back', (static function () use ($terms, $rel) {
    $give = static fn ($structure, $term) => $term->taxonomy === 'post_tag' ? '/zz-tags/%post_tag%' : $structure;
    add_filter('pre_term_link', $give, 10, 2);
    $link = $rel(get_term_link($terms['a tag']));
    remove_filter('pre_term_link', $give, 10);
    return $link;
})());
echo json_encode($log, JSON_UNESCAPED_SLASHES), "\n";
