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
        WP_CLI::add_command('option get', [OptionCommand::class, 'get'], $early);
        WP_CLI::add_command('option add', [OptionCommand::class, 'add'], $early);
        WP_CLI::add_command('option update', [OptionCommand::class, 'update'], $early);
        WP_CLI::add_command('option delete', [OptionCommand::class, 'delete'], $early);
        WP_CLI::add_command('user list', [UserCommand::class, 'list'], $early);
        WP_CLI::add_command('user get', [UserCommand::class, 'get'], $early);
        WP_CLI::add_command('user login', [UserCommand::class, 'login'], $early);
        WP_CLI::add_command('minn', MinnCommand::class, $early);
    }
}
