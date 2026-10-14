<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Ops\EngineUpdate;
use Minn\Runtime\Options;
use Minn\Support\FileHeaders;
use Minn\Support\Files;
use Minn\Support\FileTree;
use Minn\Support\Serialized;
use RuntimeException;
use Throwable;

/**
 * Minn Admin's home on a site is wp-content/plugins/minn-admin: the same
 * plugin WordPress runs, so an eject leaves the site its admin, and Minn
 * Admin's own updates keep it current between engine releases. The engine
 * carries the Minn Admin release of its build at minn/admin and lays it into
 * the plugins folder the first time a new engine version serves a request:
 * into an empty place, where it is also activated, or over an older copy,
 * whose active state is kept; never over a newer copy, a link, or a folder
 * under git, and never from a development checkout.
 *
 * The copy served is the plugin when it is at least the engine's own and
 * asks for no newer engine (a "Requires Minn" header); otherwise the
 * engine's copy, which also stands in while the plugin folder is missing.
 */
final class AdminPlugin
{
    public const FOLDER = 'minn-admin';
    public const MAIN = 'minn-admin/minn-admin.php';
    /** The engine version that last settled the plugin: one autoloaded option, so a request with nothing to do reads nothing more. */
    public const OPTION = 'minn_admin_laid';
    /** Why deleting Minn Admin is refused, wherever it is asked for. */
    public const KEPT = 'Minn Admin is this site\'s admin, so it cannot be deleted while Minn runs the site. Deactivate it to switch the admin off.';
    /** A lay that died midway leaves its lock behind; one older than this is taken over. */
    private const LOCK_TIMEOUT = 600;

    /** The app a request serves, with the address its files load from. */
    public static function app(string $engineDir, string $contentDir): App
    {
        $plugin = "{$contentDir}/plugins/" . self::FOLDER;
        if (!EngineUpdate::isCheckout($engineDir) && self::servable($plugin, "{$engineDir}/admin", self::engineVersion($engineDir))) {
            return new App($plugin, '/wp-content/plugins/' . self::FOLDER);
        }
        return new App("{$engineDir}/admin");
    }

    /**
     * Once per engine version: lays the engine's copy into the plugins
     * folder when that is due, activating it when it was not there. A copy
     * that cannot be laid (an unwritable folder) is not retried until the
     * next version; the engine's copy is served meanwhile. Called once the
     * runtime has booted, so the check is a read of the autoloaded list.
     */
    public static function settle(string $engineDir, string $contentDir, Options $options): void
    {
        $version = self::engineVersion($engineDir);
        if ($options->raw(self::OPTION) === $version) {
            return;
        }
        $options->update(self::OPTION, $version, 'on');
        if (EngineUpdate::isCheckout($engineDir)) {
            return;
        }
        try {
            $laid = self::lay("{$engineDir}/admin", "{$contentDir}/plugins/" . self::FOLDER);
        } catch (Throwable) {
            return;
        }
        $activated = $laid === 'laid' ? self::activated($options->raw('active_plugins')) : null;
        if ($activated !== null) {
            $options->update('active_plugins', $activated);
        }
    }

    /**
     * The stored active_plugins list with Minn Admin added, sorted as
     * activation sorts it; null when it is already there.
     *
     * @return list<string>|null
     */
    public static function activated(?string $stored): ?array
    {
        $active = Serialized::stringList($stored);
        if (in_array(self::MAIN, $active, true)) {
            return null;
        }
        $active[] = self::MAIN;
        sort($active);
        return $active;
    }

    /**
     * Puts the engine's copy at $target: "laid" into an empty place,
     * "upgraded" over an older copy, "kept" when the copy there is as new or
     * newer, "skipped" when nothing may be written (no whole plugin to lay,
     * a link or a git checkout in the way, another lay in progress). The new
     * copy is built beside the target and swapped in by renames, so a
     * request never finds half a plugin.
     */
    public static function lay(string $bundle, string $target): string
    {
        if (!is_file("{$bundle}/minn-admin.php") || !is_dir("{$bundle}/includes")) {
            return 'skipped';
        }
        if (is_link($target) || EngineUpdate::isCheckout($target) || (file_exists($target) && !is_dir($target))) {
            return 'skipped';
        }
        $present = is_file("{$target}/minn-admin.php");
        if ($present && version_compare((new App($target))->version(), (new App($bundle))->version(), '>=')) {
            return 'kept';
        }
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException("Cannot create {$parent}.");
        }
        $lock = "{$target}.laying";
        if (!@mkdir($lock) && !(is_dir($lock) && time() - (int) @filemtime($lock) > self::LOCK_TIMEOUT && touch($lock))) {
            return 'skipped';
        }
        try {
            $stage = "{$target}.new-" . bin2hex(random_bytes(4));
            if (!FileTree::copy($bundle, $stage)) {
                Files::deleteTree($stage);
                throw new RuntimeException("Cannot copy Minn Admin to {$stage}.");
            }
            $old = is_dir($target) ? "{$target}.old-" . bin2hex(random_bytes(4)) : null;
            if ($old !== null && !rename($target, $old)) {
                Files::deleteTree($stage);
                throw new RuntimeException("Cannot move {$target} aside.");
            }
            if (!rename($stage, $target)) {
                $old !== null && rename($old, $target);
                Files::deleteTree($stage);
                throw new RuntimeException("Cannot move Minn Admin into {$target}.");
            }
            $old !== null && Files::deleteTree($old);
            return $present ? 'upgraded' : 'laid';
        } finally {
            @rmdir($lock);
        }
    }

    /** Whether the plugin copy is whole, at least the engine's own, and asks for no newer engine. */
    public static function servable(string $plugin, string $bundle, string $engineVersion): bool
    {
        if (!is_file("{$plugin}/minn-admin.php") || !is_dir("{$plugin}/assets")) {
            return false;
        }
        if (version_compare((new App($plugin))->version(), (new App($bundle))->version(), '<')) {
            return false;
        }
        $requires = FileHeaders::values("{$plugin}/minn-admin.php", ['Requires Minn'])['Requires Minn'];
        return $requires === '' || version_compare($requires, $engineVersion, '<=');
    }

    private static function engineVersion(string $engineDir): string
    {
        return defined('MINN_ENGINE_VERSION') ? (string) MINN_ENGINE_VERSION : EngineUpdate::versionOf($engineDir);
    }
}
