# `Minn\Blocks`

the block parser and renderer

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Attributes`](#attributes) | final readonly class | 30 | The JSON object a block delimiter carries, read in place. Rewriting one |
| [`Bindings`](#bindings) | final class | 138 | Block bindings (probe block-bindings): a block attribute bound to a |
| [`Block`](#block) | final readonly class | 63 | One parsed block. A null name is freeform HTML between blocks. The |
| [`BlockName`](#blockname) | final class | 17 | The block name rules: a string, lower-case, `namespace/name`. |
| [`Context`](#context) | final class | 76 | What the template blocks render against: the resolution, the main |
| [`CoreBlocks`](#coreblocks) | final class | 26 | The core block types' metadata as the reference registers them |
| [`Duotone`](#duotone) | final class | 79 | Duotone filters (probe block-supports): a CSS color read into its red, |
| [`Elements`](#elements) | final class | 121 | Per-block element styles (style.elements in a block's attributes, the |
| [`ImageTags`](#imagetags) | final readonly class | 263 | The attributes the reference adds to an <img> that carries a |
| [`Layout`](#layout) | final class | 167 | The layout support's classes and rules (wp_render_layout_support_flag, |
| [`LayoutStyle`](#layoutstyle) | final class | 193 | The CSS rules a block's layout writes (wp_get_layout_style, probe |
| [`Parser`](#parser) | final class | 91 | Parses block markup into a tree. The grammar is the delimiter comment: |
| [`QueryVars`](#queryvars) | final class | 122 | The query variables a Query Loop block's context asks for, the way the |
| [`RenderState`](#renderstate) | final class | 329 | Per-request rendering state, owned by the renderer. The reference numbers |
| [`Renderer`](#renderer) | final class | 350 | Renders a block tree the way the reference renders post_content: |
| [`Selector`](#selector) | final class | 42 | The CSS selector a block type declares for its root or for one feature, from its `selectors` map or the older per-support keys. |
| [`Serializer`](#serializer) | final class | 46 | Parsed blocks back to markup. A core block is written by its short name; |
| [`States`](#states) | final class | 160 | Block state styles (a block's style[":hover"] and the like, probe |
| [`StyleEngine`](#styleengine) | final class | 148 | What a block's style object comes to, as the reference's style engine |
| [`Styles`](#styles) | final class | 155 | The inline style and class names a block's "style" and preset |
| [`TemplatePartVariations`](#templatepartvariations) | final class | 28 | The template part block's variations as the reference builds them (probe |
| [`Wrapper`](#wrapper) | final class | 59 | The opening tag of a dynamic block's wrapper, in the reference's class |

## Attributes

`final readonly class Minn\Blocks\Attributes` · `public/minn/src/Minn/Blocks/Attributes.php`

The JSON object a block delimiter carries, read in place. Rewriting one
block's attributes must not disturb the rest of the markup, so callers
take the object's exact substring rather than reserializing the document.

Used by: `Minn\Theme\TemplatePartTheme`, `Minn\Theme\TemplatePatterns`

### static `objectAt(string $markup, int $at): ?string`

The JSON object starting at $at, brace-matched through any strings; null when there is none.


## Bindings

`final class Minn\Blocks\Bindings` · `public/minn/src/Minn/Blocks/Bindings.php`

Block bindings (probe block-bindings): a block attribute bound to a
source registered with register_block_bindings_source takes the source's
value as the block renders. Only the attributes a block supports bind
(SUPPORTED, widened by block_bindings_supported_attributes and its
per-block form); a "__default" pattern-overrides binding stands for every
one of them. A static block's saved HTML takes the values (rich text,
kses'd, inside the element its definition selects; plain values set as
the selected element's attribute); a dynamic block renders with them.

- const `SUPPORTED` = `array (   'core/paragraph' =>    array (     0 => 'content',   ),   'core/heading' =>    array (     0 => 'content',   ),   'core/image' =>    array (     0 => 'id',     1 => 'url',     2 => 'title',     3 => 'alt',     4 => 'caption',   ),   'core/button' =>    array (     0 => 'url',     1 => 'text',     2 => 'linkTarget',     3 => 'rel',   ),   'core/post-date' =>    array (     0 => 'datetime',   ),   'core/navigation-link' =>    array (     0 => 'url',   ),   'core/navigation-submenu' =>    array (     0 => 'url',   ), )`

Used by: `Minn\Blocks\Renderer`

### static `supported(string $name): array`

The attributes a block may bind (get_block_bindings_supported_attributes). @return list<string>

- `@return list<string>`

### static `values(Minn\Blocks\Block $block, array $context): array`

The bound attributes' values for a block about to render, as the
sources answer them for a WP_Block made with the context given (each
source also seeing the context it uses); the expanded bindings ride
along under metadata for a pattern-overrides default. Empty when
nothing is bound or nothing answered.

- `@param array<string, mixed> $context`
- `@return array<string, mixed>`

### static `html(string $html, string $blockName, array $values): string`

A static block's HTML with the bound values in place, by its type's
attribute definitions: rich text and html replace what the selected
element holds; an attribute source sets the selected element's
attribute; anything else is left alone.

- `@param array<string, mixed> $values`

Internals: `inner()` (private, line 106), `closing()` (private, line 127), `opening()` (private, line 141), `attribute()` (private, line 147)


## Block

`final readonly class Minn\Blocks\Block` · `public/minn/src/Minn/Blocks/Block.php`

One parsed block. A null name is freeform HTML between blocks. The
innerContent list holds the block's own HTML chunks in order, with a
null placeholder wherever an inner block sits.

Used by: `Minn\Blocks\Bindings`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Dynamic\Theme\TermBlocks`, `Minn\Blocks\Parser`, `Minn\Blocks\Renderer`, `Minn\Blocks\Serializer`, `Minn\Blocks\Wrapper`, `Minn\Content\ContentScan`, `Minn\Content\Excerpt`, `Minn\Content\Menus`, `Minn\Extension\SeamRunner`, `Minn\Runtime\BlockFilters`, `Minn\Support\Kses`, `Minn\Theme\TemplatePatterns`

