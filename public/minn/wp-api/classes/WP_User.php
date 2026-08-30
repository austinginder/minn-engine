<?php

use Minn\Runtime\Runtime;

/** The user object: a row in `data`, the role and capability maps, and meta by property. */
class WP_User
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
            'login' => $users->findByLogin((string) $value),
            'email' => $users->findByEmail((string) $value),
            'slug' => $users->findBySlug((string) $value),
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
        $prefix = Runtime::current()->db->prefix();
        $this->cap_key = $prefix . 'capabilities';
        $this->caps = $this->ID > 0 ? (array) (get_user_meta($this->ID, $this->cap_key, true) ?: []) : [];
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
        foreach ($this->caps as $cap => $granted) {
            if ($granted && isset($roles[$cap])) {
                $this->roles[] = (string) $cap;
            }
        }
        foreach ($this->roles as $role) {
            foreach ($roles[$role]['capabilities'] as $cap => $granted) {
                $this->allcaps[$cap] = (bool) $granted;
            }
        }
        foreach ($this->caps as $cap => $granted) {
            if (!isset($roles[$cap])) {
                $this->allcaps[$cap] = (bool) $granted;
            }
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
        if (count($this->roles) === 1 && $this->roles[0] === $role) {
            return;
        }
        $old = $this->roles;
        foreach ($this->roles as $existing) {
            unset($this->caps[$existing]);
        }
        if ($role !== '' && $role !== null) {
            $this->caps[$role] = true;
        }
        update_user_meta($this->ID, $this->cap_key, $this->caps);
        $this->get_role_caps();
        $this->update_user_level_from_caps();
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
        if (is_numeric($cap)) {
            $cap = 'level_' . (int) $cap;
        }
        $caps = map_meta_cap($cap, $this->ID, ...$args);
        $capabilities = apply_filters('user_has_cap', $this->allcaps, $caps, array_merge([$cap, $this->ID], $args), $this);
        $capabilities['exist'] = true;
        unset($capabilities['do_not_allow']);
        foreach ((array) $caps as $required) {
            if (empty($capabilities[$required])) {
                return false;
            }
        }
        return true;
    }

    public function translate_level_to_cap($level)
    {
        return 'level_' . $level;
    }
}
