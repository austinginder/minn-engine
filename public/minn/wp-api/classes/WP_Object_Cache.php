<?php
/**
 * The object cache class plugins read ($wp_object_cache->cache_hits and the
 * rest) when no object-cache.php drop-in brought its own: a view over the
 * request's Minn\Runtime\ObjectCache. Declared only when the class does not
 * exist yet, since a drop-in loads first and declares its own.
 */

use Minn\Runtime\Runtime;

if (!class_exists('WP_Object_Cache', false)) {
    #[AllowDynamicProperties]
    class WP_Object_Cache
    {
        public $cache_hits = 0;
        public $cache_misses = 0;
        protected $global_groups = [];
        private $cache = [];
        private $blog_prefix = '';
        private $multisite = false;

        public function __construct()
        {
            $this->multisite = is_multisite();
            $this->blog_prefix = $this->multisite ? get_current_blog_id() . ':' : '';
        }

        public function __get($name)
        {
            return property_exists($this, $name) ? $this->$name : null;
        }

        public function __set($name, $value)
        {
            $this->$name = $value;
        }

        public function __isset($name)
        {
            return isset($this->$name);
        }

        public function __unset($name)
        {
            unset($this->$name);
        }

        protected function is_valid_key($key)
        {
            return is_int($key) || (is_string($key) && trim($key) !== '');
        }

        protected function _exists($key, $group)
        {
            Runtime::cache()->get((string) $key, $this->group($group), $found);
            return (bool) $found;
        }

        public function add($key, $data, $group = 'default', $expire = 0)
        {
            return $this->is_valid_key($key) && Runtime::cache()->add((string) $key, is_object($data) ? clone $data : $data, $this->group($group));
        }

        public function add_multiple($data, $group = '', $expire = 0)
        {
            $out = [];
            foreach ((array) $data as $key => $value) {
                $out[$key] = $this->add($key, $value, $group, $expire);
            }
            return $out;
        }

        public function replace($key, $data, $group = 'default', $expire = 0)
        {
            return $this->_exists($key, $group) && $this->set($key, $data, $group, $expire);
        }

        public function set($key, $data, $group = 'default', $expire = 0)
        {
            return $this->is_valid_key($key) && Runtime::cache()->set((string) $key, is_object($data) ? clone $data : $data, $this->group($group));
        }

        public function set_multiple($data, $group = '', $expire = 0)
        {
            $out = [];
            foreach ((array) $data as $key => $value) {
                $out[$key] = $this->set($key, $value, $group, $expire);
            }
            return $out;
        }

        public function get($key, $group = 'default', $force = false, &$found = null)
        {
            $value = $this->is_valid_key($key) ? Runtime::cache()->get((string) $key, $this->group($group), $found) : false;
            $found = (bool) $found;
            $found ? $this->cache_hits++ : $this->cache_misses++;
            return $found ? (is_object($value) ? clone $value : $value) : false;
        }

        public function get_multiple($keys, $group = 'default', $force = false)
        {
            $out = [];
            foreach ((array) $keys as $key) {
                $out[$key] = $this->get($key, $group, $force);
            }
            return $out;
        }

        public function delete($key, $group = 'default', $deprecated = false)
        {
            return $this->is_valid_key($key) && Runtime::cache()->delete((string) $key, $this->group($group));
        }

        public function delete_multiple($keys, $group = '')
        {
            $out = [];
            foreach ((array) $keys as $key) {
                $out[$key] = $this->delete($key, $group);
            }
            return $out;
        }

        public function incr($key, $offset = 1, $group = 'default')
        {
            $value = $this->get($key, $group);
            if (!is_numeric($value)) {
                return false;
            }
            $value = max(0, (int) $value + (int) $offset);
            $this->set($key, $value, $group);
            return $value;
        }

        public function decr($key, $offset = 1, $group = 'default')
        {
            return $this->incr($key, -(int) $offset, $group);
        }

        public function flush()
        {
            return Runtime::cache()->flush();
        }

        public function flush_group($group)
        {
            return Runtime::cache()->flushGroup($this->group($group));
        }

        public function add_global_groups($groups)
        {
            $this->global_groups = array_merge($this->global_groups, array_fill_keys((array) $groups, true));
        }

        public function switch_to_blog($blog_id)
        {
            $this->blog_prefix = $this->multisite ? (int) $blog_id . ':' : '';
        }

        public function reset()
        {
            _deprecated_function(__FUNCTION__, '3.5.0', 'WP_Object_Cache::switch_to_blog()');
        }

        public function stats()
        {
            echo '<p><strong>Cache Hits:</strong> ' . (int) $this->cache_hits . '<br /><strong>Cache Misses:</strong> ' . (int) $this->cache_misses . '</p>';
        }

        private function group($group): string
        {
            return $group === '' || $group === null ? 'default' : (string) $group;
        }
    }
}
