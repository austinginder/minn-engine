# `Minn\Blocks`

the block parser and renderer

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Attributes`](#attributes) | final readonly class | 30 | The JSON object a block delimiter carries, read in place. Rewriting one |
| [`Block`](#block) | final readonly class | 27 | One parsed block. A null name is freeform HTML between blocks. The |
| [`BlockName`](#blockname) | final class | 16 | The block name rules: a string, lower-case, `namespace/name`. |
| [`Context`](#context) | final class | 68 | What the template blocks render against: the resolution, the main |
| [`Elements`](#elements) | final class | 79 | Per-block element styles (style.elements in a block's attributes, the |
| [`ImageTags`](#imagetags) | final readonly class | 167 | The attributes the reference adds to an <img> that carries a |
| [`Layout`](#layout) | final class | 121 | The layout-support classes the reference adds at render time. Every |
| [`Parser`](#parser) | final class | 87 | Parses block markup into a tree. The grammar is the delimiter comment: |
| [`QueryVars`](#queryvars) | final class | 78 | The query variables a Query Loop block's context asks for, the way the reference's query block builds them. |
| [`RenderState`](#renderstate) | final class | 217 | Per-request rendering state. The reference numbers galleries, style |
| [`Renderer`](#renderer) | final class | 205 | Renders a block tree the way the reference renders post_content: |
| [`Selector`](#selector) | final class | 40 | The CSS selector a block type declares for its root or for one feature, from its `selectors` map or the older per-support keys. |
| [`Styles`](#styles) | final class | 155 | The inline style and class names a block's "style" and preset |
| [`Supports`](#supports) | final class | 70 | The wrapper attributes a block's supports declaration earns from its |
| [`Wrapper`](#wrapper) | final class | 59 | The opening tag of a dynamic block's wrapper, in the reference's class |

## Attributes

`final readonly class Minn\Blocks\Attributes` · `public/minn/src/Minn/Blocks/Attributes.php`

The JSON object a block delimiter carries, read in place. Rewriting one
block's attributes must not disturb the rest of the markup, so callers
take the object's exact substring rather than reserializing the document.

Used by: `Minn\Theme\TemplatePartTheme`, `Minn\Theme\TemplatePatterns`

### static `objectAt(string $markup, int $at): ?string`

The JSON object starting at $at, brace-matched through any strings; null when there is none.


## Block

`final readonly class Minn\Blocks\Block` · `public/minn/src/Minn/Blocks/Block.php`

One parsed block. A null name is freeform HTML between blocks. The
innerContent list holds the block's own HTML chunks in order, with a
null placeholder wherever an inner block sits.

Used by: `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Parser`, `Minn\Blocks\Renderer`, `Minn\Blocks\Wrapper`, `Minn\Content\ContentScan`, `Minn\Content\Excerpt`, `Minn\Content\Menus`, `Minn\Extension\SeamRunner`, `Minn\Runtime\BlockFilters`, `Minn\Theme\TemplatePatterns`

```php
__construct(?string $name, array $attrs, array $innerBlocks, string $innerHtml, array $innerContent)
```

- readonly `?string $name`
- readonly `array $attrs`
- readonly `array $innerBlocks`
- readonly `string $innerHtml`
- readonly `array $innerContent`

### static `freeform(string $html): self`

### `attr(string $key, mixed $default = NULL): mixed`

### `className(): string`

The class names declared on the block's own attributes (className).


## BlockName

`final class Minn\Blocks\BlockName` · `public/minn/src/Minn/Blocks/BlockName.php`

The block name rules: a string, lower-case, `namespace/name`.

### static `refuse(mixed $name): ?Minn\Runtime\Refusal`


## Context

`final class Minn\Blocks\Context` · `public/minn/src/Minn/Blocks/Context.php`

What the template blocks render against: the resolution, the main
query's posts, and a stack of "current post" frames pushed by post
templates and comment templates as they loop.

Used by: `Minn\Blocks\Renderer`, `Minn\Front\ProbeController`, `Minn\Theme\PageRenderer`

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

### `post(): ?Minn\Content\PostRecord`

### `pushPost(Minn\Content\PostRecord $post): void`

### `popPost(): void`

### `inLoop(): bool`

Inside a post template loop (as opposed to the singular view).

### `comment(): ?Minn\Content\CommentRecord`

### `withComment(?Minn\Content\CommentRecord $comment): void`

### `paged(): int`

### `totalPages(): int`


## Elements

`final class Minn\Blocks\Elements` · `public/minn/src/Minn/Blocks/Elements.php`

Per-block element styles (style.elements in a block's attributes, the
link colour most often). The reference gives each such block a
numbered wp-elements-N class and a rule per element state, emitted with
the page's support styles in render order.

- const `SELECTORS` = `array (   'link' => 'a:where(:not(.wp-element-button))',   'heading' => 'h1, h2, h3, h4, h5, h6',   'h1' => 'h1',   'h2' => 'h2',   'h3' => 'h3',   'h4' => 'h4',   'h5' => 'h5',   'h6' => 'h6',   'button' => '.wp-element-button, .wp-block-button__link', )`
- const `WITHOUT_ELEMENTS` = `array (   0 => 'core/search',   1 => 'core/navigation',   2 => 'core/social-links',   3 => 'core/buttons',   4 => 'core/button', )` — Blocks whose colour support has no element slots: their stored
element styles are ignored and do not take a number.

Used by: `Minn\Blocks\Renderer`

### static `className(array $attrs, ?string $blockName = NULL): ?string`

The block's wp-elements-N class, recording its rules; null when the block styles no element.

Internals: `declarations()` (private, line 67)


## ImageTags

`final readonly class Minn\Blocks\ImageTags` · `public/minn/src/Minn/Blocks/ImageTags.php`

The attributes the reference adds to an <img> that carries a
wp-image-{id} class: loading, decoding, width and height (prepended, in
that order), and srcset plus sizes (appended). The srcset lists the
displayed size first, then every same-ratio size in stored order, then
the original.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Media\Uploads $uploads)
```


