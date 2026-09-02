# `Minn\Http`

request, response, routing, and the outgoing client

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Access`](#access) | enum | 13 | Who a route is for. The five answers every route gives, so the |
| [`Args`](#args) | final class | 181 | The parameters a route accepts, as the reference describes them in the |
| [`Client`](#client) | final class | 101 | The engine's outgoing HTTP transport over curl. Redirects are followed by |
| [`Download`](#download) | final class | 106 | A file the engine fetches for itself (a package, a language pack). Every |
| [`Exchange`](#exchange) | final readonly class | 29 | What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error. |
| [`Failure`](#failure) | final class | 166 | What the public sees when the engine cannot answer: a plain page with no |
| [`Kernel`](#kernel) | final readonly class | 36 | The edge. Turns a request into a response through the router and turns |
| [`Method`](#method) | enum | 33 |  |
| [`Outbound`](#outbound) | final readonly class | 47 | One outgoing HTTP request, normalised: the client below needs nothing else. |
| [`Policy`](#policy) | final readonly class | 54 | What a route requires of its caller, as data on the route: the router |
| [`Request`](#request) | final readonly class | 122 | An immutable picture of the incoming request. Built once from the PHP |
| [`Response`](#response) | final readonly class | 101 | What a handler returns. Nothing is written to the client until the |
| [`Route`](#route) | final readonly class | 45 | Declares a handler method as a route. The policy lives here, as |
| [`RouteMiss`](#routemiss) | final class | 3 | A handler declining a request its pattern matched: the router swallows |
| [`Router`](#router) | final class | 79 | Matches a request to a #[Route] on one of the registered handler |
| [`TrustedProxies`](#trustedproxies) | final readonly class | 104 | Which addresses in front of the engine may speak for the client. |

## Access

`enum Minn\Http\Access` · `public/minn/src/Minn/Http/Access.php`

Who a route is for. The five answers every route gives, so the
authorization surface of the engine reads as a list of these.

Cases: `Public`, `SignedIn`, `Cap`, `Floor`, `Own`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Policy`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\SettingsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TypesController`


## Args

`final class Minn\Http\Args` · `public/minn/src/Minn/Http/Args.php`

The parameters a route accepts, as the reference describes them in the
REST index: name => {description, type, ...}. The descriptions and the
enums were captured from the reference, so a client that reads either
index is told the same thing.

A route declares only what it really reads. An argument published here
that the handler ignores would be worse than none at all, because a
client reading the index would build a request around it, so each set
below names the code that consumes it.

- const `CONTEXT` = `array (   'context' =>    array (     'description' => 'Scope under which the request is made; determines fields present in response.',     'type' => 'string',     'enum' =>      array (       0 => 'view',       1 => 'embed',       2 => 'edit',     ),     'default' => 'view',     'required' => false,   ), )` — Read by Rest\Context::of(): which view of a resource is wanted.
- const `FIELDS` = `array (   '_fields' =>    array (     'description' => 'Limit response to specific fields.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ), )` — Read by Rest\Fields::fromQuery(): the subset of fields to return.
- const `EMBED` = `array (   '_embed' =>    array (     'description' => 'Embed the resources linked to the response.',     'type' => 'string',     'required' => false,   ), )` — Read by Rest\Embed: whether linked resources are embedded in the response.
- const `LISTING` = `array (   'page' =>    array (     'description' => 'Current page of the collection.',     'type' => 'integer',     'default' => 1,     'minimum' => 1,     'required' => false,   ),   'per_page' =>    array (     'description' => 'Maximum number of items to be returned in result set.',     'type' => 'integer',     'default' => 10,     'minimum' => 1,     'maximum' => 100,     'required' => false,   ),   'search' =>    array (     'description' => 'Limit results to those matching a string.',     'type' => 'string',     'required' => false,   ),   'include' =>    array (     'description' => 'Limit result set to specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'exclude' =>    array (     'description' => 'Ensure result set excludes specific IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'author' =>    array (     'description' => 'Limit result set to posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'author_exclude' =>    array (     'description' => 'Ensure result set excludes posts assigned to specific authors.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'parent' =>    array (     'description' => 'Limit result set to items with particular parent IDs.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'parent_exclude' =>    array (     'description' => 'Limit result set to all items except those of a particular parent ID.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'default' =>      array (     ),     'required' => false,   ),   'slug' =>    array (     'description' => 'Limit result set to posts with one or more specific slugs.',     'type' => 'array',     'items' =>      array (       'type' => 'string',     ),     'required' => false,   ),   'order' =>    array (     'description' => 'Order sort attribute ascending or descending.',     'type' => 'string',     'default' => 'desc',     'enum' =>      array (       0 => 'asc',       1 => 'desc',     ),     'required' => false,   ),   'orderby' =>    array (     'description' => 'Sort collection by post attribute.',     'type' => 'string',     'default' => 'date',     'enum' =>      array (       0 => 'author',       1 => 'date',       2 => 'id',       3 => 'include',       4 => 'modified',       5 => 'parent',       6 => 'relevance',       7 => 'slug',       8 => 'include_slugs',       9 => 'title',     ),     'required' => false,   ), )` — Read by Rest\ListQuery::fromRequest(): how a collection is paged, narrowed and ordered.
- const `TERMS` = `array (   'categories' =>    array (     'description' => 'Limit result set to items with specific terms assigned in the categories taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'categories_exclude' =>    array (     'description' => 'Limit result set to items except those with specific terms assigned in the categories taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'tags' =>    array (     'description' => 'Limit result set to items with specific terms assigned in the tags taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ),   'tags_exclude' =>    array (     'description' => 'Limit result set to items except those with specific terms assigned in the tags taxonomy.',     'type' => 'array',     'items' =>      array (       'type' => 'integer',     ),     'required' => false,   ), )` — Read by Rest\ListQuery::termFilters(): the taxonomy narrowings a post collection takes.
- const `STATUS` = `array (   'status' =>    array (     'description' => 'Limit result set to posts assigned one or more statuses.',     'type' => 'array',     'items' =>      array (       'enum' =>        array (         0 => 'publish',         1 => 'future',         2 => 'draft',         3 => 'pending',         4 => 'private',         5 => 'trash',         6 => 'auto-draft',         7 => 'inherit',         8 => 'request-pending',         9 => 'request-confirmed',         10 => 'request-failed',         11 => 'request-completed',         12 => 'any',       ),       'type' => 'string',     ),     'default' => 'publish',     'required' => false,   ), )` — Read by Rest\PostsController::serveList(): the statuses a listing may ask for.
- const `FORCE` = `array (   'force' =>    array (     'description' => 'Whether to bypass Trash and force deletion.',     'type' => 'boolean',     'default' => false,     'required' => false,   ), )` — Read by Content\PostWriter: whether a delete bypasses the trash.

Used by: `Minn\Http\Route`, `Minn\Rest\AbilitiesController`, `Minn\Rest\CommentsController`, `Minn\Rest\MediaController`, `Minn\Rest\PostsController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`

### static `merge(array $sets): array`

The sets merged into one map, as a route's endpoint publishes them.

- `@param list<array<string, array<string, mixed>>> $sets`
- `@return array<string, array<string, mixed>>`


## Client

`final class Minn\Http\Client` · `public/minn/src/Minn/Http/Client.php`

The engine's outgoing HTTP transport over curl. Redirects are followed by
curl, so the header lines of every hop arrive in order; only the last
response's block is kept, the way plugin code expects to read it.

### static `get(string $url, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

The common cases, so a one-off request needs no Outbound at the call site.

### static `post(string $url, ?string $body = NULL, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

A POST with an optional body, sent at once.

### static `head(string $url, array $headers = array ( ), float $timeout = 5.0): Minn\Http\Exchange`

A HEAD request, sent at once.

### static `send(Minn\Http\Outbound $request): Minn\Http\Exchange`

Performs one outgoing request over curl and returns the exchange, a transport error included.

Internals: `lastBlock()` (private, line 79)


## Download

`final class Minn\Http\Download` · `public/minn/src/Minn/Http/Download.php`

A file the engine fetches for itself (a package, a language pack). Every
hop of a redirect chain is judged on its own: https only, and when host
prefixes are given, one of them, so a redirect cannot lead a download
off the host the caller trusted. The body is capped, and a body over the
cap fails rather than being truncated.

- const `MAX_HOPS` = `5`

Used by: `Minn\Admin\Translations`, `Minn\Ops\Packages`

### static `https(string $url, int $maxBytes, array $hostPrefixes = array ( ), string $userAgent = 'Minn Engine'): string`

The body at $url, following at most five redirects.

- `@param list<string> $hostPrefixes URL prefixes every hop must start with (none: any https host)`

Internals: `allow()` (private, line 60), `status()` (private, line 77), `location()` (private, line 88), `resolve()` (private, line 100), `host()` (private, line 117)


## Exchange

`final readonly class Minn\Http\Exchange` · `public/minn/src/Minn/Http/Exchange.php`

What came back: the final response's status, headers (repeats as lists), Set-Cookie values, and body, or the transport error.

Used by: `Minn\Http\Client`

```php
__construct(int $code, array $headers, array $cookies, string $body, ?string $error = NULL)
```
- `@param array<string, string|list<string>> $headers`
- `@param list<string> $cookies raw Set-Cookie header values`

- readonly `int $code`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `?string $error`

### `failed(): bool`

Whether the transport failed before any status came back.

### `ok(): bool`

A response arrived and it was a 2xx.

### `json(): mixed`

The body decoded as JSON, or null when it is not JSON.


## Failure

`final class Minn\Http\Failure` · `public/minn/src/Minn/Http/Failure.php`

What the public sees when the engine cannot answer: a plain page with no
detail, while the detail goes to the log. Installed once per request,
it turns display_errors off (unless the site's own WP_DEBUG_DISPLAY asks
for them) and catches the fatal errors PHP would otherwise print.

- const `FATAL` = `4437`

Used by: `Minn\Engine`, `Minn\Runtime\Plugins`


### static `armRecovery(): void`

Opens the window in which a failure is an extension's fault: while
the plugins and the theme's functions file load and the boot actions
fire, code that fails fails for every visitor, so pausing it heals
the site. A failure after that (a route, a shortcode, a template)
answers to one request's input and is never grounds for a pause;
it is logged and the request ends in the error page.

### static `disarmRecovery(): void`

Closes the window armRecovery() opened.

### static `onFatal(callable $recorder): void`

What to do with a fatal beyond showing the page: the engine records
the extension it came from so the next request loads without it.
Installed once the runtime is up, since it needs the database.

- `@param callable(array{type:int,file:string,line:int,message:string}): void $recorder`

### static `install(): void`

Installs the error handlers; detail is shown only under WP_DEBUG_DISPLAY.

### static `report(Throwable $error): Minn\Http\Response`

Logs the cause; the page says only that something went wrong.

### static `reportJson(Throwable $error): Minn\Http\Response`

The same, for a request that asked for JSON: a client that speaks REST
is answered in REST, never handed the HTML error page.

### static `internal(): Minn\Http\Response`

The 500 page.

### static `databaseUnavailable(): Minn\Http\Response`

The 503 page the reference shows when the database cannot be reached.

### static `detailed(string $class, string $message, string $file, int $line): Minn\Http\Response`

The same page with the cause on it, for a site that asked to see
errors. Only ever reached when WP_DEBUG_DISPLAY (or WP_DEBUG) is on:
a site that has not asked never learns this much from a response.

Internals: `discardOutput()` (private, line 82), `note()` (private, line 111), `record()` (private, line 125), `page()` (private, line 167)


## Kernel

`final readonly class Minn\Http\Kernel` · `public/minn/src/Minn/Http/Kernel.php`

The edge. Turns a request into a response through the router and turns
a RestError thrown anywhere underneath into the WordPress error shape.

Used by: `Minn\Engine`

```php
__construct(Minn\Http\Router $router)
```


### `handle(Minn\Http\Request $request): ?Minn\Http\Response`

Routes the request; a thrown failure becomes its response, and null means no route matched.

Internals: `harden()` (private, line 35)


## Method

`enum Minn\Http\Method` · `public/minn/src/Minn/Http/Method.php`

Cases: `Get` = `'GET'`, `Head` = `'HEAD'`, `Post` = `'POST'`, `Put` = `'PUT'`, `Patch` = `'PATCH'`, `Delete` = `'DELETE'`, `Options` = `'OPTIONS'`, `Any` = `'*'`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Redirects`, `Minn\Front\SitemapController`, `Minn\Http\Request`, `Minn\Http\Route`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

### static `fromName(string $name): self`

The verb for a name, GET when the name is unknown.

### `matches(self $declared): bool`

HEAD is served by GET handlers; the kernel drops the body.

### `canonicalRedirects(): bool`

Canonical redirects (trailing slash, pretty-URL mapping, 404 guessing)
run only for reads; every other method renders the URL as typed.


## Outbound

`final readonly class Minn\Http\Outbound` · `public/minn/src/Minn/Http/Outbound.php`

One outgoing HTTP request, normalised: the client below needs nothing else.

Used by: `Minn\Http\Client`

```php
__construct(string $method, string $url, array $headers = array ( ), ?string $body = NULL, float $timeout = 5.0, int $redirects = 5, bool $verifySsl = true, string $userAgent = '', ?string $caInfo = NULL, bool $blocking = true)
```
- `@param list<string> $headers "Name: value" lines`

- readonly `string $method`
- readonly `string $url`
- readonly `array $headers`
- readonly `?string $body`
- readonly `float $timeout`
- readonly `int $redirects`
- readonly `bool $verifySsl`
- readonly `string $userAgent`
- readonly `?string $caInfo`
- readonly `bool $blocking`

### static `get(string $url, array $headers = array ( ), float $timeout = 5.0): self`

A GET.

- `@param list<string> $headers "Name: value" lines`

### static `post(string $url, ?string $body = NULL, array $headers = array ( ), float $timeout = 5.0): self`

A POST with an optional body.

- `@param list<string> $headers "Name: value" lines`

### static `head(string $url, array $headers = array ( ), float $timeout = 5.0): self`

A HEAD.

- `@param list<string> $headers "Name: value" lines`


## Policy

`final readonly class Minn\Http\Policy` · `public/minn/src/Minn/Http/Policy.php`

What a route requires of its caller, as data on the route: the router
enforces it before the handler runs, so a grep over the attributes is
the authorization surface. The two refusals are the reference's: a
caller who is not signed in gets the sign-in code (401), a signed-in
caller who lacks the capability gets the refusal code (403); how a bad
nonce is answered belongs to whoever judges the policy.

Written inline in the attribute with `new`, which is what an attribute
argument allows: `policy: new Policy(Access::Cap, 'upload_files',
refuse: 'rest_cannot_create', message: '...')`.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Route`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\SettingsController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TypesController`

```php
__construct(Minn\Http\Access $access = Minn\Http\Access::Public, ?string $cap = NULL, array $caps = array ( ), ?string $param = NULL, string $signIn = 'rest_not_logged_in', string $signInMessage = 'You are not currently logged in.', string $refuse = 'rest_forbidden', string $message = 'Sorry, you are not allowed to do that.', ?Minn\Http\Policy $edit = NULL)
```
- `@param list<string> $caps further capabilities every one of which the caller must hold`

- readonly `Minn\Http\Access $access`
- readonly `?string $cap`
- readonly `array $caps`
- readonly `?string $param`
- readonly `string $signIn`
- readonly `string $signInMessage`
- readonly `string $refuse`
- readonly `string $message`
- readonly `?Minn\Http\Policy $edit`

### `isPublic(): bool`

Whether the route is open to anyone, so the caller need not be resolved at all.

### `capabilities(): array`

Every capability the policy asks for by name, the one on the object
included, so a listing can show what a route takes.

- `@return list<string>`

### `describe(): string`

The policy in one line, for the route index and the docs.


## Request

`final readonly class Minn\Http\Request` · `public/minn/src/Minn/Http/Request.php`

An immutable picture of the incoming request. Built once from the PHP
globals at the edge; handlers only ever see this object.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Auth\Authenticator`, `Minn\Auth\SignIn`, `Minn\Autoloader`, `Minn\Context`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\AssetsController`, `Minn\Front\Canonical`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Resolver`, `Minn\Front\SitemapController`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Media\Upload`, `Minn\Ops\Diagnostics`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\Caller`, `Minn\Rest\CommentsController`, `Minn\Rest\Context`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\ListQuery`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`, `Minn\Runtime\Runtime`

```php
__construct(Minn\Http\Method $method, string $path, array $query, array $headers, array $cookies, string $body, bool $secure, string $host, array $form = array ( ), array $files = array ( ), string $remoteAddress = '', array $server = array ( ))
```
- `@param array<string, string|array> $query`
- `@param array<string, string> $headers lower-cased names`
- `@param array<string, string> $cookies`
- `@param array<string, mixed> $form decoded form fields, for bodies that are not JSON`
- `@param array<string, array> $files uploaded files, keyed by field`

- readonly `Minn\Http\Method $method`
- readonly `string $path`
- readonly `array $query`
- readonly `array $headers`
- readonly `array $cookies`
- readonly `string $body`
- readonly `bool $secure`
- readonly `string $host`
- readonly `array $form`
- readonly `array $files`
- readonly `string $remoteAddress`
- readonly `array $server` — what the server says about itself: software, protocol, and address; diagnostics only

### static `fromGlobals(): self`

The request PHP received, read once from the superglobals.

### `json(): array`

The JSON body as an array, or the form fields when the body is empty.

### `withPath(string $path): self`

The same request addressed to another path (a REST route carried in ?rest_route=).

### `query(string $key, ?string $default = NULL): ?string`

One query value as a string, or the default when it is absent or not a string.

### `has(string $key): bool`

Whether the query carries this key at all, even empty.

### `header(string $name): ?string`

A request header by case-insensitive name, or null.

### `cookie(string $name): ?string`

A cookie's value, or null.

### `segments(): array`

The path split into non-empty segments: "/a/b/" becomes ["a", "b"].

### `queryStringWithout(string ...$keys): string`

The query string with the given keys removed, ready to append to a redirect.


## Response

`final readonly class Minn\Http\Response` · `public/minn/src/Minn/Http/Response.php`

What a handler returns. Nothing is written to the client until the
kernel calls send(), so a response can be inspected, wrapped, or
replaced on the way out. Work that belongs after the client has its
answer (a cron run a page found due) rides along as afterSend closures.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Auth\AuthCookies`, `Minn\Auth\SignIn`, `Minn\Engine`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Failure`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\Embed`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\Reply`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

```php
__construct(int $status = 200, array $headers = array ( ), string $body = '', array $cookies = array ( ), array $afterSend = array ( ))
```
- `@param array<string, string> $headers`
- `@param list<array{0: string, 1: string, 2: array}> $cookies name, value, setcookie options`
- `@param list<Closure(): void> $afterSend run once the response has been handed to the client`

- readonly `int $status`
- readonly `array $headers`
- readonly `string $body`
- readonly `array $cookies`
- readonly `array $afterSend`

### static `html(string $body, int $status = 200): self`

An HTML response.

### static `json(mixed $payload, int $status = 200): self`

A JSON response with the payload encoded.

### static `redirect(string $location, int $status = 301): self`

A redirect to a location.

### `withHeader(string $name, string $value): self`

The same response with one header set.

### `withoutHeader(string $name): self`

The same response without one header.

### `withCookie(string $name, string $value, array $options): self`

The same response with a cookie to set.

- `@param array<string, mixed> $options setcookie options: expires, path, secure, httponly, samesite`

### `withoutBody(): self`

The same response with an empty body, the HEAD answer.

### `afterSend(Closure $work): self`

The same response with work to run once the client has been answered. @param Closure(): void $work

- `@param Closure(): void $work`

### `send(): void`

Writes the status, the headers, the cookies, and the body, ends the
request for the client, then runs the after-send work in the same
process (the server that can close the connection first does).


## Route

`final readonly class Minn\Http\Route` · `public/minn/src/Minn/Http/Route.php`

Declares a handler method as a route. The policy lives here, as
metadata the router enforces before the handler runs, so the
authorization surface of the engine is a grep away; a route with no
policy is one the ratchet in the style suite counts down. The parameter
sets it reads live here too, so the REST index can tell a client what a
route takes as well as what it requires of them.

Patterns: "/wp/v2/posts/{id}" captures one segment, "{id:\d+}" constrains
it, and "/{path*}" captures the rest of the path (slashes included).

Used by: `Minn\Admin\AppController`, `Minn\Admin\BundleController`, `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Front\AssetsController`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Http\Router`, `Minn\Login\LoginController`, `Minn\Rest\AbilitiesController`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\BlocksController`, `Minn\Rest\CommentsController`, `Minn\Rest\DeclaredPostsController`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\IndexController`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\NavigationController`, `Minn\Rest\PluginsController`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RevisionsController`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\TaxonomiesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\TypesController`, `Minn\Rest\UsersController`

```php
__construct(Minn\Http\Method $method, string $pattern, ?Minn\Http\Policy $policy = NULL, array $args = array ( ))
```
- `@param list<array<string, array<string, mixed>>> $args the parameter sets this route reads, from Args`

- readonly `Minn\Http\Method $method`
- readonly `string $pattern`
- readonly `?Minn\Http\Policy $policy`
- readonly `array $args`

### `arguments(): array`

The parameters this route accepts, as the REST index publishes them.

- `@return array<string, array<string, mixed>>`

### `regex(): string`

The pattern as a regular expression with named captures.


## RouteMiss

`final class Minn\Http\RouteMiss` · `public/minn/src/Minn/Http/RouteMiss.php` · implements `Stringable`, `Throwable`

A handler declining a request its pattern matched: the router swallows
it and goes on to the next route, so a catch-all pattern can leave a
path it does not own to whatever registers after it (a plugin's route
under the same namespace, say) instead of answering "no route" itself.

Used by: `Minn\Http\Router`, `Minn\Rest\DeclaredPostsController`


## Router

`final class Minn\Http\Router` · `public/minn/src/Minn/Http/Router.php`

Matches a request to a #[Route] on one of the registered handler
objects, has the gate judge the route's policy, and invokes the method
with the request plus the named pattern captures. The gate is the one
thing a router cannot be built without: a policy nobody judges is a
route nobody may call.

Used by: `Minn\Engine`, `Minn\Http\Kernel`, `Minn\Rest\Api`, `Minn\Rest\Embed`, `Minn\Rest\EngineRoutes`, `Minn\Rest\IndexController`

```php
__construct(Closure $gate)
```
- `@param Closure(Policy $policy, Request $request, array<string, string> $captures): void $gate throws when the policy refuses the caller`


### `register(object ...$handlers): self`

Registers every #[Route] method of the given handlers; returns the router for chaining.

### `routes(): array`

The registered routes, for an index: pattern => methods. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `table(): array`

Every registered route with its policy, for the docs and the ratchet.

- `@return list<array{method: string, pattern: string, policy: ?Policy, args: array<string, array<string, mixed>>, handler: string}>`

### `dispatch(Minn\Http\Request $request): ?Minn\Http\Response`

Null when nothing matched, so the caller can fall through.


## TrustedProxies

`final readonly class Minn\Http\TrustedProxies` · `public/minn/src/Minn/Http/TrustedProxies.php`

Which addresses in front of the engine may speak for the client.

By default none do: a forwarded header is a request header, and a request
header is the client's word. Behind a proxy or CDN that terminates TLS,
`REMOTE_ADDR` is then the proxy's, which makes every visitor look like one
address (the sign-in throttle would lock them all out together) and every
request look insecure (the auth cookie would lose its Secure flag).

A site says who to believe in `wp-config.php`:

define('MINN_TRUSTED_PROXIES', '10.0.0.0/8, 2400:cb00::/32');
define('MINN_TRUSTED_PROXIES', '*');   // any: only when nothing but the proxy can reach PHP

The client is then the right-most address in `X-Forwarded-For` that is not
itself trusted, which is what a proxy chain appends, so a client that sends
its own `X-Forwarded-For` cannot put an address the engine believes to the
right of the proxy's.

Used by: `Minn\Http\Request`


### static `none(): self`

Nobody in front of the engine speaks for the client.

### static `configured(): self`

What the site declared in wp-config.php, or nobody.

### `trusts(string $address): bool`

Whether the engine believes what this address says about the client.

### `clientAddress(string $remoteAddress, string $forwardedFor): string`

The client's address, given who connected and what was forwarded: the
right-most forwarded address that is not itself a trusted proxy.

### `forwardedSecure(string $remoteAddress, string $forwardedProto): bool`

Whether a forwarded scheme may be believed, and says https.

Internals: `bare()` (private, line 95), `covers()` (private, line 106)

