<?php
/**
 * Behaviour probe for the script modules API: registration, the queue,
 * the import map, preloads, the printed module tags, module data, and where
 * the class hooks itself. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace($home, '{home}', $v) : $v;
$modules = wp_script_modules();
$say('class', get_class($modules));
wp_default_script_modules();
// The engine serves its own module files; each default module's URL is masked to its id on both stacks.
$defaultIds = ['@wordpress/interactivity', '@wordpress/interactivity-router', '@wordpress/a11y', '@wordpress/block-library/navigation/view', '@wordpress/block-library/image/view', '@wordpress/block-library/query/view', '@wordpress/block-library/search/view', '@wordpress/block-library/file/view', '@wordpress/block-library/form/view', '@wordpress/block-library/accordion/view', '@wordpress/block-library/tabs/view', '@wordpress/block-library/playlist/view', '@wordpress/block-library/comments-pagination/view', '@wordpress/block-library/query-total/view', '@wordpress/block-library/gallery/view'];
$masks = [];
foreach ($defaultIds as $id) {
    $registered = $modules->get_registered($id);
    if ($registered !== null) {
        $masks['#' . preg_quote($registered['src'], '#') . '\?ver=[^"&]+#'] = '{module:' . $id . '}';
    }
}
$mask = static fn (string $text): string => (string) preg_replace(array_keys($masks), array_values($masks), $text);
$capture = static function (callable $fn) use ($rel, $mask): string {
    ob_start();
    $fn();
    return $mask($rel((string) ob_get_clean()));
};
$say('defaults registered', array_map(static fn ($id) => $modules->get_registered($id) === null ? null : array_map(static fn ($v) => is_string($v) ? '{value}' : $v, $modules->get_registered($id)) + ['src_is_absolute_or_root' => (bool) preg_match('#^(https?:)?/#', $modules->get_registered($id)['src'])], array_combine($ids = ['@wordpress/interactivity', '@wordpress/interactivity-router', '@wordpress/a11y', '@wordpress/block-library/navigation/view', '@wordpress/block-library/image/view', '@wordpress/block-library/query/view', '@wordpress/block-library/search/view', '@wordpress/block-library/file/view', '@wordpress/block-library/form/view', '@wordpress/block-library/accordion/view', '@wordpress/block-library/tabs/view', '@wordpress/block-library/playlist/view', '@wordpress/block-library/comments-pagination/view', '@wordpress/block-library/query-total/view', '@wordpress/block-library/gallery/view', '@wordpress/block-library/nope/view'], $ids)));


$hooks = [];
foreach (['wp_head', 'wp_footer', 'admin_print_footer_scripts'] as $hook) {
    foreach (['print_import_map', 'print_head_enqueued_script_modules', 'print_script_module_preloads', 'print_enqueued_script_modules', 'print_script_module_data', 'print_a11y_script_module_html', 'print_script_module_translations'] as $method) {
        $hooks[] = [$hook, $method, has_action($hook, [$modules, $method])];
    }
}
$say('hooks', $hooks);

// Before anything is enqueued.
$say('import map empty', $capture(static fn () => $modules->print_import_map()));
$say('preloads empty', $capture(static fn () => $modules->print_script_module_preloads()));
$say('enqueued empty', $capture(static fn () => $modules->print_enqueued_script_modules()));

// Registration shapes.
$say('register returns', wp_register_script_module('minn-probe/a', '/wp-content/plugins/minn-probe/a.js', [], '1.0'));
$say('register again returns', wp_register_script_module('minn-probe/a', '/wp-content/plugins/minn-probe/other.js', [], '2.0'));
wp_register_script_module('minn-probe/b', 'https://cdn.example.invalid/b.js', ['minn-probe/a'], false);
wp_register_script_module('minn-probe/c', 'c.js', [['id' => 'minn-probe/b', 'import' => 'dynamic'], ['id' => 'minn-probe/a']], null);
wp_register_script_module('minn-probe/d', '/d.js', ['minn-probe/missing'], '3');
wp_register_script_module('minn-probe/e', '//cdn.example.invalid/e.js?x=1', [], '1.0');
$say('enqueue unregistered returns', wp_enqueue_script_module('minn-probe/unregistered'));
$say('enqueue with src returns', wp_enqueue_script_module('minn-probe/f', '/f.js', [], '1.0'));
wp_enqueue_script_module('minn-probe/c');
$say('import map after enqueue c', $capture(static fn () => $modules->print_import_map()));
$say('preloads after enqueue c', $capture(static fn () => $modules->print_script_module_preloads()));
$say('enqueued after enqueue c', $capture(static fn () => $modules->print_enqueued_script_modules()));
$say('enqueued again prints again?', $capture(static fn () => $modules->print_enqueued_script_modules()));
$say('head enqueued', $capture(static fn () => $modules->print_head_enqueued_script_modules()));
$say('get_registered', [$modules->get_registered('minn-probe/c'), $modules->get_registered('minn-probe/nope')]);
$say('get_queue', $modules->get_queue());
wp_enqueue_script_module('minn-probe/d');
wp_enqueue_script_module('minn-probe/e');
wp_enqueue_script_module('minn-probe/unregistered');
$say('enqueued with missing dep and unregistered', $capture(static fn () => $modules->print_enqueued_script_modules()));
$say('import map with missing dep', $capture(static fn () => $modules->print_import_map()));
wp_dequeue_script_module('minn-probe/d');
$say('dequeue then print', $capture(static fn () => $modules->print_enqueued_script_modules()));
wp_deregister_script_module('minn-probe/e');
$say('deregister then print', $capture(static fn () => $modules->print_enqueued_script_modules()));
wp_deregister_script_module('minn-probe/e');
$say('deregister unknown', 'ok');

// Arguments: fetchpriority and in_footer.
wp_register_script_module('minn-probe/hi', '/hi.js', [], '1', ['fetchpriority' => 'high']);
wp_register_script_module('minn-probe/auto', '/auto.js', [], '1', ['fetchpriority' => 'auto']);
wp_register_script_module('minn-probe/bad', '/bad.js', [], '1', ['fetchpriority' => 'nope']);
wp_register_script_module('minn-probe/foot', '/foot.js', ['minn-probe/hi'], '1', ['in_footer' => true]);
wp_register_script_module('minn-probe/head', '/head.js', [], '1', ['in_footer' => false]);
foreach (['hi', 'auto', 'bad', 'foot', 'head'] as $id) {
    wp_enqueue_script_module('minn-probe/' . $id);
}
$say('head print with args', $capture(static fn () => $modules->print_head_enqueued_script_modules()));
$say('footer print with args', $capture(static fn () => $modules->print_enqueued_script_modules()));
$say('preloads with args', $capture(static fn () => $modules->print_script_module_preloads()));
$say('get_registered with args', [$modules->get_registered('minn-probe/hi'), $modules->get_registered('minn-probe/bad'), $modules->get_registered('minn-probe/foot')]);
$say('set_fetchpriority', [$modules->set_fetchpriority('minn-probe/auto', 'high'), $modules->set_fetchpriority('minn-probe/nope', 'high'), $modules->set_fetchpriority('minn-probe/auto', 'weird')]);
$say('set_in_footer', [$modules->set_in_footer('minn-probe/head', true), $modules->set_in_footer('minn-probe/nope', true)]);
$say('after setters head', $capture(static fn () => $modules->print_head_enqueued_script_modules()));
$say('after setters footer', $capture(static fn () => $modules->print_enqueued_script_modules()));

// Module data.
add_filter('script_module_data_minn-probe/a', static fn ($data) => $data + ['answer' => 42, 'html' => '<b>&amp;</b>', 'slash' => 'a/b', 'q' => "it's \"x\""]);
add_filter('script_module_data_minn-probe/empty', static fn ($data) => $data);
wp_register_script_module('minn-probe/empty', '/empty.js', [], '1');
wp_enqueue_script_module('minn-probe/a');
wp_enqueue_script_module('minn-probe/empty');
$say('module data', $capture(static fn () => $modules->print_script_module_data()));
$say('module data again', $capture(static fn () => $modules->print_script_module_data()));
wp_interactivity_state('minn-probe', ['word' => 'hello', 'nested' => ['a' => 1]]);
wp_interactivity_config('minn-probe', ['flag' => true]);
wp_enqueue_script_module('@wordpress/interactivity');
$say('interactivity module data', $capture(static fn () => $modules->print_script_module_data()));
$say('interactivity import map', $capture(static fn () => $modules->print_import_map()));
$say('interactivity preloads', $capture(static fn () => $modules->print_script_module_preloads()));
$say('interactivity enqueued', $capture(static fn () => $modules->print_enqueued_script_modules()));

// A module depending on the interactivity runtime, printed the way a block's view module is.
wp_register_script_module('minn-probe/view', '/view.js', ['@wordpress/interactivity'], '1.0');
wp_enqueue_script_module('minn-probe/view');
$say('interactivity dependent print', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_enqueued_script_modules())));
$say('interactivity dependent import map', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_import_map())));
$say('interactivity dependent preloads', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_script_module_preloads())));
wp_register_script_module('minn-probe/router', '/router.js', ['@wordpress/interactivity-router'], '1.0');
wp_enqueue_script_module('minn-probe/router');
$say('router dependent print', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_enqueued_script_modules())));
$say('router dependent a11y', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_a11y_script_module_html())));
$say('router dependent data', preg_replace('/\?ver=[0-9a-f]{20}/', '?ver={hash}', $capture(static fn () => $modules->print_script_module_data())));

// Defaults the reference registers.
$defaults = [];
foreach (['@wordpress/interactivity', '@wordpress/interactivity-router', '@wordpress/a11y', '@wordpress/block-library/navigation/view', '@wordpress/block-library/image/view', '@wordpress/block-library/query/view', '@wordpress/block-library/search/view', '@wordpress/block-library/file/view', '@wordpress/block-library/form/view', '@wordpress/block-library/accordion/view', '@wordpress/block-library/tabs/view', '@wordpress/block-library/playlist/view', '@wordpress/block-library/comments-pagination/view', '@wordpress/block-library/query-total/view'] as $id) {
    $modules->dequeue($id);
}
foreach (['@wordpress/interactivity', '@wordpress/interactivity-router', '@wordpress/a11y', '@wordpress/block-library/navigation/view', '@wordpress/block-library/image/view', '@wordpress/block-library/query/view', '@wordpress/block-library/search/view', '@wordpress/block-library/file/view', '@wordpress/block-library/form/view', '@wordpress/block-library/accordion/view', '@wordpress/block-library/tabs/view', '@wordpress/block-library/playlist/view', '@wordpress/block-library/comments-pagination/view', '@wordpress/block-library/query-total/view', '@wordpress/block-library/nope/view'] as $id) {
    wp_enqueue_script_module($id);
    $out = $capture(static fn () => $modules->print_enqueued_script_modules());
    $defaults[$id] = preg_replace('/\?ver=[0-9a-f]+/', '?ver={hash}', $out);
    wp_dequeue_script_module($id);
}
$say('defaults', $defaults);
$say('default import map', preg_replace('/\?ver=[0-9a-f]+/', '?ver={hash}', $capture(static function () use ($modules) {
    wp_enqueue_script_module('@wordpress/block-library/navigation/view');
    wp_enqueue_script_module('@wordpress/interactivity-router');
    $modules->print_import_map();
    $modules->print_script_module_preloads();
})));
$say('a11y html', $capture(static fn () => $modules->print_a11y_script_module_html()));

// Block registration from metadata.
$say('register_block_script_module_id missing', register_block_script_module_id(['name' => 'minn-probe/x'], 'viewScriptModule'));
$dir = sys_get_temp_dir() . '/minn-probe-block-' . getmypid();
@mkdir($dir);
file_put_contents($dir . '/view.js', 'export default 1;');
file_put_contents($dir . '/view.asset.php', "<?php return ['dependencies' => ['@wordpress/interactivity'], 'version' => 'abc123', 'type' => 'module'];");
$id = register_block_script_module_id(['name' => 'minn-probe/x', 'file' => $dir . '/block.json', 'viewScriptModule' => 'file:./view.js'], 'viewScriptModule');
$say('register_block_script_module_id', $id);
wp_enqueue_script_module($id);
$say('block module print', preg_replace('#src="[^"]*/minn-probe-block-\d+/#', 'src="{dir}/', $capture(static fn () => $modules->print_enqueued_script_modules())));
$say('block module import map', $capture(static fn () => $modules->print_import_map()));
$id2 = register_block_script_module_id(['name' => 'minn-probe/y', 'file' => $dir . '/block.json', 'viewScriptModule' => ['file:./view.js', 'file:./view.js']], 'viewScriptModule', 1);
$say('register_block_script_module_id index', $id2);
$say('register_block_script_module_id bare id', register_block_script_module_id(['name' => 'minn-probe/z', 'viewScriptModule' => 'minn-probe/a'], 'viewScriptModule'));
array_map('unlink', glob($dir . '/*') ?: []);
@rmdir($dir);

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
