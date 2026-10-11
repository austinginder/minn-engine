<?php
/**
 * The plugins controller class (probe rest-plugins-controller): its base
 * and routes; schema and collection parameters; the plugin parameter's
 * check and sanitizing; the permission checks for a visitor, an editor and
 * an administrator; plugins read, matched, listed and shaped; the status
 * checks and changes; and a plugin activated, deactivated and deleted
 * through the class. Its plugins are its own, written into the plugins
 * folder and removed again (installing from a directory is left out: it
 * would ask the network).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$call = static fn (object $object, string $method, ...$args) => $try(static fn () => Closure::bind(fn (...$given) => $this->{$method}(...$given), $object, get_class($object))(...$args));
$named = static function ($value) use (&$named) {
    if ($value instanceof Closure) {
        return '@closure';
    }
    if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_object($value[0]) && is_string($value[1])) {
        return '@method:' . get_class($value[0]) . '::' . $value[1];
    }
    return is_array($value) ? array_map($named, $value) : $value;
};
$shown = static fn ($response) => is_wp_error($response) ? ['error' => $response->get_error_code(), 'message' => $response->get_error_message(), 'data' => $response->get_error_data()] : ($response instanceof WP_REST_Response ? ['status' => $response->get_status(), 'data' => $response->get_data(), 'links' => $response->get_links()] : $response);
$request = static function (string $method, string $route, array $params = [], array $defaults = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $request->set_default_params($defaults);
    return $request;
};
wp_set_current_user(0);
rest_get_server();

// The plugins, the editor and the plugin options made or changed here are put back on the way out.
$folder = WP_PLUGIN_DIR;
$options = [];
foreach (['active_plugins', 'active_sitewide_plugins', 'recently_activated', 'uninstall_plugins'] as $name) {
    $options[$name] = get_option($name, null);
}
$made = ['users' => []];
$remove = static function () use ($folder): void {
    foreach (['zz-rplc-probe', 'zz-rplc-network', 'zz-rplc-delete'] as $dir) {
        foreach (glob("{$folder}/{$dir}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir("{$folder}/{$dir}")) {
            rmdir("{$folder}/{$dir}");
        }
    }
    if (is_file("{$folder}/zz-rplc-single.php")) {
        unlink("{$folder}/zz-rplc-single.php");
    }
    wp_clean_plugins_cache();
};
register_shutdown_function(static function () use (&$made, $options, $remove): void {
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
    $remove();
});
$header = static fn (array $lines) => "<?php\n/**\n" . implode('', array_map(static fn ($key, $value) => " * {$key}: {$value}\n", array_keys($lines), $lines)) . " */\n";
foreach (['zz-rplc-probe', 'zz-rplc-network', 'zz-rplc-delete'] as $dir) {
    if (!is_dir("{$folder}/{$dir}") && !mkdir("{$folder}/{$dir}")) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
}
file_put_contents("{$folder}/zz-rplc-probe/zz-rplc-probe.php", $header(['Plugin Name' => 'ZZ rplc <em>probe</em>', 'Plugin URI' => 'https://example.com/rplc', 'Description' => 'A plugin <strong>for</strong> the <script>x</script>probe.', 'Version' => '1.2.3', 'Author' => 'ZZ <b>Author</b>', 'Author URI' => 'https://example.com/author', 'Text Domain' => 'zz-rplc-domain', 'Requires at least' => '6.0', 'Requires PHP' => '7.4']));
file_put_contents("{$folder}/zz-rplc-network/zz-rplc-network.php", $header(['Plugin Name' => 'ZZ rplc network', 'Description' => 'A network plugin.', 'Version' => '2.0', 'Network' => 'true']));
file_put_contents("{$folder}/zz-rplc-delete/zz-rplc-delete.php", $header(['Plugin Name' => 'ZZ rplc delete', 'Version' => '0.1']));
file_put_contents("{$folder}/zz-rplc-single.php", $header(['Plugin Name' => 'ZZ rplc single', 'Version' => '3.0', 'Author' => 'Solo']));
wp_clean_plugins_cache();
$editor = wp_insert_user(['user_login' => 'zz_rplc_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-rplc@example.com', 'role' => 'editor']);
if (!is_int($editor) || $editor <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['users'][] = $editor;
$mask = static fn ($value) => json_decode(str_replace([$folder, (string) $editor], ['{plugins}', '{editor}'], (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);

$controller = new WP_REST_Plugins_Controller();
$say('the controller', Closure::bind(fn () => [$this->namespace, $this->rest_base, self::PATTERN], $controller, WP_REST_Plugins_Controller::class)());
$routes = (static function () use ($named): array {
    $server = $GLOBALS['wp_rest_server'];
    $GLOBALS['wp_rest_server'] = new WP_REST_Server();
    (new WP_REST_Plugins_Controller())->register_routes();
    $found = [];
    foreach ($GLOBALS['wp_rest_server']->get_routes('wp/v2') as $route => $endpoints) {
        if (!str_starts_with($route, '/wp/v2/plugins')) {
            continue;
        }
        foreach ($endpoints as $endpoint) {
            $found[$route][] = ['methods' => array_keys($endpoint['methods']), 'callback' => $named($endpoint['callback']), 'permission' => $named($endpoint['permission_callback'] ?? null), 'args' => $named($endpoint['args'])];
        }
    }
    $GLOBALS['wp_rest_server'] = $server;
    return $found;
})();
$say('routes', $routes);
$say('schema', $named($controller->get_item_schema()));
$say('collection parameters', $named($controller->get_collection_params()));
$say('the plugin parameter', [
    'checked' => array_map(static fn ($file) => $controller->validate_plugin_param($file), ['a folder plugin' => 'zz-rplc-probe/zz-rplc-probe', 'a file plugin' => 'zz-rplc-single', 'with php' => 'zz-rplc-probe/zz-rplc-probe.php', 'a dot' => 'zz.rplc', 'too deep' => 'a/b/c', 'empty' => '', 'a number' => 5, 'a path up' => '../x', 'a slash' => 'zz-rplc-probe/', 'spaces' => 'zz rplc']),
    'sanitized' => array_map(static fn ($file) => $controller->sanitize_plugin_param($file), ['a folder plugin' => 'zz-rplc-probe/zz-rplc-probe', 'a file plugin' => 'zz-rplc-single', 'with php' => 'zz-rplc-probe/zz-rplc-probe.php', 'empty' => '', 'odd characters' => 'Zz Rplc!/x?']),
]);

$checks = static function () use ($controller, $shown, $request) {
    // The plugin parameter as dispatch hands it over, sanitized to its file.
    $one = static fn (string $plugin, string $method = 'GET', array $more = []) => $request($method, '/wp/v2/plugins/' . $plugin, ['plugin' => $plugin . '.php'] + $more);
    return [
        'list' => $shown($controller->get_items_permissions_check($request('GET', '/wp/v2/plugins'))),
        'one' => $shown($controller->get_item_permissions_check($one('zz-rplc-probe/zz-rplc-probe'))),
        'a missing one' => $shown($controller->get_item_permissions_check($one('zz-rplc-none/zz-rplc-none'))),
        'install' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/plugins', ['slug' => 'zz-rplc-none']))),
        'install active' => $shown($controller->create_item_permissions_check($request('POST', '/wp/v2/plugins', ['slug' => 'zz-rplc-none', 'status' => 'active']))),
        'update' => $shown($controller->update_item_permissions_check($one('zz-rplc-probe/zz-rplc-probe', 'PUT', ['status' => 'active']))),
        'update a missing one' => $shown($controller->update_item_permissions_check($one('zz-rplc-none/zz-rplc-none', 'PUT', ['status' => 'active']))),
        'update to network active' => $shown($controller->update_item_permissions_check($one('zz-rplc-probe/zz-rplc-probe', 'PUT', ['status' => 'network-active']))),
        'delete' => $shown($controller->delete_item_permissions_check($one('zz-rplc-delete/zz-rplc-delete', 'DELETE'))),
        'delete a missing one' => $shown($controller->delete_item_permissions_check($one('zz-rplc-none/zz-rplc-none', 'DELETE'))),
    ];
};
$visitor = $checks();
wp_set_current_user($editor);
$asEditor = $checks();
wp_set_current_user(1);
$administrator = $checks();
$say('permissions', $mask(['a visitor' => $visitor, 'an editor' => $asEditor, 'an administrator' => $administrator]));

$say('a plugin\'s facts', $mask([
    'data' => [$call($controller, 'get_plugin_data', 'zz-rplc-probe/zz-rplc-probe.php'), $shown($call($controller, 'get_plugin_data', 'zz-rplc-none/zz-rplc-none.php')), $call($controller, 'get_plugin_data', 'zz-rplc-single.php')],
    'status' => [$call($controller, 'get_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php'), $call($controller, 'get_plugin_status', 'minn-admin/minn-admin.php'), $call($controller, 'get_plugin_status', 'zz-rplc-none/zz-rplc-none.php')],
    'installed' => [$call($controller, 'is_plugin_installed', 'zz-rplc-probe'), $call($controller, 'is_plugin_installed', 'zz-rplc-single'), $call($controller, 'is_plugin_installed', 'zz-rplc-none'), $call($controller, 'is_plugin_installed', 'zz-rplc-probe/zz-rplc-probe.php')],
    'read' => [$shown($call($controller, 'check_read_permission', 'zz-rplc-probe/zz-rplc-probe.php')), $shown($call($controller, 'check_read_permission', 'zz-rplc-network/zz-rplc-network.php'))],
    'filesystem' => $call($controller, 'is_filesystem_available'),
]));

$item = static fn (string $file) => get_plugins()[$file] + ['_file' => $file];
$matches = static fn (array $params, string $file) => $call($controller, 'does_plugin_match_request', $request('GET', '/wp/v2/plugins', $params), $item($file));
$say('matching', [
    'nothing asked' => $matches([], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by name' => $matches(['search' => 'rplc probe'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by name, any case' => $matches(['search' => 'RPLC'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by description' => $matches(['search' => 'for the'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by author' => $matches(['search' => 'Solo'], 'zz-rplc-single.php'),
    'by version' => $matches(['search' => '1.2.3'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by file' => $matches(['search' => 'zz-rplc-probe.php'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by text domain' => $matches(['search' => 'zz-rplc-domain'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by folder' => $matches(['search' => 'zz-rplc-probe/'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by plugin address' => $matches(['search' => 'example.com/rplc'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by author address' => $matches(['search' => 'example.com/author'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by required PHP' => $matches(['search' => '7.4'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by tag-free name' => $matches(['search' => 'rplc probe'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by a tag-free run across fields' => $matches(['search' => 'probe A plugin'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by a script\'s text' => $matches(['search' => 'the xprobe'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by markup' => $matches(['search' => 'strong'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'not found' => $matches(['search' => 'nothing like it'], 'zz-rplc-probe/zz-rplc-probe.php'),
    'by status' => $matches(['status' => ['inactive']], 'zz-rplc-probe/zz-rplc-probe.php'),
    'another status' => $matches(['status' => ['active']], 'zz-rplc-probe/zz-rplc-probe.php'),
    'both' => $matches(['search' => 'rplc', 'status' => ['inactive', 'active']], 'zz-rplc-probe/zz-rplc-probe.php'),
]);

// Reading and writing as the administrator (the reference's methods do not check, the engine's routes do).
wp_set_current_user(1);
$read = static fn (string $plugin, array $params = []) => $shown($controller->get_item($request('GET', '/wp/v2/plugins/' . $plugin, ['plugin' => $plugin . '.php'] + $params)));
$defaults = array_filter(array_map(static fn ($arg) => $arg['default'] ?? null, $controller->get_collection_params()), static fn ($value) => $value !== null);
$say('reading', $mask([
    'one' => $read('zz-rplc-probe/zz-rplc-probe'),
    'one, edit' => $read('zz-rplc-probe/zz-rplc-probe', ['context' => 'edit']),
    'one, embedded' => $read('zz-rplc-probe/zz-rplc-probe', ['context' => 'embed']),
    'one, fields' => $read('zz-rplc-probe/zz-rplc-probe', ['_fields' => 'plugin,name,status']),
    'a file plugin' => $read('zz-rplc-single'),
    'a missing one' => $read('zz-rplc-none/zz-rplc-none'),
    'shaped' => $shown($controller->prepare_item_for_response($item('zz-rplc-network/zz-rplc-network.php'), $request('GET', '/wp/v2/plugins'))),
    'links' => $call($controller, 'prepare_links', $item('zz-rplc-probe/zz-rplc-probe.php')),
    'listed by search' => $shown($controller->get_items($request('GET', '/wp/v2/plugins', ['search' => 'rplc'], $defaults))),
    'listed by search and status' => $shown($controller->get_items($request('GET', '/wp/v2/plugins', ['search' => 'rplc', 'status' => ['active']], $defaults))),
]));

$say('status checks', $mask([
    'to active' => $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'active', 'inactive')),
    'to inactive' => $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'active')),
    'to network active' => $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'network-active', 'inactive')),
    'unchanged' => $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'inactive')),
    'a network plugin to active' => $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-network/zz-rplc-network.php', 'active', 'inactive')),
    'as an editor' => (static function () use ($call, $controller, $shown, $editor) {
        wp_set_current_user($editor);
        $result = [$shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'active', 'inactive')), $shown($call($controller, 'plugin_status_permission_check', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'active'))];
        wp_set_current_user(1);
        return $result;
    })(),
]));

$steps = [];
foreach (['activate_plugin', 'activated_plugin', 'deactivate_plugin', 'deactivated_plugin', 'delete_plugin', 'deleted_plugin'] as $hook) {
    add_action($hook, static function () use (&$steps) {
        $steps[] = current_filter();
    });
}
$heard = static function () use (&$steps) {
    $heard = $steps;
    $steps = [];
    return $heard;
};
$life = [];
$life['status unchanged'] = [$shown($call($controller, 'handle_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'inactive')), $heard()];
$life['activated directly'] = [$shown($call($controller, 'handle_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php', 'active', 'inactive')), $heard(), get_option('active_plugins')];
$life['deactivated directly'] = [$shown($call($controller, 'handle_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'active')), $heard(), get_option('active_plugins')];
$life['to network active on one site'] = [$shown($call($controller, 'handle_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php', 'network-active', 'inactive')), $heard(), get_option('active_plugins'), array_keys((array) get_site_option('active_sitewide_plugins', []))];
$life['and back'] = [$shown($call($controller, 'handle_plugin_status', 'zz-rplc-probe/zz-rplc-probe.php', 'inactive', 'active')), $heard()];
$update = static fn (string $plugin, array $params) => $shown($controller->update_item($request('PUT', '/wp/v2/plugins/' . $plugin, ['plugin' => $plugin . '.php'] + $params)));
$life['activated'] = [$update('zz-rplc-probe/zz-rplc-probe', ['status' => 'active']), $heard()];
$life['activated again'] = [$update('zz-rplc-probe/zz-rplc-probe', ['status' => 'active']), $heard()];
$life['nothing asked'] = [$update('zz-rplc-probe/zz-rplc-probe', []), $heard()];
$life['an active one deleted'] = [$shown($controller->delete_item($request('DELETE', '/wp/v2/plugins/zz-rplc-probe/zz-rplc-probe', ['plugin' => 'zz-rplc-probe/zz-rplc-probe.php']))), $heard()];
$life['deactivated'] = [$update('zz-rplc-probe/zz-rplc-probe', ['status' => 'inactive']), $heard()];
$life['an update of a missing one'] = [$update('zz-rplc-none/zz-rplc-none', ['status' => 'active']), $heard()];
$life['deleted'] = [$shown($controller->delete_item($request('DELETE', '/wp/v2/plugins/zz-rplc-delete/zz-rplc-delete', ['plugin' => 'zz-rplc-delete/zz-rplc-delete.php']))), $heard(), is_dir($folder . '/zz-rplc-delete')];
$life['a missing one deleted'] = [$shown($controller->delete_item($request('DELETE', '/wp/v2/plugins/zz-rplc-none/zz-rplc-none', ['plugin' => 'zz-rplc-none/zz-rplc-none.php']))), $heard()];
wp_set_current_user(0);
$say('a plugin through its life', $mask($life));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
