<?php

declare(strict_types=1);

namespace Minn\Blocks;

/** The CSS selector a block type declares for its root or for one feature, from its `selectors` map or the older per-support keys. */
final class Selector
{
    /**
     * @param array<string, mixed> $selectors the block type's selectors map
     * @param array<string, mixed> $supports the block type's supports
     * @param string|list<string>|null $target 'root', a dotted feature path, or a path list
     */
    public static function resolve(array $selectors, array $supports, string|array|null $target, bool $fallback, string $defaultClass): ?string
    {
        $root = $selectors['root'] ?? (is_string($supports['__experimentalSelector'] ?? null) ? $supports['__experimentalSelector'] : null) ?? '.' . $defaultClass;
        if ($target === 'root' || $target === '' || $target === null) {
            return $root;
        }
        $path = is_string($target) ? explode('.', $target) : array_values($target);
        $declared = self::at($selectors, $path);
        if (is_string($declared)) {
            return $declared;
        }
        if (is_array($declared) && isset($declared['root'])) {
            return $declared['root'];
        }
        $feature = (string) ($path[0] ?? '');
        if ($feature !== '' && is_string($supports[$feature]['__experimentalSelector'] ?? null)) {
            return $supports[$feature]['__experimentalSelector'];
        }
        return $fallback ? $root : null;
    }

    /** @param list<string> $path */
    private static function at(array $tree, array $path): mixed
    {
        foreach ($path as $step) {
            if (!is_array($tree) || !array_key_exists($step, $tree)) {
                return null;
            }
            $tree = $tree[$step];
        }
        return $tree;
    }
}
