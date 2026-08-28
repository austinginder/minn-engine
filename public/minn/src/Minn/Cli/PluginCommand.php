<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\Inventory;
use WP_CLI\Formatter;

/** `wp plugin list`: the inventory CaptainCore's fetch-site-data reads. */
final class PluginCommand
{
    private const FIELDS = ['name', 'status', 'update', 'version', 'update_version', 'auto_update'];

    /**
     * Lists installed plugins, must-use plugins, and drop-ins.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter to one status: active, inactive, must-use, or dropin.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each plugin.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function list(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $items = (new Inventory(ABSPATH . 'wp-content', $runtime->site))->plugins();
        $status = (string) ($assocArgs['status'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, static fn (array $item) => $item['status'] === $status));
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }
}
