<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;
use Minn\Content\Inventory;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Cron\Cron;
use Minn\Extension\Loader;
use Minn\Mail\MailSettings;
use Minn\Mail\Mailer;
use Minn\Mail\Message;
use Minn\Content\Site;
use Minn\Runtime\Recovery;
use Minn\Runtime\ScriptPack;

/**
 * Identifies the engine.
 *
 * ## EXAMPLES
 *
 *     wp minn version
 *     wp minn info
 *     wp minn probe
 */
final class MinnCommand
{
    /**
     * Prints the engine version.
     *
     * @when before_wp_load
     */
    public function version(array $args, array $assocArgs): void
    {
        WP_CLI::log(self::engineVersion());
    }

    /**
     * Prints the inventory CaptainCore gathers from inside WordPress:
     * plugins, themes, must-use plugins, core version, home. One
     * `key:value` line per field; JSON values have no newlines. Split
     * on the first colon only.
     *
     * @when before_wp_load
     */
    public function probe(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $inventory = new Inventory(ABSPATH . 'wp-content', $runtime->site);
        $fields = ['name', 'title', 'status', 'version'];
        $slim = static function (array $items) use ($fields): string {
            $rows = [];
            foreach ($items as $item) {
                $row = [];
                foreach ($fields as $field) {
                    $row[$field] = $item[$field];
                }
                $rows[] = $row;
            }
            return (string) json_encode($rows, JSON_UNESCAPED_SLASHES);
        };
        $core = '';
        $versionFile = ABSPATH . 'wp-includes/version.php';
        if (is_file($versionFile) && preg_match("/\\\$wp_version\s*=\s*'([^']+)'/", (string) file_get_contents($versionFile), $m)) {
            $core = $m[1];
        }
        WP_CLI::log('engine:minn');
        WP_CLI::log('engine_version:' . self::engineVersion());
        WP_CLI::log('core:' . $core);
        WP_CLI::log('home_url:' . ($runtime->site->option('home') ?? ''));
        WP_CLI::log('php_version:' . PHP_VERSION);
        WP_CLI::log('plugins:' . $slim($inventory->plugins()));
        WP_CLI::log('themes:' . $slim($inventory->themes()));
        WP_CLI::log('mu_plugins:' . $slim($inventory->mustUse()));
    }

    /**
     * Prints the engine's version and where it runs from.
     *
     * @when before_wp_load
     */
    public function info(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        WP_CLI::log('Minn Engine ' . self::engineVersion());
        WP_CLI::log('Engine dir: ' . MINN_ENGINE_DIR);
        WP_CLI::log('Site root: ' . ABSPATH);
        WP_CLI::log('Table prefix: ' . $runtime->db->prefix());
        WP_CLI::log('Home: ' . ($runtime->site->option('home') ?? ''));
        $loader = new Loader(ABSPATH . 'wp-content', $runtime->site);
        WP_CLI::log('Extensions: ' . (implode(', ', array_map(static fn ($m) => $m->slug . ' ' . $m->version, $loader->active())) ?: '(none)'));
    }

    /**
     * Runs the engine's scheduled work: due posts go live, expired rows are swept.
     *
     * @when before_wp_load
     */
    public function cron(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $cron = new Cron($runtime->db, $runtime->site, new PostWriter($runtime->db, new Posts($runtime->db), $runtime->site));
        foreach ($cron->run() as $line) {
            WP_CLI::log($line);
        }
        WP_CLI::success('Cron run complete.');
    }

    /**
     * Sends a test email through the site's mail settings.
     *
     * ## OPTIONS
     *
     * <to>
     * : The address to send to.
     *
     * @when before_wp_load
     */
    public function mail(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $settings = MailSettings::fromSite($runtime->site);
        $sent = Mailer::forSite($runtime->site)->send(new Message([$args[0]], '[' . ($runtime->site->option('blogname') ?? 'Site') . '] Test email', "This is a test email from Minn Engine, sent through the {$settings->transport} transport.\n"));
        if (!$sent) {
            WP_CLI::error("Mail failed through the {$settings->transport} transport; see the error log.");
        }
        WP_CLI::success("Sent through the {$settings->transport} transport" . ($settings->transport === 'smtp' ? " ({$settings->host}:{$settings->port})" : '') . '.');
    }

    /**
     * Says what a WordPress webroot will and will not get from the engine.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot to inspect. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function preflight(array $args, array $assocArgs): void
    {
        self::installer('preflight', $args, $assocArgs);
    }

    /**
     * Parks WordPress and installs the engine into a webroot.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * [--park=<dir>]
     * : Where WordPress's own files go. Defaults to wp-parked beside the webroot.
     *
     * [--force]
     * : Install even when preflight is RED.
     *
     * @when before_wp_load
     */
    public function install(array $args, array $assocArgs): void
    {
        self::installer('install', $args, $assocArgs);
    }

    /**
     * Removes the engine and puts WordPress's files back.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function eject(array $args, array $assocArgs): void
    {
        self::installer('eject', $args, $assocArgs);
    }

    /**
     * Says whether a webroot runs WordPress or the engine.
     *
     * ## OPTIONS
     *
     * [<webroot>]
     * : The webroot. Defaults to the current directory.
     *
     * @when before_wp_load
     */
    public function status(array $args, array $assocArgs): void
    {
        self::installer('status', $args, $assocArgs);
    }

