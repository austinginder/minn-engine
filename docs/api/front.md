# `Minn\Front`

URL resolution, permalinks, feeds, sitemaps and the public page

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AdminBar`](#adminbar) | final readonly class | 317 | The Minn bar on the public site: the same server-rendered chrome the |
| [`AssetsController`](#assetscontroller) | final readonly class | 31 | The engine's own static assets, served under a reserved path. |
| [`Canonical`](#canonical) | final class | 27 | Where a URL should redirect to, by the engine's own resolution: the |
| [`CommentList`](#commentlist) | final class | 50 | The classic threaded comment walk: top-level comments in order (or |
| [`CommentPostController`](#commentpostcontroller) | final readonly class | 138 | wp-comments-post.php: the comment form's target. The reference's |
| [`DocumentTitle`](#documenttitle) | final class | 41 | The document title as parts (title, tagline, page, site) in the order the |
| [`Feeds`](#feeds) | final readonly class | 312 | The syndication feeds, byte for byte in the reference's shape: RSS 2.0 |
| [`FrontController`](#frontcontroller) | final readonly class | 44 | The public site. One catch-all route: resolve the URL, then either |
| [`Kind`](#kind) | enum | 17 | What a public URL resolved to. |
| [`ListingLinks`](#listinglinks) | final class | 53 | The prev/next links a paged listing prints: which page sits either side of |
| [`Pagination`](#pagination) | final class | 56 | Numbered page links in the reference's shape: previous, the end and |
| [`Permalinks`](#permalinks) | final readonly class | 224 | Builds public URLs from the site's permalink structure. With an empty |
| [`PluginRules`](#pluginrules) | final class | 65 | Rewrite rules a plugin registered through add_rewrite_rule(): the |
| [`PostNavigation`](#postnavigation) | final class | 24 | The links to the posts either side of this one, and the nav block that |
| [`ProbeController`](#probecontroller) | final readonly class | 175 | The surface monitors, crawlers, and hosting checks hit that is not a |
| [`Renderer`](#renderer) | final readonly class | 151 | The interim public theme: one clean template until the block-theme |
| [`Resolution`](#resolution) | final readonly class | 97 | The outcome of resolving a public URL: which kind of thing it names, |
| [`Resolver`](#resolver) | final readonly class | 543 | Turns a public URL into a Resolution, following the reference's observed |
| [`SitemapXml`](#sitemapxml) | final class | 35 | The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer. |
| [`Sitemaps`](#sitemaps) | final readonly class | 138 | The sitemap index and its providers (posts, pages, categories, tags, |
| [`TermLists`](#termlists) | final class | 169 | The two term listings themes print: the nested category list and the |

## AdminBar

`final readonly class Minn\Front\AdminBar` · `public/minn/src/Minn/Front/AdminBar.php`

The Minn bar on the public site: the same server-rendered chrome the
app ships (assets/js/bar.js and assets/css/bar.css from the bundle),
built here for a signed-in reader who can edit. There is no other bar
and no other admin on the engine, so it is on for everyone who passes
the edit_posts gate; the app's per-person opt-in does not apply.

Used by: `Minn\Engine`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Auth\Authenticated $session, Minn\Auth\Capabilities $capabilities, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Admin\App $app, Minn\Admin\Appearance $appearance, Minn\Admin\AdminTypes $types)
```


### static `forReader(?Minn\Auth\Authenticated $session, Minn\Auth\Capabilities $capabilities, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Admin\App $app, Minn\Admin\Appearance $appearance, Minn\Admin\AdminTypes $types): ?self`

### `head(): string`

The stylesheet link for the head.

### `render(Minn\Front\Resolution $resolution): string`

The bar markup, its config, and the script, for the end of the body.

Internals: `markup()` (private, line 82), `siteMenu()` (private, line 135), `statusMenu()` (private, line 153), `newMenu()` (private, line 175), `notificationsMenu()` (private, line 189), `userMenu()` (private, line 202), `status()` (private, line 220), `editTarget()` (private, line 237), `commands()` (private, line 253), `searchTypes()` (private, line 278), `customSchemeStyle()` (private, line 287), `appUrl()` (private, line 306), `appPath()` (private, line 311), `assetUrl()` (private, line 316), `icon()` (private, line 321), `gridIcon()` (private, line 326), `menuItem()` (private, line 331)


