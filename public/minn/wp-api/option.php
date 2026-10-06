<?php
/**
 * Options, transients, and the object cache as plugin code reads them.
 * Storage shapes and return values follow contracts/fixtures/api/functions.json.
 */

use Minn\Runtime\OptionSanitizer;
use Minn\Runtime\Options;
use Minn\Runtime\RegisteredSettings;
use Minn\Runtime\Runtime;
use Minn\Runtime\StoredObjects;
use Minn\Support\Serialized;

function get_option($option, $default_value = false)
{
    $option = trim((string) $option);
    if ($option === '') {
        return false;
    }
    $pre = apply_filters("pre_option_{$option}", false, $option, $default_value);
    $pre = apply_filters('pre_option', $pre, $option, $default_value);
    if ($pre !== false) {
        return $pre;
    }
    $value = Runtime::options()->get($option);
    if ($value === null) {
        return apply_filters("default_option_{$option}", $default_value, $option, func_num_args() > 1);
    }
    return apply_filters("option_{$option}", $value, $option);
}

function get_site_option($option, $default_value = false, $deprecated = true)
{
    return get_option($option, $default_value);
}

function get_network_option($network_id, $option, $default_value = false)
{
    return get_option($option, $default_value);
}

// On a single site the network options read and write the options table
// (probed 2026-08-30: the value round-trips through get_option, an
// unchanged update returns false, delete removes the regular option).
function add_network_option($network_id, $option, $value)
{
    return add_option($option, $value);
}

function update_network_option($network_id, $option, $value)
{
    return update_option($option, $value);
}

function delete_network_option($network_id, $option)
{
    return delete_option($option);
}

function add_option($option, $value = '', $deprecated = '', $autoload = null)
{
    $option = trim((string) $option);
    if ($option === '' || Options::guarded($option)) {
        return false;
    }
    $value = sanitize_option($option, $value);
    // An option is new while it reads as its default; a read that already
    // found it unset needs no second look.
    if (!Runtime::options()->knownMissing($option) && apply_filters("default_option_{$option}", false, $option, false) !== get_option($option)) {
        return false;
    }
    $flag = match (true) {
        $autoload === null, $autoload === 'auto' => 'auto',
        $autoload === 'yes', $autoload === 'on', $autoload === true => 'on',
        default => 'off',
    };
    do_action('add_option', $option, $value);
    if (!Runtime::options()->add($option, $value, $flag)) {
        return false;
    }
    do_action("add_option_{$option}", $option, $value);
    do_action('added_option', $option, $value);
    return true;
}

function add_site_option($option, $value)
{
    return add_option($option, $value);
}

function update_option($option, $value, $autoload = null)
{
    $option = trim((string) $option);
    if ($option === '' || Options::guarded($option)) {
        return false;
    }
    $value = sanitize_option($option, $value);
    $old = get_option($option);
    $value = apply_filters("pre_update_option_{$option}", $value, $old, $option);
    $value = apply_filters('pre_update_option', $value, $option, $old);
    // An unchanged value is no update: nothing is written and nobody is told.
    if ($value === $old || maybe_serialize($value) === maybe_serialize($old)) {
        return false;
    }
    if (apply_filters("default_option_{$option}", false, $option, false) === $old) {
        return add_option($option, $value, '', $autoload);
    }
    do_action('update_option', $option, $old, $value);
    if (!Runtime::options()->update($option, $value)) {
        return false;
    }
    do_action("update_option_{$option}", $old, $value, $option);
    do_action('updated_option', $option, $old, $value);
    return true;
}

function update_site_option($option, $value)
{
    // A single site keeps its site options as options, and tells plugins as the network layer does.
    $old = get_option($option);
    if (!update_option($option, $value)) {
        return false;
    }
    do_action("update_site_option_{$option}", $option, $value, $old, 1);
    do_action('update_site_option', $option, $value, $old, 1);
    return true;
}

function delete_option($option)
{
    $option = trim((string) $option);
    if ($option === '' || Options::guarded($option) || !Runtime::options()->exists($option)) {
        return false;
    }
    do_action('delete_option', $option);
    if (!Runtime::options()->delete($option)) {
        return false;
    }
    do_action("delete_option_{$option}", $option);
    do_action('deleted_option', $option);
    return true;
}

