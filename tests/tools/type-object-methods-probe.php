<?php
/**
 * Post type and taxonomy objects doing their own setup (probe
 * type-object-methods): WP_Post_Type and WP_Taxonomy built directly, as a
 * plugin would, with a range of arguments (none, public, hierarchical
 * with its own rewrite and query var, its own capability type, shown in
 * the admin only), their properties, labels and capabilities; then each
 * step of a registration called on its own and undone again: supports,
 * rewrite rules (tags, permastructs, archive and feed rules, query vars),
 * meta box callbacks, hooks, taxonomies for a type, the REST controllers,
 * and the default labels. Everything lives for the request only: nothing
 * is flushed or saved.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
global $wp_rewrite, $wp, $wp_filter, $_wp_post_type_features, $wp_taxonomies;
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$callback = static function ($cb): string {
    if (is_string($cb)) {
        return $cb;
    }
    if ($cb instanceof Closure) {
        return 'closure';
    }
    if (is_array($cb) && count($cb) === 2) {
        return (is_object($cb[0]) ? get_class($cb[0]) : (string) $cb[0]) . '::' . $cb[1];
    }
    return gettype($cb);
};
$mask = static function ($value) use (&$mask, $host, $callback) {
    if ($value instanceof Closure) {
        return 'closure';
    }
    if ($value instanceof WP_REST_Controller) {
        return ['controller' => get_class($value)];
    }
    if (is_object($value)) {
        $value = ['class' => get_class($value)] + get_object_vars($value);
    }
    if (is_array($value)) {
        if (count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
            return $callback($value);
        }
        return array_map($mask, $value);
    }
    return is_string($value) ? str_replace($host, '{host}', $value) : $value;
};
// The hooks, rewrite state, supports and taxonomy links that name the probe's objects, to compare before and after each step.
$state = static function () use ($callback): array {
    global $wp_rewrite, $wp, $wp_filter, $_wp_post_type_features, $wp_taxonomies;
    $hooks = [];
    foreach ((array) $wp_filter as $hook => $object) {
        foreach ((array) ($object->callbacks ?? []) as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $name = $callback($entry['function']);
                if (str_contains((string) $hook, 'zz') || str_contains($name, 'zz')) {
                    $hooks[$hook][] = [$priority, $name, $entry['accepted_args']];
                }
            }
        }
    }
    $zz = static fn (array $list) => array_filter($list, static fn ($value, $key) => str_contains((string) $key, 'zz') || (is_string($value) && str_contains($value, 'zz')), ARRAY_FILTER_USE_BOTH);
    $tags = [];
    foreach ((array) $wp_rewrite->rewritecode as $i => $code) {
        if (str_contains($code, 'zz')) {
            $tags[$code] = [$wp_rewrite->rewritereplace[$i] ?? null, $wp_rewrite->queryreplace[$i] ?? null];
        }
    }
    $links = [];
    foreach ((array) $wp_taxonomies as $name => $taxonomy) {
        $types = array_values(array_filter((array) $taxonomy->object_type, static fn ($type) => str_contains((string) $type, 'zz')));
        if ($types !== [] || str_contains((string) $name, 'zz')) {
            $links[$name] = array_values((array) $taxonomy->object_type);
        }
    }
    ksort($hooks);
    return [
        'hooks' => $hooks,
        'tags' => $tags,
        'permastructs' => $zz((array) $wp_rewrite->extra_permastructs),
        'rules' => $zz((array) $wp_rewrite->extra_rules_top),
        'query vars' => array_values($zz((array) $wp->public_query_vars)),
        'supports' => $zz((array) $_wp_post_type_features),
        'taxonomy links' => $links,
    ];
};
$changed = static function (Closure $step) use ($state, $mask): array {
    $before = $state();
    try {
        $returned = $mask($step());
    } catch (Throwable $e) {
        $returned = ['threw' => get_class($e)];
    }
    $after = $state();
    $diff = [];
    foreach ($after as $key => $value) {
        if ($value !== $before[$key]) {
            $diff[$key] = ['before' => $before[$key], 'after' => $value];
        }
    }
    return ['returned' => $returned, 'changed' => $diff];
};

// Arguments as a plugin passes them, and the filters a registration offers.
$heard = [];
add_filter('register_post_type_args', static function ($args, $name) use (&$heard) {
    if (str_contains((string) $name, 'zz')) {
        $heard[] = ['register_post_type_args', $name, array_keys((array) $args)];
    }
    return $args;
}, 10, 2);
add_filter('register_zzhooked_post_type_args', static function ($args, $name) use (&$heard) {
    $heard[] = ['register_zzhooked_post_type_args', $name];
    return ['label' => 'Zz Hooked Filtered'] + (array) $args;
}, 10, 2);
add_filter('register_taxonomy_args', static function ($args, $name, $types) use (&$heard) {
    if (str_contains((string) $name, 'zz')) {
        $heard[] = ['register_taxonomy_args', $name, $types, array_keys((array) $args)];
    }
    return $args;
}, 10, 3);
add_filter('register_zztaxhooked_taxonomy_args', static function ($args, $name, $types) use (&$heard) {
    $heard[] = ['register_zztaxhooked_taxonomy_args', $name, $types];
    return ['label' => 'Zz Tax Hooked Filtered'] + (array) $args;
}, 10, 3);

$typeArgs = [
    'none' => ['zzbare', []],
    'public' => ['zzpublic', ['public' => true, 'label' => 'Zz Publics']],
    'hierarchical, its own rewrite and query var' => ['zzhier', ['public' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'zz-hier', 'with_front' => false, 'feeds' => true], 'query_var' => 'zzq', 'has_archive' => 'zz-archive', 'supports' => ['title', 'page-attributes']]],
    'its own capability type' => ['zzcaps', ['capability_type' => ['zzthing', 'zzthings'], 'map_meta_cap' => true, 'capabilities' => ['create_posts' => 'zz_create']]],
    'shown in the admin only' => ['zzadmin', ['public' => false, 'show_ui' => true, 'show_in_menu' => 'tools.php', 'show_in_rest' => true, 'rest_base' => 'zz-admins', 'menu_icon' => 'dashicons-admin-generic', 'labels' => ['name' => 'Zz Admins', 'singular_name' => 'Zz Admin']]],
    'built in, with a template' => ['zzbuilt', ['_builtin' => true, 'public' => true, 'rewrite' => false, 'query_var' => false, 'template' => [['core/paragraph']], 'template_lock' => 'all', 'delete_with_user' => true, 'exclude_from_search' => true]],
    'a filter changes it' => ['zzhooked', ['public' => true, 'label' => 'Zz Hooked']],
    'given as a query string, values kept as given' => ['zzstring', 'public=1&label=Zz Strings&hierarchical=0&show_in_rest=yes'],
];
$built = [];
$construct = [];
foreach ($typeArgs as $label => [$name, $args]) {
    try {
        $built[$name] = new WP_Post_Type($name, $args);
        $construct[$label] = $mask($built[$name]);
    } catch (Throwable $e) {
        $construct[$label] = ['threw' => get_class($e)];
    }
}
$say('post types built directly', $construct);
$say('the filters heard building them', $heard);
$heard = [];

$public = $built['zzpublic'] ?? new WP_Post_Type('zzpublic');
$public->set_props(['label' => 'Zz Changed', 'public' => true, 'show_in_nav_menus' => false]);
$say('a post type given new properties', $mask($public));

$hier = $built['zzhier'] ?? new WP_Post_Type('zzhier');
$say('a post type, step by step', [
    'its supports' => $changed(static fn () => $hier->add_supports()),
    'supports for one with none named' => $changed(static fn () => ($built['zzbare'] ?? new WP_Post_Type('zzbare'))->add_supports()),
    'supports removed' => $changed(static fn () => $hier->remove_supports()),
    'its rewrite rules' => $changed(static fn () => $hier->add_rewrite_rules()),
    'its rewrite rules removed' => $changed(static fn () => $hier->remove_rewrite_rules()),
    'rewrite rules for one with none' => $changed(static fn () => ($built['zzbuilt'] ?? new WP_Post_Type('zzbuilt'))->add_rewrite_rules()),
    'rewrite rules for one not public' => $changed(static fn () => ($built['zzadmin'] ?? new WP_Post_Type('zzadmin'))->add_rewrite_rules()),
    'its hooks' => $changed(static fn () => $hier->add_hooks()),
    'its hooks removed' => $changed(static fn () => $hier->remove_hooks()),
]);

if (!function_exists('zz_probe_meta_box')) {
    function zz_probe_meta_box(): void
    {
    }
}
$withBox = new WP_Post_Type('zzboxed', ['show_ui' => true, 'register_meta_box_cb' => 'zz_probe_meta_box', 'taxonomies' => ['category', 'post_tag', 'zz-none']]);
$noBox = new WP_Post_Type('zzunboxed', ['register_meta_box_cb' => 'zz_not_callable_at_all']);
$say('meta boxes and taxonomies', [
    'a meta box callback' => $changed(static fn () => $withBox->register_meta_boxes()),
    'removed' => $changed(static fn () => $withBox->unregister_meta_boxes()),
    'one that cannot be called' => $changed(static fn () => $noBox->register_meta_boxes()),
    'taxonomies for a type not registered' => $changed(static fn () => $withBox->register_taxonomies()),
]);
// Taxonomies link only to a registered type: undone and done again on one.
register_post_type('zzlinked', ['public' => true, 'taxonomies' => ['category', 'post_tag', 'zz-none']]);
$linked = get_post_type_object('zzlinked');
$say('a registered type\'s taxonomies', [
    'removed' => $changed(static fn () => $linked->unregister_taxonomies()),
    'added again' => $changed(static fn () => $linked->register_taxonomies()),
    'its hooks, registered' => $changed(static fn () => $linked->remove_hooks()),
]);
unregister_post_type('zzlinked');

// REST controllers come from the registered type.
register_post_type('zzrest', ['public' => true, 'show_in_rest' => true, 'supports' => ['title', 'revisions', 'autosave']]);
register_post_type('zzhidden', ['public' => true, 'show_in_rest' => false]);
register_post_type('zzbadclass', ['public' => true, 'show_in_rest' => true, 'rest_controller_class' => 'Zz_No_Such_Controller']);
$rest = get_post_type_object('zzrest');
$controllers = static fn ($type) => [
    'controller' => $mask($type->get_rest_controller()),
    'the same each time' => $type->get_rest_controller() === $type->get_rest_controller(),
    'revisions' => $mask($type->get_revisions_rest_controller()),
    'autosave' => $mask($type->get_autosave_rest_controller()),
];
$say('REST controllers', [
    'a type shown in REST' => $controllers($rest),
    'a type kept out of REST' => $controllers(get_post_type_object('zzhidden')),
    'a class that does not exist' => $controllers(get_post_type_object('zzbadclass')),
    'a core type' => $controllers(get_post_type_object('page')),
    'attachments' => $controllers(get_post_type_object('attachment')),
]);
unregister_post_type('zzrest');
unregister_post_type('zzhidden');
unregister_post_type('zzbadclass');

$defaults = WP_Post_Type::get_default_labels();
add_filter('post_type_labels_zzlabels', static fn ($labels) => $labels);
WP_Post_Type::reset_default_labels();
$say('default post type labels', [$mask($defaults), $mask(WP_Post_Type::get_default_labels()) === $mask($defaults)]);

$taxArgs = [
    'none' => ['zztaxbare', 'post', []],
    'public, for two types' => ['zztaxpublic', ['post', 'page'], ['public' => true, 'label' => 'Zz Tax Publics']],
    'hierarchical, its own rewrite and query var' => ['zztaxhier', ['post'], ['public' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'zz-tax-hier', 'with_front' => false, 'hierarchical' => true], 'query_var' => 'zztq', 'show_admin_column' => true]],
    'its own capabilities and REST' => ['zztaxcaps', ['page'], ['capabilities' => ['manage_terms' => 'zz_manage'], 'show_in_rest' => true, 'rest_base' => 'zz-tax-caps', 'default_term' => ['name' => 'Zz Default', 'slug' => 'zz-default']]],
    'shown in the admin only' => ['zztaxadmin', ['post'], ['public' => false, 'show_ui' => true, 'meta_box_cb' => false, 'sort' => true, 'args' => ['orderby' => 'term_order']]],
    'a filter changes it' => ['zztaxhooked', ['post'], ['public' => true, 'label' => 'Zz Tax Hooked']],
    'given as a query string, for no type' => ['zztaxstring', null, 'public=0&show_ui=1&hierarchical=1'],
];
$taxes = [];
$construct = [];
foreach ($taxArgs as $label => [$name, $types, $args]) {
    try {
        $taxes[$name] = new WP_Taxonomy($name, $types, $args);
        $construct[$label] = $mask($taxes[$name]);
    } catch (Throwable $e) {
        $construct[$label] = ['threw' => get_class($e)];
    }
}
$say('taxonomies built directly', $construct);
$say('the filters heard building taxonomies', $heard);

$taxPublic = $taxes['zztaxpublic'] ?? new WP_Taxonomy('zztaxpublic', 'post');
$taxPublic->set_props(['page'], ['label' => 'Zz Tax Changed', 'public' => true, 'show_tagcloud' => false]);
$say('a taxonomy given new properties', $mask($taxPublic));

$taxHier = $taxes['zztaxhier'] ?? new WP_Taxonomy('zztaxhier', 'post');
$say('a taxonomy, step by step', [
    'its rewrite rules' => $changed(static fn () => $taxHier->add_rewrite_rules()),
    'its rewrite rules removed' => $changed(static fn () => $taxHier->remove_rewrite_rules()),
    'rewrite rules for one not public' => $changed(static fn () => ($taxes['zztaxadmin'] ?? new WP_Taxonomy('zztaxadmin', 'post'))->add_rewrite_rules()),
    'its hooks' => $changed(static fn () => $taxHier->add_hooks()),
    'its hooks removed' => $changed(static fn () => $taxHier->remove_hooks()),
    'hooks for one not hierarchical' => $changed(static fn () => ($taxes['zztaxbare'] ?? new WP_Taxonomy('zztaxbare', 'post'))->add_hooks()),
    'hooks for one kept out of the admin' => $changed(static fn () => (new WP_Taxonomy('zztaxnoui', 'post', ['hierarchical' => true, 'show_ui' => false, 'public' => false]))->add_hooks()),
    'hooks for one not public but shown' => $changed(static fn () => (new WP_Taxonomy('zztaxprivate', 'post', ['public' => false, 'show_ui' => true]))->add_hooks()),
]);

register_taxonomy('zztaxrest', ['post'], ['public' => true, 'show_in_rest' => true]);
register_taxonomy('zztaxhidden', ['post'], ['public' => true]);
$say('taxonomy REST controllers', [
    'shown in REST' => [$mask(get_taxonomy('zztaxrest')->get_rest_controller()), get_taxonomy('zztaxrest')->get_rest_controller() === get_taxonomy('zztaxrest')->get_rest_controller()],
    'kept out of REST' => $mask(get_taxonomy('zztaxhidden')->get_rest_controller()),
    'a core taxonomy' => $mask(get_taxonomy('category')->get_rest_controller()),
]);
unregister_taxonomy('zztaxrest');
unregister_taxonomy('zztaxhidden');

$taxDefaults = WP_Taxonomy::get_default_labels();
WP_Taxonomy::reset_default_labels();
$say('default taxonomy labels', [$mask($taxDefaults), $mask(WP_Taxonomy::get_default_labels()) === $mask($taxDefaults)]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
