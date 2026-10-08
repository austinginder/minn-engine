<?php
/** Script and style registration and printing. */

use Minn\I18n\ScriptTranslations;
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

/**
 * @internal A dependency list. The reference keeps an ARRAY of handles and
 * discards anything else outright: a scalar handle, null and false all
 * register as no dependencies at all. Casting instead turns `''` into a
 * dependency on the empty string, which no handle satisfies, so the asset
 * and everything above it silently stops printing (WooCommerce registers
 * `woocommerce-general` with `deps => ''`, which cost the whole classic
 * stylesheet set and left the My Account forms unstyled).
 */
function _minn_dep_list($deps): array
{
    return is_array($deps) ? array_values($deps) : [];
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $args = [])
{
    $args = is_array($args) ? $args : ['in_footer' => (bool) $args];
    $assets = _minn_assets('script');
    $ok = $assets->register((string) $handle, $src === false ? false : (string) $src, _minn_dep_list($deps), $ver, $args);
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
    return _minn_assets('style')->register((string) $handle, $src === false ? false : (string) $src, _minn_dep_list($deps), $ver, (string) $media);
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
    // Hooked to an action, it is handed the action's empty argument: that is every queued style.
    if ($handles === '') {
        $handles = false;
    }
    do_action('wp_print_styles');
    return _minn_print_styles($handles);
}

/** @internal the queued styles (or the handles given) printed as link and inline style tags; the handles printed */
function _minn_print_styles($handles = false)
{
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
            // A style inlined from a file names that file as its source, so a
            // browser's devtools still point at the stylesheet on disk.
            $source = _minn_inline_style_sources()[$handle] ?? ($handle . '-inline-css');
            echo '<style id="' . esc_attr($handle) . '-inline-css">' . "\n" . $css . (defined('WP_DEBUG') && WP_DEBUG ? "\n/*# sourceURL=" . esc_attr($source) . ' */' : '') . "\n</style>\n";
        }
        $assets->markDone($handle);
    }
    return $list;
}

/** @internal the translations a script was given, as the block printed before it, or null when it has none */
function _minn_script_translations_block(string $handle): ?string
{
    $item = _minn_assets('script')->item($handle);
    if (!isset($item['translations'])) {
        return null;
    }
    $json = load_script_textdomain($handle, $item['translations']['domain'], $item['translations']['path']);
    if (!is_string($json) || $json === '') {
        return null;
    }
    $id = $handle . '-js-translations';
    return wp_get_inline_script_tag(ScriptTranslations::block($item['translations']['domain'], $json) . _minn_source_url($id), ['id' => $id]);
}

/** @internal prints the scripts of one group */
function _minn_print_scripts(bool $footer): array
{
    wp_scripts()->push();
    return _minn_print_script_list(_minn_assets('script')->toPrint($footer));
}

/** @internal prints the given scripts, each with its inline blocks, and marks them done */
function _minn_print_script_list(array $list): array
{
    $assets = _minn_assets('script');
    foreach ($list as $handle) {
        $item = $assets->item($handle);
        if ($item === null) {
            continue;
        }
        // The blocks are built in the reference's order (before, after, translations, the localized data), printed in the page's.
        $before = _minn_inline_script_block($handle, 'before', $item['inline']['before']);
        $after = _minn_inline_script_block($handle, 'after', $item['inline']['after']);
        $translations = _minn_script_translations_block($handle) ?? '';
        echo $item['localized'] === [] ? '' : wp_get_inline_script_tag(implode("\n", $item['localized']) . _minn_source_url($handle . '-js-extra'), ['id' => $handle . '-js-extra']);
        echo $translations . $before;
        if ($item['src'] !== false && $item['src'] !== '') {
            $src = apply_filters('script_loader_src', _minn_asset_url($item['src'], $item['ver']), $handle);
            $strategy = (string) ($item['data']['strategy'] ?? '');
            $attributes = ['src' => $src, 'id' => $handle . '-js'] + (in_array($strategy, ['defer', 'async'], true) ? [$strategy => true, 'data-wp-strategy' => $strategy] : []);
            echo apply_filters('script_loader_tag', wp_get_script_tag($attributes), $handle, $src);
        }
        echo $after;
        $assets->markDone($handle);
    }
    return $list;
}

