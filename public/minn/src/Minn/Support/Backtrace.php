<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * A call stack named as wp_debug_backtrace_summary names it (probe
 * placeholders-a): Class->method, Class::method and function names; a
 * hook's function with the hook named; an included file by its path with
 * the given prefixes taken off, in order. Innermost first.
 */
final class Backtrace
{
    private const HOOKS = ['do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array'];
    private const INCLUDES = ['include', 'include_once', 'require', 'require_once'];

    /**
     * The frames' names, after the first $skip.
     *
     * @param list<array<string, mixed>> $frames debug_backtrace()'s frames, innermost first
     * @param list<string> $truncate path prefixes taken off an included file
     * @return list<string>
     */
    public static function summary(array $frames, ?string $ignoreClass, int $skip, array $truncate): array
    {
        $names = [];
        foreach (array_slice($frames, $skip) as $call) {
            $function = (string) ($call['function'] ?? '');
            if (isset($call['class'])) {
                if ($ignoreClass !== $call['class']) {
                    $names[] = $call['class'] . ($call['type'] ?? '->') . $function;
                }
            } elseif (in_array($function, self::HOOKS, true)) {
                $names[] = "{$function}('" . ($call['args'][0] ?? '') . "')";
            } elseif (in_array($function, self::INCLUDES, true)) {
                $names[] = "{$function}('" . str_replace($truncate, '', str_replace('\\', '/', (string) ($call['args'][0] ?? ''))) . "')";
            } else {
                $names[] = $function;
            }
        }
        return $names;
    }
}
