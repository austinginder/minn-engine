<?php
/**
 * Drop-in probe: an "external" object cache, the shape Redis and Memcached
 * drop-ins have, keeping values in memory and counting calls. It leaves out
 * the newer functions (the *_multiple family, flush_runtime, flush_group,
 * supports) so the runtime's own fill them in.
 */

define('MINN_PROBE_OBJECT_CACHE', true);

class WP_Object_Cache
{
    public $cache = [];
    public $calls = 0;

    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        $this->calls++;
        $found = isset($this->cache[$group]) && array_key_exists($key, $this->cache[$group]);
        return $found ? $this->cache[$group][$key] : false;
    }

    public function set($key, $data, $group = 'default', $expire = 0)
    {
        $this->calls++;
        $this->cache[$group][$key] = $data;
        return true;
    }

    public function add($key, $data, $group = 'default', $expire = 0)
    {
        if (isset($this->cache[$group]) && array_key_exists($key, $this->cache[$group])) {
            return false;
        }
        return $this->set($key, $data, $group, $expire);
    }

    public function delete($key, $group = 'default')
    {
        $this->calls++;
        $had = isset($this->cache[$group]) && array_key_exists($key, $this->cache[$group]);
        unset($this->cache[$group][$key]);
        return $had;
    }

    public function flush()
    {
        $this->cache = [];
        return true;
    }
}

function wp_cache_init()
{
    $GLOBALS['wp_object_cache'] = new WP_Object_Cache();
}

function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    return $GLOBALS['wp_object_cache']->get($key, $group === '' ? 'default' : $group, $force, $found);
}

function wp_cache_set($key, $data, $group = '', $expire = 0)
{
    return $GLOBALS['wp_object_cache']->set($key, $data, $group === '' ? 'default' : $group, $expire);
}

function wp_cache_add($key, $data, $group = '', $expire = 0)
{
    return $GLOBALS['wp_object_cache']->add($key, $data, $group === '' ? 'default' : $group, $expire);
}

function wp_cache_replace($key, $data, $group = '', $expire = 0)
{
    return wp_cache_get($key, $group) === false ? false : wp_cache_set($key, $data, $group, $expire);
}

function wp_cache_delete($key, $group = '')
{
    return $GLOBALS['wp_object_cache']->delete($key, $group === '' ? 'default' : $group);
}

function wp_cache_flush()
{
    return $GLOBALS['wp_object_cache']->flush();
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
    $value = (int) wp_cache_get($key, $group) + $offset;
    wp_cache_set($key, $value, $group);
    return $value;
}

function wp_cache_decr($key, $offset = 1, $group = '')
{
    return wp_cache_incr($key, -$offset, $group);
}

function wp_cache_add_global_groups($groups)
{
    $GLOBALS['minn_probe_cache_groups']['global'][] = (array) $groups;
}

function wp_cache_add_non_persistent_groups($groups)
{
    $GLOBALS['minn_probe_cache_groups']['non-persistent'][] = (array) $groups;
}

function wp_cache_switch_to_blog($blog_id)
{
}

function wp_cache_close()
{
    return true;
}