### `enrich(string $html, bool $withDataId = false, bool $front = false, bool $autoSizes = true): string`

Rewrites every wp-image-* <img> in a fragment; other images pass through.

### `featured(int $attachmentId, string $alt, string $style, bool $front): string`

A post's featured image at full size, in the reference's attribute
order (dimensions, source, class, alt, style, then the loading
attributes and the srcset). Empty when the attachment has no file.

Internals: `loadingPrefix()` (private, line 45), `minimumPriorityPixels()` (private, line 63), `enrichTag()` (private, line 69), `srcsetAttributes()` (private, line 127)


## Layout

`final class Minn\Blocks\Layout` · `public/minn/src/Minn/Blocks/Layout.php`

The layout-support classes the reference adds at render time. Every
container carries is-layout-{type} and {block}-is-layout-{type}; flex and
grid layouts with rules of their own also carry a wp-container-* class
whose suffix names their generated stylesheet.

That suffix is a digest the engine cannot reproduce (its inputs are not
observable), so the engine derives its own deterministic suffix from the
layout attributes. Same shape, different value: recorded in the contract,
and the parity suites normalise it.

Used by: `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Renderer`


### static `rootPaddingAware(bool $aware): void`

### static `classes(string $blockSlug, array $attrs, string $defaultType = 'flow', bool $alwaysContainer = false): array`

- `@return list<string>`

### static `declarations(string $type, array $layout, array $attrs): string`

The declarations behind a container class, in the reference's order.

Internals: `hasRules()` (private, line 66), `suffix()` (private, line 134)


## Parser

`final class Minn\Blocks\Parser` · `public/minn/src/Minn/Blocks/Parser.php`

Parses block markup into a tree. The grammar is the delimiter comment:
an opener with optional JSON attributes, a closer, or a self-closing
void block; a name without a namespace is core/. Anything outside a
block is a freeform block that renders as-is.

