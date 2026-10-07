# `Minn\Extension`

the extension contract and its seams

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Extension`](#extension) | interface | 5 | What a Minn extension is: a class the engine constructs once per request |
| [`Extensions`](#extensions) | final class | 14 | Where the engine looks for the seams an extension registered. The |
| [`Loader`](#loader) | final class | 127 | Finds extensions under wp-content/plugins and wp-content/mu-plugins, |
| [`Manifest`](#manifest) | final readonly class | 53 | minn.json, read as data. A folder under wp-content/plugins (or |
| [`Registrations`](#registrations) | final readonly class | 25 | Everything the extensions registered for this request, handed from Seams to the runner once. |
| [`SeamRunner`](#seamrunner) | final readonly class | 79 | The engine's side of the seams: what the loader collected, called at the |
| [`Seams`](#seams) | final class | 99 | Where an extension can take part in a request: eight typed registrations |
| [`Shortcodes`](#shortcodes) | final class | 54 | The shortcode syntax in rendered content: [tag], [tag attr="v" flag], |

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
Before the front has loaded any (REST and the command line never do)
there are none.

Used by: `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Renderer`, `Minn\Engine`, `Minn\Theme\ClassicContent`, `Minn\Theme\PageRenderer`

### static `set(Minn\Extension\Seams $seams): void`

Installs the seams for this request.

### static `runner(): ?Minn\Extension\SeamRunner`

The engine side of the seams, if any.


## Loader

`final class Minn\Extension\Loader` · `public/minn/src/Minn/Extension/Loader.php`

Finds extensions under wp-content/plugins and wp-content/mu-plugins,
decides which are active, autoloads them, and asks each to register.
An extension that throws is logged and skipped; the page still renders.

Used by: `Minn\Cli\MinnCommand`, `Minn\Cli\PluginCommand`, `Minn\Content\PluginState`, `Minn\Engine`, `Minn\Ops\InstalledSoftware`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

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

### `replacements(): array`

Which active WordPress plugin files an extension stands in for. @return array<string, string> plugin file => extension slug

- `@return array<string, string> plugin file => extension slug`

### `register(Minn\Extension\Seams $seams): void`

Registers every active extension's seams.

Internals: `autoload()` (private, line 129)


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
"types": [{"slug": "zz_note", "name": "Notes", "rest_base": "zz-note"}]
}

"replaces" names the WordPress plugin files whose behaviour this
extension stands in for; the extension is active whenever one of them is
in active_plugins, or when its own folder is listed there, or when it is
named in the minn_active_extensions option. "shortcodes" and "blocks"
are the content tokens preflight treats as provided instead of missing.
"types" are extra post types the engine should serve on wp/v2.

Used by: `Minn\Cli\Preflight`, `Minn\Content\PluginState`, `Minn\Extension\Loader`, `Minn\Ops\Packages`, `Minn\Rest\PluginsController`

```php
__construct(string $slug, string $dir, string $name, string $version, string $license, array $replaces, array $autoload, string $extension, string $covers = '', array $shortcodes = array ( ), array $blocks = array ( ), array $types = array ( ))
```
- `@param list<string> $replaces`
- `@param array<string, string> $autoload namespace prefix => directory`
- `@param list<string> $shortcodes`
- `@param list<string> $blocks`
- `@param list<array<string, mixed>> $types`

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

### static `read(string $dir): ?self`

A folder's minn.json, or null when there is none.


## Registrations

`final readonly class Minn\Extension\Registrations` · `public/minn/src/Minn/Extension/Registrations.php`

Everything the extensions registered for this request, handed from Seams to the runner once.

Used by: `Minn\Extension\SeamRunner`, `Minn\Extension\Seams`

```php
__construct(array $blockGates, array $blockFilters, array $shortcodes, array $contentFilters, array $head, array $footer, array $bodyClasses, array $documentFilters, ?Closure $title)
```
- `@param list<Closure> $blockGates`
- `@param list<Closure> $blockFilters`
- `@param array<string, Closure> $shortcodes`
- `@param list<Closure> $contentFilters`
- `@param list<Closure> $head`
- `@param list<Closure> $footer`
- `@param list<string> $bodyClasses`
- `@param list<Closure> $documentFilters`

- readonly `array $blockGates`
- readonly `array $blockFilters`
- readonly `array $shortcodes`
- readonly `array $contentFilters`
- readonly `array $head`
- readonly `array $footer`
- readonly `array $bodyClasses`
- readonly `array $documentFilters`
- readonly `?Closure $title`


## SeamRunner

`final readonly class Minn\Extension\SeamRunner` · `public/minn/src/Minn/Extension/SeamRunner.php`

The engine's side of the seams: what the loader collected, called at the
right moment and in registration order. An extension never sees this
class; it sees Seams, which is only the eight registrations.

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


## Seams

`final class Minn\Extension\Seams` · `public/minn/src/Minn/Extension/Seams.php`

Where an extension can take part in a request: eight typed registrations
and the request they run in. This is the whole surface an extension sees.
The engine calls what was registered through SeamRunner, at the right
moment and in registration order.

Used by: `Minn\Engine`, `Minn\Extension\Extension`, `Minn\Extension\Extensions`, `Minn\Extension\Loader`, `Minn\Extension\SeamRunner`, `Minn\Extension\Shortcodes`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Http\Request $request, Minn\Content\Reader $reader)
```

- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `Minn\Http\Request $request`
- readonly `Minn\Content\Reader $reader`

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

