<?php

/** A named set of CSS rules, one per selector (and rules group), kept for the request (probe style-engine). */
#[AllowDynamicProperties]
class WP_Style_Engine_CSS_Rules_Store
{
    protected static $stores = [];
    protected $name = '';
    protected $rules = [];

    public static function get_store($store_name = 'default')
    {
        if (!is_string($store_name) || $store_name === '') {
            return null;
        }
        if (!isset(static::$stores[$store_name])) {
            static::$stores[$store_name] = new static();
            static::$stores[$store_name]->set_name($store_name);
        }
        return static::$stores[$store_name];
    }

    public static function get_stores()
    {
        return static::$stores;
    }

    public static function remove_all_stores()
    {
        static::$stores = [];
    }

    public function set_name($name)
    {
        $this->name = $name;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_all_rules()
    {
        return $this->rules;
    }

    public function add_rule($selector, $rules_group = '')
    {
        $selector = trim((string) $selector);
        $rules_group = trim((string) $rules_group);
        if ($selector === '') {
            return null;
        }
        $key = $rules_group === '' ? $selector : "{$rules_group} {$selector}";
        $this->rules[$key] ??= new WP_Style_Engine_CSS_Rule($selector, [], $rules_group);
        return $this->rules[$key];
    }

    public function remove_rule($selector)
    {
        unset($this->rules[$selector]);
    }
}
