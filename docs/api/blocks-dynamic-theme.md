# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 262 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 287 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 248 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 252 | query, post-template, query-title, query-no-results, query-pagination, term-description. |
| [`Structure`](#structure) | final readonly class | 86 | template-part, pattern, site-title, site-tagline, site-logo. |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Comments $comments, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `comments()` (private, line 53), `title()` (private, line 67), `template()` (private, line 84), `list()` (private, line 99), `avatar()` (private, line 129), `date()` (private, line 141), `authorName()` (private, line 153), `content()` (private, line 167), `replyLink()` (private, line 178), `form()` (private, line 191), `formWithPlugins()` (private, line 223), `visible()` (private, line 247), `linkedEmail()` (private, line 265), `approved()` (private, line 279)


## Navigation

`final readonly class Minn\Blocks\Dynamic\Theme\Navigation` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Navigation.php`

navigation, navigation-link, page-list. A navigation block's items come
from its inner blocks, the wp_navigation post it references, the newest
published wp_navigation post, or (when none of those exist) the first
classic nav_menu; a responsive menu wraps them in the overlay markup
the reference emits.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, ?Minn\Content\Menus $menus = NULL)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `navigation()` (private, line 49), `responsive()` (private, line 117), `items()` (private, line 151), `presetClasses()` (private, line 164), `overlayColors()` (private, line 186), `link()` (private, line 198), `classicItems()` (private, line 221), `pageList()` (private, line 227), `ancestorsOf()` (private, line 237), `pageItems()` (private, line 261), `navParent()` (private, line 292), `menuPost()` (private, line 301), `enqueueView()` (private, line 311)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `title()` (private, line 52), `content()` (private, line 68), `date()` (private, line 111), `authorName()` (private, line 125), `excerpt()` (private, line 139), `featuredImage()` (private, line 151), `terms()` (private, line 191), `navigationLink()` (private, line 215), `termRow()` (private, line 236), `previewSource()` (private, line 248), `target()` (private, line 257), `open()` (private, line 262)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, term-description.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `query()` (private, line 53), `current()` (private, line 81), `postTemplate()` (private, line 86), `postClasses()` (private, line 120), `queryTitle()` (private, line 150), `noResults()` (private, line 170), `pagination()` (private, line 184), `paginationBase()` (private, line 230), `numbers()` (private, line 245), `archiveTitle()` (private, line 257), `termDescription()` (private, line 263)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `templatePart()` (private, line 42), `pattern()` (private, line 61), `siteTitle()` (private, line 79), `siteTagline()` (private, line 95)

