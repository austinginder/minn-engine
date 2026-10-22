# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 305 | comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments. |
| [`Navigation`](#navigation) | final readonly class | 554 | navigation, navigation-link, page-list. A navigation block's items come |
| [`NavigationParts`](#navigationparts) | final class | 197 | The navigation family's small answers for the block_core_navigation_* |
| [`PostBlocks`](#postblocks) | final readonly class | 295 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 333 | query, post-template, query-title, query-no-results, query-pagination, query-total. |
| [`Structure`](#structure) | final readonly class | 177 | template-part, pattern, site-title, site-tagline, site-logo. |
| [`TermBlocks`](#termblocks) | final readonly class | 71 | term-name, term-count and term-description: the term a block's context |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments.

- const `DEPTH` = `'comment_depth'` — The global the comment loop's depth is kept in, as get_comment_class and the reply link read it.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Content\Posts $posts, Minn\Content\Users $users)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

### static `query(array $vars): Minn\Objects\CommentsQuery`

The comment query a comment template's vars ask for, run once a
request: the template and its pagination links ask for the same one,
and the reference answers the repeat from its cache. A comment write
drops it (RowCache).

- `@param array<string, mixed> $vars`

Internals: `comments()` (private, line 61), `legacy()` (private, line 89), `title()` (private, line 101), `template()` (private, line 141), `records()` (private, line 167), `count()` (private, line 180), `list()` (private, line 186), `avatar()` (private, line 216), `authorAvatar()` (private, line 228), `date()` (private, line 244), `commentPost()` (private, line 260), `authorName()` (private, line 266), `content()` (private, line 281), `replyLink()` (private, line 293), `form()` (private, line 307), `commentForm()` (private, line 322)


## Navigation

`final readonly class Minn\Blocks\Dynamic\Theme\Navigation` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Navigation.php`

navigation, navigation-link, page-list. A navigation block's items come
from its inner blocks, the wp_navigation post it references, or the
fallback menu (the newest published one, made when there is none), else
a page list; a block naming a menu that is gone has none, and no list. A responsive menu wraps them in the overlay markup the reference
emits. The nav is labelled by the block's aria label, or the menu's
title, made unique in the request; the list carries the aria label as
given (probe loose-classes).

- const `LISTABLE` = `array (   0 => 'core/site-title',   1 => 'core/site-logo',   2 => 'core/social-links', )` — The other blocks a navigation lists, each in an li (block_core_navigation_listable_blocks).
- const `SUBMENU_CONTEXT` = `'{ "submenuOpenedBy": { "click": false, "hover": false, "focus": false }, "type": "submenu", "modal": null, "previousFocus": null }'` — A submenu's interactivity context.
- const `SUBMENU_TOGGLE` = `'data-wp-bind--aria-expanded="state.isSubmenuOpen" data-wp-on--click="actions.toggleMenuOnClick"'`
- const `CHEVRON` = `'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg>'` — The submenu toggle's chevron (block_core_navigation_link_render_submenu_icon).
- const `CLOSE_ICON` = `'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 11.8l6.1-6.3-1.1-1-6.1 6.2-6.1-6.2-1.1 1 6.1 6.3-6.5 6.7 1.1 1 6.5-6.6 6.5 6.6 1.1-1z" /></svg>'`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### static `submenuDirectives(string $visibility): array`

The attributes an item with a submenu takes, in the reference's order:
its context, the focus and key handlers, the hover ones for a submenu
that opens on hover, the init watcher, and its tab index.

- `@return array<string, string>`

### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `wiring()` (private, line 69), `navigation()` (private, line 95), `responsive()` (private, line 182), `items()` (private, line 219), `groups()` (private, line 241), `filteredItems()` (private, line 268), `linkedPosts()` (private, line 290), `weighLinks()` (private, line 303), `linkable()` (private, line 318), `presetClasses()` (private, line 329), `overlay()` (private, line 361), `link()` (private, line 378), `submenu()` (private, line 413), `overlayClose()` (private, line 442), `pageList()` (private, line 452), `submenuColours()` (private, line 473), `ancestorsOf()` (private, line 489), `pageItems()` (private, line 516), `navParent()` (private, line 547), `menuPost()` (private, line 568), `enqueueView()` (private, line 583)


## NavigationParts

`final class Minn\Blocks\Dynamic\Theme\NavigationParts` · `public/minn/src/Minn/Blocks/Dynamic/Theme/NavigationParts.php`

The navigation family's small answers for the block_core_navigation_*
helpers (probes navigation-blocks, nav-pomo-rest-doubts): colour classes
and inline styles from a block's attributes or context, the post ids its
links point to, whether an item renders, and the typography presets older
content stored.

### static `colors(array $text, array $background): array`

Colour classes and inline styles: for the text and for the background,
the first candidate set (a [kind, value] pair, kind named or custom)
wins: has-text-color and the named colour's class, or the custom one
inline; the same for the background.

- `@param list<array{0: string, 1: mixed}> $text`
- `@param list<array{0: string, 1: mixed}> $background`
- `@return array{css_classes: list<string>, inline_styles: string}`

### static `namedFirst(mixed $source, string $prefix = ''): array`

A source's colours with the named one first, the custom one next and,
when a style key is given, the style's colour last.

- `@return array{css_classes: list<string>, inline_styles: string}`

### static `navigationColors(mixed $attributes): array`

A navigation's colours and its overlay's, from its attributes (no style colours).

- `@return array<string, mixed>`

### static `pageListColors(mixed $context): array`

A page list's colours, the style's own last, and its overlay's, from its navigation's context.

- `@return array<string, mixed>`

### static `linkColors(mixed $context): array`

A link's colours from its navigation's context: the custom colour first,
then the named one, then the style's.

- `@return array{css_classes: list<string>, inline_styles: string}`

### static `submenuLinkColors(mixed $context): array`

A link's colours inside a submenu: the overlay's custom then named
colour first, else the link's own.

- `@return array{css_classes: list<string>, inline_styles: string}`

### static `postIds(mixed $blocks): array`

The post ids the links in blocks point to, at any depth (a block's
inner links before its own), each once at its first key.

- `@return array<int, mixed>`

### static `blockPostIds(mixed $block): array`

The post ids a block's inner links point to, then its own when it links to a post.

- `@return list<mixed>`

### static `itemRenders(mixed $attributes, mixed $block): bool`

Whether a navigation item renders: a link to a post or a page (by its
kind or its type) only while that post's status is one
render_block_core_navigation_link_allowed_post_status allows (publish).

### static `typographyBackcompat(mixed $parsedBlock): mixed`

A navigation's typography presets in the forms older content stored
(var:preset|...) read as their values, strikethrough as line-through.

Internals: `linkCandidates()` (private, line 193), `overlay()` (private, line 208)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 56), `content()` (private, line 72), `contentOf()` (private, line 78), `date()` (private, line 130), `authorName()` (private, line 150), `excerpt()` (private, line 172), `featuredImage()` (private, line 184), `terms()` (private, line 224), `navigationLink()` (private, line 256), `termRow()` (private, line 287), `previewSource()` (private, line 299), `target()` (private, line 308), `open()` (private, line 313)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, query-total.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `query()` (private, line 57), `queried()` (private, line 79), `page()` (private, line 88), `loop()` (private, line 104), `current()` (private, line 125), `total()` (private, line 138), `postTemplate()` (private, line 162), `queryTitle()` (private, line 205), `noResults()` (private, line 225), `pagination()` (private, line 246), `paginationBase()` (private, line 318), `numbers()` (private, line 338), `archiveTitle()` (private, line 358)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

- const `PART_TAGS` = `array (   0 => 'header',   1 => 'footer',   2 => 'div',   3 => 'main',   4 => 'section',   5 => 'article',   6 => 'aside',   7 => 'nav', )` — Tags a part may be wrapped in: tagName names one of these or the area's tag stands (security review 2026-08-28, finding 4).

Used by: `Minn\Blocks\Renderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `templatePart()` (private, line 60), `areaTag()` (private, line 85), `unbooted()` (private, line 97), `lookedUp()` (private, line 125), `pattern()` (private, line 160), `siteTitle()` (private, line 178), `siteTagline()` (private, line 194)


## TermBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\TermBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/TermBlocks.php`

term-name, term-count and term-description: the term a block's context
names (termId and taxonomy, as a terms query provides them), else the
term archive being viewed; nothing for neither (probe core-blocks).

- const `BRACKETS` = `array (   'round' =>    array (     0 => '(',     1 => ')',   ),   'square' =>    array (     0 => '[',     1 => ']',   ),   'curly' =>    array (     0 => '{',     1 => '}',   ),   'angle' =>    array (     0 => '<',     1 => '>',   ),   'none' =>    array (     0 => '',     1 => '',   ), )`

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Terms $terms, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `name()` (private, line 41), `count()` (private, line 57), `description()` (private, line 68), `term()` (private, line 80)

