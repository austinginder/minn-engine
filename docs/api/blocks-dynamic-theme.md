# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 266 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 374 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 260 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 283 | query, post-template, query-title, query-no-results, query-pagination, query-total. |
| [`Structure`](#structure) | final readonly class | 105 | template-part, pattern, site-title, site-tagline, site-logo. |
| [`TermBlocks`](#termblocks) | final readonly class | 71 | term-name, term-count and term-description: the term a block's context |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Content\Posts $posts, Minn\Content\Users $users)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

### static `query(array $vars): WP_Comment_Query`

The comment query a comment template's vars ask for, run once a
request: the template and its pagination links ask for the same one,
and the reference answers the repeat from its cache. A comment write
drops it (RowCache).

- `@param array<string, mixed> $vars`

Internals: `comments()` (private, line 53), `title()` (private, line 67), `template()` (private, line 102), `records()` (private, line 126), `count()` (private, line 139), `list()` (private, line 145), `avatar()` (private, line 176), `authorAvatar()` (private, line 188), `date()` (private, line 204), `commentPost()` (private, line 220), `authorName()` (private, line 226), `content()` (private, line 240), `replyLink()` (private, line 251), `form()` (private, line 264), `commentForm()` (private, line 279)


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

Internals: `wiring()` (private, line 65), `navigation()` (private, line 92), `responsive()` (private, line 166), `items()` (private, line 202), `presetClasses()` (private, line 215), `overlayColors()` (private, line 237), `link()` (private, line 249), `submenu()` (private, line 275), `overlayClose()` (private, line 297), `classicItems()` (private, line 308), `pageList()` (private, line 314), `ancestorsOf()` (private, line 328), `pageItems()` (private, line 352), `navParent()` (private, line 383), `menuPost()` (private, line 392), `enqueueView()` (private, line 402)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 53), `content()` (private, line 69), `date()` (private, line 113), `authorName()` (private, line 127), `excerpt()` (private, line 141), `featuredImage()` (private, line 153), `terms()` (private, line 193), `navigationLink()` (private, line 218), `termRow()` (private, line 249), `previewSource()` (private, line 261), `target()` (private, line 270), `open()` (private, line 275)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, query-total.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `query()` (private, line 53), `queried()` (private, line 74), `loop()` (private, line 90), `current()` (private, line 103), `total()` (private, line 116), `postTemplate()` (private, line 132), `postClasses()` (private, line 166), `queryTitle()` (private, line 196), `noResults()` (private, line 216), `pagination()` (private, line 230), `paginationBase()` (private, line 276), `numbers()` (private, line 291), `archiveTitle()` (private, line 303)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

Used by: `Minn\Blocks\Renderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `templatePart()` (private, line 43), `announcePart()` (private, line 68), `pattern()` (private, line 81), `siteTitle()` (private, line 99), `siteTagline()` (private, line 115)


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

