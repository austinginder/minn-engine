<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Objects\Errors;
use Minn\Objects\Upgrader;
use Minn\Ops\Unzip;
use Minn\Support\Files;
use Minn\Support\Url;

/**
 * What WP_Upgrader does with a package (probe upgrader), for the facade's
 * upgraders and so for everything that installs or updates a plugin or a
 * theme. run() is the whole pass: the options through
 * upgrader_package_options, the package downloaded and unpacked into
 * wp-content/upgrade/, put in place by install(), the result through
 * upgrader_install_package_result and told to the skin, and, for a single
 * item, upgrader_process_complete. The steps a subclass may replace
 * (fs_connect, download_package, unpack_package, install_package,
 * clear_destination) are called on the upgrader itself, as the reference
 * calls them, so a plugin's own upgrader class is honoured.
 *
 * An update names the folder it replaces (hook_extra temp_backup); that
 * copy is moved to wp-content/upgrade-temp-backup/<dir>/<slug> before the
 * destination is cleared, moved back when the new one fails, and removed
 * once the pass is over. The reference keeps it for a weekly event to
 * clear and puts it back at shutdown; Minn keeps nothing and restores at
 * once.
 */
final class Upgrade
{
    /** The run options in the reference's order, which upgrader_package_options sees. */
    private const RUN = ['package' => '', 'destination' => '', 'clear_destination' => false, 'clear_working' => true, 'abort_if_destination_exists' => true, 'is_multi' => false, 'hook_extra' => []];

    /** WP_Upgrader::run; false when the filesystem cannot be reached. */
    public static function run(Upgrader $upgrader, array $options): mixed
    {
        $hooks = Runtime::hooks();
        $options = (array) $hooks->filter('upgrader_package_options', [array_merge(self::RUN, $options)]);
        $skin = $upgrader->skin;
        $single = empty($options['is_multi']);
        $extra = (array) ($options['hook_extra'] ?? []);
        if ($single) {
            $skin->header();
        }
        $connected = $upgrader->fs_connect([WP_CONTENT_DIR, $options['destination']]);
        if (!$connected) {
            if ($single) {
                $skin->footer();
            }
            return false;
        }
        $skin->before();
        $working = $connected instanceof Errors ? $connected : $upgrader->download_package($options['package'], false, $extra);
        if (!$working instanceof Errors) {
            $working = $upgrader->unpack_package($working, $working !== $options['package']);
        }
        if ($working instanceof Errors) {
            $skin->error($working);
            $skin->after();
            if ($single) {
                $skin->footer();
            }
            return $working;
        }
        $result = $upgrader->install_package(['source' => $working] + array_intersect_key($options, array_flip(['destination', 'clear_destination', 'abort_if_destination_exists', 'clear_working', 'hook_extra'])));
        $result = $hooks->filter('upgrader_install_package_result', [$result, $extra]);
        $skin->set_result($result);
        self::report($upgrader, $result, $extra);
        if ($single) {
            $hooks->action('upgrader_process_complete', [$upgrader, $extra]);
            $skin->footer();
        }
        return $result;
    }

