# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 193 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 286 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 247 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 251 | query, post-template, query-title, query-no-results, query-pagination, term-description. |
| [`Structure`](#structure) | final readonly class | 85 | template-part, pattern, site-title, site-tagline, site-logo. |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Comments $comments, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Internals: `comments()` (private, line 49), `title()` (private, line 63), `template()` (private, line 80), `list()` (private, line 95), `avatar()` (private, line 124), `date()` (private, line 136), `authorName()` (private, line 148), `content()` (private, line 162), `replyLink()` (private, line 171), `form()` (private, line 184), `approved()` (private, line 207)


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

Internals: `navigation()` (private, line 48), `responsive()` (private, line 116), `items()` (private, line 150), `presetClasses()` (private, line 163), `overlayColors()` (private, line 185), `link()` (private, line 197), `classicItems()` (private, line 220), `pageList()` (private, line 226), `ancestorsOf()` (private, line 236), `pageItems()` (private, line 260), `navParent()` (private, line 291), `menuPost()` (private, line 300), `enqueueView()` (private, line 310)


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Internals: `title()` (private, line 51), `content()` (private, line 67), `date()` (private, line 110), `authorName()` (private, line 124), `excerpt()` (private, line 138), `featuredImage()` (private, line 150), `terms()` (private, line 190), `navigationLink()` (private, line 214), `termRow()` (private, line 235), `previewSource()` (private, line 247), `target()` (private, line 256), `open()` (private, line 261)


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, term-description.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Internals: `query()` (private, line 52), `current()` (private, line 80), `postTemplate()` (private, line 85), `postClasses()` (private, line 119), `queryTitle()` (private, line 149), `noResults()` (private, line 169), `pagination()` (private, line 183), `paginationBase()` (private, line 229), `numbers()` (private, line 244), `archiveTitle()` (private, line 256), `termDescription()` (private, line 262)


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

Used by: `Minn\Theme\PageRenderer`

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Internals: `templatePart()` (private, line 41), `pattern()` (private, line 60), `siteTitle()` (private, line 78), `siteTagline()` (private, line 94)

