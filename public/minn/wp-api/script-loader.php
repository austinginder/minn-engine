<?php
/** Script and style registration and printing. */

use Minn\Runtime\Assets;
use Minn\Runtime\Runtime;

/** @internal */
function _minn_assets(string $kind): Assets
{
    $runtime = Runtime::current();
    $assets = $runtime->get('assets_' . $kind);
    if (!$assets instanceof Assets) {
        $assets = new Assets($kind);
        $runtime->set('assets_' . $kind, $assets);
    }
    return $assets;
}

/** @internal a registered src as the URL the tag prints */
function _minn_asset_url(string $src, string|bool|null $ver): string
{
    if ($src === '') {
        return '';
    }
    // A relative source is prefixed with the stored site URL as given, slash or not: the reference does the same.
    if (!preg_match('#^(https?:)?//#', $src)) {
        $src = rtrim((string) get_option('siteurl'), '/') . $src;
    }
    if ($ver === false) {
        $ver = $GLOBALS['wp_version'];
    }
    if ($ver !== null && $ver !== '' && $ver !== true) {
        $src = add_query_arg('ver', (string) $ver, $src);
    }
    return $src;
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $args = [])
{
    $args = is_array($args) ? $args : ['in_footer' => (bool) $args];
    $assets = _minn_assets('script');
    $ok = $assets->register((string) $handle, $src === false ? false : (string) $src, (array) $deps, $ver, $args);
    if (!empty($args['in_footer'])) {
        $assets->addData((string) $handle, 'group', 1);
    }
    if (!empty($args['strategy'])) {
        $assets->addData((string) $handle, 'strategy', $args['strategy']);
    }
    return $ok;
}

function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $args = [])
{
    $assets = _minn_assets('script');
    if ($src !== '' || !$assets->registered((string) $handle)) {
        if ($src !== '') {
            wp_register_script($handle, $src, $deps, $ver, $args);
        }
    }
    $assets->enqueue((string) $handle);
}

function wp_deregister_script($handle)
{
    _minn_assets('script')->deregister((string) $handle);
}

function wp_dequeue_script($handle)
{
    _minn_assets('script')->dequeue((string) $handle);
}

function wp_script_is($handle, $status = 'enqueued')
{
    $assets = _minn_assets('script');
    return match ($status) {
        'registered' => $assets->registered((string) $handle),
        'enqueued', 'queue' => $assets->enqueued((string) $handle),
        'done' => $assets->done((string) $handle),
        'to_do' => $assets->enqueued((string) $handle) && !$assets->done((string) $handle),
        default => false,
    };
}

function wp_add_inline_script($handle, $data, $position = 'after')
{
    return _minn_assets('script')->addInline((string) $handle, (string) $data, (string) $position);
}

function wp_localize_script($handle, $object_name, $l10n)
{
    return _minn_assets('script')->localize((string) $handle, (string) $object_name, (array) $l10n);
}

function wp_script_add_data($handle, $key, $value)
{
    return _minn_assets('script')->addData((string) $handle, (string) $key, $value);
}

function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all')
{
    return _minn_assets('style')->register((string) $handle, $src === false ? false : (string) $src, (array) $deps, $ver, (string) $media);
}

function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
{
    if ($src !== '') {
        wp_register_style($handle, $src, $deps, $ver, $media);
    }
    _minn_assets('style')->enqueue((string) $handle);
}

function wp_deregister_style($handle)
{
    _minn_assets('style')->deregister((string) $handle);
}

function wp_dequeue_style($handle)
{
    _minn_assets('style')->dequeue((string) $handle);
}

function wp_style_is($handle, $status = 'enqueued')
{
    $assets = _minn_assets('style');
    return match ($status) {
        'registered' => $assets->registered((string) $handle),
        'enqueued', 'queue' => $assets->enqueued((string) $handle),
        'done' => $assets->done((string) $handle),
        'to_do' => $assets->enqueued((string) $handle) && !$assets->done((string) $handle),
        default => false,
    };
}

function wp_add_inline_style($handle, $data)
{
    return _minn_assets('style')->addInline((string) $handle, (string) $data, 'after');
}

function wp_style_add_data($handle, $key, $value)
{
    return _minn_assets('style')->addData((string) $handle, (string) $key, $value);
}

function wp_enqueue_scripts()
{
    do_action('wp_enqueue_scripts');
}

/** @internal the "sourceURL" trailer the reference adds to inline code under WP_DEBUG */
function _minn_source_url(string $id): string
{
    return defined('WP_DEBUG') && WP_DEBUG ? "\n//# sourceURL={$id}" : '';
}

