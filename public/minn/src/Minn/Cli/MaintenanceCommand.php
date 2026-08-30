<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;

/**
 * `wp maintenance-mode`: the `.maintenance` marker in the webroot. The
 * engine does not take the public site down while the file is present;
 * fleet scripts still need the verb to succeed so an update can bookend
 * itself with activate/deactivate.
 */
final class MaintenanceCommand
{
    /**
     * Activates maintenance mode.
     *
     * @when before_wp_load
     */
    public function activate(array $args, array $assocArgs): void
    {
        Runtime::boot();
        $file = $this->file();
        if (is_file($file)) {
            WP_CLI::error('Maintenance mode already activated.');
        }
        WP_CLI::log('Enabling Maintenance mode...');
        file_put_contents($file, '<?php $upgrading = ' . time() . ";\n");
        WP_CLI::success('Activated Maintenance mode.');
    }

    /**
     * Deactivates maintenance mode.
     *
     * @when before_wp_load
     */
    public function deactivate(array $args, array $assocArgs): void
    {
        Runtime::boot();
        $file = $this->file();
        if (!is_file($file)) {
            WP_CLI::error('Maintenance mode already deactivated.');
        }
        WP_CLI::log('Disabling Maintenance mode...');
        unlink($file);
        WP_CLI::success('Deactivated Maintenance mode.');
    }

    /**
     * Displays maintenance mode status.
     *
     * @when before_wp_load
     */
    public function status(array $args, array $assocArgs): void
    {
        Runtime::boot();
        WP_CLI::log(is_file($this->file()) ? 'Maintenance mode is active.' : 'Maintenance mode is not active.');
    }

    /**
     * Detects maintenance mode status. Exit 0 when active, 1 when not.
     *
     * @when before_wp_load
     */
    public function is_active(array $args, array $assocArgs): void
    {
        Runtime::boot();
        if (!is_file($this->file())) {
            WP_CLI::halt(1);
        }
    }

    private function file(): string
    {
        return rtrim(ABSPATH, '/') . '/.maintenance';
    }
}
