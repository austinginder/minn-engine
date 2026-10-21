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
        // A verb named with WP-CLI's own command takes that command's synopsis once it is loaded, and on a site
        // WP-CLI can load it is that command, run with the site's plugins (decision 3B, Bundled::loadable).
        // The rest stay the engine's: the gate, the update service, Minn's releases and Cove's sign-in link.
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
        self::leaf('plugin list', [PluginCommand::class, 'list'], $early, ['Plugin_Command', 'list_']);
        self::leaf('plugin install', [PluginCommand::class, 'install'], $early);
        self::leaf('plugin activate', [PluginCommand::class, 'activate'], $early);
        self::leaf('plugin deactivate', [PluginCommand::class, 'deactivate'], $early);
        self::leaf('plugin delete', [PluginCommand::class, 'delete'], $early);
        self::leaf('plugin is-installed', [PluginCommand::class, 'is_installed'], $early, ['Plugin_Command', 'is_installed']);
        self::leaf('plugin is-active', [PluginCommand::class, 'is_active'], $early, ['Plugin_Command', 'is_active']);
        self::leaf('plugin search', [PluginCommand::class, 'search'], $early);
        self::leaf('plugin update', [PluginCommand::class, 'update'], $early);
        self::leaf('plugin upgrade', [PluginCommand::class, 'update'], $early);
        self::leaf('theme list', [ThemeCommand::class, 'list'], $early, ['Theme_Command', 'list_']);
        self::leaf('theme install', [ThemeCommand::class, 'install'], $early);
        self::leaf('theme activate', [ThemeCommand::class, 'activate'], $early);
        self::leaf('theme delete', [ThemeCommand::class, 'delete'], $early);
        self::leaf('theme is-installed', [ThemeCommand::class, 'is_installed'], $early, ['Theme_Command', 'is_installed']);
        self::leaf('theme is-active', [ThemeCommand::class, 'is_active'], $early, ['Theme_Command', 'is_active']);
        self::leaf('theme search', [ThemeCommand::class, 'search'], $early);
        self::leaf('theme update', [ThemeCommand::class, 'update'], $early);
        self::leaf('theme upgrade', [ThemeCommand::class, 'update'], $early);
        self::leaf('cron event list', [CronCommand::class, 'event_list'], $early, ['Cron_Event_Command', 'list_']);
        self::leaf('cron event run', [CronCommand::class, 'event_run'], $early, ['Cron_Event_Command', 'run']);
        self::leaf('cron event schedule', [CronCommand::class, 'event_schedule'], $early, ['Cron_Event_Command', 'schedule']);
        self::leaf('cron event delete', [CronCommand::class, 'event_delete'], $early, ['Cron_Event_Command', 'delete']);
        self::leaf('cron event unschedule', [CronCommand::class, 'event_unschedule'], $early, ['Cron_Event_Command', 'unschedule']);
        self::leaf('cron schedule list', [CronCommand::class, 'schedule_list'], $early, ['Cron_Schedule_Command', 'list_']);
        self::leaf('cron test', [CronCommand::class, 'test'], $early, ['Cron_Command', 'test']);
        self::leaf('cache flush', [CacheCommand::class, 'flush'], $early, ['Cache_Command', 'flush']);
        self::leaf('rewrite flush', [RewriteCommand::class, 'flush'], $early, ['Rewrite_Command', 'flush']);
        self::leaf('rewrite structure', [RewriteCommand::class, 'structure'], $early, ['Rewrite_Command', 'structure']);
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
        $run = self::runner($callable, $bundled);
        WP_CLI::add_command($name, $run, $args + (is_array($callable) && $run !== $callable ? Bundled::docs($callable[0], $callable[1]) : []));
        $parent = explode(' ', $name)[0];
        WP_CLI::add_hook("after_add_command:{$parent}", static function () use ($name, $callable, $run, $args, $bundled): void {
            $docs = isset($bundled[0], $bundled[1]) ? Bundled::docs($bundled[0], $bundled[1]) : [];
            if ($docs === [] && is_array($callable) && $run !== $callable) {
                $docs = Bundled::docs($callable[0], $callable[1]);
            }
            WP_CLI::add_command($name, $run, $args + $docs);
        });
    }

    /**
     * What a leaf runs: the engine's method, or, for a verb named with
     * WP-CLI's own command, that command whenever WP-CLI can load the site.
     *
     * @param callable|array{0: class-string, 1: string} $callable
     * @param array{0?: string, 1?: string} $bundled
     * @return callable|array{0: class-string, 1: string}
     */
    private static function runner(callable|array $callable, array $bundled): callable|array
    {
        if (!isset($bundled[0], $bundled[1]) || !is_array($callable)) {
            return $callable;
        }
        return static function (array $args, array $assocArgs) use ($callable, $bundled): void {
            if (Bundled::loadable()) {
                Bundled::run($bundled[0], $bundled[1], $args, $assocArgs);
                return;
            }
            [$class, $method] = $callable;
            (new $class())->{$method}($args, $assocArgs);
        };
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
