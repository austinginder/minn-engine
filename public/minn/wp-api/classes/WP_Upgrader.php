<?php

use Minn\Runtime\PluginActivation;
use Minn\Runtime\Upgrade;

/**
 * The upgraders (probe upgrader): WP_Upgrader runs a package through
 * Minn\Runtime\Upgrade, which calls back into the overridable steps here;
 * Plugin_Upgrader and Theme_Upgrader install, update and bulk-update with
 * the reference's options, words, checks and hooks. One file, so each
 * class finds its parent already declared.
 */
#[AllowDynamicProperties]
class WP_Upgrader
{
    public $strings = [];
    public $skin = null;
    public $result = [];
    public $update_count = 0;
    public $update_current = 0;
    private $temp_backups = [];
    private $temp_restores = [];

    public function __construct($skin = null)
    {
        $this->skin = $skin ?? new WP_Upgrader_Skin();
    }

    public function init()
    {
        $this->skin->set_upgrader($this);
        $this->generic_strings();
    }

    /** Minn keeps no backups past the pass, so there is nothing to schedule. */
    protected function schedule_temp_backup_cleanup()
    {
    }

    public function generic_strings()
    {
        $this->strings = [
            'bad_request' => __('Invalid data provided.'),
            'fs_unavailable' => __('Could not access filesystem.'),
            'fs_error' => __('Filesystem error.'),
            'fs_no_root_dir' => __('Unable to locate WordPress root directory.'),
            'fs_no_content_dir' => __('Unable to locate WordPress content directory (wp-content).'),
            'fs_no_plugins_dir' => __('Unable to locate WordPress plugin directory.'),
            'fs_no_themes_dir' => __('Unable to locate WordPress theme directory.'),
            'fs_no_folder' => __('Unable to locate needed folder (%s).'),
            'no_package' => __('Package not available.'),
            'download_failed' => __('Download failed.'),
            'installing_package' => __('Installing the latest version&#8230;'),
            'no_files' => __('The package contains no files.'),
            'folder_exists' => __('Destination folder already exists.'),
            'mkdir_failed' => __('Could not create directory.'),
            'incompatible_archive' => __('The package could not be installed.'),
            'files_not_writable' => __('The update cannot be installed because some files could not be copied. This is usually due to inconsistent file permissions.'),
            'dir_not_readable' => __('A directory could not be read.'),
            'maintenance_start' => __('Enabling Maintenance mode&#8230;'),
            'maintenance_end' => __('Disabling Maintenance mode&#8230;'),
            'temp_backup_mkdir_failed' => __('Could not create the upgrade-temp-backup directory.'),
            'temp_backup_move_failed' => __('Could not move the old version to the upgrade-temp-backup directory.'),
            'temp_backup_restore_failed' => __('Could not restore the original version of %s.'),
            'temp_backup_delete_failed' => __('Could not delete the temporary backup directory for %s.'),
        ];
    }

    /** Minn's filesystem is the direct one; the skin may still answer for the credentials. */
    public function fs_connect($directories = [], $allow_relaxed_file_ownership = false)
    {
        $directories = (array) $directories;
        $credentials = $this->skin->request_filesystem_credentials(false, $directories[0] ?? '', $allow_relaxed_file_ownership);
        if ($credentials === false) {
            return false;
        }
        if (!WP_Filesystem($credentials, $directories[0] ?? '', $allow_relaxed_file_ownership)) {
            return new WP_Error('fs_unavailable', $this->strings['fs_unavailable']);
        }
        return true;
    }

    /** upgrader_pre_download answers first; a local file is used where it lies; anything else is downloaded. */
    public function download_package($package, $check_signatures = false, $hook_extra = [])
    {
        $reply = apply_filters('upgrader_pre_download', false, $package, $this, $hook_extra);
        if ($reply !== false) {
            return $reply;
        }
        $package = (string) $package;
        if (!preg_match('#^(https?|ftp)://#i', $package) && file_exists($package)) {
            return $package;
        }
        if ($package === '') {
            return new WP_Error('no_package', $this->strings['no_package']);
        }
        $this->skin->feedback('downloading_package', $package);
        $file = download_url($package, 300, $check_signatures);
        return is_wp_error($file) && !$file->get_error_data('softfail-filename')
            ? new WP_Error('download_failed', $this->strings['download_failed'], $file->get_error_message())
            : $file;
    }

