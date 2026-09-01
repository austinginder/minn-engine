<?php
/** The block API: parsing, serializing, registration, rendering through plugin callbacks. Behaviour from contracts/fixtures/api/blocks.json. */

use Minn\Blocks\Block as MinnBlock;
use Minn\Blocks\Parser;
use Minn\Blocks\QueryVars;
use Minn\Blocks\Selector;
use Minn\Content\Blocks as MinnBlocks;
use Minn\Runtime\BlockMetadata;
use Minn\Runtime\Runtime;

/** @internal the engine's block value objects as the arrays plugin code reads */
function _minn_block_to_array(MinnBlock $block): array
{
    return [
        'blockName' => $block->name,
        'attrs' => $block->attrs,
        'innerBlocks' => array_map('_minn_block_to_array', $block->innerBlocks),
        'innerHTML' => $block->innerHtml,
        'innerContent' => $block->innerContent,
    ];
}

/** @internal the reverse: a parsed array as the engine's value object */
function _minn_array_to_block(array $block): MinnBlock
{
    return new MinnBlock(
        $block['blockName'] ?? null,
        (array) ($block['attrs'] ?? []),
        array_map('_minn_array_to_block', (array) ($block['innerBlocks'] ?? [])),
        (string) ($block['innerHTML'] ?? ''),
        (array) ($block['innerContent'] ?? []),
    );
}

/** @internal a core block rendered by the engine, with the wrapper classes the engine gives it */
function _minn_render_core_block(WP_Block $block, string $content = ''): string
{
    // A plugin's query loop hands the engine its per-item post through the
    // block context; the engine's own renderer reads its post stack.
    $renderer = MinnBlocks::renderer();
    $contextPost = null;
    if (!empty($block->context['postId'])) {
        $contextPost = _minn_posts()->find((int) $block->context['postId']);
    }
    if ($contextPost !== null) {
        $renderer->context()->pushPost($contextPost);
    }
    try {
        return $renderer->renderBlock(_minn_array_to_block($block->parsed_block));
    } finally {
        if ($contextPost !== null) {
            $renderer->context()->popPost();
        }
    }
}

/** @internal a dynamic block a plugin registered renders through its callback on the engine's front end too */
function _minn_bridge_dynamic_block(string $name): void
{
    MinnBlocks::renderer()->registerDynamic($name, static function (MinnBlock $block): string {
        // The engine's BlockFilters already ran pre_render_block, render_block_data and render_block around this
        // call; the block object gets the context render_block() would build, and renders once.
        $parsed = _minn_block_to_array($block);
        $context = [];
        $post = get_post();
        if ($post instanceof WP_Post) {
            $context['postId'] = $post->ID;
            $context['postType'] = $post->post_type;
        }
        $context = apply_filters('render_block_context', $context, $parsed, null);
        return (new WP_Block($parsed, $context))->render(['minn_filters' => false]);
    });
}

function parse_blocks($content)
{
    $content = (string) $content;
    if ($content === '') {
        return [];
    }
    return array_map('_minn_block_to_array', Parser::parse($content));
}

