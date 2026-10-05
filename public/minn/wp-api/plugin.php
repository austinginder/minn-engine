<?php
/** Plugin file paths and the activation hooks. */

use Minn\Runtime\Runtime;

function plugin_basename($file)
{
    $file = wp_normalize_path((string) $file);
    foreach ((array) Runtime::current()->get('plugin_realpaths', []) as $real => $expected) {
        $real = wp_normalize_path((string) $real);
        if (str_starts_with($file, $real . '/')) {
            $file = wp_normalize_path((string) $expected) . substr($file, strlen($real));
            break;
        }
    }
    foreach ([WP_PLUGIN_DIR, WPMU_PLUGIN_DIR] as $dir) {
        $dir = wp_normalize_path($dir);
        foreach (array_unique([$dir, wp_normalize_path((string) (realpath($dir) ?: $dir))]) as $root) {
            if (str_starts_with($file, $root . '/')) {
                return trim(substr($file, strlen($root)), '/');
            }
        }
    }
    return ltrim($file, '/');
}

function wp_register_plugin_realpath($file)
{
    $dir = dirname(wp_normalize_path((string) $file));
    $real = realpath($dir);
    if ($real === false || wp_normalize_path($real) === $dir) {
        return false;
    }
    $map = Runtime::current()->get('plugin_realpaths', []);
    $map[wp_normalize_path($real)] = $dir;
    Runtime::current()->set('plugin_realpaths', $map);
    return true;
}

function plugin_dir_path($file)
{
    return trailingslashit(dirname((string) $file));
}

function plugin_dir_url($file)
{
    return trailingslashit(plugins_url('', $file));
}

function plugins_url($path = '', $plugin = '')
{
    $path = wp_normalize_path((string) $path);
    $plugin = wp_normalize_path((string) $plugin);
    $mu = wp_normalize_path(WPMU_PLUGIN_DIR);
    $isMu = $plugin !== '' && str_starts_with($plugin, $mu);
    $url = set_url_scheme($isMu ? WPMU_PLUGIN_URL : WP_PLUGIN_URL);
    if ($plugin !== '') {
        $folder = dirname(plugin_basename($plugin));
        if ($folder !== '.') {
            $url .= '/' . ltrim($folder, '/');
        }
    }
    if ($path !== '') {
        $url .= '/' . ltrim($path, '/');
    }
    return apply_filters('plugins_url', $url, $path, $plugin);
}

function register_activation_hook($file, $callback)
{
    add_action('activate_' . plugin_basename($file), $callback);
}

function register_deactivation_hook($file, $callback)
{
    add_action('deactivate_' . plugin_basename($file), $callback);
}

function register_uninstall_hook($file, $callback)
{
    if (is_array($callback) && is_object($callback[0])) {
        return;
    }
    $basename = plugin_basename($file);
    $uninstallable = get_option('uninstall_plugins');
    $uninstallable = is_array($uninstallable) ? $uninstallable : [];
    if (($uninstallable[$basename] ?? null) !== $callback) {
        $uninstallable[$basename] = $callback;
        update_option('uninstall_plugins', $uninstallable);
    }
}

function is_plugin_active($plugin)
{
    return in_array($plugin, (array) get_option('active_plugins', []), true);
}

function is_plugin_inactive($plugin)
{
    return !is_plugin_active($plugin);
}

/** File changes (installs, updates, deletes) are allowed unless wp-config forbids them; the filter has the last word. */
function wp_is_file_mod_allowed($context)
{
    return (bool) apply_filters('file_mod_allowed', !defined('DISALLOW_FILE_MODS') || !DISALLOW_FILE_MODS, $context);
}

function is_plugin_active_for_network($plugin)
{
    return false;
}

function is_network_only_plugin($plugin)
{
    return false;
}

function get_plugin_data($plugin_file, $markup = true, $translate = true)
{
    $headers = ['Name' => 'Plugin Name', 'PluginURI' => 'Plugin URI', 'Version' => 'Version', 'Description' => 'Description', 'Author' => 'Author', 'AuthorURI' => 'Author URI', 'TextDomain' => 'Text Domain', 'DomainPath' => 'Domain Path', 'Network' => 'Network', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP', 'UpdateURI' => 'Update URI', 'RequiresPlugins' => 'Requires Plugins'];
    $data = get_file_data($plugin_file, $headers, 'plugin');
    $data['Title'] = $data['Name'];
    $data['AuthorName'] = $data['Author'];
    $data['Network'] = strtolower($data['Network']) === 'true';
    return $data;
}

function get_file_data($file, $default_headers, $context = '')
{
    $handle = @fopen((string) $file, 'r');
    $head = $handle ? (string) fread($handle, 8 * KB_IN_BYTES) : '';
    if ($handle) {
        fclose($handle);
    }
    $head = str_replace("\r", "\n", $head);
    $headers = $context !== '' ? apply_filters("extra_{$context}_headers", []) : [];
    $headers = array_merge(array_fill_keys($headers, ''), $default_headers);
    $out = [];
    foreach ($headers as $field => $regex) {
        $label = is_string($regex) && $regex !== '' ? $regex : $field;
        if (preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($label, '/') . ':(.*)$/mi', $head, $m) && $m[1]) {
            $out[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $m[1]));
        } else {
            $out[$field] = '';
        }
    }
    return $out;
}

