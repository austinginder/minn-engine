# `Minn`

the front door, the autoloader, the one database door, the REST error

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autoloader`](#autoloader) | final class | 16 | PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php. |
| [`Context`](#context) | final readonly class | 41 | One request, as a value: the database door, who is asking, what they |
| [`Db`](#db) | final class | 238 | The one door to the database. Every query is a prepared statement; the |
| [`Engine`](#engine) | final readonly class | 270 | The engine's front door. An unmodified wp-config.php ends by requiring |
| [`Http`](#http) | final class | 205 | Outgoing HTTP, called straight from anywhere with no import: |
| [`RestError`](#resterror) | final class | 60 | A WordPress-shaped error, thrown from anywhere and rendered once by the |

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

Used by: `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Runtime\Runtime`

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

Used by: `Minn\Admin\ActivityChart`, `Minn\Admin\ActivityFeed`, `Minn\Admin\Dashboard`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\V1Controller`, `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\Cookie`, `Minn\Auth\LoginThrottle`, `Minn\Auth\Roles`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\Comments`, `Minn\Content\Menus`, `Minn\Content\PostSlugs`, `Minn\Content\PostWriter`, `Minn\Content\Posts`, `Minn\Content\Revisions`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Content\Users`, `Minn\Context`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\ArchiveAddresses`, `Minn\Front\AttachmentAddresses`, `Minn\Front\Canonical`, `Minn\Front\Permalinks`, `Minn\Front\QueryMoves`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Mail\Mailer`, `Minn\Ops\Diagnostics`, `Minn\Rest\Api`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\Subjects`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Runtime\CommentQuery`, `Minn\Runtime\DbDelta`, `Minn\Runtime\Meta`, `Minn\Runtime\Options`, `Minn\Runtime\PostLookup`, `Minn\Runtime\PostQuery`, `Minn\Runtime\Runtime`, `Minn\Runtime\TermQuery`, `Minn\Runtime\TermWriter`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`, `Minn\Theme\QueryClasses`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`, `Minn\Theme\Templates`, `Minn\Theme\UserStyles`

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

### `transaction(callable $work): mixed`

Runs a unit of work as one transaction: everything the closure writes
lands, or none of it does. The closure's return value is passed back.

A transaction already open is joined rather than nested, since the
storage engine has no nested transactions; the outermost call is the
one that commits. A table that cannot do transactions (MyISAM, which
no WordPress core table uses) silently keeps each write, which is the
behaviour there was before this existed.

- `@param callable(): T $work`

### `insertId(): int`

The id the last INSERT produced.

### `option(string $name): ?string`

One option's raw value, or null when it is unset.

Internals: `placeholders()` (private, line 154), `run()` (private, line 236)


## Engine

`final readonly class Minn\Engine` · `public/minn/src/Minn/Engine.php`

The engine's front door. An unmodified wp-config.php ends by requiring
wp-settings.php, which is the engine's own boot file; it hands off here.
REST is dispatched first (from the path or from ?rest_route=), then the
admin, the login endpoint, and finally the public site.

- const `WP_VERSION` = `'7.1'` — The WordPress release whose contracts the runtime speaks; wp-includes/version.php says the same.

Used by: `Minn\Cli\Runtime`, `Minn\Ops\Directory`

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

Internals: `restRoute()` (private, line 112), `bootRuntimeForRest()` (private, line 126), `adoptSettledUser()` (private, line 166), `handle()` (private, line 185), `frontPipeline()` (private, line 264)


## Http

`final class Minn\Http` · `public/minn/src/Minn/Http.php`

Outgoing HTTP, called straight from anywhere with no import:

$data = Minn\Http::get('https://api.example.com/status')->json();
Minn\Http::post($url, json: ['email' => $email], timeout: 10)->throw();

Every verb answers with an Exchange (->ok(), ->failed(), ->json(),
->header(), ->cookie(), ->throw(), ->code, ->body) and takes the same
named arguments, each optional:

- `query`: array added to the URL's query string
- `headers`: `name => value` (a list value repeats the header), or "Name: value" lines
- `json`, `form`, `body` (post, put, patch): an array sent as JSON, an array
form-encoded, or a string sent as it is; one of the three, and the first
two set the Content-Type
- `timeout`: seconds for the whole exchange, 5 by default
- `redirects`: how many to follow, 5 by default; 0 hands back the 3xx
- `hosts`: URL prefixes every hop must start with (`['https://api.github.com/']`)
- `private`: hosts allowed to reach a private address. Loopback, LAN,
link-local and cloud-metadata addresses are refused otherwise, at every
hop and after DNS, so a URL someone typed is safe to fetch
- `maxBytes`: the largest body accepted; a longer one fails the exchange
- `userAgent`: Minn/{version} by default

