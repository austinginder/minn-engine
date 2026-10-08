<?php
/** The block API: parsing, serializing, registration, rendering through plugin callbacks. Behaviour from contracts/fixtures/api/blocks.json. */

use Minn\Blocks\TemplatePartVariations;
use Minn\Theme\TemplateHierarchy;
use Minn\Blocks\Block as MinnBlock;
use Minn\Blocks\Parser;
use Minn\Blocks\QueryVars;
use Minn\Blocks\RenderState;
use Minn\Blocks\Selector;
use Minn\Blocks\Serializer;
use Minn\Support\Kses;
use Minn\Content\Blocks as MinnBlocks;
use Minn\Runtime\BlockFilters;
use Minn\Runtime\BlockMetadata;
use Minn\Runtime\Runtime;

/** @internal the engine's block value objects as the arrays plugin code reads */
function _minn_block_to_array(MinnBlock $block): array
{
    return $block->toArray();
}

/** @internal the reverse: a parsed array as the engine's value object */
function _minn_array_to_block(array $block): MinnBlock
{
    return MinnBlock::fromArray($block);
}

/** @internal the core block types' registration rows (data/blocks.json): the reference's settings, its render callback by name */
function _minn_core_block_rows(): array
{
    static $rows = null;
    return $rows ??= json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/blocks.json'), true) ?: [];
}

/** @internal a core block type as the engine registers it; null for a name core does not have */
function _minn_core_block_type(string $name): ?WP_Block_Type
{
    $row = _minn_core_block_rows()[$name] ?? null;
    if ($row === null) {
        return null;
    }
    unset($row['name']);
    $type = new WP_Block_Type($name);
    foreach ($row as $key => $value) {
        $type->{$key} = $value;
    }
    if ($name === 'core/template-part') {
        // Its variations are the active theme's areas and parts, built when first asked for.
        $type->variations = null;
        $type->variation_callback = 'build_template_part_block_variations';
    }
    return $type;
}

/**
 * @internal whether a core block keeps its own render callback, which the
 * engine's renderer answers natively; core/rss is the exception, its
 * callback being the renderer (it is bridged on init)
 */
function _minn_core_renders_natively(string $name, $callback): bool
{
    return $name !== 'core/rss' && is_string($callback) && $callback === (_minn_core_block_rows()[$name]['render_callback'] ?? null);
}

/**
 * @internal what a core block's render callback gives when called by name:
 * the engine renders the block whole, from the attributes given and the
 * block instance's other parts and context (the saved markup given, when
 * there is no instance). The reference's callback leaves its supports'
 * classes to render_block's filters, and called outside a render it has
 * none; the engine's output has them (probe core-blocks compares the text
 * and the tags).
 */
function _minn_render_core_callback(string $name, $attributes, $content = '', $block = null): string
{
    if ($block instanceof WP_Block && $attributes === $block->attributes) {
        return _minn_render_core_block($block);
    }
    $parsed = $block instanceof WP_Block ? $block->parsed_block : ['blockName' => $name, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => (string) $content, 'innerContent' => [(string) $content]];
    $parsed['attrs'] = (array) $attributes;
    return _minn_render_core_block(new WP_Block($parsed, $block instanceof WP_Block ? (array) $block->context : []));
}

/** @internal registers a core block type again, as its register_block_core_* function does: refused while it is registered */
function _minn_register_core_block(string $name): void
{
    $type = _minn_core_block_type($name);
    if ($type !== null) {
        WP_Block_Type_Registry::get_instance()->register($type);
    }
}

/** @internal a core block rendered by the engine's own renderer, with the wrapper classes the engine gives it */
function _minn_render_core_block(WP_Block $block, string $content = '', bool $filtered = true): string
{
    // A plugin's query loop hands the engine its per-item post (and a
    // comment loop its comment) through the block context; the engine's own
    // renderer reads its post stack and comment scope, and the rest of the
    // context the block uses (a query, a term) as provided context.
    $renderer = MinnBlocks::renderer();
    $context = (array) $block->context;
    $contextPost = empty($context['postId']) ? null : _minn_posts()->find((int) $context['postId']);
    // A callback given no instance reads the post being shown (the global one), as the reference's do.
    if ($contextPost === null && $renderer->context()->post() === null && ($GLOBALS['post'] ?? null) instanceof WP_Post) {
        $contextPost = _minn_posts()->find((int) $GLOBALS['post']->ID);
    }
    $comment = empty($context['commentId']) ? null : get_comment((int) $context['commentId']);
    $outerComment = $renderer->context()->comment();
    if ($contextPost !== null) {
        $renderer->context()->pushPost($contextPost);
    }
    if ($comment instanceof WP_Comment) {
        $renderer->context()->withComment(Minn\Content\CommentRecord::fromRow(get_object_vars($comment)));
    }
    try {
        $provided = array_diff_key($context, ['postId' => true, 'postType' => true, 'commentId' => true]);
        $native = _minn_array_to_block($block->parsed_block);
        return $renderer->providing($provided, static fn (): string => $filtered ? $renderer->renderNative($native) : $renderer->renderNativeUnfiltered($native));
    } finally {
        $renderer->context()->withComment($outerComment);
        if ($contextPost !== null) {
            $renderer->context()->popPost();
        }
    }
}

