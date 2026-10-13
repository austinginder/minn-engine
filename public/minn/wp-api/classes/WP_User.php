<?php

use Minn\Runtime\Runtime;

/** The user object: a row in `data`, the role and capability maps, and meta by property. */
#[AllowDynamicProperties]
class WP_User implements \Minn\Objects\User
{
    public $data;
    public $ID = 0;
    public $caps = [];
    public $cap_key;
    public $roles = [];
    public $allcaps = [];
    public $filter = null;
    private static $back_compat_keys = ['user_firstname' => 'first_name', 'user_lastname' => 'last_name', 'user_description' => 'description', 'user_level' => 'wp_user_level', 'wp_usersettings' => 'wp_user-settings', 'wp_usersettingstime' => 'wp_user-settings-time'];

    public function __construct($id = 0, $name = '', $site_id = '')
    {
        if ($id instanceof WP_User) {
            $this->init($id->data, $site_id);
            return;
        }
        if (is_object($id)) {
            $this->init($id, $site_id);
            return;
        }
        if ($id !== 0 && $id !== '' && !is_numeric($id)) {
            $name = $id;
            $id = 0;
        }
        $data = $id ? self::get_data_by('id', $id) : ($name !== '' ? self::get_data_by('login', $name) : false);
        if ($data) {
            $this->init($data, $site_id);
        } else {
            $this->data = new stdClass();
        }
    }

    public function init($data, $site_id = '')
    {
        if (!isset($data->ID)) {
            $data->ID = 0;
        }
        $this->data = $data;
        $this->ID = (int) $data->ID;
        $this->for_site($site_id);
    }

    public static function get_data_by($field, $value)
    {
        $users = new Minn\Content\Users(Runtime::current()->db);
        $row = match ($field) {
            'id', 'ID' => is_numeric($value) && (int) $value > 0 ? $users->find((int) $value) : null,
            // A login is looked up as sanitize_user leaves it, as the reference looks one up.
            'login' => $users->findByLogin((string) sanitize_user((string) $value)),
            // An email or a slug is looked up without its surrounding spaces (probe user-remaining).
            'email' => $users->findByEmail(trim((string) $value)),
            'slug' => $users->findBySlug(trim((string) $value)),
            default => null,
        };
        if ($row === null) {
            return false;
        }
        $object = new stdClass();
        foreach (['ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name'] as $column) {
            $object->{$column} = $column === 'ID' ? (int) $row[$column] : (string) ($row[$column] ?? '');
        }
        return $object;
    }

    public function __isset($key)
    {
        if ($key === 'id') {
            $key = 'ID';
        }
        if (isset($this->data->{$key})) {
            return true;
        }
        if (isset(self::$back_compat_keys[$key])) {
            $key = self::$back_compat_keys[$key];
        }
        return metadata_exists('user', $this->ID, $key);
    }

    public function __get($key)
    {
        if ($key === 'id') {
            return $this->ID;
        }
        if (isset($this->data->{$key})) {
            return $this->data->{$key};
        }
        if (isset(self::$back_compat_keys[$key])) {
            $key = self::$back_compat_keys[$key];
        }
        return $this->ID > 0 ? get_user_meta($this->ID, $key, true) : false;
    }

    public function __set($key, $value)
    {
        if ($key === 'id') {
            $this->ID = $value;
            return;
        }
        $this->data->{$key} = $value;
    }

    public function __unset($key)
    {
        if (isset($this->data->{$key})) {
            unset($this->data->{$key});
        }
    }

    public function exists()
    {
        return !empty($this->ID);
    }

    public function get($key)
    {
        return $this->__get($key);
    }

    public function has_prop($key)
    {
        return $this->__isset($key);
    }

    public function to_array()
    {
        return get_object_vars($this->data);
    }

    public function for_site($site_id = '')
    {
        $this->minn_read_caps(Runtime::current()->db->prefix() . 'capabilities');
    }

    /** Only _init_caps answers here, for code written before it was protected; anything else is false. */
    public function __call($name, $arguments)
    {
        return $name === '_init_caps' ? $this->_init_caps(...$arguments) : false;
    }

