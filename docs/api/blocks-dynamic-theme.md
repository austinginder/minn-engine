# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 223 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 291 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 255 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 262 | query, post-template, query-title, query-no-results, query-pagination, term-description. |
| [`Structure`](#structure) | final readonly class | 105 | template-part, pattern, site-title, site-tagline, site-logo. |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Registers this family's blocks with the renderer.

Internals: `comments()` (private, line 47), `title()` (private, line 61), `template()` (private, line 78), `records()` (private, line 102), `count()` (private, line 115), `list()` (private, line 121), `avatar()` (private, line 151), `date()` (private, line 163), `authorName()` (private, line 175), `content()` (private, line 189), `replyLink()` (private, line 200), `form()` (private, line 213), `commentForm()` (private, line 228)


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

Internals: `navigation()` (private, line 50), `responsive()` (private, line 118), `items()` (private, line 152), `presetClasses()` (private, line 165), `overlayColors()` (private, line 187), `link()` (private, line 199), `classicItems()` (private, line 222), `pageList()` (private, line 228), `ancestorsOf()` (private, line 242), `pageItems()` (private, line 266), `navParent()` (private, line 297), `menuPost()` (private, line 306), `enqueueView()` (private, line 316)


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

Internals: `query()` (private, line 52), `queried()` (private, line 79), `current()` (private, line 90), `postTemplate()` (private, line 95), `postClasses()` (private, line 129), `queryTitle()` (private, line 159), `noResults()` (private, line 179), `pagination()` (private, line 193), `paginationBase()` (private, line 239), `numbers()` (private, line 254), `archiveTitle()` (private, line 266), `termDescription()` (private, line 272)


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