/** @internal a dynamic block a plugin registered renders through its callback on the engine's front end too */
function _minn_bridge_dynamic_block(string $name): void
{
    MinnBlocks::renderer()->bridge($name, static function (MinnBlock $block): string {
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

/** The blocks of a document, by the parser block_parser_class names (WP_Block_Parser unless a theme or plugin swaps its own in). */
function parse_blocks($content)
{
    $class = apply_filters('block_parser_class', 'WP_Block_Parser');
    $parser = is_string($class) && class_exists($class) ? new $class() : new WP_Block_Parser();
    return $parser->parse((string) $content);
}

/**
 * A parsed block tree with every core/pattern block replaced by the blocks
 * of the pattern it names, all the way down. A block whose slug names no
 * pattern, carries no slug, or would reopen a pattern already being
 * resolved is left exactly as it was written.
 *
 * @param array $blocks parsed blocks, as parse_blocks() returns them
 */
function resolve_pattern_blocks($blocks)
{
    return _minn_resolve_pattern_blocks((array) $blocks, []);
}

/** @internal the recursion itself, carrying the pattern slugs already open */
function _minn_resolve_pattern_blocks(array $blocks, array $open): array
{
    $out = [];
    foreach ((array) $blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['blockName'] ?? '') === 'core/pattern') {
            $slug = (string) ($block['attrs']['slug'] ?? '');
            $markup = isset($open[$slug]) ? null : _minn_pattern_markup($slug);
            if ($markup === null) {
                $out[] = $block;
                continue;
            }
            $open[$slug] = true;
            foreach (_minn_resolve_pattern_blocks(parse_blocks($markup), $open) as $resolved) {
                $out[] = $resolved;
            }
            unset($open[$slug]);
            continue;
        }
        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = _minn_resolve_pattern_blocks($block['innerBlocks'], $open);
        }
        $out[] = $block;
    }
    return $out;
}

/** @internal a pattern's markup: the active theme's file first, then anything registered, else null */
function _minn_pattern_markup(string $slug): ?string
{
    if ($slug === '' || !Runtime::booted()) {
        return null;
    }
    $runtime = Runtime::current();
    $theme = Minn\Theme\Theme::forStyles(
        new Minn\Content\Site($runtime->db),
        Minn\Front\Permalinks::fromDb($runtime->db),
        ABSPATH . 'wp-content/themes',
    );
    $markup = $theme?->pattern($slug) ?? Minn\Runtime\BlockHooks::registeredPattern($slug);
    return $markup === '' ? null : $markup;
}

