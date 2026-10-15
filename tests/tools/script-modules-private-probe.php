<?php
/**
 * Behaviour probe for the script modules object's private reads, which
 * plugins reach through reflection: Query Monitor's assets panel calls
 * get_marked_for_enqueue(), get_dependencies() and get_src() on every
 * request it collects, and a missing method fails that request. Their
 * signatures, then what each answers for modules with static, dynamic and
 * missing dependencies, each kind of version, and an id never registered.
 * Same protocol as api-probe.php.
 */
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static function ($value) use (&$rel, $home) {
    if (is_array($value)) {
        return array_map($rel, $value);
    }
    return is_string($value) ? str_replace($home, '{home}', $value) : $value;
};
// A module with a missing dependency is part of the case; when its notice comes is not.
add_filter('doing_it_wrong_trigger_error', '__return_false');

$modules = wp_script_modules();
$reflector = new ReflectionClass($modules);
$signature = static function (string $name) use ($reflector): ?array {
    if (!$reflector->hasMethod($name)) {
        return null;
    }
    $method = $reflector->getMethod($name);
    return [
        'private' => $method->isPrivate(),
        'static' => $method->isStatic(),
        'parameters' => array_map(static fn (ReflectionParameter $p): array => [
            'name' => $p->getName(),
            'type' => (string) $p->getType(),
            'optional' => $p->isOptional(),
            'default' => $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null,
        ], $method->getParameters()),
        'returns' => (string) $method->getReturnType(),
    ];
};
$say('signatures', array_map($signature, ['get_marked_for_enqueue' => 'get_marked_for_enqueue', 'get_dependencies' => 'get_dependencies', 'get_src' => 'get_src']));

$marked = $reflector->getMethod('get_marked_for_enqueue');
$dependencies = $reflector->getMethod('get_dependencies');
$src = $reflector->getMethod('get_src');

$say('nothing queued yet: marked', $rel($marked->invoke($modules)));

$modules->register('minn-probe/base', '/wp-content/plugins/minn-probe/base.js', [], '1.0');
$modules->register('minn-probe/lazy', '/wp-content/plugins/minn-probe/lazy.js', [], false);
$modules->register('minn-probe/shared', home_url('/shared.js'), [], null);
$modules->register('minn-probe/deep', '/wp-content/plugins/minn-probe/deep.js', ['minn-probe/base']);
$modules->register('minn-probe/main', '/wp-content/plugins/minn-probe/main.js', ['minn-probe/deep', ['id' => 'minn-probe/lazy', 'import' => 'dynamic'], 'minn-probe/shared'], '2.0', ['in_footer' => true, 'fetchpriority' => 'low']);
$modules->register('minn-probe/broken', '/wp-content/plugins/minn-probe/broken.js', ['minn-probe/nowhere'], '1.0');
$modules->enqueue('minn-probe/main');
$modules->enqueue('minn-probe/broken');
$modules->enqueue('minn-probe/unregistered');

$say('marked for enqueue: queued, registered and every dependency registered', $rel($marked->invoke($modules)));
$say('dependencies, default import types', $rel($dependencies->invoke($modules, ['minn-probe/main'])));
$say('dependencies, static only', $rel($dependencies->invoke($modules, ['minn-probe/main'], ['static'])));
$say('dependencies, dynamic only', $rel($dependencies->invoke($modules, ['minn-probe/main'], ['dynamic'])));
$say('dependencies of several, one unknown and one broken', $rel($dependencies->invoke($modules, ['minn-probe/deep', 'minn-probe/nowhere', 'minn-probe/broken'])));
$say('dependencies of none', $rel($dependencies->invoke($modules, [])));
$say('src: version string, false, null, a full URL', $rel(array_map(static fn ($id) => $src->invoke($modules, $id), ['minn-probe/base', 'minn-probe/lazy', 'minn-probe/shared', 'minn-probe/main'])));
$say('src of an id never registered', $src->invoke($modules, 'minn-probe/unregistered'));
add_filter('script_module_loader_src', $filter = static fn ($url, $id) => $id === 'minn-probe/base' ? $url . '&filtered=1' : $url, 10, 2);
$say('src through script_module_loader_src', $rel($src->invoke($modules, 'minn-probe/base')));
remove_filter('script_module_loader_src', $filter, 10);

// What Query Monitor builds from them: each marked module and its dependencies, with its src.
$all = array_merge($marked->invoke($modules), $dependencies->invoke($modules, array_keys($marked->invoke($modules))));
$say('as Query Monitor reads them', $rel(array_map(static fn ($id) => ['id' => $id, 'src' => $src->invoke($modules, $id), 'version' => $all[$id]['version'] ?? '', 'dependencies' => array_values(wp_list_pluck($all[$id]['dependencies'], 'id'))], array_keys($all))));

// The import map lists them in the order get_dependencies() gives: a module's own dependencies, then theirs.
ob_start();
$modules->print_import_map();
$say('import map for those modules', $rel((string) ob_get_clean()));

foreach (['minn-probe/main', 'minn-probe/broken', 'minn-probe/unregistered', 'minn-probe/base', 'minn-probe/lazy', 'minn-probe/shared', 'minn-probe/deep'] as $id) {
    $modules->dequeue($id);
    $modules->deregister($id);
}
remove_filter('doing_it_wrong_trigger_error', '__return_false');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
