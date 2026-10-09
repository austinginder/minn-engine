<?php

use Minn\Runtime\Abilities;
use Minn\Runtime\AbilityRun;

/**
 * One registered ability (probes abilities-registry, abilities-classes).
 * Its properties are checked as it is made: a refusal throws
 * InvalidArgumentException naming the property, and the meta gains its
 * defaults. A run as the reference runs one: input left out takes the input
 * schema's default, the input is checked against the input schema, then
 * the permission, then the callback between wp_before_execute_ability and
 * wp_after_execute_ability, its output checked against the output schema.
 * It refuses to be serialized or unserialized.
 */
class WP_Ability
{
    const DEFAULT_SHOW_IN_REST = false;
    const DEFAULT_PUBLIC = false;

    protected static $default_annotations = ['readonly' => null, 'destructive' => null, 'idempotent' => null];
    protected $name;
    protected $label;
    protected $description;
    protected $category;
    protected $input_schema = [];
    protected $output_schema = [];
    protected $execute_callback;
    protected $permission_callback;
    protected $meta;

    public function __construct(string $name, array $args)
    {
        $this->name = $name;
        $properties = $this->prepare_properties($args);
        foreach (['label', 'description', 'category', 'input_schema', 'output_schema', 'execute_callback', 'permission_callback', 'meta'] as $key) {
            if (array_key_exists($key, $properties)) {
                $this->{$key} = $properties[$key];
            }
        }
    }

    protected function prepare_properties(array $args): array
    {
        $refusal = Abilities::abilityRefusal($args);
        if ($refusal !== null) {
            throw new InvalidArgumentException($refusal);
        }
        $args['meta'] = Abilities::meta($args['meta'] ?? []);
        return $args;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function get_description(): string
    {
        return $this->description;
    }

    public function get_category(): string
    {
        return $this->category;
    }

    public function get_input_schema(): array
    {
        return (array) $this->input_schema;
    }

    public function get_output_schema(): array
    {
        return (array) $this->output_schema;
    }

    public function get_meta(): array
    {
        return (array) $this->meta;
    }

    public function get_meta_item(string $key, $default_value = null)
    {
        return array_key_exists($key, $this->get_meta()) ? $this->get_meta()[$key] : $default_value;
    }

    public function normalize_input($input = null)
    {
        return $input ?? ($this->get_input_schema()['default'] ?? null);
    }

    public function validate_input($input = null)
    {
        return AbilityRun::input($this->name, $this->get_input_schema(), $input);
    }

    protected function invoke_callback(callable $callback, $input = null)
    {
        return $input === null ? call_user_func($callback) : call_user_func($callback, $input);
    }

    /** What the permission callback answers: true, false, or its error. */
    public function check_permissions($input = null)
    {
        return $this->invoke_callback($this->permission_callback, $input);
    }

    protected function do_execute($input = null)
    {
        return $this->invoke_callback($this->execute_callback, $input);
    }

    protected function validate_output($output)
    {
        return AbilityRun::output($this->name, $this->get_output_schema(), $output);
    }

    public function execute($input = null)
    {
        $input = $this->normalize_input($input);
        $valid = $this->validate_input($input);
        $allowed = is_wp_error($valid) ? $valid : AbilityRun::allowed($this->name, $this->check_permissions($input));
        if (is_wp_error($allowed)) {
            return $allowed;
        }
        do_action('wp_before_execute_ability', $this->name, $input);
        $result = $this->do_execute($input);
        $fits = is_wp_error($result) ? $result : $this->validate_output($result);
        if (is_wp_error($fits)) {
            return $fits;
        }
        do_action('wp_after_execute_ability', $this->name, $input, $result);
        return $result;
    }

    public function __wakeup(): void
    {
        throw new LogicException(__CLASS__ . ' should never be unserialized.');
    }

    public function __sleep(): array
    {
        throw new LogicException(__CLASS__ . ' should never be serialized.');
    }
}
