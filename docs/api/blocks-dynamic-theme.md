# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 305 | comments, comments-title, comment-template, the comment-* blocks, the comment form, and the legacy post-comments. |
| [`Navigation`](#navigation) | final readonly class | 561 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 281 | The post-* blocks: they render the context's current post. |
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

Internals: `wiring()` (private, line 72), `navigation()` (private, line 99), `responsive()` (private, line 188), `items()` (private, line 225), `groups()` (private, line 247), `filteredItems()` (private, line 274), `linkedPosts()` (private, line 296), `weighLinks()` (private, line 309), `linkable()` (private, line 324), `presetClasses()` (private, line 335), `overlay()` (private, line 367), `link()` (private, line 384), `submenu()` (private, line 419), `overlayClose()` (private, line 448), `classicItems()` (private, line 459), `pageList()` (private, line 465), `submenuColours()` (private, line 486), `ancestorsOf()` (private, line 502), `pageItems()` (private, line 529), `navParent()` (private, line 560), `menuPost()` (private, line 578), `enqueueView()` (private, line 593)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 55), `content()` (private, line 71), `contentOf()` (private, line 77), `date()` (private, line 129), `authorName()` (private, line 143), `excerpt()` (private, line 157), `featuredImage()` (private, line 169), `terms()` (private, line 209), `navigationLink()` (private, line 241), `termRow()` (private, line 272), `previewSource()` (private, line 284), `target()` (private, line 293), `open()` (private, line 298)


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

