# `Minn`

the front door, the autoloader, the one database door, the REST error

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autoloader`](#autoloader) | final class | 15 | PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php. |
| [`Db`](#db) | final class | 175 | The one door to the database. Every query is a prepared statement; the |
| [`Engine`](#engine) | final readonly class | 171 | The engine's front door. An unmodified wp-config.php ends by requiring |
| [`RestError`](#resterror) | final class | 43 | A WordPress-shaped error, thrown from anywhere and rendered once by the |

## Autoloader

`final class Minn\Autoloader` · `public/minn/src/Minn/Autoloader.php`

PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php.

### static `register(): void`


## Db

`final class Minn\Db` · `public/minn/src/Minn/Db.php`

The one door to the database. Every query is a prepared statement; the
placeholder types are derived from the PHP values, so callers pass plain
arrays and never spell out "issd". A parameter that is a list stands for
a list of values: its "?" becomes as many placeholders as the list is
long, so "post_type IN (?)" takes the types themselves.

```php
__construct(mysqli $connection, string $prefix)
```

### static `shared(): self`

### `connection(): mysqli`

### `escape(string $value): string`

A value escaped for direct interpolation into SQL, for callers that build their own statements.

### `prefix(): string`

### `table(string $name): string`

`posts` becomes `wp_posts`; the prefix comes from wp-config.php.

### `rows(string $sql, array $params = array ( )): array`

- `@return list<array<string, mixed>>`

### `row(string $sql, array $params = array ( )): ?array`

- `@return array<string, mixed>|null`

### `value(string $sql, array $params = array ( )): string|int|float|null`

The first column of the first row, or null when there is no row.

### `execute(string $sql, array $params = array ( )): int`

Runs a write and returns the affected row count.

### static `expand(string $sql, array $params): array`

Rewrites every "?" whose parameter is a list into that many
placeholders, and flattens the values to match, so "IN (?)" becomes
"IN (?, ?, ?)". An empty list becomes NULL, which nothing is IN, so a
caller that means "everything" when the list is empty says so itself.

- `@param list<mixed> $params`
- `@return array{0: string, 1: list<mixed>}`

### `insertId(): int`

### `option(string $name): ?string`


## Engine

`final readonly class Minn\Engine` · `public/minn/src/Minn/Engine.php`

The engine's front door. An unmodified wp-config.php ends by requiring
wp-settings.php, which is the engine's own boot file; it hands off here.
REST is dispatched first (from the path or from ?rest_route=), then the
admin, the login endpoint, and finally the public site.

- const `WP_VERSION` = `'7.1'` — The WordPress release whose contracts the runtime speaks; wp-includes/version.php says the same.

```php
__construct(string $version, string $engineDir)
```

### `serve(): never`


## RestError

`final class Minn\RestError` · `public/minn/src/Minn/RestError.php` · implements `Stringable`, `Throwable`

A WordPress-shaped error, thrown from anywhere and rendered once by the
kernel: {"code": ..., "message": ..., "data": {"status": ...}}.

```php
__construct(string $errorCode, string $message, int $status, array $extra = array ( ), array $topLevel = array ( ), bool $bare = false)
```
- `@param array<string, mixed> $extra keys added beside status inside data`
- `@param array<string, mixed> $topLevel keys added beside code/message/data`

- readonly `string $errorCode`
- readonly `int $status`
- readonly `array $extra`
- readonly `array $topLevel`

### static `noRoute(): self`

### static `missingParams(array $params): self`

- `@param list<string> $params`

### static `bare(string $code, string $message): self`

A statusless core error as REST serves it: HTTP 500 with data null.

### `payload(): array`