    private static function installer(string $command, array $args, array $assocArgs): void
    {
        $argv = [$command, $args[0] ?? (string) getcwd()];
        foreach ($assocArgs as $key => $value) {
            $argv[] = '--' . $key . ($value === true ? '' : '=' . $value);
        }
        $code = Installer::main($argv, defined('MINN_ENGINE_DIR') ? MINN_ENGINE_DIR : dirname(__DIR__, 3));
        if ($code !== 0) {
            WP_CLI::halt($code);
        }
    }

    /**
     * The site's script pack: the GPL `wp-*` JavaScript packages the engine
     * does not reimplement, which a few plugin front ends need (the
     * WooCommerce block cart and checkout). The engine never ships them;
     * this installs them into the site's own wp-content, from the
     * WordPress the swap parked beside the webroot, or from any WordPress
     * tree named with --from.
     *
     * ## OPTIONS
     *
     * [<action>]
     * : status (the default), install, or remove.
     *
     * [--from=<path>]
     * : A WordPress tree to take the packages from. Defaults to the parked
     * copy the install recorded.
     *
     * @when before_wp_load
     */
    public function scripts(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $contentDir = ABSPATH . 'wp-content';
        $action = (string) ($args[0] ?? 'status');
        if ($action === 'status') {
            $handles = ScriptPack::handles($contentDir);
            WP_CLI::log(ScriptPack::installed($contentDir)
                ? count($handles) . ' handles from ' . ScriptPack::dir($contentDir)
                : 'not installed (the engine serves its own packages only)');
            return;
        }
        if ($action === 'remove') {
            WP_CLI::log(ScriptPack::remove($contentDir) ? 'removed' : 'nothing to remove');
            return;
        }
        if ($action !== 'install') {
            WP_CLI::error("Usage: wp minn scripts <status|install|remove> [--from=<path>]");
        }
        $from = is_string($assocArgs['from'] ?? null) ? $assocArgs['from'] : self::parkedTree();
        if ($from === null) {
            WP_CLI::error('No parked WordPress recorded for this site; pass --from=<path to a WordPress tree>.');
        }
        try {
            $result = ScriptPack::installFromTree($contentDir, $from);
        } catch (\RuntimeException $e) {
            WP_CLI::error($e->getMessage());
        }
        WP_CLI::log("installed {$result['files']} files into {$result['dir']}");
        WP_CLI::log(count(ScriptPack::handles($contentDir)) . ' handles now available');
    }

    /**
     * The extensions recovery paused after they killed a request, and the
     * way back. A plugin or theme that fatals is paused so the next
     * request answers without it; nothing loads it again until it is
     * resumed here.
     *
     * ## OPTIONS
     *
     * [<action>]
     * : status (the default), resume, or resume-all.
     *
     * [<name>]
     * : For resume: the plugin file or theme slug to let back in.
     *
     * [--theme]
     * : Treat the name as a theme rather than a plugin.
     *
     * @when before_wp_load
     */
    public function recovery(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $recovery = new Recovery(new Site($runtime->db), ABSPATH . 'wp-content');
        $action = (string) ($args[0] ?? 'status');
        if ($action === 'status') {
            $rows = [];
            foreach ($recovery->pausedPlugins() as $name => $error) {
                $rows[] = ['kind' => 'plugin', 'name' => $name, 'message' => (string) ($error['message'] ?? '')];
            }
            foreach ($recovery->pausedThemes() as $name => $error) {
                $rows[] = ['kind' => 'theme', 'name' => $name, 'message' => (string) ($error['message'] ?? '')];
            }
            if ($rows === []) {
                WP_CLI::log('nothing paused');
                return;
            }
            foreach ($rows as $row) {
                WP_CLI::log(sprintf('%s %s: %s', $row['kind'], $row['name'], $row['message']));
            }
            return;
        }
        if ($action === 'resume-all') {
            WP_CLI::log($recovery->resumeAll() . ' resumed');
            return;
        }
        if ($action !== 'resume' || !isset($args[1])) {
            WP_CLI::error('Usage: wp minn recovery <status|resume <name>|resume-all> [--theme]');
        }
        $kind = empty($assocArgs['theme']) ? 'plugin' : 'theme';
        WP_CLI::log($recovery->resume($kind, (string) $args[1]) ? "resumed {$kind} {$args[1]}" : "{$kind} {$args[1]} was not paused");
    }

    /** The WordPress the swap parked beside this webroot, when the manifest names one. */
    private static function parkedTree(): ?string
    {
        $manifest = (defined('MINN_ENGINE_DIR') ? MINN_ENGINE_DIR : dirname(__DIR__, 3)) . '/.install.json';
        if (!is_file($manifest)) {
            return null;
        }
        $park = (array) json_decode((string) file_get_contents($manifest), true);
        $dir = is_string($park['park'] ?? null) ? $park['park'] : null;
        return $dir !== null && is_dir($dir) ? $dir : null;
    }

    private static function engineVersion(): string
    {
        return defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.1';
    }
}
