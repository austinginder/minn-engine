<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Auth\Capabilities;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Context;
use Minn\Db;
use Minn\Engine;
use Minn\Front\Permalinks;
use Minn\Runtime\Plugins;
use Minn\Runtime\Runtime as WordPressRuntime;
use Minn\Theme\Theme;
use WP_CLI;

/**
 * The engine, booted for a command: reads the site's wp-config.php (which
 * ends in wp-settings.php, whose engine boot stops short of serving under
 * WP-CLI) so the database constants and table prefix are known.
 */
final class Runtime
{
    private static ?self $shared = null;
    private static ?WordPressRuntime $engine = null;

    private function __construct(
        public readonly Db $db,
        public readonly Site $site,
        public readonly Users $users,
        public readonly Capabilities $capabilities,
        public readonly Permalinks $permalinks,
    ) {
    }

    /** The engine's runtime for a CLI process, booted once. */
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

    /**
     * The full WordPress runtime for a command, booted once: the facade is
     * defined, the site's plugins load as code, and the lifecycle actions
     * fire, so a verb can fire a scheduled hook whose callback a plugin
     * registered. The lighter boot() does none of this; only a command that
     * needs the runtime (cron) asks for this, since it loads every plugin.
     */
    public static function bootEngine(): WordPressRuntime
    {
        if (self::$engine !== null) {
            return self::$engine;
        }
        $lite = self::boot();
        $db = $lite->db;
        $context = new Context($db, $lite->site, null, Reader::anonymous(), $lite->capabilities, MINN_ENGINE_DIR, ABSPATH, Engine::WP_VERSION);
        $runtime = WordPressRuntime::boot(new WordPressRuntime($context));
        $permalinks = Permalinks::fromDb($db);
        $theme = Theme::active($lite->site, $permalinks, $context->themesDir());
        $runtime->set('permalinks', $permalinks);
        $runtime->set('block_theme', $theme !== null);
        $runtime->set('theme', $theme);
        Plugins::load($runtime);
        return self::$engine = $runtime;
    }

    /** wp-config.php sets $table_prefix as a variable; the engine reads it as a global. */
    private static function loadConfig(string $file): void
    {
        define('MINN_CLI_RUNTIME', true);
        require_once $file;
        if (isset($table_prefix)) {
            $GLOBALS['table_prefix'] = $table_prefix;
        }
    }
}
