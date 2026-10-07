<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Ops\Packages;
use Minn\Content\Inventory;
use Minn\Support\FileHeaders;
use WP_CLI;
use WP_CLI\Formatter;

/**
 * `wp theme list|install|update|activate|delete`: the inventory CaptainCore
 * reads, and the install/update the fleet's `wp theme` verbs run.
 */
final class ThemeCommand
{
    private const FIELDS = ['name', 'status', 'update', 'version', 'update_version', 'auto_update'];

    /**
     * Lists installed themes.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter to one status: active, inactive, or parent.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each theme.
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
        $items = (new Inventory(ABSPATH . 'wp-content', $runtime->site))->themes();
        $status = (string) ($assocArgs['status'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, static fn (array $item) => $item['status'] === $status));
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }

    /**
     * Installs one or more themes from wordpress.org, a zip, or a URL.
     *
     * ## OPTIONS
     *
     * <theme|zip|url>...
     * : A theme slug, a local zip path, or a zip URL.
     *
     * [--version=<version>]
     * : Install that wordpress.org version instead of the current one.
     *
     * [--force]
     * : Overwrite an installed copy of the same folder.
     *
     * [--activate]
     * : Activate the theme after it is installed (or if it is already on disk).
     *
     * @when before_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $installer = PackageInstaller::themes(new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content'));
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
                WP_CLI::log("Activating '{$folder}'...");
                $this->switchTo($runtime, $folder);
            }
        }
        if ($done > 0) {
            WP_CLI::success("Installed {$done} of " . count($args) . ' themes.');
            return;
        }
        if ($missing === 0 && $already > 0) {
            WP_CLI::success('Theme already installed.');
            return;
        }
        WP_CLI::error('No themes installed.');
    }

    /**
     * Updates one or more themes from wordpress.org.
     *
     * ## OPTIONS
     *
     * [<theme>...]
     * : One or more themes to update.
     *
     * [--all]
     * : If set, all themes that have updates will be updated.
     *
     * [--exclude=<theme-names>]
     * : Comma separated list of theme names that should be excluded from updating.
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
     * : If set, the theme will be updated to the specified version.
     *
     * [--dry-run]
     * : Preview which themes would be updated.
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
        AssetUpdate::boot('theme')->run($args, $assocArgs);
    }

    /**
     * Activates a theme.
     *
     * ## OPTIONS
     *
     * <theme>
     * : The theme folder to activate.
     *
     * @when before_wp_load
     */
    public function activate(array $args, array $assocArgs): void
    {
        $this->switchTo(Runtime::boot(), (string) ($args[0] ?? ''));
    }

    /**
     * Deletes one or more themes from disk.
     *
     * ## OPTIONS
     *
     * [<theme>...]
     * : One or more theme folders to delete.
     *
     * [--force]
     * : Allow deleting the active theme.
     *
     * @when before_wp_load
     */
    public function delete(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $packages = new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content');
        $active = (string) ($runtime->site->option('stylesheet') ?? '');
        $force = isset($assocArgs['force']);
        $done = 0;
        $blocked = 0;
        foreach ($args as $slug) {
            $dir = rtrim(ABSPATH, '/') . '/wp-content/themes/' . $slug;
            if (!is_dir($dir)) {
                WP_CLI::warning("The '{$slug}' theme could not be found.");
                continue;
            }
            if (!$force && $slug === $active) {
                WP_CLI::warning("Can't delete the currently active theme: {$slug}");
                $blocked++;
                continue;
            }
            $packages->remove('theme', $slug);
            WP_CLI::log("Deleted '{$slug}' theme.");
            $done++;
        }
        if ($done > 0) {
            WP_CLI::success("Deleted {$done} of " . count($args) . ' themes.');
            return;
        }
        if ($blocked > 0) {
            WP_CLI::error('No themes deleted.');
        }
        WP_CLI::success('Theme already deleted.');
    }

    /** `--version` on update force-installs that release using the install wording. */
    private function pinVersion(array $args, string $version): void
    {
        if ($args === []) {
            WP_CLI::error('Please specify one or more themes, or use --all.');
        }
        $runtime = Runtime::boot();
        $installer = PackageInstaller::themes(new Packages($runtime->site, rtrim(ABSPATH, '/') . '/wp-content'));
        $done = 0;
        foreach ($args as $slug) {
            if (!is_dir(rtrim(ABSPATH, '/') . '/wp-content/themes/' . $slug)) {
                WP_CLI::error("The '{$slug}' theme could not be found.");
            }
            [$folder, $fresh] = $installer->install($slug, true, $version);
            if ($folder !== null && $fresh) {
                $done++;
            }
        }
        if ($done > 0) {
            WP_CLI::success("Installed {$done} of " . count($args) . ' themes.');
        }
    }

    private function switchTo(Runtime $runtime, string $slug): void
    {
        $headers = FileHeaders::values(rtrim(ABSPATH, '/') . '/wp-content/themes/' . $slug . '/style.css', ['Theme Name', 'Template']);
        if ($headers['Theme Name'] === '') {
            WP_CLI::error("The '{$slug}' theme could not be found.");
        }
        if ((string) ($runtime->site->option('stylesheet') ?? '') === $slug) {
            WP_CLI::warning("The '{$headers['Theme Name']}' theme is already active.");
            return;
        }
        $template = $headers['Template'];
        if ($template !== '' && !is_dir(rtrim(ABSPATH, '/') . '/wp-content/themes/' . $template)) {
            WP_CLI::error("The parent theme is not installed.");
        }
        $runtime->site->setOption('stylesheet', $slug);
        $runtime->site->setOption('template', $template === '' ? $slug : $template);
        $runtime->site->setOption('current_theme', $headers['Theme Name']);
        WP_CLI::success("Switched to '{$headers['Theme Name']}' theme.");
    }

    /**
     * Checks if a given theme is installed. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <theme>
     * : The theme folder to check.
     *
     * @when before_wp_load
     */
    public function is_installed(array $args, array $assocArgs): void
    {
        Runtime::boot();
        $slug = (string) ($args[0] ?? '');
        if (is_dir(rtrim(ABSPATH, '/') . '/wp-content/themes/' . $slug)) {
            return;
        }
        WP_CLI::halt(1);
    }

    /**
     * Checks if a given theme is active. Exit 0 when it is, 1 when not.
     *
     * ## OPTIONS
     *
     * <theme>
     * : The theme folder to check.
     *
     * @when before_wp_load
     */
    public function is_active(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        if ((string) ($runtime->site->option('stylesheet') ?? '') !== (string) ($args[0] ?? '')) {
            WP_CLI::halt(1);
        }
    }

    /**
     * Searches the wordpress.org theme directory.
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
     * : Optional number of results to display. Defaults to 10.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each theme.
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
        DirectorySearch::run('theme', $args, $assocArgs);
    }
}