```php
__construct(?string $name, array $attrs, array $innerBlocks, string $innerHtml, array $innerContent)
```

- readonly `?string $name`
- readonly `array $attrs`
- readonly `array $innerBlocks`
- readonly `string $innerHtml`
- readonly `array $innerContent`

### static `freeform(string $html): self`

A block-less run of HTML, as the parser reads it.

### static `fromArray(array $block): self`

A block as parse_blocks hands it out, as the value object. @param array<string, mixed> $block

- `@param array<string, mixed> $block`

### `withAttrs(array $attrs): self`

The same block with other attributes. @param array<string, mixed> $attrs

- `@param array<string, mixed> $attrs`

### `toArray(): array`

The block as parse_blocks hands it to plugin code.

- `@return array{blockName: ?string, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML: string, innerContent: list<string|null>}`

### `attr(string $key, mixed $default = NULL): mixed`

One attribute, or the default.

### `className(): string`

The class names declared on the block's own attributes (className).


## BlockName

`final class Minn\Blocks\BlockName` · `public/minn/src/Minn/Blocks/BlockName.php`

The block name rules: a string, lower-case, `namespace/name`.

### static `refuse(mixed $name): ?Minn\Runtime\Refusal`

Why a block name is invalid, or null when it is fine.


## Context

`final class Minn\Blocks\Context` · `public/minn/src/Minn/Blocks/Context.php`

What the template blocks render against: the resolution, the main
query's posts, and a stack of "current post" frames pushed by post
templates and comment templates as they loop.

Used by: `Minn\Blocks\Renderer`, `Minn\Front\FeedController`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Front\Resolution $resolution, array $posts, int $total, int $perPage, bool $front)
```
- `@param list<PostRecord> $posts the main query's page of posts`

- readonly `Minn\Front\Resolution $resolution`
- readonly `array $posts`
- readonly `int $total`
- readonly `int $perPage`
- readonly `bool $front`

### static `forRest(): self`

A context for rendering outside a page: the home resolution and no posts.

### `post(): ?Minn\Content\PostRecord`

The post being rendered, innermost first.

### `pushPost(Minn\Content\PostRecord $post): void`

Enters a post's scope.

### `popPost(): void`

Leaves the innermost post's scope.

### `inLoop(): bool`

Inside a post template loop (as opposed to the singular view).

### `comment(): ?Minn\Content\CommentRecord`

The comment being rendered, if any.

### `withComment(?Minn\Content\CommentRecord $comment): void`

Enters or leaves a comment's scope.

### `paged(): int`

The page number being rendered.

### `totalPages(): int`

How many pages the listing makes.


## CoreBlocks

`final class Minn\Blocks\CoreBlocks` · `public/minn/src/Minn/Blocks/CoreBlocks.php`

The core block types' metadata as the reference registers them
(data/blocks.json): their supports and selectors, read once.

Used by: `Minn\Blocks\Layout`, `Minn\Blocks\Renderer`, `Minn\Theme\GlobalStyles`


### static `metadata(string $name): array`

A core block type's registered metadata; empty for a name core does not register.

- `@return array<string, mixed>`

### static `supports(string $name): array`

A core block type's supports.

- `@return array<string, mixed>`


## Duotone

`final class Minn\Blocks\Duotone` · `public/minn/src/Minn/Blocks/Duotone.php`

Duotone filters (probe block-supports): a CSS color read into its red,
green, blue (0 to 255) and alpha (0 to 1, to two places) channels, and
the hidden SVG filter that maps an image's shades onto a list of colors,
one table of values per channel.

- const `SVG` = `'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;" ><defs><filter id="%s"><feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=" .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 " /><feComponentTransfer color-interpolation-filters="sRGB" ><feFuncR type="table" tableValues="%s" /><feFuncG type="table" tableValues="%s" /><feFuncB type="table" tableValues="%s" /><feFuncA type="table" tableValues="%s" /></feComponentTransfer><feComposite in2="SourceGraphic" operator="in" /></filter></defs></svg>'`

### static `svg(string $filterId, array $colors): string`

The filter for a list of colors; colors that do not parse are left out.

- `@param list<string> $colors`

### static `parse(string $input): ?array`

A color's channels: hex (3, 4, 6 or 8 digits), rgb()/rgba() and hsl()/hsla().

- `@return array{r: float, g: float, b: float, a: float}|null`

Internals: `alpha()` (private, line 61), `hsl()` (private, line 70)


## Elements

`final class Minn\Blocks\Elements` · `public/minn/src/Minn/Blocks/Elements.php`

Per-block element styles (style.elements in a block's attributes, the
link colour most often; probe block-supports). A block whose element
styles set a colour gets a numbered wp-elements-N class and a rule per
element and state, recorded with the page's block-support styles in
render order: links (their text colour, plain or on hover), headings,
each heading level and buttons (text, background or gradient). A block
type whose colour support skips serialization, whole or for links,
headings or buttons, writes none for those.

- const `SELECTORS` = `array (   'link' => 'a:where(:not(.wp-element-button))',   'heading' => 'h1, h2, h3, h4, h5, h6',   'h1' => 'h1',   'h2' => 'h2',   'h3' => 'h3',   'h4' => 'h4',   'h5' => 'h5',   'h6' => 'h6',   'button' => '.wp-element-button, .wp-block-button__link', )`
- const `KINDS` = `array (   'link' => 'link',   'heading' => 'heading',   'h1' => 'heading',   'h2' => 'heading',   'h3' => 'heading',   'h4' => 'heading',   'h5' => 'heading',   'h6' => 'heading',   'button' => 'button', )` — The kind each element's skip answers to: every heading level is a heading.

Used by: `Minn\Blocks\Renderer`

### static `className(array $attrs, array $supports): ?string`

The block's wp-elements-N class, recording its rules; null when its
element styles earn none.

- `@param array<string, mixed> $supports the block type's supports`

### static `shouldAdd(array $elements, array $skip): bool`

