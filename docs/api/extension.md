# `Minn\Extension`

the extension contract and its seams

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Accounts`](#accounts) | final readonly class | 382 | Accounts for a sign-in surface of an extension's own (a game's players, a |
| [`Extension`](#extension) | interface | 5 | What a Minn extension is: a class the engine constructs once per request |
| [`Extensions`](#extensions) | final class | 71 | Where the engine looks for the seams an extension registered. The |
| [`Loader`](#loader) | final class | 179 | Finds extensions under wp-content/plugins and wp-content/mu-plugins, and |
| [`Manifest`](#manifest) | final readonly class | 101 | minn.json, read as data. A folder under wp-content/plugins (or |
| [`Registrations`](#registrations) | final readonly class | 45 | Everything the extensions registered for this request, handed from Seams to the runner once. |
| [`SeamRunner`](#seamrunner) | final readonly class | 116 | The engine's side of the seams: what the loader collected, called at the |
| [`Seams`](#seams) | final class | 294 | Where an extension can take part in a request: thirteen typed registrations, |
| [`Shortcodes`](#shortcodes) | final class | 54 | The shortcode syntax in rendered content: [tag], [tag attr="v" flag], |
| [`SignedIn`](#signedin) | final readonly class | 8 | A sign-in that worked (Accounts::signIn): who, and the response carrying the session's cookies. |
| [`Tables`](#tables) | final readonly class | 98 | An extension's own tables, from the "tables" its minn.json declares: |

## Accounts

`final readonly class Minn\Extension\Accounts` · `public/minn/src/Minn/Extension/Accounts.php`

Accounts for a sign-in surface of an extension's own (a game's players, a
members' area), from its REST routes: the sign-in, sign-out, registration
and password reset the engine's sign-in page runs, with the same
throttle, hooks, sessions and mail, handed back as values the extension
words itself. A refusal is a Refusal: its code, its message as plain
text, and every code and message in data.notices. A refused sign-in
never says which half was wrong (code incorrect_credentials) unless a
plugin refused it in its own words. They run the sign-in hooks plugins
listen on, so they belong on an extension's REST routes.

- const `REFUSALS` = `array (   0 => 'empty_username',   1 => 'empty_password',   2 => 'invalid_username',   3 => 'invalid_email',   4 => 'incorrect_password',   5 => 'authentication_failed', )` — The authenticate chain's own refusals, which read as one.

Used by: `Minn\Extension\Seams`, `Minn\Extension\SignedIn`

```php
__construct(Minn\Db $db, Minn\Http\Request $request)
```


### `signIn(Minn\Http\Response $response, string $login, string $password): Minn\Extension\SignedIn|Minn\Runtime\Refusal`

Signs in with a login (or email) and password, as the sign-in page
does, for two days ending with the browser: the response with the new
session's cookies and the user, or the refusal (too_many_attempts with
data.retryAfter in seconds, incorrect_credentials, or a plugin's own).

### `signInRemembered(Minn\Http\Response $response, string $login, string $password): Minn\Extension\SignedIn|Minn\Runtime\Refusal`

Signs in as signIn does, remembered: fourteen days, the cookies kept past the browser session.

### `signOut(Minn\Http\Response $response): Minn\Http\Response`

Ends the session the sign-in cookie names, and clears the cookies,
when the request carries that session's REST nonce (the X-WP-Nonce
header or a form's _wpnonce, as the sign-in page's sign-out asks for
its own); with no live session it only clears the cookies. A request
naming a live session without its nonce changes nothing, so a page on
another site, or a navigation to an extension page that signs out,
signs nobody out, cookies included (security sweep 2026-11-12,
finding 1.4, closed by the fix-delta review).

### `registrationOpen(): bool`

Whether the site takes new accounts (users_can_register).

### `register(string $login, string $email, string $password, string $shownAs = '', string $role = ''): Minn\Content\UserRecord|Minn\Runtime\Refusal`

A new account with the password its holder chose, the role asked for
(one the site has, else the site's default role) and, when given, the
name shown for it (display name and nickname; an email-only sign-up
passes a login of its own making), when the site takes new accounts:
the user, or every refusal (registration_closed, unknown_role, role_not_allowed for a role holding more than
the default role does; the name empty, illegal, taken or barred; the
email empty, malformed or taken; no password; whatever plugins add).
The site's address is told; nobody is signed in.

### `registerQuietly(string $login, string $email, string $password, string $shownAs = '', string $role = ''): Minn\Content\UserRecord|Minn\Runtime\Refusal`

A new account as register makes it, with the site's address told nothing (a game's players, say).

### `passwordHash(string $password): string`

A password hashed as an account stores it (slashed, as a sign-in form
sends it, through wp_hash_password), for a sign-up that holds it until
the email is proven: registerQuietlyWithHash makes the account from it.

### `registerQuietlyWithHash(string $login, string $email, string $hash, string $shownAs = '', string $role = ''): Minn\Content\UserRecord|Minn\Runtime\Refusal`

A new account as registerQuietly makes it, its password one that
passwordHash hashed earlier (a sign-up held until its email was
proven), so the password itself never had to be kept: the same checks
and refusals, and a hash that is empty or not a hash refused as no
password (empty_password).

### `signInVerified(Minn\Http\Response $response, Minn\Content\UserRecord $user): Minn\Extension\SignedIn`

Signs in a user the extension vouches for without a password (an
emailed link proved the address), remembered as signInRemembered
signs in: a new session and the three cookies on the response,
wp_login fired. The extension's own proof is the only check, so it
belongs after a single-use secret of the extension's own.

### `mail(string $to, string $subject, string $body, array|string $headers = ''): bool`

Sends an email of the extension's own through the site's mailer
(wp_mail: its filters, an SMTP plugin), headers as wp_mail takes them
(an HTML mail's Content-Type, a From): whether it was accepted.

- `@param string|list<string> $headers`

### `lostPassword(string $login, ?Closure $link = NULL, ?Closure $words = NULL): Minn\Runtime\Refusal|true`

A password reset link mailed to the account a login or email names,
as the lost-password page sends it: true, or the refusal. $link makes
the link from the account's login and the key, for a reset page of the
extension's own (which hands both to resetPassword); without it the
link opens the sign-in page's reset form. $words writes the mail
(subject, body, and optionally headers: an HTML mail's Content-Type, a
From) from the link and the account's display name, for a site whose
people never see a login; without it the mail is the sign-in page's,
which names the login.

- `@param (Closure(string, string): string)|null $link`
- `@param (Closure(string, string): array{0: string, 1: string, 2?: string|list<string>})|null $words`

### `resetPassword(string $login, string $key, string $password): Minn\Content\UserRecord|Minn\Runtime\Refusal`

A new password for the account a reset link named, as the reset form
saves one: the key must still be good (a day, or what
password_reset_expiration says), plugins may refuse the password
(validate_password_reset), and every session of the account ends. The
user, or the refusal (too_many_attempts, invalid_key, expired_key,
empty_password, a plugin's). Nobody is signed in.

### `changePassword(Minn\Http\Response $response, Minn\Content\UserRecord $user, string $current, string $new, ?Closure $words = NULL): Minn\Extension\SignedIn|Minn\Runtime\Refusal`

A new password for a signed-in user, once the current one checks out:
saved as a profile save saves it (wp_update_user, its hooks and its
notice to the account's address, in the extension's words when $words
gives them), every session of the account ended, and a new one on the
response: a sign-in cookie is bound to the password it was made under,
so the old ones stop working anyway. Refusals: too_many_attempts,
incorrect_password (counts against the throttle), empty_password, a
plugin's.

- `@param (Closure(string): array{0: string, 1: string, 2?: string|list<string>})|null $words the notice from the display name`

### `changeEmail(Minn\Content\UserRecord $user, string $current, string $email, ?Closure $words = NULL): Minn\Content\UserRecord|Minn\Runtime\Refusal`

A new email for a signed-in user, once the current password checks
out: saved as a profile save saves it, the old address told (in the
extension's words when $words gives them). Refusals:
too_many_attempts, incorrect_password, invalid_email, email_exists, a
plugin's.

- `@param (Closure(string): array{0: string, 1: string, 2?: string|list<string>})|null $words the notice from the display name`

Internals: `attempt()` (private, line 83), `storedHash()` (private, line 187), `refuseRegistration()` (private, line 220), `made()` (private, line 244), `checkCurrent()` (private, line 367), `worded()` (private, line 386), `refusal()` (private, line 405), `plain()` (private, line 422)


## Extension

`interface Minn\Extension\Extension` · `public/minn/src/Minn/Extension/Extension.php`

What a Minn extension is: a class the engine constructs once per request
and asks to register what it provides. Everything it can touch is on the
Seams object; there is no global state to reach for.

Used by: `Minn\Extension\Loader`

### `register(Minn\Extension\Seams $minn): void`

Registers the extension's seams.


## Extensions

`final class Minn\Extension\Extensions` · `public/minn/src/Minn/Extension/Extensions.php`

Where the engine looks for the seams an extension registered. The
request's runtime holds them, so nothing here keeps state of its own
and a request cannot inherit the extensions of the one before it.
Before the front or a REST request has loaded any (the command line
never does) there are none.

Used by: `Minn\Admin\RenderController`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Renderer`, `Minn\Engine`, `Minn\Rest\RenderedFields`, `Minn\Runtime\Shortcodes`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`

### static `set(Minn\Extension\Seams $seams): void`

Installs the seams for this request.

### static `setBlocks(Minn\Extension\Seams $seams): void`

Installs the extensions' blocks and content rules, for a REST request:
its answers (content.rendered, the editor's previews) draw the blocks
and hide what the page hides, while the page's own parts stay with
the themed front.

### static `runner(): ?Minn\Extension\SeamRunner`

The engine side of the seams, if any.

Internals: `install()` (private, line 43), `typeArgs()` (private, line 67)


## Loader

`final class Minn\Extension\Loader` · `public/minn/src/Minn/Extension/Loader.php`

Finds extensions under wp-content/plugins and wp-content/mu-plugins, and
in the active theme (a theme folder with a minn.json is an extension too,
active because it is the theme: the child, then its parent), decides
which are active, autoloads them, and asks each to register.
An extension that throws is logged and skipped; the page still renders.

Used by: `Minn\Cli\MinnCommand`, `Minn\Cli\PluginCommand`, `Minn\Content\PluginState`, `Minn\Engine`, `Minn\Front\FrontParts`, `Minn\Ops\InstalledSoftware`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`, `Minn\Runtime\Plugins`