    /**
     * The bulk pass of Plugin_Upgrader and Theme_Upgrader: the skin opened,
     * maintenance mode on when an active item is replaced, each item with an
     * offer run as one of many (one without is up to date, answered true),
     * the pass stopped when the filesystem refuses, then maintenance off, the
     * update cache cleaned, upgrader_process_complete told the whole list,
     * and the skin closed. False when the filesystem cannot be reached.
     *
     * @param list<string> $items plugin files or theme folders
     * @param array<string, mixed> $offers item => the offer (its package)
     * @param array{directories: list<string>, maintenance: bool, prepare: \Closure(string): array{0: string, 1: array}, clean: \Closure(): void, complete: array} $how
     * @return array<string, mixed>|false
     */
    public static function bulk(Upgrader $upgrader, array $items, array $offers, array $how): array|false
    {
        $skin = $upgrader->skin;
        $skin->header();
        if (!$upgrader->fs_connect($how['directories'])) {
            $skin->footer();
            return false;
        }
        $skin->bulk_header();
        if ($how['maintenance']) {
            $upgrader->maintenance_mode(true);
        }
        $results = [];
        $upgrader->update_count = count($items);
        $upgrader->update_current = 0;
        foreach ($items as $item) {
            ++$upgrader->update_current;
            [$destination, $extra] = ($how['prepare'])($item);
            if (!isset($offers[$item])) {
                $skin->set_result('up_to_date');
                $skin->before();
                $skin->feedback('up_to_date');
                $skin->after();
                $results[$item] = true;
                continue;
            }
            $results[$item] = $upgrader->run(['package' => ((array) $offers[$item])['package'] ?? '', 'destination' => $destination, 'clear_destination' => true, 'clear_working' => true, 'is_multi' => true, 'hook_extra' => $extra]);
            if ($results[$item] === false) {
                break;
            }
        }
        $upgrader->maintenance_mode(false);
        ($how['clean'])();
        Runtime::hooks()->action('upgrader_process_complete', [$upgrader, $how['complete']]);
        $skin->bulk_footer();
        $skin->footer();
        return $results;
    }

    /**
     * WP_Upgrader::install_package: the unpacked folder chosen (its one
     * folder, or the working folder when it holds several things), offered
     * to upgrader_pre_install and upgrader_source_selection, the destination
     * cleared or refused when something is there, the folder moved in (and
     * walked by the symbol gate at its next check), and the result offered
     * to upgrader_post_install.
     */
    public static function install(Upgrader $upgrader, array $args): array|Errors
    {
        $installed = self::installUnpacked($upgrader, $args);
        // A package unpacked in the upgrade folder and not installed is removed, so its files are never left at an
        // address under wp-content (security sweep 2026-11-12, finding 2.6). A source anywhere else is the caller's.
        $working = realpath(rtrim((string) ($args['source'] ?? ''), '/'));
        $upgrade = realpath(WP_CONTENT_DIR . '/upgrade');
        if ($installed instanceof Errors && $working !== false && $upgrade !== false && str_starts_with($working, $upgrade . '/')) {
            self::remove($working);
        }
        return $installed;
    }

    /** install()'s steps, any of which may refuse. */
    private static function installUnpacked(Upgrader $upgrader, array $args): array|Errors
    {
        $args = array_merge(['source' => '', 'destination' => '', 'clear_destination' => false, 'clear_working' => false, 'abort_if_destination_exists' => true, 'hook_extra' => []], $args);
        $working = rtrim((string) $args['source'], '/');
        $destination = (string) $args['destination'];
        $extra = (array) $args['hook_extra'];
        if ($working === '' || $destination === '') {
            return Handoff::newError('bad_request', $upgrader->strings['bad_request']);
        }
        $hooks = Runtime::hooks();
        $upgrader->skin->feedback('installing_package');
        $ready = $hooks->filter('upgrader_pre_install', [true, $extra]);
        if ($ready instanceof Errors) {
            return $ready;
        }
        $found = self::listing($working);
        if ($found === []) {
            return Handoff::newError('incompatible_archive_empty', $upgrader->strings['incompatible_archive'], $upgrader->strings['no_files']);
        }
        $source = Url::withTrailingSlash(count($found) === 1 && is_dir("{$working}/{$found[0]}") ? "{$working}/{$found[0]}" : $working);
        $source = $hooks->filter('upgrader_source_selection', [$source, $working, $upgrader, $extra]);
        if ($source instanceof Errors) {
            return $source;
        }
        $source = (string) $source;
        $sourceFiles = self::listing(rtrim($source, '/'));
        $remote = self::destinationFor($destination, $source);
        $placed = self::clearOrRefuse($upgrader, $remote, $args);
        if ($placed === true) {
            $placed = self::move(rtrim($source, '/'), rtrim($remote, '/'), $upgrader);
        }
        if ($placed instanceof Errors) {
            return $placed;
        }
        Symbols::recheck($remote, Runtime::options());
        if ($args['clear_working'] && is_dir($working)) {
            Files::deleteTree($working);
        }
        $result = [
            'source' => $source,
            'source_files' => $sourceFiles,
            'destination' => $remote,
            'destination_name' => basename($remote),
            'local_destination' => $destination,
            'remote_destination' => $remote,
            'clear_destination' => $args['clear_destination'],
        ];
        $after = $hooks->filter('upgrader_post_install', [true, $extra, $result]);
        $upgrader->result = $after instanceof Errors ? $after : $result;
        return $upgrader->result;
    }