    public function unpack_package($package, $delete_package = true)
    {
        $working = Upgrade::unpack($this, (string) $package);
        if ($delete_package && is_file((string) $package)) {
            @unlink((string) $package);
        }
        return $working;
    }

    /** @return array<string, array> the nested dirlist flattened to "path/name" keys */
    protected function flatten_dirlist($nested_files, $path = '')
    {
        $files = [];
        foreach ((array) $nested_files as $name => $details) {
            $files[$path . $name] = $details;
            if (($details['type'] ?? '') === 'd' && !empty($details['files'])) {
                $files += $this->flatten_dirlist($details['files'], $path . $name . '/');
            }
        }
        return $files;
    }

    public function clear_destination($remote_destination)
    {
        return Upgrade::clear($this, (string) $remote_destination);
    }

    public function install_package($args = [])
    {
        return Upgrade::install($this, (array) $args);
    }

    public function run($options)
    {
        return Upgrade::run($this, (array) $options);
    }

    public function maintenance_mode($enable = false)
    {
        $enable ? Upgrade::maintenanceOn($this) : Upgrade::maintenanceOff($this);
    }

    /** A lock as an option holding its time; taken when free or older than the timeout. */
    public static function create_lock($lock_name, $release_timeout = null)
    {
        $option = $lock_name . '.lock';
        if (add_option($option, time(), '', false)) {
            return true;
        }
        $held = (int) get_option($option);
        if ($held > time() - ($release_timeout ?: HOUR_IN_SECONDS)) {
            return false;
        }
        update_option($option, time(), false);
        return true;
    }

    public static function release_lock($lock_name)
    {
        return delete_option($lock_name . '.lock');
    }

    /** Upgrade sets an update's copy aside itself, inside install_package; nothing is left to do here. */
    public function move_to_temp_backup_dir($args)
    {
        return true;
    }

    public function restore_temp_backup($temp_backups = [])
    {
        return true;
    }

    public function delete_temp_backup($temp_backups = [])
    {
        return true;
    }
}

class Plugin_Upgrader extends WP_Upgrader
{
    public $result;
    public $bulk = false;
    public $new_plugin_data = [];

    public function upgrade_strings()
    {
        $this->strings['up_to_date'] = __('The plugin is at the latest version.');
        $this->strings['no_package'] = __('Update package not available.');
        $this->strings['downloading_package'] = __('Downloading update from <span class="code pre">%s</span>&#8230;');
        $this->strings['unpack_package'] = __('Unpacking the update&#8230;');
        $this->strings['remove_old'] = __('Removing the old version of the plugin&#8230;');
        $this->strings['remove_old_failed'] = __('Could not remove the old plugin.');
        $this->strings['process_failed'] = __('Plugin update failed.');
        $this->strings['process_success'] = __('Plugin updated successfully.');
        $this->strings['process_bulk_success'] = __('Plugins updated successfully.');
    }

    public function install_strings()
    {
        $this->strings['no_package'] = __('Installation package not available.');
        $this->strings['downloading_package'] = __('Downloading installation package from <span class="code pre">%s</span>&#8230;');
        $this->strings['unpack_package'] = __('Unpacking the package&#8230;');
        $this->strings['installing_package'] = __('Installing the plugin&#8230;');
        $this->strings['remove_old'] = __('Removing the current plugin&#8230;');
        $this->strings['remove_old_failed'] = __('Could not remove the current plugin.');
        $this->strings['no_files'] = __('The plugin contains no files.');
        $this->strings['process_failed'] = __('Plugin installation failed.');
        $this->strings['process_success'] = __('Plugin installed successfully.');
        $this->strings['process_success_specific'] = __('Successfully installed the plugin <strong>%1$s %2$s</strong>.');
    }

