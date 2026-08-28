<?php

declare(strict_types=1);

namespace Minn\Cli;

use WP_CLI;

/** `wp cache flush`: the engine has no object cache, so this is a no-op success. */
final class CacheCommand
{
    /**
     * Flushes the object cache.
     *
     * The engine does not run an object cache. The verb still succeeds so
     * fleet scripts that call it after a write do not abort.
     *
     * @when before_wp_load
     */
    public function flush(array $args, array $assocArgs): void
    {
        WP_CLI::success('The cache was flushed.');
    }
}
