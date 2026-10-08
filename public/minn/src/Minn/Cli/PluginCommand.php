<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\Content\Inventory;
use Minn\Content\PluginState;
use Minn\Extension\Loader;
use WP_CLI;
use WP_CLI\Formatter;

/** `wp plugin list|install|update|activate|deactivate|delete`: the inventory and the fleet's install/update/delete. */
final class PluginCommand
{
    private const FIELDS = ['name', 'status', 'update', 'version', 'update_version', 'auto_update'];

    /**
     * Lists installed plugins, must-use plugins, and drop-ins.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter to one status: active, inactive, must-use, or dropin.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each plugin.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function list(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $items = (new Inventory(ABSPATH . 'wp-content', $runtime->site))->plugins();
        $status = (string) ($assocArgs['status'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, static fn (array $item) => $item['status'] === $status));
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }

    /**
     * Activates one or more plugins: a WordPress plugin joins active_plugins
     * (its stored state; the engine runs none of its code), a Minn extension
     * joins the engine's own list.
     *
     * ## OPTIONS
     *
     * <plugin>...
     * : One or more plugins to activate.
     *
     * @when before_wp_load
     */
    public function activate(array $args, array $assocArgs): void
    {
        $this->switch($args, 'activate');
    }

    /**
     * Deactivates one or more plugins.
     *
     * ## OPTIONS
     *
     * <plugin>...
     * : One or more plugins to deactivate.
     *
     * @when before_wp_load
     */
    public function deactivate(array $args, array $assocArgs): void
    {
        $this->switch($args, 'deactivate');
    }

    /**
     * Installs one or more plugins from the plugin directory, a zip, or a URL.
     *
     * ## OPTIONS
     *
     * <plugin|zip|url>...
     * : A plugin slug, a local zip path, or a zip URL.
     *
     * [--version=<version>]
     * : Install that version from the directory instead of the current one.
     *
     * [--force]
     * : Overwrite an installed copy of the same folder.
     *
     * [--activate]
     * : Activate the plugin after it is installed (or if it is already on disk).
     *
     * @when before_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $installer = PackageInstaller::plugins(new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content'));
        $force = isset($assocArgs['force']);
        $activate = isset($assocArgs['activate']);
        $version = (string) ($assocArgs['version'] ?? '');
        $done = 0;
        $already = 0;
        $missing = 0;
        foreach ($args as $source) {
            [$folder, $fresh] = $installer->install($source, $force, $version);
            if ($folder === null) {
                $missing++;
                continue;
            }
            if ($fresh) {
                $done++;
            } else {
                $already++;
            }
            if ($activate) {
                $this->activateFolder($runtime, $folder);
            }
        }
        $total = count($args);
        if ($done > 0 && $missing === 0) {
            WP_CLI::success("Installed {$done} of {$total} plugins.");
            return;
        }
        if ($done > 0) {
            WP_CLI::error("Only installed {$done} of {$total} plugins.");
        }
        if ($missing === 0 && $already > 0) {
            WP_CLI::success('Plugin already installed.');
            return;
        }
        WP_CLI::error('No plugins installed.');
    }

    /**
     * Updates one or more plugins from the plugin directory.
     *
     * ## OPTIONS
     *
     * [<plugin>...]
     * : One or more plugins to update.
     *
     * [--all]
     * : If set, all plugins that have updates will be updated.
     *
     * [--exclude=<name>]
     * : Comma separated list of plugin names that should be excluded from updating.
     *
     * [--minor]
     * : Only perform updates for minor releases.
     *
     * [--patch]
     * : Only perform updates for patch releases.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - summary
     * ---
     *
     * [--version=<version>]
     * : If set, the plugin will be updated to the specified version.
     *
     * [--dry-run]
     * : Preview which plugins would be updated.
     *
     * @alias upgrade
     * @when before_wp_load
     */
    public function update(array $args, array $assocArgs): void
    {
        if ((string) ($assocArgs['version'] ?? '') !== '') {
            $this->pinVersion($args, (string) $assocArgs['version']);
            return;
        }
        AssetUpdate::boot('plugin')->run($args, $assocArgs);
    }

    /**
     * Deletes plugin files without deactivating.
     *
     * ## OPTIONS
     *
     * [<plugin>...]
     * : One or more plugin folders to delete.
     *
     * @when before_wp_load
     */
    public function delete(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        $done = 0;
        foreach ($args as $slug) {
            $dir = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
            $file = $dir . '.php';
            if (!is_dir($dir) && !is_file($file)) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            if (is_file($file) && !is_dir($dir)) {
                unlink($file);
            } else {
                $packages->remove('plugin', $slug);
            }
            WP_CLI::log("Deleted '{$slug}' plugin.");
            $done++;
        }
        if ($done > 0) {
            WP_CLI::success("Deleted {$done} of " . count($args) . ' plugins.');
            return;
        }
        WP_CLI::success('Plugin already deleted.');
    }

