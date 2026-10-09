<?php
/**
 * The block bindings and block templates registries used directly (probe
 * block-registries): sources registered, refused, looked up and removed, a
 * source's value through its callback and filter, and plugin templates
 * registered, refused, found by slug and by query, and removed. Everything
 * the probe registers is removed again; nothing is stored.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message, $version) use (&$wrong) {
    $wrong[] = [$function, wp_strip_all_tags((string) $message), $version];
}, 10, 3);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function () use (&$wrong) {
    $out = $wrong;
    $wrong = [];
    return $out;
};
$error = static fn ($value) => is_wp_error($value) ? ['WP_Error', $value->get_error_code(), $value->get_error_message()] : $value;

$bindings = WP_Block_Bindings_Registry::get_instance();
$source = static fn ($value) => $value instanceof WP_Block_Bindings_Source ? [get_class($value), $value->name, $value->label, $value->uses_context ?? null] : $value;
$callback = static fn (array $args, $block, string $attribute) => 'value:' . ($args['key'] ?? '') . ':' . $attribute;
$say('a bindings source registered', [
    [get_class($bindings), $bindings === WP_Block_Bindings_Registry::get_instance()],
    [$source($bindings->register('zz-probe/source', ['label' => 'Zz Source', 'get_value_callback' => $callback, 'uses_context' => ['postId']])), $heard()],
    [$source($bindings->register('zz-probe/source', ['label' => 'Again', 'get_value_callback' => $callback])), $heard()],
    [$bindings->register('zz-probe', ['label' => 'No namespace', 'get_value_callback' => $callback]), $heard()],
    [$bindings->register('Zz-Probe/Upper', ['label' => 'Upper', 'get_value_callback' => $callback]), $heard()],
    [$bindings->register(5, ['label' => 'Number', 'get_value_callback' => $callback]), $heard()],
    [$bindings->register('zz-probe/no-label', ['get_value_callback' => $callback]), $heard()],
    [$bindings->register('zz-probe/no-callback', ['label' => 'No callback']), $heard()],
    [$bindings->register('zz-probe/bad-callback', ['label' => 'Bad', 'get_value_callback' => 'zz_not_a_function']), $heard()],
    [$bindings->register('zz-probe/bad-context', ['label' => 'Bad context', 'get_value_callback' => $callback, 'uses_context' => 'postId']), $heard()],
    [$source($bindings->register('zz-probe/extra', ['label' => 'Extra', 'get_value_callback' => $callback, 'zz' => 1])), $heard()],
]);
$say('a bindings source looked up', [
    $source($bindings->get_registered('zz-probe/source')),
    $bindings->get_registered('zz-probe/none'),
    [$bindings->is_registered('zz-probe/source'), $bindings->is_registered('zz-probe/none'), $bindings->is_registered('')],
    array_values(array_filter(array_keys($bindings->get_all_registered()), static fn ($name) => str_starts_with($name, 'zz-probe/'))),
]);
$say('a bindings source\'s value', (static function () use ($bindings) {
    $registered = $bindings->get_registered('zz-probe/source');
    $out = [$registered->get_value(['key' => 'k'], null, 'content')];
    $heard = [];
    $filter = static function ($value, ...$rest) use (&$heard) {
        $heard[] = [$value, count($rest), $rest[0] ?? null, gettype($rest[1] ?? null), $rest[2] ?? null];
        return $value . '|filtered';
    };
    add_filter('block_bindings_source_value', $filter, 10, 5);
    $out[] = $registered->get_value(['key' => 'k2'], null, 'url');
    remove_filter('block_bindings_source_value', $filter, 10);
    $out[] = $heard;
    $own = new WP_Block_Bindings_Source('zz-probe/own', ['label' => 'Own', 'get_value_callback' => static fn () => 42, 'uses_context' => ['zz']]);
    $out[] = [$own->name, $own->label, $own->uses_context, $own->get_value([], null, 'x')];
    foreach ([$bindings, $own] as $object) {
        try {
            $object->__wakeup();
            $out[] = 'woke';
        } catch (Throwable $thrown) {
            $out[] = [get_class($thrown), $thrown->getMessage()];
        }
    }
    return $out;
})());
$say('a bindings source removed', [
    [$source($bindings->unregister('zz-probe/source')), $bindings->is_registered('zz-probe/source'), $heard()],
    [$bindings->unregister('zz-probe/source'), $heard()],
    [$source($bindings->unregister('zz-probe/extra')), $heard()],
]);

$templates = WP_Block_Templates_Registry::get_instance();
$template = static function ($value) {
    if (!$value instanceof WP_Block_Template) {
        return is_wp_error($value) ? ['WP_Error', $value->get_error_code(), $value->get_error_message()] : $value;
    }
    $vars = get_object_vars($value);
    return $vars;
};
$say('a plugin template registered', [
    [get_class($templates), $templates === WP_Block_Templates_Registry::get_instance()],
    [$template($templates->register('zz-plugin//zz-tpl', ['title' => 'Zz Tpl', 'description' => 'Zz described', 'content' => '<!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->', 'post_types' => ['page']])), $heard()],
    [$template($templates->register('zz-plugin//zz-tpl', ['title' => 'Again'])), $heard()],
    [$template($templates->register('zz-plugin//zz-bare', [])), $heard()],
    [$template($templates->register('zz-no-slashes', ['title' => 'Bad'])), $heard()],
    [$template($templates->register('Zz-Plugin//Upper', ['title' => 'Upper'])), $heard()],
    [$template($templates->register('', ['title' => 'Empty'])), $heard()],
    [$template($templates->register(5, ['title' => 'Number'])), $heard()],
    [$template($templates->register('zz-plugin//zz-other', ['title' => 'Other', 'post_types' => ['post', 'page'], 'zz' => 1])), $heard()],
]);
$say('plugin templates found', [
    $template($templates->get_registered('zz-plugin//zz-tpl')),
    $templates->get_registered('zz-plugin//none'),
    $template($templates->get_by_slug('zz-tpl')),
    $templates->get_by_slug('zz-none'),
    array_keys(array_filter($templates->get_by_query(['slug__in' => ['zz-tpl', 'zz-other', 'zz-bare']]), static fn ($t) => str_starts_with((string) $t->slug, 'zz-'))),
    array_keys(array_filter($templates->get_by_query(['slug__not_in' => ['zz-tpl']]), static fn ($t) => str_starts_with((string) $t->slug, 'zz-'))),
    array_keys(array_filter($templates->get_by_query(['post_type' => 'post']), static fn ($t) => str_starts_with((string) $t->slug, 'zz-'))),
    array_keys(array_filter($templates->get_by_query(['post_type' => 'page', 'slug__in' => ['zz-other']]), static fn ($t) => str_starts_with((string) $t->slug, 'zz-'))),
    array_keys(array_filter($templates->get_by_query([]), static fn ($t) => str_starts_with((string) $t->slug, 'zz-'))),
    [$templates->is_registered('zz-plugin//zz-tpl'), $templates->is_registered('zz-plugin//none'), $templates->is_registered('')],
    array_values(array_filter(array_keys($templates->get_all_registered()), static fn ($name) => str_starts_with($name, 'zz-plugin//'))),
]);
$say('plugin templates removed', [
    [$template($templates->unregister('zz-plugin//zz-tpl')), $templates->is_registered('zz-plugin//zz-tpl'), $heard()],
    [$template($templates->unregister('zz-plugin//zz-tpl')), $heard()],
    [$template($templates->unregister('zz-plugin//zz-bare')), $template($templates->unregister('zz-plugin//zz-other')), $heard()],
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
