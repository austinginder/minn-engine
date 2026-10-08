<?php
/**
 * What WordPress's upgraders do (probe upgrader): Plugin_Upgrader and
 * Theme_Upgrader, with Automatic_Upgrader_Skin, install, overwrite and
 * update throwaway packages the probe zips into a temporary folder, so no
 * request leaves the machine. Recorded per call, in order: the upgrader
 * hooks (actions and filters, which is which, their arguments in words),
 * the update transients deleted or written, the answer, the upgrader's
 * result and plugin_info/theme_info, the skin's messages, and what is on
 * disk after. Refusals: the folder exists, an archive with no plugin or
 * theme in it, a file that is not a zip, and the upgrader_pre_download,
 * upgrader_pre_install and upgrader_source_selection filters answering
 * with errors. Seen at upgrader_pre_install and upgrader_post_install:
 * whether maintenance mode is on, the plugin active, the old copy backed
 * up; after: the backup and the upgrade folder, and the cleanup event.
 * The theme caches (theme roots, pattern files), which Minn does not keep,
 * are left out of what is heard. Then the other skins: the default, installer and bulk skins (whose
 * wp-admin markup is not recorded: they draw admin screens, which Minn does
 * not have) and the errors the Ajax skin collects. The printing skins flush
 * every output buffer as they go, so one buffer that cannot be removed holds
 * everything printed for the whole run. The stacks share a database: the update transients are
 * saved first and put back, and the probe's folders removed, whatever
 * happens. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$printedSoFar = '';
$passThrough = false;
ob_start(static function (string $chunk) use (&$printedSoFar, &$passThrough): string {
    if ($passThrough) {
        return $chunk;
    }
    $printedSoFar .= $chunk;
    return '';
}, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
// The skins' attempts to end that buffer each raise a notice; nothing else is silenced.
set_error_handler(static fn (int $level, string $message): bool => str_contains($message, 'Failed to delete buffer') || str_contains($message, 'failed to delete buffer'), E_NOTICE | E_USER_NOTICE);
foreach (['wp-admin/includes/plugin.php', 'wp-admin/includes/file.php', 'wp-admin/includes/misc.php', 'wp-admin/includes/theme.php', 'wp-admin/includes/class-wp-upgrader.php'] as $admin) {
    if (is_file(ABSPATH . $admin)) {
        require_once ABSPATH . $admin;
    }
}
$tmp = sys_get_temp_dir() . '/minn-upgrader-probe-' . getmypid();
$saved = [];
foreach (['_site_transient_update_plugins', '_site_transient_timeout_update_plugins', '_site_transient_update_themes', '_site_transient_timeout_update_themes'] as $option) {
    $saved[$option] = get_option($option, null);
}
$sweep = static function () use ($tmp): void {
    foreach (['plugins/zz-up-plugin', 'themes/zz-up-theme', 'plugins/zz-up-other', 'upgrade-temp-backup/plugins/zz-up-plugin', 'upgrade-temp-backup/themes/zz-up-theme'] as $folder) {
        $path = WP_CONTENT_DIR . '/' . $folder;
        if (is_dir($path)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($path);
        }
    }
    foreach (glob("{$tmp}/*") ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($tmp)) {
        rmdir($tmp);
    }
};
$restore = static function () use ($saved): void {
    foreach ($saved as $option => $value) {
        $value === null ? delete_option($option) : update_option($option, $value, false);
    }
};
// The old copy an update set aside is cleared before each call (the reference's own clearing waits for a weekly event).
$clearBackups = static function (): void {
    foreach (['upgrade-temp-backup/plugins/zz-up-plugin', 'upgrade-temp-backup/themes/zz-up-theme'] as $folder) {
        $path = WP_CONTENT_DIR . '/' . $folder;
        if (is_dir($path)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($path);
        }
    }
};
$sweep();
// Every run starts with no update transients, whatever the shared database held.
foreach (array_keys($saved) as $option) {
    delete_option($option);
}
register_shutdown_function(static function () use ($sweep, $restore): void {
    $sweep();
    $restore();
});
mkdir($tmp, 0755, true);

$zip = static function (string $name, array $files) use ($tmp): string {
    $path = "{$tmp}/{$name}";
    $archive = new ZipArchive();
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $entry => $contents) {
        $archive->addFromString($entry, $contents);
    }
    $archive->close();
    return $path;
};
$plugin = static fn (string $version): string => "<?php\n/**\n * Plugin Name: ZZ Up Plugin\n * Version: {$version}\n */\n";
$theme = static fn (string $version): string => "/*\nTheme Name: ZZ Up Theme\nVersion: {$version}\n*/\n";
$p1 = $zip('zz-up-plugin.1.0.zip', ['zz-up-plugin/zz-up-plugin.php' => $plugin('1.0'), 'zz-up-plugin/readme.txt' => "=== ZZ Up ===\n"]);
$p2 = $zip('zz-up-plugin.2.0.zip', ['zz-up-plugin/zz-up-plugin.php' => $plugin('2.0')]);
$p3 = $zip('zz-up-plugin.3.0.zip', ['zz-up-plugin/zz-up-plugin.php' => $plugin('3.0')]);
$none = $zip('zz-up-nothing.zip', ['zz-up-nothing/readme.txt' => "Nothing here.\n"]);
file_put_contents("{$tmp}/zz-up-not-a-zip.zip", 'This is not a zip archive.');
$t1 = $zip('zz-up-theme.1.0.zip', ['zz-up-theme/style.css' => $theme('1.0'), 'zz-up-theme/index.php' => "<?php\n"]);
$t2 = $zip('zz-up-theme.2.0.zip', ['zz-up-theme/style.css' => $theme('2.0'), 'zz-up-theme/index.php' => "<?php\n"]);
$needsPhp = $zip('zz-up-plugin-php.zip', ['zz-up-plugin/zz-up-plugin.php' => "<?php\n/**\n * Plugin Name: ZZ Up Plugin\n * Version: 4.0\n * Requires PHP: 99.0\n */\n"]);
$needsWp = $zip('zz-up-plugin-wp.zip', ['zz-up-plugin/zz-up-plugin.php' => "<?php\n/**\n * Plugin Name: ZZ Up Plugin\n * Version: 4.0\n * Requires at least: 99.0\n */\n"]);
$themePhp = $zip('zz-up-theme-php.zip', ['zz-up-theme/style.css' => "/*\nTheme Name: ZZ Up Theme\nVersion: 3.0\nRequires PHP: 99.0\n*/\n", 'zz-up-theme/index.php' => "<?php\n"]);
$themeNoIndex = $zip('zz-up-theme-noindex.zip', ['zz-up-theme/style.css' => $theme('3.0'), 'zz-up-theme/functions.php' => "<?php\n"]);
$themeBlock = $zip('zz-up-theme-block.zip', ['zz-up-theme/style.css' => $theme('3.0'), 'zz-up-theme/templates/index.html' => "<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->\n"]);

