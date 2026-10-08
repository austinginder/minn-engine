<?php

use Minn\Blocks\Block as MinnBlock;
use Minn\Content\Blocks as MinnBlocks;
use Minn\Runtime\BlockFilters;
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

    /** The bound attributes' values, as the sources answer them for this block and its context (Minn\\Blocks\\Bindings). */
    public function process_block_bindings()
    {
        return \Minn\Blocks\Bindings::values(MinnBlock::fromArray($this->parsed_block), (array) $this->available_context);
    }

    public function render($options = [])
    {
        $options = wp_parse_args($options, ['dynamic' => true, 'minn_filters' => true]);
        if ($this->rendered_by_engine((bool) $options['dynamic'])) {
            // The engine's renderer renders a core block whole (its inner blocks, bindings and the supports it
            // applies itself); the block's own render_block filters run once, after it, as on the reference.
            $block_content = MinnBlocks::renderer()->providing((array) $this->available_context, fn (): string => _minn_render_core_block($this, '', false));
            if ($this->block_type->is_dynamic()) {
                $this->enqueue_assets();
            }
            return $options['minn_filters'] === false ? $block_content : BlockFilters::rendered($block_content, (array) $this->parsed_block, $this, BlockFilters::NATIVE_RENDER_DONE);
        }
        $is_dynamic = $options['dynamic'] && $this->name && $this->block_type !== null && $this->block_type->is_dynamic();
        // The engine's renderer binds a static core block itself; this one binds the rest.
        $bound = $is_dynamic || !str_starts_with((string) $this->name, 'core/') ? $this->process_block_bindings() : [];
        if ($bound !== []) {
            $this->attributes = array_merge($this->attributes, $bound);
        }
        $block_content = !$options['dynamic'] || empty($this->block_type->skip_inner_blocks) ? $this->render_inner_blocks() : '';
        if ($is_dynamic) {
            $global_post = $GLOBALS['post'] ?? null;
            $parent = WP_Block_Supports::$block_to_render;
            WP_Block_Supports::$block_to_render = $this->parsed_block;
            $block_content = (string) call_user_func($this->block_type->render_callback, $this->attributes, $block_content, $this);
            WP_Block_Supports::$block_to_render = $parent;
            $GLOBALS['post'] = $global_post;
        }
        if ($bound !== [] && $block_content !== '') {
            $block_content = \Minn\Blocks\Bindings::html($block_content, (string) $this->name, $bound);
        }
        $this->enqueue_assets();
        if (($options['minn_filters'] ?? true) === false) {
            // The engine's renderer applies the render_block filters once around a bridged block.
            return $block_content;
        }
        return BlockFilters::rendered($block_content, (array) $this->parsed_block, $this);
    }

    /**
     * Whether the engine's own renderer answers for this block: a registered
     * core block, static, or dynamic with its own render callback (none a
     * plugin took over), rendered with its callback.
     */
    private function rendered_by_engine(bool $dynamic): bool
    {
        if ($this->block_type === null || !_minn_renders_natively($this->name)) {
            return false;
        }
        return !$this->block_type->is_dynamic() || ($dynamic && _minn_core_renders_natively((string) $this->name, $this->block_type->render_callback));
    }

    /**
     * Inner content is a list of HTML chunks with null slots where the inner
     * blocks go, in order. Each inner block's context passes through the
     * render_block_context filter before it renders (the reference does this
     * for every nesting level; it is how a query loop hands postId down),
     * and a changed context rebuilds the inner block so it cascades.
     */
    private function render_inner_blocks(): string
    {
        $content = '';
        $index = 0;
        foreach ($this->inner_content as $chunk) {
            if (is_string($chunk)) {
                $content .= $chunk;
                continue;
            }
            $inner = $this->inner_blocks[$index++];
            $pre_render = apply_filters('pre_render_block', null, $inner->parsed_block, $this);
            if ($pre_render !== null) {
                $content .= $pre_render;
                continue;
            }
            $parsed = BlockFilters::data((array) $inner->parsed_block, $this, _minn_renders_natively($inner->name) ? BlockFilters::NATIVE_DATA_DONE : []);
            $context = apply_filters('render_block_context', $inner->available_context, $parsed, $this);
            $context = is_array($context) ? $context : $inner->available_context;
            if ($parsed !== $inner->parsed_block || $context !== $inner->available_context) {
                $inner = new self($parsed, $context, $this->registry);
            }
            $content .= $inner->render();
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
#[AllowDynamicProperties]
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
#[AllowDynamicProperties]
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

    /** Registers the supports' attributes on every block type registered so far (hooked to init at 22). */
    public static function init()
    {
        self::get_instance()->register_attributes();
    }

    /** Records a support: its register_attribute and apply callbacks, in the order supports apply. */
    public function register($block_support_name, $block_support_config)
    {
        $this->block_supports[$block_support_name] = array_merge($block_support_config, ['name' => $block_support_name]);
    }

    /**
     * The wrapper attributes the block being rendered earns: every support's
     * apply callback in turn, a later one's value joined to an earlier one's
     * with a space.
     */
    public function apply_block_supports()
    {
        $block = self::$block_to_render;
        $block_type = $block === null || empty($block['blockName']) ? null : WP_Block_Type_Registry::get_instance()->get_registered($block['blockName']);
        if (!$block_type instanceof WP_Block_Type) {
            return [];
        }
        $attributes = $block_type->prepare_attributes_for_render((array) ($block['attrs'] ?? []));
        $output = [];
        foreach (array_filter(array_column($this->block_supports, 'apply')) as $apply) {
            foreach ((array) call_user_func($apply, $block_type, $attributes) as $name => $value) {
                $output[$name] = empty($output[$name]) ? $value : $output[$name] . ' ' . $value;
            }
        }
        return $output;
    }

    /** Lets each support add the attributes it needs to every registered block type. */
    public function register_attributes()
    {
        foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $block_type) {
            foreach ($this->block_supports as $support) {
                if (!empty($support['register_attribute'])) {
                    call_user_func($support['register_attribute'], $block_type);
                }
            }
        }
    }
}
