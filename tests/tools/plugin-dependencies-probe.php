<?php
/**
 * Plugin dependencies (probe plugin-dependencies): WP_Plugin_Dependencies
 * over a set of probe plugins whose Requires Plugins headers name each
 * other, one that is missing, a loop, and slugs that are not slugs.
 * Dependents and dependencies both ways, their names and files, unmet and
 * circular ones, the data a dependency is shown with, and the admin
 * notices. Which plugins are active is answered by a filter and the
 * plugins API by another, so nothing is written to the database and
 * nothing leaves the machine. The probe's plugin files are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$dir = WP_PLUGIN_DIR;
$plugins = [
    'zz-deps-a/zz-deps-a.php' => ['Zz Deps A', 'zz-deps-b, zz-deps-missing'],
    'zz-deps-b/zz-deps-b.php' => ['Zz Deps B', ''],
    'zz-deps-c/zz-deps-c.php' => ['Zz Deps C', 'zz-deps-b'],
    'zz-deps-loop1/zz-deps-loop1.php' => ['Zz Deps Loop One', 'zz-deps-loop2'],
    'zz-deps-loop2/zz-deps-loop2.php' => ['Zz Deps Loop Two', 'zz-deps-loop1'],
    'zz-deps-bad/zz-deps-bad.php' => ['Zz Deps Bad', 'Bad Slug, zz-deps-c, zz-deps-c, , ZZ-Upper, zz_under, -zz'],
    'zz-deps-single.php' => ['Zz Deps Single', 'zz-deps-c'],
    'zz-deps-c1/zz-deps-c1.php' => ['Zz Deps C1', 'zz-deps-c2'],
    'zz-deps-c2/zz-deps-c2.php' => ['Zz Deps C2', 'zz-deps-c3'],
    'zz-deps-c3/zz-deps-c3.php' => ['Zz Deps C3', 'zz-deps-c1'],
    'zz-deps-self/zz-deps-self.php' => ['Zz Deps Self', 'zz-deps-self'],
    'zz-deps-l1/zz-deps-l1.php' => ['Zz Deps L1', 'zz-deps-l2'],
    'zz-deps-l2/zz-deps-l2.php' => ['Zz Deps L2', 'zz-deps-l1, zz-deps-c1'],
    'zz-deps-d/zz-deps-d.php' => ['Zz Deps D', 'zz-deps-l1'],
];
register_shutdown_function(static function () use ($dir, $plugins): void {
    foreach (array_keys($plugins) as $file) {
        if (is_file("{$dir}/{$file}")) {
            unlink("{$dir}/{$file}");
        }
        if (str_contains($file, '/') && is_dir($dir . '/' . dirname($file))) {
            rmdir($dir . '/' . dirname($file));
        }
    }
    wp_clean_plugins_cache(false);
    delete_site_transient('wp_plugin_dependencies_plugin_data');
});
foreach ($plugins as $file => [$name, $requires]) {
    if (str_contains($file, '/')) {
        wp_mkdir_p($dir . '/' . dirname($file));
    }
    file_put_contents("{$dir}/{$file}", "<?php\n/**\n * Plugin Name: {$name}\n * Version: 1.0\n" . ($requires === '' ? '' : " * Requires Plugins: {$requires}\n") . " */\n");
}
wp_clean_plugins_cache(false);
$active = ['zz-deps-b/zz-deps-b.php', 'zz-deps-c/zz-deps-c.php'];
add_filter('option_active_plugins', static fn ($list) => array_values(array_unique([...array_filter((array) $list, static fn ($p) => !str_starts_with((string) $p, 'zz-deps')), ...$active])));
add_filter('plugins_api', static function ($result, $action, $args) {
    if ($action === 'plugin_information' && str_starts_with((string) ($args->slug ?? ''), 'zz-')) {
        return (object) ['name' => 'Zz Remote ' . $args->slug, 'slug' => $args->slug, 'version' => '9.9', 'author' => 'Zz', 'requires' => '6.0', 'requires_php' => '7.4', 'short_description' => 'From the API.', 'download_link' => 'https://zz.example/' . $args->slug . '.zip', 'icons' => [], 'banners' => []];
    }
    return $result;
}, 10, 3);
delete_site_transient('wp_plugin_dependencies_plugin_data');
add_filter('pre_site_transient_wp_plugin_dependencies_plugin_data', static fn () => false);