A misspelt argument is an error at the line that has it. Credentials
(Authorization, Cookie) are dropped when a redirect leaves the origin.

- const `REDIRECTS` = `array (   0 => 301,   1 => 302,   2 => 303,   3 => 307,   4 => 308, )`
- const `CREDENTIALS` = `array (   0 => 'authorization',   1 => 'cookie',   2 => 'proxy-authorization', )`

Used by: `Minn\Http\Access`, `Minn\Http\Args`, `Minn\Http\CertificateName`, `Minn\Http\CookieText`, `Minn\Http\Destination`, `Minn\Http\Download`, `Minn\Http\Envelope`, `Minn\Http\Exchange`, `Minn\Http\Failure`, `Minn\Http\Fake`, `Minn\Http\Ipv6`, `Minn\Http\IriParts`, `Minn\Http\Kernel`, `Minn\Http\Location`, `Minn\Http\Matched`, `Minn\Http\Method`, `Minn\Http\Outbound`, `Minn\Http\Policy`, `Minn\Http\Punycode`, `Minn\Http\RawResponse`, `Minn\Http\Request`, `Minn\Http\RequestFailed`, `Minn\Http\RequestsNames`, `Minn\Http\Response`, `Minn\Http\Route`, `Minn\Http\RouteMiss`, `Minn\Http\RouteParams`, `Minn\Http\RouteRow`, `Minn\Http\Router`, `Minn\Http\Subject`, `Minn\Http\Transport`, `Minn\Http\TrustedProxies`, `Minn\Ops\Changelog`, `Minn\Ops\Directory`, `Minn\Ops\Releases`


### static `get(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A GET.

### static `head(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?string $userAgent = NULL): Minn\Http\Exchange`

A HEAD: the status and headers a GET would bring, without the body.

### static `delete(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A DELETE.

### static `post(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A POST with a json:, form: or body: payload.

### static `put(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A PUT with a json:, form: or body: payload.

### static `patch(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A PATCH with a json:, form: or body: payload.

### static `send(Minn\Http\Outbound $request): Minn\Http\Exchange`

Sends exactly what the Outbound says, with none of the verbs' rules
about where a request may go: the door WordPress's own HTTP API comes
through after applying its rules, and the one a fake answers at.

### static `fake(array $answers = array ( )): Minn\Http\Fake`

Answers requests from $answers instead of the network until
restore(), for tests. Keys are URL patterns (* matches anything; the
scheme and query may be left off); values are an array (sent as JSON),
a string (the body), a status code, an Exchange from reply(), or a
closure taking the Outbound and returning one of those. A request no
pattern matches fails as if nothing were listening. The fake answers
wp_remote_*() too, and refuses to start outside the command line.

- `@param array<string, mixed> $answers`

### static `restore(): void`

Puts the network back after fake().

### static `reply(array|string $body = '', int $status = 200, array $headers = array ( )): Minn\Http\Exchange`

A response made by hand, for a fake to answer with. An array body is sent as JSON.

- `@param array<string, string|list<string>> $headers name => value; Set-Cookie values become cookies`

Internals: `follow()` (private, line 146), `deliver()` (private, line 165), `nextHop()` (private, line 175), `outbound()` (private, line 186), `payload()` (private, line 201), `lines()` (private, line 219), `withQuery()` (private, line 238)


## RestError

`final class Minn\RestError` · `public/minn/src/Minn/RestError.php` · implements `Stringable`, `Throwable`

A WordPress-shaped error, thrown from anywhere and rendered once by the
kernel: {"code": ..., "message": ..., "data": {"status": ...}}.

Used by: `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\Translations`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PackageInstaller`, `Minn\Engine`, `Minn\Http\Envelope`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Media\Uploads`, `Minn\Ops\Archive`, `Minn\Ops\CoreStatus`, `Minn\Ops\Directory`, `Minn\Ops\Logs`, `Minn\Ops\Packages`, `Minn\Ops\Updates`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BatchController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\Caller`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\LiveSettings`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\OEmbedController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\Reply`, `Minn\Rest\RestMeta`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`

```php
__construct(string $errorCode, string $message, int $status, array $extra = array ( ), array $topLevel = array ( ), bool $bare = false)
```
- `@param array<string, mixed> $extra keys added beside status inside data (the whole of data when it names status)`
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

### static `invalidParam(string $param, string $message, string $code = 'rest_not_in_enum', mixed $data = NULL): self`

The reference's 400 for one parameter its schema refuses: the message
under params, and under details with the refusal's code and data, as
WP_REST_Request::has_valid_params reports it.

### static `bare(string $code, string $message): self`

A statusless core error as REST serves it: HTTP 500 with data null.

### `payload(): array`

The error as the reference's JSON body: code, message, data.

