# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 245 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 287 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 255 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 274 | query, post-template, query-title, query-no-results, query-pagination, term-description. |
| [`Structure`](#structure) | final readonly class | 105 | template-part, pattern, site-title, site-tagline, site-logo. |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Comments $comments, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `comments()` (private, line 53), `title()` (private, line 67), `template()` (private, line 84), `list()` (private, line 99), `avatar()` (private, line 129), `date()` (private, line 141), `authorName()` (private, line 153), `content()` (private, line 167), `replyLink()` (private, line 178), `form()` (private, line 191), `commentForm()` (private, line 206), `visible()` (private, line 230), `linkedEmail()` (private, line 248), `approved()` (private, line 262)


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

Internals: `title()` (private, line 52), `content()` (private, line 68), `date()` (private, line 112), `authorName()` (private, line 126), `excerpt()` (private, line 140), `featuredImage()` (private, line 152), `terms()` (private, line 192), `navigationLink()` (private, line 216), `termRow()` (private, line 243), `previewSource()` (private, line 255), `target()` (private, line 264), `open()` (private, line 269)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, term-description.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `query()` (private, line 53), `queried()` (private, line 92), `current()` (private, line 103), `postTemplate()` (private, line 108), `postClasses()` (private, line 142), `queryTitle()` (private, line 172), `noResults()` (private, line 192), `pagination()` (private, line 206), `paginationBase()` (private, line 252), `numbers()` (private, line 267), `archiveTitle()` (private, line 279), `termDescription()` (private, line 285)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `templatePart()` (private, line 43), `announcePart()` (private, line 68), `pattern()` (private, line 81), `siteTitle()` (private, line 99), `siteTagline()` (private, line 115)

