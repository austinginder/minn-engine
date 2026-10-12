<?php
/**
 * The core REST routes (probe create-initial-rest-routes): a fresh
 * WP_REST_Server given create_initial_rest_routes(), and each core route
 * it then answers listed with its methods. Routes whose handlers belong to
 * a controller the engine leaves out on purpose (Site Health, the editor's
 * block and pattern directories, the font library, the site export, the
 * navigation fallback, template autosaves and revisions) are set aside by
 * their handler's class, and so is a namespace left with only its index
 * (the engine has none of them, so how many is not compared).
 * Only the namespaces the function registers are
 * listed (wp/v2, wp-block-editor/v1, wp-site-health/v1, wp-abilities/v1):
 * the server's own routes, oEmbed's and plugins' are registered elsewhere.
 * Registering outside rest_api_init draws a notice on the reference, which
 * the probe silences. The global server is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
wp_set_current_user(1);
if (!did_action('init')) {
    do_action('init');
}
$left = ['WP_REST_Site_Health_Controller', 'WP_REST_Block_Directory_Controller', 'WP_REST_Pattern_Directory_Controller', 'WP_REST_Font_Collections_Controller', 'WP_REST_Font_Families_Controller', 'WP_REST_Font_Faces_Controller', 'WP_REST_Edit_Site_Export_Controller', 'WP_REST_Navigation_Fallback_Controller', 'WP_REST_Template_Autosaves_Controller', 'WP_REST_Template_Revisions_Controller'];
global $wp_rest_server;
$saved = $wp_rest_server;
$wp_rest_server = new WP_REST_Server();
$returned = create_initial_rest_routes();
$table = [];
foreach ($wp_rest_server->get_routes() as $route => $endpoints) {
    $classes = [];
    $methods = [];
    foreach ($endpoints as $endpoint) {
        $callback = $endpoint['callback'] ?? null;
        if (is_array($callback) && is_object($callback[0] ?? null)) {
            $classes[] = get_class($callback[0]);
        }
        foreach (array_keys((array) ($endpoint['methods'] ?? [])) as $method) {
            $methods[$method] = true;
        }
    }
    if (array_intersect($classes, $left) !== []) {
        continue;
    }
    $namespace = preg_match('#^/([^/]+/v\d+)#', (string) $route, $m) === 1 ? $m[1] : '';
    if (!in_array($namespace, ['wp/v2', 'wp-block-editor/v1', 'wp-site-health/v1', 'wp-abilities/v1'], true)) {
        continue;
    }
    $table[$namespace][$route] = (static function (array $names): array {
        sort($names);
        return $names;
    })(array_keys($methods));
}
$wp_rest_server = $saved;
// A namespace left with nothing but its own index had only left-out routes: it is set aside with them.
foreach ($table as $namespace => $routes) {
    if (array_keys($routes) === ['/' . $namespace]) {
        unset($table[$namespace]);
    }
}
ksort($table);
foreach ($table as &$routes) {
    ksort($routes);
}
unset($routes);
$say('returned', $returned);
$say('the namespaces', array_map(static fn (array $routes) => count($routes), $table));
foreach ($table as $namespace => $routes) {
    $say("the routes under '{$namespace}'", $routes);
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
