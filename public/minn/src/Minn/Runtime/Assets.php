<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The registry behind wp_register_/wp_enqueue_ for scripts and styles:
 * handles, sources, dependencies, versions, inline additions, localized
 * data, and the head/footer split. Printing follows the reference's tag
 * shapes as far as captured; see contracts/runtime.md.
 */
final class Assets
{
    /** @var array<string, array<string, mixed>> */
    private array $items = [];
    /** @var list<string> */
    private array $queue = [];
    /** @var list<string> */
    private array $done = [];

    public function __construct(private readonly string $kind)
    {
    }

    public function register(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool
    {
        if (isset($this->items[$handle])) {
            return false;
        }
        $this->items[$handle] = ['src' => $src, 'deps' => array_values(array_map('strval', $deps)), 'ver' => $ver, 'extra' => $extra, 'inline' => ['before' => [], 'after' => []], 'localized' => [], 'data' => [], 'args' => []];
        return true;
    }

    public function deregister(string $handle): void
    {
        unset($this->items[$handle]);
        $this->queue = array_values(array_diff($this->queue, [$handle]));
    }

    public function enqueue(string $handle): void
    {
        if (!in_array($handle, $this->queue, true)) {
            $this->queue[] = $handle;
        }
    }

    public function dequeue(string $handle): void
    {
        $this->queue = array_values(array_diff($this->queue, [$handle]));
    }

    public function registered(string $handle): bool
    {
        return isset($this->items[$handle]);
    }

    public function enqueued(string $handle): bool
    {
        return in_array($handle, $this->queue, true);
    }

    public function done(string $handle): bool
    {
        return in_array($handle, $this->done, true);
    }

    public function addInline(string $handle, string $code, string $position): bool
    {
        if (!isset($this->items[$handle])) {
            return false;
        }
        $this->items[$handle]['inline'][$position === 'before' ? 'before' : 'after'][] = $code;
        return true;
    }

    public function addData(string $handle, string $key, mixed $value): bool
    {
        if (!isset($this->items[$handle])) {
            return false;
        }
        $this->items[$handle]['data'][$key] = $value;
        return true;
    }

    public function data(string $handle, string $key): mixed
    {
        return $this->items[$handle]['data'][$key] ?? false;
    }

    public function localize(string $handle, string $name, array $data): bool
    {
        if (!isset($this->items[$handle])) {
            return false;
        }
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $data[$k] = html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8');
            }
        }
        $this->items[$handle]['localized'][] = 'var ' . $name . ' = ' . json_encode($data) . ';';
        return true;
    }

    /** Every queued handle not yet printed, dependencies first, filtered to the group (footer or not). */
    public function toPrint(?bool $footer = null): array
    {
        $order = [];
        // A handle whose dependency is unregistered never prints, nor does anything that depends on it.
        $visit = function (string $handle) use (&$order, &$visit): bool {
            if (in_array($handle, $order, true) || in_array($handle, $this->done, true)) {
                return true;
            }
            if (!isset($this->items[$handle])) {
                return false;
            }
            foreach ($this->items[$handle]['deps'] as $dep) {
                if (!$visit($dep)) {
                    return false;
                }
            }
            $order[] = $handle;
            return true;
        };
        foreach ($this->queue as $handle) {
            $visit($handle);
        }
        if ($footer === null) {
            return $order;
        }
        return array_values(array_filter($order, fn (string $h) => (bool) ($this->items[$h]['data']['group'] ?? false) === $footer));
    }

    public function markDone(string $handle): void
    {
        $this->done[] = $handle;
    }

    public function item(string $handle): ?array
    {
        return $this->items[$handle] ?? null;
    }

    /** @return array<string, array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<string> */
    public function queue(): array
    {
        return $this->queue;
    }

    public function kind(): string
    {
        return $this->kind;
    }
}
