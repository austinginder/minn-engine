# `Minn\Theme`

the block-theme reader, templates, global styles and the page renderer

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`ArchiveTitle`](#archivetitle) | final class | 54 | The label and name an archive titles itself with: `Category:` around the |
| [`BodyClasses`](#bodyclasses) | final class | 65 | The body-class list a classic theme's body_class() starts from, in the |
| [`BodyFacts`](#bodyfacts) | final readonly class | 11 | The facts about a page that decide the body classes a classic theme's |
| [`ClassicContent`](#classiccontent) | final class | 39 | What a classic theme's the_content() prints: the engine's block pipeline |
| [`ClassicRenderer`](#classicrenderer) | final readonly class | 145 | A whole page from the active classic theme: the reference's PHP template |
| [`ClassicTheme`](#classictheme) | final readonly class | 31 | The active classic (PHP-template) theme on disk. A theme is classic when |
| [`EmbedRenderer`](#embedrenderer) | final readonly class | 32 | A post's embed page (its /embed/ address, or ?embed= on it), the card |
| [`FeedHeaders`](#feedheaders) | final class | 50 | The headers a feed is sent with, as the reference's send_headers sends |
| [`Folder`](#folder) | final readonly class | 77 | A theme folder read from disk: its style.css headers, which folder its templates come from, its screenshot, whether it is a block theme. |
| [`FrontLifecycle`](#frontlifecycle) | final class | 96 | WordPress's front-end request steps around the main query, as WP::main |
| [`GlobalStyles`](#globalstyles) | final readonly class | 555 | theme.json to CSS. Presets become custom properties on :root and their |
| [`HeadLinks`](#headlinks) | final readonly class | 133 | The links the reference puts in every head: the site and comments |
| [`Hierarchy`](#hierarchy) | final class | 146 | The classic template hierarchy: the candidate file names each template |
| [`MainQueryBridge`](#mainquerybridge) | final readonly class | 138 | Stands the main query for a themed page and runs the front-end steps |
| [`NotModified`](#notmodified) | final class | 3 | Raised once a reader's copy of a feed has been found current and the |
| [`PageRenderer`](#pagerenderer) | final readonly class | 205 | A whole page from the active block theme: the template the resolution |
| [`PatternText`](#patterntext) | final class | 198 | Block-theme patterns are PHP files whose only code is a handful of |
| [`Printed`](#printed) | final class | 3 | Raised once a WordPress handler has printed a whole response (a sitemap, |
| [`StylePresets`](#stylepresets) | final class | 174 | The preset side of theme.json: the colour, gradient, font-size, |
| [`StyleSettings`](#stylesettings) | final class | 82 | The settings and styles nodes as wp/v2/global-styles reports them: |
| [`TemplateHierarchy`](#templatehierarchy) | final class | 75 | The templates a block theme falls back through for a template slug, as |
| [`TemplateIndex`](#templateindex) | final class | 250 | Every block template and template part the site offers, in the order the |
| [`TemplatePartTheme`](#templateparttheme) | final readonly class | 45 | A template-part block inside a template says which theme's part it means. |
| [`TemplatePatterns`](#templatepatterns) | final readonly class | 88 | A template can name a pattern instead of carrying its blocks, and the |
| [`TemplateRecord`](#templaterecord) | final readonly class | 34 | One block template or template part, whatever it came from: a theme |
| [`TemplateWriter`](#templatewriter) | final readonly class | 92 | Saving and removing block templates. A template the theme ships is never |
| [`Templates`](#templates) | final readonly class | 175 | Which template renders a resolution, and where its markup comes from: |
| [`Theme`](#theme) | final class | 334 | The active block theme on disk, read as data: theme.json, the templates |
| [`ThemeStyles`](#themestyles) | final readonly class | 115 | The active theme's global styles as wp/v2/global-styles/themes/{stylesheet} |
| [`UserStyles`](#userstyles) | final readonly class | 106 | The site editor's saved global styles: one wp_global_styles post per |

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

The archive title's prefix and title for a resolution.

- `@return array{string, string} the label (no colon) and the escaped bare name; both empty when the view has none`

### static `compose(string $label, string $name): string`

The reference's prefixed shape: `Category: <span>Uncategorized</span>`.

Internals: `dateParts()` (private, line 53), `taxonomyLabel()` (private, line 70)


## BodyClasses

`final class Minn\Theme\BodyClasses` · `public/minn/src/Minn/Theme/BodyClasses.php`

The body-class list a classic theme's body_class() starts from, in the
reference's order: the query tokens (with the singular and template
tokens spliced in front of the type token), logged-in, the embed and
theme tokens, with the numbered paging tokens re-seated after the embed
token. The body_class filter runs over this list in the facade.

Used by: `Minn\Theme\ClassicRenderer`

### static `classic(Minn\Front\Resolution $resolution, array $coreClasses, ?string $customTemplate, string $themeSlug, ?string $parentSlug, Minn\Theme\BodyFacts $facts): array`

The body classes a classic theme's page carries.

- `@param list<string> $coreClasses`
- `@return list<string>`

Internals: `withSingularTokens()` (private, line 67)


## BodyFacts

`final readonly class Minn\Theme\BodyFacts` · `public/minn/src/Minn/Theme/BodyFacts.php`

The facts about a page that decide the body classes a classic theme's
page carries beyond the view tokens: privacy policy page, a signed-in
reader, a custom logo, responsive embeds, and the Minn bar.

Used by: `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`

```php
__construct(bool $privacyPage = false, bool $loggedIn = false, bool $customLogo = false, bool $embedResponsive = true, bool $bar = false)
```

- readonly `bool $privacyPage`
- readonly `bool $loggedIn`
- readonly `bool $customLogo`
- readonly `bool $embedResponsive`
- readonly `bool $bar`


## ClassicContent

`final class Minn\Theme\ClassicContent` · `public/minn/src/Minn/Theme/ClassicContent.php`

What a classic theme's the_content() prints: the engine's block pipeline
(render, password gate, the more-tag teaser in listings), the cached
embeds, then the runtime's shortcodes and the_content filters so plugin
code sees the same hook order the reference runs.

### static `render(Minn\Content\PostRecord $post, ?string $moreLinkText): string`

A post's content as the classic loop prints it, with the more link.


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

A classic renderer over the database door.

### `render(Minn\Front\Resolution $resolution, array $coreClasses, string $title): ?string`

The page for a resolution through the theme's PHP templates.

### `themeClasses(Minn\Front\Resolution $resolution, array $coreClasses): array`

The body-class tokens before plugins filter them (get_body_class filters them). @return list<string>

- `@return list<string>`

Internals: `template()` (private, line 87), `bodyClasses()` (private, line 126), `standTitle()` (private, line 143), `registerHead()` (private, line 153), `registerStyles()` (private, line 159)


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

The active classic theme, or null under a block theme.


## EmbedRenderer

`final readonly class Minn\Theme\EmbedRenderer` · `public/minn/src/Minn/Theme/EmbedRenderer.php`

A post's embed page (its /embed/ address, or ?embed= on it), the card
another site's iframe shows, as the reference prints it: the main query
stood with the embed asked for, then the embed template (the theme's
own embed-{type}.php or embed.php, else the engine's theme-compat one)
through template_include, with embed_head, embed_content,
embed_content_meta and embed_footer. An address that embeds nothing
gets the 404 card.

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Theme\MainQueryBridge $bridge)
```


### static `asked(Minn\Http\Request $request, Minn\Front\Resolution $resolution): bool`

Whether a request for this resolution asks for its embed page.

### `render(Minn\Front\Resolution $resolution, array $bodyClasses): Minn\Http\Response`

The embed page, under the status the request's main query settled on;
the body classes the theme's page would carry (the Minn bar's aside).

- `@param list<string> $bodyClasses`


## FeedHeaders

`final class Minn\Theme\FeedHeaders` · `public/minn/src/Minn/Theme/FeedHeaders.php`

The headers a feed is sent with, as the reference's send_headers sends
them (suite feed-hooks): the feed type's content type, and when the site
last changed (its posts, and its comments too for a comments feed) as
Last-Modified with an ETag over it; a reader whose copy carries that
date or tag is told the copy is current.

- const `FORMAT` = `'D, d M Y H:i:s'`

Used by: `Minn\Theme\FrontLifecycle`

### static `for(array $vars, ?Minn\Http\Request $request): array`

The headers, and whether the reader's copy is current.

- `@param array<string, mixed> $vars the request's query variables`
- `@return array{0: array<string, string>, 1: bool}`

Internals: `carriesComments()` (private, line 50)


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

A theme folder with its style.css headers.

- `@param array<string, string> $labels key => the style.css header label`
- `@param Closure(string, array<string, string>): array<string, string>|null $reader the header reader (the facade's, so header filters apply); the engine's own by default`

### `dir(): string`

The folder's path.

### `screenshot(): ?string`

The screenshot file name, or null when the theme has none.

### static `isBlockTheme(array $dirs): bool`

Whether any of the folders ships a block template index.

- `@param list<string> $dirs the stylesheet and template directories`

### static `filePath(string $stylesheetDir, string $templateDir, string $file): string`

The first of the stylesheet and template directories that holds the file, or the template directory's path.


## FrontLifecycle

`final class Minn\Theme\FrontLifecycle` · `public/minn/src/Minn/Theme/FrontLifecycle.php`

WordPress's front-end request steps around the main query, as WP::main
runs them (front lifecycle trace): parse the request (do_parse_request
may take it over; query_vars names the public variables, a plugin's own
read from the query string; request filters the result; parse_request
follows), the main query, the 404 decision (pre_handle_404 first), the
globals, and the headers (wp_headers, the status, send_headers). The
engine has already resolved the URL; these steps tell plugins about it
in the reference's order and let them change what the query asks.

Used by: `Minn\Theme\MainQueryBridge`

### static `parseRequest(WP $wp, array $vars, array $given, ?Closure $parse = NULL): bool`

Parses the request: false when a plugin's do_parse_request took the
parse over (the main query then does not run).

- `@param array<string, mixed> $vars the variables the engine resolved`
- `@param array<string, mixed> $given the query string and form, where a plugin's own variables are read`
- `@param (\Closure(list<string>): array<string, mixed>)|null $parse the reference's parse vars for the public vars, when the request is known`

### static `handle404(WP_Query $query, bool $notFound): void`

The 404 decision: a plugin's pre_handle_404 may make it; otherwise a
request the engine could not resolve is a 404 (the query says so, the
status and no-cache headers follow), as is a page past the end of a
listing (the query found nothing on it); anything else is a 200.

### static `sendHeaders(WP $wp): void`

The headers the reference sends for the request: no caching for a
signed-in reader or a 404, the content type, a pingback address for a
single post that takes pings; then wp_headers, the status an error
variable names, and send_headers.


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

The global stylesheet from theme.json and the user's styles, as a page prints it: only the core blocks it rendered.

### `stylesheet(array $types): string`

The stylesheet wp_get_global_stylesheet answers (probe editor-styles),
by type: the custom properties, the styles (every block's, rendered or
not), the preset classes; in that order.

- `@param list<string> $types`

### `fontFaces(): string`

The @font-face rules for every family that declares font files, as
the reference prints them in its own style element: family (quoted
when it has a space), style, weight, display, then the sources with
file:./ resolved against the theme that carries the file and the
format named from the extension. Families without files print
nothing.

Internals: `parts()` (private, line 129), `fontUrl()` (private, line 185), `structuralRules()` (private, line 203), `gapRules()` (private, line 223), `rootStyles()` (private, line 239), `elementStyles()` (private, line 253), `blockStyles()` (private, line 292), `selectorsOf()` (private, line 320), `byFeature()` (private, line 335), `withoutEmpty()` (private, line 362), `scopedCss()` (private, line 382), `scope()` (private, line 406), `append()` (private, line 419), `variationStyles()` (private, line 425), `containerStyles()` (private, line 456), `declarations()` (private, line 481), `ordered()` (private, line 554)


## HeadLinks

`final readonly class Minn\Theme\HeadLinks` · `public/minn/src/Minn/Theme/HeadLinks.php`

The links the reference puts in every head: the site and comments
feeds (plus the archive's own feed), the REST discovery link, the
JSON alternate for the queried object, and the site icon set. The
block path prints them as one run; the classic path prints the same
pieces from the reference's wp_head hooks.

Used by: `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Content\SiteIcon $icon, Minn\Front\Permalinks $permalinks)
```


### `engineStylesheet(): string`

The link to the engine's own block stylesheet.

### `all(Minn\Front\Resolution $resolution): string`

Every head link for a resolution.

### `feedLinks(): string`

The site and comments feed links.

### static `siteFeeds(array $args): string`

The site's two feed links as feed_links prints them: the posts feed
and the comments feed (each unless its feed_links_show_* filter says
no), titled with the caller's separator and words.

- `@param array<string, mixed> $args`

### `extraFeedLink(Minn\Front\Resolution $resolution): string`

The feed link a single or an archive adds.

### `restLink(): string`

No trailing newline: the reference prints the JSON alternate and the RSD link on the same physical line.

### `jsonAlternate(Minn\Front\Resolution $resolution): string`

The wp/v2 alternate link for the resolution.

### `rsdLink(): string`

The RSD link.

### `shortlink(Minn\Front\Resolution $resolution): string`

Singular views only; posts and pages alike shortlink as ?p={id}, in the reference's single quotes.

### `icons(): string`

The site icon links: the 32 and 192 pixel icons, the Apple touch icon, and the tile image.


## Hierarchy

`final class Minn\Theme\Hierarchy` · `public/minn/src/Minn/Theme/Hierarchy.php`

The classic template hierarchy: the candidate file names each template
type tries, most specific first, as the reference resolves them. The
facade's get_{type}_template() functions feed these to get_query_template(),
which locates the first candidate the theme (child, then parent) ships.

### static `frontPage(): array`

The front page templates.

- `@return list<string>`

### static `home(): array`

The home templates.

- `@return list<string>`

### static `privacyPolicy(): array`

The privacy policy templates.

- `@return list<string>`

### static `page(string $custom, string $slug, int $id): array`

The page templates, custom first.

- `@return list<string>`

### static `single(string $type, string $slug, string $custom): array`

The single templates, custom first.

- `@return list<string>`

### static `attachment(string $mimeType): array`

The attachment templates by mime type.

- `@return list<string>`

### static `term(string $taxonomy, string $slug, int $id): array`

The term archive templates.

- `@return list<string>`

### static `author(string $nicename, int $id): array`

The author archive templates.

- `@return list<string>`

### static `archive(array $postTypes): array`

The post type archive templates.

- `@param list<string> $postTypes @return list<string>`


## MainQueryBridge

`final readonly class Minn\Theme\MainQueryBridge` · `public/minn/src/Minn/Theme/MainQueryBridge.php`

Stands the main query for a themed page and runs the front-end steps
around it as WP::main does (FrontLifecycle): the request parsed, the main
query through WP_Query (so pre_get_posts and every posts_* filter shape
it), the 404 decision, the globals, the headers, then "wp" and
template_redirect. A listing's posts are the query's; a single post the
query does not find (a preview, a draft its author reads) is the one the
engine resolved. Without the runtime, the engine's own listing.

- const `LISTINGS` = `array (   0 =>    \Minn\Front\Kind::Home,   1 =>    \Minn\Front\Kind::Category,   2 =>    \Minn\Front\Kind::Tag,   3 =>    \Minn\Front\Kind::Taxonomy,   4 =>    \Minn\Front\Kind::PostTypeArchive,   5 =>    \Minn\Front\Kind::Author,   6 =>    \Minn\Front\Kind::Date,   7 =>    \Minn\Front\Kind::Search, )`

Used by: `Minn\Engine`, `Minn\Front\FeedController`, `Minn\Front\PrintedResponse`, `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, int $perPage)
```


### `stand(Minn\Front\Resolution $resolution, array $extra = array ( )): Minn\Content\Page`

The page of posts a resolution shows, through the runtime's main query
when it is up; the variables a request adds of its own (a feed's) win.

- `@param array<string, mixed> $extra`

### `perPage(): int`

Posts per page.

Internals: `vars()` (private, line 98), `queried()` (private, line 118), `seeded()` (private, line 131), `objectTypes()` (private, line 139), `listing()` (private, line 146)


## NotModified

`final class Minn\Theme\NotModified` · `public/minn/src/Minn/Theme/NotModified.php` · implements `Throwable`, `Stringable`

Raised once a reader's copy of a feed has been found current and the
headers that say so are sent: the reference stops the request there, so
the engine answers with nothing more.

Used by: `Minn\Front\FeedController`, `Minn\Theme\FrontLifecycle`


## PageRenderer

`final readonly class Minn\Theme\PageRenderer` · `public/minn/src/Minn/Theme/PageRenderer.php`

A whole page from the active block theme: the template the resolution
maps to, rendered against the main query, inside the document shell the
reference emits (skip link, wp-site-blocks, the skip-link target on the
first main element).

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Content\Site $site, Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Blocks\Renderer $renderer, Minn\Theme\MainQueryBridge $bridge, Minn\Theme\HeadLinks $headLinks, ?Minn\Front\AdminBar $bar = NULL)
```


### static `create(Minn\Db $db, Minn\Theme\Theme $theme, Minn\Front\Permalinks $permalinks, int $perPage, ?Minn\Front\AdminBar $bar = NULL): self`

A page renderer over the database door.

### `bodyClasses(Minn\Front\Resolution $resolution, array $coreClasses): array`

The reference's body-class tokens: the singular and template tokens
sit in front of the type token ("page", "single"), the custom-logo
and embed tokens follow the core set, the paging tokens come after
those, and the theme (and child theme) tokens close the list; then
the body_class filter.

- `@return list<string>`

### `themeClasses(Minn\Front\Resolution $resolution, array $coreClasses): array`

The body-class tokens before plugins filter them (bodyClasses()). @return list<string>

- `@return list<string>`

### `render(Minn\Front\Resolution $resolution, array $coreClasses, string $title): ?string`

The page for a resolution, or null when the theme has no template for it.

Internals: `pluginTemplate()` (private, line 170), `skipLinkTarget()` (private, line 191), `documentTitle()` (private, line 200), `head()` (private, line 222)


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

A pattern file's HTML with its PHP interpreted, never executed.

Internals: `statements()` (private, line 46), `printf()` (private, line 73), `expression()` (private, line 84), `term()` (private, line 100), `escape()` (private, line 128), `arguments()` (private, line 134), `stringLiteral()` (private, line 161), `identifier()` (private, line 184), `skipSpace()` (private, line 194)


## Printed

`final class Minn\Theme\Printed` · `public/minn/src/Minn/Theme/Printed.php` · implements `Throwable`, `Stringable`

Raised once a WordPress handler has printed a whole response (a sitemap,
a stylesheet) or sent a redirect, where the reference exits: the request
ends there and the engine answers with what was printed.

Used by: `Minn\Front\PrintedResponse`, `Minn\Front\SitemapRequest`


## StylePresets

`final class Minn\Theme\StylePresets` · `public/minn/src/Minn/Theme/StylePresets.php`

The preset side of theme.json: the colour, gradient, font-size,
font-family, spacing, and shadow lists a theme declares over core's
own, as the custom properties on :root and the has-* utility classes
the reference prints for them. Pure: settings in, CSS fragments out.

Used by: `Minn\Theme\GlobalStyles`

### static `presets(array $settings): array`

Every preset list the theme declares, over core's own, keyed by kind.

### static `presetList(mixed $presets): array`

A preset list as theme.json writes it is a plain list; as the site
editor saves it, it is keyed by origin (default, theme, custom). The
reference prints the origins in that order, so the two shapes flatten
to one list here.

- `@return list<array>`

### static `fontFamilies(array $settings): array`

The font family presets in the settings.

- `@return list<array>`

### static `presetProperties(array $presets): string`

The presets as custom properties for :root.

### static `presetClasses(array $presets): string`

The has-* utility classes the presets give every colour, gradient, font size, and font family.

### static `defaultSlugsFirst(array $presets, array $defaults): array`

A theme size that reuses one of core's slugs prints in core's position;
the theme's own slugs follow.

- `@param list<array> $presets`
- `@param list<string> $defaults`
- `@return list<array>`

### static `spacingPresets(array $defaults, array $own): array`

The default spacing scale (20 to 80) is always present, whatever
defaultSpacingSizes says; a theme size with the same slug replaces the
default in place, and the theme's other sizes follow the scale.

- `@return list<array{slug: string, value: string}>`

### static `fluidFontSize(array $preset, array $settings): string`

A font size with fluid bounds becomes clamp(min, min + ((1vw - v) * f),
max) scaled between a 320px viewport and the theme's wide size, which
is how the reference arrives at 0.196 for a 1rem to 1.125rem size on a
1340px wide layout. A plain size stays as written.

### static `fontFormat(string $url): string`

The format() a font source is declared with, from its file extension.


## StyleSettings

`final class Minn\Theme\StyleSettings` · `public/minn/src/Minn/Theme/StyleSettings.php`

The settings and styles nodes as wp/v2/global-styles reports them:
preset lists keyed by the origin that declared them, appearanceTools
spelled out as the flags it stands for, and var:preset tokens resolved
to the custom properties they name. Pure: a node in, a node out.

- const `PRESET_PATHS` = `array (   0 =>    array (     0 => 'color',     1 => 'palette',   ),   1 =>    array (     0 => 'color',     1 => 'gradients',   ),   2 =>    array (     0 => 'color',     1 => 'duotone',   ),   3 =>    array (     0 => 'typography',     1 => 'fontSizes',   ),   4 =>    array (     0 => 'typography',     1 => 'fontFamilies',   ),   5 =>    array (     0 => 'spacing',     1 => 'spacingSizes',   ),   6 =>    array (     0 => 'shadow',     1 => 'presets',   ),   7 =>    array (     0 => 'dimensions',     1 => 'aspectRatios',   ),   8 =>    array (     0 => 'dimensions',     1 => 'dimensionSizes',   ), )` — Where a settings node keeps preset lists, at its root and under each block.
- const `APPEARANCE_TOOLS` = `array (   'background' =>    array (     0 => 'backgroundImage',     1 => 'backgroundSize',     2 => 'gradient',   ),   'border' =>    array (     0 => 'color',     1 => 'radius',     2 => 'style',     3 => 'width',   ),   'color' =>    array (     0 => 'link',     1 => 'heading',     2 => 'button',     3 => 'caption',   ),   'dimensions' =>    array (     0 => 'aspectRatio',     1 => 'height',     2 => 'minHeight',     3 => 'minWidth',     4 => 'width',   ),   'position' =>    array (     0 => 'sticky',   ),   'spacing' =>    array (     0 => 'blockGap',     1 => 'margin',     2 => 'padding',   ),   'typography' =>    array (     0 => 'lineHeight',     1 => 'textColumns',   ), )` — What appearanceTools switches on, group by group in the order the
reference appends the groups it has to add; a group already present
keeps its place and gains the flags at its end.

Used by: `Minn\Rest\GlobalStylesObject`, `Minn\Theme\GlobalStyles`, `Minn\Theme\ThemeStyles`

### static `normalize(array $settings, string $origin): array`

A settings node as written (theme.json, a variation, or the site
editor's post) with its presets keyed by origin and appearanceTools
expanded.

### static `resolved(array $node): array`

Every var:preset|kind|slug token replaced by the custom property it names.

Internals: `presetsUnder()` (private, line 69), `withAppearanceTools()` (private, line 81)


## TemplateHierarchy

`final class Minn\Theme\TemplateHierarchy` · `public/minn/src/Minn/Theme/TemplateHierarchy.php`

The templates a block theme falls back through for a template slug, as
get_template_hierarchy builds them (probe rest-templates-lookup): the
slug itself, then its family's chain to index. A single entry's slug
reaches single-{type} and single only when the type is registered (and
goes straight to singular when not); a taxonomy's reaches taxonomy-{tax}
and taxonomy only when the taxonomy is; pages, categories, tags and
authors step through their bare name when the slug has more to it. A
prefix names the next step outright. A custom template is a page's.

### static `for(string $slug, bool $custom, string $prefix, array $types, array $taxonomies): array`

A slug's fallback chain, most specific first, ending at index.

- `@param list<string> $types the registered post types`
- `@param list<string> $taxonomies the registered taxonomies`
- `@return list<string>`

Internals: `chain()` (private, line 45), `single()` (private, line 64), `taxonomy()` (private, line 74), `registered()` (private, line 81)


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

Every template of a type: the theme's files with the saved rows over them.

- `@return list<TemplateRecord>`

### `find(string $type, string $id): ?Minn\Theme\TemplateRecord`

Null when the id names another theme, or a slug nothing provides.

### `themeSlug(): string`

The theme the index reads.

### `hasFile(string $type, string $slug): bool`

Whether the theme ships a file for a slug.

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

The default template types.

- `@return array<string, array{title: string, description: string}>`

### `markup(string $content): string`

Markup as a caller of the REST route sees it: patterns spliced in
where the template only named them, then every template-part block
told which theme it belongs to. A record carries the markup as
stored, because that is what get_block_templates() hands a plugin.

Internals: `savedRows()` (private, line 151), `pluginRows()` (private, line 166), `fromRow()` (private, line 175), `fromFile()` (private, line 201), `fromPlugin()` (private, line 223), `fileTitle()` (private, line 248), `savedArea()` (private, line 257)


## TemplatePartTheme

`final readonly class Minn\Theme\TemplatePartTheme` · `public/minn/src/Minn/Theme/TemplatePartTheme.php`

A template-part block inside a template says which theme's part it means.
The theme's own files usually leave that out, and the reference fills the
active theme in on the way out, for saved rows as much as for files. It
appends the attribute and leaves every other byte of the markup alone.

- const `TAG` = `'<!-- wp:template-part'`

Used by: `Minn\Theme\TemplateIndex`

### static `apply(string $markup, string $theme): string`

Template part blocks with the theme attribute set.

Internals: `withTheme()` (private, line 40), `encode()` (private, line 50), `pair()` (private, line 55)


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

Markup with its pattern blocks replaced by the patterns' content.

Internals: `closerAt()` (private, line 49), `pattern()` (private, line 55), `stamped()` (private, line 79), `isOneBlock()` (private, line 97)


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

The reference's template id: theme//slug.

### `isPart(): bool`

Whether this is a template part.


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

Deletes a saved template row.

Internals: `setArea()` (private, line 90), `themeTermId()` (private, line 100)


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

The template that renders a resolution, or null.

- `@return array{slug: string, markup: string}|null`

### `template(string $slug): ?string`

A template's markup by slug, saved first.

### `part(string $slug): ?string`

A template part's markup by slug, saved first.

### `partSource(string $slug): array`

Where a template part comes from: a part saved for the theme (its
post id), the theme's file (its path), or nowhere; with its markup.

- `@return array{0: 'post'|'file'|'none', 1: int|string, 2: ?string}`

### `candidates(Minn\Front\Resolution $resolution): array`

The block-theme template hierarchy for each kind of resolution.

- `@return list<string>`

### `userStyles(): ?array`

The site editor's saved global styles for the active theme, when any.
An emptied node is stored as [] and reads as nothing, the way the
reference reads it; a list where a map belongs is dropped the same way.

### `customTemplate(int $pageId): ?string`

A page's chosen custom template, from _wp_page_template meta.

Internals: `filtered()` (private, line 93), `hierarchy()` (private, line 117), `saved()` (private, line 175), `savedRow()` (private, line 182)


## Theme

`final class Minn\Theme\Theme` · `public/minn/src/Minn/Theme/Theme.php`

The active block theme on disk, read as data: theme.json, the templates
and parts directories, and the patterns index. A child theme's files
win and its parent fills in what the child does not define; theme.json
merges the same way, with the child's preset lists replacing the
parent's whole. The engine reads the site's installed theme the way it
reads the site's database; it never runs the theme's PHP.

Used by: `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Rest\Services`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\GlobalStyles`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplateIndex`, `Minn\Theme\TemplatePatterns`, `Minn\Theme\Templates`, `Minn\Theme\ThemeStyles`

```php
__construct(string $slug, string $dir, string $uri, ?Minn\Theme\Theme $parent = NULL)
```

- readonly `string $slug`
- readonly `string $dir`
- readonly `string $uri`
- readonly `?Minn\Theme\Theme $parent`

### static `active(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $themesDir): ?self`

The active block theme, or null under a classic one.

### static `forStyles(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $themesDir): ?self`

The active theme as styling data (theme.json present), whether or not
it is a block theme; the classic renderer prints its presets where the
reference prints a classic theme's global styles.

### `parentSlug(): ?string`

The theme's own name, and the parent's, for body classes.

### `json(): array`

The theme.json with the parent's merged in.

### `styleFiles(): array`

Every JSON file under styles/, the parent theme's first and each
theme's in path order: block style partials and style variations alike.

- `@return list<string>`

### static `merge(array $base, array $over): array`

Layered theme.json: maps merge key by key, lists (palettes, font
sizes, template parts) replace as a whole.

### `templateFile(string $slug): ?string`

A template file's markup, or null.

### `partFile(string $slug): ?string`

A template part file's markup, or null.

### `partPath(string $slug): ?string`

The file a template part is read from: this theme's, else its parent's; null when neither has one.

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

Internals: `at()` (private, line 59), `withStylePartials()` (private, line 90), `partialFiles()` (private, line 126), `safe()` (private, line 157), `htmlFiles()` (private, line 227), `patternIndex()` (private, line 339)


## ThemeStyles

`final readonly class Minn\Theme\ThemeStyles` · `public/minn/src/Minn/Theme/ThemeStyles.php`

The active theme's global styles as wp/v2/global-styles/themes/{stylesheet}
reports them: the engine's defaults (captured from the reference under a
theme with no theme.json) with the theme's own settings and styles over
them, and the style variations the theme ships.

Used by: `Minn\Rest\GlobalStylesController`, `Minn\Rest\Services`

```php
__construct(?Minn\Theme\Theme $theme, string $stylesheet)
```

- readonly `string $stylesheet`

### static `forSite(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $themesDir): self`

The site's active theme as styling data, whether or not it is a block theme.

### `settings(): array`

Settings: presets by origin, appearanceTools expanded, the defaults underneath.

### `styles(): array`

Styles: the theme's over the defaults, block style partials and section styles in, every var:preset token resolved.

### `variations(): array`

The style variations under styles/: every JSON file there that names
no blockTypes (those are block style partials), the parent theme's
first, in path order, $schema dropped and the nodes normalized the
way the theme's own are.

- `@return list<array>`

Internals: `withSectionStyles()` (private, line 91), `partials()` (private, line 113), `defaults()` (private, line 126)


## UserStyles

`final readonly class Minn\Theme\UserStyles` · `public/minn/src/Minn/Theme/UserStyles.php`

The site editor's saved global styles: one wp_global_styles post per
theme, tied to it by the wp_theme term, holding a versioned JSON body
of the settings and styles the editor wrote. Every changed save leaves
a revision behind, as a post's does.

Used by: `Minn\Rest\GlobalStylesController`, `Minn\Rest\GlobalStylesObject`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\PostWriter $writer, Minn\Content\Terms $terms, Minn\Content\Site $site)
```


### `idFor(string $stylesheet): ?int`

The newest published global-styles post carrying the theme's term, or null.

### `ensure(string $stylesheet, int $userId): int`

The theme's post, made the way the reference makes it when there is none yet.

### `find(int $id): ?Minn\Content\PostRecord`

The post when it is a global-styles post, else null.

### static `decode(string $json): array`

What a global-styles body holds; a missing, malformed, or list-shaped
node is empty, as the reference reads it.

- `@return array{settings: array, styles: array}`

### static `isBlank(string $json): bool`

True when the body carries nothing at all under settings or styles, malformed or not.

### `save(int $id, int $userId, ?string $title, ?array $settings, ?array $styles): void`

Replaces what the caller names (null keeps the stored value), stamps
the modified time, and snapshots a revision when anything changed.

