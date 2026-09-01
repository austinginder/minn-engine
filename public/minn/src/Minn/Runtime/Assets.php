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

    private ?\Closure $onChange = null;

    public function __construct(private readonly string $kind)
    {
    }

    /** A listener called after every change, so a plugin-facing view can stay current. */
    public function watch(\Closure $listener): void
    {
        $this->onChange = $listener;
        $listener();
    }

    /** The queue as a plugin left it after editing the view directly. */
    public function setQueue(array $queue): void
    {
        $this->queue = array_values(array_map('strval', $queue));
    }

    private function changed(): void
    {
        if ($this->onChange !== null) {
            ($this->onChange)();
        }
    }

    /** Registers an asset under a handle. */
    public function register(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool
    {
        if (isset($this->items[$handle])) {
            $this->changed();
            return false;
        }
        $this->items[$handle] = ['src' => $src, 'deps' => array_values(array_map('strval', $deps)), 'ver' => $ver, 'extra' => $extra, 'inline' => ['before' => [], 'after' => []], 'localized' => [], 'data' => [], 'args' => []];
        $this->changed();
        return true;
        $this->changed();
    }

    /**
     * The hosts the queued assets load from, other than the site's own.
     *
     * @return list<string> hosts of enqueued sources (dependencies first) away from the given host, for dns-prefetch hints
     */
    public function externalHosts(string $ownHost): array
    {
        $hosts = [];
        $seen = [];
        $walk = function (string $handle) use (&$walk, &$hosts, &$seen, $ownHost): void {
            if (isset($seen[$handle]) || !isset($this->items[$handle])) {
                return;
            }
            $seen[$handle] = true;
            foreach ((array) ($this->items[$handle]['deps'] ?? []) as $dep) {
                $walk((string) $dep);
            }
            $host = parse_url((string) ($this->items[$handle]['src'] ?? ''), PHP_URL_HOST);
            if (is_string($host) && $host !== '' && $host !== $ownHost && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        };
        foreach ($this->queue as $handle) {
            $walk($handle);
        }
        return $hosts;
    }

    /** Forgets an asset. */
    public function deregister(string $handle): void
    {
        unset($this->items[$handle]);
        $this->queue = array_values(array_diff($this->queue, [$handle]));
        $this->changed();
    }

    /** Queues an asset for printing. */
    public function enqueue(string $handle): void
    {
        if (!in_array($handle, $this->queue, true)) {
            $this->queue[] = $handle;
        }
        $this->changed();
    }

    /** Removes an asset from the queue. */
    public function dequeue(string $handle): void
    {
        $this->queue = array_values(array_diff($this->queue, [$handle]));
        $this->changed();
    }

    /** Whether a handle is registered. */
    public function registered(string $handle): bool
    {
        return isset($this->items[$handle]);
    }

    /**
     * Queued directly, or pulled in as the dependency of something queued,
     * however deep. The reference answers the same way, and plugin code
     * leans on it: WooCommerce only attaches its settings blob when it
     * finds `wc-settings` "enqueued", and nothing queues that handle by
     * name, it only ever rides in as a dependency.
     */
    public function enqueued(string $handle): bool
    {
        if (in_array($handle, $this->queue, true)) {
            return true;
        }
        $seen = [];
        $reaches = function (string $from) use (&$reaches, &$seen, $handle): bool {
            if (isset($seen[$from])) {
                return false;
            }
            $seen[$from] = true;
            foreach ($this->items[$from]['deps'] ?? [] as $dep) {
                if ($dep === $handle || $reaches($dep)) {
                    return true;
                }
            }
            return false;
        };
        foreach ($this->queue as $queued) {
            if ($reaches($queued)) {
                return true;
            }
        }
        return false;
    }

    /** Whether a handle has been printed. */
    public function done(string $handle): bool
    {
        return in_array($handle, $this->done, true);
    }

    /** Attaches inline code to an asset. */
    public function addInline(string $handle, string $code, string $position): bool
    {
        if (!isset($this->items[$handle])) {
            $this->changed();
            return false;
        }
        $this->items[$handle]['inline'][$position === 'before' ? 'before' : 'after'][] = $code;
        $this->changed();
        return true;
        $this->changed();
    }

    /** Attaches a data key to an asset. */
    public function addData(string $handle, string $key, mixed $value): bool
    {
        if (!isset($this->items[$handle])) {
            $this->changed();
            return false;
        }
        $this->items[$handle]['data'][$key] = $value;
        $this->changed();
        return true;
        $this->changed();
    }

    /** A data key of an asset, or false. */
    public function data(string $handle, string $key): mixed
    {
        return $this->items[$handle]['data'][$key] ?? false;
    }

    /** Attaches a localized object to a script. */
    public function localize(string $handle, string $name, array $data): bool
    {
        if (!isset($this->items[$handle])) {
            $this->changed();
            return false;
        }
        foreach ($data as $k => $v) {
            if (is_scalar($v)) {
                $data[$k] = html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8');
            }
        }
        // The reference prints localized data with slashes unescaped.
        $this->items[$handle]['localized'][] = 'var ' . $name . ' = ' . json_encode($data, JSON_UNESCAPED_SLASHES) . ';';
        $this->changed();
        return true;
        $this->changed();
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

    /**
     * Enqueued handles that name a file on disk, mapped to that path. A style
     * registered with a `path` datum is saying it can be inlined; whether it
     * is small enough to be worth inlining is the caller's decision.
     *
     * @return array<string, string> handle => path
     */
    public function withPath(): array
    {
        $out = [];
        foreach ($this->toPrint() as $handle) {
            $path = $this->items[$handle]['data']['path'] ?? null;
            if (is_string($path) && $path !== '') {
                $out[$handle] = $path;
            }
        }
        return $out;
    }

    /** Drops a handle's source so it prints as markup rather than a link. */
    public function unsource(string $handle): void
    {
        if (isset($this->items[$handle])) {
            $this->items[$handle]['src'] = false;
            $this->changed();
        }
    }

    /** Records a handle as printed. */
    public function markDone(string $handle): void
    {
        $this->done[] = $handle;
        $this->changed();
    }

    /** One registered asset, or null. */
    public function item(string $handle): ?array
    {
        return $this->items[$handle] ?? null;
    }

    /**
     * Every registered asset.
     *
     * @return array<string, array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * The handles queued.
     *
     * @return list<string>
     */
    public function queue(): array
    {
        return $this->queue;
    }

    /** Whether these are scripts or styles. */
    public function kind(): string
    {
        return $this->kind;
    }
}
