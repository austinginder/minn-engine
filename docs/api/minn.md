# `Minn`

the front door, the autoloader, the one database door, the REST error

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autoloader`](#autoloader) | final class | 16 | PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php. |
| [`Context`](#context) | final readonly class | 41 | One request, as a value: the database door, who is asking, what they |
| [`Db`](#db) | final class | 203 | The one door to the database. Every query is a prepared statement; the |
| [`Engine`](#engine) | final readonly class | 215 | The engine's front door. An unmodified wp-config.php ends by requiring |
| [`RestError`](#resterror) | final class | 49 | A WordPress-shaped error, thrown from anywhere and rendered once by the |

## Autoloader

`final class Minn\Autoloader` · `public/minn/src/Minn/Autoloader.php`

PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php.

### static `register(): void`

Registers the PSR-4 loader for the Minn namespace.


## Context

`final readonly class Minn\Context` · `public/minn/src/Minn/Context.php`

One request, as a value: the database door, who is asking, what they
may do, and where the site's files are. It is built once at the front
door and handed down, so nothing below has to reach for a global to
learn what request it is answering.

The reader is the one part that differs by surface (REST resolves it
from the nonce-bound caller, the front from the session cookie), so a
context is made with the reader its surface resolved.

Used by: `Minn\Engine`, `Minn\Runtime\Runtime`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, ?Minn\Http\Request $request, Minn\Content\Reader $reader, Minn\Auth\Capabilities $capabilities, string $engineDir, string $absPath, string $version)
```

- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `?Minn\Http\Request $request`
- readonly `Minn\Content\Reader $reader`
- readonly `Minn\Auth\Capabilities $capabilities`
- readonly `string $engineDir` — the minn/ folder: the engine's own files
- readonly `string $absPath` — the site root with a trailing slash, as ABSPATH holds it
- readonly `string $version` — the WordPress release whose contracts the runtime speaks

### `withReader(Minn\Content\Reader $reader): self`

The same context with another reader, for a surface that resolves one later.

### `contentDir(): string`

wp-content under the site root.

### `themesDir(): string`

The themes folder under wp-content.

### `isSecure(): bool`

Whether the request is over HTTPS.


## Db

`final class Minn\Db` · `public/minn/src/Minn/Db.php`

The one door to the database. Every query is a prepared statement; the
placeholder types are derived from the PHP values, so callers pass plain
arrays and never spell out "issd". A parameter that is a list stands for
a list of values: its "?" becomes as many placeholders as the list is
long, so "post_type IN (?)" takes the types themselves.

Used by: `Minn\Admin\ActivityChart`, `Minn\Admin\ActivityFeed`, `Minn\Admin\Dashboard`, `Minn\Admin\Diagnostics`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\V1Controller`, `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\Cookie`, `Minn\Auth\LoginThrottle`, `Minn\Auth\Roles`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\Comments`, `Minn\Content\Menus`, `Minn\Content\PostWriter`, `Minn\Content\Posts`, `Minn\Content\Revisions`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Content\Users`, `Minn\Context`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\Canonical`, `Minn\Front\Feeds`, `Minn\Front\Permalinks`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\Sitemaps`, `Minn\Mail\Mailer`, `Minn\Rest\Api`, `Minn\Rest\MediaController`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Runtime\CommentQuery`, `Minn\Runtime\DbDelta`, `Minn\Runtime\Meta`, `Minn\Runtime\MetaClause`, `Minn\Runtime\Options`, `Minn\Runtime\PostLookup`, `Minn\Runtime\PostQuery`, `Minn\Runtime\Runtime`, `Minn\Runtime\TaxonomyClause`, `Minn\Runtime\TermQuery`, `Minn\Runtime\TermWriter`, `Minn\Runtime\UserQuery`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`, `Minn\Theme\Templates`, `Minn\Theme\UserStyles`

```php
__construct(mysqli $connection, string $prefix)
```


### static `current(): self`

The connection the request being answered speaks through: its
runtime's own, so nothing deep in a render can reach a connection
belonging to another request. With no runtime (the command line, the
unit suite) it is the process's own connection.

This is the one place in the core that asks the runtime anything; the
alternative is a database door threaded through every renderer, and
the seam is here until that threading is done.

### static `shared(): self`

The one connection for this process, opened from wp-config's constants on first use.

### `connection(): mysqli`

The underlying mysqli handle, for the facade's wpdb.

### `escape(string $value): string`

A value escaped for direct interpolation into SQL, for callers that build their own statements.

### `prefix(): string`

The table prefix from wp-config.

### `table(string $name): string`

`posts` becomes `wp_posts`; the prefix comes from wp-config.php.

### `rows(string $sql, array $params = array ( )): array`

Every row of a prepared query, as associative arrays.

- `@return list<array<string, mixed>>`

### `row(string $sql, array $params = array ( )): ?array`

The first row of a prepared query, or null.

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

The id the last INSERT produced.

### `option(string $name): ?string`

One option's raw value, or null when it is unset.

Internals: `placeholders()` (private, line 152), `run()` (private, line 201)


## Engine

`final readonly class Minn\Engine` · `public/minn/src/Minn/Engine.php`

The engine's front door. An unmodified wp-config.php ends by requiring
wp-settings.php, which is the engine's own boot file; it hands off here.
REST is dispatched first (from the path or from ?rest_route=), then the
admin, the login endpoint, and finally the public site.

- const `WP_VERSION` = `'7.1'` — The WordPress release whose contracts the runtime speaks; wp-includes/version.php says the same.

Used by: `Minn\Admin\Packages`, `Minn\Cli\AssetUpdate`, `Minn\Rest\Services`

```php
__construct(string $version, string $engineDir)
```


### `serve(): void`

The front door: answer the request that arrived and send it. This is
the only place the engine touches the world, so everything under it
is a function of a request, callable more than once in a process.

### `answer(Minn\Http\Request $request): Minn\Http\Response`

The response for one request, whatever happens: a database that
cannot be reached, salts that are not set, or a failure anywhere
underneath, each answered in the language the request asked in.

Internals: `restRoute()` (private, line 118), `bootRuntimeForRest()` (private, line 132), `handle()` (private, line 153), `frontPipeline()` (private, line 231)


## RestError

`final class Minn\RestError` · `public/minn/src/Minn/RestError.php` · implements `Stringable`, `Throwable`

A WordPress-shaped error, thrown from anywhere and rendered once by the
kernel: {"code": ..., "message": ..., "data": {"status": ...}}.

Used by: `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\Logs`, `Minn\Admin\OverviewController`, `Minn\Admin\Packages`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\Translations`, `Minn\Admin\Updates`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Engine`, `Minn\Http\Kernel`, `Minn\Media\Uploads`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\Caller`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

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

The reference's 404 for a route nothing answers.

### static `missingParams(array $params): self`

The reference's 400 for required parameters that did not arrive.

- `@param list<string> $params`

### static `bare(string $code, string $message): self`

A statusless core error as REST serves it: HTTP 500 with data null.

### `payload(): array`

The error as the reference's JSON body: code, message, data.

