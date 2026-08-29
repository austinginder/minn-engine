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
        self::leaf('plugin activate', [PluginCommand::class, 'activate'], $early);
        self::leaf('plugin deactivate', [PluginCommand::class, 'deactivate'], $early);
        self::leaf('theme list', [ThemeCommand::class, 'list'], $early);
        self::leaf('cache flush', [CacheCommand::class, 'flush'], $early);
        self::replace('search-replace', SearchReplaceCommand::class, $early);
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
