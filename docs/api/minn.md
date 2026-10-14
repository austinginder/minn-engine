# `Minn`

the front door, the autoloader, the one database door, the REST error

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Autoloader`](#autoloader) | final class | 16 | PSR-4 for the Minn namespace: Minn\Http\Request lives at src/Minn/Http/Request.php. |
| [`Context`](#context) | final readonly class | 41 | One request, as a value: the database door, who is asking, what they |
| [`Db`](#db) | final class | 370 | The one door to the database. Every query is a prepared statement; the |
| [`Engine`](#engine) | final readonly class | 335 | The engine's front door. An unmodified wp-config.php ends by requiring |
| [`Html`](#html) | final class | 133 | HTML, the front door for escaping and cutting markup: plain static calls, |
| [`Http`](#http) | final readonly class | 184 | Outgoing HTTP, a client handed to the code that uses it. An extension |
| [`Json`](#json) | final class | 134 | JSON in and out, for the engine and for code written for Minn: |
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

- const `INCOMPATIBLE_MODES` = `array (   0 => 'NO_ZERO_DATE',   1 => 'ONLY_FULL_GROUP_BY',   2 => 'STRICT_TRANS_TABLES',   3 => 'STRICT_ALL_TABLES',   4 => 'TRADITIONAL',   5 => 'ANSI', )` — The SQL modes WordPress turns off for its session (wpdb's incompatible modes): strictness and the modes that change what queries mean.

Used by: `Minn\Admin\ActivityChart`, `Minn\Admin\ActivityFeed`, `Minn\Admin\Dashboard`, `Minn\Admin\Notifications`, `Minn\Admin\OverviewController`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\StructureController`, `Minn\Admin\V1Controller`, `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Auth\Capabilities`, `Minn\Auth\Cookie`, `Minn\Auth\LoginThrottle`, `Minn\Auth\Roles`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\Comments`, `Minn\Content\Links`, `Minn\Content\Menus`, `Minn\Content\PostSlugs`, `Minn\Content\PostWriter`, `Minn\Content\Posts`, `Minn\Content\Revisions`, `Minn\Content\RowCache`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Content\Users`, `Minn\Context`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Extension\Seams`, `Minn\Front\ArchiveAddresses`, `Minn\Front\AttachmentAddresses`, `Minn\Front\Canonical`, `Minn\Front\Permalinks`, `Minn\Front\QueryMoves`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Mail\Mailer`, `Minn\Ops\Diagnostics`, `Minn\Rest\Api`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\Subjects`, `Minn\Rest\TermObject`, `Minn\Rest\TermsController`, `Minn\Rest\UserObject`, `Minn\Rest\UsersController`, `Minn\Runtime\CommentQuery`, `Minn\Runtime\DbDelta`, `Minn\Runtime\Meta`, `Minn\Runtime\Options`, `Minn\Runtime\PostLookup`, `Minn\Runtime\PostQuery`, `Minn\Runtime\Runtime`, `Minn\Runtime\TermQuery`, `Minn\Runtime\TermWriter`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`, `Minn\Theme\QueryClasses`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`, `Minn\Theme\Templates`, `Minn\Theme\UserStyles`

```php
__construct(mysqli $connection, string $prefix)
```


### static `columnLength(string $type): array|false`

The most a column of a MySQL type holds, as wpdb::get_col_length
answers: characters for char and varchar, bytes for the binary, blob
and text types; false for any other type.

- `@return array{type: string, length: int}|false`

### static `current(): self`

The connection the request being answered speaks through: its
runtime's own, so nothing deep in a render can reach a connection
belonging to another request. With no runtime (the command line, the
unit suite) it is the process's own connection.

This is the one place in the core that asks the runtime anything; the
alternative is a database door threaded through every renderer, and
the seam is here until that threading is done.

### static `shared(): self`

The one connection for this process, opened from wp-config's constants
on first use, its session without the incompatible modes, as
WordPress's own connection is (a plugin's insert that leaves out a text
column then succeeds, as it does there).

### static `sessionModes(string $modes): string`

The session's modes: the server's, less the reference's incompatible
ones and NO_BACKSLASH_ESCAPES, which the engine's own literal escaping
(Query\Sql) relies on being off. Plugins see only the reference's list
(incompatible_sql_modes).

### static `compatibleModes(string $modes, array $incompatible): string`

A comma-separated SQL mode list without the incompatible ones (any case).

- `@param list<string> $incompatible`

### `setModes(string $modes): void`

Sets this session's SQL modes, never NO_BACKSLASH_ESCAPES: the
engine's literals escape quotes with a backslash (Query\Sql), which
that mode would turn into a plain character, so a set_sql_mode()
asking for it gets every other mode it named.

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

### static `onWrite(string $table, Closure $listener): void`

Asks to be told whenever a write statement names a table (Content\RowCache drops what it kept from it).

### static `wrote(string $sql): void`

Tells the listeners of every table a write statement names, whichever
door ran it (this one, or the facade's wpdb for plugin code). A name
that merely contains another (wp_postmeta for posts) is not it.

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

One option's stored value, or null when it is unset; home and siteurl
answer to WP_HOME and WP_SITEURL. Once the runtime boots, the read is
its option store's (the autoloaded list, wp_cache's options group),
so the engine's own reads cost no query each, as get_option()'s don't.

### static `readOptionsThrough(?Closure $source): void`

Where option reads come from from now on: the runtime's option store, or null for the row itself.

### static `configured(string $name): ?string`

What wp-config.php sets for an option, or null: WP_HOME and WP_SITEURL,
when defined, stand for home and siteurl on every read, as on the
reference (_config_wp_home), without a trailing slash.

### `storedOption(string $name): ?string`

One option's row as stored, whatever wp-config.php says, or null when it is unset.

Internals: `placeholders()` (private, line 249), `run()` (private, line 368)


## Engine

`final readonly class Minn\Engine` · `public/minn/src/Minn/Engine.php`

The engine's front door. An unmodified wp-config.php ends by requiring
wp-settings.php, which is the engine's own boot file; it hands off here.
REST is dispatched first (from the path or from ?rest_route=), then the
admin, the login endpoint, and finally the public site.

- const `WP_VERSION` = `'7.1.3'` — The WordPress release whose contracts the runtime speaks; wp-includes/version.php says the same.

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

Internals: `restRoute()` (private, line 122), `bootRuntimeForRest()` (private, line 136), `recheckNonce()` (private, line 179), `adoptSettledUser()` (private, line 201), `markThemedRequest()` (private, line 225), `armRecoveryPause()` (private, line 242), `plain()` (private, line 267), `handle()` (private, line 273), `frontPipeline()` (private, line 335)


## Html

`final class Minn\Html` · `public/minn/src/Minn/Html.php`

HTML, the front door for escaping and cutting markup: plain static calls,
one import away (use Minn\Html;), the way Minn\Http and Minn\Json are.
Escaping at output boundaries goes through here.

- const `SPLIT` = `'/(<(?:!--.*?(?:-->|$)|!\\[CDATA\\[.*?(?:\\]\\]>|$)|[^>]*>?))/s'` — Markup's pieces of HTML: comments and CDATA whole (to the end when unclosed), else from "<" to the next ">".

Used by: `Minn\Admin\AppController`, `Minn\Admin\LanguageChoices`, `Minn\Blocks\Duotone`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Dynamic\Theme\TermBlocks`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Renderer`, `Minn\Blocks\Wrapper`, `Minn\Content\Clickable`, `Minn\Content\Menus`, `Minn\Content\NoteMarkers`, `Minn\Content\PasswordGate`, `Minn\Content\SiteIcon`, `Minn\Content\TermLinks`, `Minn\Front\AdminBar`, `Minn\Front\Calendar`, `Minn\Front\PostNavigation`, `Minn\Front\Renderer`, `Minn\Front\SitemapXml`, `Minn\Front\TermLists`, `Minn\Html\Decoder`, `Minn\Html\Edits`, `Minn\Html\Escaped`, `Minn\Html\Scanner`, `Minn\Html\TagQuery`, `Minn\Html\Tags`, `Minn\Html\TokenMap`, `Minn\Http\Failure`, `Minn\I18n\SiteLanguage`, `Minn\Login\LoginController`, `Minn\Login\LoginForm`, `Minn\Login\LoginHooks`, `Minn\Login\LoginNotices`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\PluginsController`, `Minn\Runtime\Shortcodes`, `Minn\Support\Escape`, `Minn\Support\Sanitize`, `Minn\Support\Text`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`, `Minn\Widgets\WidgetForms`

### static `checked(mixed $checked, mixed $current = true): string`

The checked attribute when the two agree as text (checked), else nothing.

### static `selected(mixed $selected, mixed $current = true): string`

The selected attribute when the two agree as text (selected), else nothing.

### static `chosen(mixed $helper, mixed $current, string $type): string`

An attribute of the given name when the two agree as text, else nothing.

### static `split(string $html): array`

Markup cut into text and HTML pieces, the HTML ones at the odd
positions (wp_html_split): a comment or CDATA section is one piece
whatever it holds, and a "<" in text starts a piece that runs to the
next ">".

- `@return list<string>`

### static `esc(?string $value): string`

Text escaped for HTML.

### static `encodeAll(string $value): string`

Every special and both quotes encoded, references included (an invalid UTF-8 string gives nothing): esc_textarea's encoding.

### static `attr(?string $value): string`

Text escaped for an attribute value.

### static `addClasses(string $html, array $classes, ?string $onClass = NULL): string`

Appends classes to the first element in a fragment (or to the first
element already carrying $onClass), adding a class attribute when
the element has none. Existing classes keep their order.

- `@param list<string> $classes`

### static `stripAllTags(string $text, bool $collapseWhitespace = false): string`

Drops script and style elements with their contents, then every other tag; whitespace runs optionally collapse to one space.

### static `textField(string $text, bool $keepNewlines, Closure $validUtf8, Closure $encodeLessThan): string`

A single line of plain text from user input: tags stripped, control
whitespace folded, percent escapes removed. The caller passes its own
UTF-8 check and less-than encoder so its filters keep applying.

- `@param Closure(string): string $validUtf8 @param Closure(string): string $encodeLessThan`

### static `scriptBody(string $markup): ?string`

The code inside markup that is exactly one bare script element once the
surrounding whitespace is gone, or null for anything else: a tag with
attributes, an empty element, a missing end. The tag names match in any
case and the code is returned as written.


## Http

`final readonly class Minn\Http` · `public/minn/src/Minn/Http.php`

Outgoing HTTP, a client handed to the code that uses it. An extension
finds one on $minn->http, bound to the hosts its minn.json asks for under
needs.http; an engine class takes one in its constructor, the real network
unless a test passes a fake's:

$data = $minn->http->get('https://api.example.com/status')->json();
$this->http->post($url, json: ['email' => $email], timeout: 10)->throw();

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
A fake (new Http\Fake([...])->http()) answers only the client it made,
so a test shares nothing with the rest of the process.

- const `REDIRECTS` = `array (   0 => 301,   1 => 302,   2 => 303,   3 => 307,   4 => 308, )`
- const `CREDENTIALS` = `array (   0 => 'authorization',   1 => 'cookie',   2 => 'proxy-authorization', )`

Used by: `Minn\Admin\Translations`, `Minn\Extension\Seams`, `Minn\Html`, `Minn\Http\Access`, `Minn\Http\Args`, `Minn\Http\CertificateName`, `Minn\Http\CookieText`, `Minn\Http\Destination`, `Minn\Http\DiePage`, `Minn\Http\Download`, `Minn\Http\Envelope`, `Minn\Http\Exchange`, `Minn\Http\Failure`, `Minn\Http\Fake`, `Minn\Http\Ipv6`, `Minn\Http\IriParts`, `Minn\Http\Kernel`, `Minn\Http\LegacyHeaders`, `Minn\Http\Location`, `Minn\Http\Matched`, `Minn\Http\MediaType`, `Minn\Http\Method`, `Minn\Http\NoCache`, `Minn\Http\Outbound`, `Minn\Http\Policy`, `Minn\Http\Punycode`, `Minn\Http\RawResponse`, `Minn\Http\Refusal`, `Minn\Http\Request`, `Minn\Http\RequestFailed`, `Minn\Http\RequestsNames`, `Minn\Http\Response`, `Minn\Http\Route`, `Minn\Http\RouteMiss`, `Minn\Http\RouteParams`, `Minn\Http\RouteRow`, `Minn\Http\Router`, `Minn\Http\StatusText`, `Minn\Http\Subject`, `Minn\Http\Transport`, `Minn\Http\TrustedProxies`, `Minn\Ops\Changelog`, `Minn\Ops\Directory`, `Minn\Ops\EngineChecksums`, `Minn\Ops\EngineUpdate`, `Minn\Ops\Packages`, `Minn\Ops\Releases`, `Minn\Ops\Updates`

```php
__construct(array $within = array ( ), ?Minn\Http\Fake $fake = NULL, ?string $refusal = NULL)
```
The network, or $fake answering in its place. With $within, every hop
of every request must also start with one of those URL prefixes,
whatever a call's own hosts: say (an extension's needs.http).
- `@param list<string> $within`


### static `refused(string $why): self`

A client that throws $why at every call: what an extension holds when its minn.json did not ask for http.

### `get(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A GET.

### `head(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?string $userAgent = NULL): Minn\Http\Exchange`

A HEAD: the status and headers a GET would bring, without the body.

### `delete(string $url, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A DELETE.

### `post(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A POST with a json:, form: or body: payload.

### `put(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A PUT with a json:, form: or body: payload.

### `patch(string $url, ?array $json = NULL, ?array $form = NULL, ?string $body = NULL, array $query = array ( ), array $headers = array ( ), float $timeout = 5.0, int $redirects = 5, array $hosts = array ( ), array $private = array ( ), ?int $maxBytes = NULL, ?string $userAgent = NULL): Minn\Http\Exchange`

A PATCH with a json:, form: or body: payload.

### `send(Minn\Http\Outbound $request): Minn\Http\Exchange`

Sends exactly what the Outbound says, with none of the verbs' rules
about where a request may go: the door WordPress's own HTTP API comes
through after applying its rules. A client bound to hosts refuses it,
since curl follows a sent request's redirects where no rule can see.

Internals: `follow()` (private, line 122), `deliver()` (private, line 142), `throwIfRefused()` (private, line 151), `nextHop()` (private, line 159), `outbound()` (private, line 170), `payload()` (private, line 185), `lines()` (private, line 203), `withQuery()` (private, line 222)


## Json

`final class Minn\Json` · `public/minn/src/Minn/Json.php`

JSON in and out, for the engine and for code written for Minn:

use Minn\Json;

Json::encode($rows);              // {"url":"https://example.com/a","name":"Café"}
Json::decode($text);              // objects as arrays; JsonException when it is not JSON
Json::decode($stored, or: []);    // the fallback, for text that may be empty or broken
Json::file($path);                // the same, read from a file; the error names the file

encode() writes slashes and characters as themselves unless flags say
otherwise (Json::ESCAPED is the form WordPress writes, which REST bodies
keep). A string cut mid-character still encodes: each invalid byte becomes
"?", as wp_json_encode() does it (probe functions-utils). What cannot be
encoded even then (NAN, nesting past the depth) throws a JsonException.

- const `READABLE` = `320` — Slashes and non-ASCII characters written as themselves: encode()'s default.
- const `ESCAPED` = `0` — Slashes and non-ASCII characters escaped, as wp_json_encode() and the REST API write them.

Used by: `Minn\Admin\AdminTypes`, `Minn\Admin\AppController`, `Minn\Admin\PackagesController`, `Minn\Admin\StructureController`, `Minn\Admin\Translations`, `Minn\Auth\Roles`, `Minn\Blocks\BlockStream`, `Minn\Blocks\CoreBlocks`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Parser`, `Minn\Blocks\Serializer`, `Minn\Cli\Installer`, `Minn\Cli\MinnCommand`, `Minn\Cli\OptionCommand`, `Minn\Cli\Preflight`, `Minn\Content\Emoji`, `Minn\Content\PluginState`, `Minn\Extension\Loader`, `Minn\Extension\Manifest`, `Minn\Front\AdminBar`, `Minn\Html`, `Minn\Http`, `Minn\Http\Exchange`, `Minn\Http\Fake`, `Minn\Http\Request`, `Minn\Http\Response`, `Minn\I18n\ScriptTranslations`, `Minn\Mail\MailLog`, `Minn\Mail\MailSettings`, `Minn\Mail\MailerStrings`, `Minn\Media\FileTypes`, `Minn\Ops\Changelog`, `Minn\Ops\Packages`, `Minn\Ops\Releases`, `Minn\Ops\ShapeFiles`, `Minn\Ops\Updates`, `Minn\Rest\ArgCheck`, `Minn\Rest\BatchController`, `Minn\Rest\Embed`, `Minn\Rest\Reply`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\Schema`, `Minn\Rest\SchemaData`, `Minn\Rest\Taxonomies`, `Minn\Rest\Types`, `Minn\Runtime\Assets`, `Minn\Runtime\Constants`, `Minn\Runtime\Interactivity`, `Minn\Runtime\OEmbed`, `Minn\Runtime\Recovery`, `Minn\Runtime\Registry`, `Minn\Runtime\ScriptModules`, `Minn\Runtime\SymbolGap`, `Minn\Runtime\Symbols`, `Minn\Runtime\ThemeSupports`, `Minn\Support\Accents`, `Minn\Support\KsesEntities`, `Minn\Support\KsesProtocols`, `Minn\Theme\GlobalStyles`, `Minn\Theme\StyleLayers`, `Minn\Theme\StylePresets`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplatePartTheme`, `Minn\Theme\TemplatePatterns`, `Minn\Theme\Templates`, `Minn\Theme\Theme`, `Minn\Theme\ThemeStyles`, `Minn\Theme\UserStyles`

### static `encode(mixed $value, int $flags = self::READABLE, int $depth = 512): string`

A value as JSON; invalid UTF-8 is repaired, never a reason to fail.

### static `decode(string $text, mixed $or = NULL): mixed`

The value the text holds, objects as arrays; with `or:` given, that comes back instead of an exception.

### static `file(string $path, mixed $or = NULL): mixed`

The value a JSON file holds, objects as arrays; with `or:` given, a missing or broken file gives that instead.

### static `repair(mixed $value, int $depth = 512): mixed`

A value with every string and key made valid UTF-8, each invalid byte becoming "?", objects read as plain objects of their public properties.

### static `decodeKeepingEmptyObjects(string $json): mixed`

JSON as arrays, but an empty object stays an object: an in-process
REST answer keeps `{}` where the reference's data holds an empty
object (a global style's emptied settings), which decoding to arrays
would turn into `[]`.

Internals: `member()` (private, line 128), `text()` (private, line 137)


## RestError

`final class Minn\RestError` · `public/minn/src/Minn/RestError.php` · implements `Stringable`, `Throwable`

A WordPress-shaped error, thrown from anywhere and rendered once by the
kernel: {"code": ..., "message": ..., "data": {"status": ...}}.

Used by: `Minn\Admin\EditorController`, `Minn\Admin\LanguageController`, `Minn\Admin\OverviewController`, `Minn\Admin\PackagesController`, `Minn\Admin\PreferencesController`, `Minn\Admin\RenderController`, `Minn\Admin\SessionsController`, `Minn\Admin\SystemController`, `Minn\Admin\ThemesController`, `Minn\Admin\Translations`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PackageInstaller`, `Minn\Engine`, `Minn\Http\Envelope`, `Minn\Http\Kernel`, `Minn\Http\Router`, `Minn\Media\Upload`, `Minn\Ops\Archive`, `Minn\Ops\CoreStatus`, `Minn\Ops\Directory`, `Minn\Ops\Logs`, `Minn\Ops\Packages`, `Minn\Ops\Updates`, `Minn\Ops\UpgraderRun`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\ArgCheck`, `Minn\Rest\BatchController`, `Minn\Rest\BlockRendererController`, `Minn\Rest\BlockTypesController`, `Minn\Rest\BlocksController`, `Minn\Rest\Caller`, `Minn\Rest\CommentsController`, `Minn\Rest\Embed`, `Minn\Rest\ExistingPost`, `Minn\Rest\GlobalStylesController`, `Minn\Rest\InstalledThemesController`, `Minn\Rest\LiveSettings`, `Minn\Rest\MediaController`, `Minn\Rest\MenusController`, `Minn\Rest\PluginsController`, `Minn\Rest\PolicyGate`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`, `Minn\Rest\RegisteredFields`, `Minn\Rest\Reply`, `Minn\Rest\RestMeta`, `Minn\Rest\RevisionsController`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimeRoutes`, `Minn\Rest\SearchController`, `Minn\Rest\SettingsController`, `Minn\Rest\SidebarsController`, `Minn\Rest\StatusesController`, `Minn\Rest\TemplatesController`, `Minn\Rest\TermsController`, `Minn\Rest\UsersController`, `Minn\Rest\WidgetsController`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\MenuEvents`, `Minn\Runtime\PostSave`, `Minn\Runtime\TermEvents`, `Minn\Runtime\UserEvents`

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

