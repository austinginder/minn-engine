<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Files;

/**
 * Deleting plugins as the reference's delete_plugins does it: each in turn
 * is uninstalled first when it can be (its uninstall.php, or the callback
 * it registered), then delete_plugin, its folder (or file) removed,
 * deleted_plugin, and its installed translations removed with it; the
 * updates offered for the deleted ones are forgotten. A plugin already
 * gone counts as deleted. Only what lies inside the plugins folder is
 * ever removed, and a symlinked folder is unlinked, never followed.
 */
final class PluginRemoval
{
    /**
     * delete_plugins: true, false for none asked, or the error naming those left behind.
     *
     * @param array<int, string> $plugins plugin files, relative to the plugins folder
     */
    public static function delete(array $plugins): bool|\WP_Error
    {
        if ($plugins === []) {
            return false;
        }
        $translations = (array) \wp_get_installed_translations('plugins');
        $failed = [];
        foreach (array_map('strval', $plugins) as $plugin) {
            if (!self::deleteOne($plugin, $translations)) {
                $failed[] = $plugin;
            }
        }
        self::forgetUpdates(array_diff(array_map('strval', $plugins), $failed));
        return $failed === [] ? true : new \WP_Error('could_not_remove_plugin', sprintf('Could not fully remove the plugin(s) %s.', implode(', ', $failed)));
    }

    /**
     * uninstall_plugin: pre_uninstall_plugin, then the plugin's uninstall.php
     * (WP_UNINSTALL_PLUGIN naming it) or the callback it registered (heard on
     * uninstall_<file>, with the plugin loaded); either is forgotten once run.
     */
    public static function uninstall(string $plugin): ?bool
    {
        $file = (string) \plugin_basename($plugin);
        $registered = (array) \get_option('uninstall_plugins');
        \do_action('pre_uninstall_plugin', $plugin, $registered);
        $script = WP_PLUGIN_DIR . '/' . dirname($file) . '/uninstall.php';
        if (file_exists($script)) {
            self::forget($file, $registered);
            // A constant lasts the process: a worker that uninstalled one plugin keeps naming it.
            defined('WP_UNINSTALL_PLUGIN') || define('WP_UNINSTALL_PLUGIN', $file);
            \wp_register_plugin_realpath(WP_PLUGIN_DIR . '/' . $file);
            include_once $script;
            return true;
        }
        if (isset($registered[$file])) {
            $callback = $registered[$file];
            self::forget($file, $registered);
            \wp_register_plugin_realpath(WP_PLUGIN_DIR . '/' . $file);
            include_once WP_PLUGIN_DIR . '/' . $file;
            \add_action("uninstall_{$file}", $callback);
            \do_action("uninstall_{$file}");
        }
        return null;
    }

    /** One plugin, uninstalled, removed and announced; whether it is gone. @param array<string, mixed> $translations */
    private static function deleteOne(string $plugin, array $translations): bool
    {
        if (\is_uninstallable_plugin($plugin)) {
            \uninstall_plugin($plugin);
        }
        \do_action('delete_plugin', $plugin);
        $deleted = self::removeFiles($plugin);
        \do_action('deleted_plugin', $plugin, $deleted);
        if ($deleted) {
            self::removeTranslations(dirname($plugin), $translations);
        }
        return $deleted;
    }

    /** The plugin's folder, or its file when it has none; false for anything outside the plugins folder. */
    private static function removeFiles(string $plugin): bool
    {
        $root = rtrim(WP_PLUGIN_DIR, '/');
        if (\validate_file($plugin) !== 0 || $plugin === '' || str_starts_with($plugin, '/')) {
            return false;
        }
        $path = $root . '/' . (str_contains($plugin, '/') ? dirname($plugin) : $plugin);
        $parent = realpath(dirname($path));
        $rootReal = realpath($root);
        if ($parent === false || $rootReal === false || ($parent !== $rootReal && !str_starts_with($parent . '/', $rootReal . '/'))) {
            return !file_exists($path) && !is_link($path);
        }
        if (is_link($path) || is_file($path)) {
            return @unlink($path);
        }
        return !is_dir($path) || Files::deleteTree($path);
    }

    /** A folder plugin's installed translations (.po, .mo, .l10n.php, script .json), by locale. @param array<string, mixed> $translations */
    private static function removeTranslations(string $slug, array $translations): void
    {
        if ($slug === '.' || empty($translations[$slug]) || !is_array($translations[$slug])) {
            return;
        }
        $dir = WP_LANG_DIR . '/plugins/';
        foreach (array_keys($translations[$slug]) as $locale) {
            foreach (['.po', '.mo', '.l10n.php'] as $extension) {
                @unlink("{$dir}{$slug}-{$locale}{$extension}");
            }
            foreach (glob("{$dir}{$slug}-{$locale}-*.json") ?: [] as $json) {
                @unlink($json);
            }
        }
    }

    /** The registered uninstall callback, dropped from the option. @param array<string, mixed> $registered */
    private static function forget(string $file, array $registered): void
    {
        if (isset($registered[$file])) {
            unset($registered[$file]);
            \update_option('uninstall_plugins', $registered);
        }
    }

    /** The updates offered for plugins that are gone. @param array<int, string> $deleted */
    private static function forgetUpdates(array $deleted): void
    {
        $offered = \get_site_transient('update_plugins');
        if (!is_object($offered) || !isset($offered->response) || !is_array($offered->response)) {
            return;
        }
        $kept = array_diff_key($offered->response, array_flip($deleted));
        if (count($kept) !== count($offered->response)) {
            $offered->response = $kept;
            \set_site_transient('update_plugins', $offered);
        }
    }
}
