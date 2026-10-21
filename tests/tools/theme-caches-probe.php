<?php
/**
 * wp_clean_themes_cache() lets every theme's caches go: with each installed
 * theme's patterns cached first, the site transients it deletes, in order
 * (the update check's first when asked, then each theme's patterns cache),
 * and the patterns read again afterwards. Same protocol as api-probe.php.
 */
$log = [];
$hash = static fn (string $name): string => (string) preg_replace('/-[0-9a-f]{32}$/', '-{hash}', $name);
$saved = get_site_transient('update_themes');
foreach ([true, false] as $clear) {
    set_site_transient('update_themes', (object) ['zz_theme_caches' => 1]);
    foreach (wp_get_themes() as $theme) {
        $theme->get_block_patterns();
    }
    $deleted = [];
    $listen = static function ($transient) use (&$deleted, $hash): void {
        $deleted[] = $hash((string) $transient);
    };
    add_action('deleted_site_transient', $listen);
    wp_clean_themes_cache($clear);
    remove_action('deleted_site_transient', $listen);
    $log[] = [$clear ? 'wp_clean_themes_cache(true)' : 'wp_clean_themes_cache(false)', [count(wp_get_themes()), $deleted, get_site_transient('update_themes') !== false]];
}
$theme = wp_get_theme();
$theme->get_block_patterns();
$deleted = [];
$listen = static function ($transient) use (&$deleted, $hash): void {
    $deleted[] = $hash((string) $transient);
};
add_action('deleted_site_transient', $listen);
$theme->cache_delete();
remove_action('deleted_site_transient', $listen);
$log[] = ['WP_Theme::cache_delete', $deleted];
$saved === false ? delete_site_transient('update_themes') : set_site_transient('update_themes', $saved);
echo json_encode($log), "\n";