```php
__construct(string $contentDir, Minn\Content\Site $site)
```


### `found(): array`

Every extension manifest on disk.

- `@return list<Manifest> every extension on disk`

### `refresh(): void`

Forgets the cached activation list after an option write.

### `active(): array`

The manifests that are active.

- `@return list<Manifest> the extensions this site has switched on`

### `declaredTypes(): array`

Extra post types declared by active extensions, keyed by slug.

- `@return array<string, array<string, mixed>>`

### `declaredTaxonomies(): array`

Extra taxonomies declared by active extensions, keyed by slug.

- `@return array<string, array<string, mixed>>`

### `replacements(): array`

Which active WordPress plugin files an extension stands in for. @return array<string, string> plugin file => extension slug

- `@return array<string, string> plugin file => extension slug`

### `register(Minn\Extension\Seams $seams): void`

Registers every active extension, each on seams of its own; one that
throws adds nothing. An extension's declared tables are settled first
(Tables), so its routes find them on the request that creates them.

Internals: `themeDirs()` (private, line 62), `autoload()` (private, line 184)


## Manifest

`final readonly class Minn\Extension\Manifest` · `public/minn/src/Minn/Extension/Manifest.php`

minn.json, read as data. A folder under wp-content/plugins (or
mu-plugins) with this file is an extension; the same folder may also be
a WordPress plugin, in which case the two share a name and a version.

