<?php
/**
 * The view configuration functions (probe view-config-functions): a
 * configuration's filter name, the default post type form, each core
 * type's own patch (page, patterns, templates, template parts) on an empty
 * configuration and on one with values of its own, the hooks that apply
 * them, and the configuration an entity ends with: core types, a plugin's
 * type, a missing one, the site, other kinds, and one a plugin patches on
 * its filter. Registrations and filters live for the request only.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
$doingItWrong = [];
add_action('doing_it_wrong_run', static function ($function) use (&$doingItWrong): void {
    $doingItWrong[] = $function;
});
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// Pattern categories are registered on init, which the engine's probe runner does not fire.
if (!did_action('init')) {
    do_action('init');
}
// What a configuration holds, read from inside it.
$config = static fn (WP_View_Config_Data $data) => Closure::bind(fn () => $this->config, $data, WP_View_Config_Data::class)();

$say('filter names', [
    'a post type' => wp_get_entity_view_config_hook_name('postType', 'page'),
    'upper case' => wp_get_entity_view_config_hook_name('ROOT', 'Site'),
    'a mixed-case type' => wp_get_entity_view_config_hook_name('postType', 'WP_Block'),
    'nothing' => wp_get_entity_view_config_hook_name('', ''),
]);
$say('the default post type form', _wp_get_default_posttype_form());
$say('the core patches, hooked', array_map(static fn ($type) => has_filter("get_entity_view_config_posttype_{$type}", "_wp_get_entity_view_config_posttype_{$type}"), ['page' => 'page', 'wp_block' => 'wp_block', 'wp_template' => 'wp_template', 'wp_template_part' => 'wp_template_part', 'post' => 'post']));

// Each core patch called by its name, on an empty configuration and on one with values of its own.
$starts = [
    'on an empty configuration' => ['version' => 1, 'default_view' => [], 'default_layouts' => [], 'view_list' => [], 'form' => []],
    'on one with values of its own' => ['version' => 1, 'default_view' => ['type' => 'grid', 'zz' => 1, 'perPage' => 5], 'default_layouts' => ['zz' => ['a' => 1], 'table' => ['zzt' => 1]], 'view_list' => [['title' => 'Zz', 'slug' => 'zz']], 'form' => ['zz' => 1]],
];
$patch = static function (Closure $apply) use ($starts, $config, $try) {
    $out = [];
    foreach ($starts as $label => $start) {
        $data = new WP_View_Config_Data($start);
        $out[$label] = $try(static fn () => ['returned the same' => $apply($data) === $data, 'config' => $config($data)]);
    }
    return $out;
};
$patched = [
    'page' => $patch(static fn ($data) => _wp_get_entity_view_config_posttype_page($data)),
    'wp_block' => $patch(static fn ($data) => _wp_get_entity_view_config_posttype_wp_block($data)),
    'wp_template' => $patch(static fn ($data) => _wp_get_entity_view_config_posttype_wp_template($data)),
    'wp_template_part' => $patch(static fn ($data) => _wp_get_entity_view_config_posttype_wp_template_part($data)),
];
$say('the core patches', $patched);

register_post_type('zzview', ['public' => true, 'show_in_rest' => true, 'labels' => ['name' => 'Zz Views', 'all_items' => 'Every Zz View']]);
$say('configurations', [
    'pages' => $try(static fn () => wp_get_entity_view_config('postType', 'page')),
    'posts' => $try(static fn () => wp_get_entity_view_config('postType', 'post')),
    'patterns' => $try(static fn () => wp_get_entity_view_config('postType', 'wp_block')),
    'templates' => $try(static fn () => wp_get_entity_view_config('postType', 'wp_template')),
    'template parts' => $try(static fn () => wp_get_entity_view_config('postType', 'wp_template_part')),
    'media' => $try(static fn () => wp_get_entity_view_config('postType', 'attachment')),
    'a plugin\'s type' => $try(static fn () => wp_get_entity_view_config('postType', 'zzview')),
    'a missing type' => $try(static fn () => wp_get_entity_view_config('postType', 'zz-none')),
    'the site' => $try(static fn () => wp_get_entity_view_config('root', 'site')),
    'a taxonomy' => $try(static fn () => wp_get_entity_view_config('taxonomy', 'category')),
    'the kind in lower case' => $try(static fn () => wp_get_entity_view_config('posttype', 'page')),
]);

// A plugin patches a configuration on its filter, in any case it was asked for.
add_filter('get_entity_view_config_posttype_zzview', static fn ($data) => $data->merge(['view_list' => [['title' => 'Zz Mine', 'slug' => 'zz-mine']], 'default_view' => ['perPage' => 50]], 1));
add_filter('get_entity_view_config_root_site', static fn ($data) => $data->set(['default_view' => ['type' => 'list']], 1));
$say('configurations a plugin patched', [
    'its type' => $try(static fn () => wp_get_entity_view_config('postType', 'zzview')),
    'its type, asked in another case' => $try(static fn () => wp_get_entity_view_config('postType', 'ZZView')),
    'the site' => $try(static fn () => wp_get_entity_view_config('root', 'site')),
    'the site, asked in upper case' => $try(static fn () => wp_get_entity_view_config('ROOT', 'SITE')),
]);
unregister_post_type('zzview');
$say('mistakes reported', $doingItWrong);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
