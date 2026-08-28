<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Auth\Capabilities;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Permalinks;
use WP_CLI;

/**
 * The engine, booted for a command: reads the site's wp-config.php (which
 * ends in wp-settings.php, whose engine boot stops short of serving under
 * WP-CLI) so the database constants and table prefix are known.
 */
final class Runtime
{
    private static ?self $shared = null;

    private function __construct(
        public readonly Db $db,
        public readonly Site $site,
        public readonly Users $users,
        public readonly Capabilities $capabilities,
        public readonly Permalinks $permalinks,
    ) {
    }

    public static function boot(): self
    {
        if (self::$shared !== null) {
            return self::$shared;
        }
        $root = defined('ABSPATH') ? ABSPATH : rtrim(WP_CLI::get_runner()->find_wp_root(), '/') . '/';
        if (!is_file($root . 'wp-config.php')) {
            WP_CLI::error("No wp-config.php found at {$root}.");
        }
        self::loadConfig($root . 'wp-config.php');
        $db = Db::shared();
        return self::$shared = new self($db, new Site($db), new Users($db), Capabilities::fromDb($db), Permalinks::fromDb($db));
    }

    /** wp-config.php sets $table_prefix as a variable; the engine reads it as a global. */
    private static function loadConfig(string $file): void
    {
        require_once $file;
        if (isset($table_prefix)) {
            $GLOBALS['table_prefix'] = $table_prefix;
        }
    }
}