- const `TOKEN` = `'/<!--\\s+(?P<closer>\\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\\/)?(?P<name>[a-z][a-z0-9_-]*)\\s+(?P<attrs>\\{(?:(?!\\}\\s+\\/?-->).)*+\\}\\s+)?(?P<void>\\/)?-->/s'`

Used by: `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Content\ContentScan`, `Minn\Content\Excerpt`, `Minn\Theme\PageRenderer`, `Minn\Theme\TemplatePatterns`

### static `parse(string $markup): array`

- `@return list<Block>`

### static `contains(string $content, string $name): bool`

Whether serialized content carries a block by name; a bare name means core/, and core blocks are also found by their short delimiter.


## QueryVars

`final class Minn\Blocks\QueryVars` · `public/minn/src/Minn/Blocks/QueryVars.php`

The query variables a Query Loop block's context asks for, the way the reference's query block builds them.

### static `fromContext(?array $context, int $page, array $sticky, callable $postTypeExists, callable $taxonomyViewable): array`

- `@param array<string, mixed>|null $context the block's `query` context`
- `@param list<int> $sticky the site's sticky post ids`
- `@param callable(string): bool $postTypeExists`
- `@param callable(string): bool $taxonomyViewable`

Internals: `taxQuery()` (private, line 60)


## RenderState

`final class Minn\Blocks\RenderState` · `public/minn/src/Minn/Blocks/RenderState.php`

Per-request rendering state. The reference numbers galleries, style
variations, and search inputs from ONE counter that runs across every
block rendered in the request (a list response keeps counting from post
to post), so the counter lives here, not on any block.

- const `MAX_DEPTH` = `64`

Used by: `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Elements`, `Minn\Blocks\ImageTags`, `Minn\Blocks\Layout`, `Minn\Blocks\Renderer`, `Minn\Blocks\Wrapper`, `Minn\Content\Excerpt`, `Minn\Front\ProbeController`, `Minn\Theme\GlobalStyles`, `Minn\Theme\PageRenderer`


### static `nextId(): int`

### static `nextImage(): int`

Content images seen so far in this page, for the loading rules.

### static `refundImages(int $count, bool $priority): void`

Images a plugin's block filter removed from the page give their budget
back, so the next image still counts as if the hidden ones never rendered.

### static `claimPriority(): bool`

True once, for the image that gets fetchpriority="high".

### static `recordContainer(string $class, string $declarations): void`

A container stylesheet this page needs: the class and its declarations.

### static `containers(): array`

- `@return array<string, string>`

### static `recordBlock(string $blockName): void`

Every block name the page rendered; the stylesheet prints block styles for these only.

### static `blocks(): array`

- `@return array<string, true>`

### static `recordVariation(string $blockName, string $style, int $instance): void`

### static `variations(): array`

- `@return list<array{0: string, 1: string, 2: int}>`

### static `nextElements(): int`

The next wp-elements-N class; the reference numbers these apart from the shared counter.

### static `uniqueLabel(string $label): string`

A navigation's aria-label: the label itself the first time, then "label N" for repeats.

### static `enterNavigation(): void`

A page list renders plain on its own and takes the navigation block's
item classes, submenu toggles, and interactivity only while it sits
inside one, so the navigation block marks the span it owns.

### static `leaveNavigation(): void`

### static `inNavigation(): bool`

### static `enter(string $key): bool`

Marks a nested source as being rendered; false when it is already open (a cycle).

### static `leave(string $key): void`

### static `descend(): bool`

True while the block tree is shallower than the cap; deeper blocks render as nothing.

### static `ascend(): void`

### static `depth(): int`

How deep the block tree is right now; zero outside a page render.

### static `setPendingElements(?string $class): ?string`

A dynamic block claims its element class before rendering, so a block that renders nothing still counts.

### static `takePendingElements(): ?string`

### static `recordElementRule(string $css): void`

### static `elementRules(): array`

- `@return list<string>`

### static `recordGallery(int $instance): void`

### static `galleries(): array`

- `@return list<int>`

### static `reset(): void`


## Renderer

`final class Minn\Blocks\Renderer` · `public/minn/src/Minn/Blocks/Renderer.php`