    /** Installs a package; true, or what the run ended with (null when it failed before the plugin was placed). */
    public function install($package, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true, 'overwrite_package' => false]);
        $this->init();
        $this->install_strings();
        add_filter('upgrader_source_selection', [$this, 'check_package']);
        if ($args['clear_update_cache']) {
            add_action('upgrader_process_complete', 'wp_clean_plugins_cache', 9, 0);
        }
        $this->run(['package' => $package, 'destination' => WP_PLUGIN_DIR, 'clear_destination' => $args['overwrite_package'], 'clear_working' => true, 'hook_extra' => ['type' => 'plugin', 'action' => 'install']]);
        remove_action('upgrader_process_complete', 'wp_clean_plugins_cache', 9);
        remove_filter('upgrader_source_selection', [$this, 'check_package']);
        if (!$this->result || is_wp_error($this->result)) {
            return $this->result;
        }
        wp_clean_plugins_cache($args['clear_update_cache']);
        if ($args['overwrite_package']) {
            do_action('upgrader_overwrote_package', $package, $this->new_plugin_data, 'plugin');
        }
        return true;
    }

    /**
     * Updates one plugin to the offer in the update_plugins transient; false
     * when none is offered. The plugin is switched off first (silently) and
     * left so: switching it back on is the caller's, as on the reference.
     */
    public function upgrade($plugin, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true]);
        $this->init();
        $this->upgrade_strings();
        $offer = (get_site_transient('update_plugins')->response ?? [])[$plugin] ?? null;
        if ($offer === null) {
            $this->skin->before();
            $this->skin->set_result(false);
            $this->skin->error('up_to_date');
            $this->skin->after();
            return false;
        }
        $hooks = [['upgrader_pre_install', [$this, 'deactivate_plugin_before_upgrade'], 10, 2], ['upgrader_pre_install', [$this, 'active_before'], 10, 2], ['upgrader_post_install', [$this, 'active_after'], 10, 2], ['upgrader_clear_destination', [$this, 'delete_old_plugin'], 10, 4], ['upgrader_source_selection', [$this, 'check_package'], 10, 1]];
        foreach ($hooks as [$hook, $callback, $priority, $accepted]) {
            add_filter($hook, $callback, $priority, $accepted);
        }
        if ($args['clear_update_cache']) {
            add_action('upgrader_process_complete', 'wp_clean_plugins_cache', 9, 0);
        }
        $this->run(['package' => ((array) $offer)['package'] ?? '', 'destination' => WP_PLUGIN_DIR, 'clear_destination' => true, 'clear_working' => true, 'hook_extra' => ['plugin' => $plugin, 'type' => 'plugin', 'action' => 'update', 'temp_backup' => ['slug' => dirname((string) $plugin), 'src' => WP_PLUGIN_DIR, 'dir' => 'plugins']]]);
        remove_action('upgrader_process_complete', 'wp_clean_plugins_cache', 9);
        foreach ($hooks as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }
        if (!$this->result || is_wp_error($this->result)) {
            return $this->result;
        }
        wp_clean_plugins_cache($args['clear_update_cache']);
        return true;
    }

    /**
     * Updates several plugins, each to its offer; one answer per plugin (true
     * for one with nothing offered). Maintenance mode is on while an active
     * plugin is replaced, and active plugins stay active.
     */
    public function bulk_upgrade($plugins, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true]);
        $plugins = array_values((array) $plugins);
        $this->init();
        $this->bulk = true;
        $this->upgrade_strings();
        $offers = (array) (get_site_transient('update_plugins')->response ?? []);
        $active = array_filter($plugins, static fn ($plugin) => isset($offers[$plugin]) && is_plugin_active($plugin));
        add_filter('upgrader_clear_destination', [$this, 'delete_old_plugin'], 10, 4);
        add_filter('upgrader_source_selection', [$this, 'check_package']);
        $results = Upgrade::bulk($this, $plugins, $offers, [
            'directories' => [WP_CONTENT_DIR, WP_PLUGIN_DIR],
            'maintenance' => $active !== [],
            'prepare' => function (string $plugin): array {
                $this->skin->plugin_info = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, true);
                $this->skin->plugin_active = is_plugin_active($plugin);
                return [WP_PLUGIN_DIR, ['plugin' => $plugin, 'temp_backup' => ['slug' => dirname($plugin), 'src' => WP_PLUGIN_DIR, 'dir' => 'plugins']]];
            },
            'clean' => static fn () => wp_clean_plugins_cache($args['clear_update_cache']),
            'complete' => ['action' => 'update', 'type' => 'plugin', 'bulk' => true, 'plugins' => $plugins],
        ]);
        remove_filter('upgrader_source_selection', [$this, 'check_package']);
        remove_filter('upgrader_clear_destination', [$this, 'delete_old_plugin']);
        return $results;
    }

    /** The unpacked folder must hold a plugin this site can run; its headers are kept as new_plugin_data. */
    public function check_package($source)
    {
        $this->new_plugin_data = [];
        if (is_wp_error($source) || !is_dir((string) $source)) {
            return $source;
        }
        foreach (glob(trailingslashit((string) $source) . '*.php') ?: [] as $file) {
            $data = get_plugin_data($file, false, false);
            if (!empty($data['Name'])) {
                $this->new_plugin_data = $data;
                break;
            }
        }
        if ($this->new_plugin_data === []) {
            return new WP_Error('incompatible_archive_no_plugins', $this->strings['incompatible_archive'], __('No valid plugins were found.'));
        }
        return _minn_package_requirements($source, $this->new_plugin_data, 'plugin', $this->strings['incompatible_archive']);
    }

    /** The installed plugin's file ("folder/file.php"), the first by name in the folder it landed in; false when none. */
    public function plugin_info()
    {
        if (!is_array($this->result) || empty($this->result['destination_name'])) {
            return false;
        }
        $plugins = get_plugins('/' . $this->result['destination_name']);
        return $plugins === [] ? false : $this->result['destination_name'] . '/' . array_key_first($plugins);
    }

    /** An update switches the plugin off first, silently; a background update leaves it on (maintenance covers it). */
    public function deactivate_plugin_before_upgrade($response, $plugin)
    {
        if (is_wp_error($response) || wp_doing_cron()) {
            return $response;
        }
        $file = (string) ($plugin['plugin'] ?? '');
        if ($file !== '' && is_plugin_active($file)) {
            PluginActivation::deactivate([$file], null);
        }
        return $response;
    }

    /** A background update of an active plugin turns maintenance mode on while it is replaced. */
    public function active_before($response, $plugin)
    {
        if (!is_wp_error($response) && wp_doing_cron() && !$this->bulk && is_plugin_active((string) ($plugin['plugin'] ?? ''))) {
            $this->maintenance_mode(true);
        }
        return $response;
    }

    public function active_after($response, $plugin)
    {
        if (!is_wp_error($response) && wp_doing_cron() && !$this->bulk && is_plugin_active((string) ($plugin['plugin'] ?? ''))) {
            $this->maintenance_mode(false);
        }
        return $response;
    }

    /** The old plugin's folder (or its single file) removed before the new one goes in. */
    public function delete_old_plugin($removed, $local_destination, $remote_destination, $plugin)
    {
        if (is_wp_error($removed)) {
            return $removed;
        }
        $file = (string) ($plugin['plugin'] ?? '');
        if ($file === '') {
            return new WP_Error('bad_request', $this->strings['bad_request']);
        }
        $path = WP_PLUGIN_DIR . '/' . (str_contains($file, '/') ? dirname($file) : $file);
        if (!file_exists($path)) {
            return $removed;
        }
        return Minn\Support\Files::deleteTree($path) ? true : new WP_Error('remove_old_failed', $this->strings['remove_old_failed']);
    }
}