    /** @deprecated 4.9.0 the capabilities under a key of the caller's, or the site's own */
    protected function _init_caps($cap_key = '')
    {
        _deprecated_function(__METHOD__, '4.9.0', 'WP_User::for_site()');
        $this->minn_read_caps($cap_key === '' ? Runtime::current()->db->prefix() . 'capabilities' : (string) $cap_key);
    }

    /** @deprecated 4.9.0 */
    public function for_blog($blog_id = 0)
    {
        _deprecated_function(__METHOD__, '4.9.0', 'WP_User::for_site()');
        $this->for_site($blog_id);
    }

    /** @internal the user's capabilities read from a meta key, and their roles and full set worked out */
    private function minn_read_caps(string $key): void
    {
        $this->cap_key = $key;
        $this->caps = $this->ID > 0 ? (array) (get_user_meta($this->ID, $key, true) ?: []) : [];
        $this->get_role_caps();
    }

    public function get_site_id()
    {
        return 1;
    }

    public function get_role_caps()
    {
        $roles = Runtime::current()->capabilities->roles()->all();
        $this->roles = [];
        $this->allcaps = [];
        // A stored role counts whatever it is set to; the user's own caps, role names included, go over the roles' (probe: allcaps on the reference).
        foreach ($this->caps as $cap => $granted) {
            if (isset($roles[$cap])) {
                $this->roles[] = (string) $cap;
            }
        }
        foreach ($this->roles as $role) {
            foreach ($roles[$role]['capabilities'] as $cap => $granted) {
                $this->allcaps[$cap] = $granted;
            }
        }
        foreach ($this->caps as $cap => $granted) {
            $this->allcaps[$cap] = $granted;
        }
        return $this->allcaps;
    }

    public function add_role($role)
    {
        if (!isset(Runtime::current()->capabilities->roles()->all()[$role]) || in_array($role, $this->roles, true)) {
            return;
        }
        $this->caps[$role] = true;
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
        do_action('add_user_role', $this->ID, $role);
    }

    public function remove_role($role)
    {
        if (!in_array($role, $this->roles, true)) {
            return;
        }
        unset($this->caps[$role]);
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
        do_action('remove_user_role', $this->ID, $role);
    }

    public function set_role($role)
    {
        if (count($this->roles) === 1 && current($this->roles) === $role) {
            return;
        }
        $old = $this->roles;
        foreach ($old as $existing) {
            unset($this->caps[$existing]);
        }
        if (!empty($role)) {
            $this->caps[$role] = true;
        }
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
        foreach ($old as $existing) {
            if ($existing !== $role) {
                do_action('remove_user_role', $this->ID, $existing);
            }
        }
        if (!empty($role) && !in_array($role, $old, true)) {
            do_action('add_user_role', $this->ID, $role);
        }
        do_action('set_user_role', $this->ID, $role, $old);
    }

    public function level_reduction($max, $item)
    {
        if (preg_match('/^level_(10|[0-9])$/i', (string) $item, $m)) {
            return max($max, (int) $m[1]);
        }
        return $max;
    }

    public function update_user_level_from_caps()
    {
        $level = array_reduce(array_keys($this->allcaps), [$this, 'level_reduction'], 0);
        update_user_meta($this->ID, Runtime::current()->db->prefix() . 'user_level', $level);
    }

    public function add_cap($cap, $grant = true)
    {
        $this->caps[$cap] = $grant;
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
    }

    public function remove_cap($cap)
    {
        if (!isset($this->caps[$cap])) {
            return;
        }
        unset($this->caps[$cap]);
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
    }

    public function remove_all_caps()
    {
        $this->caps = [];
        delete_user_meta($this->ID, $this->cap_key);
        delete_user_meta($this->ID, Runtime::current()->db->prefix() . 'user_level');
        $this->get_role_caps();
    }

    public function has_cap($cap, ...$args)
    {
        return \Minn\Auth\Can::check($this, $cap, ...$args);
    }

    public function translate_level_to_cap($level)
    {
        return 'level_' . $level;
    }
}
