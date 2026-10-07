<?php

use Minn\Runtime\AbilityRun;

/**
 * One registered ability: its recorded arguments behind the getters, and a
 * run as the reference runs one (probe abilities-registry): the input
 * checked against the input schema, then the permission, then the
 * callback between wp_before_execute_ability and wp_after_execute_ability,
 * its output checked against the output schema.
 */
class WP_Ability
{
    protected $name;
    protected $args;

    public function __construct($name, $args = [])
    {
        $this->name = (string) $name;
        $this->args = (array) $args;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_label()
    {
        return (string) ($this->args['label'] ?? '');
    }

    public function get_description()
    {
        return (string) ($this->args['description'] ?? '');
    }

    public function get_category()
    {
        return (string) ($this->args['category'] ?? '');
    }

    public function get_input_schema()
    {
        return (array) ($this->args['input_schema'] ?? []);
    }

    public function get_output_schema()
    {
        return (array) ($this->args['output_schema'] ?? []);
    }

    public function get_meta()
    {
        return (array) ($this->args['meta'] ?? []);
    }

    public function get_meta_item($key, $default_value = null)
    {
        return array_key_exists($key, $this->get_meta()) ? $this->get_meta()[$key] : $default_value;
    }

    /** What the permission callback answers: true, false, or its error. */
    public function check_permissions($input = null)
    {
        $callback = $this->args['permission_callback'] ?? null;
        if (!is_callable($callback)) {
            return false;
        }
        return $input === null ? call_user_func($callback) : call_user_func($callback, $input);
    }

    public function has_permission($input = null)
    {
        return $this->check_permissions($input) === true;
    }

    public function validate_input($input = null)
    {
        return AbilityRun::input($this->name, $this->get_input_schema(), $input);
    }

    public function execute($input = null)
    {
        return AbilityRun::execute($this, $this->args['execute_callback'] ?? null, $input);
    }
}