class Theme_Upgrader extends WP_Upgrader
{
    public $result;
    public $bulk = false;
    public $new_theme_data = [];

    public function upgrade_strings()
    {
        $this->strings['up_to_date'] = __('The theme is at the latest version.');
        $this->strings['no_package'] = __('Update package not available.');
        $this->strings['downloading_package'] = __('Downloading update from <span class="code pre">%s</span>&#8230;');
        $this->strings['unpack_package'] = __('Unpacking the update&#8230;');
        $this->strings['remove_old'] = __('Removing the old version of the theme&#8230;');
        $this->strings['remove_old_failed'] = __('Could not remove the old theme.');
        $this->strings['process_failed'] = __('Theme update failed.');
        $this->strings['process_success'] = __('Theme updated successfully.');
    }

    public function install_strings()
    {
        $this->strings['no_package'] = __('Installation package not available.');
        $this->strings['downloading_package'] = __('Downloading installation package from <span class="code pre">%s</span>&#8230;');
        $this->strings['unpack_package'] = __('Unpacking the package&#8230;');
        $this->strings['installing_package'] = __('Installing the theme&#8230;');
        $this->strings['remove_old'] = __('Removing the old version of the theme&#8230;');
        $this->strings['remove_old_failed'] = __('Could not remove the old theme.');
        $this->strings['no_files'] = __('The theme contains no files.');
        $this->strings['process_failed'] = __('Theme installation failed.');
        $this->strings['process_success'] = __('Theme installed successfully.');
        $this->strings['process_success_specific'] = __('Successfully installed the theme <strong>%1$s %2$s</strong>.');
        $this->strings['parent_theme_search'] = __('This theme requires a parent theme. Checking if it is installed&#8230;');
        $this->strings['parent_theme_prepare_install'] = __('Preparing to install <strong>%1$s %2$s</strong>&#8230;');
        $this->strings['parent_theme_currently_installed'] = __('The parent theme, <strong>%1$s %2$s</strong>, is currently installed.');
        $this->strings['parent_theme_install_success'] = __('Successfully installed the parent theme, <strong>%1$s %2$s</strong>.');
        $this->strings['parent_theme_not_found'] = __('<strong>The parent theme could not be found.</strong> You will need to install the parent theme, <strong>%s</strong>, before you can use this child theme.');
        $this->strings['current_theme_has_errors'] = __('The active theme has the following error: "%s".');
    }

