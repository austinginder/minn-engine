<?php
/**
 * Recovery, read through WordPress's API (probe recovery): the paused
 * plugins and themes storage and what it answers with nothing paused and no
 * recovery session, writes to it refused outside a session, the lists the
 * loaders filter through it, and the recovery-mode object. Minn pauses on its
 * own (Runtime\Recovery) and keeps no recovery session (Austin, D4); this pins
 * the answers both give a plugin that asks. The options it could touch are
 * compared before and after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
global $wpdb;
$watched = static fn () => $wpdb->get_results("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE '%paused%' OR option_name LIKE 'recovery%' ORDER BY option_name", ARRAY_A);
$before = $watched();
$error = ['type' => E_ERROR, 'file' => WP_PLUGIN_DIR . '/zz-recovery/zz-recovery.php', 'line' => 3, 'message' => 'ZZ failed'];

$plugins = wp_paused_plugins();
$themes = wp_paused_themes();
$say('the storage objects', [
    'plugins' => get_class($plugins), 'themes' => get_class($themes),
    'kept' => wp_paused_plugins() === $plugins && wp_paused_themes() === $themes, 'apart' => $plugins !== $themes,
]);
$storage = static fn (WP_Paused_Extensions_Storage $store) => [
    'all' => $store->get_all(), 'one' => $store->get('zz-recovery'),
    'set' => $store->set('zz-recovery', $error), 'after set' => $store->get_all(), 'one after set' => $store->get('zz-recovery'),
    'delete' => $store->delete('zz-recovery'), 'delete all' => $store->delete_all(), 'after' => $store->get_all(),
];
$say('paused plugins, nothing paused and no session', $storage($plugins));
$say('paused themes, nothing paused and no session', $storage($themes));
$say('a storage made directly', [
    'plugin' => $storage(new WP_Paused_Extensions_Storage('plugin')),
    'theme' => $storage(new WP_Paused_Extensions_Storage('theme')),
    'an unknown type' => $storage(new WP_Paused_Extensions_Storage('zz')),
]);

// The loaders' lists, filtered through the storage.
$pluginPaths = [WP_PLUGIN_DIR . '/zz-recovery/zz-recovery.php', WP_PLUGIN_DIR . '/hello.php', WP_PLUGIN_DIR . '/akismet/akismet.php'];
$themePaths = [get_theme_root() . '/twentytwentyfive', get_theme_root() . '/zz-recovery-theme'];
$mask = static fn ($value) => json_decode(str_replace([str_replace('/', '\/', WP_PLUGIN_DIR), str_replace('/', '\/', get_theme_root())], ['{plugins}', '{themes}'], (string) json_encode($value)), true);
$say('paused plugins skipped', [
    'a list' => $mask(wp_skip_paused_plugins($pluginPaths)), 'keyed' => $mask(wp_skip_paused_plugins(['a' => $pluginPaths[0], 'b' => $pluginPaths[1]])),
    'empty' => wp_skip_paused_plugins([]),
]);
$say('paused themes skipped', ['a list' => $mask(wp_skip_paused_themes($themePaths)), 'empty' => wp_skip_paused_themes([])]);

$mode = wp_recovery_mode();
$say('the recovery mode object', ['class' => get_class($mode), 'kept' => wp_recovery_mode() === $mode, 'in recovery mode' => wp_is_recovery_mode()]);

$after = $watched();
$say('options left as they were', $after === $before);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