function get_mu_plugins()
{
    $out = [];
    foreach (glob(WPMU_PLUGIN_DIR . '/*.php') ?: [] as $file) {
        $out[basename($file)] = get_plugin_data($file, false, false);
    }
    return $out;
}

function get_plugins($plugin_folder = '')
{
    $out = [];
    $root = WP_PLUGIN_DIR . ($plugin_folder !== '' ? '/' . trim((string) $plugin_folder, '/') : '');
    foreach (array_merge(glob($root . '/*.php') ?: [], glob($root . '/*/*.php') ?: []) as $file) {
        $data = get_plugin_data($file, false, false);
        if ($data['Name'] !== '') {
            $out[plugin_basename($file)] = $data;
        }
    }
    ksort($out);
    return $out;
}

function wp_get_active_and_valid_plugins()
{
    $out = [];
    foreach ((array) get_option('active_plugins', []) as $plugin) {
        $file = WP_PLUGIN_DIR . '/' . $plugin;
        if (is_file($file)) {
            $out[] = $file;
        }
    }
    return $out;
}

/** The id a callback registers under: its name, Class::method, or an object's hash plus method. */
function _wp_filter_build_unique_id($hook_name, $callback, $priority)
{
    if (is_string($callback)) {
        return $callback;
    }
    if (is_object($callback)) {
        return spl_object_hash($callback);
    }
    $callback = (array) $callback;
    return (is_object($callback[0]) ? spl_object_hash($callback[0]) : (string) $callback[0]) . '::' . (string) $callback[1];
}

/** The drop-in files present under wp-content, keyed by filename, with their headers. */
function get_dropins()
{
    $names = ['advanced-cache.php', 'db.php', 'db-error.php', 'install.php', 'maintenance.php', 'object-cache.php', 'php-error.php', 'fatal-error-handler.php', 'sunrise.php', 'blog-deleted.php', 'blog-inactive.php', 'blog-suspended.php'];
    $out = [];
    foreach ($names as $name) {
        $file = WP_CONTENT_DIR . '/' . $name;
        if (is_file($file)) {
            $out[$name] = get_plugin_data($file, false, false);
        }
    }
    return $out;
}

/** @internal $wp_filter, $wp_actions, $wp_filters, $wp_current_filter and $shortcode_tags over the engine's registries */
function _minn_bind_hook_globals(): void
{
    $hooks = Runtime::hooks();
    $GLOBALS['wp_filter'] = [];
    $hooks->onNew(static function (string $name): void {
        $GLOBALS['wp_filter'][$name] = WP_Hook::bound($name);
    });
    $GLOBALS['wp_actions'] = &$hooks->actionCounters();
    $GLOBALS['wp_filters'] = &$hooks->filterCounters();
    $GLOBALS['wp_current_filter'] = &$hooks->stackRef();
    $GLOBALS['shortcode_tags'] = &Runtime::shortcodes()->tags();
    $GLOBALS['wp_roles'] = wp_roles();
    $GLOBALS['wp_embed'] ??= new WP_Embed();
}

function get_plugin_updates()
{
    $offers = (array) (get_site_transient('update_plugins')->response ?? []);
    $out = [];
    foreach (get_plugins() as $file => $header) {
        if (isset($offers[$file])) {
            $out[$file] = (object) ($header + ['update' => $offers[$file]]);
        }
    }
    return $out;
}

function _get_dropins()
{
    return array_map(static fn (array $dropin): array => [__($dropin[0]), $dropin[1]], Minn\Content\Inventory::KNOWN_DROPINS);
}

function add_allowed_options($new_options, $options = '')
{
    if ($options === '') {
        global $allowed_options;
        $allowed_options = Minn\Runtime\AllowedOptions::merge((array) $new_options, (array) ($allowed_options ?? []));
        return $allowed_options;
    }
    return Minn\Runtime\AllowedOptions::merge((array) $new_options, (array) $options);
}

function add_option_whitelist($new_options, $options = '')
{
    _deprecated_function(__FUNCTION__, '5.5.0', 'add_allowed_options()');
    return add_allowed_options($new_options, $options);
}