function wp_print_styles($handles = false)
{
    do_action('wp_print_styles');
    $assets = _minn_assets('style');
    wp_styles()->push();
    $list = $handles === false ? $assets->toPrint() : (array) $handles;
    foreach ($list as $handle) {
        $item = $assets->item($handle);
        if ($item === null) {
            continue;
        }
        if ($item['src'] !== false && $item['src'] !== '') {
            $href = apply_filters('style_loader_src', _minn_asset_url($item['src'], $item['ver']), $handle);
            $tag = "<link rel='stylesheet' id='" . esc_attr($handle) . "-css' href='" . esc_url($href) . "' media='" . esc_attr((string) $item['extra']) . "' />\n";
            echo apply_filters('style_loader_tag', $tag, $handle, $href, (string) $item['extra']);
        }
        foreach ($item['inline']['after'] as $css) {
            echo '<style id="' . esc_attr($handle) . '-inline-css">' . "\n" . $css . (defined('WP_DEBUG') && WP_DEBUG ? "\n/*# sourceURL=" . esc_attr($handle) . '-inline-css */' : '') . "\n</style>\n";
        }
        $assets->markDone($handle);
    }
    return $list;
}

/** @internal prints the scripts of one group */
function _minn_print_scripts(bool $footer): array
{
    $assets = _minn_assets('script');
    wp_scripts()->push();
    $list = $assets->toPrint($footer);
    foreach ($list as $handle) {
        $item = $assets->item($handle);
        if ($item === null) {
            continue;
        }
        if ($item['localized'] !== []) {
            echo '<script id="' . esc_attr($handle) . '-js-extra">' . "\n" . implode("\n", $item['localized']) . _minn_source_url($handle . '-js-extra') . "\n</script>\n";
        }
        foreach ($item['inline']['before'] as $js) {
            echo '<script id="' . esc_attr($handle) . '-js-before">' . "\n" . $js . _minn_source_url($handle . '-js-before') . "\n</script>\n";
        }
        if ($item['src'] !== false && $item['src'] !== '') {
            $src = apply_filters('script_loader_src', _minn_asset_url($item['src'], $item['ver']), $handle);
            $strategy = $item['data']['strategy'] ?? '';
            $attr = $strategy === 'defer' ? 'data-wp-strategy="defer" defer ' : ($strategy === 'async' ? 'async data-wp-strategy="async" ' : '');
            $tag = '<script ' . $attr . 'id="' . esc_attr($handle) . '-js" src="' . esc_url($src) . '"></script>' . "\n";
            echo apply_filters('script_loader_tag', $tag, $handle, $src);
        }
        foreach ($item['inline']['after'] as $js) {
            echo '<script id="' . esc_attr($handle) . '-js-after">' . "\n" . $js . _minn_source_url($handle . '-js-after') . "\n</script>\n";
        }
        $assets->markDone($handle);
    }
    return $list;
}

/** @internal the engine's own stylesheets, printed where a theme's would be */
function _minn_print_engine_styles()
{
    echo (string) Runtime::current()->get('engine_head_styles', '');
}

function wp_print_head_scripts()
{
    do_action('wp_print_scripts');
    return _minn_print_scripts(false);
}

function wp_print_scripts($handles = false)
{
    do_action('wp_print_scripts');
    return _minn_print_scripts(false);
}

/**
 * Fires the action only; `_wp_footer_scripts` is hooked to it and does the
 * printing. That indirection is the contract, not an accident: a plugin
 * hooks this action ahead of the printer to add inline data to a handle it
 * already enqueued (WooCommerce adds the whole `wcSettings` blob at
 * priority 1 this way). Printing here directly would run before every one
 * of those callbacks and drop their data.
 */
function wp_print_footer_scripts()
{
    do_action('wp_print_footer_scripts');
}

function _wp_footer_scripts()
{
    print_late_styles();
    print_footer_scripts();
}

function print_footer_scripts()
{
    return _minn_print_scripts(true);
}

function print_late_styles()
{
    return wp_print_styles();
}

function print_head_scripts()
{
    return wp_print_head_scripts();
}

function wp_enqueue_code_editor($args)
{
    $settings = wp_get_code_editor_settings($args);
    if ($settings === false || $settings === []) {
        return false;
    }
    wp_enqueue_script('code-editor');
    wp_enqueue_style('code-editor');
    if (isset($settings['codemirror']['mode'])) {
        wp_enqueue_script('wp-codemirror');
        wp_enqueue_style('wp-codemirror');
    }
    wp_add_inline_script('code-editor', sprintf('jQuery.extend( wp.codeEditor.defaultSettings, %s );', wp_json_encode($settings)));
    do_action('wp_enqueue_code_editor', $settings);
    return $settings;
}