Whether element styles earn a class: a link's text colour (or its
hover's), a heading's, a heading level's or a button's text,
background or gradient, for a kind not skipped.

- `@param array<string, mixed> $elements`
- `@param array<string, bool> $skip by kind: link, heading, button`

### static `skips(array $supports): array`

The kinds a block type's colour support skips serializing.

- `@param array<string, mixed> $supports`
- `@return array<string, bool>`

Internals: `rules()` (private, line 99), `declarations()` (private, line 124)


## ImageTags

`final readonly class Minn\Blocks\ImageTags` · `public/minn/src/Minn/Blocks/ImageTags.php`

The attributes the reference adds to an <img> that carries a
wp-image-{id} class: loading, decoding, width and height (prepended, in
that order), and srcset plus sizes (appended). The srcset lists the
displayed size first, then every same-ratio size in stored order, then
the original.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Uploads $uploads, Closure $front)
```
- `@param Closure(): bool $front whether the images are on a front-end page, where the loading budget applies`


### `enrich(string $html): string`

Rewrites every wp-image-* <img> in a fragment; other images pass through.

### `enrichGallery(string $html): string`

The same for a gallery: every image also carries its data-id.

### `enrichPlugin(string $html): string`

The same for a plugin block's output, where the reference adds no sizes="auto".

### `content(string $html, mixed $context = NULL): string`

Every <img> and <iframe> of a content fragment as the reference's
wp_filter_content_tags leaves it: an attachment's image is enriched as
above (a loading it already has is kept, and an eager large one is
fetched first); any other image is decoded asynchronously and, when its
size is known, loaded lazily or by the page's budget; an iframe with a
size is loaded lazily.

### `finished(string $html, string $context): string`

Content the engine fitted out itself, with what the_content's other
callbacks added on the way (an attachment's own link, a plugin's
image): an image still without its loading attributes is fitted as
the rest were, then each is offered to wp_content_img_tag, as
wp_filter_content_tags would.

### `featured(int $attachmentId, string $alt, string $style): string`

A post's featured image at full size, in the reference's attribute
order (dimensions, source, class, alt, style, then the loading
attributes and the srcset). Empty when the attachment has no file.

Internals: `fit()` (private, line 85), `attachmentOf()` (private, line 93), `plainImage()` (private, line 99), `lazyFrame()` (private, line 110), `rewrite()` (private, line 118), `loadingPrefix()` (private, line 133), `givenLoading()` (private, line 151), `minimumPriorityPixels()` (private, line 160), `enrichTag()` (private, line 166), `srcsetAttributes()` (private, line 224)


## Layout

`final class Minn\Blocks\Layout` · `public/minn/src/Minn/Blocks/Layout.php`

The layout support's classes and rules (wp_render_layout_support_flag,
probe block-supports), for core and plugin blocks alike:

- is-layout-{type} and {block}-is-layout-{type} for the layout the block
uses (its own over its type's default), with has-global-padding for a
constrained layout under a theme with root-padding-aware alignments;
- is-vertical or is-horizontal, is-content-justification-* and is-nowrap
from the layout the block itself stores;
- a wp-container-{block}-is-layout-* class when the layout writes CSS
(LayoutStyle), and wp-container-content-* for a child's own size or
place in its parent's layout.

The rules go to the page's block-support styles through the private
minn_block_support_rules action. A container class's suffix is a digest
the engine cannot reproduce (its inputs are not observable), so the engine
derives its own from the layout: same shape, different value, which the
parity suites normalise.

Used by: `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`

### static `classes(string $blockName, array $attrs): array`

The layout classes a core block's wrapper carries, under its type's
supports (data/blocks.json).

- `@return list<string>`

### static `forBlock(string $blockName, array $attrs, array $supports): array`

The layout classes a block's wrapper carries, its container's rules
recorded, under the theme's layout settings the render state holds
(root-padding-aware alignments, block gaps).

- `@param array<string, mixed> $supports the block type's supports (layout and its default, spacing)`
- `@return list<string>`

### static `childClass(array $child, array $parent): ?string`

The class a child's own layout earns (its size or place in the parent's
layout), its rules recorded; null when it writes none.

- `@param array<string, mixed> $child the block's style.layout`
- `@param array<string, mixed> $parent the parent's layout`

### static `sanitizeGap(mixed $gap): mixed`

A block gap as the layout reads it: a value with a character that could
end a declaration or open a function (\ ( & = } or a comment) is
dropped, a side of one too; anything not a string is left as it is.

### static `gapCss(mixed $gap): array|string|null`

A gap with its presets as custom properties, a side at a time.

- `@return string|array<string, string>|null`

### static `paddingCss(array $padding): array`

A block's side padding with its presets as custom properties.

- `@return array<string, string>`

### static `widths(array $layout): array`

A layout with its widths checked: the first declaration of each, through
the style attribute filter, empty when that drops it.

- `@return array<string, mixed>`

Internals: `safeGap()` (private, line 103), `skipsGap()` (private, line 122), `used()` (private, line 135), `typeClasses()` (private, line 146), `record()` (private, line 191)


## LayoutStyle

`final class Minn\Blocks\LayoutStyle` · `public/minn/src/Minn/Blocks/LayoutStyle.php`

The CSS rules a block's layout writes (wp_get_layout_style, probe
block-supports), as selector => declarations pairs the style engine turns
into a stylesheet:

- flow: only the gap between children (none, then the gap, on each);
- constrained: the content width on the children that are not aligned
left, right or full, the wide width on wide ones, no limit on full ones
(justifyContent pushes the children to a side: both margins with a
width, the one side alone without), negative margins on full ones for a
block with side padding, then the children's gap;
- flex: nowrap, the gap, then the direction and alignments the
orientation reads from justifyContent and verticalAlignment;
- grid: the column template (a count, a minimum width, or both folded
together with the gap, or the fallback gap without one), then the gap.

Widths arrive checked (one dropped as unsafe is empty) and gaps with
presets turned into custom properties.

