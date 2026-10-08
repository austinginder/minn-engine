<?php
/**
 * One registered block bindings source (probe block-bindings): its name,
 * label and the context it uses; get_value asks its callback for a bound
 * attribute's value and hands the answer to block_bindings_source_value.
 */
final class WP_Block_Bindings_Source
{
    public $name;
    public $label;
    public $uses_context;
    private $get_value_callback;

    public function __construct(string $name, array $source_properties)
    {
        $this->name = $name;
        foreach ($source_properties as $property_name => $property_value) {
            $this->$property_name = $property_value;
        }
    }

    public function get_value(array $source_args, $block_instance, string $attribute_name)
    {
        $value = call_user_func_array($this->get_value_callback, [$source_args, $block_instance, $attribute_name]);
        return apply_filters('block_bindings_source_value', $value, $this->name, $source_args, $block_instance, $attribute_name);
    }

    public function __wakeup()
    {
        throw new LogicException(__CLASS__ . ' should never be unserialized');
    }
}
