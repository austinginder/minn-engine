<?php

use Minn\Blocks\BlockName;
use Minn\Runtime\Runtime;

/** A block type as registered; the core set is data/blocks.json. Shapes from contracts/fixtures/api/blocks.json. */
#[AllowDynamicProperties]
class WP_Block_Type
{
    public $api_version = 1;
    public $name;
    public $title = '';
    public $category = null;
    public $parent = null;
    public $ancestor = null;
    public $allowed_blocks = null;
    public $icon = null;
    public $description = '';
    public $keywords = [];
    public $textdomain = null;
    public $styles = [];
    public $variations = [];
    public $variation_callback = null;
    public $selectors = [];
    public $supports = null;
    public $example = null;
    public $render_callback = null;
    public $attributes = null;
    public $uses_context = [];
    public $provides_context = null;
    public $block_hooks = [];
    public $editor_script_handles = [];
    public $script_handles = [];
    public $view_script_handles = [];
    public $view_script_module_ids = [];
    public $editor_style_handles = [];
    public $style_handles = [];
    public $view_style_handles = [];
    private $deprecated_properties = ['editor_script', 'script', 'view_script', 'editor_style', 'style', 'view_style'];

    public function __construct($block_type, $args = [])
    {
        $this->name = $block_type;
        $this->set_props($args);
    }

    public function __get($name)
    {
        if (!in_array($name, $this->deprecated_properties, true)) {
            return null;
        }
        $handles = $this->{$name . '_handles'};
        if (!is_array($handles) || $handles === []) {
            return null;
        }
        return $handles[0];
    }

    public function __isset($name)
    {
        if (!in_array($name, $this->deprecated_properties, true)) {
            return false;
        }
        return $this->{$name . '_handles'} !== [];
    }

    public function __set($name, $value)
    {
        if (!in_array($name, $this->deprecated_properties, true)) {
            $this->{$name} = $value;
            return;
        }
        $this->{$name . '_handles'} = is_string($value) && $value !== '' ? [$value] : (array) $value;
    }

    public function render($attributes = [], $content = '')
    {
        if (!$this->is_dynamic()) {
            return '';
        }
        $attributes = $this->prepare_attributes_for_render($attributes);
        return (string) call_user_func($this->render_callback, $attributes, $content);
    }

    public function is_dynamic()
    {
        return is_callable($this->render_callback);
    }

    public function prepare_attributes_for_render($attributes)
    {
        if (!$this->attributes) {
            return $attributes;
        }
        foreach ($attributes as $attribute_name => $value) {
            if (!isset($this->attributes[$attribute_name])) {
                continue;
            }
            $schema = $this->attributes[$attribute_name];
            $is_valid = rest_validate_value_from_schema($value, $schema, $attribute_name);
            if (is_wp_error($is_valid)) {
                unset($attributes[$attribute_name]);
            }
        }
        $missing = array_diff_key($this->attributes, $attributes);
        foreach ($missing as $attribute_name => $schema) {
            if (isset($schema['default'])) {
                $attributes[$attribute_name] = $schema['default'];
            }
        }
        return $attributes;
    }

    public function set_props($args)
    {
        $args = wp_parse_args($args, ['render_callback' => null]);
        $args['name'] = $this->name;
        $args = apply_filters('register_block_type_args', $args, $this->name);
        if (empty($args['attributes']) || !is_array($args['attributes'])) {
            $args['attributes'] = [];
        }
        if (!array_key_exists('lock', $args['attributes'])) {
            $args['attributes']['lock'] = ['type' => 'object'];
        }
        if (!array_key_exists('metadata', $args['attributes'])) {
            $args['attributes']['metadata'] = ['type' => 'object'];
        }
        foreach ($args as $property_name => $property_value) {
            $this->{$property_name} = $property_value;
        }
    }

    public function get_attributes()
    {
        return is_array($this->attributes) ? $this->attributes : [];
    }

    public function get_variations()
    {
        if (!isset($this->variations)) {
            $this->variations = [];
            if (is_callable($this->variation_callback)) {
                $this->variations = call_user_func($this->variation_callback);
            }
        }
        return apply_filters('get_block_type_variations', $this->variations, $this);
    }

    public function get_uses_context()
    {
        return apply_filters('get_block_type_uses_context', $this->uses_context, $this);
    }
}

/** The registry, seeded lazily with the core blocks captured from the reference. */
#[AllowDynamicProperties]
final class WP_Block_Type_Registry
{
    private $registered_block_types = [];
    private static $instance = null;
    private $seeded = false;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function seed(): void
    {
        if ($this->seeded) {
            return;
        }
        $this->seeded = true;
        $rows = json_decode((string) file_get_contents(Runtime::current()->engineDir . '/data/blocks.json'), true) ?: [];
        foreach ($rows as $name => $row) {
            $dynamic = !empty($row['dynamic']);
            unset($row['dynamic'], $row['name']);
            $type = new WP_Block_Type($name);
            foreach ($row as $key => $value) {
                $type->{$key} = $value;
            }
            if ($dynamic) {
                // The engine renders its own dynamic core blocks; the callback is the bridge to that renderer.
                $type->render_callback = static fn ($attributes, $content, $block) => _minn_render_core_block($block);
            }
            if ($name === 'core/template-part') {
                // Its variations are the active theme's areas and parts, built when first asked for.
                $type->variations = null;
                $type->variation_callback = 'build_template_part_block_variations';
            }
            $this->registered_block_types[$name] = $type;
        }
    }

    public function register($name, $args = [])
    {
        $this->seed();
        $block_type = $name instanceof WP_Block_Type ? $name : null;
        $name = $block_type?->name ?? $name;
        $refused = BlockName::refuse($name);
        if ($refused !== null) {
            _doing_it_wrong(__METHOD__, $refused->message, '5.0.0');
            return false;
        }
        if ($this->is_registered($name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block type "%s" is already registered.', $name), '5.0.0');
            return false;
        }
        $block_type ??= new WP_Block_Type($name, $args);
        $this->registered_block_types[$name] = $block_type;
        if ($block_type->is_dynamic() && function_exists('_minn_bridge_dynamic_block')) {
            _minn_bridge_dynamic_block($name);
        }
        return $block_type;
    }

    public function unregister($name)
    {
        $this->seed();
        if ($name instanceof WP_Block_Type) {
            $name = $name->name;
        }
        if (!$this->is_registered($name)) {
            _doing_it_wrong(__METHOD__, sprintf('Block type "%s" is not registered.', $name), '5.0.0');
            return false;
        }
        $unregistered = $this->registered_block_types[$name];
        unset($this->registered_block_types[$name]);
        return $unregistered;
    }

    public function get_registered($name)
    {
        $this->seed();
        return $this->registered_block_types[$name] ?? null;
    }

    public function get_all_registered()
    {
        $this->seed();
        return $this->registered_block_types;
    }

    public function is_registered($name)
    {
        $this->seed();
        return isset($this->registered_block_types[$name]);
    }

    public function __wakeup()
    {
        throw new LogicException('WP_Block_Type_Registry should not be serialized');
    }
}
