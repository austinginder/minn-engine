<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
    public static function run(\WP_Upgrader $upgrader, array $options): mixed
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
        $working = $connected instanceof \WP_Error ? $connected : $upgrader->download_package($options['package'], false, $extra);
        if (!$working instanceof \WP_Error) {
            $working = $upgrader->unpack_package($working, $working !== $options['package']);
        }
        if ($working instanceof \WP_Error) {
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
    public static function bulk(\WP_Upgrader $upgrader, array $items, array $offers, array $how): array|false
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
     * cleared or refused when something is there, the folder moved in, and
     * the result offered to upgrader_post_install.
     */
    public static function install(\WP_Upgrader $upgrader, array $args): array|\WP_Error
    {
        $args = array_merge(['source' => '', 'destination' => '', 'clear_destination' => false, 'clear_working' => false, 'abort_if_destination_exists' => true, 'hook_extra' => []], $args);
        $working = rtrim((string) $args['source'], '/');
        $destination = (string) $args['destination'];
        $extra = (array) $args['hook_extra'];
        if ($working === '' || $destination === '') {
            return new \WP_Error('bad_request', $upgrader->strings['bad_request']);
        }
        $hooks = Runtime::hooks();
        $upgrader->skin->feedback('installing_package');
        $ready = $hooks->filter('upgrader_pre_install', [true, $extra]);
        if ($ready instanceof \WP_Error) {
            return $ready;
        }
        $found = self::listing($working);
        if ($found === []) {
            return new \WP_Error('incompatible_archive_empty', $upgrader->strings['incompatible_archive'], $upgrader->strings['no_files']);
        }
        $source = Url::withTrailingSlash(count($found) === 1 && is_dir("{$working}/{$found[0]}") ? "{$working}/{$found[0]}" : $working);
        $source = $hooks->filter('upgrader_source_selection', [$source, $working, $upgrader, $extra]);
        if ($source instanceof \WP_Error) {
            return $source;
        }
        $source = (string) $source;
        $sourceFiles = self::listing(rtrim($source, '/'));
        $remote = self::destinationFor($destination, $source);
        $placed = self::clearOrRefuse($upgrader, $remote, $args);
        if ($placed === true) {
            $placed = self::move(rtrim($source, '/'), rtrim($remote, '/'), $upgrader);
        }
        if ($placed instanceof \WP_Error) {
            return $placed;
        }
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
        $upgrader->result = $after instanceof \WP_Error ? $after : $result;
        return $upgrader->result;
    }

    /**
     * WP_Upgrader::unpack_package, without the package's deletion (the
     * caller's): the upgrade folder emptied of what earlier passes left,
     * the package unzipped into a folder named for it, and an archive that
     * will not open refused in the upgrader's words with the reader's reason.
     */
    public static function unpack(\WP_Upgrader $upgrader, string $package): string|\WP_Error
    {
        $upgrader->skin->feedback('unpack_package');
        $folder = WP_CONTENT_DIR . '/upgrade';
        foreach (self::listing($folder) as $left) {
            Files::deleteTree("{$folder}/{$left}");
        }
        $working = $folder . '/' . basename(basename($package, '.tmp'), '.zip');
        $unzipped = \unzip_file($package, $working);
        if ($unzipped instanceof \WP_Error) {
            Files::deleteTree($working);
            return $unzipped->get_error_code() === 'incompatible_archive'
                ? new \WP_Error('incompatible_archive', $upgrader->strings['incompatible_archive'], $unzipped->get_error_data())
                : $unzipped;
        }
        return $working;
    }

    /** WP_Upgrader::clear_destination: whatever is at the destination removed; true when nothing is left there. */
    public static function clear(\WP_Upgrader $upgrader, string $remote): bool|\WP_Error
    {
        if (!file_exists($remote) && !is_link($remote)) {
            return true;
        }
        return Files::deleteTree(rtrim($remote, '/')) ? true : new \WP_Error('remove_old_failed', $upgrader->strings['remove_old_failed']);
    }

    /** Maintenance mode on, as a bulk update of active plugins turns it on: the .maintenance file the front door reads. */
    public static function maintenanceOn(\WP_Upgrader $upgrader): void
    {
        $upgrader->skin->feedback('maintenance_start');
        file_put_contents(ABSPATH . '.maintenance', '<?php $upgrading = ' . time() . '; ?>');
    }

    /** Maintenance mode off; said only when it was on. */
    public static function maintenanceOff(\WP_Upgrader $upgrader): void
    {
        if (is_file(ABSPATH . '.maintenance')) {
            $upgrader->skin->feedback('maintenance_end');
            unlink(ABSPATH . '.maintenance');
        }
    }

    /** The folder the item lands in: a folder named for the package inside a known parent, otherwise the destination itself. */
    private static function destinationFor(string $destination, string $source): string
    {
        $parents = array_map(static fn (string $dir): string => rtrim($dir, '/'), [ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WP_CONTENT_DIR . '/themes', (string) \get_theme_root()]);
        return in_array(rtrim($destination, '/'), $parents, true)
            ? Url::withTrailingSlash($destination) . basename($source) . '/'
            : Url::withTrailingSlash($destination);
    }

    /** Clears the destination (an update's copy set aside first), or refuses when something is there and must not be replaced. */
    private static function clearOrRefuse(\WP_Upgrader $upgrader, string $remote, array $args): bool|\WP_Error
    {
        $extra = (array) $args['hook_extra'];
        if (!$args['clear_destination']) {
            return $args['abort_if_destination_exists'] && file_exists($remote)
                ? new \WP_Error('folder_exists', $upgrader->strings['folder_exists'], $remote)
                : true;
        }
        $aside = self::setAside($upgrader, (array) ($extra['temp_backup'] ?? []));
        if ($aside instanceof \WP_Error) {
            return $aside;
        }
        $upgrader->skin->feedback('remove_old');
        $removed = Runtime::hooks()->filter('upgrader_clear_destination', [$upgrader->clear_destination($remote), (string) $args['destination'], $remote, $extra]);
        if ($removed instanceof \WP_Error) {
            return $removed;
        }
        return $removed ? true : new \WP_Error('remove_old_failed', $upgrader->strings['remove_old_failed']);
    }

    /** The source moved (or, across filesystems, copied) to the destination; an existing destination is merged into. */
    private static function move(string $source, string $remote, \WP_Upgrader $upgrader): bool|\WP_Error
    {
        if (!file_exists($remote) && @rename($source, $remote)) {
            return true;
        }
        if (!is_dir($remote) && !@mkdir($remote, 0755, true)) {
            return new \WP_Error('mkdir_failed_destination', $upgrader->strings['mkdir_failed'], $remote);
        }
        return self::copyInto($source, $remote) ? true : new \WP_Error('copy_failed_copy_dir', $upgrader->strings['files_not_writable']);
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
    private static function report(\WP_Upgrader $upgrader, mixed $result, array $extra): void
    {
        $skin = $upgrader->skin;
        $backup = (array) ($extra['temp_backup'] ?? []);
        if ($result instanceof \WP_Error) {
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
    private static function setAside(\WP_Upgrader $upgrader, array $backup): true|\WP_Error
    {
        $from = self::backedUp($backup, 'from');
        if ($from === null || !file_exists($from)) {
            return true;
        }
        $to = (string) self::backedUp($backup, 'to');
        if ((!is_dir(dirname($to)) && !@mkdir(dirname($to), 0755, true)) || (file_exists($to) && !Files::deleteTree($to))) {
            return new \WP_Error('fs_temp_backup_mkdir', $upgrader->strings['temp_backup_mkdir_failed']);
        }
        return @rename($from, $to) ? true : new \WP_Error('fs_temp_backup_move', $upgrader->strings['temp_backup_move_failed']);
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