    /**
     * Checks if a given plugin is installed. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <plugin>
     * : The plugin folder to check.
     *
     * @when before_wp_load
     */
    public function is_installed(array $args, array $assocArgs): void
    {
        Runtime::boot();
        $slug = (string) ($args[0] ?? '');
        $root = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
        if (is_dir($root) || is_file($root . '.php')) {
            return;
        }
        WP_CLI::halt(1);
    }

    /**
     * Checks if a given plugin is active. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <plugin>
     * : The plugin folder to check.
     *
     * [--network]
     * : Ignored on a single site.
     *
     * @when before_wp_load
     */
    public function is_active(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $contentDir = ABSPATH . 'wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $plugin = $state->find((string) ($args[0] ?? ''));
        if ($plugin === null || !$state->isActive($plugin)) {
            WP_CLI::halt(1);
        }
    }

    /**
     * Searches the plugin directory.
     *
     * ## OPTIONS
     *
     * <search>
     * : The string to search for.
     *
     * [--page=<page>]
     * : Optional page to display.
     * ---
     * default: 1
     * ---
     *
     * [--per-page=<per-page>]
     * : Optional number of results to display.
     * ---
     * default: 10
     * ---
     *
     * [--field=<field>]
     * : Prints the value of a single field for each plugin.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields. Defaults to name,slug,rating.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function search(array $args, array $assocArgs): void
    {
        DirectorySearch::run('plugin', $args, $assocArgs);
    }

    /** The reference's wording, line for line: one warning per miss, a Success summary or an Error with none done. */
    private function switch(array $slugs, string $verb): void
    {
        $on = $verb === 'activate';
        $runtime = Runtime::boot();
        // With WordPress loaded, as WP-CLI runs these on the reference: a plugin's
        // requirements are checked and its activation and deactivation hooks run.
        Runtime::bootEngine();
        $contentDir = ABSPATH . 'wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $verb = $on ? 'activated' : 'deactivated';
        $done = 0;
        foreach ($slugs as $slug) {
            $plugin = $state->find($slug);
            if ($plugin === null) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            if ($state->isActive($plugin) === $on) {
                WP_CLI::warning($on ? "Plugin '{$slug}' is already active." : "Plugin '{$slug}' isn't active.");
                if (count($slugs) === 1) {
                    WP_CLI::success($on ? 'Plugin already activated.' : 'Plugin already deactivated.');
                    return;
                }
                continue;
            }
            if (!$on) {
                $state->deactivate($plugin);
            } elseif (($refusal = $state->activate($plugin)) !== null) {
                WP_CLI::warning('Failed to activate plugin. ' . self::plain($refusal->message));
                continue;
            }
            WP_CLI::log("Plugin '{$slug}' {$verb}.");
            $done++;
        }
        $total = count($slugs);
        if ($done === 0) {
            WP_CLI::error('No plugins ' . $verb . '.');
        }
        WP_CLI::success(ucfirst($verb) . " {$done} of {$total} plugins.");
    }

    /** `--version` on update force-installs that release using the install wording. */
    private function pinVersion(array $args, string $version): void
    {
        if ($args === []) {
            WP_CLI::error('Please specify one or more plugins, or use --all.');
        }
        $runtime = Runtime::boot();
        $installer = PackageInstaller::plugins(new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content'));
        $done = 0;
        foreach ($args as $slug) {
            $dest = rtrim(ABSPATH, '/') . '/wp-content/plugins/' . $slug;
            if (!is_dir($dest) && !is_file($dest . '.php')) {
                WP_CLI::warning("The '{$slug}' plugin could not be found.");
                continue;
            }
            [$folder, $fresh] = $installer->install($slug, true, $version);
            if ($folder !== null && $fresh) {
                $done++;
            }
        }
        if ($done > 0) {
            WP_CLI::success("Installed {$done} of " . count($args) . ' plugins.');
        }
    }

    private function activateFolder(Runtime $runtime, string $folder): void
    {
        $contentDir = rtrim(ABSPATH, '/') . '/wp-content';
        $state = new PluginState($runtime->site, new Inventory($contentDir, $runtime->site), new Loader($contentDir, $runtime->site));
        $plugin = $state->find($folder);
        WP_CLI::log("Activating '{$folder}'...");
        if ($plugin === null) {
            WP_CLI::warning("The '{$folder}' plugin could not be found.");
            return;
        }
        if ($state->isActive($plugin)) {
            return;
        }
        Runtime::bootEngine();
        $refusal = $state->activate($plugin);
        if ($refusal !== null) {
            WP_CLI::warning('Failed to activate plugin. ' . self::plain($refusal->message));
            return;
        }
        WP_CLI::log("Plugin '{$folder}' activated.");
    }

    /** A refusal's HTML message as WP-CLI words it in a warning: links dropped, tags stripped, no "Error:" lead. */
    private static function plain(string $message): string
    {
        return trim(str_replace('Error: ', '', strip_tags((string) preg_replace('/<a\s[^>]+>.*<\/a>/im', '', $message))));
    }
}
