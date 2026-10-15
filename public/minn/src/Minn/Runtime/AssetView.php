<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A registry's view in the shape plugins read and edit it: the public
 * arrays of WordPress's dependency classes ($wp_scripts->registered, ->queue,
 * ->done), kept current from Runtime\Assets one handle at a time. What a
 * plugin edits in the view is handed back (Runtime\AssetEdits) before the
 * registry changes or reads that handle, and for every handle before the
 * view is rebuilt or printed from. Rebuilding the whole view at each change
 * cost a page that registers hundreds of handles tens of milliseconds.
 */
final class AssetView
{
    /** @var array<string, array{0: mixed, 1: list<string>, 2: mixed, 3: mixed, 4: mixed}> each handle as the view last showed it */
    private array $synced = [];
    /** @var array{0: array<mixed>, 1: array<mixed>} the queue and the printed list as the view last showed them */
    private array $syncedLists = [[], []];
    private bool $reconciling = false;

    public function __construct(private readonly Assets $assets, private readonly object $owner)
    {
        $assets->watch(function (?string $handle): void {
            $this->refresh($handle);
        }, function (string $handle): void {
            $this->handBack($handle);
        });
        $this->sync();
    }

    /** Rebuilds the whole view from the registry, after handing back what a plugin edited in it. */
    public function sync(): void
    {
        $this->push();
        $this->owner->registered = [];
        $this->synced = [];
        foreach (array_keys($this->assets->items()) as $handle) {
            $this->show((string) $handle);
        }
        $this->showLists();
    }

    /** Hands back what a plugin edited in place (every handle, the queue, the printed list). */
    public function push(): void
    {
        $this->reconcile(null);
        $this->handBack(null);
    }

    /** One handle of the view (none: null), the queue and the printed list, kept current after the registry changed them. */
    private function refresh(?string $handle): void
    {
        $this->handBack($handle);
        if ($handle !== null) {
            $this->show($handle);
        }
        $this->showLists();
    }

    /** A handle as the registry holds it, in the view and remembered as shown; gone from both when the registry has none. */
    private function show(string $handle): void
    {
        $item = $this->assets->items()[$handle] ?? null;
        if ($item === null) {
            unset($this->owner->registered[$handle], $this->synced[$handle]);
            return;
        }
        $dependency = Handoff::newDependency($handle, $item['src'], $item['deps'], $item['ver'], $item['extra']);
        $dependency->extra = ['before' => $item['inline']['before'], 'after' => $item['inline']['after']] + $item['data'];
        if (isset($item['translations'])) {
            $dependency->textdomain = $item['translations']['domain'];
            $dependency->translations_path = $item['translations']['path'];
        }
        $this->owner->registered[$handle] = $dependency;
        $this->synced[$handle] = [$dependency->src, array_values((array) $dependency->deps), $dependency->ver, $dependency->args, $dependency->extra['data'] ?? null];
    }

    private function showLists(): void
    {
        $this->owner->queue = $this->assets->queue();
        $this->owner->done = $this->assets->doneList();
        $this->syncedLists = [$this->owner->queue, $this->owner->done];
    }

    /** Hands back what a plugin edited in place for one handle (none: null), and in the queue and the printed list. */
    private function handBack(?string $handle): void
    {
        if ($handle !== null) {
            $this->reconcile($handle);
        }
        if ($this->owner->queue !== $this->syncedLists[0]) {
            $this->assets->setQueue((array) $this->owner->queue);
        }
        if ($this->owner->done !== $this->syncedLists[1]) {
            $this->assets->setDone((array) $this->owner->done);
        }
        $this->syncedLists = [(array) $this->owner->queue, (array) $this->owner->done];
    }

    /** What a plugin did to the view's handles directly, for one handle or (null) every one, handed to the registry. */
    private function reconcile(?string $handle): void
    {
        if ($this->reconciling) {
            return;
        }
        $this->reconciling = true;
        try {
            AssetEdits::apply($this->assets, (array) $this->owner->registered, $this->synced, $handle);
        } finally {
            $this->reconciling = false;
        }
    }
}
