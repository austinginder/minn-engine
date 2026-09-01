# `Minn\Theme`

the block-theme reader, templates, global styles and the page renderer

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`ArchiveTitle`](#archivetitle) | final class | 52 | The label and name an archive titles itself with: `Category:` around the |
| [`BodyClasses`](#bodyclasses) | final class | 67 | The body-class list a classic theme's body_class() starts from, in the |
| [`ClassicContent`](#classiccontent) | final class | 36 | What a classic theme's the_content() prints: the engine's block pipeline |
| [`ClassicRenderer`](#classicrenderer) | final readonly class | 133 | A whole page from the active classic theme: the reference's PHP template |
| [`ClassicTheme`](#classictheme) | final readonly class | 30 | The active classic (PHP-template) theme on disk. A theme is classic when |
| [`Folder`](#folder) | final readonly class | 70 | A theme folder read from disk: its style.css headers, which folder its templates come from, its screenshot, whether it is a block theme. |
| [`GlobalStyles`](#globalstyles) | final readonly class | 606 | theme.json to CSS. Presets become custom properties on :root and their |
| [`HeadLinks`](#headlinks) | final readonly class | 123 | The links the reference puts in every head: the site and comments |
| [`Hierarchy`](#hierarchy) | final class | 110 | The classic template hierarchy: the candidate file names each template |
| [`MainQueryBridge`](#mainquerybridge) | final readonly class | 65 | Stands the main query for a themed page: a plugin's archive runs through |
| [`PageRenderer`](#pagerenderer) | final readonly class | 204 | A whole page from the active block theme: the template the resolution |
| [`PatternText`](#patterntext) | final class | 197 | Block-theme patterns are PHP files whose only code is a handful of |
| [`TemplateIndex`](#templateindex) | final class | 240 | Every block template and template part the site offers, in the order the |
| [`TemplatePartTheme`](#templateparttheme) | final readonly class | 44 | A template-part block inside a template says which theme's part it means. |
| [`TemplatePatterns`](#templatepatterns) | final readonly class | 87 | A template can name a pattern instead of carrying its blocks, and the |
| [`TemplateRecord`](#templaterecord) | final readonly class | 32 | One block template or template part, whatever it came from: a theme |
| [`TemplateWriter`](#templatewriter) | final readonly class | 91 | Saving and removing block templates. A template the theme ships is never |
| [`Templates`](#templates) | final readonly class | 140 | Which template renders a resolution, and where its markup comes from: |
| [`Theme`](#theme) | final class | 314 | The active block theme on disk, read as data: theme.json, the templates |

## ArchiveTitle

`final class Minn\Theme\ArchiveTitle` · `public/minn/src/Minn/Theme/ArchiveTitle.php`

The label and name an archive titles itself with: `Category:` around the
term, `Author:` around the display name, the date formatted for its
granularity. One source of truth for the block path's query-title and
the classic path's get_the_archive_title(), which used to compute the
same labels separately. Search and post-type archives stay with their
callers: their captured shapes differ between the two paths.

Used by: `Minn\Blocks\Dynamic\Theme\QueryBlocks`

### static `parts(Minn\Front\Resolution $resolution, ?string $dateFormat): array`

- `@return array{string, string} the label (no colon) and the escaped bare name; both empty when the view has none`

### static `compose(string $label, string $name): string`

The reference's prefixed shape: `Category: <span>Uncategorized</span>`.

Internals: `dateParts()` (private, line 51), `taxonomyLabel()` (private, line 68)


## BodyClasses

`final class Minn\Theme\BodyClasses` · `public/minn/src/Minn/Theme/BodyClasses.php`

The body-class list a classic theme's body_class() starts from, in the
reference's order: the query tokens (with the singular and template
tokens spliced in front of the type token), logged-in, the embed and
theme tokens, with the numbered paging tokens re-seated after the embed
token. The body_class filter runs over this list in the facade.

Used by: `Minn\Theme\ClassicRenderer`

### static `classic(Minn\Front\Resolution $resolution, array $coreClasses, ?string $customTemplate, bool $privacyPage, bool $loggedIn, bool $customLogo, bool $embedResponsive, string $themeSlug, ?string $parentSlug, bool $bar): array`

- `@param list<string> $coreClasses`
- `@return list<string>`

Internals: `withSingularTokens()` (private, line 69)


## ClassicContent

`final class Minn\Theme\ClassicContent` · `public/minn/src/Minn/Theme/ClassicContent.php`

What a classic theme's the_content() prints: the engine's block pipeline
(render, password gate, the more-tag teaser in listings), the cached
embeds, then the runtime's shortcodes and the_content filters so plugin
code sees the same hook order the reference runs.

### static `render(Minn\Content\PostRecord $post, ?string $moreLinkText): string`


## ClassicRenderer

`final readonly class Minn\Theme\ClassicRenderer` · `public/minn/src/Minn/Theme/ClassicRenderer.php`

A whole page from the active classic theme: the reference's PHP template
loader. The main query stands, template_redirect fires, the hierarchy
picks a PHP file, template_include filters it, and load_template() runs
it as the response body. The theme prints its own document; the engine
contributes the reference's wp_head defaults (title tag, robots, feed
links, REST and oEmbed discovery, canonical, site icon) as hooks the
theme's wp_head() call fires.

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Theme\ClassicTheme $theme, ?Minn\Theme\Theme $styleTheme, Minn\Theme\MainQueryBridge $bridge, ?Minn\Front\AdminBar $bar = NULL)
```


### static `create(Minn\Db $db, Minn\Theme\ClassicTheme $theme, ?Minn\Theme\Theme $styleTheme, Minn\Front\Permalinks $permalinks, int $perPage, ?Minn\Front\AdminBar $bar = NULL): self`

### `render(Minn\Front\Resolution $resolution, array $coreClasses, string $title): ?string`

Internals: `template()` (private, line 75), `bodyClasses()` (private, line 108), `standTitle()` (private, line 130), `registerHead()` (private, line 140), `registerStyles()` (private, line 146)


## ClassicTheme

`final readonly class Minn\Theme\ClassicTheme` · `public/minn/src/Minn/Theme/ClassicTheme.php`

The active classic (PHP-template) theme on disk. A theme is classic when
it ships no block template index; its templates are PHP files the engine
dispatches through the reference's hierarchy and runs via load_template().
The theme's own functions.php loads through the runtime's symbol gate.

Used by: `Minn\Engine`, `Minn\Theme\ClassicRenderer`

- readonly `string $stylesheet`
- readonly `string $template`
- readonly `string $stylesheetDir`
- readonly `string $templateDir`

### static `active(Minn\Content\Site $site, string $themesDir): ?self`


## Folder

`final readonly class Minn\Theme\Folder` · `public/minn/src/Minn/Theme/Folder.php`

A theme folder read from disk: its style.css headers, which folder its templates come from, its screenshot, whether it is a block theme.

- const `SCREENSHOTS` = `array (   0 => 'png',   1 => 'gif',   2 => 'jpg',   3 => 'jpeg',   4 => 'webp',   5 => 'avif', )`

- readonly `string $root`
- readonly `string $slug`
- readonly `bool $exists`
- readonly `array $headers`
- readonly `string $template`

### static `read(string $root, string $slug, array $labels, ?Closure $reader = NULL): self`

- `@param array<string, string> $labels key => the style.css header label`
- `@param Closure(string, array<string, string>): array<string, string>|null $reader the header reader (the facade's, so header filters apply); the engine's own by default`

### `dir(): string`

### `screenshot(): ?string`

The screenshot file name, or null when the theme has none.

### static `isBlockTheme(array $dirs): bool`

- `@param list<string> $dirs the stylesheet and template directories`

### static `filePath(string $stylesheetDir, string $templateDir, string $file): string`

The first of the stylesheet and template directories that holds the file, or the template directory's path.


## GlobalStyles

`final readonly class Minn\Theme\GlobalStyles` · `public/minn/src/Minn/Theme/GlobalStyles.php`

theme.json to CSS. Presets become custom properties on :root and their
has-* utility classes; settings.layout sizes, root styles, elements, and
per-block styles become rules; the containers, galleries, and style
variations the page actually rendered get their own stylesheets. The
layout rules themselves (flow, constrained, flex, grid, alignments) are
the engine's own, written to the same class hooks.

- const `ELEMENT_SELECTORS` = `array (   'link' => 'a:where(:not(.wp-element-button))',   'heading' => 'h1, h2, h3, h4, h5, h6',   'h1' => 'h1',   'h2' => 'h2',   'h3' => 'h3',   'h4' => 'h4',   'h5' => 'h5',   'h6' => 'h6',   'button' => '.wp-element-button, .wp-block-button__link',   'caption' => '.wp-element-caption, .wp-block-audio figcaption, .wp-block-embed figcaption, .wp-block-gallery figcaption, .wp-block-image figcaption, .wp-block-table figcaption, .wp-block-video figcaption',   'cite' => 'cite', )` — Element selectors in the order the reference prints them, whatever
order theme.json or the saved styles list them in: the heading group
lands before the individual levels, so an h1 line-height beats the
group's. Themes list h1..h6 before heading and would otherwise win.
- const `BLOCK_SELECTORS` = `array (   'core/paragraph' => 'p',   'core/list-item' => '.wp-block-list > li',   'core/button' => '.wp-block-button .wp-block-button__link',   'core/table' => '.wp-block-table > table',   'core/icon' => '.wp-block-icon svg', )` — Blocks whose metadata names a root selector other than .wp-block-{slug}.
- const `STYLESHEET_LESS` = `array (   0 => 'core/block',   1 => 'core/column',   2 => 'core/comments-pagination-next',   3 => 'core/comments-pagination-numbers',   4 => 'core/comments-pagination-previous',   5 => 'core/comments-title',   6 => 'core/freeform',   7 => 'core/home-link',   8 => 'core/html',   9 => 'core/legacy-widget',   10 => 'core/list-item',   11 => 'core/missing',   12 => 'core/more',   13 => 'core/navigation-submenu',   14 => 'core/nextpage',   15 => 'core/page-list-item',   16 => 'core/pattern',   17 => 'core/query-no-results',   18 => 'core/query-pagination-next',   19 => 'core/query-pagination-numbers',   20 => 'core/query-pagination-previous',   21 => 'core/query',   22 => 'core/shortcode',   23 => 'core/social-link',   24 => 'core/tab-panels',   25 => 'core/template-part',   26 => 'core/terms-query',   27 => 'core/widget-group', )` — The reference prints a core block's theme.json styles only when the
page rendered the block with output (a generated excerpt counts), and
attaches them to the block's own stylesheet; these core blocks have
none, so their styles never reach a page. A block from outside core
has no such stylesheet to wait for, so its styles always print.

Used by: `Minn\Admin\RenderController`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, ?array $user = NULL)
```


### `styles(): array`

The theme.json (plus site-editor) styles node, under the engine's own defaults.

### `resolvedStyles(): array`

The same node with every `var:preset|…` token resolved to the custom
property it names, which is the shape a caller reading the styles as
data expects (the CSS writer resolves them on the way out instead).

### `css(): string`

### static `presetList(mixed $presets): array`

A preset list as theme.json writes it is a plain list; as the site
editor saves it, it is keyed by origin (default, theme, custom). The
reference prints the origins in that order, so the two shapes flatten
to one list here.

- `@return list<array>`

### static `fontFamilies(array $settings): array`

- `@return list<array>`

### `fontFaces(): string`

The @font-face rules for every family that declares font files, as
the reference prints them in its own style element: family (quoted
when it has a space), style, weight, display, then the sources with
file:./ resolved against the theme that carries the file and the
format named from the extension. Families without files print
nothing.

Internals: `presets()` (private, line 126), `defaultSlugsFirst()` (private, line 151), `spacingPresets()` (private, line 170), `fontUrl()` (private, line 253), `fontFormat()` (private, line 270), `fluidFontSize()` (private, line 288), `presetProperties()` (private, line 309), `presetClasses()` (private, line 320), `structuralRules()` (private, line 345), `gapRules()` (private, line 365), `rootStyles()` (private, line 381), `elementStyles()` (private, line 395), `blockStyles()` (private, line 424), `withoutEmpty()` (private, line 443), `scopedCss()` (private, line 456), `variationStyles()` (private, line 478), `containerStyles()` (private, line 509), `declarations()` (private, line 534), `ordered()` (private, line 605)


## HeadLinks

`final readonly class Minn\Theme\HeadLinks` · `public/minn/src/Minn/Theme/HeadLinks.php`

The links the reference puts in every head: the site and comments
feeds (plus the archive's own feed), the REST discovery link, the
JSON alternate for the queried object, and the site icon set. The
block path prints them as one run; the classic path prints the same
pieces from the reference's wp_head hooks.

Used by: `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### `all(Minn\Front\Resolution $resolution): string`

### `feedLinks(): string`

### `extraFeedLink(Minn\Front\Resolution $resolution): string`

### `restLink(): string`

No trailing newline: the reference prints the JSON alternate and the RSD link on the same physical line.

### `jsonAlternate(Minn\Front\Resolution $resolution): string`

### `rsdLink(): string`

### `shortlink(Minn\Front\Resolution $resolution): string`

Singular views only; posts and pages alike shortlink as ?p={id}, in the reference's single quotes.

### `icons(): string`

Internals: `iconFileAt()` (private, line 127)


## Hierarchy

`final class Minn\Theme\Hierarchy` · `public/minn/src/Minn/Theme/Hierarchy.php`

The classic template hierarchy: the candidate file names each template
type tries, most specific first, as the reference resolves them. The
facade's get_{type}_template() functions feed these to get_query_template(),
which locates the first candidate the theme (child, then parent) ships.

### static `frontPage(): array`

- `@return list<string>`

### static `home(): array`

- `@return list<string>`

### static `privacyPolicy(): array`

- `@return list<string>`

### static `page(string $custom, string $slug, int $id): array`

- `@return list<string>`

### static `single(string $type, string $slug, string $custom): array`

- `@return list<string>`

### static `attachment(string $mimeType): array`

- `@return list<string>`

### static `term(string $taxonomy, string $slug, int $id): array`

- `@return list<string>`

### static `author(string $nicename, int $id): array`

- `@return list<string>`

### static `archive(array $postTypes): array`

- `@param list<string> $postTypes @return list<string>`


## MainQueryBridge

`final readonly class Minn\Theme\MainQueryBridge` · `public/minn/src/Minn/Theme/MainQueryBridge.php`

Stands the main query for a themed page: a plugin's archive runs through
WP_Query (pre_get_posts shapes it), everything else is the engine's own
listing seeded into the query globals. Then the front-end lifecycle
fires: "wp" with the request object, then template_redirect.

Used by: `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, int $perPage)
```


### `stand(Minn\Front\Resolution $resolution): Minn\Content\Page`

### `perPage(): int`

Internals: `lifecycle()` (private, line 53), `objectTypes()` (private, line 60), `listing()` (private, line 67)


## PageRenderer

`final readonly class Minn\Theme\PageRenderer` · `public/minn/src/Minn/Theme/PageRenderer.php`

A whole page from the active block theme: the template the resolution
maps to, rendered against the main query, inside the document shell the
reference emits (skip link, wp-site-blocks, the skip-link target on the
first main element).

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Blocks\Renderer $renderer, int $perPage, ?Minn\Front\AdminBar $bar = NULL, ?Minn\Theme\MainQueryBridge $bridge = NULL, ?Minn\Theme\HeadLinks $headLinks = NULL)
```


### static `create(Minn\Db $db, Minn\Theme\Theme $theme, Minn\Front\Permalinks $permalinks, int $perPage, ?Minn\Front\AdminBar $bar = NULL): self`

### `bodyClasses(Minn\Front\Resolution $resolution, array $coreClasses): array`

The reference's body-class tokens: the singular and template tokens
sit in front of the type token ("page", "single"), the custom-logo
and embed tokens follow the core set, the paging tokens come after
those, and the theme (and child theme) tokens close the list.

- `@return list<string>`

### `render(Minn\Front\Resolution $resolution, array $coreClasses, string $title): ?string`

Internals: `pluginTemplate()` (private, line 164), `skipLinkTarget()` (private, line 185), `documentTitle()` (private, line 194), `head()` (private, line 215), `headLinks()` (private, line 241)


## PatternText

`final class Minn\Theme\PatternText` · `public/minn/src/Minn/Theme/PatternText.php`

Block-theme patterns are PHP files whose only code is a handful of
echo-and-escape calls around literal strings. The engine never executes
them: it interprets that small text grammar (string literals, "."
concatenation, the i18n and escaping wrappers, the theme URI helper, and
printf with %s) and leaves anything else out. The header comment block
is dropped, and every byte outside the PHP tags is kept as-is.

- const `PASSTHROUGH` = `array (   0 => '__',   1 => '_x',   2 => 'esc_url',   3 => 'wp_kses_post',   4 => 'esc_url_raw', )` — Wrappers whose value is their first argument, unchanged.
- const `ESCAPING` = `array (   0 => 'esc_html__',   1 => 'esc_html_x',   2 => 'esc_attr__',   3 => 'esc_attr_x',   4 => 'esc_html',   5 => 'esc_attr', )` — Wrappers whose value is their first argument with HTML special characters encoded.

Used by: `Minn\Theme\Theme`


### static `render(string $file, string $themeUri): string`

Internals: `statements()` (private, line 45), `printf()` (private, line 72), `expression()` (private, line 83), `term()` (private, line 99), `escape()` (private, line 127), `arguments()` (private, line 133), `stringLiteral()` (private, line 160), `identifier()` (private, line 183), `skipSpace()` (private, line 193)


## TemplateIndex

`final class Minn\Theme\TemplateIndex` · `public/minn/src/Minn/Theme/TemplateIndex.php`

Every block template and template part the site offers, in the order the
reference lists them: the rows the site editor saved first (newest post
date first), then the theme's own files in the order the directory hands
them back, then whatever a plugin registered. A saved row shadows the
theme file of the same slug and reports has_theme_file so the file can
be restored by deleting the row.

- const `TEMPLATE` = `'wp_template'`
- const `PART` = `'wp_template_part'`

Used by: `Minn\Rest\Services`, `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Theme\TemplateWriter`

```php
__construct(Minn\Db $db, Minn\Theme\Theme $theme, Minn\Content\Site $site, ?Minn\Runtime\BlockTemplates $registered = NULL)
```


### `all(string $type): array`

- `@return list<TemplateRecord>`

### `find(string $type, string $id): ?Minn\Theme\TemplateRecord`

Null when the id names another theme, or a slug nothing provides.

### `themeSlug(): string`

### `hasFile(string $type, string $slug): bool`

### `authorText(Minn\Theme\TemplateRecord $record): string`

Who the caller is told made this: the theme's own name for anything
that has a file behind it, the plugin for a registered one, the
author's display name for a row a person saved, and the site's name
when nobody is recorded.

### `originalSource(Minn\Theme\TemplateRecord $record): string`

theme when a file backs it, plugin when one registered it, user when someone saved it, else site.

### static `isCustom(string $slug): bool`

A slug the reference does not name in its default template types is a custom template.

### static `defaults(): array`

- `@return array<string, array{title: string, description: string}>`

### `markup(string $content): string`

Markup as a caller of the REST route sees it: patterns spliced in
where the template only named them, then every template-part block
told which theme it belongs to. A record carries the markup as
stored, because that is what get_block_templates() hands a plugin.

Internals: `savedRows()` (private, line 141), `pluginRows()` (private, line 156), `fromRow()` (private, line 165), `fromFile()` (private, line 191), `fromPlugin()` (private, line 213), `fileTitle()` (private, line 238), `savedArea()` (private, line 247)


## TemplatePartTheme

`final readonly class Minn\Theme\TemplatePartTheme` · `public/minn/src/Minn/Theme/TemplatePartTheme.php`

A template-part block inside a template says which theme's part it means.
The theme's own files usually leave that out, and the reference fills the
active theme in on the way out, for saved rows as much as for files. It
appends the attribute and leaves every other byte of the markup alone.

- const `TAG` = `'<!-- wp:template-part'`

Used by: `Minn\Theme\TemplateIndex`

### static `apply(string $markup, string $theme): string`

Internals: `withTheme()` (private, line 39), `encode()` (private, line 49), `pair()` (private, line 54)


## TemplatePatterns

`final readonly class Minn\Theme\TemplatePatterns` · `public/minn/src/Minn/Theme/TemplatePatterns.php`

A template can name a pattern instead of carrying its blocks, and the
reference hands the caller the blocks: every "wp:pattern" delimiter is
replaced by the pattern's own markup before a template or part is
served. A slug the theme does not know stays exactly as it is, which is
what tells an editor the pattern went missing rather than the block.

- const `TAG` = `'<!-- wp:pattern'`
- const `DEPTH` = `5`

Used by: `Minn\Theme\TemplateIndex`

### static `expand(string $markup, Minn\Theme\Theme $theme, int $depth = 0): string`

Internals: `closerAt()` (private, line 48), `pattern()` (private, line 54), `stamped()` (private, line 78), `isOneBlock()` (private, line 96)


## TemplateRecord

`final readonly class Minn\Theme\TemplateRecord` · `public/minn/src/Minn/Theme/TemplateRecord.php`

One block template or template part, whatever it came from: a theme
file, a row the site editor saved, or a template a plugin registered.
The id every caller uses is "<theme>//<slug>" in all three cases.

Used by: `Minn\Rest\TemplateObject`, `Minn\Rest\TemplatesController`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplateWriter`

```php
__construct(string $theme, string $slug, string $type, string $content, string $title, string $description, string $source, ?string $origin, bool $hasThemeFile, int $author, ?string $modified, ?string $date, int $wpId, string $status = 'publish', ?string $area = NULL, ?string $plugin = NULL)
```

- readonly `string $theme`
- readonly `string $slug`
- readonly `string $type`
- readonly `string $content`
- readonly `string $title`
- readonly `string $description`
- readonly `string $source`
- readonly `?string $origin`
- readonly `bool $hasThemeFile`
- readonly `int $author`
- readonly `?string $modified`
- readonly `?string $date`
- readonly `int $wpId`
- readonly `string $status`
- readonly `?string $area`
- readonly `?string $plugin`

### `id(): string`

### `isPart(): bool`


## TemplateWriter

`final readonly class Minn\Theme\TemplateWriter` · `public/minn/src/Minn/Theme/TemplateWriter.php`

Saving and removing block templates. A template the theme ships is never
touched on disk: editing one writes a wp_template row that shadows the
file, and deleting that row is what "reset to the theme version" means.

Used by: `Minn\Rest\Services`, `Minn\Rest\TemplatesController`

```php
__construct(Minn\Db $db, Minn\Content\PostWriter $writer, Minn\Content\Terms $terms, Minn\Content\Site $site, Minn\Theme\TemplateIndex $index)
```


### `save(Minn\Theme\TemplateRecord $record, array $fields, int $authorId): int`

Writes the site's own copy of a template, creating the row the first
time. Returns the post id.

- `@param array{title?: string, content?: string, description?: string, area?: string} $fields`

### `trash(Minn\Theme\TemplateRecord $record): void`

Moves a site copy to the trash, which hands the theme's file back.

### `destroy(Minn\Theme\TemplateRecord $record): void`

Internals: `setArea()` (private, line 89), `themeTermId()` (private, line 99)


## Templates

`final readonly class Minn\Theme\Templates` · `public/minn/src/Minn/Theme/Templates.php`

Which template renders a resolution, and where its markup comes from:
a wp_template post saved from the site editor (matched by slug and the
theme term) wins over the theme's file. Parts resolve the same way
through wp_template_part.

Used by: `Minn\Admin\RenderController`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Theme\Theme $theme, ?Minn\Runtime\BlockTemplates $registered = NULL)
```


### `forResolution(Minn\Front\Resolution $resolution): ?array`

- `@return array{slug: string, markup: string}|null`

### `template(string $slug): ?string`

### `part(string $slug): ?string`

### `candidates(Minn\Front\Resolution $resolution): array`

The block-theme template hierarchy for each kind of resolution.

- `@return list<string>`

### `userStyles(): ?array`

The site editor's saved global styles for the active theme, when any.

### `customTemplate(int $pageId): ?string`

A page's chosen custom template, from _wp_page_template meta.

Internals: `filtered()` (private, line 71), `hierarchy()` (private, line 95), `saved()` (private, line 146)


## Theme

`final class Minn\Theme\Theme` · `public/minn/src/Minn/Theme/Theme.php`

The active block theme on disk, read as data: theme.json, the templates
and parts directories, and the patterns index. A child theme's files
win and its parent fills in what the child does not define; theme.json
merges the same way, with the child's preset lists replacing the
parent's whole. The engine reads the site's installed theme the way it
reads the site's database; it never runs the theme's PHP.

Used by: `Minn\Admin\RenderController`, `Minn\Admin\V1Controller`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Engine`, `Minn\Rest\Services`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\GlobalStyles`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplatePatterns`, `Minn\Theme\Templates`

```php
__construct(string $slug, string $dir, string $uri, ?Minn\Theme\Theme $parent = NULL)
```

- readonly `string $slug`
- readonly `string $dir`
- readonly `string $uri`
- readonly `?Minn\Theme\Theme $parent`

### static `active(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $themesDir): ?self`

### static `forStyles(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $themesDir): ?self`

The active theme as styling data (theme.json present), whether or not
it is a block theme; the classic renderer prints its presets where the
reference prints a classic theme's global styles.

### `parentSlug(): ?string`

The theme's own name, and the parent's, for body classes.

### `json(): array`

### static `merge(array $base, array $over): array`

Layered theme.json: maps merge key by key, lists (palettes, font
sizes, template parts) replace as a whole.

### `templateFile(string $slug): ?string`

### `partFile(string $slug): ?string`

### `name(): string`

The theme's display name, as the stylesheet header states it; a child
theme answers with its own. Falls back to theme.json's title, then the
folder name.

### `fileSlugs(string $folder): array`

Every template (or part) slug the theme offers, the child's files
first and the parent's after, each in the order the directory hands
them back: the reference walks these directories unsorted and its
template list carries that order through.

- `@return list<string>`

### `customTemplate(string $slug): ?array`

A template title and description the theme declares for a custom template slug.

### `partTitle(string $slug): ?string`

The title theme.json gives a template part, when it names one.

### `partArea(string $slug): string`

The template-part area declared in theme.json (header, footer, or uncategorized).

### `pattern(string $slug): ?string`

A pattern's markup, with its PHP text subset interpreted; null when unknown.

### `patternMeta(string $slug): array`

A pattern's declared Block Types and Categories, the header fields a
plugin reads to tell (say) a header pattern from any other.

- `@return array{blockTypes: list<string>, categories: list<string>}`

### `patternHeader(string $slug): ?array`

The header fields a pattern declares, as the metadata attribute the
reference writes onto the pattern's first block when it splices the
pattern into a template. A field the header omits is omitted here.

- `@return array{patternName: string, name: string, description?: string, categories?: list<string>}|null`

### `styleUri(): ?string`

The theme's stylesheet URL when it ships one; a child's own, else nothing (the parent's is not enqueued for it).

Internals: `at()` (private, line 57), `withStylePartials()` (private, line 87), `partialFiles()` (private, line 114), `safe()` (private, line 145), `htmlFiles()` (private, line 206), `patternIndex()` (private, line 318)