Renders a block tree the way the reference renders post_content:
delimiters gone, each block's own HTML kept with inner blocks rendered
in place, then the per-block render-time additions (layout classes, the
paragraph class, image attributes, gallery ids, style-variation
counters), and finally texturize over the whole.

- const `NUMBERED_STYLES` = `array (   'core/separator' =>    array (     0 => 'wide',   ),   'core/button' =>    array (     0 => 'outline',   ),   'core/post-terms' =>    array (     0 => 'post-terms-1',   ), )` — Style variations that carry a numbered companion class at render.
These come from the active theme's registered block styles; the set
mirrors the reference's theme until the engine reads theme data.

Used by: `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\SyncedPattern`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Wrapper`, `Minn\Content\Blocks`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Blocks\ImageTags $images)
```


### static `numberedStyle(string $blockName, string $className): ?string`

The numbered companion of a registered style variation, consuming a counter; null when none applies.

### `context(): Minn\Blocks\Context`

### `withContext(Minn\Blocks\Context $context): void`

### `images(): Minn\Blocks\ImageTags`

### static `forDb(Minn\Db $db): self`

### `registerDynamic(string $name, callable $render): void`

- `@param callable(Block, Renderer): string $render`

### `render(string $markup): string`

### `renderBlocks(array $blocks): string`

- `@param list<Block> $blocks`

### `renderBlock(Minn\Blocks\Block $block): string`

Internals: `renderNamed()` (private, line 171), `decorate()` (private, line 192), `gallery()` (private, line 224), `flexWithoutContainer()` (private, line 232)


## Selector

`final class Minn\Blocks\Selector` · `public/minn/src/Minn/Blocks/Selector.php`

The CSS selector a block type declares for its root or for one feature, from its `selectors` map or the older per-support keys.

### static `resolve(array $selectors, array $supports, array|string|null $target, bool $fallback, string $defaultClass): ?string`

- `@param array<string, mixed> $selectors the block type's selectors map`
- `@param array<string, mixed> $supports the block type's supports`
- `@param string|list<string>|null $target 'root', a dotted feature path, or a path list`

Internals: `at()` (private, line 37)


## Styles

`final class Minn\Blocks\Styles` · `public/minn/src/Minn/Blocks/Styles.php`

The inline style and class names a block's "style" and preset
attributes produce at render time, for the dynamic blocks that build
their own wrapper (static blocks already carry them in stored markup).

- const `SIDES` = `array (   0 => 'top',   1 => 'right',   2 => 'bottom',   3 => 'left', )`

Used by: `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\SocialLinks`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Elements`, `Minn\Blocks\Layout`, `Minn\Blocks\Wrapper`, `Minn\Theme\GlobalStyles`

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


## Supports

`final class Minn\Blocks\Supports` · `public/minn/src/Minn/Blocks/Supports.php`

The wrapper attributes a block's supports declaration earns from its
attributes: align and class-name classes, color and gradient presets or
inline values, the font-size preset, the anchor id.

### static `attributes(array $attributes, array $supports, string $defaultClass, callable $kebab): array`

- `@param array<string, mixed> $attributes the block's prepared attributes`
- `@param array<string, mixed> $supports the block type's supports`
- `@param callable(string): string $kebab the slug form of a preset name (filtered on the reference)`
- `@return array<string, string> class, style, id, only those that apply`

Internals: `color()` (private, line 57)


## Wrapper

`final class Minn\Blocks\Wrapper` · `public/minn/src/Minn/Blocks/Wrapper.php`

The opening tag of a dynamic block's wrapper, in the reference's class
order: text alignment, the link-colour marker (for the blocks that
carry it), the block's own extra classes (a taxonomy, an alignment),
the custom class and its numbered style companion, the element-style
class, the block's class, colour presets, font size and family, and
the layout classes last; the style attribute inline.

Used by: `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`

### static `open(string $tag, string $blockClass, Minn\Blocks\Block $block, bool $styleFirst = false, string $blockName = '', bool $linkColorClass = false, array $extraClasses = array ( ), array $trailingClasses = array ( )): string`

