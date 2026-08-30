<?php

use Minn\Blocks\Block as MinnBlock;
use Minn\Blocks\Supports;
use Minn\Content\Blocks as MinnBlocks;
use Minn\Runtime\Runtime;

/** A parsed block ready to render, with its context and inner blocks. */
#[AllowDynamicProperties]
class WP_Block
{
    public $parsed_block;
    public $name;
    public $block_type;
    public $context = [];
    protected $available_context;
    protected $registry;
    public $inner_blocks = [];
    public $inner_html = '';
    public $inner_content = [];

    public function __construct($block, $available_context = [], $registry = null)
    {
        $this->parsed_block = $block;
        $this->name = $block['blockName'];
        if ($registry === null) {
            $registry = WP_Block_Type_Registry::get_instance();
        }
        $this->registry = $registry;
        $this->block_type = $registry->get_registered($this->name);
        $this->available_context = $available_context;
        $this->refresh_context_dependents();
    }

    public function refresh_context_dependents()
    {
        $this->context = [];
        if (!empty($this->block_type->uses_context)) {
            foreach ($this->block_type->uses_context as $context_name) {
                if (array_key_exists($context_name, $this->available_context)) {
                    $this->context[$context_name] = $this->available_context[$context_name];
                }
            }
        }
        $this->refresh_parsed_block_dependents();
    }

    public function refresh_parsed_block_dependents()
    {
        if (!empty($this->parsed_block['innerBlocks'])) {
            $child_context = $this->available_context;
            if (!empty($this->block_type->provides_context)) {
                foreach ($this->block_type->provides_context as $context_name => $attribute_name) {
                    if (array_key_exists($attribute_name, $this->attributes)) {
                        $child_context[$context_name] = $this->attributes[$attribute_name];
                    }
                }
            }
            $this->inner_blocks = new WP_Block_List($this->parsed_block['innerBlocks'], $child_context, $this->registry);
        }
        if (!empty($this->parsed_block['innerHTML'])) {
            $this->inner_html = $this->parsed_block['innerHTML'];
        }
        if (!empty($this->parsed_block['innerContent'])) {
            $this->inner_content = $this->parsed_block['innerContent'];
        }
    }

    public function __get($name)
    {
        if ($name === 'attributes') {
            $this->attributes = $this->parsed_block['attrs'] ?? [];
            if ($this->block_type !== null) {
                $this->attributes = $this->block_type->prepare_attributes_for_render($this->attributes);
            }
            return $this->attributes;
        }
        return null;
    }

    public function process_block_bindings()
    {
        return [];
    }

    public function render($options = [])
    {
        $options = wp_parse_args($options, ['dynamic' => true, 'minn_filters' => true]);
        $is_dynamic = $options['dynamic'] && $this->name && $this->block_type !== null && $this->block_type->is_dynamic();
        $block_content = !$options['dynamic'] || empty($this->block_type->skip_inner_blocks) ? $this->render_inner_blocks() : '';
        if ($is_dynamic) {
            $global_post = $GLOBALS['post'] ?? null;
            $parent = WP_Block_Supports::$block_to_render;
            WP_Block_Supports::$block_to_render = $this->parsed_block;
            $block_content = (string) call_user_func($this->block_type->render_callback, $this->attributes, $block_content, $this);
            WP_Block_Supports::$block_to_render = $parent;
            $GLOBALS['post'] = $global_post;
        } elseif ($this->name !== null && str_starts_with($this->name, 'core/') && $this->block_type !== null) {
            // A static core block takes its classes from the engine's own renderer, which applies the block filters itself.
            return _minn_render_core_block($this, $block_content);
        }
        $this->enqueue_assets();
        if (($options['minn_filters'] ?? true) === false) {
            // The engine's renderer applies the render_block filters once around a bridged block.
            return $block_content;
        }
        $block_content = apply_filters('render_block', $block_content, $this->parsed_block, $this);
        return apply_filters("render_block_{$this->name}", $block_content, $this->parsed_block, $this);
    }

    /** Inner content is a list of HTML chunks with null slots where the inner blocks go, in order. */
    private function render_inner_blocks(): string
    {
        $content = '';
        $index = 0;
        foreach ($this->inner_content as $chunk) {
            $content .= is_string($chunk) ? $chunk : $this->inner_blocks[$index++]->render();
        }
        return $content;
    }

    private function enqueue_assets(): void
    {
        if ($this->block_type === null) {
            return;
        }
        foreach ([...(array) $this->block_type->script_handles, ...(array) $this->block_type->view_script_handles] as $handle) {
            wp_enqueue_script($handle);
        }
        foreach ((array) $this->block_type->view_script_module_ids as $id) {
            wp_enqueue_script_module($id);
        }
        foreach ((array) $this->block_type->style_handles as $handle) {
            wp_enqueue_style($handle);
        }
    }
}

/** The inner blocks of a block, with the context their parent provides. */
class WP_Block_List implements Iterator, ArrayAccess, Countable
{
    protected $blocks;
    protected $available_context;
    protected $registry;

    public function __construct($blocks, $available_context = [], $registry = null)
    {
        if ($registry === null) {
            $registry = WP_Block_Type_Registry::get_instance();
        }
        $this->blocks = $blocks;
        $this->available_context = $available_context;
        $this->registry = $registry;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->blocks[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        $block = $this->blocks[$offset] ?? null;
        if (isset($block) && is_array($block)) {
            $block = new WP_Block($block, $this->available_context, $this->registry);
            $this->blocks[$offset] = $block;
        }
        return $block;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->blocks[] = $value;
        } else {
            $this->blocks[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->blocks[$offset]);
    }

    public function rewind(): void
    {
        reset($this->blocks);
    }

    public function current(): mixed
    {
        return $this->offsetGet($this->key());
    }

    public function key(): mixed
    {
        return key($this->blocks);
    }

    public function next(): void
    {
        next($this->blocks);
    }

    public function valid(): bool
    {
        return $this->key() !== null;
    }

    public function count(): int
    {
        return count($this->blocks);
    }
}

/** The block being rendered, for get_block_wrapper_attributes(), and the supports that shape its wrapper. */
final class WP_Block_Supports
{
    public static $block_to_render = null;
    private $block_supports = [];
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init()
    {
    }

    public function register($block_support_name, $block_support_config)
    {
        $this->block_supports[$block_support_name] = array_merge($block_support_config, ['name' => $block_support_name]);
    }

    public function apply_block_supports()
    {
        $block = self::$block_to_render;
        if ($block === null || empty($block['blockName'])) {
            return [];
        }
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']);
        if ($block_type === null) {
            return [];
        }
        $attributes = $block_type->prepare_attributes_for_render($block['attrs'] ?? []);
        $supports = is_array($block_type->supports) ? $block_type->supports : [];
        return Supports::attributes($attributes, $supports, wp_get_block_default_classname($block['blockName']), static fn (string $name) => _wp_to_kebab_case($name));
    }

    public function register_attributes()
    {
    }
}
