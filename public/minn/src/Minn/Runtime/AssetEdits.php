<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What a plugin did to $wp_scripts->registered or $wp_styles->registered
 * directly, as wp_default_scripts callbacks do (a dependency dropped, a
 * source swapped, an entry unset or added), handed to the registry: each
 * handle as the view showed it last against the view as the plugin left
 * it.
 */
final class AssetEdits
{
    /**
     * The edits applied. The view is rebuilt by every change the registry
     * takes, so the edited view is read from the copy handed in.
     *
     * @param array<string, mixed> $edited the view as the plugin left it
     * @param array<string, array{0: mixed, 1: list<string>, 2: mixed, 3: mixed}> $synced each handle as the view last showed it
     */
    public static function apply(Assets $assets, array $edited, array $synced): void
    {
        foreach ($synced as $handle => $was) {
            $dep = $edited[$handle] ?? null;
            if (!$dep instanceof \_WP_Dependency) {
                $assets->deregister((string) $handle);
                continue;
            }
            $now = [$dep->src, array_values((array) $dep->deps), $dep->ver, $dep->args];
            if ($now !== $was) {
                $assets->revise((string) $handle, $dep->src === false ? false : (string) $dep->src, (array) $dep->deps, $dep->ver, $dep->args);
            }
        }
        foreach (array_diff_key($edited, $synced) as $handle => $dep) {
            if ($dep instanceof \_WP_Dependency) {
                $assets->register((string) $handle, $dep->src === false ? false : (string) $dep->src, (array) $dep->deps, $dep->ver, $dep->args);
            }
        }
    }
}
