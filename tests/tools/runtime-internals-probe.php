<?php
/**
 * Runtime internals (probe runtime-internals): the core block bindings
 * sources each registered by its own function, the old cache reset, a
 * capability checked for a site, the metadata lazyloader, a menu item that
 * names itself as its parent, cron arrays from before version 2, the
 * script registration arguments and the warning for registering too
 * early, SimplePie's old autoloader, an attachment's missing metadata
 * made, and the speculative loading overrides. The cron option is saved
 * first and written back in place; the probe's attachment is removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$cronRow = $wpdb->get_row("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = 'cron'", ARRAY_A);
$made = [];
register_shutdown_function(static function () use ($cronRow, $wpdb, &$made): void {
    if (is_array($cronRow)) {
        $wpdb->update($wpdb->options, $cronRow, ['option_name' => 'cron']);
        wp_cache_delete('cron', 'options');
        wp_cache_delete('alloptions', 'options');
    }
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_attachment($id, true);
        }
    }
});
$heard = [];
add_action('deprecated_function_run', static function (...$args) use (&$heard) {
    $heard[] = ['deprecated', ...$args];
}, 10, 3);
add_action('doing_it_wrong_run', static function (...$args) use (&$heard) {
    $heard[] = ['wrong', ...$args];
}, 10, 3);
add_filter('deprecated_function_trigger_error', '__return_false');
add_filter('doing_it_wrong_trigger_error', '__return_false');
$listen = static function (Closure $call) use (&$heard) {
    $heard = [];
    $result = $call();
    return [$result, $heard];
};

// Each source's registering function by name (for its init hook) and called by name (so the proof count sees it).
$sources = [
    'core/pattern-overrides' => ['_register_block_bindings_pattern_overrides_source', static fn () => _register_block_bindings_pattern_overrides_source()],
    'core/post-data' => ['_register_block_bindings_post_data_source', static fn () => _register_block_bindings_post_data_source()],
    'core/post-meta' => ['_register_block_bindings_post_meta_source', static fn () => _register_block_bindings_post_meta_source()],
    'core/term-data' => ['_register_block_bindings_term_data_source', static fn () => _register_block_bindings_term_data_source()],
];
$source = static function (string $name) {
    $found = get_block_bindings_source($name);
    if ($found === null) {
        return null;
    }
    return [$found->name, $found->label, $found->uses_context];
};
$say('the core bindings sources', array_map(static function (string $name) use ($sources, $source) {
    [$hooked, $function] = $sources[$name];
    // The engine's probe runner fires no init, where the sources register; the function does it here.
    if ($source($name) === null) {
        $function();
    }
    $before = $source($name);
    unregister_block_bindings_source($name);
    $gone = $source($name);
    $result = $function();
    return [$name, has_action('init', $hooked), $before, $gone, $result, $source($name) === $before];
}, array_keys($sources)));

$say('the old cache reset', $listen(static function () {
    wp_cache_set('zz-local', 'v', 'zz_probe_group');
    wp_cache_add_global_groups('zz_probe_global');
    wp_cache_set('zz-global', 'v', 'zz_probe_global');
    $result = wp_cache_reset();
    return [$result, wp_cache_get('zz-local', 'zz_probe_group'), wp_cache_get('zz-global', 'zz_probe_global')];
}));

$say('a capability for a site', [
    user_can_for_site(1, 1, 'manage_options'), user_can_for_site(1, 99, 'manage_options'), user_can_for_site(0, 1, 'read'), user_can_for_site(1, 1, 'edit_post', 1),
    user_can_for_site(get_userdata(1), get_current_blog_id(), 'zz_cap'), user_can_for_site(1, 1, 'edit_user', 1), user_can_for_site(999999, 1, 'read'),
]);

class Zz_Probe_Lazyloader extends WP_Metadata_Lazyloader
{
    public function peek(): array
    {
        $settings = [];
        foreach ($this->settings as $type => $setting) {
            $callback = $setting['callback'];
            $settings[$type] = [$setting['filter'], is_array($callback) ? [$callback[0] === $this, $callback[1]] : $callback];
        }
        return [$this->pending_objects, $settings];
    }
}
$say('the metadata lazyloader', (static function () use ($listen) {
    $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids', 'number' => 2]);
    $first = (int) $terms[0];
    $queued = [];
    $notice = static function (...$args) use (&$queued) {
        $queued[] = [$args[0], $args[1], get_class($args[2])];
    };
    add_action('metadata_lazyloader_queued_objects', $notice, 10, 3);
    $lazy = new Zz_Probe_Lazyloader();
    $hooked = static fn (string $filter) => has_filter($filter, [$lazy, 'lazyload_meta_callback']);
    $error = static fn ($result) => is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message()] : $result;
    $out = [$lazy->peek()];
    $out[] = [$lazy->queue_objects('term', [$first]), $lazy->peek()[0], $hooked('get_term_metadata'), $queued];
    $out[] = [$lazy->queue_objects('term', [$first, 999999, '7']), $lazy->peek()[0]];
    $out[] = $error($lazy->queue_objects('zz', [1]));
    $out[] = [$lazy->queue_objects('comment', []), $lazy->peek()[0], $hooked('get_comment_metadata')];
    $out[] = [$lazy->lazyload_meta_callback('kept', 0, 'k', true, 'comment'), $lazy->peek()[0], $hooked('get_comment_metadata')];
    wp_cache_delete($first, 'term_meta');
    wp_cache_delete(999998, 'term_meta');
    $out[] = [wp_cache_get($first, 'term_meta') !== false, $lazy->lazyload_meta_callback('zzcheck', 999998, 'k', true, 'term'), $lazy->peek()[0], wp_cache_get($first, 'term_meta') !== false, wp_cache_get(999998, 'term_meta') !== false, $hooked('get_term_metadata')];
    $out[] = [$lazy->lazyload_meta_callback(null, 1, 'k', true, 'post'), $lazy->lazyload_meta_callback(null, 1, 'k', true, 'zz'), $lazy->lazyload_meta_callback(null, 1, 'k', true, 'blog')];
    $lazy->queue_objects('term', [$first]);
    $out[] = [$lazy->reset_queue('term'), $lazy->peek()[0], $hooked('get_term_metadata')];
    $out[] = $error($lazy->reset_queue('zz'));
    $lazy->queue_objects('term', [$first]);
    wp_cache_delete($first, 'term_meta');
    $out[] = [get_term_meta($first, 'zz_none', true), $lazy->peek()[0], wp_cache_get($first, 'term_meta') !== false, $hooked('get_term_metadata')];
    $lazy->queue_objects('term', [$first]);
    $out[] = $listen(static fn () => [$lazy->lazyload_term_meta('zzc'), $lazy->peek()[0]]);
    $lazy->queue_objects('comment', [1]);
    $out[] = $listen(static fn () => [$lazy->lazyload_comment_meta('zzc'), $lazy->peek()[0]]);
    remove_action('metadata_lazyloader_queued_objects', $notice, 10);
    $global = wp_metadata_lazyloader();
    $global->reset_queue('term');
    $pending = new ReflectionProperty($global, 'pending_objects');
    $pending->setAccessible(true);
    $out[] = [get_class($global), $global === wp_metadata_lazyloader(), isset($GLOBALS['wp_metadata_lazyloader']), wp_lazyload_term_meta([$first, 999999]), ($pending->getValue($global) ?? [])['term'] ?? null];
    $global->reset_queue('term');
    return $out;
})());

$say('a menu item that is its own parent', array_map(static fn ($data) => _wp_reset_invalid_menu_item_parent($data), [
    ['ID' => 41, 'menu_item_parent' => 41], ['ID' => 41, 'menu_item_parent' => '41'], ['ID' => '41', 'menu_item_parent' => 41, 'zz' => 1], ['ID' => 41, 'menu_item_parent' => 42],
    ['ID' => 41, 'menu_item_parent' => 0], ['ID' => 0, 'menu_item_parent' => 0], ['menu_item_parent' => 41], ['ID' => 41], [], ['ID' => 41, 'menu_item_parent' => -1], ['ID' => 41, 'menu_item_parent' => 'x'], ['ID' => 41, 'menu_item_parent' => 999999],
]));

$say('cron arrays from before version 2', (static function () {
    $original = get_option('cron');
    $stored = static function () {
        wp_cache_delete('cron', 'options');
        $value = get_option('cron');
        return is_array($value) ? $value : [gettype($value)];
    };
    $out = [];
    foreach ([
        ['version' => 2, 100 => ['zz_kept' => ['k' => ['schedule' => false, 'args' => []]]]],
        [],
        [100 => ['zz_old' => ['schedule' => 'hourly', 'args' => ['a'], 'interval' => 3600]], 200 => ['zz_one' => ['schedule' => false, 'args' => []], 'zz_two' => ['schedule' => 'daily', 'args' => [1, 'b'], 'interval' => 86400]]],
        ['version' => 1, 100 => ['zz_v1' => ['schedule' => false, 'args' => []]]],
    ] as $cron) {
        update_option('cron', $original);
        $out[] = [$cron, _upgrade_cron_array($cron), $stored() === $original ? 'unchanged' : $stored()];
    }
    update_option('cron', [300 => ['zz_unversioned' => ['schedule' => false, 'args' => ['x']]]]);
    $out[] = ['read', _get_cron_array(), $stored()];
    update_option('cron', $original);
    $out[] = $stored() === $original;
    return $out;
})());

$say('script registration arguments', (static function () use ($listen) {
    $out = [];
    foreach ([['strategy' => 'defer'], ['strategy' => 'async', 'in_footer' => true], ['in_footer' => false], ['in_footer' => 'yes'], ['fetchpriority' => 'high'], ['strategy' => 'zz'], ['fetchpriority' => 'zz'], [], ['strategy' => '', 'fetchpriority' => '']] as $i => $args) {
        $handle = 'zz-probe-args-' . $i;
        wp_register_script($handle, 'https://zz.example/' . $i . '.js');
        $result = $listen(static fn () => _wp_scripts_add_args_data(wp_scripts(), $handle, $args));
        $out[] = [$args, $result, [wp_scripts()->get_data($handle, 'strategy'), wp_scripts()->get_data($handle, 'group'), wp_scripts()->get_data($handle, 'fetchpriority')]];
        wp_deregister_script($handle);
    }
    $out[] = $listen(static fn () => _wp_scripts_add_args_data(wp_scripts(), 'zz-probe-not-registered', ['strategy' => 'defer']));
    return $out;
})());

$say('registered too early', (static function () use ($listen) {
    global $wp_actions;
    $saved = $wp_actions;
    // After init, as a loaded site is (the engine's probe runner fires none).
    $wp_actions['init'] = max(1, (int) ($wp_actions['init'] ?? 0));
    $out = [$listen(static fn () => _wp_scripts_maybe_doing_it_wrong('wp_enqueue_script', 'zz-h'))];
    $wp_actions['init'] = 0;
    foreach (['wp_enqueue_script' => 'zz-h', 'wp_register_style' => '', 'wp_add_inline_script' => 'zz <b>'] as $function => $handle) {
        $out[] = $listen(static fn () => _wp_scripts_maybe_doing_it_wrong($function, $handle));
    }
    $out[] = $listen(static fn () => _wp_scripts_maybe_doing_it_wrong('wp_enqueue_script'));
    foreach (['wp_enqueue_scripts', 'admin_enqueue_scripts', 'login_enqueue_scripts', 'enqueue_block_assets', 'wp_loaded'] as $action) {
        $wp_actions[$action] = 1;
        $out[] = [$action, $listen(static fn () => _wp_scripts_maybe_doing_it_wrong('wp_enqueue_script', 'zz-h'))];
        unset($wp_actions[$action]);
    }
    // Which of the asset functions warn when called this early.
    $calls = [
        'wp_register_script' => static fn () => wp_register_script('zz-early', 'https://zz.example/e.js'),
        'wp_enqueue_script' => static fn () => wp_enqueue_script('zz-early'),
        'wp_add_inline_script' => static fn () => wp_add_inline_script('zz-early', 'x()'),
        'wp_localize_script' => static fn () => wp_localize_script('zz-early', 'zzEarly', ['a' => 1]),
        'wp_script_add_data' => static fn () => wp_script_add_data('zz-early', 'zz', 'v'),
        'wp_script_is' => static fn () => wp_script_is('zz-early'),
        'wp_dequeue_script' => static fn () => wp_dequeue_script('zz-early'),
        'wp_deregister_script' => static fn () => wp_deregister_script('zz-early'),
        'wp_register_style' => static fn () => wp_register_style('zz-early', 'https://zz.example/e.css'),
        'wp_enqueue_style' => static fn () => wp_enqueue_style('zz-early'),
        'wp_add_inline_style' => static fn () => wp_add_inline_style('zz-early', 'a{}'),
        'wp_style_add_data' => static fn () => wp_style_add_data('zz-early', 'rtl', 'replace'),
        'wp_style_is' => static fn () => wp_style_is('zz-early'),
        'wp_dequeue_style' => static fn () => wp_dequeue_style('zz-early'),
        'wp_deregister_style' => static fn () => wp_deregister_style('zz-early'),
    ];
    $warned = [];
    foreach ($calls as $name => $call) {
        $warned[$name] = array_map(static fn ($entry) => $entry[0] . ' ' . $entry[1], $listen($call)[1]);
    }
    $out[] = $warned;
    foreach ($saved as $action => $count) {
        $wp_actions[$action] = $count;
    }
    wp_deregister_script('zz-early');
    wp_deregister_style('zz-early');
    return $out;
})());

if (!function_exists('wp_simplepie_autoload')) {
    require_once ABSPATH . WPINC . '/class-simplepie.php';
}
$say('SimplePie\'s old autoloader', [$listen(static fn () => wp_simplepie_autoload('SimplePie_Zz_Missing')), $listen(static fn () => wp_simplepie_autoload('Zz_Other'))]);

$say('missing attachment metadata made', (static function () use (&$made) {
    $out = [wp_maybe_generate_attachment_metadata(null), wp_maybe_generate_attachment_metadata((object) ['ID' => 0]), wp_maybe_generate_attachment_metadata((object) [])];
    $image = imagecreatetruecolor(400, 300);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));
    ob_start();
    imagepng($image);
    $bits = (string) ob_get_clean();
    $upload = wp_upload_bits('zz-maybe-meta.png', null, $bits);
    $made[] = $id = (int) wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Zz maybe meta', 'post_status' => 'inherit'], $upload['file']);
    $shape = static function (int $id) {
        $meta = wp_get_attachment_metadata($id);
        if (!is_array($meta)) {
            return $meta;
        }
        return [array_keys($meta), $meta['width'] ?? null, $meta['height'] ?? null, basename((string) ($meta['file'] ?? '')) === basename((string) get_attached_file($id)), array_map(static fn ($size) => [$size['width'], $size['height'], $size['mime-type']], $meta['sizes'] ?? [])];
    };
    $out[] = $shape($id);
    $out[] = [wp_maybe_generate_attachment_metadata(get_post($id)), $shape($id)];
    // Metadata already there is kept; the generated metadata goes back after, so removing the attachment removes its sizes too.
    $generated = wp_get_attachment_metadata($id);
    wp_update_attachment_metadata($id, ['width' => 1, 'height' => 1, 'file' => basename((string) get_attached_file($id)), 'sizes' => []]);
    $out[] = [wp_maybe_generate_attachment_metadata(get_post($id)), $shape($id)];
    wp_update_attachment_metadata($id, $generated);
    $made[] = $missing = (int) wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Zz maybe meta missing', 'post_status' => 'inherit'], wp_get_upload_dir()['basedir'] . '/zz-not-there.png');
    $out[] = [wp_maybe_generate_attachment_metadata(get_post($missing)), $shape($missing)];
    return $out;
})());

$say('speculative loading overrides', (static function () {
    putenv('ZZ_SPEC_ENV=from-env');
    putenv('ZZ_SPEC_BOTH=from-env');
    putenv('ZZ_SPEC_EMPTY=');
    define('ZZ_SPEC_BOTH', 'from-constant');
    define('ZZ_SPEC_INT', 5);
    define('ZZ_SPEC_BOOL', true);
    define('ZZ_SPEC_NULL', null);
    define('ZZ_SPEC_INT_ENV', 5);
    putenv('ZZ_SPEC_INT_ENV=from-env');
    $out = array_map(static fn ($name) => wp_get_speculative_loading_override($name), ['ZZ_SPEC_ENV', 'ZZ_SPEC_BOTH', 'ZZ_SPEC_EMPTY', 'ZZ_SPEC_INT', 'ZZ_SPEC_BOOL', 'ZZ_SPEC_NULL', 'ZZ_SPEC_NONE', 'ZZ_SPEC_INT_ENV']);
    putenv('ZZ_SPEC_INT_ENV');
    putenv('ZZ_SPEC_ENV');
    putenv('ZZ_SPEC_BOTH');
    putenv('ZZ_SPEC_EMPTY');
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