    /**
     * Removes a path in the upgrade folder through the site's filesystem, as
     * that is what unzipped it (FTP or SSH), and straight from disk when the
     * filesystem left it there.
     */
    private static function remove(string $path): void
    {
        $filesystem = $GLOBALS['wp_filesystem'] ?? null;
        if (is_object($filesystem) && method_exists($filesystem, 'delete')) {
            $filesystem->delete($path, true);
        }
        if (file_exists($path) || is_link($path)) {
            Files::deleteTree($path);
        }
    }

    /** An archive unpacked into a folder through the site's filesystem (unzip_file): true, or the error why not. */
    public static function unzip(mixed $file, mixed $to): mixed
    {
        $filesystem = $GLOBALS['wp_filesystem'] ?? null;
        if (!$filesystem || !is_object($filesystem)) {
            return Handoff::newError('fs_unavailable', Gettext::text('Could not access filesystem.'));
        }
        $result = (new Unzip($filesystem, static fn (string $hook, mixed ...$args): mixed => Runtime::hooks()->filter($hook, $args), defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644))->into((string) $file, (string) $to);
        return $result instanceof Refusal ? Handoff::newError($result->code, Gettext::text($result->message), $result->data) : $result;
    }

    /**
     * WP_Upgrader::unpack_package, without the package's deletion (the
     * caller's): the upgrade folder emptied of what earlier passes left,
     * the package unzipped into a folder named for it with a random tail, and an archive that
     * will not open refused in the upgrader's words with the reader's reason.
     */
    public static function unpack(Upgrader $upgrader, string $package): string|Errors
    {
        $upgrader->skin->feedback('unpack_package');
        $folder = WP_CONTENT_DIR . '/upgrade';
        foreach (array_diff(self::listing($folder), ['index.php']) as $left) {
            self::remove("{$folder}/{$left}");
        }
        // The folder never lists itself, and an unzipped package is not where anyone could guess while it is
        // there: the package's name and sixteen random letters and digits (probe patch-714).
        // Made before the unzip, which would otherwise create the folder itself and leave it unguarded (finding 2.6),
        // through the site's filesystem, so on an FTP or SSH method it belongs to the user the unzip writes as.
        $filesystem = $GLOBALS['wp_filesystem'] ?? null;
        if (!is_dir($folder) && is_object($filesystem) && method_exists($filesystem, 'mkdir')) {
            $filesystem->mkdir($folder, defined('FS_CHMOD_DIR') ? FS_CHMOD_DIR : 0755);
        }
        if (is_dir($folder) && !is_file("{$folder}/index.php")) {
            $silence = "<?php\n// Silence is golden.\n";
            if (is_object($filesystem) && method_exists($filesystem, 'put_contents')) {
                $filesystem->put_contents("{$folder}/index.php", $silence, defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644);
            }
            // Written straight to disk when the site's filesystem could not (a dropped FTP connection).
            if (!is_file("{$folder}/index.php")) {
                @file_put_contents("{$folder}/index.php", $silence);
            }
        }
        $working = $folder . '/' . basename(basename($package, '.tmp'), '.zip') . '-' . self::randomName(16);
        $unzipped = self::unzip($package, $working);
        if ($unzipped instanceof Errors) {
            self::remove($working);
            return $unzipped->get_error_code() === 'incompatible_archive'
                ? Handoff::newError('incompatible_archive', $upgrader->strings['incompatible_archive'], $unzipped->get_error_data())
                : $unzipped;
        }
        return $working;
    }