function serialize_block_attributes($block_attributes)
{
    $encoded = wp_json_encode($block_attributes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $encoded = (string) preg_replace('/--/', '\\u002d\\u002d', (string) $encoded);
    // The reference emits lower-case hex escapes.
    $encoded = (string) preg_replace_callback('/\\\\u00([0-9A-F]{2})/', static fn (array $m) => '\\u00' . strtolower($m[1]), $encoded);
    return $encoded;
}

function strip_core_block_namespace($block_name = null)
{
    if (is_string($block_name) && str_starts_with($block_name, 'core/')) {
        return substr($block_name, 5);
    }
    return $block_name;
}

function get_comment_delimited_block_content($block_name, $block_attributes, $block_content)
{
    if ($block_name === null) {
        return $block_content;
    }
    $serialized_block_name = strip_core_block_namespace($block_name);
    $serialized_attributes = empty($block_attributes) ? '' : serialize_block_attributes($block_attributes) . ' ';
    if (empty($block_content)) {
        return sprintf('<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes);
    }
    return sprintf('<!-- wp:%s %s-->%s<!-- /wp:%s -->', $serialized_block_name, $serialized_attributes, $block_content, $serialized_block_name);
}

function serialize_block($block)
{
    $block_content = '';
    $index = 0;
    foreach ($block['innerContent'] as $chunk) {
        $block_content .= is_string($chunk) ? $chunk : serialize_block($block['innerBlocks'][$index++]);
    }
    if (!is_array($block['attrs'])) {
        $block['attrs'] = [];
    }
    return get_comment_delimited_block_content($block['blockName'], $block['attrs'], $block_content);
}

function serialize_blocks($blocks)
{
    return implode('', array_map('serialize_block', $blocks));
}

function has_blocks($post = null)
{
    if (!is_string($post)) {
        $wp_post = get_post($post);
        if (!$wp_post instanceof WP_Post) {
            return false;
        }
        $post = $wp_post->post_content;
    }
    return str_contains((string) $post, '<!-- wp:');
}

function has_block($block_name, $post = null)
{
    if (!has_blocks($post)) {
        return false;
    }
    if (!is_string($post)) {
        $wp_post = get_post($post);
        $post = $wp_post instanceof WP_Post ? $wp_post->post_content : $post;
    }
    return Parser::contains((string) $post, (string) $block_name);
}

function block_version($content)
{
    return has_blocks($content) ? 1 : 0;
}

function get_dynamic_block_names()
{
    $names = [];
    foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
        if ($type->is_dynamic()) {
            $names[] = $name;
        }
    }
    return $names;
}

function register_block_type($block_type, $args = [])
{
    if (is_string($block_type) && file_exists($block_type)) {
        return register_block_type_from_metadata($block_type, $args);
    }
    return WP_Block_Type_Registry::get_instance()->register($block_type, $args);
}

function unregister_block_type($name)
{
    return WP_Block_Type_Registry::get_instance()->unregister($name);
}

function block_has_support($block_type, $feature, $default_value = false)
{
    $block_support = $default_value;
    if ($block_type instanceof WP_Block_Type && $block_type->supports) {
        if (is_array($feature) && count($feature) === 1) {
            $feature = $feature[0];
        }
        if (is_array($feature)) {
            $block_support = _wp_array_get($block_type->supports, $feature, $default_value);
        } elseif (isset($block_type->supports[$feature])) {
            $block_support = $block_type->supports[$feature];
        }
    }
    return $block_support === true || is_array($block_support);
}

function wp_get_block_default_classname($block_name)
{
    if (str_starts_with((string) $block_name, 'core/')) {
        $block_name = substr((string) $block_name, 5);
    }
    return apply_filters('block_default_classname', 'wp-block-' . str_replace('/', '-', (string) $block_name), $block_name);
}

function wp_get_block_css_selector($block_type, $target = 'root', $fallback = false)
{
    if (!$block_type instanceof WP_Block_Type) {
        return null;
    }
    return Selector::resolve((array) ($block_type->selectors ?: []), (array) ($block_type->supports ?: []), $target, (bool) $fallback, wp_get_block_default_classname($block_type->name));
}

function get_block_wrapper_attributes($extra_attributes = [])
{
    $new_attributes = WP_Block_Supports::get_instance()->apply_block_supports();
    if (empty($new_attributes) && empty($extra_attributes)) {
        return '';
    }
    foreach (['class', 'style'] as $attribute) {
        if (!empty($extra_attributes[$attribute]) && !empty($new_attributes[$attribute])) {
            $new_attributes[$attribute] = $extra_attributes[$attribute] . ' ' . $new_attributes[$attribute];
            unset($extra_attributes[$attribute]);
        }
    }
    if (!empty($new_attributes['class'])) {
        // A class named by both the caller and the block supports appears once.
        $new_attributes['class'] = implode(' ', array_unique(preg_split('/\s+/', trim((string) $new_attributes['class']), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }
    $attributes = array_merge($new_attributes, $extra_attributes);
    $normalized = [];
    foreach ($attributes as $key => $value) {
        if ($key === 'class') {
            $value = trim((string) preg_replace('/\s+/', ' ', (string) $value));
        }
        $normalized[] = $key . '="' . esc_attr((string) $value) . '"';
    }
    return implode(' ', $normalized);
}

function render_block($parsed_block)
{
    $pre_render = apply_filters('pre_render_block', null, $parsed_block, null);
    if ($pre_render !== null) {
        return $pre_render;
    }
    $source_block = $parsed_block;
    $parsed_block = apply_filters('render_block_data', $parsed_block, $source_block, null);
    $context = [];
    $post = get_post();
    if ($post instanceof WP_Post) {
        $context['postId'] = $post->ID;
        $context['postType'] = $post->post_type;
    }
    $context = apply_filters('render_block_context', $context, $parsed_block, null);
    $block = new WP_Block($parsed_block, $context);
    return $block->render();
}

function do_blocks($content)
{
    $blocks = parse_blocks((string) $content);
    $output = '';
    foreach ($blocks as $block) {
        $output .= render_block($block);
    }
    return $output;
}

function excerpt_remove_blocks($content)
{
    $allowed = apply_filters('excerpt_allowed_blocks', ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/pullquote', 'core/table', 'core/preformatted', 'core/verse', 'core/columns', 'core/column', 'core/group', 'core/block']);
    $output = '';
    foreach (parse_blocks((string) $content) as $block) {
        if ($block['blockName'] === null) {
            $output .= $block['innerHTML'];
            continue;
        }
        if (in_array($block['blockName'], $allowed, true)) {
            $output .= render_block($block);
        }
    }
    return $output;
}

function excerpt_remove_footnotes($content)
{
    return preg_replace('_<sup data-fn="[^"]+" class="fn"><a href="[^"]+" id="[^"]+">\d+</a></sup>_', '', (string) $content);
}

function filter_block_kses($block, $allowed_html, $allowed_protocols = [])
{
    $block['attrs'] = filter_block_kses_value($block['attrs'], $allowed_html, $allowed_protocols, $block);
    if (is_array($block['innerBlocks'])) {
        foreach ($block['innerBlocks'] as $i => $inner_block) {
            $block['innerBlocks'][$i] = filter_block_kses($inner_block, $allowed_html, $allowed_protocols);
        }
    }
    return $block;
}

function filter_block_kses_value($value, $allowed_html, $allowed_protocols = [], $block_context = null)
{
    if (is_array($value)) {
        foreach ($value as $key => $inner_value) {
            $filtered_key = filter_block_kses_value($key, $allowed_html, $allowed_protocols, $block_context);
            $filtered_value = filter_block_kses_value($inner_value, $allowed_html, $allowed_protocols, $block_context);
            if ($filtered_key !== $key) {
                unset($value[$key]);
            }
            $value[$filtered_key] = $filtered_value;
        }
    } elseif (is_string($value)) {
        return wp_kses($value, $allowed_html, $allowed_protocols);
    }
    return $value;
}

function filter_block_content($text, $allowed_html = 'post', $allowed_protocols = [])
{
    $result = '';
    if (str_contains((string) $text, '<!--') && str_contains((string) $text, '--->')) {
        $text = preg_replace_callback('%<!--(.*?)--->%', '_filter_block_content_callback', (string) $text);
    }
    foreach (parse_blocks((string) $text) as $block) {
        $block = filter_block_kses($block, $allowed_html, $allowed_protocols);
        $result .= serialize_block($block);
    }
    return $result;
}

function _filter_block_content_callback($matches)
{
    return '<!--' . rtrim($matches[1], '-') . '-->';
}

/** Anchor block name => relative position => the block types registered to hook there. */
function get_hooked_blocks()
{
    $hooked = [];
    foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $type) {
        foreach ((array) ($type->block_hooks ?? []) as $anchor => $position) {
            $hooked[$anchor][$position][] = $type->name;
        }
    }
    return $hooked;
}

function wp_get_global_styles_svg_filters()
{
    return '';
}

function register_block_style($block_name, $style_properties)
{
    return WP_Block_Styles_Registry::get_instance()->register($block_name, $style_properties);
}

function unregister_block_style($block_name, $block_style_name)
{
    return WP_Block_Styles_Registry::get_instance()->unregister($block_name, $block_style_name);
}

function register_block_pattern($pattern_name, $pattern_properties)
{
    return WP_Block_Patterns_Registry::get_instance()->register($pattern_name, $pattern_properties);
}

function unregister_block_pattern($pattern_name)
{
    return WP_Block_Patterns_Registry::get_instance()->unregister($pattern_name);
}

function register_block_pattern_category($category_name, $category_properties)
{
    return WP_Block_Pattern_Categories_Registry::get_instance()->register($category_name, $category_properties);
}

function unregister_block_pattern_category($category_name)
{
    return WP_Block_Pattern_Categories_Registry::get_instance()->unregister($category_name);
}

function register_block_bindings_source($source_name, array $source_properties)
{
    $sources = Runtime::current()->get('block_bindings', []);
    $sources[$source_name] = $source_properties + ['name' => $source_name];
    Runtime::current()->set('block_bindings', $sources);
    return (object) $sources[$source_name];
}

function unregister_block_bindings_source($source_name)
{
    $sources = Runtime::current()->get('block_bindings', []);
    $removed = $sources[$source_name] ?? false;
    unset($sources[$source_name]);
    Runtime::current()->set('block_bindings', $sources);
    return $removed === false ? false : (object) $removed;
}

function get_all_registered_block_bindings_sources()
{
    return Runtime::current()->get('block_bindings', []);
}

function get_block_bindings_source($source_name)
{
    $sources = Runtime::current()->get('block_bindings', []);
    return isset($sources[$source_name]) ? (object) $sources[$source_name] : null;
}

function wp_interactivity_data_wp_context($context, $store_namespace = '')
{
    $json = $context === [] ? '{}' : wp_json_encode($context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return "data-wp-context='" . ($store_namespace !== '' ? $store_namespace . '::' : '') . $json . "'";
}

function wp_interactivity_state($store_namespace = null, $state = [])
{
    return wp_interactivity()->state($store_namespace, $state);
}

function wp_interactivity_config($store_namespace, $config = [])
{
    return wp_interactivity()->config($store_namespace, $config);
}

function wp_interactivity_process_directives($html)
{
    return wp_interactivity()->process_directives($html);
}

function wp_interactivity_get_context($store_namespace = null)
{
    return wp_interactivity()->get_context($store_namespace);
}

function wp_interactivity_get_element()
{
    return wp_interactivity()->get_element();
}

function wp_interactivity()
{
    return WP_Interactivity_API::instance();
}

function get_block_metadata_i18n_schema()
{
    return (object) ['title' => 'block title', 'description' => 'block description', 'keywords' => ['block keyword'], 'styles' => [['label' => 'block style label']], 'variations' => [['title' => 'block variation title', 'description' => 'block variation description', 'keywords' => ['block variation keyword']]]];
}

function register_block_script_handle($metadata, $field_name, $index = 0)
{
    if (empty($metadata[$field_name])) {
        return false;
    }
    $script_handle_or_path = $metadata[$field_name];
    if (is_array($script_handle_or_path)) {
        if (empty($script_handle_or_path[$index])) {
            return false;
        }
        $script_handle_or_path = $script_handle_or_path[$index];
    }
    $script_path = remove_block_asset_path_prefix($script_handle_or_path);
    if ($script_handle_or_path === $script_path) {
        return $script_handle_or_path;
    }
    $path = dirname($metadata['file']);
    $script_asset_raw_path = $path . '/' . substr_replace($script_path, '.asset.php', -strlen('.js'));
    $script_handle = generate_block_asset_handle($metadata['name'], $field_name, $index);
    $script_asset_path = wp_normalize_path(realpath($script_asset_raw_path) ?: $script_asset_raw_path);
    $script_path_norm = wp_normalize_path(realpath($path . '/' . $script_path) ?: $path . '/' . $script_path);
    $script_uri = plugins_url(str_replace(wp_normalize_path(WP_PLUGIN_DIR), '', $script_path_norm));
    $script_asset = file_exists($script_asset_path) ? require $script_asset_path : ['dependencies' => [], 'version' => false];
    $script_dependencies = $script_asset['dependencies'] ?? [];
    $result = wp_register_script($script_handle, $script_uri, $script_dependencies, $script_asset['version'] ?? false, ['in_footer' => $field_name === 'viewScript']);
    if (!$result) {
        return false;
    }
    if (!empty($metadata['textdomain']) && in_array('wp-i18n', $script_dependencies, true)) {
        wp_set_script_translations($script_handle, $metadata['textdomain']);
    }
    return $script_handle;
}

function register_block_style_handle($metadata, $field_name, $index = 0)
{
    if (empty($metadata[$field_name])) {
        return false;
    }
    $style_handle = $metadata[$field_name];
    if (is_array($style_handle)) {
        if (empty($style_handle[$index])) {
            return false;
        }
        $style_handle = $style_handle[$index];
    }
    $style_path = remove_block_asset_path_prefix($style_handle);
    if ($style_handle === $style_path) {
        return $style_handle;
    }
    $path = dirname($metadata['file']);
    $style_handle_name = generate_block_asset_handle($metadata['name'], $field_name, $index);
    $style_path_norm = wp_normalize_path(realpath($path . '/' . $style_path) ?: $path . '/' . $style_path);
    $style_uri = plugins_url(str_replace(wp_normalize_path(WP_PLUGIN_DIR), '', $style_path_norm));
    $version = !empty($metadata['version']) ? $metadata['version'] : false;
    $result = wp_register_style($style_handle_name, $style_uri, [], $version);
    return $result ? $style_handle_name : false;
}

function remove_block_asset_path_prefix($asset_handle_or_path)
{
    if (!str_starts_with((string) $asset_handle_or_path, 'file:')) {
        return $asset_handle_or_path;
    }
    $path = substr((string) $asset_handle_or_path, strlen('file:'));
    if (str_starts_with($path, './')) {
        $path = substr($path, 2);
    }
    return $path;
}

/** The id a block.json script module field registers under, registering it from the file and its .asset.php on the way. */
function register_block_script_module_id($metadata, $field_name, $index = 0)
{
    if (empty($metadata[$field_name])) {
        return false;
    }
    $value = $metadata[$field_name];
    if (is_array($value)) {
        $value = $value[$index] ?? '';
    }
    $value = (string) $value;
    if (!str_starts_with($value, 'file:')) {
        return $value;
    }
    $path = dirname((string) ($metadata['file'] ?? ''));
    $relative = remove_block_asset_path_prefix($value);
    $id = generate_block_asset_handle($metadata['name'], $field_name, $index);
    $asset_raw = $path . '/' . substr_replace($relative, '.asset.php', -strlen('.js'));
    $asset_path = wp_normalize_path(realpath($asset_raw) ?: $asset_raw);
    $module_path = wp_normalize_path(realpath($path . '/' . $relative) ?: $path . '/' . $relative);
    $uri = str_starts_with($module_path, wp_normalize_path(WP_PLUGIN_DIR)) ? plugins_url(str_replace(wp_normalize_path(WP_PLUGIN_DIR), '', $module_path)) : str_replace(wp_normalize_path(ABSPATH), site_url('/'), $module_path);
    $asset = file_exists($asset_path) ? require $asset_path : ['dependencies' => [], 'version' => false];
    wp_register_script_module($id, $uri, $asset['dependencies'] ?? [], $asset['version'] ?? false);
    return $id;
}

function generate_block_asset_handle($block_name, $field_name, $index = 0)
{
    return BlockMetadata::assetHandle((string) $block_name, (string) $field_name, (int) $index);
}

function register_block_type_from_metadata($file_or_folder, $args = [])
{
    $file_or_folder = (string) $file_or_folder;
    $metadata_file = !str_ends_with($file_or_folder, 'block.json') ? trailingslashit($file_or_folder) . 'block.json' : $file_or_folder;
    if (!file_exists($metadata_file)) {
        return false;
    }
    $metadata = wp_json_file_decode($metadata_file, ['associative' => true]);
    if (!is_array($metadata) || empty($metadata['name'])) {
        return false;
    }
    $metadata['file'] = wp_normalize_path(realpath($metadata_file));
    $metadata = apply_filters('block_type_metadata', $metadata);
    $settings = BlockMetadata::settings(
        $metadata,
        static fn (array $meta, string $field, int $index) => register_block_script_handle($meta, $field, $index),
        static fn (array $meta, string $field, int $index) => register_block_style_handle($meta, $field, $index),
        static fn (array $meta, string $field, int $index) => register_block_script_module_id($meta, $field, $index),
        static function (string $render) use ($metadata): ?Closure {
            $path = wp_normalize_path(realpath(dirname($metadata['file']) . '/' . remove_block_asset_path_prefix($render)) ?: '');
            if ($path === '' || !is_file($path)) {
                return null;
            }
            return static function ($attributes, $content, $block) use ($path) {
                ob_start();
                require $path;
                return (string) ob_get_clean();
            };
        },
    );
    $settings = apply_filters('block_type_metadata_settings', array_merge($settings, (array) $args), $metadata);
    return WP_Block_Type_Registry::get_instance()->register($settings['name'] ?? $metadata['name'], $settings);
}

function wp_register_block_metadata_collection($path, $manifest)
{
}

function wp_register_block_types_from_metadata_collection($path, $manifest = '')
{
}

function wp_render_layout_support_flag($block_content, $block)
{
    return $block_content;
}

function wp_migrate_old_typography_shape($metadata)
{
    return $metadata;
}

/** The hooked block types for an anchor and position, as the filter leaves them. */
function _minn_hooked_block_types(array $anchor, string $position, array $hooked_blocks, $context): array
{
    $name = (string) ($anchor['blockName'] ?? '');
    $types = $hooked_blocks[$name][$position] ?? [];
    return (array) apply_filters('hooked_block_types', $types, $position, $name, $context);
}

/** Serialized markup for the blocks hooked to an anchor at a position, skipping any the anchor lists as ignored. */
function insert_hooked_blocks(&$parsed_anchor_block, $relative_position, $hooked_blocks, $context)
{
    $markup = '';
    $ignored = (array) ($parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks'] ?? []);
    foreach (_minn_hooked_block_types($parsed_anchor_block, (string) $relative_position, $hooked_blocks, $context) as $type) {
        if (in_array($type, $ignored, true)) {
            continue;
        }
        $parsed = ['blockName' => $type, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
        $parsed = apply_filters('hooked_block', $parsed, $type, $relative_position, $parsed_anchor_block, $context);
        $parsed = apply_filters("hooked_block_{$type}", $parsed, $type, $relative_position, $parsed_anchor_block, $context);
        if ($parsed === null) {
            continue;
        }
        $markup .= serialize_block($parsed);
    }
    return $markup;
}

/** Records the hooked types on the anchor's ignoredHookedBlocks metadata; nothing is inserted. */
function set_ignored_hooked_blocks_metadata(&$parsed_anchor_block, $relative_position, $hooked_blocks, $context)
{
    $types = _minn_hooked_block_types($parsed_anchor_block, (string) $relative_position, $hooked_blocks, $context);
    if ($types === []) {
        return '';
    }
    $ignored = (array) ($parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks'] ?? []);
    $parsed_anchor_block['attrs']['metadata']['ignoredHookedBlocks'] = array_values(array_unique(array_merge($ignored, $types)));
    return '';
}

/**
 * The inside of a serialized block: everything between the first opening
 * delimiter and the last closing one. Offsets only, no parse, so a string
 * that is not a block comes back sliced the same way the reference slices
 * it (both offsets fall back to 0/3).
 */
function remove_serialized_parent_block($serialized_block)
{
    $serialized_block = (string) $serialized_block;
    $start = (int) strpos($serialized_block, '-->') + 3;
    $end = (int) strrpos($serialized_block, '<!--');
    return substr($serialized_block, $start, $end - $start);
}

/** The reverse slice: the opening delimiter plus everything from the last one. */
function extract_serialized_parent_block($serialized_block)
{
    $serialized_block = (string) $serialized_block;
    $start = (int) strpos($serialized_block, '-->') + 3;
    $end = (int) strrpos($serialized_block, '<!--');
    return substr($serialized_block, 0, $start) . substr($serialized_block, $end);
}

/**
 * A template part's content with its hooked blocks applied. The content is
 * wrapped in a virtual core/template-part block first so the part's own
 * first_child and last_child positions have an anchor, then unwrapped:
 * that wrapper is why a part fires six positions, not two.
 */
function _minn_apply_hooks_to_template_part(string $content, $context): string
{
    $hooked = get_hooked_blocks();
    if ($hooked === [] && !has_filter('hooked_block_types')) {
        return $content;
    }
    $wrapped = get_comment_delimited_block_content('core/template-part', [], $content);
    $wrapped = apply_block_hooks_to_content($wrapped, $context, 'insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata');
    return remove_serialized_parent_block($wrapped);
}

/**
 * Inserts the hooked blocks AND stamps the anchor's ignoredHookedBlocks in
 * one pass: the insert runs first (so it still sees the anchor's own
 * ignores), then the metadata records what was inserted. Each position
 * therefore fires hooked_block_types twice, consecutively.
 */
function insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata(&$parsed_anchor_block, $relative_position, $hooked_blocks, $context)
{
    $markup = insert_hooked_blocks($parsed_anchor_block, $relative_position, $hooked_blocks, $context);
    set_ignored_hooked_blocks_metadata($parsed_anchor_block, $relative_position, $hooked_blocks, $context);
    return $markup;
}

function make_before_block_visitor($hooked_blocks, $context, $callback = 'insert_hooked_blocks')
{
    return static function (&$block, &$parent_block = null, $prev = null) use ($hooked_blocks, $context, $callback) {
        $markup = '';
        if ($parent_block && !$prev) {
            $markup .= $callback($parent_block, 'first_child', $hooked_blocks, $context);
        }
        $markup .= $callback($block, 'before', $hooked_blocks, $context);
        return $markup;
    };
}

function make_after_block_visitor($hooked_blocks, $context, $callback = 'insert_hooked_blocks')
{
    return static function (&$block, &$parent_block = null, $next = null) use ($hooked_blocks, $context, $callback) {
        $markup = $callback($block, 'after', $hooked_blocks, $context);
        if ($parent_block && !$next) {
            $markup .= $callback($parent_block, 'last_child', $hooked_blocks, $context);
        }
        return $markup;
    };
}

/** Serializes one block, calling the visitors around each inner block. */
function traverse_and_serialize_block($block, $pre_callback = null, $post_callback = null)
{
    $parent = null;
    return _minn_traverse_block($block, $pre_callback, $post_callback, $parent);
}

/**
 * @internal
 * BOTH visitors run before the block is serialized: a visitor at the
 * `after` position still mutates the anchor's attributes (the metadata
 * pass stamps ignoredHookedBlocks there), and serializing in between
 * would freeze the block before that mutation landed.
 */
function _minn_traverse_block(array &$block, $pre, $post, ?array &$parent): string
{
    $content = '';
    $index = 0;
    $count = count($block['innerBlocks'] ?? []);
    foreach ((array) ($block['innerContent'] ?? []) as $chunk) {
        if (is_string($chunk)) {
            $content .= $chunk;
            continue;
        }
        $inner = &$block['innerBlocks'][$index];
        $prev = $index > 0 ? $block['innerBlocks'][$index - 1] : null;
        $next = $index + 1 < $count ? $block['innerBlocks'][$index + 1] : null;
        $before = $pre !== null ? (string) $pre($inner, $block, $prev) : '';
        $after = $post !== null ? (string) $post($inner, $block, $next) : '';
        $content .= $before . _minn_traverse_block($inner, $pre, $post, $block) . $after;
        unset($inner);
        $index++;
    }
    return get_comment_delimited_block_content($block['blockName'] ?? null, $block['attrs'] ?? [], $content);
}

/** Serializes a list of blocks, calling the visitors before and after each. */
function traverse_and_serialize_blocks($blocks, $pre_callback = null, $post_callback = null)
{
    $result = '';
    $parent = null;
    $count = count($blocks);
    foreach (array_keys($blocks) as $i) {
        $block = &$blocks[$i];
        $prev = $i > 0 ? $blocks[$i - 1] : null;
        $next = $i + 1 < $count ? $blocks[$i + 1] : null;
        // Both visitors first, then serialize (see _minn_traverse_block).
        $before = $pre_callback !== null ? (string) $pre_callback($block, $parent, $prev) : '';
        $after = $post_callback !== null ? (string) $post_callback($block, $parent, $next) : '';
        $result .= $before . _minn_traverse_block($block, $pre_callback, $post_callback, $parent) . $after;
        unset($block);
    }
    return $result;
}

/** Content with its hooked blocks inserted; untouched when nothing hooks anywhere. */
function apply_block_hooks_to_content($content, $context = null, $callback = 'insert_hooked_blocks')
{
    $hooked_blocks = get_hooked_blocks();
    if ($hooked_blocks === [] && !has_filter('hooked_block_types')) {
        return $content;
    }
    $blocks = parse_blocks((string) $content);
    return traverse_and_serialize_blocks($blocks, make_before_block_visitor($hooked_blocks, $context, $callback), make_after_block_visitor($hooked_blocks, $context, $callback));
}

/** A template part by its attributes, through the engine's template-part block. */
function render_block_core_template_part($attributes)
{
    $html = render_block(['blockName' => 'core/template-part', 'attrs' => (array) $attributes, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
    // Outside a block render the reference adds no block-support class to the wrapper (it prints "<header >").
    return preg_replace_callback('/^(<\w+)([^>]*?)\sclass="([^"]*)"/', static function (array $m): string {
        $classes = array_values(array_diff(preg_split('/\s+/', trim($m[3]), -1, PREG_SPLIT_NO_EMPTY) ?: [], ['wp-block-template-part']));
        return $m[1] . $m[2] . ' ' . ($classes === [] ? '' : 'class="' . implode(' ', $classes) . '"');
    }, $html, 1);
}

/** The WP_Query vars a query loop's context asks for on a page of it. */
function build_query_vars_from_query_block($block, $page)
{
    $context = $block->context['query'] ?? null;
    $sticky = array_map('intval', (array) get_option('sticky_posts', []));
    $query = QueryVars::fromContext(is_array($context) ? $context : null, (int) $page, $sticky, static fn (string $type) => post_type_exists($type), static fn (string $taxonomy) => is_taxonomy_viewable($taxonomy));
    return is_array($context) ? apply_filters('query_loop_block_query_vars', $query, $block, $page) : $query;
}

/** @internal the sticky setting: "only" lists the sticky posts, "exclude" keeps them out */

/** @internal the tax_query a query loop's taxonomy and format settings become */

/** The comment query vars a comment template's context asks for. */
function build_comment_query_vars_from_block($block)
{
    $vars = ['orderby' => 'comment_date_gmt', 'order' => 'ASC', 'status' => 'approve', 'no_found_rows' => false];
    if (is_user_logged_in()) {
        $vars['include_unapproved'] = [get_current_user_id()];
    } else {
        $commenter = wp_get_current_commenter();
        if (!empty($commenter['comment_author_email'])) {
            $vars['include_unapproved'] = [$commenter['comment_author_email']];
        }
    }
    if (!empty($block->context['postId'])) {
        $vars['post_id'] = (int) $block->context['postId'];
    }
    $vars['hierarchical'] = get_option('thread_comments') ? 'threaded' : false;
    if (get_option('page_comments') && (int) get_option('comments_per_page') > 0) {
        $per_page = (int) get_option('comments_per_page');
        $vars['number'] = $per_page;
        $page = (int) get_query_var('cpage');
        if ($page > 0) {
            $vars['paged'] = $page;
        } elseif (get_option('default_comments_page') === 'oldest') {
            $vars['paged'] = 1;
        } elseif (!empty($vars['post_id'])) {
            $count = (int) get_comments(['post_id' => $vars['post_id'], 'status' => 'approve', 'count' => true]);
            $vars['paged'] = max(1, (int) ceil($count / $per_page));
        }
    }
    return $vars;
}

/** @internal a WP_Block_Template for a template a plugin registered */
function _minn_registered_block_template(array $row): WP_Block_Template
{
    $template = new WP_Block_Template();
    $template->type = 'wp_template';
    $template->theme = get_stylesheet();
    $template->slug = $row['slug'];
    $template->id = get_stylesheet() . '//' . $row['slug'];
    $template->title = $row['title'];
    $template->content = $row['content'];
    $template->description = $row['description'];
    $template->source = 'plugin';
    $template->origin = 'plugin';
    $template->status = 'publish';
    $template->is_custom = true;
    $template->plugin = $row['plugin'];
    $template->post_types = $row['post_types'];
    return $template;
}

/** @internal a WP_Block_Template for a theme's file, or null when the theme has none for the slug */
function _minn_theme_block_template(string $slug, string $type): ?WP_Block_Template
{
    $theme = Runtime::current()->get('theme');
    if ($theme === null) {
        return null;
    }
    $content = $type === 'wp_template_part' ? $theme->partFile($slug) : $theme->templateFile($slug);
    if ($content === null) {
        return null;
    }
    $template = new WP_Block_Template();
    $template->type = $type;
    $template->theme = get_stylesheet();
    $template->slug = $slug;
    $template->id = get_stylesheet() . '//' . $slug;
    $template->content = $content;
    $template->source = 'theme';
    $template->status = 'publish';
    $template->has_theme_file = true;
    $template->is_custom = false;
    $template->title = _minn_block_template_title($slug, $type, $theme);
    $template->area = $type === 'wp_template_part' ? $theme->partArea($slug) : null;
    return $template;
}

/** @internal the theme.json title for a part or custom template, else the reference's name for the slug */
function _minn_block_template_title(string $slug, string $type, $theme): string
{
    $json = $theme->json();
    foreach ((array) ($json[$type === 'wp_template_part' ? 'templateParts' : 'customTemplates'] ?? []) as $entry) {
        if (($entry['name'] ?? '') === $slug && !empty($entry['title'])) {
            return (string) $entry['title'];
        }
    }
    $titles = ['index' => 'Index', 'home' => 'Blog Home', 'front-page' => 'Front Page', 'singular' => 'Single Entries', 'single' => 'Single Posts', 'page' => 'Pages', 'archive' => 'All Archives', 'author' => 'Author Archives', 'category' => 'Category Archives', 'taxonomy' => 'Taxonomy', 'date' => 'Date Archives', 'tag' => 'Tag Archives', 'attachment' => 'Attachment Pages', 'search' => 'Search Results', 'privacy-policy' => 'Privacy Policy', '404' => 'Page: 404', 'header' => 'Header', 'footer' => 'Footer', 'sidebar' => 'Sidebar', 'comments' => 'Comments'];
    return $titles[$slug] ?? ucwords(str_replace(['-', '_'], ' ', $slug));
}

/** @internal the slugs the theme (and its parent) ship files for */
function _minn_theme_block_template_slugs(string $type): array
{
    $folder = $type === 'wp_template_part' ? 'parts' : 'templates';
    $slugs = [];
    foreach (array_unique([get_stylesheet_directory(), get_template_directory()]) as $dir) {
        foreach (glob("{$dir}/{$folder}/*.html") ?: [] as $file) {
            $slugs[] = basename($file, '.html');
        }
    }
    return array_values(array_unique($slugs));
}

/** Registers a plugin's template; a WP_Error names the reference's refusal. */
function register_block_template($template_name, $args = [])
{
    return WP_Block_Templates_Registry::get_instance()->register($template_name, $args);
}

function unregister_block_template($template_name)
{
    return WP_Block_Templates_Registry::get_instance()->unregister($template_name);
}

/** Theme files (and registered plugin templates, unless a post_type query without slugs) matching the query. */
function get_block_templates($query = [], $template_type = 'wp_template')
{
    $templates = [];
    foreach (_minn_theme_block_template_slugs($template_type) as $slug) {
        $template = _minn_theme_block_template($slug, $template_type);
        if ($template !== null && _minn_block_template_matches($template, $query)) {
            $templates[] = $template;
        }
    }
    $themeSlugs = array_map(static fn ($t) => $t->slug, $templates);
    if ($template_type === 'wp_template' && (empty($query['post_type']) || !empty($query['slug__in']))) {
        foreach (WP_Block_Templates_Registry::get_instance()->get_by_query($query) as $name => $template) {
            if (!in_array($template->slug, $themeSlugs, true) && _minn_block_template_matches($template, $query)) {
                $templates[$name] = $template;
            }
        }
    }
    return apply_filters('get_block_templates', $templates, $query, $template_type);
}

/** @internal */
function _minn_block_template_matches(WP_Block_Template $template, array $query): bool
{
    if (!empty($query['slug__in']) && !in_array($template->slug, (array) $query['slug__in'], true)) {
        return false;
    }
    if (!empty($query['slug__not_in']) && in_array($template->slug, (array) $query['slug__not_in'], true)) {
        return false;
    }
    if (!empty($query['area']) && $template->area !== $query['area']) {
        return false;
    }
    if (isset($query['wp_id']) && (int) $template->wp_id !== (int) $query['wp_id']) {
        return false;
    }
    return true;
}

/** A template by "theme//slug": the theme's file, else a plugin's registration under the active theme. */
function get_block_template($id, $template_type = 'wp_template')
{
    $parts = explode('//', (string) $id, 2);
    if (count($parts) < 2) {
        return null;
    }
    [$theme, $slug] = $parts;
    $template = null;
    if ($theme === get_stylesheet()) {
        $template = _minn_theme_block_template($slug, $template_type);
        if ($template === null && $template_type === 'wp_template') {
            $template = WP_Block_Templates_Registry::get_instance()->get_by_slug($slug);
        }
    } elseif ($template_type === 'wp_template') {
        // A plugin's own "plugin//slug" name resolves to its registration.
        $template = WP_Block_Templates_Registry::get_instance()->get_registered((string) $id);
    }
    if ($template instanceof WP_Block_Template && $template_type === 'wp_template_part' && is_string($template->content)) {
        $template->content = _minn_apply_hooks_to_template_part($template->content, $template);
    }
    return apply_filters('get_block_template', $template, $id, $template_type);
}

/** True when any inner block (however deep) shows the featured image: the block itself, or a cover set to use it. */
function block_core_post_template_uses_featured_image($inner_blocks)
{
    foreach ($inner_blocks as $block) {
        $parsed = $block instanceof WP_Block ? $block->parsed_block : (array) $block;
        $name = (string) ($parsed['blockName'] ?? '');
        if ($name === 'core/post-featured-image' || ($name === 'core/cover' && !empty($parsed['attrs']['useFeaturedImage']))) {
            return true;
        }
        if (!empty($parsed['innerBlocks']) && block_core_post_template_uses_featured_image($parsed['innerBlocks'])) {
            return true;
        }
    }
    return false;
}
