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

/**
 * Warns (doing it wrong, 3.3.0) when an asset function runs before init
 * and before any of the enqueue actions, naming the handle when there is
 * one.
 */
function _wp_scripts_maybe_doing_it_wrong($function_name, $handle = '')
{
    if (did_action('init') || did_action('wp_enqueue_scripts') || did_action('admin_enqueue_scripts') || did_action('login_enqueue_scripts')) {
        return;
    }
    $message = sprintf(__('Scripts and styles should not be registered or enqueued until the %1$s, %2$s, or %3$s hooks.'), '<code>wp_enqueue_scripts</code>', '<code>admin_enqueue_scripts</code>', '<code>login_enqueue_scripts</code>');
    if ($handle) {
        $message .= ' ' . sprintf(__('This notice was triggered by the %s handle.'), '<code>' . $handle . '</code>');
    }
    _doing_it_wrong($function_name, $message, '3.3.0');
}

/** A registered script's arguments as its data: in_footer as group 1, then a strategy and a fetch priority (the registry refuses ones it does not know). */
function _wp_scripts_add_args_data($wp_scripts, $handle, $args)
{
    if (!empty($args['in_footer'])) {
        $wp_scripts->add_data($handle, 'group', 1);
    }
    // Through the registry, which refuses a strategy or fetch priority the reference refuses (probe dependencies-classes).
    foreach (['strategy', 'fetchpriority'] as $key) {
        if (!empty($args[$key])) {
            $wp_scripts->add_data($handle, $key, $args[$key]);
        }
    }
}

/** @internal a script registered with its arguments, without the too-early check (the caller made it) */
function _minn_register_script($handle, $src, $deps, $ver, $args)
{
    $args = is_array($args) ? $args : ['in_footer' => (bool) $args];
    $ok = _minn_assets('script')->register((string) $handle, $src === false ? false : (string) $src, _minn_dep_list($deps), $ver, $args);
    if ($ok) {
        _wp_scripts_add_args_data(wp_scripts(), $handle, $args);
    }
    return $ok;
}

function wp_register_script($handle, $src, $deps = [], $ver = false, $args = [])
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    return _minn_register_script($handle, $src, $deps, $ver, $args);
}

function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $args = [])
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    if ($src !== '') {
        _minn_register_script($handle, $src, $deps, $ver, $args);
    }
    _minn_assets('script')->enqueue((string) $handle);
}

function wp_deregister_script($handle)
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    _minn_assets('script')->deregister((string) $handle);
}

function wp_dequeue_script($handle)
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    _minn_assets('script')->dequeue((string) $handle);
}

function wp_script_is($handle, $status = 'enqueued')
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
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
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
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
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    return _minn_assets('style')->register((string) $handle, $src === false ? false : (string) $src, _minn_dep_list($deps), $ver, (string) $media);
}

function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    if ($src !== '') {
        _minn_assets('style')->register((string) $handle, $src === false ? false : (string) $src, _minn_dep_list($deps), $ver, (string) $media);
    }
    _minn_assets('style')->enqueue((string) $handle);
}

function wp_deregister_style($handle)
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    _minn_assets('style')->deregister((string) $handle);
}

function wp_dequeue_style($handle)
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
    _minn_assets('style')->dequeue((string) $handle);
}