    /** WP_Upgrader::clear_destination: whatever is at the destination removed; true when nothing is left there. */
    public static function clear(Upgrader $upgrader, string $remote): bool|Errors
    {
        if (!file_exists($remote) && !is_link($remote)) {
            return true;
        }
        return Files::deleteTree(rtrim($remote, '/')) ? true : Handoff::newError('remove_old_failed', $upgrader->strings['remove_old_failed']);
    }

    /** Maintenance mode on, as a bulk update of active plugins turns it on: the .maintenance file the front door reads. */
    public static function maintenanceOn(Upgrader $upgrader): void
    {
        $upgrader->skin->feedback('maintenance_start');
        file_put_contents(ABSPATH . '.maintenance', '<?php $upgrading = ' . time() . '; ?>');
    }

    /**
     * An update's own line taken out of the stored list of updates still to
     * be mailed about (auto_plugin_theme_update_emails), once it is done
     * (probe db-mail-rewrite-doubts).
     */
    public static function forgetUpdateEmail(string $key): void
    {
        $emails = Runtime::options()->filtered('auto_plugin_theme_update_emails', []);
        if (is_array($emails) && array_key_exists($key, $emails)) {
            unset($emails[$key]);
            OptionWrites::update('auto_plugin_theme_update_emails', $emails);
        }
    }

    /**
     * Whether a failed core update holds the next one back: a critical
     * failure does, and so does the same offer failing from the same version
     * when it is not to be retried.
     */
    public static function lastCoreUpdateFailed(string $offered, string $current, mixed $failure): bool
    {
        if (!is_array($failure) || $failure === []) {
            return false;
        }
        if (!empty($failure['critical'])) {
            return true;
        }
        return ($failure['current'] ?? null) === $current && ($failure['attempted'] ?? null) === $offered && empty($failure['retry']);
    }

    /** Maintenance mode off; said only when it was on. */
    public static function maintenanceOff(Upgrader $upgrader): void
    {
        if (is_file(ABSPATH . '.maintenance')) {
            $upgrader->skin->feedback('maintenance_end');
            unlink(ABSPATH . '.maintenance');
        }
    }

