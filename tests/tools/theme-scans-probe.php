<?php
/**
 * How a theme's folders are walked (probe theme-scans): the pattern files
 * and get_files, with pattern folders nested two deep, one named before the
 * others, and one theme_scandir_exclusions leaves out (vendor). What the
 * pattern list holds and in what order, the patterns read from it, the
 * files get_files finds to any depth and to one, and how often each walk
 * asks theme_scandir_exclusions. It makes its folders in the active theme's
 * patterns folder and removes them; every name starts with zz or aa-zz.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$theme = wp_get_theme();
$patterns = $theme->get_stylesheet_directory() . '/patterns';
$made = ["{$patterns}/zzsub/zzdeep", "{$patterns}/zzsub", "{$patterns}/vendor", "{$patterns}/aa-zz-first"];
foreach (array_reverse($made) as $dir) {
    @mkdir($dir, 0755, true);
}
$pattern = "<?php\n/**\n * Title: ZZ Scan\n * Slug: zz/scan\n */\n?>\n<p>zz</p>\n";
$files = ["{$patterns}/zzsub/zz-sub.php", "{$patterns}/zzsub/zzdeep/zz-deep.php", "{$patterns}/vendor/zz-vendor.php", "{$patterns}/aa-zz-first/aa-zz.php", "{$patterns}/zzsub/zz-note.txt"];
foreach ($files as $file) {
    file_put_contents($file, $pattern);
}
$theme->delete_pattern_cache();
$asked = 0;
$count = static function ($exclusions) use (&$asked) {
    $asked++;
    return $exclusions;
};
add_filter('theme_scandir_exclusions', $count);
$listed = null;
$keep = static function ($list) use (&$listed) {
    $listed = array_keys((array) $list);
    return $list;
};
add_filter('theme_block_pattern_files', $keep);
$read = array_keys($theme->get_block_patterns());
$say('the pattern files, in order', [$listed, $asked]);
$say('the patterns read from them', array_values(array_filter($read, static fn ($name) => str_contains($name, 'zz'))));
$asked = 0;
$say('get_files to any depth', [array_keys($theme->get_files('php', -1)), $asked]);
$asked = 0;
$say('get_files one folder down', [array_keys($theme->get_files(['php', 'txt'], 1)), $asked]);
remove_filter('theme_scandir_exclusions', $count);
remove_filter('theme_block_pattern_files', $keep);
foreach ($files as $file) {
    @unlink($file);
}
foreach ($made as $dir) {
    @rmdir($dir);
}
$theme->delete_pattern_cache();
$say('removed', array_values(array_filter($made, 'is_dir')));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