- const `JUSTIFY` = `array (   'left' => 'flex-start',   'right' => 'flex-end',   'center' => 'center',   'stretch' => 'stretch',   'space-between' => 'space-between', )`
- const `ALIGN` = `array (   'top' => 'flex-start',   'center' => 'center',   'bottom' => 'flex-end',   'stretch' => 'stretch',   'space-between' => 'space-between', )`
- const `CHILD_KEYS` = `array (   0 => 'selfStretch',   1 => 'flexSize',   2 => 'columnSpan',   3 => 'rowSpan',   4 => 'columnStart',   5 => 'rowStart', )`

Used by: `Minn\Blocks\Layout`

### static `rules(string $selector, array $layout, array|string|null $gap, array $padding = array ( ), string $fallbackGap = '0.5em'): array`

The rules for a container.

- `@param array<string, mixed> $layout the layout, its widths checked`
- `@param string|array<string, string>|null $gap the block gap (a value, or top and left), null when it is not written`
- `@param array<string, string> $padding the block's own padding (left, right), for full-width children`
- `@return list<array{selector: string, declarations: array<string, string>}>`

### static `childRules(string $selector, array $child, array $parent): array`

The rules a child writes from its own layout (wp_get_child_layout_style_rules):
a fixed or filling flex size, a grid span, a grid position, released
again (the full row for a span) in a container narrower than the
columns it needs: the columns at the parent's minimum width (12rem by
default) and the gaps between them (1.5rem, or 24px for a width in px).

- `@param array<string, mixed> $child the child's layout (selfStretch, flexSize, columnSpan, rowSpan, columnStart, rowStart)`
- `@param array<string, mixed> $parent the parent's layout`
- `@return list<array<string, mixed>>`

### static `containerValues(array $layout): array`

A layout's container values: everything but the child keys.

### static `childValues(array $layout): array`

A layout's child values: the child keys alone.

Internals: `flowGap()` (private, line 53), `constrained()` (private, line 66), `flex()` (private, line 99), `grid()` (private, line 121), `gapValue()` (private, line 141), `filled()` (private, line 154), `single()` (private, line 163)


## Parser

`final class Minn\Blocks\Parser` · `public/minn/src/Minn/Blocks/Parser.php`

Parses block markup into a tree. The grammar is the delimiter comment:
an opener with optional JSON attributes, a closer, or a self-closing
void block; a name without a namespace is core/. Anything outside a
block is a freeform block that renders as-is.

- const `TOKEN` = `'/<!--\\s+(?P<closer>\\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\\/)?(?P<name>[a-z][a-z0-9_-]*)\\s+(?P<attrs>\\{(?:(?!\\}\\s+\\/?-->).)*+\\}\\s+)?(?P<void>\\/)?-->/s'`

Used by: `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Content\ContentScan`, `Minn\Content\Excerpt`, `Minn\Support\Kses`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplatePatterns`

### static `parse(string $markup): array`

Block markup as a tree of blocks.

- `@return list<Block>`

### static `contains(string $content, string $name): bool`

Whether serialized content carries a block by name; a bare name means core/, and core blocks are also found by their short delimiter.


## QueryVars

`final class Minn\Blocks\QueryVars` · `public/minn/src/Minn/Blocks/QueryVars.php`

The query variables a Query Loop block's context asks for, the way the
reference's build_query_vars_from_query_block builds them (probe
query-loop): a viewable post type; stickies only, left out, or ignored;
exclusions; a page size and offset for the page asked for; the old
category and tag ids and each viewable taxonomy's terms, merged; formats
(standard as no format) OR'd, beside the terms in a group of their own;
order, orderby, authors (a list, a comma list, or one id), a search, and
parents for a hierarchical type.

### static `fromContext(?array $context, int $page, array $sticky, array $site): array`

The query vars a query block's context amounts to.

- `@param array<string, mixed>|null $context the block's `query` context`
- `@param list<int> $sticky the site's sticky post ids`
- `@param array{viewableType: callable(string): bool, hierarchicalType: callable(string): bool, viewableTaxonomy: callable(string): bool, formats: list<string>} $site what the site says about types, taxonomies and formats`

Internals: `sticky()` (private, line 64), `idTerms()` (private, line 75), `taxonomyTerms()` (private, line 87), `formats()` (private, line 106), `author()` (private, line 129)


## RenderState

`final class Minn\Blocks\RenderState` · `public/minn/src/Minn/Blocks/RenderState.php`

Per-request rendering state, owned by the renderer. The reference numbers
galleries, style variations, and search inputs from ONE counter that runs
across every block rendered in the request (a list response keeps
counting from post to post), so the counter lives here, not on any block.
Leaf helpers with no renderer in hand reach it through current().

- const `MAX_DEPTH` = `64`

