<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\Inventory;
use WP_CLI\Formatter;

/** `wp theme list`: the inventory CaptainCore's fetch-site-data reads. */
final class ThemeCommand
{
    private const FIELDS = ['name', 'status', 'update', 'version', 'update_version', 'auto_update'];

    /**
     * Lists installed themes.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter to one status: active, inactive, or parent.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each theme.
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
        $items = (new Inventory(ABSPATH . 'wp-content', $runtime->site))->themes();
        $status = (string) ($assocArgs['status'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, static fn (array $item) => $item['status'] === $status));
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }
}
