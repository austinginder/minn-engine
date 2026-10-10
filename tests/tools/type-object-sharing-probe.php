<?php
/**
 * Post type and taxonomy objects shared as the reference shares them
 * (probe type-object-sharing): one object per registered type or
 * taxonomy, the same from get_post_type_object, get_post_types,
 * $wp_post_types, register_post_type and the registered_post_type action
 * (get_taxonomy, get_taxonomies, get_object_taxonomies, $wp_taxonomies
 * and register_taxonomy for taxonomies), a new one on registering again,
 * none once unregistered, and a change a plugin makes to one (its labels,
 * a flag) seen by the lists that filter on it and by REST. Registrations
 * and changes live for the request only.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
global $wp_post_types, $wp_taxonomies;

$heard = [];
add_action('registered_post_type', static function ($name, $object) use (&$heard): void {
    $heard[$name] = $object;
}, 10, 2);
$registered = register_post_type('zzshare', ['label' => 'Zz Shares', 'public' => true, 'show_in_rest' => true]);
$object = get_post_type_object('zzshare');
$say('one post type object', [
    'the same each time' => $object === get_post_type_object('zzshare'),
    'the one registered' => $registered === $object,
    'the one the action heard' => ($heard['zzshare'] ?? null) === $object,
    'the one in $wp_post_types' => is_array($wp_post_types ?? null) && ($wp_post_types['zzshare'] ?? null) === $object,
    'the one get_post_types lists' => (get_post_types([], 'objects')['zzshare'] ?? null) === $object,
    'a core type too' => get_post_type_object('post') === get_post_type_object('post') && get_post_type_object('post') === ($wp_post_types['post'] ?? null),
    'every registered type is in $wp_post_types' => is_array($wp_post_types ?? null) && array_keys(get_post_types()) === array_keys($wp_post_types),
]);

$object->labels->name = 'Zz Renamed';
$object->labels->all_items = 'Every Renamed';
$object->label = 'Zz Renamed';
$object->show_in_nav_menus = false;
$types = new WP_REST_Request('GET', '/wp/v2/types/zzshare');
$typesResponse = rest_do_request($types);
$say('a post type changed by a plugin', [
    'get_post_type_object' => [get_post_type_object('zzshare')->labels->name, get_post_type_object('zzshare')->label],
    'get_post_type_labels' => get_post_type_labels(get_post_type_object('zzshare'))->name,
    'filtered out by the flag' => array_key_exists('zzshare', get_post_types(['show_in_nav_menus' => false])),
    'still listed among the public' => array_key_exists('zzshare', get_post_types(['public' => true])),
    'REST names it' => [$typesResponse->get_status(), $typesResponse->get_data()['name'] ?? null],
]);

$again = register_post_type('zzshare', ['label' => 'Zz Again', 'public' => true]);
$say('registered again', [
    'a new object' => $again !== $object,
    'the new one is shared' => $again === get_post_type_object('zzshare') && $again === ($wp_post_types['zzshare'] ?? null),
    'the change is gone' => get_post_type_object('zzshare')->labels->name,
]);
unregister_post_type('zzshare');
$say('unregistered', [get_post_type_object('zzshare'), array_key_exists('zzshare', (array) $wp_post_types)]);

$taxonomy = register_taxonomy('zzsharetax', ['post'], ['label' => 'Zz Share Taxes', 'show_in_rest' => true, 'public' => true]);
$tax = get_taxonomy('zzsharetax');
$say('one taxonomy object', [
    'the same each time' => $tax === get_taxonomy('zzsharetax'),
    'the one registered' => $taxonomy === $tax,
    'the one in $wp_taxonomies' => is_array($wp_taxonomies ?? null) && ($wp_taxonomies['zzsharetax'] ?? null) === $tax,
    'the one get_taxonomies lists' => (get_taxonomies([], 'objects')['zzsharetax'] ?? null) === $tax,
    'the one get_object_taxonomies lists' => (get_object_taxonomies('post', 'objects')['zzsharetax'] ?? null) === $tax,
    'a core taxonomy too' => get_taxonomy('category') === get_taxonomy('category') && get_taxonomy('category') === ($wp_taxonomies['category'] ?? null),
    'every registered taxonomy is in $wp_taxonomies' => is_array($wp_taxonomies ?? null) && array_keys(get_taxonomies()) === array_keys($wp_taxonomies),
]);
$tax->labels->name = 'Zz Renamed Taxes';
$tax->label = 'Zz Renamed Taxes';
$tax->show_tagcloud = false;
$taxResponse = rest_do_request(new WP_REST_Request('GET', '/wp/v2/taxonomies/zzsharetax'));
$say('a taxonomy changed by a plugin', [
    'get_taxonomy' => [get_taxonomy('zzsharetax')->labels->name, get_taxonomy('zzsharetax')->label],
    'get_taxonomy_labels' => get_taxonomy_labels(get_taxonomy('zzsharetax'))->name,
    'filtered by the flag' => array_key_exists('zzsharetax', get_taxonomies(['show_tagcloud' => false])),
    'REST names it' => [$taxResponse->get_status(), $taxResponse->get_data()['name'] ?? null],
]);
unregister_taxonomy('zzsharetax');
$say('taxonomy unregistered', [get_taxonomy('zzsharetax'), array_key_exists('zzsharetax', (array) $wp_taxonomies)]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
