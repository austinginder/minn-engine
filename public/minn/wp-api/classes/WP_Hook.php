<?php

use Minn\Runtime\Runtime;

/**
 * The hook object plugins reach through $wp_filter: its callbacks array is
 * the engine registry's own storage for that hook (shared by reference),
 * so what a plugin edits in place the engine runs. A hook made with new
 * WP_Hook() stands alone and runs its own callbacks.
 */
final class WP_Hook implements Iterator, ArrayAccess
{
    public $callbacks = [];
    public $priorities = [];
    private $name = null;
    private $nesting = [];

    /** @internal a hook object over the registry's storage for the name */
    public static function bound(string $name): self
    {
        $hook = new self();
        $hook->name = $name;
        $storage = &Runtime::hooks()->storage();
        $hook->callbacks = &$storage[$name];
        return $hook;
    }

    public function add_filter($hook_name, $callback, $priority, $accepted_args)
    {
        if ($this->name !== null) {
            Runtime::hooks()->add($this->name, $callback, $priority, (int) $accepted_args);
        } else {
            $this->callbacks[(int) $priority][_wp_filter_build_unique_id($hook_name, $callback, $priority)] = ['function' => $callback, 'accepted_args' => (int) $accepted_args];
            ksort($this->callbacks, SORT_NUMERIC);
        }
        $this->priorities = array_keys($this->callbacks);
    }

    public function remove_filter($hook_name, $callback, $priority)
    {
        $id = _wp_filter_build_unique_id($hook_name, $callback, $priority);
        $exists = isset($this->callbacks[(int) $priority][$id]);
        if ($exists) {
            unset($this->callbacks[(int) $priority][$id]);
            if ($this->callbacks[(int) $priority] === []) {
                unset($this->callbacks[(int) $priority]);
            }
        }
        $this->priorities = array_keys($this->callbacks);
        return $exists;
    }

    public function has_filter($hook_name = '', $callback = false)
    {
        if ($callback === false) {
            return $this->has_filters();
        }
        $id = _wp_filter_build_unique_id($hook_name, $callback, false);
        foreach ($this->callbacks as $priority => $callbacks) {
            if (isset($callbacks[$id])) {
                return $priority;
            }
        }
        return false;
    }

    public function has_filters()
    {
        foreach ($this->callbacks as $callbacks) {
            if ($callbacks !== []) {
                return true;
            }
        }
        return false;
    }

    public function remove_all_filters($priority = false)
    {
        if ($priority === false) {
            $this->callbacks = [];
        } else {
            unset($this->callbacks[(int) $priority]);
        }
        $this->priorities = array_keys($this->callbacks);
    }

    public function apply_filters($value, $args)
    {
        if ($this->callbacks === []) {
            return $value;
        }
        $args = array_values((array) $args);
        $priorities = array_keys($this->callbacks);
        sort($priorities, SORT_NUMERIC);
        foreach ($priorities as $priority) {
            $this->nesting[] = $priority;
            foreach ($this->callbacks[$priority] ?? [] as $entry) {
                $args[0] = $value;
                $accepted = (int) ($entry['accepted_args'] ?? 1);
                $value = ($entry['function'])(...($accepted <= 0 ? [] : array_slice($args, 0, $accepted)));
            }
            array_pop($this->nesting);
        }
        return $value;
    }

    public function do_action($args)
    {
        $args = array_values((array) $args);
        $this->apply_filters($args[0] ?? '', $args);
    }

    public function do_all_hook(&$args)
    {
        foreach ($this->callbacks as $callbacks) {
            foreach ($callbacks as $entry) {
                ($entry['function'])(...$args);
            }
        }
    }

    public function current_priority()
    {
        if ($this->name !== null) {
            return Runtime::hooks()->currentPriority($this->name);
        }
        return $this->nesting === [] ? false : $this->nesting[count($this->nesting) - 1];
    }

    public static function build_preinitialized_hooks($filters)
    {
        $out = [];
        foreach ($filters as $hook_name => $prioritized) {
            $hook = new self();
            foreach ($prioritized as $priority => $callbacks) {
                foreach ($callbacks as $entry) {
                    $hook->add_filter($hook_name, $entry['function'], $priority, $entry['accepted_args'] ?? 1);
                }
            }
            $out[$hook_name] = $hook;
        }
        return $out;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->callbacks[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->callbacks[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->callbacks[] = $value;
        } else {
            $this->callbacks[$offset] = $value;
        }
        $this->priorities = array_keys($this->callbacks);
    }

    public function offsetUnset($offset): void
    {
        unset($this->callbacks[$offset]);
        $this->priorities = array_keys($this->callbacks);
    }

    public function current(): mixed
    {
        return current($this->callbacks);
    }

    public function next(): void
    {
        next($this->callbacks);
    }

    public function key(): mixed
    {
        return key($this->callbacks);
    }

    public function valid(): bool
    {
        return key($this->callbacks) !== null;
    }

    public function rewind(): void
    {
        reset($this->callbacks);
    }
}