function wp_style_is($handle, $status = 'enqueued')
{
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
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
    _wp_scripts_maybe_doing_it_wrong(__FUNCTION__, $handle);
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
    return wp_styles()->do_items($handles);
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

/** @internal the engine's own stylesheets, printed where a theme's would be */
function _minn_print_engine_styles()
{
    echo (string) Runtime::current()->get('engine_head_styles', '');
}

/** The head's scripts; wp_print_scripts fires first, once a request (probe script-printing). */
function wp_print_head_scripts()
{
    if (!did_action('wp_print_scripts')) {
        do_action('wp_print_scripts');
    }
    return wp_scripts()->do_head_items();
}

/** Every queued script, the footer's too, or the handles given with what they depend on, queued or not (probe dependencies-classes). */
function wp_print_scripts($handles = false)
{
    do_action('wp_print_scripts');
    return wp_scripts()->do_items($handles === '' ? false : $handles);
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
    return wp_scripts()->do_footer_items();
}

function print_late_styles()
{
    return wp_print_styles();
}

function print_head_scripts()
{
    return wp_print_head_scripts();
}

/**
 * A classic theme's block styles loaded on demand (wp_default_styles 0):
 * the page is buffered for enhancement, core block assets load separately
 * and on demand, and styles printed late are hoisted into the head. A block
 * theme, a buffer filtered off, or a REST request stops it part way (probe
 * script-printing).
 */
function wp_load_classic_theme_block_styles_on_demand()
{
    if (wp_is_block_theme()) {
        return;
    }
    add_filter('wp_should_output_buffer_template_for_enhancement', '__return_true', 0);
    if (!wp_should_output_buffer_template_for_enhancement()) {
        return;
    }
    add_filter('should_load_separate_core_block_assets', '__return_true', 0);
    if (wp_is_rest_endpoint()) {
        return;
    }
    add_filter('should_load_block_assets_on_demand', '__return_true', 0);
    add_action('wp_template_enhancement_output_buffer_started', 'wp_hoist_late_printed_styles');
}

/** The footer prints its scripts alone; the styles queued late print before the head closes, through the enhancement buffer. */
function wp_hoist_late_printed_styles()
{
    $late = '';
    remove_action('wp_print_footer_scripts', '_wp_footer_scripts');
    add_action('wp_print_footer_scripts', static function () use (&$late) {
        ob_start();
        print_late_styles();
        $late = (string) ob_get_clean();
        print_footer_scripts();
    });
    add_filter('wp_template_enhancement_output_buffer', static function ($buffer) use (&$late) {
        return $late === '' ? $buffer : _minn_before_head_closer((string) $buffer, $late . "\n");
    });
}

/** @internal the text put just before the document's first HEAD closer (a closer inside a script or comment is not one); the document as it was without one */
function _minn_before_head_closer(string $html, string $text): string
{
    $tags = new Minn\Html\Tags($html);
    while ($tags->nextTag(['tag_name' => 'HEAD', 'tag_closers' => 'visit'])) {
        if ($tags->isCloser()) {
            $start = $tags->tokenSpan()[0];
            return substr($html, 0, $start) . $text . substr($html, $start);
        }
    }
    return $html;
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
        // Made, it tells wp_default_scripts itself (WP_Scripts::init).
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

/** @internal a registry's defaults action told again at init 0 (the registry told it when made), its edits handed back */
function _minn_default_dependencies(string $action, WP_Dependencies $registry): void
{
    if (did_action('init')) {
        return;
    }
    add_action('init', static function () use ($action, $registry): void {
        do_action_ref_array($action, [&$registry]);
        $registry->push();
    }, 0);
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
 * A block's own stylesheet (probes editor-styles, content-blocks):
 * registered and enqueued when a block of that name renders (one
 * render_block filter per call) where core blocks load their assets one by
 * one, otherwise on the page's asset hooks.
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
        add_filter('render_block', static fn ($content, $block) => ($block['blockName'] ?? null) === $block_name ? $enqueue($content) : $content, 10, 2);
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
    return wp_script_modules()->set_translations((string) $id, (string) $domain, (string) $path);
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
    $body = Minn\Html::scriptBody((string) $contents);
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

/** The suffix a core script file takes: none under SCRIPT_DEBUG, else .min (always .min for "dev"). */
function wp_scripts_get_suffix($type = '')
{
    return $type !== 'dev' && defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
}

/** The jQuery UI datepicker, when it is queued, told the site's month and day names, date format, first day and direction. */
function wp_localize_jquery_ui_datepicker()
{
    global $wp_locale;
    if (!wp_script_is('jquery-ui-datepicker', 'enqueued')) {
        return;
    }
    $format = str_replace(['d', 'j', 'l', 'z', 'F', 'M', 'n', 'm', 'Y', 'y'], ['dd', 'd', 'DD', 'o', 'MM', 'M', 'm', 'mm', 'yy', 'y'], (string) get_option('date_format'));
    $defaults = wp_json_encode(['closeText' => __('Close'), 'currentText' => __('Today'), 'monthNames' => array_values($wp_locale->month), 'monthNamesShort' => array_values($wp_locale->month_abbrev), 'nextText' => __('Next'), 'prevText' => __('Previous'), 'dayNames' => array_values($wp_locale->weekday), 'dayNamesShort' => array_values($wp_locale->weekday_abbrev), 'dayNamesMin' => array_values($wp_locale->weekday_initial), 'dateFormat' => $format, 'firstDay' => absint(get_option('start_of_week')), 'isRTL' => $wp_locale->is_rtl()], JSON_UNESCAPED_SLASHES);
    wp_add_inline_script('jquery-ui-datepicker', "jQuery(function(jQuery){jQuery.datepicker.setDefaults({$defaults});});");
}

/** The script concatenation and compression globals: off, as the front end always has them. */
function script_concat_settings()
{
    global $concatenate_scripts, $compress_scripts, $compress_css;
    $concatenate_scripts ??= false;
    $compress_scripts ??= false;
    $compress_css ??= false;
}

// Script loader helpers (probe script-loader-helpers).

/** A data-* attribute's dataset name as the DOM gives it (data-wp-on--click is wpOn-Click); null for any other name, or one holding a space, slash, equals sign or >. */
function wp_js_dataset_name(string $html_attribute_name): ?string
{
    if (strncasecmp($html_attribute_name, 'data-', 5) !== 0 || strpbrk($html_attribute_name, " \t\n\f\r/=>") !== false) {
        return null;
    }
    return preg_replace_callback('/-([a-z])/', static fn (array $m) => strtoupper($m[1]), strtolower(substr($html_attribute_name, 5)));
}

/** The data-* attribute a dataset name stands for, each capital letter a hyphen and its lowercase; null for a name holding a space, slash, equals sign or >. */
function wp_html_custom_data_attribute_name(string $js_dataset_name): ?string
{
    if (strpbrk($js_dataset_name, " \t\n\f\r/=>") !== false) {
        return null;
    }
    return 'data-' . preg_replace_callback('/[A-Z]/', static fn (array $m) => '-' . strtolower($m[0]), $js_dataset_name);
}

/**
 * A stylesheet's relative url() references joined to the folder it is
 * served from, then made host-relative. A reference starting with a slash,
 * #, data: or http(s): is left alone; one leading ./ is dropped.
 */
function _wp_normalize_relative_css_links($css, $stylesheet_url)
{
    $folder = dirname((string) $stylesheet_url);
    return preg_replace_callback('/url\(\s*([\'"]?)\s*([^\'")]+)/', static function (array $m) use ($folder): string {
        $url = $m[2];
        if (preg_match('#^(?:/|\#|data:|https?:)#', $url) === 1) {
            return $m[0];
        }
        return substr($m[0], 0, -strlen($url)) . wp_make_link_relative($folder . '/' . (str_starts_with($url, './') ? substr($url, 2) : $url));
    }, (string) $css);
}

/** The theme.json nodes outside any block: a node whose path names blocks is dropped, the others keep their keys. */
function wp_filter_out_block_nodes($nodes)
{
    return array_filter((array) $nodes, static fn ($node) => !in_array('blocks', (array) ($node['path'] ?? []), true));
}

/**
 * The inline loader that writes a polyfill's script tag when its test
 * fails, one per registered handle in order. A source that is not absolute
 * gets the registry's base URL; a version is added when it has one; a
 * source script_loader_src empties is skipped.
 */
function wp_get_script_polyfill($scripts, $tests)
{
    $polyfill = '';
    foreach ((array) $tests as $test => $handle) {
        $item = $scripts->registered[$handle] ?? null;
        if ($item === null) {
            continue;
        }
        $src = preg_match('#^(https?:)?//#', (string) $item->src) === 1 ? (string) $item->src : $scripts->base_url . $item->src;
        $src = apply_filters('script_loader_src', empty($item->ver) ? $src : add_query_arg('ver', $item->ver, $src), $handle);
        $polyfill .= $src ? '( ' . $test . ' ) || document.write( \'<script src="' . esc_url($src) . '"></scr\' + \'ipt>\' );' : '';
    }
    return $polyfill;
}

/** A script list with prototype moved to just before jquery when it came after it. */
function wp_prototype_before_jquery($js_array)
{
    $jquery = array_search('jquery', $js_array, true);
    $prototype = array_search('prototype', $js_array, true);
    if ($jquery === false || $prototype === false || $prototype < $jquery) {
        return $js_array;
    }
    array_splice($js_array, $prototype, 1);
    array_splice($js_array, $jquery, 0, 'prototype');
    return $js_array;
}

/** @internal a concatenation list as load-*.php query chunks of 128 characters */
function _minn_concat_chunks(string $concat): string
{
    $out = '';
    foreach (str_split($concat, 128) as $key => $chunk) {
        $out .= "&load%5Bchunk_{$key}%5D={$chunk}";
    }
    return $out;
}

/** The concatenated scripts: their held inline code, one load-scripts.php tag for the list, then the markup kept out of the concatenation. */
function _print_scripts()
{
    $scripts = wp_scripts();
    $concat = trim((string) $scripts->concat, ', ');
    if ($concat !== '') {
        if ((string) $scripts->print_code !== '') {
            wp_print_inline_script_tag($scripts->print_code . "\n//# sourceURL=js-inline-concat-" . rawurlencode($concat));
        }
        $src = $scripts->base_url . '/wp-admin/load-scripts.php?c=' . (empty($GLOBALS['compress_scripts']) ? 0 : 1) . _minn_concat_chunks($concat) . '&ver=' . $scripts->default_version;
        wp_print_script_tag(['src' => esc_url($src)]);
    }
    echo (string) $scripts->print_html;
}

/** The concatenated styles: one load-styles.php link for the list in the registry's text direction, their held inline CSS, then the markup kept out of the concatenation. */
function _print_styles()
{
    $styles = wp_styles();
    $concat = trim((string) $styles->concat, ', ');
    if ($concat !== '') {
        $href = $styles->base_url . '/wp-admin/load-styles.php?c=' . (empty($GLOBALS['compress_css']) ? 0 : 1) . '&dir=' . $styles->text_direction . _minn_concat_chunks($concat) . '&ver=' . $styles->default_version;
        echo "<link rel='stylesheet' href='" . esc_attr($href) . "' media='all' />\n";
        if ((string) $styles->print_code !== '') {
            echo "<style>\n" . $styles->print_code . "\n/*# sourceURL=css-inline-concat-" . rawurlencode($concat) . " */\n</style>\n";
        }
    }
    echo (string) $styles->print_html;
}

/** The autosave, mce-view and word-count scripts told their settings: the autosave interval and site, the registered shortcodes, and words as the count type. */
function wp_just_in_time_script_localization()
{
    $shortcodes = array_keys((array) ($GLOBALS['shortcode_tags'] ?? []));
    wp_localize_script('autosave', 'autosaveL10n', ['autosaveInterval' => AUTOSAVE_INTERVAL, 'blog_id' => get_current_blog_id()]);
    wp_localize_script('mce-view', 'mceViewL10n', ['shortcodes' => $shortcodes]);
    wp_localize_script('word-count', 'wordCountL10n', ['type' => _x('words', 'word count type'), 'shortcodes' => $shortcodes]);
}

/**
 * The style engine's stored rules, each store as inline CSS on a handle of
 * its own, registered without a source and enqueued: core-block-supports for
 * block supports (headed by a comment when prettified), wp-style-engine-{context}
 * for the rest. Prettified when asked, or under SCRIPT_DEBUG. Not hooked:
 * the engine prints the block-supports store inside the global styles.
 */
function wp_enqueue_stored_styles($options = [])
{
    $prettify = (bool) ($options['prettify'] ?? (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG));
    foreach (array_keys(WP_Style_Engine_CSS_Rules_Store::get_stores()) as $context) {
        $css = (string) wp_style_engine_get_stylesheet_from_context($context, ['prettify' => $prettify]);
        if ($css === '') {
            continue;
        }
        $handle = $context === 'block-supports' ? 'core-block-supports' : 'wp-style-engine-' . $context;
        wp_register_style($handle, false);
        wp_add_inline_style($handle, ($prettify && $context === 'block-supports' ? "/**\n * Core styles: block-supports\n */\n" : '') . $css);
        wp_enqueue_style($handle);
    }
}

/**
 * Block style variations' assets: inline CSS joins the block's own
 * stylesheet (wp-block-{name} when that is registered, wp-block-library
 * otherwise), every call; a stylesheet is enqueued now, or as a block of that
 * name renders when block assets load on demand.
 */
function enqueue_block_styles_assets()
{
    foreach (WP_Block_Styles_Registry::get_instance()->get_all_registered() as $block_name => $styles) {
        foreach ((array) $styles as $style) {
            if (isset($style['style_handle'])) {
                _minn_enqueue_block_style_handle((string) $block_name, (string) $style['style_handle']);
            }
            if (isset($style['inline_style'])) {
                $handle = 'wp-block-' . str_replace('core/', '', (string) $block_name);
                wp_add_inline_style(wp_style_is($handle, 'registered') ? $handle : 'wp-block-library', $style['inline_style']);
            }
        }
    }
}

/** @internal a block style variation's stylesheet, enqueued now or as a block of its name renders */
function _minn_enqueue_block_style_handle(string $block_name, string $handle): void
{
    if (!wp_should_load_block_assets_on_demand()) {
        wp_enqueue_style($handle);
        return;
    }
    add_filter('render_block', static function ($html, $block) use ($block_name, $handle) {
        if (($block['blockName'] ?? null) === $block_name) {
            wp_enqueue_style($handle);
        }
        return $html;
    }, 10, 2);
}

/** Every registered block's style and script handles enqueued, unless block assets load on demand. */
function wp_enqueue_registered_block_scripts_and_styles()
{
    if (wp_should_load_block_assets_on_demand()) {
        return;
    }
    foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $block_type) {
        array_map('wp_enqueue_style', (array) $block_type->style_handles);
        array_map('wp_enqueue_script', (array) $block_type->script_handles);
    }
}

/** The editor's development-build scripts: Minn ships none, so nothing is registered (outside development mode the reference registers none either). */
function wp_register_development_scripts($scripts)
{
}