Used by: `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Elements`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Layout`, `Minn\Blocks\Renderer`, `Minn\Blocks\Wrapper`, `Minn\Content\Excerpt`, `Minn\Front\FeedController`, `Minn\Runtime\Runtime`, `Minn\Theme\GlobalStyles`, `Minn\Theme\PageRenderer`


### static `current(): self`

The state of the request being rendered, which its runtime holds, so
a request cannot count on from the one before it. Rendering with no
runtime (the command line, the unit suite) shares one off-request
state instead, since the counters have to survive between calls.

### `adopt(): void`

Makes this the state current() answers with, for the renderer that brought its own.

### `rootPaddingAware(): bool`

Whether the theme puts root padding in custom properties, which decides the constrained layout's classes.

### `useRootPadding(bool $aware): void`

Records what the active theme's layout settings say about root padding.

### `blockGap(): bool`

Whether the theme writes block gaps, which decides whether a layout's gap makes CSS.

### `useBlockGap(bool $writes): void`

Records whether the active theme's spacing settings write block gaps.

### `nextId(): int`

The next per-request counter value.

### `nextImage(): int`

Content images seen so far in this page, for the loading rules.

### `refundImages(int $count, bool $priority): void`

Images a plugin's block filter removed from the page give their budget
back, so the next image still counts as if the hidden ones never rendered.

### `aside(Closure $render): mixed`

Runs rendering whose images do not count toward the page's loading
budget (an excerpt's): the reference fits no image while it makes one,
so whatever was seen meanwhile is given back.

- `@param \Closure(): T $render`

### `filteringContent(string $content, Closure $filter): mixed`

$filter run while the_content filters content the engine rendered: the
images it already holds count as fitted out, so only what the filter's
other callbacks add is fitted (fittedImage()).

### `fittedImage(string $tag): bool`

Whether an image tag is one the content being filtered held when the filter began.

### `claimPriority(): bool`

True once, for the image that gets fetchpriority="high".

### `priorityAvailable(): bool`

Whether no element has taken high fetch priority yet on this page.

### `closePriority(): void`

Takes high fetch priority off the table for the rest of the page; a plugin prioritising its own element does this.

### `reopenPriority(): void`

Puts high fetch priority back on offer, so the next large enough image takes it.

### `recordBlock(string $blockName): void`

Every block name the page rendered; the stylesheet prints block styles for these only.

### `blocks(): array`

The block names rendering met.

- `@return array<string, true>`

### `nextElements(): int`

The next wp-elements-N class; the reference numbers these apart from the shared counter.

### `uniqueLabel(string $label): string`

A navigation's aria-label: the label itself the first time, then "label N" for repeats.

### `enterNavigation(): void`

A page list renders plain on its own and takes the navigation block's
item classes, submenu toggles, and interactivity only while it sits
inside one, so the navigation block marks the span it owns.

### `leaveNavigation(): void`

Leaves a navigation block.

### `inNavigation(): bool`

Whether rendering is inside a navigation block.

### `enter(string $key): bool`

Marks a nested source as being rendered; false when it is already open (a cycle).

### `leave(string $key): void`

Leaves a cycle-guarded key.

### `inside(string $key): bool`

Whether a key is open: a source being rendered, or the page template ("template").

### `descend(): bool`

True while the block tree is shallower than the cap; deeper blocks render as nothing.

### `ascend(): void`

Leaves one nesting level.

### `depth(): int`

How deep the block tree is right now; zero outside any block render.

### `setPendingElements(?string $class): ?string`

A dynamic block claims its element class before rendering, so a block that renders nothing still counts.

### `takePendingElements(): ?string`

The pending elements class, cleared.

### `setPendingVariation(?string $class): ?string`

Holds the numbered style variation class a dynamic block claimed before rendering; returns the one it replaces.

### `takePendingVariation(): ?string`

The numbered style variation class the dynamic block being rendered claimed, once: its wrapper takes it.

### `recordGallery(int $instance): void`

Notes a gallery instance.

### `galleries(): array`

The gallery instances rendering met.

- `@return list<int>`

### `reset(): void`

Clears every per-request counter.


## Renderer

`final class Minn\Blocks\Renderer` · `public/minn/src/Minn/Blocks/Renderer.php`

Renders a block tree the way the reference renders post_content:
delimiters gone, each block's own HTML kept with inner blocks rendered
in place, then the per-block render-time additions (layout classes, the
paragraph class, image attributes, gallery ids, style-variation
counters), and finally texturize over the whole.

Used by: `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Dynamic\Theme\TermBlocks`, `Minn\Content\Blocks`, `Minn\Runtime\Runtime`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Blocks\ImageTags $images)
```


### static `numberedStyle(string $blockName, string $className): ?string`

The numbered companion of a block style variation the block's class
names (is-style-{name}--N), consuming the counter: a variation with
styles of its own, the theme's or a registered style's (the facade
answers the private minn_block_style_variation filter), whose CSS the
facade prints once numbered (minn_block_style_variation_used). Null
when none applies.

### `state(): Minn\Blocks\RenderState`

The per-request rendering state: counters, containers, variations, the images seen.

### `context(): Minn\Blocks\Context`

What is being rendered.

### `withContext(Minn\Blocks\Context $context): void`

Sets what is being rendered.

### `images(): Minn\Blocks\ImageTags`

The image tag builder.

### static `forDb(Minn\Db $db): self`

A renderer with every dynamic block wired over the database door.

### `registerDynamic(string $name, callable $render): void`

Registers the engine's own renderer for a dynamic block. A plugin's
callback that already took the block over (bridge()) keeps it, as a
replaced render callback does on the reference.

- `@param callable(Block, Renderer): string $render`

### `bridge(string $name, callable $render): void`

Hands a block to a plugin's render callback; the engine's own renderer
for it stays for renderNative().

- `@param callable(Block, Renderer): string $render`

### `renderNative(Minn\Blocks\Block $block): string`

One block as HTML through the engine's own renderer for its name (its
static markup when it has none), even where a plugin's callback took
the block over: what a core render callback gives, so a plugin's
callback that calls the one it replaced does not call itself.

### `render(string $markup): string`

Block markup as HTML, texturized.

### `renderBlocks(array $blocks): string`

A tree of blocks as HTML.

- `@param list<Block> $blocks`

### `renderNativeUnfiltered(Minn\Blocks\Block $block): string`

A core block as renderNative() renders it, without the block filters
around this one block (its inner blocks keep theirs): its caller, the
facade's WP_Block::render, applies them once.

### `renderBlock(Minn\Blocks\Block $block): string`

One block as HTML, with the filters around it (unless renderNativeUnfiltered() asked for this one without).

### `blockContext(): array`

The context a block's bindings read: the post being rendered (postId,
postType) and what enclosing blocks provide (a synced pattern's
overrides).

- `@return array<string, mixed>`

### `providing(array $context, Closure $render): string`

