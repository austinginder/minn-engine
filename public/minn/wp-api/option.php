<?php
/**
 * Options, transients, and the object cache as plugin code reads them.
 * Storage shapes and return values follow contracts/fixtures/api/functions.json.
 */

use Minn\Runtime\OptionSanitizer;
use Minn\Runtime\Options;
use Minn\Runtime\OptionWrites;
use Minn\Runtime\RegisteredSettings;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteOptions;
use Minn\Runtime\StoredObjects;
use Minn\Runtime\Transients;
use Minn\Support\Serialized;

function get_option($option, $default_value = false)
{
    return func_num_args() > 1 ? Runtime::options()->filtered((string) $option, $default_value) : Runtime::options()->filtered((string) $option);
}

function get_site_option($option, $default_value = false, $deprecated = true)
{
    return get_network_option(null, $option, $default_value);
}

// The network's options are Minn\Runtime\SiteOptions (probe site-options).

function get_network_option($network_id, $option, $default_value = false)
{
    return SiteOptions::get($option, $default_value, $network_id);
}

function add_network_option($network_id, $option, $value)
{
    return SiteOptions::add($option, $value, $network_id);
}

/** An unchanged value is no update; an option not there yet is added. */
function update_network_option($network_id, $option, $value)
{
    return SiteOptions::update($option, $value, $network_id);
}

function delete_network_option($network_id, $option)
{
    return SiteOptions::delete($option, $network_id);
}

function add_site_option($option, $value)
{
    return add_network_option(null, $option, $value);
}

function update_site_option($option, $value)
{
    return update_network_option(null, $option, $value);
}

function delete_site_option($option)
{
    return delete_network_option(null, $option);
}

function add_option($option, $value = '', $deprecated = '', $autoload = null)
{
    return OptionWrites::add($option, $value, $autoload);
}


function update_option($option, $value, $autoload = null)
{
    return OptionWrites::update($option, $value, $autoload);
}


function delete_option($option)
{
    return OptionWrites::delete($option);
}


