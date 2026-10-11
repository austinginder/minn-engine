<?php
/**
 * The rest of the Interactivity API object (probe interactivity-api-methods):
 * the hooks it adds; the data it hands its two script modules; the module
 * attribute that opts a module into client navigation; the router's
 * markup; what a router region does when the server processes one (the
 * loading bar in the footer, its animation styles); and the methods 6.7
 * deprecated, whose notices are silenced and recorded.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('doing_it_wrong_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
$printed = static function (callable $run) {
    ob_start();
    $result = $run();
    return [$result, ob_get_clean()];
};
$interactivity = wp_interactivity();
$say('the object', get_class($interactivity) === WP_Interactivity_API::class);

$hooks = [];
foreach (['script_module_data_@wordpress/interactivity', 'script_module_data_@wordpress/interactivity-router', 'wp_script_attributes', 'wp_footer'] as $hook) {
    $hooks[$hook] = [];
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && $callback['function'][0] instanceof WP_Interactivity_API) {
                $hooks[$hook][] = [$priority, $callback['function'][1], $callback['accepted_args']];
            }
        }
    }
}
$say('its hooks', $hooks);

$say('module data', [
    'nothing stored' => $interactivity->filter_script_module_interactivity_data(['kept' => true]),
    'stored' => (static function () use ($interactivity) {
        $interactivity->state('zz/iam', ['count' => 1, 'list' => ['a']]);
        $interactivity->config('zz/iam', ['flag' => true]);
        $interactivity->config('zz/iam-config-only', ['x' => 1]);
        return $interactivity->filter_script_module_interactivity_data(['kept' => true]);
    })(),
    'the router' => $interactivity->filter_script_module_interactivity_router_data(['kept' => true]),
    'through the filter' => apply_filters('script_module_data_@wordpress/interactivity', []),
    'the router, through the filter' => apply_filters('script_module_data_@wordpress/interactivity-router', []),
]);

$attributes = static fn (array $cases) => array_map(static fn ($case) => $interactivity->add_load_on_client_navigation_attribute_to_script_modules($case), $cases);
$cases = [
    'a module' => ['type' => 'module', 'id' => 'zz-iam-js-module', 'src' => 'x'],
    'its bare id' => ['type' => 'module', 'id' => 'zz-iam'],
    'a classic script' => ['type' => 'text/javascript', 'id' => 'zz-iam-js-module'],
    'no type' => ['id' => 'zz-iam-js-module'],
    'no id' => ['type' => 'module'],
    'already set' => ['type' => 'module', 'id' => 'zz-iam-js-module', 'data-wp-router-options' => 'mine'],
    'another module' => ['type' => 'module', 'id' => 'zz-other-js-module'],
];
$say('client navigation', [
    'before' => $attributes($cases),
    'supported' => $interactivity->add_client_navigation_support_to_script_module('zz-iam'),
    'after' => $attributes($cases),
    'not an array' => $interactivity->add_load_on_client_navigation_attribute_to_script_modules('text'),
    'a script tag' => wp_get_script_tag(['type' => 'module', 'src' => 'https://example.com/m.js', 'id' => 'zz-iam-js-module']),
]);

$routerState = static fn () => [
    'footer' => has_action('wp_footer', [$interactivity, 'print_router_markup']),
    'style registered' => wp_style_is('wp-interactivity-router-animations', 'registered'),
    'style enqueued' => wp_style_is('wp-interactivity-router-animations', 'enqueued'),
    'style source' => wp_styles()->registered['wp-interactivity-router-animations']->src ?? null,
    'inline' => wp_styles()->get_data('wp-interactivity-router-animations', 'after'),
];
$router = ['before' => $routerState()];
$router['outside an interactive region'] = [$interactivity->process_directives('<div data-wp-router-region="zz-outside"><p>x</p></div>'), $routerState()];
$router['inside one'] = [$interactivity->process_directives('<div data-wp-interactive="zz/iam" data-wp-router-region="zz-main"><span data-wp-text="state.count"></span></div>'), $routerState()];
$router['a second one'] = [$interactivity->process_directives('<div data-wp-interactive="zz/iam"><div data-wp-router-region="zz-second"></div></div>'), $routerState()];
$router['the markup'] = $printed(static fn () => $interactivity->print_router_markup());
$say('router', $router);

$say('deprecated', [
    'client data' => $printed(static fn () => $interactivity->print_client_interactivity_data()),
    'script modules' => $printed(static fn () => $interactivity->register_script_modules()),
    'loading markup' => $printed(static fn () => $interactivity->print_router_loading_and_screen_reader_markup()),
    'heard' => $deprecated,
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
