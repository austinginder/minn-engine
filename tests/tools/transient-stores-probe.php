<?php
/**
 * Where transients live (probe transient-stores): while installing
 * (wp_installing) and with a persistent object cache (wp_using_ext_object_cache),
 * each switched on for a moment and back, a site and a network transient
 * set, read, read again once the switch is off, and removed; what the
 * options table holds of them and what the object cache does, and which
 * hooks fire. Every name starts with zz_store_; what it writes it removes.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$heard = [];
foreach (['set_transient', 'set_site_transient', 'deleted_transient', 'deleted_site_transient'] as $hook) {
    add_action($hook, static function (...$args) use (&$heard, $hook): void {
        if (str_starts_with((string) $args[0], 'zz_store_')) {
            $heard[] = [$hook, $args[0]];
        }
    }, 10, 3);
}
$rows = static fn (string $name): array => $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", '%' . $wpdb->esc_like($name)));
$round = static function (string $label, callable $on, callable $off) use ($say, $rows, &$heard): void {
    $heard = [];
    set_transient('zz_store_kept', 'from the table', 300);
    set_site_transient('zz_store_site_kept', 'from the table');
    $on();
    $read = [get_transient('zz_store_kept'), get_site_transient('zz_store_site_kept')];
    $set = [set_transient('zz_store_new', 'new', 60), set_site_transient('zz_store_site_new', 'new', 60), set_transient('zz_store_forever', 'forever')];
    $back = [get_transient('zz_store_new'), get_site_transient('zz_store_site_new'), get_transient('zz_store_forever')];
    $cache = [wp_cache_get('zz_store_new', 'transient'), wp_cache_get('zz_store_site_new', 'site-transient'), wp_cache_get('zz_store_kept', 'transient')];
    $table = [$rows('zz_store_new'), $rows('zz_store_site_new'), $rows('zz_store_forever')];
    $removed = [delete_transient('zz_store_new'), delete_site_transient('zz_store_site_new'), delete_transient('zz_store_kept')];
    $off();
    $after = [get_transient('zz_store_new'), get_transient('zz_store_forever'), get_transient('zz_store_kept'), $rows('zz_store_kept')];
    delete_transient('zz_store_kept');
    delete_site_transient('zz_store_site_kept');
    delete_transient('zz_store_forever');
    wp_cache_delete('zz_store_forever', 'transient');
    $say($label, ['read' => $read, 'set' => $set, 'read back' => $back, 'object cache' => $cache, 'table' => $table, 'removed' => $removed, 'after' => $after, 'heard' => $heard]);
};
$round('while installing', static fn () => wp_installing(true), static fn () => wp_installing(false));
// Unset, the flag reads null, and handing null back changes nothing: it is put back as false.
$external = (bool) wp_using_ext_object_cache();
$round('with a persistent object cache', static fn () => wp_using_ext_object_cache(true), static fn () => wp_using_ext_object_cache($external));
$say('the switches are off again', [wp_installing(), (bool) wp_using_ext_object_cache()]);
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%zz\\_store\\_%'");
wp_cache_delete('alloptions', 'options');
wp_cache_delete('notoptions', 'options');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
