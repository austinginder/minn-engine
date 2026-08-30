<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;

/**
 * `wp rewrite flush|structure`: permalink_structure is the engine's
 * resolver input; there is no .htaccess rewrite file to regenerate.
 */
final class RewriteCommand
{
    /**
     * Flushes rewrite rules. The engine resolves URLs from options, so
     * this is a success no-op the way `cache flush` is.
     *
     * ## OPTIONS
     *
     * [--hard]
     * : Ignored: the engine does not write .htaccess.
     *
     * @when before_wp_load
     */
    public function flush(array $args, array $assocArgs): void
    {
        Runtime::boot();
        WP_CLI::success('Rewrite rules flushed.');
    }

    /**
     * Updates the permalink structure.
     *
     * ## OPTIONS
     *
     * <permastruct>
     * : The new permalink structure, stored as permalink_structure.
     *
     * [--category-base=<base>]
     * : Stored as category_base.
     *
     * [--tag-base=<base>]
     * : Stored as tag_base.
     *
     * [--hard]
     * : Ignored: the engine does not write .htaccess.
     *
     * @when before_wp_load
     */
    public function structure(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $runtime->site->setOption('permalink_structure', (string) ($args[0] ?? ''));
        if (isset($assocArgs['category-base'])) {
            $runtime->site->setOption('category_base', (string) $assocArgs['category-base']);
        }
        if (isset($assocArgs['tag-base'])) {
            $runtime->site->setOption('tag_base', (string) $assocArgs['tag-base']);
        }
        WP_CLI::success('Rewrite structure set.');
        WP_CLI::success('Rewrite rules flushed.');
    }
}