## AssetsController

`final readonly class Minn\Front\AssetsController` · `public/minn/src/Minn/Front/AssetsController.php`

The engine's own static assets, served under a reserved path.

- const `TYPES` = `array (   'css' => 'text/css',   'js' => 'application/javascript', )`

Used by: `Minn\Engine`

```php
__construct(string $assetsDir)
```


### `asset(Minn\Http\Request $request, string $path): Minn\Http\Response`

Route: `GET /minn/assets/{path*}`

### `jquery(Minn\Http\Request $request, string $file): Minn\Http\Response`

Route: `GET /wp-includes/js/jquery/{file:[a-z0-9.-]+\.js}`

The MIT libraries the engine ships, served at the paths the reference registers them under.


## Canonical

`final class Minn\Front\Canonical` · `public/minn/src/Minn/Front/Canonical.php`

Where a URL should redirect to, by the engine's own resolution: the
canonical form of a request (trailing slash, `?p=` to permalink, doubled
slashes, former slugs). The front controller applies this before any
plugin runs; the `redirect_canonical()` facade asks it again for a URL a
plugin names.

### static `location(Minn\Db $db, Minn\Http\Request $current, ?string $url): ?string`

Internals: `requestFor()` (private, line 29)


## CommentList

`final class Minn\Front\CommentList` · `public/minn/src/Minn/Front/CommentList.php`

The classic threaded comment walk: top-level comments in order (or
reversed), replies nested to the depth allowed under a "children" list,
each item and its close rendered by the caller's closures.

### static `render(array $comments, array $args, Closure $item, Closure $close): string`

- `@param list<array<string, mixed>> $comments rows with comment_ID and comment_parent`
- `@param Closure(array, int): string $item the opening markup for a comment at a depth`
- `@param Closure(array, int): string $close the closing markup`

Internals: `level()` (private, line 45)


## CommentPostController

`final readonly class Minn\Front\CommentPostController` · `public/minn/src/Minn/Front/CommentPostController.php`

wp-comments-post.php: the comment form's target. The reference's
answers, captured: 302 to the comment's anchor (with unapproved and
moderation-hash when held), plain pages with 200 for a missing field,
403 when comments are closed, 409 for a duplicate, 429 for posting too
quickly, and 405 for anything but POST.

- const `FLOOD_SECONDS` = `15`

Used by: `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Content\Comments $comments, Minn\Front\Permalinks $permalinks, Minn\Auth\Authenticator $authenticator, Minn\Auth\Capabilities $capabilities, Minn\Auth\AuthCookies $cookies)
```


### `post(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-comments-post.php`

Internals: `approval()` (private, line 132), `rememberAuthor()` (private, line 139), `moderationHash()` (private, line 149), `notifyModerator()` (private, line 154), `refusal()` (private, line 161)


## DocumentTitle

`final class Minn\Front\DocumentTitle` · `public/minn/src/Minn/Front/DocumentTitle.php`

The document title as parts (title, tagline, page, site) in the order the
reference joins them, so plugin code filtering document_title_parts sees
the same array, and the plain composition the engine prints without one.

Used by: `Minn\Front\Renderer`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

### static `parts(Minn\Front\Resolution $resolution, string $site, string $tagline): array`

- `@param array<string, string> $record the resolved record, when there is one`
- `@return array<string, string>`

### static `compose(array $parts): string`

- `@param array<string, string> $parts`


## Feeds

`final readonly class Minn\Front\Feeds` · `public/minn/src/Minn/Front/Feeds.php`

The syndication feeds, byte for byte in the reference's shape: RSS 2.0
for the site, its archives, and comments; Atom and RDF for the site.
The whitespace inside each item is part of the captured output and is
reproduced as-is.

Used by: `Minn\Engine`, `Minn\Front\ProbeController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Content\Comments $comments, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, string $generatorVersion)
```


### static `contentType(string $kind): string`

