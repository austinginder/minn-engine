<?php

use Minn\Runtime\Runtime;

class WP_Role
{
    public $name;
    public $capabilities;

    public function __construct($role, $capabilities)
    {
        $this->name = $role;
        $this->capabilities = $capabilities;
    }

    public function add_cap($cap, $grant = true)
    {
        $this->capabilities[$cap] = $grant;
        wp_roles()->add_cap($this->name, $cap, $grant);
    }

    public function remove_cap($cap)
    {
        unset($this->capabilities[$cap]);
        wp_roles()->remove_cap($this->name, $cap);
    }

    public function has_cap($cap)
    {
        $capabilities = apply_filters('role_has_cap', $this->capabilities, $cap, $this->name);
        return !empty($capabilities[$cap]);
    }
}

class WP_Roles
{
    public $roles = [];
    public $role_objects = [];
    public $role_names = [];
    public $role_key;
    public $use_db = true;

    public function __construct($site_id = null)
    {
        $this->role_key = Runtime::current()->db->prefix() . 'user_roles';
        $this->init_roles();
    }

    public function init_roles()
    {
        $this->roles = Runtime::current()->capabilities->roles()->all();
        $this->role_objects = [];
        $this->role_names = [];
        foreach ($this->roles as $role => $data) {
            $this->role_objects[$role] = new WP_Role($role, $data['capabilities']);
            $this->role_names[$role] = $data['name'];
        }
    }

    public function for_site($site_id = null)
    {
        $this->init_roles();
    }

    public function get_site_id()
    {
        return 1;
    }

    public function add_role($role, $display_name, $capabilities = [])
    {
        if (empty($role) || isset($this->roles[$role])) {
            return null;
        }
        $this->roles[$role] = ['name' => $display_name, 'capabilities' => $capabilities];
        $this->persist();
        $this->role_objects[$role] = new WP_Role($role, $capabilities);
        $this->role_names[$role] = $display_name;
        return $this->role_objects[$role];
    }

    public function remove_role($role)
    {
        if (!isset($this->role_objects[$role])) {
            return;
        }
        unset($this->role_objects[$role], $this->role_names[$role], $this->roles[$role]);
        $this->persist();
        if (get_option('default_role') === $role) {
            update_option('default_role', 'subscriber');
        }
    }

    public function add_cap($role, $cap, $grant = true)
    {
        if (!isset($this->roles[$role])) {
            return;
        }
        $this->roles[$role]['capabilities'][$cap] = $grant;
        $this->persist();
    }

    public function remove_cap($role, $cap)
    {
        if (!isset($this->roles[$role])) {
            return;
        }
        unset($this->roles[$role]['capabilities'][$cap]);
        $this->persist();
    }

    public function get_role($role)
    {
        return $this->role_objects[$role] ?? null;
    }

    public function get_names()
    {
        return $this->role_names;
    }

    public function is_role($role)
    {
        return isset($this->role_names[$role]);
    }

    private function persist()
    {
        update_option($this->role_key, $this->roles);
    }
}
