# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 257 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 340 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 259 | The post-* blocks: they render the context's current post. |
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

Internals: `comments()` (private, line 54), `title()` (private, line 68), `template()` (private, line 90), `records()` (private, line 114), `count()` (private, line 127), `list()` (private, line 133), `avatar()` (private, line 164), `authorAvatar()` (private, line 176), `date()` (private, line 192), `commentPost()` (private, line 208), `authorName()` (private, line 214), `content()` (private, line 228), `replyLink()` (private, line 239), `form()` (private, line 252), `commentForm()` (private, line 267)


## Navigation

`final readonly class Minn\Blocks\Dynamic\Theme\Navigation` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Navigation.php`

navigation, navigation-link, page-list. A navigation block's items come
from its inner blocks, the wp_navigation post it references, the newest
published wp_navigation post, or (when none of those exist) the first
classic nav_menu; a responsive menu wraps them in the overlay markup
the reference emits.

- const `SUBMENU_WIRING` = `'data-wp-context="{ &quot;submenuOpenedBy&quot;: { &quot;click&quot;: false, &quot;hover&quot;: false, &quot;focus&quot;: false }, &quot;type&quot;: &quot;submenu&quot;, &quot;modal&quot;: null, &quot;previousFocus&quot;: null }" data-wp-interactive="core/navigation" data-wp-on--focusout="actions.handleMenuFocusout" data-wp-on--keydown="actions.handleMenuKeydown"'` — A submenu's interactivity: its context and the focus and key handlers (hover ones are added unless it opens on click).
- const `SUBMENU_HOVER` = `' data-wp-on--pointerenter="actions.openMenuOnHover" data-wp-on--pointerleave="actions.closeMenuOnHover"'`
- const `SUBMENU_TOGGLE` = `'data-wp-bind--aria-expanded="state.isSubmenuOpen" data-wp-on--click="actions.toggleMenuOnClick"'`
- const `CHEVRON` = `'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true" focusable="false"><path d="M1.50002 4L6.00002 8L10.5 4" stroke-width="1.5"></path></svg>'`
- const `CLOSE_ICON` = `'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 11.8l6.1-6.3-1.1-1-6.1 6.2-6.1-6.2-1.1 1 6.1 6.3-6.5 6.7 1.1 1 6.5-6.6 6.5 6.6 1.1-1z" /></svg>'`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, ?Minn\Content\Menus $menus = NULL)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `navigation()` (private, line 59), `responsive()` (private, line 129), `items()` (private, line 163), `presetClasses()` (private, line 176), `overlayColors()` (private, line 198), `link()` (private, line 210), `submenu()` (private, line 238), `overlayClose()` (private, line 260), `classicItems()` (private, line 271), `pageList()` (private, line 277), `ancestorsOf()` (private, line 291), `pageItems()` (private, line 315), `navParent()` (private, line 346), `menuPost()` (private, line 355), `enqueueView()` (private, line 365)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 53), `content()` (private, line 69), `date()` (private, line 113), `authorName()` (private, line 127), `excerpt()` (private, line 141), `featuredImage()` (private, line 153), `terms()` (private, line 193), `navigationLink()` (private, line 218), `termRow()` (private, line 248), `previewSource()` (private, line 260), `target()` (private, line 269), `open()` (private, line 274)


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

