<?php
/**
 * The block bindings sources (probe block-bindings): a source registers
 * once, under a lower-case namespaced name, with a label and a callable
 * get_value_callback; anything else is refused with a notice.
 */
final class WP_Block_Bindings_Registry
{
    private $sources = [];
    private static $instance = null;
    private $allowed_source_properties = ['label', 'get_value_callback', 'uses_context'];

    public function register(string $source_name, array $source_properties)
    {
        $refusal = _minn_block_bindings_refusal($source_name, $source_properties, isset($this->sources[$source_name]), $this->allowed_source_properties);
        if ($refusal !== null) {
            _doing_it_wrong(__METHOD__, $refusal, '6.5.0');
            return false;
        }
        $source = new WP_Block_Bindings_Source($source_name, $source_properties);
        $this->sources[$source_name] = $source;
        return $source;
    }

    public function unregister(string $source_name)
    {
        if (!$this->is_registered($source_name)) {
            _doing_it_wrong(__METHOD__, sprintf(__('Block binding "%s" not found.'), $source_name), '6.5.0');
            return false;
        }
        $source = $this->sources[$source_name];
        unset($this->sources[$source_name]);
        return $source;
    }

    public function get_all_registered()
    {
        return $this->sources;
    }

    public function get_registered(string $source_name)
    {
        return $this->sources[$source_name] ?? null;
    }

    public function is_registered($source_name)
    {
        return isset($this->sources[$source_name]);
    }

    public function __wakeup()
    {
        if (!$this->sources) {
            return;
        }
        foreach ($this->sources as $source) {
            if (!$source instanceof WP_Block_Bindings_Source) {
                throw new UnexpectedValueException();
            }
        }
    }

    public static function get_instance()
    {
        return self::$instance ??= new self();
    }
}

/** @internal why a source cannot register, or null when it can */
function _minn_block_bindings_refusal(string $name, array $properties, bool $taken, array $allowed): ?string
{
    return match (true) {
        !preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $name) => __('Block bindings source names must contain a namespace prefix and only lowercase alphanumeric characters or dashes. Example: my-plugin/my-custom-source'),
        $taken => sprintf(__('Block bindings source "%s" already registered.'), $name),
        !isset($properties['label']) => __('The $source_properties must contain a "label".'),
        !isset($properties['get_value_callback']) => __('The $source_properties must contain a "get_value_callback".'),
        !is_callable($properties['get_value_callback']) => __('The "get_value_callback" parameter must be a valid callback.'),
        isset($properties['uses_context']) && !is_array($properties['uses_context']) => __('The "uses_context" parameter must be an array.'),
        array_diff(array_keys($properties), $allowed) !== [] => sprintf(__('The following properties are not allowed for a block bindings source: "%s".'), implode('", "', array_diff(array_keys($properties), $allowed))),
        default => null,
    };
}