// Arguments in words: the same on both stacks, paths relative, temporary folders named by role.
$words = static function ($value) use (&$words, $tmp) {
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code();
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_array($value)) {
        return array_map($words, $value);
    }
    if (is_string($value)) {
        $value = str_replace([$tmp, WP_CONTENT_DIR], ['{tmp}', 'wp-content'], $value);
        return (string) preg_replace('#wp-content/upgrade(-temp-backup)?/[^/]+#', 'wp-content/upgrade$1/{work}', $value);
    }
    return $value;
};
$heard = [];
$listening = false;
add_action('all', static function (string $name) use (&$heard, &$listening, $words): void {
    // cove_* hooks are the local stack's own mu-plugin, not WordPress.
    if (!$listening || str_starts_with($name, 'cove_') || preg_match('/upgrader|^(set|delete|deleted)_site_transient(_update_(plugins|themes))?$|^(set|delete)_site_transient_update_(plugins|themes)$|^(activate|deactivate)d?_plugin$|^switch_theme$/', $name) !== 1) {
        return;
    }
    // The theme caches (theme roots, pattern files) are written and cleared as their state allows; Minn keeps neither.
    $first = func_get_args()[1] ?? null;
    if (is_string($first) && preg_match('/^(theme_roots|wp_theme_files_patterns-)/', $first) === 1) {
        return;
    }
    $before = did_action($name);
    $heard[] = [$name, $words(array_slice(func_get_args(), 1))];
    $heard[count($heard) - 1][] = $before > 0 && doing_action($name) ? 'action' : 'filter';
}, PHP_INT_MIN);
$moments = [];
$moment = static function (string $when) use (&$moments, &$listening): Closure {
    return static function ($answer) use ($when, &$moments, &$listening) {
        if ($listening) {
            $moments[$when] = [
                'maintenance' => file_exists(ABSPATH . '.maintenance'),
                'active' => is_plugin_active('zz-up-plugin/zz-up-plugin.php'),
                'plugin backup' => is_dir(WP_CONTENT_DIR . '/upgrade-temp-backup/plugins/zz-up-plugin'),
                'theme backup' => is_dir(WP_CONTENT_DIR . '/upgrade-temp-backup/themes/zz-up-theme'),
            ];
        }
        return $answer;
    };
};
add_filter('upgrader_pre_install', $moment('pre_install'), PHP_INT_MAX);
add_filter('upgrader_post_install', $moment('post_install'), PHP_INT_MAX);
$cleanupBefore = (bool) wp_next_scheduled('wp_delete_temp_updater_backups');
$disk = static function (): array {
    $found = [];
    foreach (['plugins/zz-up-plugin/zz-up-plugin.php' => 'Version', 'themes/zz-up-theme/style.css' => 'Version'] as $file => $header) {
        $path = WP_CONTENT_DIR . '/' . $file;
        $found[$file] = is_file($path) ? get_file_data($path, ['Version' => $header])['Version'] : null;
    }
    $found['plugins/zz-up-nothing'] = is_dir(WP_CONTENT_DIR . '/plugins/zz-up-nothing');
    $found['maintenance'] = file_exists(ABSPATH . '.maintenance');
    return $found;
};
$offers = static function (string $kind) use ($words): array {
    $transient = get_site_transient("update_{$kind}");
    $response = is_object($transient) ? (array) ($transient->response ?? []) : null;
    return $response === null ? ['transient' => false] : ['transient' => true, 'zz' => $words(array_keys(array_filter($response, static fn ($k): bool => str_starts_with((string) $k, 'zz-up'), ARRAY_FILTER_USE_KEY)))];
};
$run = static function (string $label, string $kind, callable $call, ?WP_Upgrader_Skin $skin = null) use ($say, &$heard, &$listening, $words, $disk, $offers, &$moments, &$printedSoFar, $clearBackups): void {
    $quiet = $skin === null || $skin instanceof WP_Ajax_Upgrader_Skin;
    $clearBackups();
    $skin ??= new Automatic_Upgrader_Skin();
    $upgrader = $kind === 'theme' ? new Theme_Upgrader($skin) : new Plugin_Upgrader($skin);
    $heard = [];
    $moments = [];
    $listening = true;
    @ob_flush();
    $printedSoFar = '';
    try {
        $answer = $call($upgrader);
    } finally {
        @ob_flush();
        $printed = $quiet ? $printedSoFar : null;
        $printedSoFar = '';
        $listening = false;
    }
    $info = $kind === 'theme' ? $upgrader->theme_info() : $upgrader->plugin_info();
    $say($label, [
        'answer' => $words($answer),
        'heard' => $heard,
        'result' => $words($upgrader->result),
        'info' => $info instanceof WP_Theme ? 'theme:' . $info->get_stylesheet() : $words($info),
        'messages' => method_exists($skin, 'get_upgrade_messages') ? $words($skin->get_upgrade_messages()) : null,
        'errors' => $skin instanceof WP_Ajax_Upgrader_Skin ? [$words($skin->get_errors()->get_error_codes()), $words($skin->get_error_messages())] : null,
        'skin result' => $words($skin->result),
        'moments' => $moments,
        'printed' => $printed,
        'disk' => $disk(),
        'offers' => $offers($kind === 'theme' ? 'themes' : 'plugins'),
    ]);
};
$offer = static function (string $kind, string $key, string $version, string $package): void {
    $transient = get_site_transient("update_{$kind}");
    if (!is_object($transient)) {
        $transient = (object) ['last_checked' => time(), 'checked' => [], 'response' => [], 'no_update' => [], 'translations' => []];
    }
    $entry = ['new_version' => $version, 'package' => $package, 'url' => 'https://example.com/zz-up'];
    if ($kind === 'plugins') {
        $entry = (object) (['id' => 'zz-up', 'slug' => 'zz-up-plugin', 'plugin' => $key] + $entry);
    } else {
        $entry = ['theme' => $key] + $entry;
    }
    $transient->response[$key] = $entry;
    set_site_transient("update_{$kind}", $transient);
};