function serialize_block_attributes($block_attributes)
{
    return Serializer::attributes((array) $block_attributes);
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

function wp_apply_generated_classname_support($block_type)
{
    if (!$block_type instanceof WP_Block_Type || !block_has_support($block_type, 'className', true)) {
        return [];
    }
    $class = wp_get_block_default_classname($block_type->name);
    return $class === '' ? [] : ['class' => $class];
}

function wp_get_block_css_selector($block_type, $target = 'root', $fallback = false)
{
    if (!$block_type instanceof WP_Block_Type) {
        return null;
    }
    return Selector::resolve((array) ($block_type->selectors ?: []), (array) ($block_type->supports ?: []), $target, (bool) $fallback, wp_get_block_default_classname($block_type->name));
}

/**
 * The wrapper attributes of the block being rendered (probe block-supports):
 * the supports' classes after the caller's (each once), the supports'
 * styles before the caller's (split on semicolons, each part trimmed, the
 * ends trimmed of semicolons), and the caller's other attributes, the
 * caller's id and aria-label winning. Style, class, id and aria-label come
 * first, in that order.
 */
function get_block_wrapper_attributes($extra_attributes = [])
{
    $new_attributes = WP_Block_Supports::get_instance()->apply_block_supports();
    if (empty($new_attributes) && empty($extra_attributes)) {
        return '';
    }
    $extra_attributes = (array) $extra_attributes;
    $attributes = [];
    if (!empty($new_attributes['style']) || !empty($extra_attributes['style'])) {
        $style = (string) ($new_attributes['style'] ?? '') . (string) ($extra_attributes['style'] ?? '');
        $attributes['style'] = trim(implode(';', array_map('trim', explode(';', $style))), ';');
    }
    if (!empty($new_attributes['class']) || !empty($extra_attributes['class'])) {
        $classes = preg_split('/\s+/', trim(($extra_attributes['class'] ?? '') . ' ' . ($new_attributes['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $attributes['class'] = implode(' ', array_unique($classes));
    }
    unset($new_attributes['style'], $new_attributes['class'], $extra_attributes['style'], $extra_attributes['class']);
    foreach (['id', 'aria-label'] as $name) {
        $value = $extra_attributes[$name] ?? $new_attributes[$name] ?? null;
        if ($value !== null && $value !== '') {
            $attributes[$name] = $value;
        }
        unset($new_attributes[$name], $extra_attributes[$name]);
    }
    $normalized = [];
    foreach ($attributes + $new_attributes + $extra_attributes as $key => $value) {
        $normalized[] = $key . '="' . esc_attr((string) $value) . '"';
    }
    return implode(' ', $normalized);
}

/** @internal whether the engine's renderer answers for a block by name: a core block no plugin's callback took over */
function _minn_renders_natively($name): bool
{
    return is_string($name) && str_starts_with($name, 'core/') && MinnBlocks::renderer()->rendersNatively($name);
}

/**
 * A parsed block as HTML: pre_render_block may answer for it, then
 * render_block_data and render_block_context shape it (the block-support
 * filters the engine's renderer applies itself left out for a core block it
 * renders) before WP_Block renders it.
 */
function render_block($parsed_block)
{
    $pre_render = apply_filters('pre_render_block', null, $parsed_block, null);
    if ($pre_render !== null) {
        return $pre_render;
    }
    $parsed_block = BlockFilters::data((array) $parsed_block, null, _minn_renders_natively($parsed_block['blockName'] ?? null) ? BlockFilters::NATIVE_DATA_DONE : []);
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
    // Blocks bring their own paragraphs: wpautop sits out the rest of this
    // the_content run and comes back after it, at the end of its priority.
    $priority = has_filter('the_content', 'wpautop');
    if ($priority !== false && doing_filter('the_content') && has_blocks($content)) {
        remove_filter('the_content', 'wpautop', $priority);
        add_filter('the_content', '_restore_wpautop_hook', $priority + 1);
    }
    return $output;
}

/** Puts wpautop back on the_content after do_blocks took it off for one run. */
function _restore_wpautop_hook($content)
{
    $current = has_filter('the_content', '_restore_wpautop_hook');
    add_filter('the_content', 'wpautop', $current - 1);
    remove_filter('the_content', '_restore_wpautop_hook', $current);
    return $content;
}

/**
 * The blocks an excerpt keeps (probe excerpt): text blocks, rendered; the
 * wrapper blocks (columns, column, group, through
 * excerpt_allowed_wrapper_blocks) give up their kept children without their
 * own markup; a block with children is kept only when every child is a
 * plain text block with none of its own. excerpt_allowed_blocks names the
 * rest.
 */
function excerpt_remove_blocks($content)
{
    if (!has_blocks($content)) {
        return $content;
    }
    $base = [null, 'core/freeform', 'core/heading', 'core/html', 'core/list', 'core/media-text', 'core/paragraph', 'core/preformatted', 'core/pullquote', 'core/quote', 'core/table', 'core/verse'];
    $wrappers = (array) apply_filters('excerpt_allowed_wrapper_blocks', ['core/columns', 'core/column', 'core/group']);
    $allowed = (array) apply_filters('excerpt_allowed_blocks', array_merge($base, $wrappers));
    return _minn_excerpt_blocks(parse_blocks((string) $content), $allowed, $wrappers, $base);
}

/**
 * @internal the kept top-level blocks, rendered and run together (the
 * whitespace between them is a block too): a wrapper opened up, any other
 * block whole when its inner blocks are plain allowed ones
 */
function _minn_excerpt_blocks(array $blocks, array $allowed, array $wrappers, array $base): string
{
    $output = [];
    foreach ($blocks as $block) {
        if (!in_array($block['blockName'], $allowed, true)) {
            continue;
        }
        if (!empty($block['innerBlocks']) && in_array($block['blockName'], $wrappers, true)) {
            $output[] = _excerpt_render_inner_blocks($block, $allowed);
            continue;
        }
        foreach ($block['innerBlocks'] ?? [] as $inner) {
            if (!in_array($inner['blockName'], $base, true) || !empty($inner['innerBlocks'])) {
                continue 2;
            }
        }
        $output[] = render_block($block);
    }
    return implode('', $output);
}

/**
 * The allowed blocks inside a block, rendered: one with inner blocks of its
 * own is opened up the same way, so only the innermost render (probe
 * deprecated: a quote in a column gives its paragraphs, not the quote).
 */
function _excerpt_render_inner_blocks($parsed_block, $allowed_blocks)
{
    $output = '';
    foreach ($parsed_block['innerBlocks'] ?? [] as $inner) {
        if (in_array($inner['blockName'], $allowed_blocks, true)) {
            $output .= empty($inner['innerBlocks']) ? render_block($inner) : _excerpt_render_inner_blocks($inner, $allowed_blocks);
        }
    }
    return $output;
}

/**
 * Registers the footnotes meta for every type shown in REST that supports
 * the editor, custom fields and revisions (probe meta-registry).
 */
function register_block_core_footnotes_post_meta()
{
    foreach (get_post_types(['show_in_rest' => true]) as $type) {
        if (post_type_supports($type, 'editor') && post_type_supports($type, 'custom-fields') && post_type_supports($type, 'revisions')) {
            register_post_meta($type, 'footnotes', ['show_in_rest' => true, 'single' => true, 'type' => 'string', 'revisions_enabled' => true, 'auth_callback' => '__return_true']);
        }
    }
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
    return Kses::blockAttributes((string) $text, static fn (string $value): string => wp_kses($value, $allowed_html, (array) $allowed_protocols));
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
    return WP_Block_Bindings_Registry::get_instance()->register((string) $source_name, $source_properties);
}

function unregister_block_bindings_source($source_name)
{
    return WP_Block_Bindings_Registry::get_instance()->unregister((string) $source_name);
}

function get_all_registered_block_bindings_sources()
{
    return WP_Block_Bindings_Registry::get_instance()->get_all_registered();
}

function get_block_bindings_source($source_name)
{
    return WP_Block_Bindings_Registry::get_instance()->get_registered((string) $source_name);
}

/** The attributes a block may bind to a source, through block_bindings_supported_attributes and its per-block form. */
function get_block_bindings_supported_attributes($block_type)
{
    return Minn\Blocks\Bindings::supported((string) $block_type);
}

/** @internal whether a bound post's data may be read here: viewable to all or readable by this user, and not behind a password */
function _minn_block_bindings_post_readable($post): bool
{
    return $post instanceof WP_Post && (is_post_publicly_viewable($post) || current_user_can('read_post', $post->ID)) && !post_password_required($post);
}

/** A post meta value for a binding: the post must be readable, the key unprotected and shown in REST. */
function _block_bindings_post_meta_get_value(array $source_args, $block_instance)
{
    $key = $source_args['key'] ?? '';
    $post_id = $block_instance->context['postId'] ?? 0;
    if ($key === '' || empty($post_id) || !_minn_block_bindings_post_readable(get_post($post_id)) || is_protected_meta($key, 'post')) {
        return null;
    }
    $keys = array_merge(get_registered_meta_keys('post', (string) ($block_instance->context['postType'] ?? '')), get_registered_meta_keys('post', ''));
    return empty($keys[$key]['show_in_rest']) ? null : get_post_meta($post_id, $key, true);
}

/** A synced pattern's override for a named block's attribute, from the pattern/overrides context. */
function _block_bindings_pattern_overrides_get_value(array $source_args, $block_instance, string $attribute_name)
{
    $name = $block_instance->attributes['metadata']['name'] ?? '';
    return $name === '' ? null : _wp_array_get($block_instance->context, ['pattern/overrides', $name, $attribute_name], null);
}

/** A post's date (ISO 8601), its modified date when later than that ("" otherwise), or its link, for a binding. */
function _block_bindings_post_data_get_value(array $source_args, $block_instance)
{
    $post_id = $block_instance->context['postId'] ?? 0;
    if (empty($post_id) || !_minn_block_bindings_post_readable(get_post($post_id))) {
        return null;
    }
    return match ($source_args['key'] ?? '') {
        'date' => get_the_date('c', $post_id),
        'modified' => get_the_modified_date('U', $post_id) > get_the_date('U', $post_id) ? get_the_modified_date('c', $post_id) : '',
        'link' => get_permalink($post_id),
        default => null,
    };
}

/** Term data for a binding: no case the probe could make gave a value on the reference, so none is given here. */
function _block_bindings_term_data_get_value(array $source_args, $block_instance)
{
    return null;
}

/** The core bindings sources, registered at init as the reference registers them. */
function _minn_register_block_bindings_sources()
{
    register_block_bindings_source('core/pattern-overrides', ['label' => _x('Pattern Overrides', 'block bindings source'), 'get_value_callback' => '_block_bindings_pattern_overrides_get_value', 'uses_context' => ['pattern/overrides']]);
    register_block_bindings_source('core/post-data', ['label' => _x('Post Data', 'block bindings source'), 'get_value_callback' => '_block_bindings_post_data_get_value', 'uses_context' => ['postId', 'postType']]);
    register_block_bindings_source('core/post-meta', ['label' => _x('Post Meta', 'block bindings source'), 'get_value_callback' => '_block_bindings_post_meta_get_value', 'uses_context' => ['postId', 'postType']]);
    register_block_bindings_source('core/term-data', ['label' => _x('Term Data', 'block bindings source'), 'get_value_callback' => '_block_bindings_term_data_get_value', 'uses_context' => ['termId', 'taxonomy']]);
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
    $html = _minn_render_core_callback('core/template-part', ...func_get_args());
    if (WP_Block_Supports::$block_to_render !== null) {
        return $html;
    }
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
    $site = ['viewableType' => static fn (string $type) => is_post_type_viewable($type), 'hierarchicalType' => static fn (string $type) => is_post_type_hierarchical($type), 'viewableTaxonomy' => static fn (string $taxonomy) => is_taxonomy_viewable($taxonomy), 'formats' => array_values(get_post_format_slugs())];
    $query = QueryVars::fromContext(is_array($context) ? $context : null, (int) $page, $sticky, $site);
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
        $email = (string) wp_get_current_commenter()['comment_author_email'] ?: wp_get_unapproved_comment_author_email();
        if ($email !== '') {
            $vars['include_unapproved'] = [$email];
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

/** @internal the site's templates and parts, theme files and saved rows alike */
function _minn_template_index(): ?Minn\Theme\TemplateIndex
{
    $theme = Runtime::current()->get('theme');
    $db = Minn\Db::shared();
    return $theme === null
        ? null
        : new Minn\Theme\TemplateIndex($db, $theme, new Minn\Content\Site($db), Runtime::blockTemplates());
}

/** @internal */
function _minn_block_template_from(Minn\Theme\TemplateRecord $record, Minn\Theme\TemplateIndex $index): WP_Block_Template
{
    $template = new WP_Block_Template();
    $template->type = $record->type;
    $template->theme = $record->theme;
    $template->slug = $record->slug;
    $template->id = $record->id();
    // The markup as stored: a caller that wants patterns spliced in resolves
    // them itself, the way the reference's own callers do.
    $template->content = $record->content;
    $template->source = $record->source;
    $template->origin = $record->origin;
    $template->status = $record->status;
    $template->has_theme_file = $record->hasThemeFile;
    $saved = $record->wpId > 0;
    $template->wp_id = $saved ? $record->wpId : null;
    // A saved row's author, as stored, and its dates as the post keeps them (the record carries the REST form).
    $template->author = $saved ? (string) $record->author : null;
    $template->modified = $saved ? str_replace('T', ' ', (string) $record->modified) : null;
    $template->date = $saved ? str_replace('T', ' ', (string) $record->date) : null;
    $template->description = $record->description;
    $template->title = $record->title;
    $template->is_custom = $record->isPart() || Minn\Theme\TemplateIndex::isCustom($record->slug);
    $template->area = $record->area;
    $template->plugin = $record->plugin;
    if ($record->source === 'theme') {
        $template->content = Minn\Theme\TemplatePartTheme::apply($record->content, $record->theme);
        $template->post_types = $index->fileInfo($record->type, $record->slug)['postTypes'] ?? null;
    }
    return $template;
}

/** One theme template file, described (Theme\TemplateIndex::fileInfo); null when the theme has none or the type is neither. */
function _get_block_template_file($template_type, $slug)
{
    if ($template_type !== 'wp_template' && $template_type !== 'wp_template_part') {
        return null;
    }
    return _minn_template_index()?->fileInfo((string) $template_type, (string) $slug);
}

/** The theme's template files of a type, in the directory's order, that match slug__in, slug__not_in, area and post_type; null for neither type. */
function _get_block_templates_files($template_type, $query = [])
{
    if ($template_type !== 'wp_template' && $template_type !== 'wp_template_part') {
        return null;
    }
    $query = (array) $query;
    $files = [];
    foreach (_minn_template_index()?->files((string) $template_type) ?? [] as $file) {
        if ((!empty($query['slug__in']) && !in_array($file['slug'], (array) $query['slug__in'], true))
            || (!empty($query['slug__not_in']) && in_array($file['slug'], (array) $query['slug__not_in'], true))
            || (!empty($query['area']) && ($file['area'] ?? null) !== $query['area'])
            || (!empty($query['post_type']) && !in_array($query['post_type'], (array) ($file['postTypes'] ?? []), true))) {
            continue;
        }
        $files[] = $file;
    }
    return $files;
}

/**
 * A template built from a file as _get_block_template_file describes one:
 * its markup with the theme named on every template part; the default
 * template types' title and description for the slugs they name (which
 * are not custom); a part's area; a custom template's post types.
 */
function _build_block_template_result_from_file($template_file, $template_type)
{
    $file = (array) $template_file;
    $known = $template_type === 'wp_template' ? (get_default_block_template_types()[$file['slug']] ?? null) : null;
    $template = new WP_Block_Template();
    $template->id = $file['theme'] . '//' . $file['slug'];
    $template->theme = $file['theme'];
    $template->content = Minn\Theme\TemplatePartTheme::apply((string) file_get_contents((string) $file['path']), (string) $file['theme']);
    $template->slug = $file['slug'];
    $template->source = 'theme';
    $template->type = $template_type;
    $template->title = $known['title'] ?? ($file['title'] ?? $file['slug']);
    $template->description = $known['description'] ?? '';
    $template->status = 'publish';
    $template->has_theme_file = true;
    $template->is_custom = $known === null;
    $template->post_types = $file['postTypes'] ?? null;
    $template->area = $template_type === 'wp_template_part' ? ($file['area'] ?? null) : null;
    return $template;
}

/** A saved template or part as a template, under the theme its wp_theme term names; an error when it names none. */
function _build_block_template_result_from_post($post)
{
    $post = get_post($post);
    $terms = $post ? get_the_terms($post, 'wp_theme') : false;
    if (is_wp_error($terms)) {
        return $terms;
    }
    $index = _minn_template_index();
    if (!$post || !$terms || $index === null) {
        return new WP_Error('template_missing_theme', __('No theme is defined for this template.'));
    }
    $template = _minn_block_template_from($index->postRecord(get_object_vars($post)), $index);
    $template->theme = $terms[0]->name;
    $template->id = $template->theme . '//' . $post->post_name;
    return $template;
}

/** A theme file's own template by id, past whatever the site saved over it; pre_get_block_file_template may answer first. */
function get_block_file_template($id, $template_type = 'wp_template')
{
    $template = apply_filters('pre_get_block_file_template', null, $id, $template_type);
    if ($template !== null) {
        return $template;
    }
    $parts = explode('//', (string) $id, 2);
    $file = count($parts) === 2 && $parts[0] === get_stylesheet() ? _get_block_template_file($template_type, $parts[1]) : null;
    $template = $file === null ? null : _build_block_template_result_from_file($file, $template_type);
    return apply_filters('get_block_file_template', $template, $id, $template_type);
}

/** The first template the hierarchy names (file extensions dropped) that the site has, saved or the theme's; null for none. */
function resolve_block_template($template_type, $template_hierarchy, $fallback_template)
{
    if (!$template_type) {
        return null;
    }
    $slugs = array_map('_strip_template_file_suffix', $template_hierarchy === [] ? [$template_type] : (array) $template_hierarchy);
    $templates = array_values(get_block_templates(['slug__in' => $slugs]));
    $priority = array_flip($slugs);
    usort($templates, static fn ($a, $b) => $priority[$a->slug] <=> $priority[$b->slug]);
    return $templates[0] ?? null;
}

/** A template file name without its .php or .html. */
function _strip_template_file_suffix($template_file)
{
    return preg_replace('/\.(php|html)$/', '', (string) $template_file);
}

/**
 * The block template for a request under a theme with block templates: no
 * less specific than a PHP template already found (one outside the theme
 * keeps only the first candidate). Its id and markup go to the globals
 * the canvas renders (a signed-in visitor sees the empty-template warning
 * for an empty one) and the canvas is the template; with none, the
 * template given.
 */
function locate_block_template($template, $type, array $templates)
{
    global $_wp_current_template_id, $_wp_current_template_content;
    if (!current_theme_supports('block-templates')) {
        return $template;
    }
    if ($template) {
        $relative = str_replace([get_stylesheet_directory() . '/', get_template_directory() . '/'], '', (string) $template);
        $templates = array_slice($templates, 0, (int) array_search($relative, $templates, true) + 1);
    }
    $block_template = resolve_block_template($type, $templates, $template);
    if (!$block_template) {
        return $template;
    }
    $_wp_current_template_id = $block_template->id;
    $_wp_current_template_content = empty($block_template->content) && is_user_logged_in() ? wp_render_empty_block_template_warning($block_template) : $block_template->content;
    return MINN_ENGINE_DIR . '/wp-api/template-canvas.php';
}

/** Deprecated since 6.4: the active theme named on every template part that names none. */
function _inject_theme_attribute_in_block_template_content($template_content)
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'traverse_and_serialize_blocks( parse_blocks( $template_content ), "_inject_theme_attribute_in_template_part_block" )');
    return traverse_and_serialize_blocks(parse_blocks($template_content), '_inject_theme_attribute_in_template_part_block');
}

/** Deprecated since 6.4: the theme attribute taken off every template part. */
function _remove_theme_attribute_in_block_template_content($template_content)
{
    _deprecated_function(__FUNCTION__, '6.4.0', 'traverse_and_serialize_blocks( parse_blocks( $template_content ), "_remove_theme_attribute_from_template_part_block" )');
    return traverse_and_serialize_blocks(parse_blocks($template_content), '_remove_theme_attribute_from_template_part_block');
}

/** A template part block given the active theme when it names none. */
function _inject_theme_attribute_in_template_part_block(&$block)
{
    if (($block['blockName'] ?? '') === 'core/template-part' && !isset($block['attrs']['theme'])) {
        $block['attrs']['theme'] = get_stylesheet();
    }
}

/** A template part block's theme attribute taken off. */
function _remove_theme_attribute_from_template_part_block(&$block)
{
    if (($block['blockName'] ?? '') === 'core/template-part' && isset($block['attrs']['theme'])) {
        unset($block['attrs']['theme']);
    }
}

/** What a signed-in visitor sees for an empty template: its title and a note, with a link to edit it. */
function wp_render_empty_block_template_warning($block_template)
{
    return sprintf(
        "<div id=\"wp-empty-template-alert\">\n\t\t\t<h2>%s</h2>\n\t\t\t<p>%s</p>\n\t\t\t<a href=\"%s\" class=\"wp-element-button\">\n\t\t\t\t%s\n\t\t\t</a>\n\t\t</div>",
        esc_html($block_template->title),
        __('This page is blank because the template is empty. You can reset or customize it in the Site Editor.'),
        esc_url((string) get_edit_post_link($block_template->wp_id)),
        __('Edit template'),
    );
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
    $index = _minn_template_index();
    foreach ($index === null ? [] : $index->all($template_type) as $record) {
        // Plugin registrations come back through the registry below, where
        // the post_type query still applies to them.
        if ($record->source === 'plugin') {
            continue;
        }
        $template = _minn_block_template_from($record, $index);
        if (_minn_block_template_matches($template, $query)) {
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
        $index = _minn_template_index();
        $record = $index?->find($template_type, (string) $id);
        $template = $record === null || $record->source === 'plugin' || $index === null
            ? null
            : _minn_block_template_from($record, $index);
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

/** Whether the site has a published post, as the calendar block stores it. */
function block_core_calendar_has_published_posts()
{
    $has = get_option('wp_calendar_block_has_published_posts', null);
    return $has === null ? block_core_calendar_update_has_published_posts() : (bool) $has;
}

/** Stores whether the site has a published post, for the calendar block. */
function block_core_calendar_update_has_published_posts()
{
    $has = _minn_posts()->hasPublished('post');
    update_option('wp_calendar_block_has_published_posts', $has);
    return $has;
}

/** The default on transition_post_status: a post moving into or out of publish asks the calendar's question again. */
function block_core_calendar_update_has_published_post_on_transition_post_status($new_status, $old_status, $post)
{
    if ($new_status === $old_status || get_post_type($post) !== 'post' || ($new_status !== 'publish' && $old_status !== 'publish')) {
        return;
    }
    block_core_calendar_update_has_published_posts();
}

/** The default on delete_post: a published post going away asks the calendar's question again. */
function block_core_calendar_update_has_published_post_on_delete($post_id)
{
    $post = get_post($post_id);
    if ($post === null || $post->post_type !== 'post' || $post->post_status !== 'publish') {
        return;
    }
    block_core_calendar_update_has_published_posts();
}

/** The block hooks applied to a post's content, the post as the anchor's context. */
function apply_block_hooks_to_content_from_post_object($content, $post = null, $callback = 'insert_hooked_blocks', &$ignored_hooked_blocks_at_root = null)
{
    $post = get_post($post);
    return $post === null ? $content : apply_block_hooks_to_content((string) $content, $post, $callback);
}

/** The template types a block theme may define (data/template-types.json), through default_template_types. */
function get_default_block_template_types()
{
    return apply_filters('default_template_types', json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/template-types.json'), true));
}

/** The areas a template part may belong to (data/template-part-areas.json), through default_wp_template_part_areas. */
function get_allowed_block_template_part_areas()
{
    return apply_filters('default_wp_template_part_areas', json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/template-part-areas.json'), true));
}

/**
 * The template part block's variations: one per area that has parts (the
 * general area aside), then one per template part the theme offers, as the
 * reference builds them. Building them reads the theme's data, which
 * registers its block style partials, as the reference's build does.
 */
function build_template_part_block_variations()
{
    WP_Theme_JSON_Resolver::get_theme_data();
    return TemplatePartVariations::build(get_allowed_block_template_part_areas(), get_block_templates([], 'wp_template_part'));
}

/** The templates a slug falls back through (Theme\TemplateHierarchy), against the registered types and taxonomies. */
function get_template_hierarchy($slug, $is_custom = false, $template_prefix = '')
{
    return TemplateHierarchy::for((string) $slug, (bool) $is_custom, (string) $template_prefix, array_values(get_post_types()), array_values(get_taxonomies()));
}

/**
 * The social link block's services (captured as data/social-link-services.json):
 * one service's field, one service, or every service when the one asked
 * for is not there.
 */
function block_core_social_link_services($service = '', $field = '')
{
    static $services = null;
    $services ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/social-link-services.json'), true);
    if ($service !== '' && $field !== '' && isset($services[$service][$field])) {
        return $services[$service][$field];
    }
    return $service !== '' && isset($services[$service]) ? $services[$service] : $services;
}

/** A social link service's name, or the generic label. */
function block_core_social_link_get_name($service)
{
    $services = block_core_social_link_services();
    return isset($services[$service]['name']) ? $services[$service]['name'] : __('Share Icon');
}

/** The block inserter's own categories, in the reference's order. */
function get_default_block_categories()
{
    return [
        ['slug' => 'text', 'title' => _x('Text', 'block category'), 'icon' => null],
        ['slug' => 'media', 'title' => _x('Media', 'block category'), 'icon' => null],
        ['slug' => 'design', 'title' => _x('Design', 'block category'), 'icon' => null],
        ['slug' => 'widgets', 'title' => _x('Widgets', 'block category'), 'icon' => null],
        ['slug' => 'theme', 'title' => _x('Theme', 'block category'), 'icon' => null],
        ['slug' => 'embed', 'title' => _x('Embeds', 'block category'), 'icon' => null],
        ['slug' => 'reusable', 'title' => _x('Patterns', 'block category'), 'icon' => null],
    ];
}

/** The categories the editor offers for a post or an editor context, through block_categories_all (and the older block_categories for a post). */
function get_block_categories($post_or_block_editor_context)
{
    $context = $post_or_block_editor_context instanceof WP_Block_Editor_Context ? $post_or_block_editor_context : new WP_Block_Editor_Context(['post' => $post_or_block_editor_context]);
    $categories = apply_filters('block_categories_all', get_default_block_categories(), $context);
    if (!empty($context->post)) {
        $categories = apply_filters_deprecated('block_categories', [$categories, $context->post], '5.8.0', 'block_categories_all');
    }
    return $categories;
}

/** A block style object's CSS, declarations and class names (WP_Style_Engine); kept in a store when a context and a selector are given. */
function wp_style_engine_get_styles($block_styles, $options = [])
{
    $options = wp_parse_args($options, ['selector' => null, 'context' => null, 'convert_vars_to_classnames' => false]);
    $parsed = WP_Style_Engine::parse_block_styles($block_styles, $options);
    $out = [];
    if (!empty($parsed['declarations'])) {
        $out['css'] = WP_Style_Engine::compile_css($parsed['declarations'], $options['selector']);
        $out['declarations'] = $parsed['declarations'];
        if (!empty($options['context'])) {
            WP_Style_Engine::store_css_rule($options['context'], $options['selector'], $parsed['declarations']);
        }
    }
    if (!empty($parsed['classnames'])) {
        $out['classnames'] = implode(' ', array_unique($parsed['classnames']));
    }
    return array_filter($out);
}

/** A stylesheet from rules ({selector, declarations, rules_group}), a selector's merged; kept in a store when a context is given. */
function wp_style_engine_get_stylesheet_from_css_rules($css_rules, $options = [])
{
    $options = wp_parse_args($options, ['context' => null]);
    $objects = [];
    foreach (empty($css_rules) ? [] : (array) $css_rules as $rule) {
        if (empty($rule['selector']) || empty($rule['declarations']) || !is_array($rule['declarations'])) {
            continue;
        }
        $group = (string) ($rule['rules_group'] ?? '');
        if (!empty($options['context'])) {
            WP_Style_Engine::store_css_rule($options['context'], $rule['selector'], $rule['declarations'], $group);
        }
        $objects[] = new WP_Style_Engine_CSS_Rule($rule['selector'], $rule['declarations'], $group);
    }
    return $objects === [] ? '' : WP_Style_Engine::compile_stylesheet_from_css_rules($objects, $options);
}

/** The stylesheet of the rules a context's store keeps. */
function wp_style_engine_get_stylesheet_from_context($context, $options = [])
{
    return WP_Style_Engine::compile_stylesheet_from_css_rules(WP_Style_Engine::get_store($context)->get_all_rules(), $options);
}

/** The first block of a name in a parsed tree, depth first; an empty array when there is none. */
function wp_get_first_block($blocks, $block_name)
{
    foreach ($blocks as $block) {
        if ($block['blockName'] === $block_name) {
            return $block;
        }
        $inner = empty($block['innerBlocks']) ? [] : wp_get_first_block($block['innerBlocks'], $block_name);
        if ($inner !== []) {
            return $inner;
        }
    }
    return [];
}

/** A featured image block's border as class and style attributes, through the style engine (a preset colour as its class). */
function get_block_core_post_featured_image_border_attributes($attributes)
{
    $border = (array) ($attributes['style']['border'] ?? []);
    $styles = array_intersect_key($border, array_flip(['radius', 'style', 'width']));
    $styles['color'] = array_key_exists('borderColor', $attributes) ? "var:preset|color|{$attributes['borderColor']}" : ($border['color'] ?? null);
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        $styles[$side] = ['color' => $border[$side]['color'] ?? null, 'style' => $border[$side]['style'] ?? null, 'width' => $border[$side]['width'] ?? null];
    }
    $css = wp_style_engine_get_styles(['border' => $styles]);
    return array_filter(['class' => $css['classnames'] ?? '', 'style' => $css['css'] ?? '']);
}

/** A fresh class for a block's element styles: wp-elements- and the next number, the count the engine's renderer shares. */
function wp_get_elements_class_name()
{
    return 'wp-elements-' . RenderState::current()->nextElements();
}

/** A block template part of the theme's, printed through do_blocks; nothing when the theme has none by that name. */
function block_template_part($part)
{
    $template = get_block_template(get_stylesheet() . '//' . $part, 'wp_template_part');
    if ($template && !empty($template->content)) {
        echo do_blocks($template->content);
    }
}

/** The theme's header part. */
function block_header_area()
{
    block_template_part('header');
}

/** The theme's footer part. */
function block_footer_area()
{
    block_template_part('footer');
}

/** Every block and every block inside one, breadth first, as references into the list. */
function _flatten_blocks(&$blocks)
{
    $all = [];
    $queue = [];
    foreach ($blocks as &$block) {
        $queue[] = &$block;
    }
    while ($queue !== []) {
        $current = &$queue[0];
        array_shift($queue);
        $all[] = &$current;
        foreach (array_keys((array) ($current['innerBlocks'] ?? [])) as $key) {
            $queue[] = &$current['innerBlocks'][$key];
        }
        unset($current);
    }
    return $all;
}

/** Whether block assets load only for the blocks a page has: on a block theme, through should_load_block_assets_on_demand; never in the admin, a feed or the REST API. */
function wp_should_load_block_assets_on_demand()
{
    if (is_admin() || is_feed() || wp_is_rest_endpoint()) {
        return false;
    }
    return (bool) apply_filters('should_load_block_assets_on_demand', wp_is_block_theme());
}

/** Whether a block type skips serializing a support set, or one feature of it. */
function wp_should_skip_block_supports_serialization($block_type, $feature_set, $feature = null)
{
    if (!is_object($block_type) || !$feature_set) {
        return false;
    }
    $skip = _wp_array_get((array) $block_type->supports, [$feature_set, '__experimentalSkipSerialization'], false);
    return is_array($skip) ? in_array($feature, $skip, true) : $skip;
}

/** Whether a block type supports a border feature (all of them when it declares border support as true); the default when it declares nothing. */
function wp_has_border_feature_support($block_type, $feature, $default_value = false)
{
    $support = is_object($block_type) ? ($block_type->supports['__experimentalBorder'] ?? $default_value) : $default_value;
    return $support === true || (bool) _wp_array_get((array) (is_object($block_type) ? $block_type->supports : []), ['__experimentalBorder', $feature], $default_value);
}

/** The class a block's own settings presets hang on: wp-settings- and the MD5 of the serialized block. */
function _wp_get_presets_class_name($block)
{
    return 'wp-settings-' . md5(serialize($block));
}

/** The arrow a comments pagination link shows for its context's style (arrow or chevron); null for none. */
function get_comments_pagination_arrow($block, $pagination_type = 'next')
{
    return _minn_pagination_arrow((string) ($block->context['comments/paginationArrow'] ?? ''), 'comments', (string) $pagination_type);
}

/** The arrow a query pagination link shows for its context's style (arrow or chevron); null for none. */
function get_query_pagination_arrow($block, $is_next)
{
    return _minn_pagination_arrow((string) ($block->context['paginationArrow'] ?? ''), 'query', $is_next ? 'next' : 'previous');
}

/** @internal a pagination arrow: its span with the direction and style classes, or null for a style without arrows */
function _minn_pagination_arrow(string $style, string $kind, string $direction): ?string
{
    $arrow = ['arrow' => ['next' => '→', 'previous' => '←'], 'chevron' => ['next' => '»', 'previous' => '«']][$style][$direction] ?? null;
    return $arrow === null ? null : "<span class='wp-block-{$kind}-pagination-{$direction}-arrow is-arrow-{$style}' aria-hidden='true'>{$arrow}</span>";
}

/** The block a theme.json path is about: the name after styles and blocks, else the first core/ block named anywhere in it, else "". */
function wp_get_block_name_from_theme_json_path($path)
{
    $path = (array) $path;
    if (count($path) >= 3 && $path[0] === 'styles' && $path[1] === 'blocks' && str_contains((string) $path[2], '/')) {
        return $path[2];
    }
    foreach ($path as $item) {
        if (is_string($item) && str_starts_with($item, 'core/')) {
            return $item;
        }
    }
    return '';
}

/** The address of a block asset by its path: under wp-includes, the theme (or its parent), else a plugin's; false for no path. */
function get_block_asset_url($path)
{
    if (empty($path)) {
        return false;
    }
    $path = wp_normalize_path((string) $path);
    $includes = wp_normalize_path((string) realpath(ABSPATH . WPINC));
    if ($includes !== '' && str_starts_with($path, $includes)) {
        return includes_url(str_replace($includes, '', $path));
    }
    // The theme's folder as given or resolved (the themes folder may be a link).
    foreach ([get_stylesheet_directory(), get_template_directory()] as $dir) {
        foreach (array_unique([wp_normalize_path($dir), wp_normalize_path((string) realpath($dir))]) as $form) {
            if ($form !== '' && str_starts_with($path, trailingslashit($form))) {
                return get_theme_file_uri(str_replace($form, '', $path));
            }
        }
    }
    return plugins_url(basename($path), $path);
}

/** The navigation's parsed blocks without the freeform ones between them. */
function block_core_navigation_filter_out_empty_blocks($parsed_blocks)
{
    return array_filter((array) $parsed_blocks, static fn ($block) => isset($block['blockName']));
}

/** The editor settings a classic theme's supports amount to: the custom-value switches, and its palettes and sizes when it declares them. */
function get_classic_theme_supports_block_editor_settings()
{
    $settings = ['disableCustomColors' => get_theme_support('disable-custom-colors'), 'disableCustomFontSizes' => get_theme_support('disable-custom-font-sizes'), 'disableCustomGradients' => get_theme_support('disable-custom-gradients'), 'disableLayoutStyles' => get_theme_support('disable-layout-styles'), 'enableCustomLineHeight' => get_theme_support('custom-line-height'), 'enableCustomSpacing' => get_theme_support('custom-spacing'), 'enableCustomUnits' => get_theme_support('custom-units')];
    foreach (['colors' => 'editor-color-palette', 'fontSizes' => 'editor-font-sizes', 'gradients' => 'editor-gradient-presets'] as $key => $feature) {
        $declared = current((array) get_theme_support($feature));
        if ($declared !== false) {
            $settings[$key] = $declared;
        }
    }
    return $settings;
}

/** A block support's style printed in a style tag: in the head under a block theme, else in the footer, at the priority given. */
function wp_enqueue_block_support_styles($style, $priority = 10)
{
    add_action(wp_is_block_theme() ? 'wp_head' : 'wp_footer', static function () use ($style): void {
        echo "<style>{$style}</style>\n";
    }, $priority);
}

/** A template file's description with the title and post types theme.json gives a custom template of that slug. */
function _add_block_template_info($template_item)
{
    $declared = wp_theme_has_theme_json() ? (wp_get_theme_data_custom_templates()[$template_item['slug']] ?? null) : null;
    return $declared === null ? $template_item : array_merge($template_item, ['title' => $declared['title'], 'postTypes' => $declared['postTypes']]);
}

/** A template part's description with the title and area theme.json gives it, else the uncategorized area. */
function _add_block_template_part_area_info($template_info)
{
    $declared = wp_theme_has_theme_json() ? (wp_get_theme_data_template_parts()[$template_info['slug']] ?? null) : null;
    if (isset($declared['area'])) {
        return array_merge($template_info, ['title' => $declared['title'], 'area' => _filter_block_template_part_area($declared['area'])]);
    }
    return array_merge($template_info, ['area' => WP_TEMPLATE_PART_AREA_UNCATEGORIZED]);
}

/** A template part area that is one of the allowed areas, else uncategorized (with a notice). */
function _filter_block_template_part_area($type)
{
    if (in_array($type, array_column(get_allowed_block_template_part_areas(), 'area'), true)) {
        return $type;
    }
    wp_trigger_error('', sprintf(__('"%1$s" is not a supported wp_template_part area value and has been added as "%2$s".'), $type, WP_TEMPLATE_PART_AREA_UNCATEGORIZED), E_USER_NOTICE);
    return WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
}

/** Every .html file under a folder, however deep; none for a folder that is not there. */
function _get_block_templates_paths($base_directory)
{
    if (!is_dir((string) $base_directory)) {
        return [];
    }
    $paths = [];
    foreach (new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator((string) $base_directory)), '/^.+\.html$/i', RegexIterator::GET_MATCH) as $path => $file) {
        $paths[] = $path;
    }
    return $paths;
}
