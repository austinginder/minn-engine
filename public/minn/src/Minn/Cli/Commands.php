<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;

/**
 * The verbs the engine answers to. Every one is registered for the
 * before_wp_load phase, which WP-CLI runs before it looks for WordPress,
 * and each replaces the bundled subcommand of the same name (a deferred
 * addition lands after the bundle and overrides its leaf).
 */
final class Commands
{
    /** The engine verbs that read the site, so ask that it be installed (core verify-checksums reads files only, as on the reference). */
    private static array $siteVerbs = [];

    /** Registers every engine verb with WP-CLI in the phase it runs in. */
    public static function register(): void
    {
        $early = ['when' => 'before_wp_load'];
        // Options and users take the bundled command's synopsis (the arguments and values this WP-CLI accepts) once it is loaded.
        self::leaf('option get', [OptionCommand::class, 'get'], $early, ['Option_Command', 'get']);
        self::leaf('option add', [OptionCommand::class, 'add'], $early, ['Option_Command', 'add']);
        self::leaf('option update', [OptionCommand::class, 'update'], $early, ['Option_Command', 'update']);
        self::leaf('option set', [OptionCommand::class, 'update'], $early, ['Option_Command', 'update']);
        self::leaf('option delete', [OptionCommand::class, 'delete'], $early, ['Option_Command', 'delete']);
        self::leaf('user list', [UserCommand::class, 'list'], $early, ['User_Command', 'list_']);
        self::leaf('user get', [UserCommand::class, 'get'], $early, ['User_Command', 'get']);
        self::leaf('user login', [UserCommand::class, 'login'], $early);
        // User create, update and delete stay WP-CLI's: they run on the engine's WordPress runtime, its
        // hooks and mail (notices through wp_mail) as on the reference.
        self::leaf('plugin list', [PluginCommand::class, 'list'], $early);
        self::leaf('plugin install', [PluginCommand::class, 'install'], $early);
        self::leaf('plugin activate', [PluginCommand::class, 'activate'], $early);
        self::leaf('plugin deactivate', [PluginCommand::class, 'deactivate'], $early);
        self::leaf('plugin delete', [PluginCommand::class, 'delete'], $early);
        self::leaf('plugin is-installed', [PluginCommand::class, 'is_installed'], $early);
        self::leaf('plugin is-active', [PluginCommand::class, 'is_active'], $early);
        self::leaf('plugin search', [PluginCommand::class, 'search'], $early);
        self::leaf('plugin update', [PluginCommand::class, 'update'], $early);
        self::leaf('plugin upgrade', [PluginCommand::class, 'update'], $early);
        self::leaf('theme list', [ThemeCommand::class, 'list'], $early);
        self::leaf('theme install', [ThemeCommand::class, 'install'], $early);
        self::leaf('theme activate', [ThemeCommand::class, 'activate'], $early);
        self::leaf('theme delete', [ThemeCommand::class, 'delete'], $early);
        self::leaf('theme is-installed', [ThemeCommand::class, 'is_installed'], $early);
        self::leaf('theme is-active', [ThemeCommand::class, 'is_active'], $early);
        self::leaf('theme search', [ThemeCommand::class, 'search'], $early);
        self::leaf('theme update', [ThemeCommand::class, 'update'], $early);
        self::leaf('theme upgrade', [ThemeCommand::class, 'update'], $early);
        self::leaf('cron event list', [CronCommand::class, 'event_list'], $early);
        self::leaf('cron event run', [CronCommand::class, 'event_run'], $early);
        self::leaf('cron event schedule', [CronCommand::class, 'event_schedule'], $early);
        self::leaf('cron event delete', [CronCommand::class, 'event_delete'], $early);
        self::leaf('cron event unschedule', [CronCommand::class, 'event_unschedule'], $early);
        self::leaf('cron schedule list', [CronCommand::class, 'schedule_list'], $early);
        self::leaf('cron test', [CronCommand::class, 'test'], $early);
        self::leaf('cache flush', [CacheCommand::class, 'flush'], $early);
        self::leaf('rewrite flush', [RewriteCommand::class, 'flush'], $early);
        self::leaf('rewrite structure', [RewriteCommand::class, 'structure'], $early);
        self::replace('search-replace', SearchReplaceCommand::class, $early);
        self::replace('maintenance-mode', MaintenanceCommand::class, $early);
        self::leaf('maintenance-mode activate', [MaintenanceCommand::class, 'activate'], $early);
        self::leaf('maintenance-mode deactivate', [MaintenanceCommand::class, 'deactivate'], $early);
        self::leaf('maintenance-mode status', [MaintenanceCommand::class, 'status'], $early);
        self::leaf('maintenance-mode is-active', [MaintenanceCommand::class, 'is_active'], $early);
        self::leaf('core update', [CoreCommand::class, 'update'], $early);
        self::leaf('core upgrade', [CoreCommand::class, 'update'], $early);
        self::leaf('core verify-checksums', [CoreCommand::class, 'verifyChecksums'], $early);
        // WP-CLI's checksum package adds this subcommand on its own, after core; ours goes back on top of it.
        self::replace('core verify-checksums', [CoreCommand::class, 'verifyChecksums'], $early);
        WP_CLI::add_hook('before_invoke:core download', [CoreCommand::class, 'guardDownload']);
        WP_CLI::add_command('minn', MinnCommand::class, $early);
        unset(self::$siteVerbs['core verify-checksums']);
        WP_CLI::add_hook('before_run_command', [self::class, 'requireInstalled']);
    }

