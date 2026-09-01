# `Minn\Blocks\Dynamic\Theme`

the template blocks a block theme composes with

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Comments`](#comments) | final readonly class | 193 | comments, comments-title, comment-template, the comment-* blocks, and the comment form. |
| [`Navigation`](#navigation) | final readonly class | 277 | navigation, navigation-link, page-list. A navigation block's items come |
| [`PostBlocks`](#postblocks) | final readonly class | 247 | The post-* blocks: they render the context's current post. |
| [`QueryBlocks`](#queryblocks) | final class | 251 | query, post-template, query-title, query-no-results, query-pagination, term-description. |
| [`Structure`](#structure) | final readonly class | 85 | template-part, pattern, site-title, site-tagline, site-logo. |

## Comments

`final readonly class Minn\Blocks\Dynamic\Theme\Comments` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Comments.php`

comments, comments-title, comment-template, the comment-* blocks, and the comment form.

```php
__construct(Minn\Db $db, Minn\Content\Comments $comments, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```

### `register(Minn\Blocks\Renderer $renderer): void`


## Navigation

`final readonly class Minn\Blocks\Dynamic\Theme\Navigation` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Navigation.php`

navigation, navigation-link, page-list. A navigation block's items come
from its inner blocks, the wp_navigation post it references, the newest
published wp_navigation post, or (when none of those exist) the first
classic nav_menu; a responsive menu wraps them in the overlay markup
the reference emits.

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, ?Minn\Content\Menus $menus = NULL)
```

### `register(Minn\Blocks\Renderer $renderer): void`


## PostBlocks

`final readonly class Minn\Blocks\Dynamic\Theme\PostBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/PostBlocks.php`

The post-* blocks: they render the context's current post.

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```

### `register(Minn\Blocks\Renderer $renderer): void`


## QueryBlocks

`final class Minn\Blocks\Dynamic\Theme\QueryBlocks` · `public/minn/src/Minn/Blocks/Dynamic/Theme/QueryBlocks.php`

query, post-template, query-title, query-no-results, query-pagination, term-description.

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```

### `register(Minn\Blocks\Renderer $renderer): void`


## Structure

`final readonly class Minn\Blocks\Dynamic\Theme\Structure` · `public/minn/src/Minn/Blocks/Dynamic/Theme/Structure.php`

template-part, pattern, site-title, site-tagline, site-logo.

```php
__construct(Minn\Theme\Theme $theme, Minn\Theme\Templates $templates, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```

### `register(Minn\Blocks\Renderer $renderer): void`