function delete_site_option($option)
{
    return delete_option($option);
}

function wp_load_alloptions($force_cache = false)
{
    return Runtime::options()->autoloaded();
}

// The object cache. An object-cache.php drop-in defines its own functions before
// these load; each here is defined only where the drop-in left a gap, and the
// newer ones then work through the drop-in's single-key functions.
if (!function_exists('wp_cache_get')) :
function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    return Runtime::cache()->get((string) $key, $group === '' ? 'default' : (string) $group, $found);
}
endif;

if (!function_exists('wp_cache_set')) :
function wp_cache_set($key, $data, $group = '', $expire = 0)
{
    return Runtime::cache()->set((string) $key, $data, $group === '' ? 'default' : (string) $group);
}
endif;

if (!function_exists('wp_cache_add')) :
function wp_cache_add($key, $data, $group = '', $expire = 0)
{
    return Runtime::cache()->add((string) $key, $data, $group === '' ? 'default' : (string) $group);
}
endif;

if (!function_exists('wp_cache_replace')) :
function wp_cache_replace($key, $data, $group = '', $expire = 0)
{
    $cache = Runtime::cache();
    $cache->get((string) $key, $group === '' ? 'default' : (string) $group, $found);
    return $found ? $cache->set((string) $key, $data, $group === '' ? 'default' : (string) $group) : false;
}
endif;

if (!function_exists('wp_cache_delete')) :
function wp_cache_delete($key, $group = '')
{
    return Runtime::cache()->delete((string) $key, $group === '' ? 'default' : (string) $group);
}
endif;

if (!function_exists('wp_cache_flush')) :
function wp_cache_flush()
{
    return Runtime::cache()->flush();
}
endif;

if (!function_exists('wp_cache_flush_runtime')) :
function wp_cache_flush_runtime()
{
    // An external cache that brought no flush_runtime of its own cannot be asked to.
    return wp_using_ext_object_cache() ? false : Runtime::cache()->flush();
}
endif;

if (!function_exists('wp_cache_flush_group')) :
function wp_cache_flush_group($group)
{
    return wp_using_ext_object_cache() ? false : Runtime::cache()->flushGroup((string) $group);
}
endif;

if (!function_exists('wp_cache_supports')) :
function wp_cache_supports($feature)
{
    return !wp_using_ext_object_cache() && in_array($feature, ['flush_runtime', 'flush_group'], true);
}
endif;

if (!function_exists('wp_cache_get_multiple')) :
function wp_cache_get_multiple($keys, $group = '', $force = false)
{
    $out = [];
    foreach ((array) $keys as $key) {
        $out[$key] = wp_cache_get($key, $group);
    }
    return $out;
}
endif;

if (!function_exists('wp_cache_add_multiple')) :
function wp_cache_add_multiple(array $data, $group = '', $expire = 0)
{
    $out = [];
    foreach ($data as $key => $value) {
        $out[$key] = wp_cache_add($key, $value, $group, $expire);
    }
    return $out;
}
endif;

if (!function_exists('wp_cache_set_multiple')) :
function wp_cache_set_multiple(array $data, $group = '', $expire = 0)
{
    $out = [];
    foreach ($data as $key => $value) {
        $out[$key] = wp_cache_set($key, $value, $group);
    }
    return $out;
}
endif;

if (!function_exists('wp_cache_delete_multiple')) :
function wp_cache_delete_multiple(array $keys, $group = '')
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = wp_cache_delete($key, $group);
    }
    return $out;
}
endif;

if (!function_exists('wp_cache_incr')) :
function wp_cache_incr($key, $offset = 1, $group = '')
{
    $value = wp_cache_get($key, $group);
    if (!is_numeric($value)) {
        return false;
    }
    $value = max(0, (int) $value + (int) $offset);
    wp_cache_set($key, $value, $group);
    return $value;
}
endif;

