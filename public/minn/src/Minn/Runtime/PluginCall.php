<?php

// No strict_types in this file, on purpose: see the class.

namespace Minn\Runtime;

/**
 * Calls code a plugin, theme or drop-in registered (a hook callback, a
 * shortcode, a widget, a REST permission or field callback), as WordPress
 * calls it. PHP judges a call's argument types by the file the call is made
 * from: the engine's own files declare strict types, so a callback typed
 * `int $post_id` handed the string "3494" would throw a TypeError there,
 * where WordPress's untyped files let PHP convert it (Elementor's heartbeat
 * passes the post ID it was posted, a string, to just such a callback).
 * Every call into extension code is made from here, the one engine file
 * that leaves strict types off.
 */
final class PluginCall
{
    /**
     * Runs a callback extension code registered, with the arguments given.
     *
     * @param list<mixed> $args
     */
    public static function run(callable $callback, array $args = []): mixed
    {
        return $callback(...$args);
    }
}
