<?php
/**
 * Looking a term up by name, slug or id, and what each lookup hands
 * pre_term_name: a parent with a name that saving changes ("A & B" is
 * stored "A &amp; B"), a child under it, and a term whose name is another
 * term's slug. Same protocol as api-probe.php; the terms are its own and
 * go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
foreach (get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'search' => 'zz lookup']) as $old) {
    wp_delete_term($old->term_id, 'category');
}
foreach (['zz-lookup-slug-of-one', 'zz-lookup-name-is-slug', 'zz-lookup-quoted-custom'] as $slug) {
    $old = get_term_by('slug', $slug, 'category');
    if ($old) {
        wp_delete_term($old->term_id, 'category');
    }
}
$made = [];
$parent = wp_insert_term('zz lookup A & B', 'category');
$made[] = (int) $parent['term_id'];
$child = wp_insert_term('zz lookup child & co', 'category', ['parent' => (int) $parent['term_id']]);
$made[] = (int) $child['term_id'];
$one = wp_insert_term('zz lookup one', 'category', ['slug' => 'zz-lookup-slug-of-one']);
$made[] = (int) $one['term_id'];
$two = wp_insert_term('zz-lookup-slug-of-one', 'category', ['slug' => 'zz-lookup-name-is-slug']);
$made[] = (int) $two['term_id'];
$quoted = wp_insert_term('zz lookup "quoted" one', 'category', ['slug' => 'zz-lookup-quoted-custom']);
$made[] = (int) $quoted['term_id'];
$ids = array_flip($made);
$label = static function ($found) use ($ids, $made) {
    if (is_array($found) && isset($found['term_id'])) {
        return ['term' => $ids[(int) $found['term_id']] ?? 'other', 'types' => [gettype($found['term_id']), gettype($found['term_taxonomy_id'] ?? null)]];
    }
    if (is_object($found)) {
        return ['term' => $ids[(int) $found->term_id] ?? 'other'];
    }
    if (is_string($found) && ctype_digit($found)) {
        return ['term' => $ids[(int) $found] ?? 'other', 'type' => 'string'];
    }
    return $found;
};
$seen = [];
$recorder = static function (string $hook) use (&$seen): void {
    if (preg_match('/^pre_(term|category|_)?_?name$/', $hook)) {
        $seen[] = $hook . ' ' . json_encode(array_slice(func_get_args(), 1));
    }
};
$check = static function (string $name, callable $lookup) use (&$seen, $recorder, $say, $label, &$made): void {
    $seen = [];
    add_action('all', $recorder);
    $found = $lookup();
    remove_action('all', $recorder);
    $mask = static fn (string $line): string => (string) preg_replace_callback('/\b\d+\b/', static fn ($m) => in_array((int) $m[0], $made, true) ? '{term' . array_search((int) $m[0], $made, true) . '}' : $m[0], $line);
    $say($name, ['found' => $label($found), 'filters' => array_map($mask, $seen)]);
};
$parentId = (int) $parent['term_id'];
$check('term_exists the raw name', static fn () => term_exists('zz lookup A & B', 'category'));
$check('term_exists the stored name', static fn () => term_exists('zz lookup A &amp; B', 'category'));
$check('term_exists the raw name, no taxonomy', static fn () => term_exists('zz lookup A & B'));
$check('term_exists a child by raw name and parent', static fn () => term_exists('zz lookup child & co', 'category', $parentId));
$check('term_exists a child by stored name and parent', static fn () => term_exists('zz lookup child &amp; co', 'category', $parentId));
$check('term_exists a child under the wrong parent', static fn () => term_exists('zz lookup child & co', 'category', 1));
$check('term_exists a slug that is another name', static fn () => term_exists('zz-lookup-slug-of-one', 'category'));
$check('term_exists by id string', static fn () => term_exists((string) $made[2], 'category'));
$check('term_exists by id', static fn () => term_exists($made[2]));
$check('term_exists by id, wrong taxonomy', static fn () => term_exists($made[2], 'post_tag'));
$check('get_term_by raw name', static fn () => get_term_by('name', 'zz lookup A & B', 'category'));
$check('get_term_by stored name', static fn () => get_term_by('name', 'zz lookup A &amp; B', 'category'));
$check('get_term_by slug of a name', static fn () => get_term_by('slug', 'zz lookup A & B', 'category'));
$check('get_terms raw name', static fn () => array_map($label, get_terms(['taxonomy' => 'category', 'name' => 'zz lookup A & B', 'hide_empty' => false])));
$check('get_terms stored name', static fn () => array_map($label, get_terms(['taxonomy' => 'category', 'name' => 'zz lookup A &amp; B', 'hide_empty' => false])));
$check('get_terms name list', static fn () => array_map($label, get_terms(['taxonomy' => 'category', 'name' => ['zz lookup one', 'zz lookup child & co'], 'hide_empty' => false, 'orderby' => 'term_id'])));
$check('get_terms name, no taxonomy', static fn () => array_map($label, get_terms(['name' => 'zz lookup one', 'hide_empty' => false])));
$check('term_exists a quoted name', static fn () => term_exists('zz lookup "quoted" one', 'category'));
$check('get_term_by a quoted name', static fn () => get_term_by('name', 'zz lookup "quoted" one', 'category'));
$check('get_terms a quoted name', static fn () => array_map($label, get_terms(['taxonomy' => 'category', 'name' => 'zz lookup "quoted" one', 'hide_empty' => false])));
$check('get_terms under a childless parent', static fn () => get_terms(['taxonomy' => 'category', 'parent' => $made[2], 'name' => 'zz lookup one', 'hide_empty' => false]));
$check('a count under a childless parent', static fn () => get_terms(['taxonomy' => 'category', 'parent' => $made[2], 'hide_empty' => false, 'fields' => 'count']));
$stored = get_term($parentId, 'category');
$say('stored', [$stored->name, $stored->slug, get_term($made[1], 'category')->name, get_term($made[1], 'category')->slug]);
foreach (array_reverse($made) as $id) {
    wp_delete_term($id, 'category');
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
