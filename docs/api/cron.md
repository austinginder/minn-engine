# `Minn\Cron`

scheduled publishing

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Cron`](#cron) | final readonly class | 175 | The engine's scheduled work: scheduled posts go live when their time |

## Cron

`final readonly class Minn\Cron\Cron` · `public/minn/src/Minn/Cron/Cron.php`

The engine's scheduled work: scheduled posts go live when their time
comes, the cron option's due events fire, expired throttle rows and
transients are swept, and the daily auto-update check runs. Triggered
by wp-cron.php, `wp minn cron`, `minn cron`, or a front request that
finds something due (after its response is sent). One run at a time,
through a short-lived lock option.

Firing the cron option's due hooks needs the booted runtime and the
facade, so it arrives as a closure the caller supplies (from the front
pipeline, or the CLI once it has booted the runtime); with none, only
the engine's own scheduled posts and sweeps run.

- const `LOCK` = `'minn_cron_lock'`
- const `LOCK_TTL` = `60`

Used by: `Minn\Cli\MinnCommand`, `Minn\Engine`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\PostWriter $writer, ?Minn\Ops\Updates $updates = NULL, ?Closure $fireDueEvents = NULL)
```
- `@param ?Closure(): int $fireDueEvents fires the cron option's due hooks and returns how many ran`


### static `create(Minn\Db $db, Minn\Content\Site $site, string $contentDir, ?Closure $fireDueEvents): self`

The one recipe every trigger builds from: the post writer, the
updater over the site's wp-content, and the runtime's firing closure.

- `@param ?Closure(): int $fireDueEvents`

### `run(): array`

Runs every due job; the report lists what happened. @return list<string>

- `@return list<string>`

### `idle(): bool`

True when a hit has nothing to do: no job is due and the housekeeping
(expired transients and throttle rows) ran within the last minute, so
a request repeated as fast as a visitor can send it costs two reads.

### `due(): bool`

True when a scheduled post's time has come or the cron option holds a due event.

Internals: `fireEvents()` (private, line 101), `applyAutoUpdates()` (private, line 119), `postDue()` (private, line 130), `publishDue()` (private, line 145), `sweepTransients()` (private, line 167), `sweepThrottle()` (private, line 181), `lock()` (private, line 186), `unlock()` (private, line 201)