/** @internal a script's inline code at one position as its one element (each piece trimmed, joined by lines), or '' when it has none */
function _minn_inline_script_block(string $handle, string $position, array $code): string
{
    if ($code === []) {
        return '';
    }
    $id = "{$handle}-js-{$position}";
    $joined = implode("\n", array_map(static fn ($piece): string => trim((string) $piece, "\n\r "), $code));
    return wp_get_inline_script_tag($joined . _minn_source_url($id), ['id' => $id]);
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
    if ($handles === false || $handles === '' || $handles === []) {
        return _minn_print_scripts(false);
    }
    // Named handles print now, with what they depend on, whether or not they were queued.
    wp_scripts()->push();
    return _minn_print_script_list(_minn_assets('script')->toPrintHandles((array) $handles));
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

/**
 * The handles the reference registers itself and plugin code depends on: the MIT libraries the engine ships under
 * minn/assets (served from that folder so hosts can read the files off disk), the wp.* packages it reimplements with
 * the reference's dependency graph, then the site's own script pack (wp-content/minn-packages/wp-scripts: the GPL
 * packages the engine does not reimplement, installed by the site owner) for what is left.
 */
function wp_default_scripts($scripts)
{
    if (!$scripts instanceof WP_Scripts) {
        return;
    }
    $scripts->add('jquery-core', '/minn/assets/vendor/jquery/jquery.min.js', [], '3.7.1');
    $scripts->add('jquery-migrate', '/minn/assets/vendor/jquery/jquery-migrate.min.js', [], '3.4.1');
    $scripts->add('jquery', false, ['jquery-core', 'jquery-migrate'], '3.7.1');
    $wp = static fn (string $file) => '/minn/assets/wp/' . $file . '.js';
    foreach (['wp-polyfill' => [], 'wp-hooks' => [], 'wp-i18n' => ['wp-hooks'], 'wp-dom-ready' => [], 'wp-escape-html' => [], 'wp-url' => ['wp-polyfill'], 'wp-html-entities' => [], 'wp-a11y' => ['wp-dom-ready', 'wp-i18n', 'wp-polyfill'], 'wp-api-fetch' => ['wp-i18n', 'wp-url']] as $handle => $deps) {
        $scripts->add($handle, $wp(substr($handle, 3)), $deps, MINN_ENGINE_VERSION);
    }
    $contentDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
    foreach (Minn\Runtime\ScriptPack::handles($contentDir) as $handle => $row) {
        if (!wp_script_is($handle, 'registered')) {
            $scripts->add($handle, content_url(Minn\Runtime\ScriptPack::RELATIVE_DIR . '/' . $row['file']), $row['deps'], $row['ver']);
        }
    }
}

function wp_default_styles($styles)
{
}

/** The scripts registry; made, it tells wp_default_scripts (again on init, as the reference's constructor does). */
function wp_scripts()
{
    if (!isset($GLOBALS['wp_scripts']) || !$GLOBALS['wp_scripts'] instanceof WP_Scripts) {
        $GLOBALS['wp_scripts'] = new WP_Scripts(_minn_assets('script'));
        _minn_default_dependencies('wp_default_scripts', $GLOBALS['wp_scripts']);
    }
    return $GLOBALS['wp_scripts'];
}

/** The styles registry; made, it tells wp_default_styles (again on init). */
function wp_styles()
{
    if (!isset($GLOBALS['wp_styles']) || !$GLOBALS['wp_styles'] instanceof WP_Styles) {
        $GLOBALS['wp_styles'] = new WP_Styles(_minn_assets('style'));
        _minn_default_dependencies('wp_default_styles', $GLOBALS['wp_styles']);
    }
    return $GLOBALS['wp_styles'];
}

/** @internal a registry's defaults action, now and at init 0 (the engine registered the defaults themselves), its edits handed back */
function _minn_default_dependencies(string $action, WP_Dependencies $registry): void
{
    $tell = static function () use ($action, $registry): void {
        do_action_ref_array($action, [&$registry]);
        $registry->push();
    };
    $tell();
    if (!did_action('init')) {
        add_action('init', $tell, 0);
    }
}

/**
 * Fires the block-assets action on the front end. Plugins hang their
 * block-theme stylesheets off it (WooCommerce enqueues
 * woocommerce-blocktheme.css there), so leaving it unfired costs them
 * silently.
 */
function wp_common_block_scripts_and_styles()
{
    do_action('enqueue_block_assets');
}

/**
 * A block's own stylesheet (probe editor-styles): registered and enqueued
 * when the block renders where core blocks load their assets one by one,
 * otherwise on the page's asset hooks.
 */
function wp_enqueue_block_style($block_name, $args)
{
    $args = wp_parse_args($args, ['handle' => '', 'src' => '', 'deps' => [], 'ver' => false, 'media' => 'all']);
    $enqueue = static function ($content = '') use ($args) {
        if (!empty($args['src'])) {
            wp_register_style($args['handle'], $args['src'], $args['deps'], $args['ver'], $args['media']);
        }
        if (!empty($args['path'])) {
            wp_style_add_data($args['handle'], 'path', $args['path']);
        }
        wp_enqueue_style($args['handle']);
        return $content;
    };
    if (wp_should_load_separate_core_block_assets()) {
        add_filter("render_block_{$block_name}", static fn ($content) => $enqueue($content), 10, 1);
        return;
    }
    add_filter(did_action('wp_enqueue_scripts') ? 'wp_footer' : 'wp_enqueue_scripts', $enqueue);
    add_action('enqueue_block_assets', $enqueue);
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

/** @internal handle => the file URL a style was inlined from */
function _minn_inline_style_sources(): array
{
    $sources = Runtime::current()->get('inline_style_sources', []);
    return is_array($sources) ? $sources : [];
}

function wp_maybe_inline_styles()
{
    $styles = _minn_assets('style');
    $limit = (int) apply_filters('styles_inline_size_limit', 40000);
    $sources = _minn_inline_style_sources();
    foreach ($styles->withPath() as $handle => $path) {
        $size = is_file($path) ? (int) filesize($path) : 0;
        if ($size < 1 || $size > $limit) {
            continue;
        }
        $css = (string) file_get_contents($path);
        if ($css === '') {
            continue;
        }
        // The style stops being a link and becomes its own markup, but it
        // keeps the address it came from for the sourceURL trailer.
        $item = $styles->item($handle);
        $sources[$handle] = _minn_asset_url((string) ($item['src'] ?? ''), null);
        $styles->unsource($handle);
        wp_add_inline_style($handle, $css);
    }
    Runtime::current()->set('inline_style_sources', $sources);
}

function wp_remove_surrounding_empty_script_tags($contents)
{
    $body = Minn\Support\Html::scriptBody((string) $contents);
    if ($body !== null) {
        return $body;
    }
    $message = __('Expected string to start with script tag (without attributes) and end with script tag, with optional whitespace.');
    _doing_it_wrong(__FUNCTION__, $message, '6.4');
    return 'console.error(' . wp_json_encode(sprintf(__('Function %1$s used incorrectly in PHP.'), __FUNCTION__ . '()') . ' ' . $message) . ')';
}

/** A stylesheet's source: wp-admin's own relative to the installer while installing; the admin colour scheme has no screen on Minn. */
function wp_style_loader_src($src, $handle)
{
    if (wp_installing()) {
        return preg_replace('#^wp-admin/#', './', $src);
    }
    return $src;
}