$run('plugin: install', 'plugin', static fn ($u) => $u->install($p1));
$run('plugin: install again, the folder exists', 'plugin', static fn ($u) => $u->install($p2));
$run('plugin: install over it, overwrite_package', 'plugin', static fn ($u) => $u->install($p2, ['overwrite_package' => true]));
$run('plugin: an archive with no plugin in it', 'plugin', static fn ($u) => $u->install($none));
$run('plugin: a file that is not a zip', 'plugin', static fn ($u) => $u->install("{$tmp}/zz-up-not-a-zip.zip"));
$run('plugin: a package that is not there', 'plugin', static fn ($u) => $u->install("{$tmp}/zz-up-missing.zip"));
$run('plugin: update with nothing offered', 'plugin', static fn ($u) => $u->upgrade('zz-up-plugin/zz-up-plugin.php'));
$offer('plugins', 'zz-up-plugin/zz-up-plugin.php', '3.0', $p3);
$run('plugin: update to the offer', 'plugin', static fn ($u) => $u->upgrade('zz-up-plugin/zz-up-plugin.php'));
$offer('plugins', 'zz-up-plugin/zz-up-plugin.php', '3.0', $p2);
$run('plugin: bulk update to an offer', 'plugin', static fn ($u) => $u->bulk_upgrade(['zz-up-plugin/zz-up-plugin.php']));
$run('plugin: bulk update with nothing offered', 'plugin', static fn ($u) => $u->bulk_upgrade(['zz-up-plugin/zz-up-plugin.php']));
$offer('plugins', 'zz-up-plugin/zz-up-plugin.php', '3.0', $p3);
activate_plugin('zz-up-plugin/zz-up-plugin.php', '', false, true);
$run('plugin: update an active plugin', 'plugin', static fn ($u) => $u->upgrade('zz-up-plugin/zz-up-plugin.php'));
$say('plugin: still active after the update', is_plugin_active('zz-up-plugin/zz-up-plugin.php'));
deactivate_plugins('zz-up-plugin/zz-up-plugin.php', true);
$refused = static function (string $hook, string $label, callable $call) use ($run): void {
    $refuse = static fn () => new WP_Error("zz_{$hook}", "Refused by {$hook}.");
    add_filter("upgrader_{$hook}", $refuse);
    $run($label, 'plugin', $call);
    remove_filter("upgrader_{$hook}", $refuse);
};
$refused('pre_download', 'plugin: upgrader_pre_download answers an error', static fn ($u) => $u->install($p1, ['overwrite_package' => true]));
$refused('pre_install', 'plugin: upgrader_pre_install answers an error', static fn ($u) => $u->install($p1, ['overwrite_package' => true]));
$refused('source_selection', 'plugin: upgrader_source_selection answers an error', static fn ($u) => $u->install($p1, ['overwrite_package' => true]));
$refused('post_install', 'plugin: upgrader_post_install answers an error', static fn ($u) => $u->install($p1, ['overwrite_package' => true]));