Renders with context provided to the blocks inside (render_block_context's job on the reference). @param array<string, mixed> $context

- `@param array<string, mixed> $context`

### `rendersNatively(string $name): bool`

Whether the engine's own renderer answers for a block: a core block no
plugin's callback took over. It applies the block supports it knows
itself (layout, element styles); the facade's render_block filters do
the rest, and all of them for any other block.

Internals: `renderWith()` (private, line 206), `elementsClass()` (private, line 332), `renderNamed()` (private, line 338), `decorate()` (private, line 363), `gallery()` (private, line 385)


## Selector

`final class Minn\Blocks\Selector` · `public/minn/src/Minn/Blocks/Selector.php`

The CSS selector a block type declares for its root or for one feature, from its `selectors` map or the older per-support keys.

### static `resolve(array $selectors, array $supports, array|string|null $target, bool $fallback, string $defaultClass): ?string`

The CSS selector a block's style targets, or null.

- `@param array<string, mixed> $selectors the block type's selectors map`
- `@param array<string, mixed> $supports the block type's supports`
- `@param string|list<string>|null $target 'root', a dotted feature path, or a path list`

Internals: `at()` (private, line 39)


## Serializer

`final class Minn\Blocks\Serializer` · `public/minn/src/Minn/Blocks/Serializer.php`

Parsed blocks back to markup. A core block is written by its short name;
attributes are JSON with every character that could end the comment or
open a tag spelled as an escape ("<" "<", "--" "--", a
backslash "\"), so a value can never break out of its delimiter.

Used by: `Minn\Support\Kses`

### static `blocks(array $blocks): string`

A list of parsed blocks back to markup, freeform runs as written.

- `@param list<Block> $blocks`

### static `block(Minn\Blocks\Block $block): string`

One block with its inner blocks, as the delimiter comments the parser reads.

### static `delimited(?string $name, array $attrs, string $content): string`

Content wrapped in a block's delimiters; a block with no content is written self-closing.

### static `attributes(array $attrs): string`

Block attributes as the delimiter carries them.


## States

`final class Minn\Blocks\States` · `public/minn/src/Minn/Blocks/States.php`

Block state styles (a block's style[":hover"] and the like, probe
block-supports): the selector arithmetic and the fallbacks the reference
applies when it writes a state's rules. Pure: styles and selectors in,
styles, groups and declarations out.

- const `SIDES` = `array (   0 => 'top',   1 => 'right',   2 => 'bottom',   3 => 'left', )`

### static `split(string $selector): array`

A selector list split at its top-level commas (none inside parentheses
counts), each part as written.

- `@return list<string>`

### static `selector(string $base, string $selectors, string $state): string`

A state on each selector of a block's list, its leading compound
selector swapped for the base; the base alone for an empty list.

### static `presetVars(mixed $value): mixed`

Presets (var:preset|{kind}|{slug}) as their custom properties, through arrays; anything else as it is.

### static `backgroundResets(array $declarations): array`

Declarations with a background image unset under a background colour
that sets neither a background nor an image of its own.

- `@param array<string, string> $declarations`
- `@return array<string, string>`

### static `borderFallbacks(array $declarations): array`

Declarations with a solid border style for a border (or a side of one)
given a width or a colour and no style.

- `@param array<string, string> $declarations`
- `@return array<string, string>`

### static `dimensionFallbacks(array $style): array`

A state's style with an explicit aspect ratio unsetting the height and
minimum height, or a height or minimum height unsetting the aspect ratio.

- `@param array<string, mixed> $style`
- `@return array<string, mixed>`

### static `groups(array $style, array $selectors): array`

A state's style split by the selectors a block gives its features: a
feature (or one of its properties) with a selector of its own goes
there, a feature's other properties to its root selector, the rest to
the block's root. Groups keep the order they are first met in.

- `@param array<string, mixed> $style`
- `@param array<string, mixed> $selectors the block's selectors, root among them`
- `@return list<array{selector: ?string, style: array<string, mixed>}>`

### static `addGroup(array $groups, ?string $selector, array $style): void`

Adds a style to the group of a selector, merged into what it holds.

- `@param array<string, array{selector: ?string, style: array<string, mixed>}> $groups`
- `@param array<string, mixed> $style`


## StyleEngine

`final class Minn\Blocks\StyleEngine` · `public/minn/src/Minn/Blocks/StyleEngine.php`

What a block's style object comes to, as the reference's style engine
reads it (probe style-engine): the declarations each style property
makes and the class names a preset or a set colour earns.

- Groups in the order background, colour, border, shadow, dimensions,
spacing, typography, and properties in their order within a group.
- A value goes into the declarations as given. Whether it makes CSS is
for the declarations holder to judge.
- A preset ("var:preset|color|base") becomes a custom property only
where the definition names one for its kind (a colour, a gradient,
spacing, a font size or family, a shadow, a dimension, a border radius,
a border side's colour). Elsewhere it makes nothing, and with presets
turned into class names it makes none anywhere.
- Sides and corners ("padding" => ["top" => ...]) name their own
properties in the order given. Border sides come after the whole
border.

- const `DEFINITIONS` = `array (   'background' =>    array (     'backgroundImage' =>      array (       'property_keys' =>        array (         'default' => 'background-image',       ),       'value_func' =>        array (         0 => 'WP_Style_Engine',         1 => 'get_url_or_value_css_declaration',       ),       'path' =>        array (         0 => 'background',         1 => 'backgroundImage',       ),     ),     'backgroundPosition' =>      array (       'property_keys' =>        array (         'default' => 'background-position',       ),       'path' =>        array (         0 => 'background',         1 => 'backgroundPosition',       ),     ),     'backgroundRepeat' =>      array (       'property_keys' =>        array (         'default' => 'background-repeat',       ),       'path' =>        array (         0 => 'background',         1 => 'backgroundRepeat',       ),     ),     'backgroundSize' =>      array (       'property_keys' =>        array (         'default' => 'background-size',       ),       'path' =>        array (         0 => 'background',         1 => 'backgroundSize',       ),     ),     'backgroundAttachment' =>      array (       'property_keys' =>        array (         'default' => 'background-attachment',       ),       'path' =>        array (         0 => 'background',         1 => 'backgroundAttachment',       ),     ),     'gradient' =>      array (       'property_keys' =>        array (         'default' => 'background-image',       ),       'css_vars' =>        array (         'gradient' => '--wp--preset--gradient--$slug',       ),       'path' =>        array (         0 => 'background',         1 => 'gradient',       ),       'classnames' =>        array (         'has-background' => true,       ),     ),   ),   'color' =>    array (     'text' =>      array (       'property_keys' =>        array (         'default' => 'color',       ),       'path' =>        array (         0 => 'color',         1 => 'text',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),       'classnames' =>        array (         'has-text-color' => true,         'has-$slug-color' => 'color',       ),     ),     'background' =>      array (       'property_keys' =>        array (         'default' => 'background-color',       ),       'path' =>        array (         0 => 'color',         1 => 'background',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),       'classnames' =>        array (         'has-background' => true,         'has-$slug-background-color' => 'color',       ),     ),     'gradient' =>      array (       'property_keys' =>        array (         'default' => 'background',       ),       'path' =>        array (         0 => 'color',         1 => 'gradient',       ),       'css_vars' =>        array (         'gradient' => '--wp--preset--gradient--$slug',       ),       'classnames' =>        array (         'has-background' => true,         'has-$slug-gradient-background' => 'gradient',       ),     ),   ),   'border' =>    array (     'color' =>      array (       'property_keys' =>        array (         'default' => 'border-color',         'individual' => 'border-%s-color',       ),       'path' =>        array (         0 => 'border',         1 => 'color',       ),       'classnames' =>        array (         'has-border-color' => true,         'has-$slug-border-color' => 'color',       ),     ),     'radius' =>      array (       'property_keys' =>        array (         'default' => 'border-radius',         'individual' => 'border-%s-radius',       ),       'path' =>        array (         0 => 'border',         1 => 'radius',       ),       'css_vars' =>        array (         'border-radius' => '--wp--preset--border-radius--$slug',       ),     ),     'style' =>      array (       'property_keys' =>        array (         'default' => 'border-style',         'individual' => 'border-%s-style',       ),       'path' =>        array (         0 => 'border',         1 => 'style',       ),     ),     'width' =>      array (       'property_keys' =>        array (         'default' => 'border-width',         'individual' => 'border-%s-width',       ),       'path' =>        array (         0 => 'border',         1 => 'width',       ),     ),     'top' =>      array (       'value_func' =>        array (         0 => 'WP_Style_Engine',         1 => 'get_individual_property_css_declarations',       ),       'path' =>        array (         0 => 'border',         1 => 'top',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),     ),     'right' =>      array (       'value_func' =>        array (         0 => 'WP_Style_Engine',         1 => 'get_individual_property_css_declarations',       ),       'path' =>        array (         0 => 'border',         1 => 'right',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),     ),     'bottom' =>      array (       'value_func' =>        array (         0 => 'WP_Style_Engine',         1 => 'get_individual_property_css_declarations',       ),       'path' =>        array (         0 => 'border',         1 => 'bottom',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),     ),     'left' =>      array (       'value_func' =>        array (         0 => 'WP_Style_Engine',         1 => 'get_individual_property_css_declarations',       ),       'path' =>        array (         0 => 'border',         1 => 'left',       ),       'css_vars' =>        array (         'color' => '--wp--preset--color--$slug',       ),     ),   ),   'shadow' =>    array (     'shadow' =>      array (       'property_keys' =>        array (         'default' => 'box-shadow',       ),       'path' =>        array (         0 => 'shadow',       ),       'css_vars' =>        array (         'shadow' => '--wp--preset--shadow--$slug',       ),     ),   ),   'dimensions' =>    array (     'aspectRatio' =>      array (       'property_keys' =>        array (         'default' => 'aspect-ratio',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'aspectRatio',       ),       'classnames' =>        array (         'has-aspect-ratio' => true,       ),     ),     'height' =>      array (       'property_keys' =>        array (         'default' => 'height',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'height',       ),       'css_vars' =>        array (         'dimension' => '--wp--preset--dimension--$slug',       ),     ),     'minHeight' =>      array (       'property_keys' =>        array (         'default' => 'min-height',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'minHeight',       ),       'css_vars' =>        array (         'dimension' => '--wp--preset--dimension--$slug',       ),     ),     'minWidth' =>      array (       'property_keys' =>        array (         'default' => 'min-width',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'minWidth',       ),       'css_vars' =>        array (         'dimension' => '--wp--preset--dimension--$slug',       ),     ),     'objectFit' =>      array (       'property_keys' =>        array (         'default' => 'object-fit',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'objectFit',       ),     ),     'width' =>      array (       'property_keys' =>        array (         'default' => 'width',       ),       'path' =>        array (         0 => 'dimensions',         1 => 'width',       ),       'css_vars' =>        array (         'dimension' => '--wp--preset--dimension--$slug',       ),     ),   ),   'spacing' =>    array (     'padding' =>      array (       'property_keys' =>        array (         'default' => 'padding',         'individual' => 'padding-%s',       ),       'path' =>        array (         0 => 'spacing',         1 => 'padding',       ),       'css_vars' =>        array (         'spacing' => '--wp--preset--spacing--$slug',       ),     ),     'margin' =>      array (       'property_keys' =>        array (         'default' => 'margin',         'individual' => 'margin-%s',       ),       'path' =>        array (         0 => 'spacing',         1 => 'margin',       ),       'css_vars' =>        array (         'spacing' => '--wp--preset--spacing--$slug',       ),     ),   ),   'typography' =>    array (     'fontSize' =>      array (       'property_keys' =>        array (         'default' => 'font-size',       ),       'path' =>        array (         0 => 'typography',         1 => 'fontSize',       ),       'css_vars' =>        array (         'font-size' => '--wp--preset--font-size--$slug',       ),       'classnames' =>        array (         'has-$slug-font-size' => 'font-size',       ),     ),     'fontFamily' =>      array (       'property_keys' =>        array (         'default' => 'font-family',       ),       'css_vars' =>        array (         'font-family' => '--wp--preset--font-family--$slug',       ),       'path' =>        array (         0 => 'typography',         1 => 'fontFamily',       ),       'classnames' =>        array (         'has-$slug-font-family' => 'font-family',       ),     ),     'fontStyle' =>      array (       'property_keys' =>        array (         'default' => 'font-style',       ),       'path' =>        array (         0 => 'typography',         1 => 'fontStyle',       ),     ),     'fontWeight' =>      array (       'property_keys' =>        array (         'default' => 'font-weight',       ),       'path' =>        array (         0 => 'typography',         1 => 'fontWeight',       ),     ),     'lineHeight' =>      array (       'property_keys' =>        array (         'default' => 'line-height',       ),       'path' =>        array (         0 => 'typography',         1 => 'lineHeight',       ),     ),     'textColumns' =>      array (       'property_keys' =>        array (         'default' => 'column-count',       ),       'path' =>        array (         0 => 'typography',         1 => 'textColumns',       ),     ),     'textDecoration' =>      array (       'property_keys' =>        array (         'default' => 'text-decoration',       ),       'path' =>        array (         0 => 'typography',         1 => 'textDecoration',       ),     ),     'textIndent' =>      array (       'property_keys' =>        array (         'default' => 'text-indent',       ),       'path' =>        array (         0 => 'typography',         1 => 'textIndent',       ),     ),     'textTransform' =>      array (       'property_keys' =>        array (         'default' => 'text-transform',       ),       'path' =>        array (         0 => 'typography',         1 => 'textTransform',       ),     ),     'letterSpacing' =>      array (       'property_keys' =>        array (         'default' => 'letter-spacing',       ),       'path' =>        array (         0 => 'typography',         1 => 'letterSpacing',       ),     ),     'writingMode' =>      array (       'property_keys' =>        array (         'default' => 'writing-mode',       ),       'path' =>        array (         0 => 'typography',         1 => 'writingMode',       ),     ),   ), )` — The reference's style definitions, by group and property (WP_Style_Engine::BLOCK_STYLE_DEFINITIONS_METADATA).

Used by: `Minn\Theme\StylePresets`

### static `parse(array $styles, array $options = array ( )): array`

A style object's declarations and class names.

- `@param array<string, mixed> $styles`
- `@param array<string, mixed> $options convert_vars_to_classnames turns presets into class names alone`
- `@return array{declarations: array<string, mixed>, classnames: list<string>}`

### static `slug(mixed $value, string $kind): ?string`

A preset's slug for a kind ("var:preset|color|base" for color is "base"), or null.

### static `var(mixed $value, array $vars): ?string`

A preset as the custom property the definition names for its kind, or null. @param array<string, string> $vars

- `@param array<string, string> $vars`

### static `kebab(string $text): string`

A name in kebab case, as the reference's _wp_to_kebab_case cuts it ("lineHeight", "Zz Space" become "line-height", "zz-space").

Internals: `at()` (private, line 79), `present()` (private, line 91), `classes()` (private, line 97), `declarations()` (private, line 111), `one()` (private, line 124), `sides()` (private, line 134), `side()` (private, line 146), `url()` (private, line 158)


## Styles

`final class Minn\Blocks\Styles` · `public/minn/src/Minn/Blocks/Styles.php`

The inline style and class names a block's "style" and preset
attributes produce at render time, for the dynamic blocks that build
their own wrapper (static blocks already carry them in stored markup).

- const `SIDES` = `array (   0 => 'top',   1 => 'right',   2 => 'bottom',   3 => 'left', )`

Used by: `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\TermBlocks`, `Minn\Blocks\Elements`, `Minn\Blocks\Layout`, `Minn\Blocks\Wrapper`, `Minn\Theme\GlobalStyles`

### static `value(string $value): string`

var:preset|spacing|40 becomes var(--wp--preset--spacing--40). A value
that could close a declaration or a style element, or carry code,
is dropped: these values come from stored block attributes.

### static `slug(string $value): string`

A class-name token from an attribute: letters, digits, dashes, underscores; nothing that ends an attribute.

### static `inline(array $style): string`

The style attribute's declarations. The reference emits the style
groups in a fixed order (border, colour, typography, spacing,
dimensions) and most properties in a fixed order within a group,
but a spacing box's sides come out as the block stored them.

### static `classes(array $attrs): array`

The preset classes (font size, text alignment, custom class names) a block's attributes declare.

### static `align(array $attrs): ?string`

The align class an "align" attribute declares.

Internals: `borderDeclarations()` (private, line 64), `colorDeclarations()` (private, line 85), `spacingDeclarations()` (private, line 95), `typographyDeclarations()` (private, line 112), `dimensionDeclarations()` (private, line 133)


## TemplatePartVariations

`final class Minn\Blocks\TemplatePartVariations` · `public/minn/src/Minn/Blocks/TemplatePartVariations.php`

The template part block's variations as the reference builds them (probe
rest-block-types): one per area some part belongs to, the general area
aside, carrying the area's label, description and icon; then one per
template part, in the order the parts are listed, offered in the
inserter with the part's slug, theme and area as its attributes and its
example.

### static `build(array $areas, array $parts): array`

The variations: the areas' first, then the parts'.

- `@param list<array<string, mixed>> $areas get_allowed_block_template_part_areas()`
- `@param list<object> $parts get_block_templates() for template parts`
- `@return list<array<string, mixed>>`


## Wrapper

`final class Minn\Blocks\Wrapper` · `public/minn/src/Minn/Blocks/Wrapper.php`

The opening tag of a dynamic block's wrapper, in the reference's class
order: text alignment, the link-colour marker (for the blocks that
carry it), the block's own extra classes (a taxonomy, an alignment),
the custom class and its numbered style companion, the element-style
class, the block's class, colour presets, font size and family, and
the layout classes last; the style attribute inline.

Used by: `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Dynamic\Theme\TermBlocks`

### static `open(string $tag, string $blockClass, Minn\Blocks\Block $block, bool $styleFirst = false, bool $linkColorClass = false, array $extraClasses = array ( ), array $trailingClasses = array ( )): string`

A dynamic block's opening tag with its classes in the reference's order.

