<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Filters that run before the runtime exists, over the hooks added that
 * early: WP-CLI's add_wp_hook writes them into $wp_filter as plain arrays
 * (tag => priority => id => function and accepted_args). The reference has
 * its hook API by then; the engine reads the same arrays directly.
 */
final class EarlyFilters
{
    /** The value through every early callback on the tag, lowest priority first; as given when there are none. */
    public static function apply(string $tag, mixed $value, mixed ...$args): mixed
    {
        $hooks = $GLOBALS['wp_filter'][$tag] ?? null;
        if (!is_array($hooks)) {
            return $value;
        }
        ksort($hooks);
        foreach ($hooks as $callbacks) {
            foreach ((array) $callbacks as $callback) {
                if (is_array($callback) && isset($callback['function']) && is_callable($callback['function'])) {
                    $value = ($callback['function'])(...array_slice([$value, ...$args], 0, max(1, (int) ($callback['accepted_args'] ?? 1))));
                }
            }
        }
        return $value;
    }
}
