<?php
/**
 * The abilities registry as plugins use it, as the reference answers
 * (probe abilities-registry): the two init actions and their order on
 * first use, a category and an ability registered on their own actions,
 * what each registration returns and keeps, and the refusals (outside its
 * action, a duplicate, a malformed slug or name, missing fields, an
 * unknown category), each with its notice; the lookups and unregistering.
 * Same protocol as api-probe.php; everything it registers goes at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong): void {
    $wrong[] = [$function, preg_replace('/\s*\(This message was added in version [^)]+\.\)/', '', wp_strip_all_tags((string) $message))];
}, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
$take = static function () use (&$wrong): array {
    $out = $wrong;
    $wrong = [];
    return $out;
};
// What a registration handed back, in words both stacks share.
$shape = static function ($value): mixed {
    if (is_object($value)) {
        $out = ['class' => get_class($value)];
        foreach (['get_slug', 'get_name', 'get_label', 'get_description', 'get_category', 'get_meta'] as $method) {
            if (method_exists($value, $method)) {
                $out[$method] = $value->$method();
            }
        }
        return $out;
    }
    return $value;
};

$order = [];
foreach (['wp_abilities_api_categories_init', 'wp_abilities_api_init'] as $hook) {
    add_action($hook, static function () use ($hook, &$order): void {
        $order[] = [$hook, did_action('wp_abilities_api_categories_init'), did_action('wp_abilities_api_init')];
    }, 1);
}

$registered = [];
add_action('wp_abilities_api_categories_init', static function () use (&$registered, $shape): void {
    $registered['category'] = $shape(wp_register_ability_category('zz-tools', ['label' => 'Zz Tools', 'description' => 'Probe tools.']));
    $registered['category with meta'] = $shape(wp_register_ability_category('zz-meta', ['label' => 'Zz Meta', 'description' => 'With meta.', 'meta' => ['zz' => 1]]));
    $registered['duplicate category'] = $shape(wp_register_ability_category('zz-tools', ['label' => 'Again', 'description' => 'Again.']));
    $registered['uppercase slug'] = $shape(wp_register_ability_category('Zz Bad', ['label' => 'Bad', 'description' => 'Bad.']));
    $registered['no label'] = $shape(wp_register_ability_category('zz-nolabel', ['description' => 'No label.']));
    $registered['no description'] = $shape(wp_register_ability_category('zz-nodesc', ['label' => 'No description']));
});
$execute = static fn ($input = null) => ['echo' => $input];
add_action('wp_abilities_api_init', static function () use (&$registered, $shape, $execute): void {
    $base = ['label' => 'Zz Echo', 'description' => 'Echoes its input.', 'category' => 'zz-tools', 'execute_callback' => $execute, 'permission_callback' => '__return_true'];
    $registered['ability'] = $shape(wp_register_ability('zz/echo', $base));
    $registered['duplicate ability'] = $shape(wp_register_ability('zz/echo', $base));
    $registered['no namespace'] = $shape(wp_register_ability('zz-echo', $base));
    $registered['uppercase name'] = $shape(wp_register_ability('Zz/Echo', $base));
    $registered['unknown category'] = $shape(wp_register_ability('zz/lost', ['category' => 'zz-nowhere'] + $base));
    $registered['no category'] = $shape(wp_register_ability('zz/nocat', array_diff_key($base, ['category' => true])));
    $registered['no execute callback'] = $shape(wp_register_ability('zz/noexec', array_diff_key($base, ['execute_callback' => true])));
    $registered['no permission callback'] = $shape(wp_register_ability('zz/noperm', array_diff_key($base, ['permission_callback' => true])));
    $registered['no label'] = $shape(wp_register_ability('zz/nolabel', array_diff_key($base, ['label' => true])));
    // For running: typed input and output, refusals, errors, meta.
    $typed = ['input_schema' => ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']], 'required' => ['n']], 'output_schema' => ['type' => 'integer']];
    wp_register_ability('zz/typed', $typed + ['execute_callback' => static fn ($input) => $input['n'] * 2] + $base);
    wp_register_ability('zz/badout', $typed + ['execute_callback' => static fn ($input) => 'text'] + $base);
    wp_register_ability('zz/denied', ['permission_callback' => '__return_false'] + $base);
    wp_register_ability('zz/errperm', ['permission_callback' => static fn () => new WP_Error('zz_no', 'No.')] + $base);
    wp_register_ability('zz/errexec', ['execute_callback' => static fn () => new WP_Error('zz_fail', 'Failed.')] + $base);
    wp_register_ability('zz/noinput', ['execute_callback' => static fn () => 'ok'] + $base);
    $registered['meta'] = $shape(wp_register_ability('zz/meta', ['meta' => ['annotations' => ['readonly' => true], 'show_in_rest' => true, 'zz' => 1]] + $base));
    // Shown in REST, for running over REST: read-only with typed input, one that refuses, one that writes.
    $rest = ['meta' => ['annotations' => ['readonly' => true], 'show_in_rest' => true]];
    wp_register_ability('zz/rest-typed', $rest + $typed + ['execute_callback' => static fn ($input) => $input['n'] * 2] + $base);
    wp_register_ability('zz/rest-denied', $rest + ['permission_callback' => '__return_false'] + $base);
    wp_register_ability('zz/rest-write', ['meta' => ['show_in_rest' => true]] + ['input_schema' => ['type' => 'object', 'properties' => ['v' => ['type' => 'string']]]] + ['execute_callback' => static fn ($input) => ['wrote' => $input['v'] ?? null]] + $base);
    wp_register_ability('zz/rest-fails', $rest + ['execute_callback' => static fn () => new WP_Error('zz_fail', 'Failed.', ['status' => 409])] + $base);
});

// Outside the actions, before anything asked: the registrations are refused.
$early = [
    'category' => $shape(wp_register_ability_category('zz-early', ['label' => 'Early', 'description' => 'Early.'])),
    'ability' => $shape(wp_register_ability('zz/early', ['label' => 'Early', 'description' => 'Early.', 'category' => 'zz-tools', 'execute_callback' => $execute, 'permission_callback' => '__return_true'])),
];
$say('registered outside the actions', [$early, $take()]);

// First use runs the actions.
$categories = wp_get_ability_categories();
$say('init order on first use', $order);
$say('registrations', [$registered, $take()]);
$say('categories lookup', [
    'keys include' => array_values(array_intersect(array_keys((array) $categories), ['zz-tools', 'zz-meta', 'zz-nolabel', 'zz-nodesc', 'site', 'user'])),
    'list is keyed by slug' => array_keys((array) $categories) === array_values(array_map(static fn ($c) => is_object($c) && method_exists($c, 'get_slug') ? $c->get_slug() : null, (array) $categories)),
    'one' => $shape(wp_get_ability_category('zz-tools')),
    'missing' => $shape(wp_get_ability_category('zz-nowhere')),
    'has' => [wp_has_ability_category('zz-tools'), wp_has_ability_category('zz-nowhere')],
]);
$abilities = wp_get_abilities();
$say('abilities lookup', [
    'names include' => array_values(array_intersect(array_map(static fn ($a) => $a->get_name(), array_values((array) $abilities)), ['zz/echo', 'zz/noperm', 'zz/nolabel', 'zz/nocat', 'zz/noexec', 'zz/lost'])),
    'keyed by name' => array_keys((array) $abilities) === array_map(static fn ($a) => $a->get_name(), array_values((array) $abilities)),
    'one' => $shape(wp_get_ability('zz/echo')),
    'has' => [wp_has_ability('zz/echo'), wp_has_ability('zz/lost')],
    'run' => wp_get_ability('zz/echo') ? wp_get_ability('zz/echo')->execute(['a' => 1]) : null,
    'missing' => $shape(wp_get_ability('zz/nowhere')),
    'init order after both' => $order,
    'core registrations' => [has_action('wp_abilities_api_categories_init', 'wp_register_core_ability_categories'), has_action('wp_abilities_api_init', 'wp_register_core_abilities')],
    'notices' => $take(),
]);
// Running abilities: what each answer is, and the actions around it.
$ran = [];
add_action('wp_before_execute_ability', static function ($name, $input) use (&$ran): void {
    $ran[] = ['before', $name, $input];
}, 10, 2);
add_action('wp_after_execute_ability', static function ($name, $input, $result) use (&$ran): void {
    $ran[] = ['after', $name, $input, $result];
}, 10, 3);
$answer = static fn ($result) => $result instanceof WP_Error ? ['error' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $result->get_error_data()] : $result;
$runs = [];
foreach ([['zz/typed', ['n' => 4]], ['zz/typed', ['n' => 'x']], ['zz/typed', null], ['zz/badout', ['n' => 1]], ['zz/denied', null], ['zz/errperm', null], ['zz/errexec', null], ['zz/noinput', null], ['zz/noinput', 'given']] as [$name, $input]) {
    $ability = wp_get_ability($name);
    $runs[] = [$name, $input, $ability ? $answer($ability->execute($input)) : 'missing', $ability ? $answer($ability->check_permissions($input)) : null];
}
$say('running', [$runs, $ran, $take()]);
$meta = wp_get_ability('zz/meta');
$say('meta and schemas', [$meta ? $meta->get_meta() : null, $meta ? [$meta->get_meta_item('zz'), $meta->get_meta_item('none', 'fallback')] : null, wp_get_ability('zz/typed') ? [wp_get_ability('zz/typed')->get_input_schema(), wp_get_ability('zz/typed')->get_output_schema()] : null, $take()]);
// Over REST: which abilities the catalogue shows, and one hidden from it.
wp_set_current_user(1);
$rest = static function (string $route): array {
    $response = rest_do_request(new WP_REST_Request('GET', $route));
    return [$response->get_status(), (array) rest_get_server()->response_to_data($response, false)];
};
[$listStatus, $list] = $rest('/wp-abilities/v1/abilities');
$say('over REST', [
    'list' => [$listStatus, array_values(array_filter(array_column($list, 'name'), static fn ($n) => str_starts_with((string) $n, 'zz/')))],
    'shown in REST' => (static function ($answer) {
        return [$answer[0], array_intersect_key($answer[1], array_flip(['name', 'category', 'meta', 'input_schema', 'output_schema']))];
    })($rest('/wp-abilities/v1/abilities/zz/meta')),
    'kept from REST' => (static fn ($answer) => [$answer[0], $answer[1]['code'] ?? null])($rest('/wp-abilities/v1/abilities/zz/echo')),
    'categories' => (static fn ($answer) => [$answer[0], array_values(array_filter(array_column($answer[1], 'slug'), static fn ($s) => str_starts_with((string) $s, 'zz-')))])($rest('/wp-abilities/v1/categories')),
]);
$run = static function (string $method, string $name, ?array $query = null, ?array $body = null): array {
    $request = new WP_REST_Request($method, "/wp-abilities/v1/abilities/{$name}/run");
    if ($query !== null) {
        $request->set_query_params($query);
    }
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body((string) json_encode($body));
    }
    $response = rest_do_request($request);
    return [$response->get_status(), rest_get_server()->response_to_data($response, false)];
};
$say('running over REST', [
    'read-only, valid input' => $run('GET', 'zz/rest-typed', ['input' => ['n' => 3]]),
    'read-only, invalid input' => $run('GET', 'zz/rest-typed', ['input' => ['n' => 'x']]),
    'read-only, no input' => $run('GET', 'zz/rest-typed'),
    'read-only over POST' => $run('POST', 'zz/rest-typed', null, ['input' => ['n' => 3]]),
    'refused' => $run('GET', 'zz/rest-denied'),
    'writes over POST' => $run('POST', 'zz/rest-write', null, ['input' => ['v' => 'hi']]),
    'writes over GET' => $run('GET', 'zz/rest-write'),
    'callback error' => $run('GET', 'zz/rest-fails'),
    'hidden from REST' => $run('GET', 'zz/echo'),
    'notices' => $take(),
]);
wp_set_current_user(0);
foreach (['zz/typed', 'zz/badout', 'zz/denied', 'zz/errperm', 'zz/errexec', 'zz/noinput', 'zz/meta', 'zz/rest-typed', 'zz/rest-denied', 'zz/rest-write', 'zz/rest-fails'] as $name) {
    if (wp_has_ability($name)) {
        wp_unregister_ability($name);
    }
}

$say('after init, a late registration', [
    $shape(wp_register_ability_category('zz-late', ['label' => 'Late', 'description' => 'Late.'])),
    $shape(wp_register_ability('zz/late', ['label' => 'Late', 'description' => 'Late.', 'category' => 'zz-tools', 'execute_callback' => $execute, 'permission_callback' => '__return_true'])),
    $take(),
]);
$say('unregister', [
    $shape(wp_unregister_ability('zz/echo')),
    $shape(wp_unregister_ability('zz/echo')),
    $shape(wp_unregister_ability_category('zz-meta')),
    $shape(wp_unregister_ability_category('zz-meta')),
    wp_has_ability('zz/echo'),
    $take(),
]);
foreach (['zz/noperm', 'zz/nolabel', 'zz/nocat', 'zz/noexec'] as $name) {
    if (wp_has_ability($name)) {
        wp_unregister_ability($name);
    }
}
foreach (['zz-tools', 'zz-nolabel', 'zz-nodesc'] as $slug) {
    if (wp_has_ability_category($slug)) {
        wp_unregister_ability_category($slug);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