$run('theme: install', 'theme', static fn ($u) => $u->install($t1));
$run('theme: install again, the folder exists', 'theme', static fn ($u) => $u->install($t2));
$offer('themes', 'zz-up-theme', '2.0', $t2);
$run('theme: update to the offer', 'theme', static fn ($u) => $u->upgrade('zz-up-theme'));
$run('theme: update with nothing offered', 'theme', static fn ($u) => $u->upgrade('zz-up-theme'));
$run('theme: an archive with no theme in it', 'theme', static fn ($u) => $u->install($none));

// What a package's own headers can refuse.
$run('plugin: a package that needs a newer PHP', 'plugin', static fn ($u) => $u->install($needsPhp, ['overwrite_package' => true]));
$run('plugin: a package that needs a newer WordPress', 'plugin', static fn ($u) => $u->install($needsWp, ['overwrite_package' => true]));
$run('theme: a package that needs a newer PHP', 'theme', static fn ($u) => $u->install($themePhp, ['overwrite_package' => true]));
$run('theme: a classic theme with no index.php', 'theme', static fn ($u) => $u->install($themeNoIndex, ['overwrite_package' => true]));
$run('theme: a block theme with no index.php', 'theme', static fn ($u) => $u->install($themeBlock, ['overwrite_package' => true]));
$offer('plugins', 'zz-up-plugin/zz-up-plugin.php', '3.0', $p3);
activate_plugin('zz-up-plugin/zz-up-plugin.php', '', false, true);
$run('plugin: bulk update an active plugin', 'plugin', static fn ($u) => $u->bulk_upgrade(['zz-up-plugin/zz-up-plugin.php']));
deactivate_plugins('zz-up-plugin/zz-up-plugin.php', true);

