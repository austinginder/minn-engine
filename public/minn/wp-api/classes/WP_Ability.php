<?php
/** One registered ability: the recorded arguments behind the reflection-listed getters. */
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
        return $this->get_meta()[$key] ?? $default_value;
    }

    public function has_permission($input = null)
    {
        $callback = $this->args['permission_callback'] ?? null;
        return is_callable($callback) ? (bool) $callback($input) : false;
    }

    public function execute($input = null)
    {
        if (!$this->has_permission($input)) {
            return new WP_Error('ability_permission_denied', 'You do not have permission to execute this ability.');
        }
        $callback = $this->args['execute_callback'] ?? null;
        return is_callable($callback) ? $callback($input) : new WP_Error('ability_no_callback', 'No execute callback.');
    }
}
