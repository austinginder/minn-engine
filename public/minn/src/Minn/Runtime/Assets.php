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
    /** @var list<string> handles asked for before they were registered, queued once they are */
    private array $waiting = [];
    /** @var array<string, array<string, list<string>>>|null the handles the reference registers itself, by kind, with their dependencies */
    private static ?array $defaults = null;

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
        if (in_array($handle, $this->waiting, true)) {
            $this->waiting = array_values(array_diff($this->waiting, [$handle]));
            $this->queue[] = $handle;
        }
        $this->changed();
        return true;
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

    /**
     * Queues an asset for printing (probe placeholders-admin). One that is
     * neither registered nor among the reference's own handles waits, and
     * joins the queue when it is registered; one of the reference's own the
     * engine ships no file for is queued as the reference would queue it.
     */
    public function enqueue(string $handle): void
    {
        if (!$this->registered($handle)) {
            $this->waiting = array_values(array_unique([...$this->waiting, $handle]));
        } elseif (!in_array($handle, $this->queue, true)) {
            $this->queue[] = $handle;
        }
        $this->changed();
    }

    /** Removes an asset from the queue, or from the handles waiting for registration. */
    public function dequeue(string $handle): void
    {
        $this->queue = array_values(array_diff($this->queue, [$handle]));
        $this->waiting = array_values(array_diff($this->waiting, [$handle]));
        $this->changed();
    }

    /** Whether a handle is registered, by the site or as one the reference registers itself (data/default-assets.json). */
    public function registered(string $handle): bool
    {
        return isset($this->items[$handle]) || isset(self::defaults()[$this->kind][$handle]);
    }

    /** A handle's dependencies: as registered, or as the reference registers it. @return list<string> */
    private function depsOf(string $handle): array
    {
        return isset($this->items[$handle]) ? (array) $this->items[$handle]['deps'] : (self::defaults()[$this->kind][$handle] ?? []);
    }

    /** @return array<string, array<string, list<string>>> */
    private static function defaults(): array
    {
        if (self::$defaults === null) {
            $data = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/default-assets.json'), true);
            self::$defaults = ['script' => (array) ($data['scripts'] ?? []), 'style' => (array) ($data['styles'] ?? [])];
        }
        return self::$defaults;
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
            foreach ($this->depsOf($from) as $dep) {
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

    /**
     * The handles printed so far.
     *
     * @return list<string>
     */
    public function doneList(): array
    {
        return array_values(array_unique($this->done));
    }

    /** The printed handles as a plugin left them after editing the view directly. */
    public function setDone(array $done): void
    {
        $this->done = array_values(array_map('strval', $done));
    }

    /** Whether a handle has been printed. */
    public function done(string $handle): bool
    {
        return in_array($handle, $this->done, true);
    }

    /**
     * Whether a handle is an item to attach to: one registered here, or a
     * stylesheet the reference registers itself, held from then on with no
     * file (the engine ships none of those) so what a plugin attaches to it
     * still prints. A core script is not held: its inline code without the
     * library would only fail in the browser.
     */
    private function held(string $handle): bool
    {
        if (isset($this->items[$handle])) {
            return true;
        }
        $deps = self::defaults()['style'][$handle] ?? null;
        return $this->kind === 'style' && $deps !== null && $this->register($handle, false, $deps, false, 'all');
    }

    /** Attaches inline code to an asset. */
    public function addInline(string $handle, string $code, string $position): bool
    {
        if (!$this->held($handle)) {
            $this->changed();
            return false;
        }
        $this->items[$handle]['inline'][$position === 'before' ? 'before' : 'after'][] = $code;
        $this->changed();
        return true;
    }

    /** Attaches a data key to an asset. */
    public function addData(string $handle, string $key, mixed $value): bool
    {
        if (!$this->held($handle)) {
            $this->changed();
            return false;
        }
        $this->items[$handle]['data'][$key] = $value;
        // An asset's inline code is its before/after data, as the reference keeps it: setting either replaces the code.
        if ($key === 'before' || $key === 'after') {
            $this->items[$handle]['inline'][$key] = array_values(array_map('strval', array_filter((array) $value, 'is_scalar')));
        }
        $this->changed();
        return true;
    }

    /** Names the text domain and folder a script's translations come from, and makes the script depend on wp-i18n. */
    public function setTranslations(string $handle, string $domain, string $path): bool
    {
        if (!isset($this->items[$handle])) {
            return false;
        }
        if (!in_array('wp-i18n', $this->items[$handle]['deps'], true)) {
            $this->items[$handle]['deps'][] = 'wp-i18n';
        }
        $this->items[$handle]['translations'] = ['domain' => $domain, 'path' => $path];
        $this->changed();
        return true;
    }

    /** A data key of an asset, or false. */
    public function data(string $handle, string $key): mixed
    {
        $inline = ($key === 'before' || $key === 'after') ? ($this->items[$handle]['inline'][$key] ?? []) : [];
        return $inline !== [] ? $inline : ($this->items[$handle]['data'][$key] ?? false);
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
    }

    /** Every queued handle not yet printed, dependencies first, filtered to the group (footer or not). */
    public function toPrint(?bool $footer = null): array
    {
        $order = $this->ordered($this->queue);
        if ($footer === null) {
            return $order;
        }
        return array_values(array_filter($order, fn (string $h) => (bool) ($this->items[$h]['data']['group'] ?? false) === $footer));
    }

    /**
     * Named handles and everything they depend on, in printing order, whether
     * or not they were queued; what already printed is left out.
     *
     * @param list<string> $handles
     * @return list<string>
     */
    public function toPrintHandles(array $handles): array
    {
        return $this->ordered(array_map('strval', $handles));
    }

    /**
     * @param list<string> $start
     * @return list<string>
     */
    private function ordered(array $start): array
    {
        $order = [];
        // A handle whose dependency is unregistered never prints, nor does anything that depends on it. One of the
        // reference's own handles the engine ships no file for prints nothing itself but stands, its dependencies with it.
        $visit = function (string $handle) use (&$order, &$visit): bool {
            if (in_array($handle, $order, true) || in_array($handle, $this->done, true)) {
                return true;
            }
            if (!isset($this->items[$handle])) {
                if (!$this->registered($handle)) {
                    return false;
                }
                foreach ($this->depsOf($handle) as $dep) {
                    $visit($dep);
                }
                return true;
            }
            foreach ($this->items[$handle]['deps'] as $dep) {
                if (!$visit($dep)) {
                    return false;
                }
            }
            $order[] = $handle;
            return true;
        };
        foreach ($start as $handle) {
            $visit($handle);
        }
        return $order;
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
