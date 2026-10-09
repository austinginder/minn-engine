<?php
/**
 * The abilities classes (probe abilities-classes): WP_Abilities_Registry and
 * WP_Ability_Categories_Registry (one instance each, registering on their
 * init actions and refusing outside them, lookups, unregistering), the
 * ability and category objects' own steps (properties checked, input
 * normalized from the schema, the callback invoked, a run and its output
 * checked), and both refusing to be serialized. Registrations live for the
 * request only. Same protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, strip_tags((string) $message)];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$heard = static function (Closure $call) use (&$wrong) {
    $wrong = [];
    $out = $call();
    return [$out, $wrong];
};
$shape = static fn ($value) => is_object($value) ? [get_class($value), method_exists($value, 'get_name') ? $value->get_name() : (method_exists($value, 'get_slug') ? $value->get_slug() : null)] : $value;
$schema = ['type' => 'object', 'properties' => ['text' => ['type' => 'string', 'default' => 'hi'], 'times' => ['type' => 'integer', 'default' => 1, 'minimum' => 1]], 'additionalProperties' => false];
$ability = [
    'label' => 'Zz Echo', 'description' => 'Echoes text.', 'category' => 'zz-cat',
    'input_schema' => $schema, 'output_schema' => ['type' => 'string'],
    'execute_callback' => static fn ($input) => str_repeat((string) ($input['text'] ?? ''), (int) ($input['times'] ?? 1)),
    'permission_callback' => static fn () => true,
    'meta' => ['annotations' => ['readonly' => true]],
];
$during = [];
add_action('wp_abilities_api_categories_init', static function ($registry) use (&$during, $shape): void {
    $during['categories'] = [get_class($registry), $shape($registry->register('zz-cat', ['label' => 'Zz Cat', 'description' => 'A probe category.'])), $shape($registry->register('zz-cat', ['label' => 'Again', 'description' => 'Twice.']))];
});
add_action('wp_abilities_api_init', static function ($registry) use (&$during, $shape, $ability): void {
    $during['abilities'] = [
        get_class($registry),
        $shape($registry->register('zz/echo', $ability)),
        $shape($registry->register('zz/echo', $ability)),
        $shape($registry->register('Not A Name', $ability)),
        $shape($registry->register('zz/nocat', ['category' => 'zz-missing'] + $ability)),
        $shape($registry->register('zz/bad', ['label' => 'No callback', 'description' => 'x', 'category' => 'zz-cat', 'permission_callback' => '__return_true'])),
        $shape($registry->register('zz/nolabel', ['label' => '', 'category' => 'zz-missing'] + $ability)),
        $shape($registry->register('zz/badschema', ['input_schema' => 'nope'] + $ability)),
        $shape($registry->register('zz/dflt', ['input_schema' => ['type' => 'string', 'default' => 'dflt'], 'execute_callback' => static fn ($input) => strtoupper((string) $input)] + $ability)),
    ];
});

$abilities = WP_Abilities_Registry::get_instance();
$categories = WP_Ability_Categories_Registry::get_instance();
$say('during the init actions', [$during, $wrong]);
$say('the registries', (static function () use ($abilities, $categories, $shape, $heard, $ability) { return [
    $abilities === WP_Abilities_Registry::get_instance(), $categories === WP_Ability_Categories_Registry::get_instance(),
    $abilities->is_registered('zz/echo'), $abilities->is_registered('zz/bad'), $abilities->is_registered('zz/never'),
    $shape($abilities->get_registered('zz/echo')),
    $heard(static fn () => $abilities->get_registered('zz/never')),
    array_values(array_filter(array_keys($abilities->get_all_registered()), static fn ($n) => str_starts_with($n, 'zz/'))),
    count(array_filter(array_keys($abilities->get_all_registered()), static fn ($n) => str_starts_with($n, 'core/'))) > 0,
    $categories->is_registered('zz-cat'), $shape($categories->get_registered('zz-cat')), $heard(static fn () => $categories->get_registered('zz-never')),
    in_array('zz-cat', array_keys($categories->get_all_registered()), true),
    $heard(static fn () => $shape($abilities->register('zz/late', $ability))),
    $heard(static fn () => $shape($categories->register('zz-late', ['label' => 'Late', 'description' => 'Late.']))),
]; })());

$echo = $abilities->get_registered('zz/echo');
$inside = static fn (object $object, string $method, ...$args) => (new ReflectionMethod($object, $method))->invoke($object, ...$args);
$say('the ability object', [
    $echo->get_name(), $echo->get_label(), $echo->get_category(), $echo->get_meta_item('annotations'),
    $inside($echo, 'normalize_input', null), $inside($echo, 'normalize_input', ['text' => 'yo']), $inside($echo, 'normalize_input', 'scalar'),
    $echo->validate_input(['text' => 'ok', 'times' => 2]),
    $heard(static fn () => is_wp_error($r = $echo->validate_input(['times' => 0])) ? [$r->get_error_code(), $r->get_error_message()] : $r),
    $echo->execute(['text' => 'ab', 'times' => 3]),
    $echo->execute(),
    (static function () use ($echo, $inside) {
        $r = $inside($echo, 'validate_output', 7);
        return is_wp_error($r) ? [$r->get_error_code(), $r->get_error_message()] : $r;
    })(),
    $inside($echo, 'validate_output', 'fine'),
    $inside($echo, 'do_execute', ['text' => 'x', 'times' => 2]),
    $inside($echo, 'invoke_callback', static fn ($input = null) => ['called', $input], ['a' => 1]),
    $inside($echo, 'invoke_callback', static fn () => 'no input', null),
    (static function () use ($echo, $inside) {
        try {
            return $inside($echo, 'prepare_properties', ['label' => 'x']);
        } catch (Throwable $e) {
            return [get_class($e), $e->getMessage()];
        }
    })(),
    (static function () use ($echo, $inside, $ability) {
        try {
            return array_keys($inside($echo, 'prepare_properties', $ability));
        } catch (Throwable $e) {
            return [get_class($e), $e->getMessage()];
        }
    })(),
]);
$category = $categories->get_registered('zz-cat');
$dflt = $abilities->get_registered('zz/dflt');
$minimal = ['label' => 'Min', 'description' => 'Minimal.', 'category' => 'zz-cat', 'execute_callback' => '__return_true', 'permission_callback' => '__return_true'];
$caught = static function (Closure $call) {
    try {
        return $call();
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage()];
    }
};
$say('a schema default', [
    $inside($dflt, 'normalize_input', null), $inside($dflt, 'normalize_input', 'given'), $dflt->execute(), $dflt->execute('given'),
]);
$say('properties checked', [
    $caught(static fn () => $inside($echo, 'prepare_properties', [])),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['label' => 5] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['category' => ''] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['execute_callback' => 'zz_not_a_function'] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['permission_callback' => 'zz_not_a_function'] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['input_schema' => 'nope'] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['output_schema' => 'nope'] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => 'nope'] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['annotations' => 'nope']] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['show_in_rest' => 'yes']] + $minimal)),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['category' => 'zz-unregistered'] + $minimal)['category']),
    $caught(static function () use ($echo, $inside, $minimal) {
        $out = $inside($echo, 'prepare_properties', $minimal);
        return [array_keys($out), $out['meta']];
    }),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['annotations' => ['destructive' => false, 'extra' => 1], 'public' => true, 'zz' => 1]] + $minimal)['meta']),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['public' => true, 'show_in_rest' => false]] + $minimal)['meta']),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['public' => 'yes']] + $minimal)['meta']),
    $caught(static fn () => $inside($echo, 'prepare_properties', ['meta' => ['public' => false]] + $minimal)['meta']),
]);
$say('constructed directly', [
    $caught(static fn () => (new WP_Ability('zz/direct', $minimal))->get_meta()),
    $caught(static fn () => new WP_Ability('zz/direct', ['label' => 'x'])),
    $caught(static fn () => (new WP_Ability('Not A Name', ['category' => 'zz-unregistered'] + $minimal))->get_name()),
    $caught(static fn () => (new WP_Ability_Category('zz-direct', ['label' => 'D', 'description' => 'Direct.']))->get_meta()),
    $caught(static fn () => new WP_Ability_Category('zz-direct', [])),
    $caught(static fn () => new WP_Ability_Category('zz-direct', ['label' => 'D', 'description' => 'Direct.', 'meta' => 'nope'])),
]);
$say('the category object', [
    $category->get_slug(), $category->get_label(), $category->get_description(), $category->get_meta(),
    (static function () use ($category, $inside) {
        try {
            return $inside($category, 'prepare_properties', ['label' => 'Only label']);
        } catch (Throwable $e) {
            return [get_class($e), $e->getMessage()];
        }
    })(),
]);
$say('not serialized', array_map(static function ($object) {
    try {
        serialize($object);
        return 'serialized';
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage()];
    }
}, [$echo, $category, $abilities, $categories]));
$say('not unserialized', array_map(static function ($class) {
    try {
        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
        return 'unserialized';
    } catch (Throwable $e) {
        return [get_class($e), $e->getMessage()];
    }
}, ['WP_Ability', 'WP_Ability_Category', 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry']));
$say('unregistered', [
    $shape($abilities->unregister('zz/echo')), $abilities->is_registered('zz/echo'), $shape($abilities->unregister('zz/dflt')),
    $heard(static fn () => $abilities->unregister('zz/echo')),
    $shape($categories->unregister('zz-cat')), $heard(static fn () => $categories->unregister('zz-cat')),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