if (!function_exists('wp_cache_decr')) :
function wp_cache_decr($key, $offset = 1, $group = '')
{
    return wp_cache_incr($key, -(int) $offset, $group);
}
endif;

if (!function_exists('wp_cache_add_global_groups')) :
function wp_cache_add_global_groups($groups)
{
    if (isset($GLOBALS['wp_object_cache']) && is_object($GLOBALS['wp_object_cache']) && method_exists($GLOBALS['wp_object_cache'], 'add_global_groups')) {
        $GLOBALS['wp_object_cache']->add_global_groups($groups);
    }
}
endif;

if (!function_exists('wp_cache_add_non_persistent_groups')) :
function wp_cache_add_non_persistent_groups($groups)
{
}
endif;

if (!function_exists('wp_cache_switch_to_blog')) :
function wp_cache_switch_to_blog($blog_id)
{
}
endif;

if (!function_exists('wp_cache_init')) :
function wp_cache_init()
{
    $GLOBALS['wp_object_cache'] = new WP_Object_Cache();
}
endif;

if (!function_exists('wp_cache_close')) :
function wp_cache_close()
{
    return true;
}
endif;

function get_transient($transient)
{
    $transient = (string) $transient;
    $pre = apply_filters("pre_transient_{$transient}", false, $transient);
    if ($pre !== false) {
        return $pre;
    }
    $timeout = get_option("_transient_timeout_{$transient}");
    if ($timeout !== false && (int) $timeout < time()) {
        delete_option("_transient_{$transient}");
        delete_option("_transient_timeout_{$transient}");
        return false;
    }
    $value = get_option("_transient_{$transient}");
    return apply_filters("transient_{$transient}", $value, $transient);
}

function set_transient($transient, $value, $expiration = 0)
{
    $transient = (string) $transient;
    $expiration = (int) $expiration;
    $value = apply_filters("pre_set_transient_{$transient}", $value, $expiration, $transient);
    $expiration = (int) apply_filters("expiration_of_transient_{$transient}", $expiration, $value, $transient);
    $name = "_transient_{$transient}";
    $timeoutName = "_transient_timeout_{$transient}";
    if (get_option($name) === false) {
        $autoload = 'on';
        if ($expiration !== 0) {
            $autoload = 'off';
            add_option($timeoutName, time() + $expiration, '', 'off');
        }
        $result = add_option($name, $value, '', $autoload);
    } else {
        $update = true;
        if ($expiration !== 0) {
            if (get_option($timeoutName) === false) {
                delete_option($name);
                add_option($timeoutName, time() + $expiration, '', 'off');
                $result = add_option($name, $value, '', 'off');
                $update = false;
            } else {
                update_option($timeoutName, time() + $expiration);
            }
        }
        if ($update) {
            $result = update_option($name, $value);
        }
    }
    if ($result) {
        do_action("set_transient_{$transient}", $value, $expiration, $transient);
        do_action('setted_transient', $transient, $value, $expiration);
    }
    return $result;
}

function delete_transient($transient)
{
    $transient = (string) $transient;
    do_action("delete_transient_{$transient}", $transient);
    $result = delete_option("_transient_{$transient}");
    if ($result) {
        delete_option("_transient_timeout_{$transient}");
        do_action('deleted_transient', $transient);
    }
    return $result;
}

function get_site_transient($transient)
{
    $transient = (string) $transient;
    $pre = apply_filters("pre_site_transient_{$transient}", false, $transient);
    if ($pre !== false) {
        return $pre;
    }
    $timeout = get_option("_site_transient_timeout_{$transient}");
    if ($timeout !== false && (int) $timeout < time()) {
        delete_option("_site_transient_{$transient}");
        delete_option("_site_transient_timeout_{$transient}");
        return false;
    }
    return apply_filters("site_transient_{$transient}", get_option("_site_transient_{$transient}"), $transient);
}

