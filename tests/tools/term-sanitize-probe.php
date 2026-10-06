<?php
/**
 * sanitize_term_field and sanitize_term in each context: which filters run
 * for each field, what they are handed, and what comes back, for text with
 * markup, quotes and an ampersand and for numbers written loosely. Same
 * protocol as api-probe.php; nothing is saved.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$seen = [];
$field = '';
// Only the field's own hooks: the reference's option reads along the way are not the contract.
$recorder = static function (string $hook) use (&$seen, &$field): void {
    if (str_contains($hook, "_{$field}") && !str_starts_with($hook, 'pre_option') && !str_starts_with($hook, 'option_') && !str_starts_with($hook, 'default_option')) {
        $seen[] = $hook . '(' . implode(' | ', array_map(static fn ($a) => var_export(is_object($a) ? 'object' : $a, true), array_slice(func_get_args(), 1))) . ')';
    }
};
$text = ' A <b>"bold"</b> & &amp; \'quoted\' <script>x()</script>' . "\n" . 'line ';
$values = ['name' => $text, 'description' => $text, 'slug' => ' Some Slug & Co ', 'parent' => '12abc', 'term_id' => '7.9', 'count' => '-3', 'term_group' => '-x', 'term_taxonomy_id' => '-5', 'object_id' => '9'];
foreach (['raw', 'edit', 'db', 'display', 'attribute', 'js', 'rss', 'other'] as $context) {
    foreach ($values as $field => $value) {
        $field = (string) $field;
        foreach (['category', 'post_tag'] as $taxonomy) {
            if ($taxonomy === 'post_tag' && !in_array($field, ['name', 'slug'], true)) {
                continue;
            }
            $seen = [];
            add_action('all', $recorder);
            $out = sanitize_term_field($field, $value, 3, $taxonomy, $context);
            remove_action('all', $recorder);
            $say("{$context} {$field} {$taxonomy}", ['out' => $out, 'filters' => $seen]);
        }
    }
}
$term = (object) ['term_id' => '4', 'name' => 'A & B', 'slug' => 'a-b', 'term_group' => '0', 'term_taxonomy_id' => '6', 'taxonomy' => 'category', 'description' => '<em>d</em>', 'parent' => '0', 'count' => '2', 'filter' => 'raw'];
foreach (['db', 'display', 'edit', 'raw'] as $context) {
    $object = sanitize_term(clone $term, 'category', $context);
    $array = sanitize_term((array) $term, 'category', $context);
    $say("sanitize_term {$context}", ['object' => get_object_vars($object), 'array' => $array]);
}
$noId = (array) $term;
unset($noId['term_id']);
$say('sanitize_term without an id', sanitize_term($noId, 'category', 'display'));
$done = clone $term;
$done->filter = 'display';
$done->name = 'A & B';
$say('sanitize_term already in that context', get_object_vars(sanitize_term($done, 'category', 'display')));
// The readers that take a context, over the default category: which name filters run and what comes back.
$field = 'name';
$read = static function (string $label, callable $reader) use (&$seen, $recorder, $say): void {
    $seen = [];
    add_action('all', $recorder);
    $out = $reader();
    remove_action('all', $recorder);
    $say($label, ['out' => $out, 'filters' => $seen]);
};
$default = (int) get_option('default_category');
$read('get_term display', static function () use ($default) {
    $term = get_term($default, 'category', OBJECT, 'display');
    return [$term->filter, $term->name, $term->term_id];
});
$read('get_term edit as array', static fn () => get_term($default, 'category', ARRAY_A, 'edit')['filter']);
$read('get_term raw', static fn () => get_term($default, 'category')->filter);
$read('get_term_field name', static fn () => get_term_field('name', $default, 'category'));
$read('get_term_field name raw', static fn () => get_term_field('name', $default, 'category', 'raw'));
$read('get_term_field unknown', static fn () => get_term_field('zz_nothing', $default, 'category'));
$read('get_term_field missing term', static fn () => is_wp_error($missing = get_term_field('name', 999999, 'category')) ? $missing->get_error_code() : $missing);
$read('WP_Term filter', static function () use ($default) {
    $term = get_term($default, 'category');
    $returned = $term->filter('display');
    return [is_object($returned) ? get_class($returned) : $returned, $term->filter];
});
$read('get_category display', static fn () => get_category($default, OBJECT, 'display')->filter);
$read('get_term_by display', static fn () => get_term_by('id', $default, 'category', OBJECT, 'display')->filter);
// The callbacks each field's chains start with, as registered.
$chains = [];
foreach (['pre_term_name', 'pre_term_description', 'pre_term_slug', 'pre_term_parent', 'term_name', 'term_description', 'term_name_rss', 'term_description_rss', 'edit_term_name', 'edit_term_description', 'pre_category_name', 'pre_post_tag_name', 'category_description', 'term_slug', 'pre_category_nicename', 'pre_term_term_group', 'pre_term_count', 'wp_unique_term_slug', 'wp_insert_term_data', 'wp_update_term_data', 'term_id_filter', 'pre_insert_term'] as $hook) {
    $chains[$hook] = [];
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            $chains[$hook][] = $priority . ' ' . (is_string($callback['function']) ? $callback['function'] : 'closure') . ' ' . $callback['accepted_args'];
        }
    }
}
$say('default chains', $chains);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