function wp_load_alloptions($force_cache = false)
{
    return Runtime::options()->autoloaded((bool) $force_cache);
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
    if (wp_suspend_cache_addition()) {
        return false;
    }
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
    Minn\Content\RowCache::flush();
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

// Salted entries (probe canonical-helpers): the data is stored with the salt it is good for (a last_changed stamp,
// or several joined by colons), and read back only under that salt.

/** @internal a salt as stored: a list of salts joined by colons */
function _minn_cache_salt($salt): string
{
    return is_array($salt) ? implode(':', $salt) : (string) $salt;
}

/** @internal a salted entry's data when it was stored under this salt; false otherwise, and for null data */
function _minn_cache_salted_data($cache, $salt)
{
    return is_array($cache) && isset($cache['data'], $cache['salt']) && $cache['salt'] === _minn_cache_salt($salt) ? $cache['data'] : false;
}

if (!function_exists('wp_cache_get_salted')) :
function wp_cache_get_salted($cache_key, $group, $salt)
{
    return _minn_cache_salted_data(wp_cache_get($cache_key, $group), $salt);
}
endif;

if (!function_exists('wp_cache_set_salted')) :
function wp_cache_set_salted($cache_key, $data, $group, $salt, $expire = 0)
{
    return wp_cache_set($cache_key, ['data' => $data, 'salt' => _minn_cache_salt($salt)], $group, $expire);
}
endif;

if (!function_exists('wp_cache_get_multiple_salted')) :
function wp_cache_get_multiple_salted($cache_keys, $group, $salt)
{
    return array_map(static fn ($cache) => _minn_cache_salted_data($cache, $salt), wp_cache_get_multiple($cache_keys, $group));
}
endif;

if (!function_exists('wp_cache_set_multiple_salted')) :
function wp_cache_set_multiple_salted($data, $group, $salt, $expire = 0)
{
    $salt = _minn_cache_salt($salt);
    return wp_cache_set_multiple(array_map(static fn ($value) => ['data' => $value, 'salt' => $salt], (array) $data), $group, $expire);
}
endif;

if (!function_exists('wp_cache_incr')) :
/** A stored counter moved up; anything not numeric counts as 0 (probe cache-remaining). */
function wp_cache_incr($key, $offset = 1, $group = '')
{
    return $GLOBALS['wp_object_cache']->incr($key, $offset, $group);
}
endif;

if (!function_exists('wp_cache_decr')) :
function wp_cache_decr($key, $offset = 1, $group = '')
{
    return $GLOBALS['wp_object_cache']->decr($key, $offset, $group);
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

if (!function_exists('wp_cache_reset')) :
/** @deprecated 3.5.0 Empties the cache's per-site groups (global groups are kept); use wp_cache_switch_to_blog(). */
function wp_cache_reset()
{
    _deprecated_function(__FUNCTION__, '3.5.0', 'wp_cache_switch_to_blog()');
    global $wp_object_cache;
    $wp_object_cache->reset();
}
endif;

if (!function_exists('wp_cache_switch_to_blog')) :
function wp_cache_switch_to_blog($blog_id)
{
}
endif;

if (!function_exists('wp_cache_init')) :
/**
 * The object cache, started. Started again, it starts empty: nothing cached
 * before survives (probe cache-remaining). The store is the runtime's, so a
 * restart empties it; the first start, at boot, leaves it as it is.
 */
function wp_cache_init()
{
    $restarted = isset($GLOBALS['wp_object_cache']);
    $GLOBALS['wp_object_cache'] = new WP_Object_Cache();
    if ($restarted) {
        $GLOBALS['wp_object_cache']->flush();
    }
}
endif;

if (!function_exists('wp_cache_close')) :
function wp_cache_close()
{
    return true;
}
endif;

// The engine asks the object cache through Minn\Runtime\Handoff, so a drop-in's functions answer it.
Minn\Runtime\Handoff::bind([
    'cacheGet' => static fn ($key, $group = '', $force = false, &$found = null) => wp_cache_get($key, $group, $force, $found),
    'cacheSet' => static fn (...$args) => wp_cache_set(...$args),
    'cacheDelete' => static fn (...$args) => wp_cache_delete(...$args),
]);

function get_transient($transient)
{
    return Transients::get($transient);
}

function set_transient($transient, $value, $expiration = 0)
{
    return Transients::set($transient, $value, $expiration);
}

function delete_transient($transient)
{
    return Transients::delete($transient);
}

/** A site transient (probe site-options): the update transients are read with no timeout; an expired one is deleted and reads false. */
function get_site_transient($transient)
{
    return Transients::getSite($transient);
}

/** Written as site options, the timeout first; told as set_site_transient_<name> and set_site_transient. */
function set_site_transient($transient, $value, $expiration = 0)
{
    return Transients::setSite($transient, $value, $expiration);
}

function delete_site_transient($transient)
{
    return Transients::deleteSite($transient);
}

function is_serialized($data, $strict = true)
{
    if (!is_string($data)) {
        return false;
    }
    return Serialized::looksSerialized($data);
}

function is_serialized_string($data)
{
    return is_string($data) && preg_match('/^s:\d+:".*";$/s', trim($data)) === 1;
}

function maybe_serialize($data)
{
    return Serialized::maybeEncode($data);
}

function maybe_unserialize($data)
{
    return StoredObjects::maybeDecode($data);
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
    return $passed_default ? $default_value : RegisteredSettings::defaultOf((string) $option, $default_value);
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
    Runtime::options()->prime(array_values(array_map('strval', (array) $options)));
    return null;
}

/** Per option, whether its autoload flag changed; a missing option reports false. */
function wp_set_option_autoload($option, $autoload)
{
    return (bool) (wp_set_option_autoload_values([$option => $autoload])[$option] ?? false);
}

function wp_set_option_autoload_values($options)
{
    return OptionWrites::setAutoload($options);
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

/** Stops with wp_die when asked to touch alloptions or notoptions, the names the option cache keeps for itself. */
function wp_protect_special_option($option)
{
    OptionWrites::protect($option);
}

/** Options keep no cache here to prime. */
function wp_prime_option_caches_by_group($option_group)
{
}

/** Several options at once, name => value, after one priming read. */
function get_options($options)
{
    wp_prime_option_caches($options);
    $values = [];
    foreach ((array) $options as $option) {
        $values[$option] = get_option($option);
    }
    return $values;
}

/** One autoload flag for several options; per option, whether it changed. */
function wp_set_options_autoload($options, $autoload)
{
    return wp_set_option_autoload_values(array_fill_keys((array) $options, $autoload));
}

/** The autoload flag an option is stored with (probe option-more). */
function wp_determine_option_autoload_value($option, $value, $serialized_value, $autoload)
{
    return OptionWrites::autoloadFlag($option, $value, $serialized_value, $autoload);
}

/** An option whose stored value is longer than wp_max_autoloaded_option_size (150000 bytes) is not autoloaded (on wp_default_autoload_value). */
function wp_filter_default_autoload_value_via_option_size($autoload, $option, $value, $serialized_value)
{
    $max = (int) apply_filters('wp_max_autoloaded_option_size', 150000);
    return strlen((string) $serialized_value) > $max ? false : $autoload;
}

/** Primes the site options' cache; on a single site they are options. */
function wp_prime_site_option_caches($options)
{
    wp_prime_network_option_caches(null, $options);
}

/** Primes a network's options; on a single site, whatever the network, they are options. */
function wp_prime_network_option_caches($network_id, $options)
{
    wp_prime_option_caches($options);
}

/** A multisite network's core options, loaded together; a single site has none. */
function wp_load_core_site_options($network_id = null)
{
}

/**
 * Saves a user's settings (user-settings, with user-settings-time): each
 * name and value kept to letters, digits, "_" and "-", a pair with no name
 * left out. False with nobody signed in.
 */
function wp_set_all_user_settings($user_settings)
{
    global $_updated_user_settings;
    $user = get_current_user_id();
    if (!$user) {
        return false;
    }
    $pairs = [];
    foreach ((array) $user_settings as $name => $value) {
        $name = (string) preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $name);
        if ($name !== '') {
            $pairs[] = $name . '=' . preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $value);
        }
    }
    $settings = implode('&', $pairs);
    parse_str($settings, $_updated_user_settings);
    update_user_option($user, 'user-settings', $settings, false);
    update_user_option($user, 'user-settings-time', time(), false);
    return true;
}

/** Sets one of the current user's settings. */
function set_user_setting($name, $value)
{
    if (headers_sent()) {
        return false;
    }
    $all = get_all_user_settings();
    $all[$name] = $value;
    return wp_set_all_user_settings($all);
}

/** Removes one or more of the current user's settings; false when none of them was set. */
function delete_user_setting($names)
{
    if (headers_sent()) {
        return false;
    }
    $all = get_all_user_settings();
    $deleted = false;
    foreach ((array) $names as $name) {
        if (isset($all[$name])) {
            unset($all[$name]);
            $deleted = true;
        }
    }
    return $deleted ? wp_set_all_user_settings($all) : false;
}

/** Empties the current user's settings and expires their settings cookie. */
function delete_all_user_settings()
{
    $user = get_current_user_id();
    if (!$user) {
        return;
    }
    update_user_option($user, 'user-settings', '', false);
    setcookie('wp-settings-' . $user, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH);
}

/** An option printed for a form field's value, escaped for an attribute (probe placeholder-helpers). */
function form_option($option)
{
    echo esc_attr(get_option($option));
}
