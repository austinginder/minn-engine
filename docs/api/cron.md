# `Minn\Cron`

scheduled publishing

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Cron`](#cron) | final readonly class | 214 | The engine's scheduled work: scheduled posts go live when their time |

## Cron

`final readonly class Minn\Cron\Cron` · `public/minn/src/Minn/Cron/Cron.php`

The engine's scheduled work: scheduled posts go live when their time
comes, the cron option's due events fire, expired throttle rows and
transients are swept, and once every 24 hours the update service is
asked about Minn, plugins and themes before automatic updates run. Triggered
by wp-cron.php, `wp minn cron`, `minn cron`, or a front request that
finds something due (after its response is sent). One run at a time,
through a short-lived lock option.

Firing the cron option's due hooks needs the booted runtime and the
facade, so it arrives as a closure the caller supplies (from the front
pipeline, or the CLI once it has booted the runtime); with none, only
the engine's own scheduled posts and sweeps run.

- const `LOCK` = `'minn_cron_lock'`
- const `LOCK_TTL` = `60`
- const `DAILY` = `'minn_daily_next'` — When the next daily update pass is due (a timestamp); the slots stay 24 hours apart however late a run is.
- const `DAY` = `86400`

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

True when a scheduled post's time has come, the cron option holds a due event, or the daily update pass is due.

### static `nextSlot(int $slot, int $now): int`

The slot after a daily pass that ran at `$now` for `$slot`: 24 hours on
from the first run, then whole days on from the slot, the first one
still ahead (a site that slept a week checks once, not seven times).

Internals: `fireEvents()` (private, line 110), `dailySlot()` (private, line 132), `daily()` (private, line 149), `postDue()` (private, line 173), `publishDue()` (private, line 188), `sweepTransients()` (private, line 210), `sweepThrottle()` (private, line 224), `lock()` (private, line 229), `unlock()` (private, line 244)

