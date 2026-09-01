# `Minn\Blocks\Dynamic`

dynamic core blocks that render from data

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Archives`](#archives) | final readonly class | 27 | core/archives: the months that have published posts, newest first. |
| [`Categories`](#categories) | final readonly class | 26 | core/categories: the non-empty categories as a list, by name. |
| [`Dates`](#dates) | final class | 19 | Site-local dates the way the dynamic blocks print them. |
| [`LatestComments`](#latestcomments) | final readonly class | 67 | core/latest-comments: the newest approved comments with avatar, meta, and a 20-word excerpt. |
| [`LatestPosts`](#latestposts) | final readonly class | 33 | core/latest-posts: the newest published posts as a list, optionally dated. |
| [`Search`](#search) | final readonly class | 47 | core/search: the site search form. The button sits outside or inside |
| [`SocialLinks`](#sociallinks) | final class | 74 | core/social-links and core/social-link. The list keeps its stored |
| [`SyncedPattern`](#syncedpattern) | final readonly class | 24 | core/block: a synced pattern, rendered from the wp_block post it references. |
| [`TagCloud`](#tagcloud) | final readonly class | 35 | core/tag-cloud: non-empty tags by name, sized from 8pt to 22pt in |

## Archives

`final readonly class Minn\Blocks\Dynamic\Archives` · `public/minn/src/Minn/Blocks/Dynamic/Archives.php`

core/archives: the months that have published posts, newest first.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db, Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block, Minn\Blocks\Renderer $renderer): string`


## Categories

`final readonly class Minn\Blocks\Dynamic\Categories` · `public/minn/src/Minn/Blocks/Dynamic/Categories.php`

core/categories: the non-empty categories as a list, by name.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db, Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block, Minn\Blocks\Renderer $renderer): string`


## Dates

`final class Minn\Blocks\Dynamic\Dates` · `public/minn/src/Minn/Blocks/Dynamic/Dates.php`

Site-local dates the way the dynamic blocks print them.

Used by: `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\PostBlocks`

### static `iso(Minn\Content\Site $site, string $local): string`

ISO 8601 with the site's offset, from a site-local MySQL datetime.

### static `format(Minn\Content\Site $site, string $local): string`

The date_format option applied to a site-local MySQL datetime.


## LatestComments

`final readonly class Minn\Blocks\Dynamic\LatestComments` · `public/minn/src/Minn/Blocks/Dynamic/LatestComments.php`

core/latest-comments: the newest approved comments with avatar, meta, and a 20-word excerpt.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block, Minn\Blocks\Renderer $renderer): string`

Internals: `excerpt()` (private, line 76)


## LatestPosts

`final readonly class Minn\Blocks\Dynamic\LatestPosts` · `public/minn/src/Minn/Blocks/Dynamic/LatestPosts.php`

core/latest-posts: the newest published posts as a list, optionally dated.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block): string`


## Search

`final readonly class Minn\Blocks\Dynamic\Search` · `public/minn/src/Minn/Blocks/Dynamic/Search.php`

core/search: the site search form. The button sits outside or inside
the field and shows text or the search icon; the block's colour and
font presets land on the button (font family on the input too). The
input id comes from the shared request counter.

- const `ICON` = `'<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24"> 					<path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path> 				</svg>'`

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block, Minn\Blocks\Renderer $renderer): string`


## SocialLinks

`final class Minn\Blocks\Dynamic\SocialLinks` · `public/minn/src/Minn/Blocks/Dynamic/SocialLinks.php`

core/social-links and core/social-link. The list keeps its stored
wrapper and gains the flex layout classes; each link renders from its
parent's icon colours and the service's icon. The icons are the
reference's rendered output captured as data (src/data/social-icons.json,
the upstream set is CC0); an unknown service gets the share icon.

Used by: `Minn\Blocks\Renderer`

```php
__construct(string $iconsFile)
```


### `register(Minn\Blocks\Renderer $renderer): void`

Internals: `list()` (private, line 36), `link()` (private, line 51), `icons()` (private, line 90)


## SyncedPattern

`final readonly class Minn\Blocks\Dynamic\SyncedPattern` · `public/minn/src/Minn/Blocks/Dynamic/SyncedPattern.php`

core/block: a synced pattern, rendered from the wp_block post it references.

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db)
```


### `render(Minn\Blocks\Block $block, Minn\Blocks\Renderer $renderer): string`


## TagCloud

`final readonly class Minn\Blocks\Dynamic\TagCloud` · `public/minn/src/Minn/Blocks/Dynamic/TagCloud.php`

core/tag-cloud: non-empty tags by name, sized from 8pt to 22pt in
proportion to their counts (every tag at 8pt when the counts are equal).

Used by: `Minn\Blocks\Renderer`

```php
__construct(Minn\Db $db, Minn\Front\Permalinks $permalinks)
```


### `render(Minn\Blocks\Block $block): string`