// How each quiet skin words what it is told, and what the base skin prints.
$tell = static function (WP_Upgrader_Skin $skin): void {
    $upgrader = new Plugin_Upgrader($skin);
    $upgrader->init();
    $upgrader->install_strings();
    $skin->feedback('downloading_package', '<b>x</b>&y');
    $skin->feedback('A literal <em>message</em> with %s', '<i>arg</i>');
    $skin->feedback('No placeholder here', 'extra');
    $skin->feedback('');
    $skin->feedback('   ');
    $skin->feedback('process_success_specific', 'Name', '1.0');
    $skin->error(new WP_Error('zz_one', 'First <b>bold</b>.', 'data <i>x</i> & y'));
    $skin->error(new WP_Error('zz_two', 'Second.', ['not' => 'a string']));
    $skin->error('up_to_date');
    $skin->error('folder_exists');
    $skin->error(new WP_Error());
};
$quietSkins = ['Automatic_Upgrader_Skin' => new Automatic_Upgrader_Skin(), 'WP_Ajax_Upgrader_Skin' => new WP_Ajax_Upgrader_Skin()];
foreach ($quietSkins as $class => $skin) {
    @ob_flush();
    $printedSoFar = '';
    $tell($skin);
    @ob_flush();
    $say("skin: {$class} told things", [
        'messages' => $skin->get_upgrade_messages(),
        'errors' => $skin instanceof WP_Ajax_Upgrader_Skin ? ['codes' => $skin->get_errors()->get_error_codes(), 'data' => $skin->get_errors()->get_all_error_data('zz_one'), 'text' => $skin->get_error_messages()] : null,
        'printed' => $printedSoFar,
    ]);
}
@ob_flush();
$printedSoFar = '';
$tell(new WP_Upgrader_Skin());
@ob_flush();
$say('skin: WP_Upgrader_Skin told things, printed', $printedSoFar);
$printedSoFar = '';

// The words each upgrader speaks in, as it sets them for an install and an update.
$u = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
$u->init();
$say('strings: generic', $u->strings);
foreach (['Plugin_Upgrader', 'Theme_Upgrader'] as $class) {
    foreach (['install_strings', 'upgrade_strings'] as $phase) {
        $u = new $class(new Automatic_Upgrader_Skin());
        $u->init();
        $u->{$phase}();
        $say("strings: {$class} {$phase}", $u->strings);
    }
}

// The skins that print, and the one that collects errors.
$run('plugin: install with the default skin', 'plugin', static fn ($u) => $u->install($p1, ['overwrite_package' => true]), new Plugin_Upgrader_Skin());
$run('plugin: install with the installer skin', 'plugin', static fn ($u) => $u->install($p2, ['overwrite_package' => true]), new Plugin_Installer_Skin(['type' => 'upload', 'url' => 'https://example.com/back', 'title' => 'Installing']));
$run('plugin: install with the installer skin, the folder exists', 'plugin', static fn ($u) => $u->install($p2), new Plugin_Installer_Skin(['type' => 'web']));
$run('plugin: install with the Ajax skin, the folder exists', 'plugin', static fn ($u) => $u->install($p2), new WP_Ajax_Upgrader_Skin());
$run('plugin: install with the Ajax skin, not a zip', 'plugin', static fn ($u) => $u->install("{$tmp}/zz-up-not-a-zip.zip"), new WP_Ajax_Upgrader_Skin());
$offer('plugins', 'zz-up-plugin/zz-up-plugin.php', '3.0', $p3);
activate_plugin('zz-up-plugin/zz-up-plugin.php', '', false, true);
$run('plugin: bulk update an active plugin with the bulk skin', 'plugin', static fn ($u) => $u->bulk_upgrade(['zz-up-plugin/zz-up-plugin.php']), new Bulk_Plugin_Upgrader_Skin(['url' => 'https://example.com/update', 'nonce' => 'bulk-update-plugins']));
$say('plugin: active after the bulk update', is_plugin_active('zz-up-plugin/zz-up-plugin.php'));
deactivate_plugins('zz-up-plugin/zz-up-plugin.php', true);
$say('cleanup event scheduled by the probe', !$cleanupBefore && (bool) wp_next_scheduled('wp_delete_temp_updater_backups'));
if (!$cleanupBefore) {
    wp_clear_scheduled_hook('wp_delete_temp_updater_backups');
}

restore_error_handler();
$passThrough = true;
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