function set_site_transient($transient, $value, $expiration = 0)
{
    $transient = (string) $transient;
    $expiration = (int) $expiration;
    $value = apply_filters("pre_set_site_transient_{$transient}", $value, $transient);
    $name = "_site_transient_{$transient}";
    if (get_option($name) === false) {
        if ($expiration !== 0) {
            add_option("_site_transient_timeout_{$transient}", time() + $expiration, '', 'off');
        }
        $result = add_option($name, $value, '', 'off');
    } else {
        if ($expiration !== 0) {
            update_option("_site_transient_timeout_{$transient}", time() + $expiration);
        }
        $result = update_option($name, $value);
    }
    return $result;
}

function delete_site_transient($transient)
{
    $transient = (string) $transient;
    $result = delete_option("_site_transient_{$transient}");
    if ($result) {
        delete_option("_site_transient_timeout_{$transient}");
    }
    return $result;
}

function is_serialized($data, $strict = true)
{
    if (!is_string($data)) {
        return false;
    }
    $data = trim($data);
    if ($data === 'N;') {
        return true;
    }
    return preg_match('/^(?:[bid]:.+;|s:\d+:".*";|[aO]:\d+:.+)$/s', $data) === 1;
}

function is_serialized_string($data)
{
    return is_string($data) && preg_match('/^s:\d+:".*";$/s', trim($data)) === 1;
}

function maybe_serialize($data)
{
    if (is_array($data) || is_object($data)) {
        return Serialized::encode($data);
    }
    if (is_serialized($data, false)) {
        return Serialized::encode($data);
    }
    return $data;
}

function maybe_unserialize($data)
{
    if (!is_serialized($data)) {
        return $data;
    }
    $decoded = Serialized::decode(trim((string) $data), StoredObjects::reviver());
    return $decoded === Serialized::INVALID ? $data : $decoded;
}

/** Registers a setting (Runtime\RegisteredSettings): its arguments kept, its sanitize callback and default hooked. */
function register_setting($option_group, $option_name, $args = [])
{
    RegisteredSettings::register((string) $option_group, (string) $option_name, $args);
}

function unregister_setting($option_group, $option_name, $deprecated = '')
{
    RegisteredSettings::unregister((string) $option_group, (string) $option_name, $deprecated);
}

function get_registered_settings()
{
    return RegisteredSettings::all();
}

/** A registered setting's default, for get_option when the reader named none. */
function filter_default_option($default_value, $option, $passed_default)
{
    return RegisteredSettings::defaultOf($default_value, (string) $option, (bool) $passed_default);
}

/** Core's settings, registered as the REST server starts. */
function register_initial_settings()
{
    RegisteredSettings::registerCore();
}

/** A value cleaned by its option's own rule (Runtime\OptionSanitizer), then sanitize_option_{$option}. */
function sanitize_option($option, $value)
{
    return OptionSanitizer::clean((string) $option, $value);
}

/** Reads the options once so later gets are cache hits; the engine caches per name already. */
function wp_prime_option_caches($options)
{
    foreach ((array) $options as $name) {
        Runtime::options()->get((string) $name);
    }
    return null;
}

/** Per option, whether its autoload flag changed; a missing option reports false. */
function wp_set_option_autoload_values($options)
{
    $results = [];
    foreach ((array) $options as $name => $autoload) {
        $on = in_array($autoload, [true, 'yes', 'on', 'auto-on'], true) || $autoload === 1;
        $results[$name] = $on ? Runtime::options()->markAutoload((string) $name) : Runtime::options()->unmarkAutoload((string) $name);
    }
    return $results;
}

function delete_expired_transients($force_db = false)
{
    $now = time();
    foreach (Runtime::current()->options()->expiredTransientNames($now) as $name) {
        delete_option('_transient_timeout_' . $name);
        delete_option('_transient_' . $name);
    }
    foreach (Runtime::current()->options()->expiredTransientNames($now, '_site_transient_timeout_') as $name) {
        delete_option('_site_transient_timeout_' . $name);
        delete_option('_site_transient_' . $name);
    }
}

function wp_autoload_values_to_autoload()
{
    // A filter may narrow the list, never widen it: what it returns is kept only where core knows the value, keys and all.
    return array_intersect(apply_filters('wp_autoload_values_to_autoload', Minn\Runtime\Options::AUTOLOAD_VALUES), Minn\Runtime\Options::AUTOLOAD_VALUES);
}