    /** A child theme's parent: said to be installed, or not found (installing it from the directory is not done yet). */
    public function check_parent_theme_filter($install_result, $hook_extra, $child_result)
    {
        if (is_wp_error($install_result)) {
            return $install_result;
        }
        $theme = wp_get_theme((string) ($child_result['destination_name'] ?? ''), (string) ($child_result['local_destination'] ?? ''));
        $parent = (string) $theme->get('Template');
        if ($parent === '') {
            return $install_result;
        }
        $this->skin->feedback('parent_theme_search');
        $installed = wp_get_theme($parent);
        if ($installed->exists()) {
            $this->skin->feedback('parent_theme_currently_installed', $installed->display('Name'), $installed->display('Version'));
            return $install_result;
        }
        $this->skin->feedback('parent_theme_not_found', $parent);
        add_filter('install_theme_complete_actions', [$this, 'hide_activate_preview_actions']);
        return $install_result;
    }

    public function hide_activate_preview_actions($actions)
    {
        unset($actions['activate'], $actions['preview']);
        return $actions;
    }

    public function install($package, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true, 'overwrite_package' => false]);
        $this->init();
        $this->install_strings();
        add_filter('upgrader_source_selection', [$this, 'check_package']);
        add_filter('upgrader_post_install', [$this, 'check_parent_theme_filter'], 10, 3);
        if ($args['clear_update_cache']) {
            add_action('upgrader_process_complete', 'wp_clean_themes_cache', 9, 0);
        }
        $this->run(['package' => $package, 'destination' => get_theme_root(), 'clear_destination' => $args['overwrite_package'], 'clear_working' => true, 'hook_extra' => ['type' => 'theme', 'action' => 'install']]);
        remove_action('upgrader_process_complete', 'wp_clean_themes_cache', 9);
        remove_filter('upgrader_source_selection', [$this, 'check_package']);
        remove_filter('upgrader_post_install', [$this, 'check_parent_theme_filter']);
        if (!$this->result || is_wp_error($this->result)) {
            return $this->result;
        }
        wp_clean_themes_cache($args['clear_update_cache']);
        if ($args['overwrite_package']) {
            do_action('upgrader_overwrote_package', $package, $this->new_theme_data, 'theme');
        }
        return true;
    }

    /** Updates one theme to the offer in the update_themes transient; false when none is offered. */
    public function upgrade($theme, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true]);
        $this->init();
        $this->upgrade_strings();
        $offer = (get_site_transient('update_themes')->response ?? [])[$theme] ?? null;
        if ($offer === null) {
            $this->skin->before();
            $this->skin->set_result(false);
            $this->skin->error('up_to_date');
            $this->skin->after();
            return false;
        }
        $hooks = [['upgrader_pre_install', [$this, 'current_before'], 10, 2], ['upgrader_post_install', [$this, 'current_after'], 10, 2], ['upgrader_clear_destination', [$this, 'delete_old_theme'], 10, 4], ['upgrader_source_selection', [$this, 'check_package'], 10, 1]];
        foreach ($hooks as [$hook, $callback, $priority, $accepted]) {
            add_filter($hook, $callback, $priority, $accepted);
        }
        if ($args['clear_update_cache']) {
            add_action('upgrader_process_complete', 'wp_clean_themes_cache', 9, 0);
        }
        $this->run(['package' => ((array) $offer)['package'] ?? '', 'destination' => get_theme_root($theme), 'clear_destination' => true, 'clear_working' => true, 'hook_extra' => ['theme' => $theme, 'type' => 'theme', 'action' => 'update', 'temp_backup' => ['slug' => $theme, 'src' => get_theme_root($theme), 'dir' => 'themes']]]);
        remove_action('upgrader_process_complete', 'wp_clean_themes_cache', 9);
        foreach ($hooks as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }
        if (!$this->result || is_wp_error($this->result)) {
            return $this->result;
        }
        wp_clean_themes_cache($args['clear_update_cache']);
        return true;
    }

    /** Updates several themes, each to its offer; maintenance mode is on while the active theme is replaced. */
    public function bulk_upgrade($themes, $args = [])
    {
        $args = wp_parse_args($args, ['clear_update_cache' => true]);
        $themes = array_values((array) $themes);
        $this->init();
        $this->bulk = true;
        $this->upgrade_strings();
        $offers = (array) (get_site_transient('update_themes')->response ?? []);
        $current = array_intersect(array_intersect($themes, array_keys($offers)), [get_stylesheet(), get_template()]);
        $filters = [['upgrader_pre_install', 'current_before', 2], ['upgrader_post_install', 'current_after', 2], ['upgrader_clear_destination', 'delete_old_theme', 4], ['upgrader_source_selection', 'check_package', 1]];
        foreach ($filters as [$hook, $method, $accepted]) {
            add_filter($hook, [$this, $method], 10, $accepted);
        }
        $results = Upgrade::bulk($this, $themes, $offers, [
            'directories' => [WP_CONTENT_DIR],
            'maintenance' => $current !== [],
            'prepare' => function (string $theme): array {
                $this->skin->theme_info = $this->theme_info($theme);
                return [get_theme_root($theme), ['theme' => $theme, 'temp_backup' => ['slug' => $theme, 'src' => get_theme_root($theme), 'dir' => 'themes']]];
            },
            'clean' => static fn () => wp_clean_themes_cache($args['clear_update_cache']),
            'complete' => ['action' => 'update', 'type' => 'theme', 'bulk' => true, 'themes' => $themes],
        ]);
        foreach ($filters as [$hook, $method]) {
            remove_filter($hook, [$this, $method]);
        }
        return $results;
    }

    /** The unpacked folder must hold a theme this site can run: style.css with a name, and a template or a parent. */
    public function check_package($source)
    {
        $this->new_theme_data = [];
        if (is_wp_error($source) || !is_dir((string) $source)) {
            return $source;
        }
        $dir = trailingslashit((string) $source);
        $failed = static fn (string $code, string $why) => new WP_Error($code, __('The package could not be installed.'), $why);
        if (!file_exists($dir . 'style.css')) {
            return $failed('incompatible_archive_theme_no_style', sprintf(__('The theme is missing the %s stylesheet.'), '<code>style.css</code>'));
        }
        $this->new_theme_data = get_file_data($dir . 'style.css', ['Name' => 'Theme Name', 'Version' => 'Version', 'Author' => 'Author', 'Template' => 'Template', 'RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP']);
        if ($this->new_theme_data['Name'] === '') {
            return $failed('incompatible_archive_theme_no_name', sprintf(__('The %s stylesheet does not contain a valid theme header.'), '<code>style.css</code>'));
        }
        if ($this->new_theme_data['Template'] === '' && !file_exists($dir . 'index.php') && !file_exists($dir . 'templates/index.html') && !file_exists($dir . 'block-templates/index.html')) {
            return $failed('incompatible_archive_theme_no_index', sprintf(__('Template is missing. Standalone themes need to have a %1$s or %2$s template file. %3$s'), '<code>templates/index.html</code>', '<code>index.php</code>', sprintf(__('Child themes need to have a %1$s header in the %2$s stylesheet.'), '<code>Template</code>', '<code>style.css</code>')));
        }
        return _minn_package_requirements($source, $this->new_theme_data, 'theme', $this->strings['incompatible_archive']);
    }

    /** Updating the active theme alone turns maintenance mode on while it is replaced. */
    public function current_before($response, $theme)
    {
        if (!is_wp_error($response) && !$this->bulk && ($theme['theme'] ?? '') === get_stylesheet()) {
            $this->maintenance_mode(true);
        }
        return $response;
    }

    public function current_after($response, $theme)
    {
        if (!is_wp_error($response) && !$this->bulk && ($theme['theme'] ?? '') === get_stylesheet()) {
            $this->maintenance_mode(false);
        }
        return $response;
    }

    public function delete_old_theme($removed, $local_destination, $remote_destination, $theme)
    {
        if (is_wp_error($removed)) {
            return $removed;
        }
        $slug = (string) ($theme['theme'] ?? '');
        if ($slug === '') {
            return false;
        }
        $path = trailingslashit((string) get_theme_root($slug)) . $slug;
        if (!file_exists($path)) {
            return $removed;
        }
        return Minn\Support\Files::deleteTree($path);
    }

    /** The installed (or named) theme, or false when there is none to name. */
    public function theme_info($theme = null)
    {
        if (empty($theme)) {
            if (!is_array($this->result) || empty($this->result['destination_name'])) {
                return false;
            }
            $theme = $this->result['destination_name'];
        }
        return wp_get_theme($theme);
    }
}

/**
 * A package's Requires PHP and Requires at least against this site, in the
 * upgraders' words (probe upgrader); the source when it may be installed.
 */
function _minn_package_requirements($source, array $data, string $kind, string $refused)
{
    $php = (string) ($data['RequiresPHP'] ?? '');
    $wp = (string) ($data['RequiresWP'] ?? '');
    if (!is_php_version_compatible($php)) {
        /* translators: 1: Current PHP version, 2: Version required by the uploaded plugin or theme. */
        return new WP_Error('incompatible_php_required_version', $refused, sprintf($kind === 'theme' ? __('The PHP version on your server is %1$s, however the uploaded theme requires %2$s.') : __('The PHP version on your server is %1$s, however the uploaded plugin requires %2$s.'), PHP_VERSION, $php));
    }
    if (!is_wp_version_compatible($wp)) {
        return new WP_Error('incompatible_wp_required_version', $refused, sprintf($kind === 'theme' ? __('Your WordPress version is %1$s, however the uploaded theme requires %2$s.') : __('Your WordPress version is %1$s, however the uploaded plugin requires %2$s.'), wp_get_wp_version(), $wp));
    }
    return $source;
}
