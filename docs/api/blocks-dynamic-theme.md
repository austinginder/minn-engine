# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 296 | comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments. |
| [`Navigation`](#navigation) | final readonly class | 552 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 280 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 281 | query, post-template, query-title, query-no-results, query-pagination, query-total. |
| [`Structure`](#structure) | final readonly class | 105 | template-part, pattern, site-title, site-tagline, site-logo. |
| [`TermBlocks`](#termblocks) | final readonly class | 71 | term-name, term-count and term-description: the term a block's context |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments.

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

Internals: `comments()` (private, line 59), `legacy()` (private, line 85), `title()` (private, line 97), `template()` (private, line 137), `records()` (private, line 161), `count()` (private, line 174), `list()` (private, line 180), `avatar()` (private, line 211), `authorAvatar()` (private, line 223), `date()` (private, line 239), `commentPost()` (private, line 255), `authorName()` (private, line 261), `content()` (private, line 275), `replyLink()` (private, line 286), `form()` (private, line 299), `commentForm()` (private, line 314)


## Navigation

`final readonly class Minn\Blocks\Dynamic\Theme\Navigation` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Navigation.php`

navigation, navigation-link, page-list. A navigation block's items come
from its inner blocks, the wp_navigation post it references, the newest
published wp_navigation post, or (when none of those exist) the first
classic nav_menu; a block naming a menu that is gone has none, and no
list. A responsive menu wraps them in the overlay markup the reference
emits. The nav is labelled by the block's aria label, or the menu's
title, made unique in the request; the list carries the aria label as
given (probe loose-classes).

- const `LISTABLE` = `array (   0 => 'core/site-title',   1 => 'core/site-logo',   2 => 'core/social-links', )` — The other blocks a navigation lists, each in an li (block_core_navigation_listable_blocks).
- const `SUBMENU_CONTEXT` = `'{ "submenuOpenedBy": { "click": false, "hover": false, "focus": false }, "type": "submenu", "modal": null, "previousFocus": null }'` — A submenu's interactivity context.
- const `SUBMENU_TOGGLE` = `'data-wp-bind--aria-expanded="state.isSubmenuOpen" data-wp-on--click="actions.toggleMenuOnClick"'`
- const `CHEVRON` = `'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg>'` — The submenu toggle's chevron (block_core_navigation_link_render_submenu_icon).
- const `CLOSE_ICON` = `'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 11.8l6.1-6.3-1.1-1-6.1 6.2-6.1-6.2-1.1 1 6.1 6.3-6.5 6.7 1.1 1 6.5-6.6 6.5 6.6 1.1-1z" /></svg>'`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, ?Minn\Content\Menus $menus = NULL)
```


### static `submenuDirectives(string $visibility): array`

The attributes an item with a submenu takes, in the reference's order:
its context, the focus and key handlers, the hover ones for a submenu
that opens on hover, the init watcher, and its tab index.

- `@return array<string, string>`

### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `wiring()` (private, line 71), `navigation()` (private, line 98), `responsive()` (private, line 180), `items()` (private, line 217), `groups()` (private, line 239), `filteredItems()` (private, line 265), `linkedPosts()` (private, line 287), `weighLinks()` (private, line 300), `linkable()` (private, line 315), `presetClasses()` (private, line 326), `overlay()` (private, line 358), `link()` (private, line 375), `submenu()` (private, line 410), `overlayClose()` (private, line 439), `classicItems()` (private, line 450), `pageList()` (private, line 456), `submenuColours()` (private, line 476), `ancestorsOf()` (private, line 492), `pageItems()` (private, line 519), `navParent()` (private, line 550), `menuPost()` (private, line 568), `enqueueView()` (private, line 583)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 55), `content()` (private, line 71), `contentOf()` (private, line 77), `date()` (private, line 129), `authorName()` (private, line 143), `excerpt()` (private, line 157), `featuredImage()` (private, line 169), `terms()` (private, line 209), `navigationLink()` (private, line 240), `termRow()` (private, line 271), `previewSource()` (private, line 283), `target()` (private, line 292), `open()` (private, line 297)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, query-total.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `query()` (private, line 55), `queried()` (private, line 77), `page()` (private, line 86), `loop()` (private, line 102), `current()` (private, line 122), `total()` (private, line 135), `postTemplate()` (private, line 159), `queryTitle()` (private, line 197), `noResults()` (private, line 217), `pagination()` (private, line 231), `paginationBase()` (private, line 277), `numbers()` (private, line 292), `archiveTitle()` (private, line 304)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

Used by: `Minn\Blocks\Renderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `templatePart()` (private, line 45), `announcePart()` (private, line 70), `pattern()` (private, line 83), `siteTitle()` (private, line 101), `siteTagline()` (private, line 117)


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

