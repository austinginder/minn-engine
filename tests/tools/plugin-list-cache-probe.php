<?php
/**
 * Whether get_plugins and get_mu_plugins keep what they read for the rest of
 * the request (probe plugin-list-cache): a plugin written after a first call,
 * a list set in the 'plugins' cache group by hand, the cache's shape after a
 * call with and without a folder, what wp_clean_plugins_cache lets through,
 * and the hooks one call fires. The probe's plugins are removed at the end,
 * whatever happens. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['wp-admin/includes/plugin.php'] as $admin) {
    if (is_file(ABSPATH . $admin)) {
        require_once ABSPATH . $admin;
    }
}
$dir = WP_PLUGIN_DIR;
$mu = WPMU_PLUGIN_DIR;
$sweep = static function () use ($dir, $mu): void {
    foreach (['zz-cache-first', 'zz-cache-late'] as $folder) {
        foreach (glob("{$dir}/{$folder}/*") ?: [] as $file) {
            unlink($file);
        }
        if (is_dir("{$dir}/{$folder}")) {
            rmdir("{$dir}/{$folder}");
        }
    }
    foreach (["{$dir}/zz-cache-single.php", "{$mu}/zz-cache-mu-late.php"] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
};
$sweep();
register_shutdown_function($sweep);
$ours = static fn (array $list): array => array_values(array_filter(array_keys($list), static fn ($key) => str_contains((string) $key, 'zz-cache')));
$shape = static function (): mixed {
    $cached = wp_cache_get('plugins', 'plugins');
    if (!is_array($cached)) {
        return ['type' => gettype($cached), 'value' => $cached];
    }
    $out = [];
    foreach ($cached as $folder => $list) {
        $out[] = [$folder, is_array($list) ? count(array_filter(array_keys($list), static fn ($key) => str_contains((string) $key, 'zz-cache'))) : gettype($list)];
    }
    return $out;
};

mkdir("{$dir}/zz-cache-first", 0755, true);
file_put_contents("{$dir}/zz-cache-first/zz-cache-first.php", "<?php\n/**\n * Plugin Name: ZZ Cache First\n */\n");
wp_clean_plugins_cache(false);
$say('cache before any call', $shape());

$hooks = [];
$listen = static function () use (&$hooks) {
    $hooks[] = current_filter();
    return func_num_args() ? func_get_arg(0) : null;
};
add_filter('all', $listen);
$first = get_plugins();
remove_filter('all', $listen);
$say('first call: our plugins', $ours($first));
$say('first call: hooks fired', array_values(array_unique(array_filter($hooks, static fn ($hook) => !in_array($hook, ['gettext', 'gettext_default', 'ngettext'], true)))));
$say('cache after the first call', $shape());

file_put_contents("{$dir}/zz-cache-single.php", "<?php\n/**\n * Plugin Name: ZZ Cache Single\n */\n");
mkdir("{$dir}/zz-cache-late", 0755, true);
file_put_contents("{$dir}/zz-cache-late/zz-cache-late.php", "<?php\n/**\n * Plugin Name: ZZ Cache Late\n */\n");
$say('second call, plugins written since: our plugins', $ours(get_plugins()));

$say('a folder call: keys', array_keys(get_plugins('/zz-cache-late')));
$say('cache after a folder call', $shape());
$say('the same folder written another way: keys', array_keys(get_plugins('zz-cache-late')));
$say('cache after that', $shape());
file_put_contents("{$dir}/zz-cache-late/zz-cache-late-two.php", "<?php\n/**\n * Plugin Name: ZZ Cache Late Two\n */\n");
$say('the folder again, a file written since: keys', array_keys(get_plugins('/zz-cache-late')));

wp_cache_set('plugins', ['' => ['zz-cache-made/zz-cache-made.php' => ['Name' => 'ZZ Cache Made']]], 'plugins');
$say('a list set in the cache by hand: get_plugins', get_plugins());
wp_cache_set('plugins', ['/zz-cache-late' => ['zz-cache-late.php' => ['Name' => 'Set']]], 'plugins');
$say('another folder cached by hand: our plugins at the root', $ours(get_plugins()));
$say('cache after that root call', $shape());

wp_clean_plugins_cache(false);
$say('after wp_clean_plugins_cache: our plugins', $ours(get_plugins()));

$mus = array_keys(get_mu_plugins());
file_put_contents("{$mu}/zz-cache-mu-late.php", "<?php\n/**\n * Plugin Name: ZZ Cache MU Late\n */\n");
$say('get_mu_plugins after a must-use file is written: has it', in_array('zz-cache-mu-late.php', array_keys(get_mu_plugins()), true));
$say('get_mu_plugins first call had it', in_array('zz-cache-mu-late.php', $mus, true));
$say('cache group after get_mu_plugins', $shape());

wp_clean_plugins_cache(false);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