    /** The folder the item lands in: a folder named for the package inside a known parent, otherwise the destination itself. */
    private static function destinationFor(string $destination, string $source): string
    {
        $parents = array_map(static fn (string $dir): string => rtrim($dir, '/'), [ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes', (string) Themes::root()]);
        return in_array(rtrim($destination, '/'), $parents, true)
            ? Url::withTrailingSlash($destination) . basename($source) . '/'
            : Url::withTrailingSlash($destination);
    }

    /** Clears the destination (an update's copy set aside first), or refuses when something is there and must not be replaced. */
    private static function clearOrRefuse(Upgrader $upgrader, string $remote, array $args): bool|Errors
    {
        $extra = (array) $args['hook_extra'];
        if (!$args['clear_destination']) {
            return $args['abort_if_destination_exists'] && file_exists($remote)
                ? Handoff::newError('folder_exists', $upgrader->strings['folder_exists'], $remote)
                : true;
        }
        $aside = self::setAside($upgrader, (array) ($extra['temp_backup'] ?? []));
        if ($aside instanceof Errors) {
            return $aside;
        }
        $upgrader->skin->feedback('remove_old');
        $removed = Runtime::hooks()->filter('upgrader_clear_destination', [$upgrader->clear_destination($remote), (string) $args['destination'], $remote, $extra]);
        if ($removed instanceof Errors) {
            return $removed;
        }
        return $removed ? true : Handoff::newError('remove_old_failed', $upgrader->strings['remove_old_failed']);
    }

    /** The source moved (or, across filesystems, copied) to the destination; an existing destination is merged into. */
    private static function move(string $source, string $remote, Upgrader $upgrader): bool|Errors
    {
        if (!file_exists($remote) && @rename($source, $remote)) {
            return true;
        }
        if (!is_dir($remote) && !@mkdir($remote, 0755, true)) {
            return Handoff::newError('mkdir_failed_destination', $upgrader->strings['mkdir_failed'], $remote);
        }
        return self::copyInto($source, $remote) ? true : Handoff::newError('copy_failed_copy_dir', $upgrader->strings['files_not_writable']);
    }

    private static function copyInto(string $from, string $to): bool
    {
        foreach (self::listing($from) as $name) {
            $ok = is_dir("{$from}/{$name}")
                ? (is_dir("{$to}/{$name}") || @mkdir("{$to}/{$name}", 0755)) && self::copyInto("{$from}/{$name}", "{$to}/{$name}")
                : @copy("{$from}/{$name}", "{$to}/{$name}");
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /** The run's outcome told to the skin; an update's set-aside copy put back on failure, removed on success. */
    private static function report(Upgrader $upgrader, mixed $result, array $extra): void
    {
        $skin = $upgrader->skin;
        $backup = (array) ($extra['temp_backup'] ?? []);
        if ($result instanceof Errors) {
            self::putBack($backup);
            $skin->error($result);
            if (!$skin->hide_process_failed($result)) {
                $skin->feedback('process_failed');
            }
        } else {
            $skin->feedback('process_success');
        }
        $skin->after();
        self::forget($backup);
    }

    /** Moves an update's current copy to the backup folder (one left there by an earlier pass is replaced). */
    private static function setAside(Upgrader $upgrader, array $backup): true|Errors
    {
        $from = self::backedUp($backup, 'from');
        if ($from === null || !file_exists($from)) {
            return true;
        }
        $to = (string) self::backedUp($backup, 'to');
        if ((!is_dir(dirname($to)) && !@mkdir(dirname($to), 0755, true)) || (file_exists($to) && !Files::deleteTree($to))) {
            return Handoff::newError('fs_temp_backup_mkdir', $upgrader->strings['temp_backup_mkdir_failed']);
        }
        return @rename($from, $to) ? true : Handoff::newError('fs_temp_backup_move', $upgrader->strings['temp_backup_move_failed']);
    }

    private static function putBack(array $backup): void
    {
        $from = self::backedUp($backup, 'from');
        $to = self::backedUp($backup, 'to');
        if ($from !== null && $to !== null && is_dir($to)) {
            if (file_exists($from)) {
                Files::deleteTree($from);
            }
            @rename($to, $from);
        }
    }

    private static function forget(array $backup): void
    {
        $to = self::backedUp($backup, 'to');
        if ($to !== null && file_exists($to)) {
            Files::deleteTree($to);
        }
    }

    /** Where an update's copy lives ('from') and where it is set aside ('to'), or null when the update names none. */
    private static function backedUp(array $backup, string $end): ?string
    {
        $slug = (string) ($backup['slug'] ?? '');
        if ($slug === '' || $slug === '.' || str_contains($slug, '/') || !isset($backup['src'], $backup['dir'])) {
            return null;
        }
        return $end === 'from'
            ? rtrim((string) $backup['src'], '/') . '/' . $slug
            : WP_CONTENT_DIR . '/upgrade-temp-backup/' . basename((string) $backup['dir']) . '/' . $slug;
    }

    /** Letters and digits drawn at random, $length of them. */
    private static function randomName(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $name = '';
        for ($i = 0; $i < $length; $i++) {
            $name .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $name;
    }

    /**
     * A folder's entries in the order the filesystem gives them, as the
     * reference's dirlist reads them (no sorting).
     *
     * @return list<string>
     */
    private static function listing(string $folder): array
    {
        $handle = is_dir($folder) ? @opendir($folder) : false;
        if ($handle === false) {
            return [];
        }
        $names = [];
        while (($name = readdir($handle)) !== false) {
            if ($name !== '.' && $name !== '..') {
                $names[] = $name;
            }
        }
        closedir($handle);
        return $names;
    }
}