### `posts(array $posts, string $kind, string $selfUrl, string $title): string`

The site feed, or an archive's, in the chosen kind. @param list<array> $posts

- `@param list<array> $posts`

### `comments(?Minn\Content\PostRecord $post, string $selfUrl): string`

The site's or one post's comments as RSS 2.0.

### `perFeed(): int`

### static `rfc2822(string $gmt): string`

### static `isoZ(string $gmt): string`

Internals: `rss2()` (private, line 60), `rssItem()` (private, line 85), `atom()` (private, line 112), `rdf()` (private, line 156), `content()` (private, line 248), `latestModified()` (private, line 270), `commentCount()` (private, line 279), `authorName()` (private, line 284), `termNames()` (private, line 290), `cdata()` (private, line 305), `plainExcerpt()` (private, line 310), `language()` (private, line 318), `title()` (private, line 325)


## FrontController

`final readonly class Minn\Front\FrontController` · `public/minn/src/Minn/Front/FrontController.php`

The public site. One catch-all route: resolve the URL, then either
redirect or render, through the active block theme when there is one,
the classic PHP template runner when the theme is classic, and the
interim template otherwise.

Used by: `Minn\Engine`

```php
__construct(Minn\Front\Resolver $resolver, Minn\Front\Renderer $renderer, ?Minn\Theme\PageRenderer $theme = NULL, ?Minn\Front\ProbeController $probes = NULL, ?Minn\Cron\Cron $cron = NULL, ?Minn\Theme\ClassicRenderer $classic = NULL)
```


### `notFound(): Minn\Http\Response`

The themed (or interim) 404 page.

### `show(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /{path*}`


## Kind

`enum Minn\Front\Kind` · `public/minn/src/Minn/Front/Kind.php`

What a public URL resolved to.

Cases: `Home`, `Single`, `Page`, `Category`, `Tag`, `Author`, `Date`, `Search`, `Taxonomy`, `PostTypeArchive`, `NotFound`, `Redirect`

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Front\AdminBar`, `Minn\Front\Canonical`, `Minn\Front\DocumentTitle`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`


## ListingLinks

`final class Minn\Front\ListingLinks` · `public/minn/src/Minn/Front/ListingLinks.php`

The prev/next links a paged listing prints: which page sits either side of
the one being read, and the anchor that points at it. Posts listings and
comment threads both come through here; only the URL builder differs.

### static `neighbours(int $current, int $maxPages): array`

The page before and after the current one, null past either end. A
listing of one page has neither, which is how the navigation blocks
know to print nothing at all.

- `@return array{0: ?int, 1: ?int}`

### static `anchor(string $url, string $attributes, string $text): string`

The anchor the reference prints. Attributes go in verbatim after the
href, and the space that separates them stays even when a caller
supplied none, so the markup reads `<a href="..." >text</a>`.

### static `label(string $label, string $default): string`

A navigation label's bare ampersands become entities, while an ampersand
that already opens an entity is left as it is.

### static `bareCommentPage(string $defaultPage, int $maxPages): int`

Which page of a comment thread the bare permalink shows: the first when
the site reads oldest comments first, the last when it reads newest
first. Every other page carries a comment-page-N segment.

The page count is whatever the caller passed and is never filled in from
the query: a caller that does not say how long the thread is gets no
collapse at all under newest-first, because no page matches an unknown
last page.


## Pagination

`final class Minn\Front\Pagination` · `public/minn/src/Minn/Front/Pagination.php`

Numbered page links in the reference's shape: previous, the end and
middle runs with one ellipsis per gap, the current page as a span, next.

### static `links(array $args, Closure $link): ?array`

- `@param Closure(int): string $link the URL for a page number`
- `@return list<string>|null the link elements, null with fewer than two pages`

### static `format(array $links, string $type): array|string`

- `@param list<string> $links`


## Permalinks

`final readonly class Minn\Front\Permalinks` · `public/minn/src/Minn/Front/Permalinks.php`

