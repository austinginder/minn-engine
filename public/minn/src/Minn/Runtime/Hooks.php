<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * The hook registry plugin code registers into and the engine fires.
 * Semantics come from contracts/fixtures/api/hooks.json: callbacks run
 * by ascending priority then insertion order; a callback added at a
 * higher priority during a run takes part in that run, one added at the
 * current or a lower priority waits for the next; a callback removed
 * before its turn is skipped; the "all" hook sees every firing with the
 * hook name first and every argument regardless of its accepted count.
 */
final class Hooks
{
    /** @var array<string, array<int, array<string, array{function: callable, accepted_args: int}>>> the reference's $wp_filter shape */
    private array $hooks = [];
    /** @var array<string, int> */
    private array $actionsDone = [];
    /** @var array<string, int> */
    private array $filtersDone = [];
    /** @var list<string> */
    private array $stack = [];
    /** @var array<string, list<int>> the priority each running hook is at, innermost last */
    private array $running = [];
    private ?Closure $onNew = null;

    /** Called with each hook name the first time a callback registers under it. */
    public function onNew(Closure $observer): void
    {
        $this->onNew = $observer;
        foreach (array_keys($this->hooks) as $name) {
            $observer((string) $name);
        }
    }

    /** The live registry, for a hook object to share by reference. */
    public function &storage(): array
    {
        return $this->hooks;
    }

    /** @return array<string, int> */
    /** How often each action, or each filter, has run, by hook. */
    public function &actionCounters(): array
    {
        return $this->actionsDone;
    }

    /** How often each filter has run, by hook, by reference for the facade's global. */
    public function &filterCounters(): array
    {
        return $this->filtersDone;
    }

    /** @return list<string> */
    /** The hooks running now, outermost first, by reference for the facade\'s globals. */
    public function &stackRef(): array
    {
        return $this->stack;
    }

    /** The priority a running hook is at, or false when it is idle. */
    public function currentPriority(string $hook): int|false
    {
        $levels = $this->running[$hook] ?? [];
        return $levels === [] ? false : $levels[count($levels) - 1];
    }

    /** Adds a callback to a hook at a priority. */
    public function add(string $hook, callable|array|string $callback, int|string $priority = 10, int $accepted = 1): bool
    {
        $new = !isset($this->hooks[$hook]);
        $this->hooks[$hook][(int) $priority][self::id($callback)] = ['function' => $callback, 'accepted_args' => $accepted];
        ksort($this->hooks[$hook], SORT_NUMERIC);
        if ($new && $this->onNew !== null) {
            ($this->onNew)($hook);
        }
        return true;
    }

    /** Removes a callback from a hook. */
    public function remove(string $hook, callable|array|string $callback, int|string $priority = 10): bool
    {
        $id = self::id($callback);
        $priority = (int) $priority;
        if (!isset($this->hooks[$hook][$priority][$id])) {
            return false;
        }
        unset($this->hooks[$hook][$priority][$id]);
        if ($this->hooks[$hook][$priority] === []) {
            unset($this->hooks[$hook][$priority]);
        }
        return true;
    }

    /** Every callback of a hook, or only those at one priority. Always true, as observed. */
    public function removeAll(string $hook, int|string|false $priority = false): bool
    {
        if ($priority === false) {
            if (isset($this->hooks[$hook])) {
                $this->hooks[$hook] = [];
            }
        } else {
            unset($this->hooks[$hook][(int) $priority]);
        }
        return true;
    }

    /** With a callback: its lowest priority, or false; without: whether anything is registered. */
    public function has(string $hook, callable|array|string|false $callback = false): int|bool
    {
        if ($callback === false) {
            return ($this->hooks[$hook] ?? []) !== [];
        }
        $id = self::id($callback);
        $priorities = array_keys($this->hooks[$hook] ?? []);
        sort($priorities);
        foreach ($priorities as $priority) {
            if (isset($this->hooks[$hook][$priority][$id])) {
                return $priority;
            }
        }
        return false;
    }

