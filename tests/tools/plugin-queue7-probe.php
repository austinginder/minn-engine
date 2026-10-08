<?php
/**
 * The seventh wave of the plugin catalogue's queue (probe plugin-queue7),
 * as the reference answers it: whether this is a protected endpoint, the
 * SSL constants, the bootstrap steps a plugin repeats (the database, its
 * variables, the object cache, the REST hook), a folder moved, an image
 * resized by the old helper. Everything made is removed at the end. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$base = sys_get_temp_dir() . '/zz-queue7-' . getmypid();
register_shutdown_function(static function () use ($base): void {
    if (!is_dir($base)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($base);
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING);
if (!did_action('init')) {
    do_action('init');
}
if (!function_exists('move_dir')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    if (!in_array([$function, $replacement, $version], $deprecated, true)) {
        $deprecated[] = [$function, $replacement, $version];
    }
}, 10, 3);
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$plain = static fn ($value) => is_wp_error($value) ? ['error' => $value->get_error_code()] : (is_string($value) ? str_replace(['/private' . $GLOBALS['zz_base'], $GLOBALS['zz_base']], '{base}', $value) : $value);
$GLOBALS['zz_base'] = $base;

// The endpoint and the constants.
$say('is_protected_endpoint', [is_protected_endpoint(), $try(static function () {
    add_filter('is_protected_endpoint', '__return_true');
    $protected = is_protected_endpoint();
    remove_filter('is_protected_endpoint', '__return_true');
    return $protected;
})]);
wp_ssl_constants();
$say('wp_ssl_constants', [defined('FORCE_SSL_ADMIN'), defined('FORCE_SSL_ADMIN') ? FORCE_SSL_ADMIN : null, defined('FORCE_SSL_LOGIN')]);

// Bootstrap steps a plugin repeats.
$wpdbBefore = $GLOBALS['wpdb'] ?? null;
$say('require_wp_db', [require_wp_db(), ($GLOBALS['wpdb'] ?? null) === $wpdbBefore]);
$say('wp_set_wpdb_vars', [$try(static fn () => wp_set_wpdb_vars()), $GLOBALS['wpdb']->prefix === $wpdbBefore->prefix]);
$say('wp_start_object_cache', [$try(static fn () => wp_start_object_cache()), wp_cache_set('zz-queue7', 'kept', 'zz') && wp_cache_get('zz-queue7', 'zz') === 'kept']);
ob_start();
$loaded = rest_api_loaded();
$say('rest_api_loaded off a REST request', [$loaded, ob_get_clean()]);

// A folder moved.
global $wp_filesystem;
WP_Filesystem();
foreach (['from/inner', 'taken'] as $dir) {
    mkdir("{$base}/{$dir}", 0755, true);
}
file_put_contents("{$base}/from/a.txt", 'a');
file_put_contents("{$base}/from/inner/b.txt", 'b');
file_put_contents("{$base}/taken/old.txt", 'old');
$tree = static function (string $dir): array {
    if (!is_dir($dir)) {
        return [];
    }
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = substr($file->getPathname(), strlen($dir) + 1);
    }
    sort($files);
    return $files;
};
$say('move_dir to a new folder', [$plain(move_dir("{$base}/from", "{$base}/to")), $tree("{$base}/to"), is_dir("{$base}/from")]);
$say('move_dir onto a folder that is there', [$plain(move_dir("{$base}/to", "{$base}/taken")), $tree("{$base}/taken")]);
$say('move_dir onto it, overwriting', [$plain(move_dir("{$base}/to", "{$base}/taken", true)), $tree("{$base}/taken"), is_dir("{$base}/to")]);
$say('move_dir onto itself', $plain(move_dir("{$base}/taken", "{$base}/TAKEN/")));
$say('move_dir of nothing', $plain(move_dir("{$base}/nope", "{$base}/elsewhere")));

// An image resized by the old helper.
$image = imagecreatetruecolor(200, 100);
imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
imagejpeg($image, "{$base}/photo.jpg", 90);
$size = static fn ($path) => is_string($path) && is_file($path) ? array_slice((array) getimagesize($path), 0, 2) : null;
$scaled = image_resize("{$base}/photo.jpg", 50, 50);
$cropped = image_resize("{$base}/photo.jpg", 50, 50, true);
$suffixed = image_resize("{$base}/photo.jpg", 80, 80, false, 'zz', "{$base}/taken");
$say('image_resize', [[$plain($scaled), $size($scaled)], [$plain($cropped), $size($cropped)], [$plain($suffixed), $size($suffixed)], $plain(image_resize("{$base}/missing.jpg", 50, 50)), $plain(image_resize("{$base}/photo.jpg", 400, 400))]);

$say('deprecated, as reported', $deprecated);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
