<?php
/**
 * The reference's hook names as contracts/api/hooks.json lists them, and
 * how a hook name met elsewhere (fired on a stack, hooked by a plugin)
 * resolves to one of them: an exact name first, else the most specific
 * dynamic pattern that matches it (pre_option_blogname is pre_option_{*}).
 * A pattern needs four characters of fixed text of its own to claim a name.
 * Shared by tests/tools/hook-status.php, hook-weights.php and
 * tests/hook-parity.test.php.
 */

/** @return Closure(string): ?string the inventory name a hook name stands for, or null when it is not the reference's */
function minn_hook_resolver(array $inventory): Closure
{
    $exact = [];
    $patterns = [];
    foreach (array_keys($inventory) as $name) {
        $name = (string) $name;
        if ($name === '' || str_contains($name, '{$')) {
            continue;
        }
        if (!str_contains($name, '{*}')) {
            $exact[$name] = true;
        } elseif (strlen(str_replace('{*}', '', $name)) >= 4) {
            $patterns[$name] = '/^' . str_replace('\{\*\}', '.+', preg_quote($name, '/')) . '$/';
        }
    }
    uksort($patterns, static fn (string $a, string $b): int => strlen(str_replace('{*}', '', $b)) <=> strlen(str_replace('{*}', '', $a)) ?: strcmp($a, $b));
    $memo = [];
    return static function (string $name) use ($exact, $patterns, &$memo): ?string {
        if (array_key_exists($name, $memo)) {
            return $memo[$name];
        }
        if (isset($exact[$name]) || isset($patterns[$name])) {
            return $memo[$name] = $name;
        }
        if (!str_contains($name, '{*}')) {
            foreach ($patterns as $pattern => $regex) {
                if (preg_match($regex, $name) === 1) {
                    return $memo[$name] = $pattern;
                }
            }
        }
        return $memo[$name] = null;
    };
}