    /**
     * Whether anything is hooked besides the callbacks the engine does the
     * work of itself (named function => priority), as filterWithout skips them.
     *
     * @param array<string, int> $done
     */
    public function hasBeyond(string $hook, array $done): bool
    {
        foreach ($this->hooks[$hook] ?? [] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $name = $callback['function'] ?? null;
                if (!is_string($name) || ($done[$name] ?? null) !== (int) $priority) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Runs a filter and returns the value.
     *
     * @param list<mixed> $args the value first
     */
    public function filter(string $hook, array $args): mixed
    {
        $this->filtersDone[$hook] = ($this->filtersDone[$hook] ?? 0) + 1;
        return $this->run($hook, $args, true);
    }

    /**
     * Runs a filter without the callbacks a caller has already done the work
     * of, named function => priority: the engine renders post content through
     * its own pipeline, then runs the_content for everything else hooked
     * there. A callback a plugin removed is simply not there to skip.
     *
     * @param list<mixed> $args the value first
     * @param array<string, int> $done
     */
    public function filterWithout(string $hook, array $args, array $done): mixed
    {
        $this->filtersDone[$hook] = ($this->filtersDone[$hook] ?? 0) + 1;
        return $this->run($hook, $args, true, $done);
    }

    /**
     * Runs an action. With no arguments its callbacks still get one, an
     * empty string, as do_action() gives them in the reference (a callback
     * that requires a parameter is not short of one).
     *
     * @param list<mixed> $args
     */
    public function action(string $hook, array $args): void
    {
        $this->actionRef($hook, $args === [] ? [''] : $args);
    }

    /**
     * Runs an action with exactly the arguments given, none included
     * (do_action_ref_array).
     *
     * @param list<mixed> $args
     */
    public function actionRef(string $hook, array $args): void
    {
        $this->actionsDone[$hook] = ($this->actionsDone[$hook] ?? 0) + 1;
        $this->run($hook, $args, false);
    }

    /**
     * do_action_ref_array: the action's callbacks get the arguments, and an
     * 'all' callback gets them as the one array they were passed in, as the
     * reference hands them on.
     *
     * @param list<mixed> $args
     */
    public function actionRefArray(string $hook, array $args): void
    {
        $this->actionsDone[$hook] = ($this->actionsDone[$hook] ?? 0) + 1;
        $this->run($hook, $args, false, [], [$args]);
    }

    /**
     * apply_filters_ref_array: as filter(), with an 'all' callback handed
     * the arguments as one array.
     *
     * @param list<mixed> $args
     */
    public function filterRefArray(string $hook, array $args): mixed
    {
        $this->filtersDone[$hook] = ($this->filtersDone[$hook] ?? 0) + 1;
        return $this->run($hook, $args, true, [], [$args]);
    }

    /** How often an action has run. */
    public function actionsDone(string $hook): int
    {
        return $this->actionsDone[$hook] ?? 0;
    }

    /** How often a filter has run. */
    public function filtersDone(string $hook): int
    {
        return $this->filtersDone[$hook] ?? 0;
    }

    /** The hook running now, or false. */
    public function current(): string|false
    {
        return $this->stack === [] ? false : $this->stack[count($this->stack) - 1];
    }

    /** Whether a hook, or any hook, is running. */
    public function doing(?string $hook): bool
    {
        return $hook === null ? $this->stack !== [] : in_array($hook, $this->stack, true);
    }

    /**
     * Every hook with callbacks, by name.
     *
     * @return array<string, array<int, list<callable>>> a read-only view for diagnostics
     */
    public function registered(): array
    {
        $out = [];
        foreach ($this->hooks as $hook => $byPriority) {
            ksort($byPriority);
            foreach ($byPriority as $priority => $entries) {
                $out[$hook][$priority] = array_map(static fn (array $e) => $e['function'], array_values($entries));
            }
        }
        return $out;
    }

    /**
     * @param list<mixed> $args
     * @param array<string, int> $skip function name => priority, passed over
     * @param list<mixed>|null $allArgs what an 'all' callback gets after the hook's name; the arguments when null
     */
    private function run(string $hook, array $args, bool $isFilter, array $skip = [], ?array $allArgs = null): mixed
    {
        $value = $isFilter ? ($args[0] ?? null) : null;
        if ($hook !== 'all' && isset($this->hooks['all'])) {
            $this->fireAll($hook, $allArgs ?? $args);
        }
        if (!isset($this->hooks[$hook])) {
            return $value;
        }
        $this->stack[] = $hook;
        $current = null;
        while (($priority = $this->nextPriority($hook, $current)) !== null) {
            $current = $priority;
            $this->running[$hook][] = $priority;
            foreach (array_keys($this->hooks[$hook][$priority] ?? []) as $id) {
                $entry = $this->hooks[$hook][$priority][$id] ?? null;
                if (!is_array($entry) || !isset($entry['function'])) {
                    continue;
                }
                $callback = $entry['function'];
                if ($skip !== [] && is_string($callback) && ($skip[$callback] ?? null) === $priority) {
                    continue;
                }
                $accepted = (int) ($entry['accepted_args'] ?? 1);
                if ($isFilter) {
                    $args[0] = $value;
                }
                $passed = $accepted <= 0 ? [] : array_slice($args, 0, $accepted);
                $result = $callback(...$passed);
                if ($isFilter) {
                    $value = $result;
                }
            }
            array_pop($this->running[$hook]);
        }
        array_pop($this->stack);
        return $value;
    }

    private function nextPriority(string $hook, ?int $after): ?int
    {
        $next = null;
        foreach (array_keys($this->hooks[$hook] ?? []) as $priority) {
            if (($after === null || $priority > $after) && ($next === null || $priority < $next)) {
                $next = $priority;
            }
        }
        return $next;
    }

    /** @param list<mixed> $args */
    private function fireAll(string $hook, array $args): void
    {
        $this->stack[] = 'all';
        $byPriority = $this->hooks['all'];
        ksort($byPriority);
        foreach ($byPriority as $entries) {
            foreach ($entries as $entry) {
                ($entry['function'])($hook, ...$args);
            }
        }
        array_pop($this->stack);
    }

    /** One identity per callable: closures and objects by instance, static methods in either spelling as one. */
    private static function id(callable|array|string $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }
        if ($callback instanceof Closure || is_object($callback)) {
            return spl_object_hash($callback);
        }
        [$target, $method] = $callback;
        return (is_object($target) ? spl_object_hash($target) : (string) $target) . '::' . $method;
    }
}
