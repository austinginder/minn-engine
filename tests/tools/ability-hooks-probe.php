<?php
/**
 * What the abilities API asks plugins (probe ability-hooks): each
 * registration's arguments (wp_register_ability_category_args,
 * wp_register_ability_args), and a run's steps, each with what it is
 * handed: the input made normal and judged, the permission's answer, a
 * plugin's chance to answer first, the result and its check, and that the
 * ability was run; the REST list's collection parameters too. Everything
 * it registers lives in memory and goes with the process.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$describe = static function ($value) use (&$describe) {
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code();
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if ($value instanceof Closure || (is_array($value) && is_callable($value))) {
        return 'callable';
    }
    if (is_array($value)) {
        return array_map(static fn ($v) => is_callable($v) && !is_string($v) ? 'callable' : $describe($v), $value);
    }
    return $value;
};
$heard = [];
$hooks = ['wp_register_ability_category_args', 'wp_register_ability_args', 'wp_ability_normalize_input', 'wp_ability_validate_input', 'wp_ability_permission_result', 'wp_pre_execute_ability', 'wp_ability_execute_result', 'wp_ability_validate_output', 'wp_ability_invoked', 'wp_before_execute_ability', 'wp_after_execute_ability', 'rest_abilities_collection_params'];
foreach ($hooks as $hook) {
    add_filter($hook, static function (...$args) use ($hook, &$heard, $describe) {
        $heard[] = [$hook, array_map($describe, $args)];
        return $args[0] ?? null;
    }, 10, 9);
}
$run = static function (string $label, callable $call) use (&$heard, $say, $describe): void {
    $heard = [];
    $result = $call();
    $say($label, ['heard' => $heard, 'result' => $describe($result)]);
};
add_filter('doing_it_wrong_trigger_error', '__return_false');

$runs = 0;
add_action('wp_abilities_api_categories_init', static function (): void {
    wp_register_ability_category('zz-hooks', ['label' => 'Zz Hooks', 'description' => 'Probe hooks.']);
});
add_action('wp_abilities_api_init', static function () use (&$runs): void {
    $schema = ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']], 'required' => ['n']];
    wp_register_ability('zz-hooks/double', [
        'label' => 'Zz Double',
        'description' => 'Doubles n.',
        'category' => 'zz-hooks',
        'input_schema' => $schema,
        'output_schema' => ['type' => 'integer'],
        'execute_callback' => static function ($input) use (&$runs) {
            $runs++;
            return 2 * (int) $input['n'];
        },
        'permission_callback' => static fn ($input) => (int) ($input['n'] ?? 0) !== 13,
    ]);
    wp_register_ability('zz-hooks/plain', [
        'label' => 'Zz Plain',
        'description' => 'Takes nothing.',
        'category' => 'zz-hooks',
        'execute_callback' => static fn () => 'plain',
        'permission_callback' => '__return_true',
    ]);
});

$run('the registrations', static fn () => array_map(static fn ($a) => $a->get_name(), array_values(array_filter(wp_get_abilities(), static fn ($a) => str_starts_with($a->get_name(), 'zz-hooks/')))));
$double = wp_get_ability('zz-hooks/double');
$plain = wp_get_ability('zz-hooks/plain');
$run('a run', static fn () => $double->execute(['n' => 4]));
$run('a run with no input', static fn () => $plain->execute());
$run('a run refused by its permission', static fn () => $double->execute(['n' => 13]));
$run('a run with the wrong input', static fn () => $double->execute(['n' => 'x']));
add_filter('wp_pre_execute_ability', $answer = static fn ($pre, $name) => $name === 'zz-hooks/double' ? 99 : $pre, 20, 2);
$run('a run a plugin answers first', static fn () => [$double->execute(['n' => 4]), $runs]);
remove_filter('wp_pre_execute_ability', $answer, 20);
add_filter('wp_ability_permission_result', $deny = static fn () => false, 20);
$run('a run a plugin refuses', static fn () => $double->execute(['n' => 4]));
remove_filter('wp_ability_permission_result', $deny, 20);
add_filter('wp_ability_execute_result', $wrong = static fn () => 'not a number', 20);
$run('a result a plugin changes past its schema', static fn () => $double->execute(['n' => 4]));
remove_filter('wp_ability_execute_result', $wrong, 20);
$run('check_permissions on its own', static fn () => $double->check_permissions(['n' => 4]));
$run('validate_input on its own', static fn () => $double->validate_input(['n' => 'x']));
$run('normalize_input on its own', static fn () => $double->normalize_input(null));
$run('the REST list\'s parameters', static fn () => array_keys((new WP_REST_Abilities_V1_List_Controller())->get_collection_params()));

echo json_encode($log, JSON_UNESCAPED_SLASHES);