{
"name": "Block Visibility for Minn",
"version": "1.0.0",
"license": "MIT",
"replaces": ["block-visibility/block-visibility.php"],
"autoload": {"Minn\\Ext\\BlockVisibility\\": "src/"},
"extension": "Minn\\Ext\\BlockVisibility\\Extension",
"shortcodes": ["example_tag"],
"blocks": ["vendor/block"],
"types": [{"slug": "zz_note", "name": "Notes", "rest_base": "zz-note"}],
"taxonomies": [{"slug": "zz_topic", "name": "Topics", "types": ["zz_note"]}],
"tables": {"minn_block_visibility_log": ["id bigint(20) unsigned NOT NULL AUTO_INCREMENT", "PRIMARY KEY  (id)"]},
"needs": {"http": ["https://api.example.com/"]}
}

"replaces" names the WordPress plugin files whose behaviour this
extension stands in for; the extension is active whenever one of them is
in active_plugins, or when its own folder is listed there, or when it is
named in the minn_active_extensions option. "shortcodes" and "blocks"
are the content tokens preflight treats as provided instead of missing.
"types" are extra post types and "taxonomies" extra taxonomies, each
registered on init as a plugin registers its own (DeclaredTypes).
"tables" are the extension's own tables, each a name (the extension's
slug, dashes as underscores, then "_" and more) and its column and key
lines; the engine creates them under the site's table prefix and adds
what a later version declares, never dropping or changing (Tables).
"needs" says what the extension reaches outside the process: "http" is
the URL prefixes its $minn->http may request (true for any public host).