    /**
     * An engine verb on a site whose database holds no install stops as the
     * reference's WP-CLI stops it while WordPress loads, before the verb's
     * arguments are read: its own message, naming any other table prefix
     * that has an install (WP-CLI's config-create scenarios).
     *
     * @param list<string> $args
     */
    public static function requireInstalled(array $args): void
    {
        $asked = null;
        foreach ([3, 2, 1] as $words) {
            $name = implode(' ', array_slice($args, 0, $words));
            if (isset(self::$siteVerbs[$name])) {
                $asked = $name;
                break;
            }
        }
        $root = defined('ABSPATH') ? ABSPATH : rtrim((string) WP_CLI::get_runner()->find_wp_root(), '/') . '/';
        if ($asked === null || !is_file($root . 'wp-config.php')) {
            return;
        }
        $db = Runtime::database();
        $prefix = $db->prefix();
        $tables = array_map(static fn (array $row): string => (string) reset($row), $db->rows("SHOW TABLES LIKE '%_options'"));
        if (in_array("{$prefix}options", $tables, true) && (string) $db->value("SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl' LIMIT 1") !== '') {
            return;
        }
        $others = array_values(array_filter(array_map(static fn (string $table): string => substr($table, 0, -strlen('options')), $tables), static fn (string $found): bool => $found !== $prefix));
        sort($others);
        WP_CLI::error($others === []
            ? "The site you have requested is not installed.\nRun `wp core install` to create database tables."
            : "The site you have requested is not installed.\nYour table prefix is '{$prefix}'. Found " . (count($others) > 1 ? 'installations' : 'installation') . ' with table prefix: ' . implode(', ', $others) . ".\nOr, run `wp core install` to create database tables.");
    }

    /**
     * Registers a leaf and re-adds it after the parent lands. WP-CLI
     * registers `plugin` as a namespace first; its later class command
     * would otherwise overwrite our list. With a bundled class and method,
     * the re-added leaf takes that method's docs: WP-CLI loads its bundled
     * commands' classes only after the engine registers, by the time the
     * parent lands.
     *
     * @param callable|array{0: class-string, 1: string} $callable
     * @param array<string, string> $args
     * @param array{0?: string, 1?: string} $bundled
     */
    private static function leaf(string $name, callable|array $callable, array $args, array $bundled = []): void
    {
        self::$siteVerbs[$name] = true;
        WP_CLI::add_command($name, $callable, $args);
        $parent = explode(' ', $name)[0];
        WP_CLI::add_hook("after_add_command:{$parent}", static function () use ($name, $callable, $args, $bundled): void {
            $docs = isset($bundled[0], $bundled[1]) ? Bundled::docs($bundled[0], $bundled[1]) : [];
            WP_CLI::add_command($name, $callable, $args + $docs);
        });
    }

    /**
     * A root command the bundle also registers. Re-add after the bundle
     * without recursing into our own after_add_command hook.
     *
     * @param callable|array{0: class-string, 1: string}|class-string $callable
     * @param array<string, string> $args
     */
    private static function replace(string $name, callable|array|string $callable, array $args): void
    {
        self::$siteVerbs[$name] = true;
        $adding = false;
        $add = static function () use (&$adding, $name, $callable, $args): void {
            if ($adding) {
                return;
            }
            $adding = true;
            WP_CLI::add_command($name, $callable, $args);
            $adding = false;
        };
        $add();
        WP_CLI::add_hook("after_add_command:{$name}", $add);
    }
}
