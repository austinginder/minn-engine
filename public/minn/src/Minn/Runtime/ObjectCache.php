<?php

declare(strict_types=1);

namespace Minn\Runtime;

/** The per-request object cache behind wp_cache_*: groups of keys, nothing persistent. */
final class ObjectCache
{
    /** @var array<string, array<string, mixed>> */
    private array $groups = [];

    public function get(string $key, string $group, ?bool &$found = null): mixed
    {
        $found = array_key_exists($key, $this->groups[$group] ?? []);
        return $found ? $this->groups[$group][$key] : false;
    }

    public function set(string $key, mixed $value, string $group): bool
    {
        $this->groups[$group][$key] = $value;
        return true;
    }

    public function add(string $key, mixed $value, string $group): bool
    {
        if (array_key_exists($key, $this->groups[$group] ?? [])) {
            return false;
        }
        return $this->set($key, $value, $group);
    }

    public function delete(string $key, string $group): bool
    {
        if (!array_key_exists($key, $this->groups[$group] ?? [])) {
            return false;
        }
        unset($this->groups[$group][$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->groups = [];
        return true;
    }

    public function flushGroup(string $group): bool
    {
        unset($this->groups[$group]);
        return true;
    }
}