Used by: `Minn\Cli\Preflight`, `Minn\Content\PluginState`, `Minn\Extension\Loader`, `Minn\Extension\Seams`, `Minn\Extension\Tables`, `Minn\Ops\Packages`, `Minn\Rest\PluginsController`

```php
__construct(string $slug, string $dir, string $name, string $version, string $license, array $replaces, array $autoload, string $extension, string $covers = '', array $shortcodes = array ( ), array $blocks = array ( ), array $types = array ( ), array $taxonomies = array ( ), ?array $http = NULL, array $tables = array ( ))
```
- `@param list<string> $replaces`
- `@param array<string, string> $autoload namespace prefix => directory`
- `@param list<string> $shortcodes`
- `@param list<string> $blocks`
- `@param list<array<string, mixed>> $types`
- `@param list<array<string, mixed>> $taxonomies`
- `@param array<string, list<string>> $tables table name without the prefix => its column and key lines`

- readonly `string $slug`
- readonly `string $dir`
- readonly `string $name`
- readonly `string $version`
- readonly `string $license`
- readonly `array $replaces`
- readonly `array $autoload`
- readonly `string $extension`
- readonly `string $covers` — what of the replaced plugin this provides, when not all of it
- readonly `array $shortcodes`
- readonly `array $blocks`
- readonly `array $types`
- readonly `array $taxonomies`
- readonly `?array $http`
- readonly `array $tables`

### static `read(string $dir): ?self`

A folder's minn.json, or null when there is none.

Internals: `tables()` (private, line 111), `http()` (private, line 130)


## Registrations

`final readonly class Minn\Extension\Registrations` · `public/minn/src/Minn/Extension/Registrations.php`

Everything the extensions registered for this request, handed from Seams to the runner once.

Used by: `Minn\Extension\SeamRunner`, `Minn\Extension\Seams`

```php
__construct(array $blockGates, array $blockFilters, array $shortcodes, array $contentFilters, array $head, array $footer, array $bodyClasses, array $documentFilters, ?Closure $title, array $routes = array ( ), array $blocks = array ( ), array $editorStyles = array ( ), array $rest = array ( ))
```
- `@param list<Closure> $blockGates`
- `@param list<Closure> $blockFilters`
- `@param array<string, Closure> $shortcodes`
- `@param list<Closure> $contentFilters`
- `@param list<Closure> $head`
- `@param list<Closure> $footer`
- `@param list<string> $bodyClasses`
- `@param list<Closure> $documentFilters`
- `@param list<object> $routes controllers with #[Route] methods`
- `@param array<string, array{metadata: array<string, mixed>, render: Closure}> $blocks`
- `@param list<string> $editorStyles`
- `@param list<object> $rest controllers with #[Route] methods for the REST API`

