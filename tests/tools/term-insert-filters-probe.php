<?php
/**
 * What wp_insert_term and wp_update_term's filters are handed, in order,
 * and what comes of them: a category, a tag with a name that needs
 * cleaning, an edit, a name already taken, and a save a plugin changes
 * through pre_insert_term, wp_insert_term_data, pre_term_name and
 * wp_unique_term_slug. Same protocol as api-probe.php; the terms are its
 * own and go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
foreach (['category', 'post_tag'] as $taxonomy) {
    foreach (get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => 'zz term probe']) as $old) {
        wp_delete_term($old->term_id, $taxonomy);
    }
}
$watch = ['pre_insert_term', 'pre_term_name', 'pre_category_name', 'pre_post_tag_name', 'pre__name', 'pre_term_description', 'pre_category_description', 'pre_post_tag_description', 'pre_term_slug', 'pre_category_slug', 'pre_post_tag_slug', 'pre_category_nicename', 'pre_term_parent', 'pre_category_parent', 'pre_post_tag_parent', 'pre_term_term_id', 'pre_term_count', 'pre_term_term_taxonomy_id', 'pre_term_term_group', 'wp_unique_term_slug_is_bad_slug', 'wp_unique_term_slug', 'wp_insert_term_data', 'wp_insert_term_duplicate_term_check', 'term_id_filter', 'wp_update_term_parent', 'wp_update_term_data', 'create_term', 'created_term', 'edit_terms', 'edited_terms', 'edit_term_taxonomy', 'edited_term_taxonomy', 'edit_term', 'edited_term', 'saved_term'];
$describe = static function ($value) {
    if (is_array($value)) {
        $keys = array_keys($value);
        sort($keys);
        return 'array[' . implode(',', $keys) . ']';
    }
    return is_object($value) ? 'object:' . get_class($value) : $value;
};
$seen = [];
$recorder = static function (string $hook) use (&$seen, $watch, $describe): void {
    if (in_array($hook, $watch, true)) {
        $seen[] = $hook . '(' . implode(' | ', array_map(static fn ($a) => var_export($describe($a), true), array_slice(func_get_args(), 1))) . ')';
    }
};
$made = [];
$run = static function (string $label, callable $save) use (&$seen, $recorder, $say, &$made): void {
    $seen = [];
    add_action('all', $recorder);
    $result = $save();
    remove_action('all', $recorder);
    $ids = is_array($result) ? [(int) $result['term_id'], (int) $result['term_taxonomy_id']] : [];
    if ($ids !== []) {
        $made[] = $ids[0];
    }
    // This save's ids, then the probe's earlier terms by their order.
    $mask = static function (string $f) use ($ids, &$made): string {
        $f = $ids === [] ? $f : (string) preg_replace(['/\b' . $ids[0] . '\b/', '/\b' . $ids[1] . '\b/'], ['{id}', '{tt_id}'], $f);
        foreach ($made as $n => $id) {
            $f = (string) preg_replace('/\b' . $id . '\b/', '{term' . $n . '}', $f);
        }
        return $f;
    };
    $refusal = is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message(), $mask((string) json_encode($result->get_error_data()))] : null;
    $say($label, ['result' => $refusal ?? ($ids !== [] ? 'ids' : $result), 'filters' => array_map($mask, $seen)]);
};

$run('a category', static fn () => wp_insert_term('zz term probe one', 'category', ['description' => 'About <script>x</script> it']));
$run('a tag needing a clean name', static fn () => wp_insert_term('  zz term probe <b>two</b> & co ', 'post_tag'));
$run('an edit', static function () use (&$made) {
    return wp_update_term((int) $made[0], 'category', ['name' => 'zz term probe one edited', 'description' => 'New <em>words</em>']);
});
$after = get_term((int) $made[0], 'category');
$say('the edit stored', $after ? [$after->name, $after->slug, $after->description] : null);
$tag = get_term((int) ($made[1] ?? 0), 'post_tag');
$say('the tag stored', $tag ? [$tag->name, $tag->slug] : null);
$run('a name already taken', static fn () => wp_insert_term('zz term probe one edited', 'category'));

$run('a missing parent', static fn () => wp_insert_term('zz term probe orphan', 'category', ['parent' => 999999]));
$run('an empty name', static fn () => wp_insert_term('   ', 'category'));
$run('a tag name already taken', static fn () => wp_insert_term('  zz term probe <b>two</b> & co ', 'post_tag'));
$dad = wp_insert_term('zz term probe dad', 'category');
$made[] = (int) $dad['term_id'];
$run('a kid whose slug is taken', static fn () => wp_insert_term('zz term probe kid', 'category', ['parent' => (int) $dad['term_id'], 'slug' => 'zz-term-probe-one']));
$run('a slug asked for and taken', static fn () => wp_insert_term('zz term probe other', 'category', ['slug' => 'zz-term-probe-dad']));
$run('a name with no slug in it', static fn () => wp_insert_term('%%%', 'category'));
$symbols = get_term((int) end($made), 'category');
$say('its slug', $symbols ? ($symbols->slug === (string) $symbols->term_id ? '{id}' : $symbols->slug) : null);
$run('an edit to an empty name', static fn () => wp_update_term((int) $made[0], 'category', ['name' => '  ']));
$run('an edit to a taken slug', static fn () => wp_update_term((int) $made[0], 'category', ['slug' => 'zz-term-probe-dad']));
$run('an edit to its own parent', static fn () => wp_update_term((int) $made[0], 'category', ['parent' => (int) $made[0]]));
$self = get_term((int) $made[0], 'category');
$say('its own parent stored', $self ? ($self->parent === $self->term_id ? 'itself' : $self->parent) : null);
$kid = wp_insert_term('zz term probe loop kid', 'category', ['parent' => (int) $dad['term_id']]);
$made[] = (int) $kid['term_id'];
$run('an edit making a loop', static fn () => wp_update_term((int) $dad['term_id'], 'category', ['parent' => (int) $kid['term_id']]));
$say('the loop stored', [get_term((int) $dad['term_id'], 'category')->parent === (int) $kid['term_id'] ? 'dad under kid' : get_term((int) $dad['term_id'], 'category')->parent, get_term((int) $kid['term_id'], 'category')->parent === (int) $dad['term_id'] ? 'kid under dad' : get_term((int) $kid['term_id'], 'category')->parent]);
wp_update_term((int) $dad['term_id'], 'category', ['parent' => 0]);
wp_update_term((int) $kid['term_id'], 'category', ['parent' => 0]);
$run('a name with a backslash', static fn () => wp_insert_term('zz term probe back\\slash \\"q\\"', 'post_tag'));
$slashed = get_term((int) end($made), 'post_tag');
$say('the backslash stored', $slashed ? [$slashed->name, $slashed->slug] : null);
$path = wp_insert_term('zz term probe path', 'category', ['description' => 'Kept in C:\\\\files\\\\x']);
$made[] = (int) $path['term_id'];
$say('a backslash described', get_term((int) $path['term_id'], 'category')->description);
wp_update_term((int) $path['term_id'], 'category', ['name' => 'zz term probe path renamed']);
$say('kept through a rename', get_term((int) $path['term_id'], 'category')->description);
$held = wp_insert_term('zz term probe held', 'post_tag', ['slug' => 'zz-term-probe-dad-3']);
$made[] = (int) $held['term_id'];
$run('a number another taxonomy holds', static fn () => wp_insert_term('zz term probe third', 'category', ['slug' => 'zz-term-probe-dad']));
$say('numbered past it', get_term((int) end($made), 'category')->slug);
$run('a slug only another taxonomy holds', static fn () => wp_insert_term('zz term probe shared', 'post_tag', ['slug' => 'zz-term-probe-dad']));
$say('kept as asked', get_term((int) end($made), 'post_tag')->slug);
$run('an edit clearing the slug', static fn () => wp_update_term((int) $dad['term_id'], 'category', ['name' => 'zz term probe one edited', 'slug' => '']));
$cleared = get_term((int) $dad['term_id'], 'category');
$say('the cleared slug', $cleared ? $cleared->slug : null);

$changes = [
    'pre_insert_term' => static fn ($term) => is_string($term) ? $term . ' (pre)' : $term,
    'wp_insert_term_data' => static function (array $data) {
        $data['term_group'] = 7;
        return $data;
    },
    'wp_unique_term_slug' => static fn ($slug) => $slug . '-zz',
];
foreach ($changes as $hook => $callback) {
    add_filter($hook, $callback);
}
$changed = wp_insert_term('zz term probe changed', 'category');
foreach ($changes as $hook => $callback) {
    remove_filter($hook, $callback);
}
if (is_array($changed)) {
    $made[] = (int) $changed['term_id'];
    $term = get_term((int) $changed['term_id'], 'category');
    $say('a plugin changes the save', [$term->name, $term->slug, (int) $term->term_group]);
}
$refuse = static fn () => new WP_Error('zz_refused', 'Refused by a plugin.');
add_filter('pre_insert_term', $refuse);
$run('pre_insert_term refuses', static fn () => wp_insert_term('zz term probe refused', 'category'));
remove_filter('pre_insert_term', $refuse);

foreach ($made as $id) {
    $term = get_term($id);
    if ($term instanceof WP_Term) {
        wp_delete_term($id, $term->taxonomy);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
