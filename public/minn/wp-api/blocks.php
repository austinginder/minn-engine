<?php
/** The block API: parsing, serializing, registration, rendering through plugin callbacks. Behaviour from contracts/fixtures/api/blocks.json. */

use Minn\Blocks\Block as MinnBlock;
use Minn\Blocks\Parser;
use Minn\Content\Blocks as MinnBlocks;
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
    return MinnBlocks::renderer()->renderBlock(_minn_array_to_block($block->parsed_block));
}

/** @internal a dynamic block a plugin registered renders through its callback on the engine's front end too */
function _minn_bridge_dynamic_block(string $name): void
{
    MinnBlocks::renderer()->registerDynamic($name, static function (MinnBlock $block): string {
        return render_block(_minn_block_to_array($block));
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
        if ($wp_post instanceof WP_Post) {
            $post = $wp_post->post_content;
        }
    }
    if (!str_contains((string) $block_name, '/')) {
        $block_name = 'core/' . $block_name;
    }
    if (str_starts_with((string) $block_name, 'core/')) {
        $block_name = substr((string) $block_name, 5);
        if (str_contains((string) $post, '<!-- wp:' . $block_name . ' ') || str_contains((string) $post, '<!-- wp:' . $block_name . ' /-->') || str_contains((string) $post, '<!-- wp:' . $block_name . ' -->') || str_contains((string) $post, '<!-- wp:' . $block_name . '/-->')) {
            return true;
        }
        $block_name = 'core/' . $block_name;
    }
    return str_contains((string) $post, '<!-- wp:' . $block_name . ' ') || str_contains((string) $post, '<!-- wp:' . $block_name . ' /-->') || str_contains((string) $post, '<!-- wp:' . $block_name . ' -->') || str_contains((string) $post, '<!-- wp:' . $block_name . '/-->');
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
    $has_selectors = !empty($block_type->selectors);
    $root_selector = null;
    if ($has_selectors && isset($block_type->selectors['root'])) {
        $root_selector = $block_type->selectors['root'];
    } elseif (isset($block_type->supports['__experimentalSelector']) && is_string($block_type->supports['__experimentalSelector'])) {
        $root_selector = $block_type->supports['__experimentalSelector'];
    }
    if ($root_selector === null) {
        $root_selector = '.' . wp_get_block_default_classname($block_type->name);
    }
    if ($target === 'root' || $target === '' || $target === null) {
        return $root_selector;
    }
    $target_path = is_string($target) ? explode('.', $target) : (array) $target;
    if ($has_selectors) {
        $selector = _wp_array_get($block_type->selectors, $target_path, null);
        if (is_string($selector)) {
            return $selector;
        }
        if (is_array($selector) && isset($selector['root'])) {
            return $selector['root'];
        }
    }
    $feature = $target_path[0] ?? '';
    if ($feature !== '' && isset($block_type->supports[$feature]['__experimentalSelector']) && is_string($block_type->supports[$feature]['__experimentalSelector'])) {
        return $block_type->supports[$feature]['__experimentalSelector'];
    }
    return $fallback ? $root_selector : null;
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

function get_hooked_blocks()
{
    return [];
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
    if (str_starts_with((string) $block_name, 'core/')) {
        $asset_handle = str_replace('core/', 'wp-block-', (string) $block_name);
        if (str_starts_with($field_name, 'editor')) {
            $asset_handle .= '-editor';
        }
        if (str_starts_with($field_name, 'view')) {
            $asset_handle .= '-view';
        }
        if (str_ends_with(strtolower($field_name), 'scriptmodule')) {
            $asset_handle .= '-script-module';
        }
        if ($index > 0) {
            $asset_handle .= '-' . ($index + 1);
        }
        return $asset_handle;
    }
    $field_mappings = ['editorScript' => 'editor-script', 'editorStyle' => 'editor-style', 'script' => 'script', 'style' => 'style', 'viewScript' => 'view-script', 'viewScriptModule' => 'view-script-module', 'viewStyle' => 'view-style'];
    $asset_handle = str_replace('/', '-', (string) $block_name) . '-' . $field_mappings[$field_name];
    if ($index > 0) {
        $asset_handle .= '-' . ($index + 1);
    }
    return $asset_handle;
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
    // block.json's textdomain feeds translation only; the registered type carries none unless given as an argument.
    $textdomain = $metadata['textdomain'] ?? null;
    $settings = [];
    $property_mappings = ['apiVersion' => 'api_version', 'name' => 'name', 'title' => 'title', 'category' => 'category', 'parent' => 'parent', 'ancestor' => 'ancestor', 'icon' => 'icon', 'description' => 'description', 'keywords' => 'keywords', 'attributes' => 'attributes', 'providesContext' => 'provides_context', 'usesContext' => 'uses_context', 'selectors' => 'selectors', 'supports' => 'supports', 'styles' => 'styles', 'variations' => 'variations', 'example' => 'example', 'allowedBlocks' => 'allowed_blocks'];
    foreach ($property_mappings as $key => $mapped_key) {
        if (isset($metadata[$key])) {
            $settings[$mapped_key] = $metadata[$key];
        }
    }
    $script_fields = ['editorScript' => 'editor_script_handles', 'script' => 'script_handles', 'viewScript' => 'view_script_handles'];
    foreach ($script_fields as $metadata_field_name => $settings_field_name) {
        if (!empty($settings[$metadata_field_name])) {
            $metadata[$metadata_field_name] = $settings[$metadata_field_name];
        }
        if (!empty($metadata[$metadata_field_name])) {
            $scripts = $metadata[$metadata_field_name];
            $processed = [];
            if (is_array($scripts)) {
                for ($index = 0; $index < count($scripts); $index++) {
                    $result = register_block_script_handle($metadata, $metadata_field_name, $index);
                    if ($result) {
                        $processed[] = $result;
                    }
                }
            } else {
                $result = register_block_script_handle($metadata, $metadata_field_name);
                if ($result) {
                    $processed[] = $result;
                }
            }
            $settings[$settings_field_name] = $processed;
        }
    }
    if (!empty($metadata['viewScriptModule'])) {
        $ids = [];
        foreach (array_values((array) $metadata['viewScriptModule']) as $index => $value) {
            $id = register_block_script_module_id($metadata + ['viewScriptModule' => $value], 'viewScriptModule', $index);
            if ($id !== false) {
                $ids[] = $id;
            }
        }
        $settings['view_script_module_ids'] = $ids;
    }
    $style_fields = ['editorStyle' => 'editor_style_handles', 'style' => 'style_handles', 'viewStyle' => 'view_style_handles'];
    foreach ($style_fields as $metadata_field_name => $settings_field_name) {
        if (!empty($settings[$metadata_field_name])) {
            $metadata[$metadata_field_name] = $settings[$metadata_field_name];
        }
        if (!empty($metadata[$metadata_field_name])) {
            $styles = $metadata[$metadata_field_name];
            $processed = [];
            if (is_array($styles)) {
                for ($index = 0; $index < count($styles); $index++) {
                    $result = register_block_style_handle($metadata, $metadata_field_name, $index);
                    if ($result) {
                        $processed[] = $result;
                    }
                }
            } else {
                $result = register_block_style_handle($metadata, $metadata_field_name);
                if ($result) {
                    $processed[] = $result;
                }
            }
            $settings[$settings_field_name] = $processed;
        }
    }
    if (!empty($metadata['blockHooks'])) {
        $position_mappings = ['before' => 'before', 'after' => 'after', 'firstChild' => 'first_child', 'lastChild' => 'last_child'];
        $settings['block_hooks'] = [];
        foreach ($metadata['blockHooks'] as $anchor_block_name => $position) {
            if (!isset($position_mappings[$position])) {
                continue;
            }
            $settings['block_hooks'][$anchor_block_name] = $position_mappings[$position];
        }
    }
    if (!empty($metadata['render'])) {
        $template_path = wp_normalize_path(realpath(dirname($metadata['file']) . '/' . remove_block_asset_path_prefix($metadata['render'])) ?: '');
        if ($template_path !== '' && is_file($template_path)) {
            $settings['render_callback'] = static function ($attributes, $content, $block) use ($template_path) {
                ob_start();
                require $template_path;
                return (string) ob_get_clean();
            };
        }
    }
    $settings = array_merge($settings, (array) $args);
    $settings = apply_filters('block_type_metadata_settings', $settings, $metadata);
    $metadata['name'] = $settings['name'] ?? $metadata['name'];
    return WP_Block_Type_Registry::get_instance()->register($metadata['name'], $settings);
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