- readonly `array $blockGates`
- readonly `array $blockFilters`
- readonly `array $shortcodes`
- readonly `array $contentFilters`
- readonly `array $head`
- readonly `array $footer`
- readonly `array $bodyClasses`
- readonly `array $documentFilters`
- readonly `?Closure $title`
- readonly `array $routes`
- readonly `array $blocks`
- readonly `array $editorStyles`
- readonly `array $rest`

### `onlyBlocks(): self`

What a request that draws content but is no themed page (REST) keeps:
the blocks and what previews them, and everything that decides what
content shows (block gates and filters, shortcodes, content filters),
so content.rendered hides what the page hides. The page's own parts
(head, footer, classes, routes) stay with the themed front.


## SeamRunner

`final readonly class Minn\Extension\SeamRunner` · `public/minn/src/Minn/Extension/SeamRunner.php`

The engine's side of the seams: what the loader collected, called at the
right moment and in registration order. An extension never sees this
class; it sees Seams, which is only the registrations.

Used by: `Minn\Extension\Extensions`, `Minn\Extension\Seams`, `Minn\Runtime\Runtime`

```php
__construct(Minn\Extension\Seams $seams, Minn\Extension\Registrations $registered)
```


### `allowsBlock(Minn\Blocks\Block $block): bool`

Whether every block gate lets a block render.

### `filterBlock(Minn\Blocks\Block $block, string $html): string`

A block's HTML through every block filter.

### `shortcodeNames(): array`

The extensions' shortcode names, which strip_shortcodes takes out too.

- `@return list<string>`

### `filterContent(string $html, Minn\Content\PostRecord $post): string`

Shortcodes, then the content filters, over rendered post content. An
extension's filter is promised the post as a row (contracts/extensions.md),
so a record is handed over as one.

### `title(string $title): string`

The document title through the title filter.

### `head(): string`

What the extensions add to the head.

### `footer(): string`

What the extensions add to the footer.

### `bodyClasses(): array`

The body classes the extensions add.

- `@return list<string>`

### `filterDocument(string $html): string`

The whole document through every document filter.

### `renderBlock(string $name, array $attributes, string $content): ?string`

An extension's block drawn from its attributes and rendered inner blocks; null for a name no extension registered.

### `editorStyles(): array`

The stylesheets the editor's previews load for the extensions, each once.

- `@return list<string>`

### `blockTypes(): array`

Every extension block's block.json, by name.

- `@return array<string, array<string, mixed>>`


## Seams

`final class Minn\Extension\Seams` · `public/minn/src/Minn/Extension/Seams.php`

Where an extension can take part in a request: thirteen typed registrations,
the request they run in (and who it speaks for), the accounts a sign-in
surface of its own may sign in and make, and the outgoing HTTP its
minn.json asks for.
This is the whole surface an extension sees. Each extension registers on
its own (forExtension), and what it registered joins the request's once
its register() returns (adopt). The engine calls what was registered
through SeamRunner, at the right moment and in registration order.

