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
    /** @var array<string, array<int, array<string, array{callable, int}>>> hook => priority => id => [callback, accepted] */
    private array $hooks = [];
    /** @var array<string, int> */
    private array $actionsDone = [];
    /** @var array<string, int> */
    private array $filtersDone = [];
    /** @var list<string> */
    private array $stack = [];

    public function add(string $hook, callable|array|string $callback, int|string $priority = 10, int $accepted = 1): bool
    {
        $this->hooks[$hook][(int) $priority][self::id($callback)] = [$callback, $accepted];
        return true;
    }

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
            unset($this->hooks[$hook]);
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

    /** @param list<mixed> $args the value first */
    public function filter(string $hook, array $args): mixed
    {
        $this->filtersDone[$hook] = ($this->filtersDone[$hook] ?? 0) + 1;
        return $this->run($hook, $args, true);
    }

    /** @param list<mixed> $args */
    public function action(string $hook, array $args): void
    {
        $this->actionsDone[$hook] = ($this->actionsDone[$hook] ?? 0) + 1;
        $this->run($hook, $args, false);
    }

    public function actionsDone(string $hook): int
    {
        return $this->actionsDone[$hook] ?? 0;
    }

    public function filtersDone(string $hook): int
    {
        return $this->filtersDone[$hook] ?? 0;
    }

    public function current(): string|false
    {
        return $this->stack === [] ? false : $this->stack[count($this->stack) - 1];
    }

    public function doing(?string $hook): bool
    {
        return $hook === null ? $this->stack !== [] : in_array($hook, $this->stack, true);
    }

    /** @return array<string, array<int, list<callable>>> a read-only view for diagnostics */
    public function registered(): array
    {
        $out = [];
        foreach ($this->hooks as $hook => $byPriority) {
            ksort($byPriority);
            foreach ($byPriority as $priority => $entries) {
                $out[$hook][$priority] = array_map(static fn (array $e) => $e[0], array_values($entries));
            }
        }
        return $out;
    }

    /** @param list<mixed> $args */
    private function run(string $hook, array $args, bool $isFilter): mixed
    {
        $value = $isFilter ? ($args[0] ?? null) : null;
        if ($hook !== 'all' && isset($this->hooks['all'])) {
            $this->fireAll($hook, $args);
        }
        if (!isset($this->hooks[$hook])) {
            return $value;
        }
        $this->stack[] = $hook;
        $current = null;
        while (($priority = $this->nextPriority($hook, $current)) !== null) {
            $current = $priority;
            foreach (array_keys($this->hooks[$hook][$priority] ?? []) as $id) {
                $entry = $this->hooks[$hook][$priority][$id] ?? null;
                if ($entry === null) {
                    continue;
                }
                [$callback, $accepted] = $entry;
                if ($isFilter) {
                    $args[0] = $value;
                }
                $passed = $accepted <= 0 ? [] : array_slice($args, 0, $accepted);
                $result = $callback(...$passed);
                if ($isFilter) {
                    $value = $result;
                }
            }
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
            foreach ($entries as [$callback]) {
                $callback($hook, ...$args);
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