Builds public URLs from the site's permalink structure. With an empty
structure every link is a query-string form (?p=, ?cat=); with a
structure, published and private posts get their pretty form and every
other status keeps the query form, which is what the reference emits.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\Diagnostics`, `Minn\Admin\ManageController`, `Minn\Admin\RenderController`, `Minn\Admin\V1Controller`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Menus`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Front\Feeds`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\Sitemaps`, `Minn\Login\LoginController`, `Minn\Media\Uploads`, `Minn\Rest\Api`, `Minn\Rest\CommentObject`, `Minn\Rest\IndexController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RestUrl`, `Minn\Rest\SearchController`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`, `Minn\Theme\Theme`

```php
__construct(Minn\Content\Posts $posts, Minn\Content\Terms $terms, string $home, string $structure, int $frontPageId = 0, int $postsPageId = 0, ?Closure $registry = NULL)
```

- readonly `string $home`
- readonly `string $structure`
- readonly `int $frontPageId`
- readonly `int $postsPageId`

### `typeSlug(string $type): string`

The rewrite slug a plugin gave its post type, else the type's name.

### `taxonomySlug(string $taxonomy): ?string`

The rewrite slug a plugin gave its taxonomy; null for one the engine does not know.

### `forPostTypeArchive(array $type): string`

A post type archive's address: its has_archive slug, or the type's slug when has_archive is true.

### static `fromDb(Minn\Db $db): self`

### `isPretty(): bool`

### `url(string $path = ''): string`

### `forPost(Minn\Content\PostRecord $post): string`

### `forPage(Minn\Content\PostRecord $page): string`

### `pagePath(Minn\Content\PostRecord $page): string`

A page's own pretty path, even for the static front page (its comments feed lives there).

### `forAttachment(Minn\Content\PostRecord $attachment): string`

An attachment's public link: its slug under the parent's permalink
when attached, at the root when not, or the query form under plain
permalinks.

### `forTerm(Minn\Content\TermRecord $term): string`

### `forAuthor(Minn\Content\UserRecord $user): string`

### `forDate(int $year, ?int $month = NULL, ?int $day = NULL): string`

### `forSearch(string $term): string`

### `forPaged(string $baseUrl, int $page): string`

### `structureRegex(): ?string`

A regex over the structure's tokens, so an incoming path can be
matched back to the post it names. Null when the structure has no
identifying token.

Internals: `hasPrettyLink()` (private, line 214), `fill()` (private, line 219)


## PluginRules

`final class Minn\Front\PluginRules` · `public/minn/src/Minn/Front/PluginRules.php`

Rewrite rules a plugin registered through add_rewrite_rule(): the
recorded rules match against the request path the way the reference
matches them, ahead of the engine's own resolution for 'top' rules and
behind it for 'bottom' ones. A match yields the substituted query vars,
kept only when the reference would recognise them (the built-in public
vars plus whatever the query_vars filter admits, which is how a plugin
registers its own).

- const `STATE` = `'rule_query_vars'`
- const `PUBLIC_VARS` = `array (   0 => 'm',   1 => 'p',   2 => 'posts',   3 => 'w',   4 => 'cat',   5 => 'withcomments',   6 => 'withoutcomments',   7 => 's',   8 => 'search',   9 => 'exact',   10 => 'sentence',   11 => 'calendar',   12 => 'page',   13 => 'paged',   14 => 'more',   15 => 'tb',   16 => 'pb',   17 => 'author',   18 => 'order',   19 => 'orderby',   20 => 'year',   21 => 'monthnum',   22 => 'day',   23 => 'hour',   24 => 'minute',   25 => 'second',   26 => 'name',   27 => 'category_name',   28 => 'tag',   29 => 'feed',   30 => 'author_name',   31 => 'pagename',   32 => 'page_id',   33 => 'error',   34 => 'attachment',   35 => 'attachment_id',   36 => 'subpost',   37 => 'subpost_id',   38 => 'preview',   39 => 'robots',   40 => 'favicon',   41 => 'taxonomy',   42 => 'term',   43 => 'cpage',   44 => 'post_type',   45 => 'embed', )` — The reference's public query vars a rule's query string may set.

Used by: `Minn\Front\Resolver`, `Minn\Runtime\MainQuery`

### static `match(string $path, bool $top): ?array`

- `@return array<string, string>|null the query vars of the first matching rule, null with no match`

### static `stashed(): array`

- `@return array<string, string> the vars the matched rule stashed for this request`


## PostNavigation

`final class Minn\Front\PostNavigation` · `public/minn/src/Minn/Front/PostNavigation.php`

The links to the posts either side of this one, and the nav block that
wraps them. The caller's format wraps the anchor: `%link` is the whole
anchor and `%title` the post's title, and the anchor carries rel="prev"
or rel="next".

Titles arrive already filtered; this only assembles.

### static `link(string $url, string $title, string $format, string $linkFormat, bool $previous): string`

### static `ariaLabel(array $args, string $default): string`

The aria-label a navigation block carries: an explicit one wins,
otherwise a caller-supplied screen-reader text stands in for it, and
only with neither does the default apply.

- `@param array<string, mixed> $args the caller's own arguments`