/** Opens the protected steps and clears what an earlier run kept. */
final class Zz_Plugin_Dependencies extends WP_Plugin_Dependencies
{
    public static function reset(): void
    {
        foreach (['plugins', 'plugin_dirnames', 'dependencies', 'dependency_slugs', 'dependent_slugs', 'dependency_api_data', 'dependency_filepaths', 'circular_dependencies_pairs', 'circular_dependencies_slugs'] as $name) {
            static::${$name} = null;
        }
        static::$initialized = false;
    }

    public static function call(string $method, ...$args)
    {
        return static::$method(...$args);
    }
}
$ours = static function ($value) use (&$ours) {
    if (!is_array($value)) {
        return $value;
    }
    $out = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && !str_starts_with($key, 'zz') && !str_starts_with($key, 'Zz')) {
            continue;
        }
        if (is_int($key) && is_string($item) && !str_starts_with($item, 'zz') && !str_starts_with($item, 'Zz')) {
            continue;
        }
        $out[$key] = $ours($item);
    }
    return array_is_list($value) ? array_values($out) : $out;
};

Zz_Plugin_Dependencies::reset();
WP_Plugin_Dependencies::initialize();
$say('dependents and dependencies', [
    WP_Plugin_Dependencies::has_dependents('zz-deps-b/zz-deps-b.php'), WP_Plugin_Dependencies::has_dependents('zz-deps-a/zz-deps-a.php'), WP_Plugin_Dependencies::has_dependents('zz-deps-c/zz-deps-c.php'),
    WP_Plugin_Dependencies::has_dependencies('zz-deps-a/zz-deps-a.php'), WP_Plugin_Dependencies::has_dependencies('zz-deps-b/zz-deps-b.php'), WP_Plugin_Dependencies::has_dependencies('zz-deps-single.php'),
    WP_Plugin_Dependencies::has_active_dependents('zz-deps-b/zz-deps-b.php'), WP_Plugin_Dependencies::has_active_dependents('zz-deps-c/zz-deps-c.php'),
    WP_Plugin_Dependencies::get_dependents('zz-deps-b'), WP_Plugin_Dependencies::get_dependents('zz-deps-c'), WP_Plugin_Dependencies::get_dependents('zz-deps-never'),
    WP_Plugin_Dependencies::get_dependencies('zz-deps-a/zz-deps-a.php'), WP_Plugin_Dependencies::get_dependencies('zz-deps-bad/zz-deps-bad.php'), WP_Plugin_Dependencies::get_dependencies('zz-deps-b/zz-deps-b.php'),
    WP_Plugin_Dependencies::get_dependent_filepath('zz-deps-a'), WP_Plugin_Dependencies::get_dependent_filepath('zz-deps-single'), WP_Plugin_Dependencies::get_dependent_filepath('zz-deps-never'),
    WP_Plugin_Dependencies::get_dependency_filepath('zz-deps-b'), WP_Plugin_Dependencies::get_dependency_filepath('zz-deps-missing'), WP_Plugin_Dependencies::get_dependency_filepath('zz-deps-never'),
]);
$say('unmet and circular', [
    WP_Plugin_Dependencies::has_unmet_dependencies('zz-deps-a/zz-deps-a.php'), WP_Plugin_Dependencies::has_unmet_dependencies('zz-deps-c/zz-deps-c.php'), WP_Plugin_Dependencies::has_unmet_dependencies('zz-deps-single.php'), WP_Plugin_Dependencies::has_unmet_dependencies('zz-deps-b/zz-deps-b.php'),
    WP_Plugin_Dependencies::has_circular_dependency('zz-deps-loop1/zz-deps-loop1.php'), WP_Plugin_Dependencies::has_circular_dependency('zz-deps-a/zz-deps-a.php'),
    $ours(Zz_Plugin_Dependencies::call('get_circular_dependencies')),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-x'], ['zz-y']),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-x', 'zz-y'], ['zz-y']),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-deps-c1'], ['zz-deps-c2']),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-deps-l1'], ['zz-deps-l2']),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-deps-d'], ['zz-deps-l1']),
    Zz_Plugin_Dependencies::call('check_for_circular_dependencies', ['zz-x', 'zz-y'], ['zz-y', 'zz-x']),
    array_map(static fn ($file) => WP_Plugin_Dependencies::has_circular_dependency($file), ['zz-deps-c2/zz-deps-c2.php', 'zz-deps-self/zz-deps-self.php', 'zz-deps-l2/zz-deps-l2.php', 'zz-deps-d/zz-deps-d.php']),
]);
$say('names and data', [
    WP_Plugin_Dependencies::get_dependent_names('zz-deps-b/zz-deps-b.php'), WP_Plugin_Dependencies::get_dependent_names('zz-deps-c/zz-deps-c.php'),
    WP_Plugin_Dependencies::get_dependency_names('zz-deps-a/zz-deps-a.php'), WP_Plugin_Dependencies::get_dependency_names('zz-deps-bad/zz-deps-bad.php'),
    WP_Plugin_Dependencies::get_dependency_data('zz-deps-b'), WP_Plugin_Dependencies::get_dependency_data('zz-deps-missing'), WP_Plugin_Dependencies::get_dependency_data('zz-deps-never'),
]);
$say('the steps', [
    $ours(Zz_Plugin_Dependencies::call('get_plugins')),
    $ours(Zz_Plugin_Dependencies::call('get_plugin_dirnames')),
    $ours(Zz_Plugin_Dependencies::call('get_dependency_filepaths')),
    Zz_Plugin_Dependencies::call('sanitize_dependency_slugs', 'Bad Slug, zz-b, zz-b, , ZZ-Upper, zz_under, -zz, zz--two, zz-c'),
    Zz_Plugin_Dependencies::call('sanitize_dependency_slugs', ''),
    Zz_Plugin_Dependencies::call('convert_to_slug', 'zz-deps-a/zz-deps-a.php'), Zz_Plugin_Dependencies::call('convert_to_slug', 'hello.php'), Zz_Plugin_Dependencies::call('convert_to_slug', 'hello-dolly/hello.php'),
    (static function () use ($ours) {
        Zz_Plugin_Dependencies::reset();
        Zz_Plugin_Dependencies::call('read_dependencies_from_plugin_headers');
        return $ours(Zz_Plugin_Dependencies::call('get_dependency_filepaths'));
    })(),
]);
Zz_Plugin_Dependencies::reset();
WP_Plugin_Dependencies::initialize();
$say('dependency data from the plugins API', [
    (static function () use ($ours) {
        $data = Zz_Plugin_Dependencies::call('get_dependency_api_data');
        return $ours(is_array($data) ? array_map(static fn ($item) => is_array($item) ? array_intersect_key($item, array_flip(['name', 'slug', 'version', 'Name', 'Version', 'short_description'])) : $item, $data) : $data);
    })(),
    WP_Plugin_Dependencies::get_dependency_data('zz-deps-missing'),
    is_array(WP_Plugin_Dependencies::get_dependency_data('zz-deps-b')) ? array_intersect_key(WP_Plugin_Dependencies::get_dependency_data('zz-deps-b'), array_flip(['Name', 'name', 'Version', 'version', 'slug'])) : WP_Plugin_Dependencies::get_dependency_data('zz-deps-b'),
    WP_Plugin_Dependencies::get_dependency_names('zz-deps-a/zz-deps-a.php'),
]);
$say('admin notices', (static function () {
    $out = [];
    foreach (['display_admin_notice_for_unmet_dependencies', 'display_admin_notice_for_circular_dependencies'] as $method) {
        ob_start();
        WP_Plugin_Dependencies::$method();
        $out[] = ob_get_clean();
        set_current_screen('plugins');
        ob_start();
        WP_Plugin_Dependencies::$method();
        $out[] = ob_get_clean();
        $GLOBALS['current_screen'] = null;
    }
    return $out;
})());
Zz_Plugin_Dependencies::reset();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
