# `Minn\Cron`

scheduled publishing

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Cron`](#cron) | final readonly class | 103 | The engine's scheduled work: scheduled posts go live when their time |

## Cron

`final readonly class Minn\Cron\Cron` · `public/minn/src/Minn/Cron/Cron.php`

The engine's scheduled work: scheduled posts go live when their time
comes, and expired throttle rows and transients are swept. Triggered by
wp-cron.php, `wp minn cron`, `minn cron`, or a front request that finds
a post due. One run at a time, through a short-lived lock option.

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\PostWriter $writer, ?Minn\Admin\Updates $updates = NULL)
```

### `run(): array`

Runs every due job; the report lists what happened. @return list<string>

- `@return list<string>`

### `due(): bool`

True when a scheduled post's time has come.

