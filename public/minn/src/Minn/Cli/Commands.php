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
    /** Registers every engine verb with WP-CLI in the phase it runs in. */
    public static function register(): void
    {
        $early = ['when' => 'before_wp_load'];
        self::leaf('option get', [OptionCommand::class, 'get'], $early);
        self::leaf('option add', [OptionCommand::class, 'add'], $early);
        self::leaf('option update', [OptionCommand::class, 'update'], $early);
        self::leaf('option set', [OptionCommand::class, 'update'], $early);
        self::leaf('option delete', [OptionCommand::class, 'delete'], $early);
        self::leaf('user list', [UserCommand::class, 'list'], $early);
        self::leaf('user get', [UserCommand::class, 'get'], $early);
        self::leaf('user login', [UserCommand::class, 'login'], $early);
        self::leaf('user create', [UserCommand::class, 'create'], $early);
        self::leaf('user update', [UserCommand::class, 'update'], $early);
        self::leaf('user delete', [UserCommand::class, 'delete'], $early);
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
        WP_CLI::add_command('minn', MinnCommand::class, $early);
    }

    /**
     * Registers a leaf and re-adds it after the parent lands. WP-CLI
     * registers `plugin` as a namespace first; its later class command
     * would otherwise overwrite our list.
     *
     * @param callable|array{0: class-string, 1: string} $callable
     * @param array<string, string> $args
     */
    private static function leaf(string $name, callable|array $callable, array $args): void
    {
        WP_CLI::add_command($name, $callable, $args);
        $parent = explode(' ', $name)[0];
        WP_CLI::add_hook("after_add_command:{$parent}", static function () use ($name, $callable, $args): void {
            WP_CLI::add_command($name, $callable, $args);
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