function wp_enqueue_media($args = [])
{
}

function wp_enqueue_editor()
{
}

function wp_default_scripts($scripts)
{
}

function wp_default_styles($styles)
{
}

function wp_scripts()
{
    if (!isset($GLOBALS['wp_scripts']) || !$GLOBALS['wp_scripts'] instanceof WP_Scripts) {
        $GLOBALS['wp_scripts'] = new WP_Scripts(_minn_assets('script'));
    }
    return $GLOBALS['wp_scripts'];
}

function wp_styles()
{
    if (!isset($GLOBALS['wp_styles']) || !$GLOBALS['wp_styles'] instanceof WP_Styles) {
        $GLOBALS['wp_styles'] = new WP_Styles(_minn_assets('style'));
    }
    return $GLOBALS['wp_styles'];
}

function wp_common_block_scripts_and_styles()
{
}

function wp_enqueue_block_style($block_name, $args)
{
}

function wp_enqueue_global_styles()
{
}

function wp_enqueue_classic_theme_styles()
{
}

// Script modules.

function _minn_script_module_url(string $src, string|false|null $version): string
{
    if ($version === false) {
        $version = (string) $GLOBALS['wp_version'];
    }
    if ($version !== null && $version !== '') {
        $src = add_query_arg('ver', $version, $src);
    }
    return esc_url($src);
}

function wp_script_modules()
{
    return WP_Script_Modules::instance();
}

function wp_register_script_module($id, $src, $deps = [], $version = false, $args = [])
{
    wp_script_modules()->register((string) $id, (string) $src, (array) $deps, $version, (array) $args);
}

function wp_enqueue_script_module($id, $src = '', $deps = [], $version = false, $args = [])
{
    wp_script_modules()->enqueue((string) $id, (string) $src, (array) $deps, $version, (array) $args);
}

function wp_dequeue_script_module($id)
{
    wp_script_modules()->dequeue((string) $id);
}

function wp_deregister_script_module($id)
{
    wp_script_modules()->deregister((string) $id);
}

function wp_set_script_module_translations($id, $domain = 'default', $path = '')
{
    wp_script_modules()->set_translations((string) $id, (string) $domain, (string) $path);
}

function wp_enqueue_block_editor_script_modules()
{
}

/**
 * The modules the reference registers itself, served from the engine's own
 * MIT implementations: the interactivity runtime, the router, a11y, and the
 * navigation block's view module.
 */
function wp_default_script_modules()
{
    $base = '/minn/assets/';
    $args = ['in_footer' => true, 'fetchpriority' => 'low'];
    $view = $args + ['attributes' => ['data-wp-router-options' => '{"loadOnClientNavigation":true}']];
    wp_register_script_module('@wordpress/interactivity', $base . 'interactivity.js', [], MINN_ENGINE_VERSION, $args);
    wp_register_script_module('@wordpress/a11y', $base . 'a11y.js', [], MINN_ENGINE_VERSION, $args);
    wp_register_script_module('@wordpress/interactivity-router', $base . 'interactivity-router.js', [['id' => '@wordpress/a11y', 'import' => 'dynamic'], '@wordpress/interactivity'], MINN_ENGINE_VERSION, $args);
    wp_register_script_module('@wordpress/block-library/navigation/view', $base . 'navigation-view.js', ['@wordpress/interactivity'], MINN_ENGINE_VERSION, $view);
    foreach (['image', 'search', 'file', 'accordion', 'tabs', 'playlist'] as $block) {
        wp_register_script_module('@wordpress/block-library/' . $block . '/view', $base . $block . '-view.js', ['@wordpress/interactivity'], MINN_ENGINE_VERSION, $view);
    }
    wp_register_script_module('@wordpress/block-library/query/view', $base . 'query-view.js', ['@wordpress/interactivity', ['id' => '@wordpress/interactivity-router', 'import' => 'dynamic']], MINN_ENGINE_VERSION, $view);
    wp_register_script_module('@wordpress/block-library/form/view', $base . 'form-view.js', [], MINN_ENGINE_VERSION, $view);
}

/** Block themes load each core block's stylesheet on its own; a filter may say otherwise. */
function wp_should_load_separate_core_block_assets()
{
    return (bool) apply_filters('should_load_separate_core_block_assets', (bool) Runtime::current()->get('block_theme', false));
}