Used by: `Minn\Engine`, `Minn\Extension\Extension`, `Minn\Extension\Extensions`, `Minn\Extension\Loader`, `Minn\Extension\SeamRunner`, `Minn\Extension\Shortcodes`, `Minn\Front\FrontParts`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Http\Request $request, Minn\Content\Reader $reader, ?Minn\Http $http = NULL)
```

- readonly `Minn\Http $http` — Outgoing HTTP, bound to the hosts the extension's needs.http lists; a client that throws when it asked for none.
- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `Minn\Http\Request $request`
- readonly `Minn\Content\Reader $reader`

### `forExtension(Minn\Extension\Manifest $manifest): self`

The seams one extension registers on: this request, and the outgoing
HTTP its manifest grants. A manifest without needs.http gets a client
that throws at its first request, naming the key to add.

### `adopt(self $extension): void`

Takes on what one extension registered, after its register() returned,
so an extension that throws half-way adds nothing. A closure the
engine hands seams to is handed that extension's own.

### `gateBlocks(Closure $gate): void`

Decides whether a block renders at all; the first gate to answer false wins.

### `filterBlocks(Closure $filter): void`

Rewrites a block's rendered markup.

### `shortcode(string $tag, Closure $render): void`

Renders [tag attr="…"]content[/tag] wherever it appears in post content.

### `filterContent(Closure $filter): void`

Rewrites rendered post content (after blocks and shortcodes).

### `head(Closure $render): void`

Markup for the document head (a style, a meta tag, a script).

### `footer(Closure $render): void`

Markup before </body>.

### `bodyClass(string $class): void`

Adds a body class.

### `title(Closure $filter): void`

Replaces the document title; receives the engine's own.

### `filterDocument(Closure $filter): void`

Rewrites the whole themed document before it is sent (what an output buffer did on the reference).

### `routes(object ...$controllers): void`

Answers paths with controllers whose public methods carry #[Route]
attributes, as the engine's own do: each method takes the Request and
the captures it names and returns a Response. They are asked after the
engine's own routes and before the themed front, so an extension can
add pages but never take over one the engine answers.

### `rest(object ...$controllers): void`

Answers REST routes (under /wp-json/, and as ?rest_route=) with
controllers whose public methods carry #[Route] attributes, as the
engine's own REST controllers do: the pattern leaves out /wp-json
("/acme/v1/notes/{id:\d+}"), and the route's Policy is judged by the
engine's REST gate, so the sign-in cookie counts only with its
wp_rest nonce (X-WP-Nonce or _wpnonce) or an application password,
and a refusal is the engine's own (401 rest_not_logged_in, 403
rest_cookie_invalid_nonce, 403 for a missing capability). They are
asked after the engine's routes and before plugins' routes; user()
is the caller the gate judged. Each pattern names a namespace of the
extension's own: one under wp, wp-*, oembed or minn-admin (where
WordPress's and the engine's routes live) throws, which skips the
extension.

### `user(): ?Minn\Content\UserRecord`

The signed-in user this request speaks for, or null for nobody. On a
REST route that is the caller the REST gate accepted (the cookie with
its nonce, or an application password); on a page it is the sign-in
cookie alone, so a page reads with it and never writes on it.

### `accounts(): Minn\Extension\Accounts`

Accounts for a sign-in surface of the extension's own, from its REST
routes: sign in and out, register with a chosen password, mail a
reset link, each as the engine's sign-in page does it (Accounts).

### `block(string $metadata, Closure $render): void`

A block of the extension's own. Its block.json (a folder holding one,
or the file, relative to the extension's folder) is the schema editors
read: name, title, attributes with their types and defaults, parent.
$render draws the block from its attributes (defaults filled in) and
its rendered inner blocks. The engine registers it as a dynamic block,
so it renders on the front, in REST answers and in the editor's
previews, and Minn Admin edits its attributes with a form. A file
that is missing or names no block throws, which skips the extension.

- `@param Closure(array<string, mixed>, string, Seams): string $render`

### `editorStyles(string ...$hrefs): void`

Stylesheets the editor's block previews load, after the theme's own:
what an extension's blocks are drawn against when the theme's
style.css is not all of it. URLs, absolute or from the site root.

### `frontBar(string $document, ?Minn\Content\PostRecord $post = NULL): string`

A whole HTML document an extension built, with the Minn bar a signed-in
editor sees on themed pages: its stylesheet before </head> and the bar
before </body>. Name the post the page shows and the bar's Edit opens
it. Anyone else gets the document unchanged.

### `useFrontBar(?Minn\Front\AdminBar $bar): void`

The engine hands over this request's Minn bar before extensions register. Extensions have no reason to call this.

### `registrations(): Minn\Extension\Registrations`

What was registered, for the engine's runner. Extensions have no reason to call this.


## Shortcodes

`final class Minn\Extension\Shortcodes` · `public/minn/src/Minn/Extension/Shortcodes.php`

The shortcode syntax in rendered content: [tag], [tag attr="v" flag],
[tag]inner[/tag], and [[tag]] as the escaped literal. Unregistered tags
stay as written, which is what the reference shows for a plugin that is
not installed.

Used by: `Minn\Extension\SeamRunner`

### static `apply(string $html, array $registry, Minn\Extension\Seams $seams): string`

Content with the registered shortcodes run.

- `@param array<string, Closure> $registry`

### static `attributes(string $text): array`

A shortcode's attributes parsed, curly quotes included.

- `@return array<string, string> named attributes; bare words keyed by position`


## SignedIn

`final readonly class Minn\Extension\SignedIn` · `public/minn/src/Minn/Extension/SignedIn.php`

A sign-in that worked (Accounts::signIn): who, and the response carrying the session's cookies.

Used by: `Minn\Extension\Accounts`

```php
__construct(Minn\Content\UserRecord $user, Minn\Http\Response $response)
```

- readonly `Minn\Content\UserRecord $user`
- readonly `Minn\Http\Response $response`


## Tables

`final readonly class Minn\Extension\Tables` · `public/minn/src/Minn/Extension/Tables.php`

An extension's own tables, from the "tables" its minn.json declares:
created under the site's table prefix the first time the extension
registers, and brought up to date (missing columns and keys added, as
dbDelta adds them) when its version or its declaration changes. What
was applied is recorded per extension in one option, so a request
after that costs one autoloaded read. Nothing is ever dropped, renamed
or changed: a column a later version leaves out stays, and so does
every table when the extension goes away.

- const `OPTION` = `'minn_extension_tables'` — The option recording, per extension slug, the version and declaration last applied.
- const `FALLBACK_COLLATION` = `'utf8mb4_unicode_520_ci'`
- const `CORE_TABLES` = `array (   0 => 'posts',   1 => 'postmeta',   2 => 'comments',   3 => 'commentmeta',   4 => 'terms',   5 => 'termmeta',   6 => 'term_taxonomy',   7 => 'term_relationships',   8 => 'users',   9 => 'usermeta',   10 => 'options',   11 => 'links',   12 => 'blogs',   13 => 'blogmeta',   14 => 'site',   15 => 'sitemeta',   16 => 'signups',   17 => 'registration_log',   18 => 'blog_versions', )` — WordPress's own tables, unprefixed: no extension declares one, whatever its slug ("term" would own term_relationships).

Used by: `Minn\Extension\Loader`, `Minn\Extension\Manifest`

```php
__construct(Minn\Db $db, Minn\Content\Site $site)
```


### `settle(Minn\Extension\Manifest $manifest): array`

Applies an extension's tables when they changed since the last time,
and records them. Returns what was done (empty when nothing was due),
by table or table.column, in DbDelta's words. A declaration that
names a table outside the extension's own throws, which skips it.

- `@return array<string, string>`

### static `statements(Minn\Extension\Manifest $manifest, string $prefix, string $collation = self::FALLBACK_COLLATION, array $others = array ( )): array`

The CREATE TABLE statements an extension declares, keyed by the
prefixed table name: each name must be the extension's slug (dashes
as underscores) followed by "_" and lowercase letters, digits or
underscores, and each line one column or key definition. No name is
one of WordPress's own tables, or inside the names of another
extension (from $others, the slugs with tables recorded) whose slug
is longer: acme may not declare acme_x_orders while acme-x has tables
(security sweep 2026-11-12, finding 1.5).

- `@param list<string> $others`
- `@return array<string, string>`

### static `marker(Minn\Extension\Manifest $manifest): string`

What is recorded for an extension once its tables are applied: its version and a digest of the declaration.

Internals: `recorded()` (private, line 109), `collation()` (private, line 116)