## ProbeController

`final readonly class Minn\Front\ProbeController` · `public/minn/src/Minn/Front/ProbeController.php`

The surface monitors, crawlers, and hosting checks hit that is not a
page: feeds, sitemaps, robots.txt, the XML-RPC and cron endpoints, and
the admin entry point.

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Front\Resolver $resolver, Minn\Front\Feeds $feeds, Minn\Front\Sitemaps $sitemaps, Closure $notFound, ?Minn\Cron\Cron $cron = NULL)
```


### `robots(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /robots.txt`

### `xmlrpc(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /xmlrpc.php`

XML-RPC is not served; GET answers the way the reference does, POST is refused outright.

### `cron(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-cron.php`

### `admin(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-admin`

Route: `GET /wp-admin/{rest*}`

The admin is Minn Admin; the reference's admin path lands there.

### `favicon(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /favicon.ico`

### `sitemapIndex(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xml`

### `sitemap(Minn\Http\Request $request, string $type, string $rest): Minn\Http\Response`

Route: `GET /wp-sitemap-{type:posts|taxonomies|users}-{rest:[a-z_0-9-]+}.xml`

### `sitemapStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xsl`

### `sitemapIndexStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap-index.xsl`

### `siteFeed(Minn\Http\Request $request, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /feed`

Route: `GET /feed/`

Route: `GET /feed/{kind:rss2|rss|atom|rdf}`

Route: `GET /feed/{kind:rss2|rss|atom|rdf}/`

### `commentsFeed(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /comments/feed`

Route: `GET /comments/feed/`

### `pathFeed(Minn\Http\Request $request, string $path, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /{path*}/feed`

Route: `GET /{path*}/feed/`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf}`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf}/`

A post's comment feed, or an archive's feed, by resolving the path in front of /feed/.

### `queryFeed(Minn\Http\Request $request, Minn\Front\Resolution $resolution, string $kind): Minn\Http\Response`

The ?feed= query form on any resolvable path.

Internals: `feed()` (private, line 158), `feedResponse()` (private, line 190), `xml()` (private, line 195)


## Renderer

`final readonly class Minn\Front\Renderer` · `public/minn/src/Minn/Front/Renderer.php`

The interim public theme: one clean template until the block-theme
reader lands. The body-class tokens are the contract (stylesheets and
crawlers key off them); the markup around them is engine-defined.

- const `CSS` = `':root { color-scheme: light dark; } body { margin: 0; font: 17px/1.6 "Hanken Grotesk", "Helvetica Neue", sans-serif; background: #fbfbfc; color: #1a1a1f; } @media (prefers-color-scheme: dark) { body { background: #0b0b0d; color: #ececed; } } .site-header, main, .site-footer { max-width: 680px; margin: 0 auto; padding: 0 24px; } .site-header { padding-top: 32px; } .site-title { font-weight: 800; letter-spacing: -0.02em; text-decoration: none; color: inherit; font-size: 20px; } main { padding-top: 40px; padding-bottom: 40px; } h1 { font-size: 36px; letter-spacing: -0.02em; line-height: 1.15; margin: 0 0 24px; } .post-list { list-style: none; margin: 0; padding: 0; } .post-list li { padding: 18px 0; border-top: 1px solid rgba(128,128,140,0.25); } .post-list a { font-weight: 600; color: inherit; text-decoration: none; font-size: 20px; } .post-list time { display: block; font-size: 13px; opacity: 0.6; } .post-list p { margin: 6px 0 0; opacity: 0.85; } .entry-content img { max-width: 100%; height: auto; } .site-footer { padding-bottom: 32px; font: 12px/1.6 "JetBrains Mono", monospace; opacity: 0.6; }'`

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, int $perPage)
```


### `bodyClasses(Minn\Front\Resolution $resolution): array`

- `@return list<string>`

### `title(Minn\Front\Resolution $resolution): string`

The document title: the item's title with the site name, or the site name alone.

### `render(Minn\Front\Resolution $resolution): string`

Internals: `pageClasses()` (private, line 98), `article()` (private, line 115), `archive()` (private, line 125)


## Resolution

`final readonly class Minn\Front\Resolution` · `public/minn/src/Minn/Front/Resolution.php`

The outcome of resolving a public URL: which kind of thing it names,
the record behind it, and the page number for paginated views. Redirects
carry their target instead.

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\AdminBar`, `Minn\Front\DocumentTitle`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

- readonly `Minn\Front\Kind $kind`
- readonly `Minn\Content\PostRecord|Minn\Content\UserRecord|Minn\Content\TermRecord|array|null $record`
- readonly `int $paged`
- readonly `?string $location`
- readonly `int $status`
- readonly `?string $search`
- readonly `?array $date`
- readonly `?string $authorName`
- readonly `bool $front` — the static front page (show_on_front = page), rendered as a page that is also home
- readonly `bool $preview` — a preview: the reader's autosave replaces the stored content
- readonly `bool $postsPage` — the posts page (page_for_posts): the record is the page, the listing is the blog

### `asPreview(): self`

### static `home(int $paged = 1): self`

### static `single(Minn\Content\PostRecord $post, int $paged = 1): self`

### static `frontPage(Minn\Content\PostRecord $page, int $paged = 1): self`

### static `postsPage(Minn\Content\PostRecord $page, int $paged = 1): self`

The page that stands for the blog: a home listing whose record is the page.

### static `term(string $taxonomy, Minn\Content\TermRecord $term, int $paged = 1): self`

### static `taxonomy(Minn\Content\TermRecord $term, int $paged = 1): self`

A plugin taxonomy's term archive; the record is the term row (with its taxonomy).

### static `postTypeArchive(array $type, int $paged = 1): self`

A plugin post type's archive; the record is the registered type (name, label, ...).

### static `author(string $name, ?Minn\Content\UserRecord $user, int $paged = 1): self`

### static `date(int $year, ?int $month, ?int $day, int $paged = 1): self`

### static `search(string $term, int $paged = 1): self`

### static `notFound(): self`

### static `redirect(string $location, int $status = 301): self`

### `id(): int`


## Resolver

`final readonly class Minn\Front\Resolver` · `public/minn/src/Minn/Front/Resolver.php`

Turns a public URL into a Resolution, following the reference's observed
rules:

- Unpaged content and archives without a trailing slash redirect to the
slashed form, as typed (case kept); paged views, search, and 404s do not.
- Query-var forms (?p=, ?page_id=, ?cat=, ?tag=, ?author=, ?m=, ?name=,
?pagename=) redirect to the pretty form when the target is public.
- Archive-shaped paths (category, tag, author, search, a four-digit year)
are strict: a mismatch is a 404 with no guessing.
- Plain-segment paths try the page hierarchy, then the post structure,
then redirect to the closest published page or post whose name starts
with the last segment, pages before posts, newest first.
- Empty term and date archives are 404; an author archive is 200 for any
name, even one that belongs to nobody.
- Non-public posts are 404 to anonymous readers and served to a reader
who can edit them.

Used by: `Minn\Engine`, `Minn\Front\Canonical`, `Minn\Front\FrontController`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Terms $terms, Minn\Front\Permalinks $permalinks, Closure $canReadUnpublished, int $perPage)
```
- `@param Closure(array $post): bool $canReadUnpublished`


### static `fromDb(Minn\Db $db, Closure $canReadUnpublished): self`

### `permalinks(): Minn\Front\Permalinks`

### `perPage(): int`

### `resolve(Minn\Http\Request $request, bool $canonical = true): Minn\Front\Resolution`

$canonical mirrors the reference's redirect_canonical rule: only GET
and HEAD get trailing-slash, pretty-URL, and 404-guess redirects;
every other method renders what the query alone finds, as typed.

### static `dateRange(int $year, ?int $month, ?int $day): ?array`

- `@return array{0: string, 1: string}|null`

Internals: `fromRuleVars()` (private, line 114), `resolvePath()` (private, line 139), `resolveQueryVars()` (private, line 199), `dateRedirect()` (private, line 281), `home()` (private, line 297), `pluginRoute()` (private, line 319), `segmentsOf()` (private, line 354), `taxonomyArchive()` (private, line 363), `termArchive()` (private, line 377), `termResolution()` (private, line 390), `authorArchive()` (private, line 399), `dateArchive()` (private, line 416), `resolveContent()` (private, line 459), `resolveSingle()` (private, line 495), `formerSlug()` (private, line 537), `singleOrRedirect()` (private, line 550), `readable()` (private, line 559), `pages()` (private, line 576)


## SitemapXml

`final class Minn\Front\SitemapXml` · `public/minn/src/Minn/Front/SitemapXml.php`

The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer.

Used by: `Minn\Front\Sitemaps`

### static `index(array $entries, ?string $stylesheet): string`

- `@param list<array<string, string|null>> $entries each a map of element name => text; null values are skipped`

### static `urlset(array $entries, ?string $stylesheet): string`

- `@param list<array<string, string|null>> $entries`

Internals: `elements()` (private, line 24), `document()` (private, line 39)


## Sitemaps

`final readonly class Minn\Front\Sitemaps` · `public/minn/src/Minn/Front/Sitemaps.php`

The sitemap index and its providers (posts, pages, categories, tags,
authors), in the reference's shape: one file per provider and page, 2000
URLs a page, lastmod on content only.

- const `PER_PAGE` = `2000`

Used by: `Minn\Engine`, `Minn\Front\ProbeController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `index(): string`

### `page(string $type, string $subtype, int $page): ?string`

One provider page, or null when the name or page does not exist.

### static `stylesheet(bool $index): string`

The engine's own stylesheet for browsers that open a sitemap.

Internals: `providers()` (private, line 56), `contentUrls()` (private, line 82), `termUrls()` (private, line 105), `userUrls()` (private, line 120), `authors()` (private, line 127), `iso()` (private, line 151)


## TermLists

`final class Minn\Front\TermLists` · `public/minn/src/Minn/Front/TermLists.php`

The two term listings themes print: the nested category list and the
tag cloud, built from term rows the caller already fetched and links the
caller resolves.

### static `parentChain(array $line, bool $link, string $separator, bool $bySlug): string`

The chain of names from the outermost ancestor down to the term itself,
each one separated and optionally wrapped in its own link. The separator
trails the last name too, so a breadcrumb reads "Root/Mid/Leaf/".

- `@param list<array{name: string, slug: string, link: string}> $line outermost first, the term itself last`

### static `categoryList(array $terms, array $args, Closure $link): string`

- `@param list<array<string, mixed>> $terms rows with term_id, name, slug, count, parent`
- `@param array<string, mixed> $args wp_list_categories arguments`
- `@param Closure(array): string $link`

### static `categoryWrapper(array $args): array`

- `@return list<string> the categories block wrapper, before and after the items`

### static `tagCloud(array $tags, array $args): ?string`

- `@param list<array<string, mixed>> $tags rows with term_id, name, count, plus "link"`
- `@param array<string, mixed> $args wp_generate_tag_cloud arguments`

### static `dropdownOptions(array $terms, array $args): string`

The option elements of a category dropdown, nested by depth when the
caller asked for a hierarchy.

- `@param list<array<string, mixed>> $terms`

Internals: `options()` (private, line 118), `sorted()` (private, line 137), `orphans()` (private, line 150), `items()` (private, line 157)

