# `Minn\Extension`

the extension contract and its seams

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Extension`](#extension) | interface | 5 | What a Minn extension is: a class the engine constructs once per request |
| [`Extensions`](#extensions) | final class | 70 | Where the engine looks for the seams an extension registered. The |
| [`Loader`](#loader) | final class | 179 | Finds extensions under wp-content/plugins and wp-content/mu-plugins, and |
| [`Manifest`](#manifest) | final readonly class | 101 | minn.json, read as data. A folder under wp-content/plugins (or |
| [`Registrations`](#registrations) | final readonly class | 39 | Everything the extensions registered for this request, handed from Seams to the runner once. |
| [`SeamRunner`](#seamrunner) | final readonly class | 106 | The engine's side of the seams: what the loader collected, called at the |
| [`Seams`](#seams) | final class | 284 | Where an extension can take part in a request: thirteen typed registrations, |
| [`Shortcodes`](#shortcodes) | final class | 54 | The shortcode syntax in rendered content: [tag], [tag attr="v" flag], |
| [`Tables`](#tables) | final readonly class | 84 | An extension's own tables, from the "tables" its minn.json declares: |

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

Used by: `Minn\Admin\RenderController`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Renderer`, `Minn\Engine`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`

### static `set(Minn\Extension\Seams $seams): void`

Installs the seams for this request.

### static `setBlocks(Minn\Extension\Seams $seams): void`

Installs only the extensions' blocks, for a REST request: its answers
(content.rendered, the editor's previews) draw them, while the filters
and the rest stay with the themed front.

### static `runner(): ?Minn\Extension\SeamRunner`

The engine side of the seams, if any.

Internals: `install()` (private, line 42), `typeArgs()` (private, line 66)


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

Only the blocks and what previews them, for a request that draws content but is no themed page (REST).


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
the request they run in (and who it speaks for), and the outgoing HTTP
its minn.json asks for.
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

### static `statements(Minn\Extension\Manifest $manifest, string $prefix, string $collation = self::FALLBACK_COLLATION): array`

The CREATE TABLE statements an extension declares, keyed by the
prefixed table name: each name must be the extension's slug (dashes
as underscores) followed by "_" and lowercase letters, digits or
underscores, and each line one column or key definition.

- `@return array<string, string>`

### static `marker(Minn\Extension\Manifest $manifest): string`

What is recorded for an extension once its tables are applied: its version and a digest of the declaration.

Internals: `recorded()` (private, line 95), `collation()` (private, line 102)

