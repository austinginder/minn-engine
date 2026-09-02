# `Minn\Cron`

scheduled publishing

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Cron`](#cron) | final readonly class | 108 | The engine's scheduled work: due scheduled events fire, scheduled posts |

## Cron

`final readonly class Minn\Cron\Cron` · `public/minn/src/Minn/Cron/Cron.php`

The engine's scheduled work: due scheduled events fire, scheduled posts
go live when their time comes, and expired throttle rows and transients
are swept. Triggered by wp-cron.php, `wp minn cron`, `minn cron`, or a
front request that finds a post due. One run at a time, through a
short-lived lock option.

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


### `run(): array`

Runs every due job; the report lists what happened. @return list<string>

- `@return list<string>`

### `due(): bool`

True when a scheduled post's time has come.

Internals: `publishDue()` (private, line 73), `sweepTransients()` (private, line 86), `sweepThrottle()` (private, line 100), `lock()` (private, line 114), `unlock()` (private, line 128)

