# `Minn\Front`

URL resolution, permalinks, feeds, sitemaps and the public page

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AdminBar`](#adminbar) | final readonly class | 318 | The Minn bar on the public site: the same server-rendered chrome the |
| [`Archives`](#archives) | final readonly class | 62 | The archive periods wp_get_archives lists: months, years, days and weeks |
| [`AssetsController`](#assetscontroller) | final readonly class | 32 | The engine's own static assets, served under a reserved path. |
| [`Calendar`](#calendar) | final readonly class | 99 | One month as the calendar widget and block draw it: a table whose caption |
| [`CalendarLabels`](#calendarlabels) | final readonly class | 44 | The words a calendar prints: weekday names Sunday first, the short form |
| [`Canonical`](#canonical) | final class | 28 | Where a URL should redirect to, by the engine's own resolution: the |
| [`CommentList`](#commentlist) | final class | 52 | The classic threaded comment walk: top-level comments in order (or |
| [`CommentPostController`](#commentpostcontroller) | final readonly class | 174 | wp-comments-post.php: the comment form's target. The reference's |
| [`CustomLogo`](#customlogo) | final class | 27 | The site logo a theme prints, as get_custom_logo builds it (probe |
| [`DocumentTitle`](#documenttitle) | final class | 47 | The document title as parts (title, tagline, page, site) in the order the |
| [`FeedController`](#feedcontroller) | final readonly class | 94 | The feeds: the site's, the comments', a post's or an archive's by the |
| [`Feeds`](#feeds) | final readonly class | 316 | The syndication feeds, byte for byte in the reference's shape: RSS 2.0 |
| [`FrontController`](#frontcontroller) | final readonly class | 55 | The public site. One catch-all route: resolve the URL, then either |
| [`Kind`](#kind) | enum | 17 | What a public URL resolved to. |
| [`ListSpacing`](#listspacing) | final readonly class | 30 | How a page list is spaced: the reference's "preserve" keeps newlines and |
| [`ListingLinks`](#listinglinks) | final class | 53 | The prev/next links a paged listing prints: which page sits either side of |
| [`Maintenance`](#maintenance) | final class | 28 | Maintenance mode, as hosting tools and updaters switch it on: a |
| [`PageLinks`](#pagelinks) | final class | 40 | The links between the pages of a post split with <!--nextpage-->, as |
| [`PageList`](#pagelist) | final readonly class | 96 | The page hierarchy as wp_list_pages and wp_dropdown_pages draw it: nested |
| [`Pagination`](#pagination) | final class | 62 | Numbered page links in the reference's shape: previous, the end and |
| [`Permalinks`](#permalinks) | final readonly class | 253 | Builds public URLs from the site's permalink structure. With an empty |
| [`PluginRules`](#pluginrules) | final class | 71 | Rewrite rules a plugin registered through add_rewrite_rule(): the |
| [`PostEmbed`](#postembed) | final class | 104 | A post as other sites embed it, the oEmbed provider side, as the |
| [`PostNavigation`](#postnavigation) | final class | 36 | The links to the posts either side of this one, and the nav block that |
| [`ProbeController`](#probecontroller) | final readonly class | 57 | The surface monitors, crawlers, and hosting checks hit that is not a |
| [`Redirects`](#redirects) | enum | 17 | Whether a resolution may answer with a canonical redirect. A GET or HEAD |
| [`Renderer`](#renderer) | final readonly class | 156 | The interim public theme: one clean template until the block-theme |
| [`Resolution`](#resolution) | final readonly class | 108 | The outcome of resolving a public URL: which kind of thing it names, |
| [`Resolver`](#resolver) | final readonly class | 554 | Turns a public URL into a Resolution, following the reference's observed |
| [`SingleAddresses`](#singleaddresses) | final readonly class | 61 | The addresses a single answers to besides its own, as the reference |
| [`SitemapController`](#sitemapcontroller) | final readonly class | 46 | The sitemap index, its pages, and the two stylesheets. |
| [`SitemapXml`](#sitemapxml) | final class | 43 | The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer. |
| [`Sitemaps`](#sitemaps) | final readonly class | 147 | The sitemap index and its providers (posts, pages, categories, tags, |
| [`TermLists`](#termlists) | final class | 178 | The two term listings themes print: the nested category list and the |

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

The bar for a signed-in editor, or null for anyone else.

### `head(): string`

The stylesheet link for the head.

### `render(Minn\Front\Resolution $resolution): string`

The bar markup, its config, and the script, for the end of the body.

Internals: `markup()` (private, line 83), `siteMenu()` (private, line 136), `statusMenu()` (private, line 154), `newMenu()` (private, line 176), `notificationsMenu()` (private, line 190), `userMenu()` (private, line 203), `status()` (private, line 221), `editTarget()` (private, line 238), `commands()` (private, line 254), `searchTypes()` (private, line 279), `customSchemeStyle()` (private, line 288), `appUrl()` (private, line 307), `appPath()` (private, line 312), `assetUrl()` (private, line 317), `icon()` (private, line 322), `gridIcon()` (private, line 327), `menuItem()` (private, line 332)


## Archives

`final readonly class Minn\Front\Archives` · `public/minn/src/Minn/Front/Archives.php`

The archive periods wp_get_archives lists: months, years, days and weeks
that hold a published post of a type, newest first, each with its post
count and archive URL, plus the post-by-post and alphabetical listings.
Labels come from the caller's date formatter so the site's locale and
week start apply; the week label joins the week's first and last day.

```php
__construct(Minn\Content\Posts $posts, Closure $date, Closure $dateLink, Closure $week)
```
- `@param Closure(string, string): string $date formats a MySQL datetime with a PHP date format`
- `@param Closure(int, ?int, ?int): string $dateLink the year, month, or day archive URL`
- `@param Closure(string): array{start: int, end: int} $week the week's first and last second around a datetime`


### `periods(string $granularity, string $type, string $order, int $limit, Closure $weekLink): array`

The periods of one granularity: monthly, yearly, daily, or weekly.

- `@return list<array{period: array{year: int, month: int, day: int, week: int}, url: string, text: string, count: int}>`

### `posts(string $type, string $orderBy, string $order, int $limit, Closure $title, Closure $link): array`

The posts themselves, by date or by title.

- `@param Closure(int, string): string $title the post's title as displayed (or its id when empty)`
- `@param Closure(int): string $link`
- `@return list<array{url: string, text: string, count: int}>`

Internals: `weekPeriod()` (private, line 56)


## AssetsController

`final readonly class Minn\Front\AssetsController` · `public/minn/src/Minn/Front/AssetsController.php`

The engine's own static assets, served under a reserved path.

- const `TYPES` = `array (   'css' => 'text/css',   'js' => 'application/javascript', )`

Used by: `Minn\Engine`

```php
__construct(string $assetsDir)
```


### `asset(Minn\Http\Request $request, string $path): Minn\Http\Response`

Route: `GET /minn/assets/{path*} (public)`

One engine asset file.

### `jquery(Minn\Http\Request $request, string $file): Minn\Http\Response`

Route: `GET /wp-includes/js/jquery/{file:[a-z0-9.-]+\.js} (public)`

The MIT libraries the engine ships, served at the paths the reference registers them under.


## Calendar

`final readonly class Minn\Front\Calendar` · `public/minn/src/Minn/Front/Calendar.php`

One month as the calendar widget and block draw it: a table whose caption
names the month, whose head lists the week from the site's first day, and
whose cells link the days a post was published on to that day's archive;
today's cell carries the id the stylesheet lights; the nav below links
the nearest months with posts on either side. Markup, whitespace and the
attribute order of the two padding cells follow the reference byte for
byte, since themes style the table by those hooks.

- const `FIXED_DATE` = `'F j, Y'`

```php
__construct(int $year, int $month, int $weekStart, Minn\Front\CalendarLabels $labels, Closure $link)
```
- `@param Closure(int, int, ?int): string $link the month archive URL, or the day's when a day is given`


### `render(array $postDays, ?array $previous, ?array $next, ?array $today): string`

The table and its navigation.

- `@param list<int> $postDays days of the month with a published post`
- `@param array{int, int}|null $previous the nearest earlier month with posts, as [year, month]`
- `@param array{int, int}|null $next the nearest later month with posts`
- `@param array{int, int, int}|null $today the site's current date, as [year, month, day]`

Internals: `table()` (private, line 47), `head()` (private, line 73), `cell()` (private, line 87), `navigation()` (private, line 102), `abbreviation()` (private, line 113)


## CalendarLabels

`final readonly class Minn\Front\CalendarLabels` · `public/minn/src/Minn/Front/CalendarLabels.php`

The words a calendar prints: weekday names Sunday first, the short form
each column shows (an initial or an abbreviation), month names by number,
and the abbreviation the month navigation uses.

Used by: `Minn\Front\Calendar`

```php
__construct(array $weekdays, array $weekdayShort, array $months, array $monthAbbrev)
```
- `@param array<int, string> $weekdays Sunday first`
- `@param array<string, string> $weekdayShort full weekday name to the column heading`
- `@param array<int, string> $months 1 to 12`
- `@param array<string, string> $monthAbbrev full month name to its abbreviation`

- readonly `array $weekdays`
- readonly `array $weekdayShort`
- readonly `array $months`
- readonly `array $monthAbbrev`

### static `english(): self`

The English tables, with weekday initials as the reference shows by default.

### static `englishAbbreviated(): self`

The English tables with three-letter weekday abbreviations.

Internals: `fromEnglish()` (private, line 43)


## Canonical

`final class Minn\Front\Canonical` · `public/minn/src/Minn/Front/Canonical.php`

Where a URL should redirect to, by the engine's own resolution: the
canonical form of a request (trailing slash, `?p=` to permalink, doubled
slashes, former slugs). The front controller applies this before any
plugin runs; the `redirect_canonical()` facade asks it again for a URL a
plugin names.

### static `location(Minn\Db $db, Minn\Http\Request $current, ?string $url): ?string`

The canonical URL of a request, or null when it already is one.

Internals: `requestFor()` (private, line 30)


## CommentList

`final class Minn\Front\CommentList` · `public/minn/src/Minn/Front/CommentList.php`

The classic threaded comment walk: top-level comments in order (or
reversed), replies nested to the depth allowed under a "children" list,
each item and its close rendered by the caller's closures.

### static `render(array $comments, array $args, Closure $item, Closure $close): string`

A threaded comment list the way the reference walks it.

- `@param list<array<string, mixed>> $comments rows with comment_ID and comment_parent`
- `@param Closure(array, int): string $item the opening markup for a comment at a depth`
- `@param Closure(array, int): string $close the closing markup`

Internals: `level()` (private, line 47)


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

Route: `* /wp-comments-post.php (public)`

The comment form's target.

Internals: `postWithoutPlugins()` (private, line 62), `postWithPlugins()` (private, line 150), `approval()` (private, line 171), `rememberAuthor()` (private, line 178), `moderationHash()` (private, line 188), `notifyModerator()` (private, line 193), `refusal()` (private, line 200)


## CustomLogo

`final class Minn\Front\CustomLogo` · `public/minn/src/Minn/Front/CustomLogo.php`

The site logo a theme prints, as get_custom_logo builds it (probe
plugin-helpers): the custom_logo theme mod's image at full size, never
lazy, its alt its own or else the site's name, linked home (marked the
current page on the front page). A theme that unlinks the home page logo
gets a plain span there, the image then decorative (an empty alt). The
image's attributes go through get_custom_logo_image_attributes and the
whole through get_custom_logo; no logo is an empty string, or in the
Customizer a hidden placeholder it can fill in.

### static `html(int $blogId): string`

The logo's markup, through get_custom_logo; empty when the site has none.


## DocumentTitle

`final class Minn\Front\DocumentTitle` · `public/minn/src/Minn/Front/DocumentTitle.php`

The document title as parts (title, tagline, page, site) in the order the
reference joins them, so plugin code filtering document_title_parts sees
the same array, and the plain composition the engine prints without one.

Used by: `Minn\Front\Renderer`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\PageRenderer`

### static `parts(Minn\Front\Resolution $resolution, string $site, string $tagline): array`

The title parts for a resolution, the way the reference assembles them.

- `@param array<string, string> $record the resolved record, when there is one`
- `@return array<string, string>`

### static `compose(array $parts): string`

The parts joined with the reference's separator.

- `@param array<string, string> $parts`


## FeedController

`final readonly class Minn\Front\FeedController` · `public/minn/src/Minn/Front/FeedController.php`

The feeds: the site's, the comments', a post's or an archive's by the
path in front of /feed/, and the ?feed= query form on any page.

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Front\Resolver $resolver, Minn\Front\Feeds $feeds, Closure $notFound)
```


### `siteFeed(Minn\Http\Request $request, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /feed (public)`

Route: `GET /feed/ (public)`

Route: `GET /feed/{kind:rss2|rss|atom|rdf} (public)`

Route: `GET /feed/{kind:rss2|rss|atom|rdf}/ (public)`

The site feed in one of its kinds.

### `commentsFeed(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /comments/feed (public)`

Route: `GET /comments/feed/ (public)`

The comments feed.

### `pathFeed(Minn\Http\Request $request, string $path, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /{path*}/feed (public)`

Route: `GET /{path*}/feed/ (public)`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf} (public)`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf}/ (public)`

A post's comment feed, or an archive's feed, by resolving the path in front of /feed/.

### `queryFeed(Minn\Http\Request $request, Minn\Front\Resolution $resolution, string $kind): Minn\Http\Response`

The ?feed= query form on any resolvable path.

Internals: `feed()` (private, line 82), `feedResponse()` (private, line 114)


## Feeds

`final readonly class Minn\Front\Feeds` · `public/minn/src/Minn/Front/Feeds.php`

The syndication feeds, byte for byte in the reference's shape: RSS 2.0
for the site, its archives, and comments; Atom and RDF for the site.
The whitespace inside each item is part of the captured output and is
reproduced as-is.

Used by: `Minn\Engine`, `Minn\Front\FeedController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Content\Comments $comments, Minn\Content\Users $users, Minn\Front\Permalinks $permalinks, string $generatorVersion)
```


### static `contentType(string $kind): string`

The content type of a feed kind.

### `posts(array $posts, string $kind, string $selfUrl, string $title): string`

The site feed, or an archive's, in the chosen kind. @param list<array> $posts

- `@param list<array> $posts`

### `comments(?Minn\Content\PostRecord $post, string $selfUrl): string`

The site's or one post's comments as RSS 2.0.

### `perFeed(): int`

How many items a feed carries.

### static `rfc2822(string $gmt): string`

A GMT datetime in RFC 2822 form.

### static `isoZ(string $gmt): string`

A GMT datetime in ISO 8601 form.

Internals: `rss2()` (private, line 61), `rssItem()` (private, line 86), `atom()` (private, line 113), `rdf()` (private, line 157), `content()` (private, line 249), `latestModified()` (private, line 272), `commentCount()` (private, line 281), `authorName()` (private, line 286), `termNames()` (private, line 292), `cdata()` (private, line 307), `plainExcerpt()` (private, line 312), `language()` (private, line 320), `title()` (private, line 327)


## FrontController

`final readonly class Minn\Front\FrontController` · `public/minn/src/Minn/Front/FrontController.php`

The public site. One catch-all route: resolve the URL, then either
redirect or render, through the active block theme when there is one,
the classic PHP template runner when the theme is classic, and the
interim template otherwise.

Used by: `Minn\Engine`

```php
__construct(Minn\Front\Resolver $resolver, Minn\Front\Renderer $renderer, ?Minn\Theme\PageRenderer $theme = NULL, ?Minn\Front\FeedController $feeds = NULL, ?Minn\Cron\Cron $cron = NULL, ?Minn\Theme\ClassicRenderer $classic = NULL)
```


### `notFound(): Minn\Http\Response`

The themed (or interim) 404 page.

### `show(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /{path*} (public)`

The public page for any path; when scheduled work is due, the run follows the response.

Internals: `page()` (private, line 61)


## Kind

`enum Minn\Front\Kind` · `public/minn/src/Minn/Front/Kind.php`

What a public URL resolved to.

Cases: `Home`, `Single`, `Page`, `Category`, `Tag`, `Author`, `Date`, `Search`, `Taxonomy`, `PostTypeArchive`, `NotFound`, `Redirect`

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Front\AdminBar`, `Minn\Front\Canonical`, `Minn\Front\DocumentTitle`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\Renderer`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`


## ListSpacing

`final readonly class Minn\Front\ListSpacing` · `public/minn/src/Minn/Front/ListSpacing.php`

How a page list is spaced: the reference's "preserve" keeps newlines and
one tab per level, "discard" prints the items on one line. The link
wrappers ride along so the list has one place to read them.

Used by: `Minn\Front\PageList`

- readonly `string $linkBefore`
- readonly `string $linkAfter`

### static `preserved(string $linkBefore = '', string $linkAfter = ''): self`

Newlines and tabs kept, the reference's default.

### static `discarded(string $linkBefore = '', string $linkAfter = ''): self`

Everything on one line, the reference's "discard".

### `newline(): string`

A newline, or nothing when spacing is discarded.

### `tab(): string`

One tab of indent, or nothing when spacing is discarded.


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


## Maintenance

`final class Minn\Front\Maintenance` · `public/minn/src/Minn/Front/Maintenance.php`

Maintenance mode, as hosting tools and updaters switch it on: a
.maintenance file in the webroot whose $upgrading is less than ten minutes
old. While it holds, every request gets the site's own maintenance.php
(which prints its page and stops) or the 503 page; an older file is ignored.

- const `WINDOW` = `600`

### static `answer(string $abspath, string $contentDir, int $now): ?Minn\Http\Response`

The answer while maintenance holds, or null when the site is open.


## PageLinks

`final class Minn\Front\PageLinks` · `public/minn/src/Minn/Front/PageLinks.php`

The links between the pages of a post split with <!--nextpage-->, as
wp_link_pages writes them (probe placeholders-a). By number: the before
markup, each page (the current one a span, unless the whole post is not
being shown and this is its first page) after a space for the first and
the separator for the rest, the after markup. By next and previous, only
where the whole post is shown: the previous page's link, the separator,
the next page's. Nothing for a post in one page.

### static `render(array $args, int $page, int $pages, int $more, Closure $open, Closure $filter): string`

The links for the page being shown of a split post.

- `@param array<string, mixed> $args wp_link_pages's arguments with their defaults`
- `@param Closure(int): string $open the opening anchor of page $i`
- `@param Closure(string, int): string $filter a link through wp_link_pages_link`


## PageList

`final readonly class Minn\Front\PageList` · `public/minn/src/Minn/Front/PageList.php`

The page hierarchy as wp_list_pages and wp_dropdown_pages draw it: nested
list items with the reference's page_item classes (has-children, current,
ancestor, parent), children indented one tab per level under a
<ul class='children'>, and the flat dropdown whose options carry a level
class and three non-breaking spaces per level. A page whose parent is not
in the set stands at the top, so include, exclude and child_of all nest
whatever remains.

```php
__construct(array $pages, array $currentTrail)
```
- `@param list<array{id: int, parent: int, title: string, link: string}> $pages in display order`
- `@param list<int> $currentTrail the queried page and its ancestors, the page first`


### `items(int $childOf, int $depth, Minn\Front\ListSpacing $spacing): string`

The list items under a page (0 for the whole tree), to a depth
(0 unlimited, -1 flat), with whitespace the way the reference keeps it.

### `options(int $childOf, int $depth, int $selected, Closure $value): string`

The dropdown options under a page, to a depth, with one selected.

Internals: `flat()` (private, line 51), `level()` (private, line 60), `item()` (private, line 76), `optionLevel()` (private, line 101)


## Pagination

`final class Minn\Front\Pagination` · `public/minn/src/Minn/Front/Pagination.php`

Numbered page links in the reference's shape: previous, the end and
middle runs with one ellipsis per gap, the current page as a span, next.

### static `links(array $args, Closure $link): ?array`

The page links the reference's paginate_links builds, or null for one page.

- `@param Closure(int): string $link the URL for a page number`
- `@return list<string>|null the link elements, null with fewer than two pages`

### static `format(array $links, string $type): array|string`

The links in the requested shape.

- `@param list<string> $links`


## Permalinks

`final readonly class Minn\Front\Permalinks` · `public/minn/src/Minn/Front/Permalinks.php`

Builds public URLs from the site's permalink structure. With an empty
structure every link is a query-string form (?p=, ?cat=); with a
structure, published and private posts get their pretty form and every
other status keeps the query form, which is what the reference emits.

- const `QUERY_ONLY` = `array (   0 => 'wp_pattern_category',   1 => 'wp_theme',   2 => 'wp_template_part_area', )` — Core taxonomies with no front-end archive: their terms link by query only.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\ThemesController`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Menus`, `Minn\Content\SiteIcon`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Front\Sitemaps`, `Minn\Login\LoginController`, `Minn\Media\Uploads`, `Minn\Ops\Diagnostics`, `Minn\Rest\CommentObject`, `Minn\Rest\IndexController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RestUrl`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`, `Minn\Theme\Theme`, `Minn\Theme\ThemeStyles`

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

Link building from the site's own settings.

### `isPretty(): bool`

Whether the site uses a permalink structure.

### `url(string $path = ''): string`

A URL under the home.

### `forPost(Minn\Content\PostRecord $post): string`

A post's permalink.

### `forPage(Minn\Content\PostRecord $page): string`

A page's permalink, the home for the front page.

### `pagePath(Minn\Content\PostRecord $page): string`

A page's own pretty path, even for the static front page (its comments feed lives there).

### `pageToken(): string`

A page link with the %pagename% token left in place, for a plugin that fills it itself.

### `pageAsPublished(Minn\Content\PostRecord $page): string`

A page's pretty path as it will read once published, for a sample of an unpublished one.

### `forAttachment(Minn\Content\PostRecord $attachment): string`

An attachment's public link: its slug under the parent's permalink
when attached, at the root when not, or the query form under plain
permalinks.

### `forTerm(Minn\Content\TermRecord $term): string`

A term's archive URL.

### `forAuthor(Minn\Content\UserRecord $user): string`

An author's archive URL.

### `forDate(int $year, ?int $month = NULL, ?int $day = NULL): string`

A date archive's URL.

### `forSearch(string $term): string`

A search's URL.

### `forPaged(string $baseUrl, int $page): string`

A listing URL for a page number.

### `structureRegex(): ?string`

A regex over the structure's tokens, so an incoming path can be
matched back to the post it names. Null when the structure has no
identifying token.

Internals: `hasPrettyLink()` (private, line 243), `fill()` (private, line 248)


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

A plugin's rewrite rule that matches the path, or null.

- `@return array<string, string>|null the query vars of the first matching rule, null with no match`

### static `stashed(): array`

The query vars the matched rule stashed.

- `@return array<string, string> the vars the matched rule stashed for this request`


## PostEmbed

`final class Minn\Front\PostEmbed` · `public/minn/src/Minn/Front/PostEmbed.php`

A post as other sites embed it, the oEmbed provider side, as the
reference answers (probe oembed): the data (version, the site as
provider, the author or else the site, the title) through
oembed_response_data, whose default makes it "rich" with the embed
markup: a blockquote linking the post, a sandboxed iframe of its /embed/
page carrying a fresh secret, and the inline script that sizes it. Only
a publicly viewable post is embeddable; the width is held between 200
and 600 (oembed_min_max_width) and the height is a 16:9 share of it, at
least 200.

### static `data(mixed $post, int $width): array|false`

get_oembed_response_data: the data, or false when the post cannot be embedded. @return array<string, mixed>|false

- `@return array<string, mixed>|false`

### static `rich(array $data, WP_Post $post, int $width, int $height): array`

get_oembed_response_data_rich: the size, the markup, and a thumbnail when the post has one. @param array<string, mixed> $data @return array<string, mixed>

- `@param array<string, mixed> $data @return array<string, mixed>`

### static `html(int $width, int $height, mixed $post): string|false`

get_post_embed_html: the blockquote, the iframe and its script, through embed_html; false for no post.

### static `url(mixed $post): string|false`

get_post_embed_url: the post's /embed/ address (?embed=true without pretty links), through post_embed_url.

### static `xml(array $data): string`

_oembed_create_xml: the data as an oembed document, nested arrays as nested elements. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

Internals: `append()` (private, line 110)


## PostNavigation

`final class Minn\Front\PostNavigation` · `public/minn/src/Minn/Front/PostNavigation.php`

The links to the posts either side of this one, and the nav block that
wraps them. The caller's format wraps the anchor: `%link` is the whole
anchor and `%title` the post's title, and the anchor carries rel="prev"
or rel="next".

Titles arrive already filtered; this only assembles.

### static `previous(string $url, string $title, string $format, string $linkFormat): string`

A previous or next post link in the reference's format.

### static `next(string $url, string $title, string $format, string $linkFormat): string`

The next-post link in the reference's format.

### static `ariaLabel(array $args, string $default): string`

The aria-label a navigation block carries: an explicit one wins,
otherwise a caller-supplied screen-reader text stands in for it, and
only with neither does the default apply.

- `@param array<string, mixed> $args the caller's own arguments`

Internals: `link()` (private, line 31)


## ProbeController

`final readonly class Minn\Front\ProbeController` · `public/minn/src/Minn/Front/ProbeController.php`

The surface monitors, crawlers, and hosting checks hit that is not a
page: robots.txt, the XML-RPC and cron endpoints, the admin entry point,
and the favicon. Feeds and sitemaps have controllers of their own.

Used by: `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Content\SiteIcon $icon, ?Minn\Cron\Cron $cron = NULL)
```


### `robots(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /robots.txt (public)`

robots.txt.

### `xmlrpc(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /xmlrpc.php (public)`

XML-RPC is not served; GET answers the way the reference does, POST is refused outright.

### `cron(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-cron.php (public)`

wp-cron.php: runs what is due.

### `admin(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-admin (public)`

Route: `GET /wp-admin/{rest*} (public)`

The admin is Minn Admin; the reference's admin path lands there.

### `favicon(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /favicon.ico (public)`

The site icon, or the reference's default.


## Redirects

`enum Minn\Front\Redirects` · `public/minn/src/Minn/Front/Redirects.php`

Whether a resolution may answer with a canonical redirect. A GET or HEAD
follows them (the trailing slash, the pretty permalink for ?p=, the
guessed destination); any other method holds and resolves the path as
typed, as the reference's redirect_canonical bails on a POST.

Cases: `Follow`, `Hold`

Used by: `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`

### static `forMethod(Minn\Http\Method $method): self`

The mode a request's method allows.

### `follows(): bool`

Whether a canonical redirect may be answered.


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

The body classes a resolution carries.

- `@return list<string>`

### `title(Minn\Front\Resolution $resolution): string`

The document title: the item's title with the site name, or the site name alone.

### `render(Minn\Front\Resolution $resolution): string`

The interim page for a resolution, without a theme.

Internals: `pageClasses()` (private, line 103), `article()` (private, line 120), `archive()` (private, line 130)


## Resolution

`final readonly class Minn\Front\Resolution` · `public/minn/src/Minn/Front/Resolution.php`

The outcome of resolving a public URL: which kind of thing it names,
the record behind it, and the page number for paginated views. Redirects
carry their target instead.

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Front\AdminBar`, `Minn\Front\DocumentTitle`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

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

The same resolution marked as a preview.

### static `home(int $paged = 1): self`

The home listing.

### static `single(Minn\Content\PostRecord $post, int $paged = 1): self`

A single post or page.

### static `frontPage(Minn\Content\PostRecord $page, int $paged = 1): self`

The static front page.

### static `postsPage(Minn\Content\PostRecord $page, int $paged = 1): self`

The page that stands for the blog: a home listing whose record is the page.

### static `term(string $taxonomy, Minn\Content\TermRecord $term, int $paged = 1): self`

A category or tag archive.

### static `taxonomy(Minn\Content\TermRecord $term, int $paged = 1): self`

A plugin taxonomy's term archive; the record is the term row (with its taxonomy).

### static `postTypeArchive(array $type, int $paged = 1): self`

A plugin post type's archive; the record is the registered type (name, label, ...).

### static `author(string $name, ?Minn\Content\UserRecord $user, int $paged = 1): self`

An author archive.

### static `date(int $year, ?int $month, ?int $day, int $paged = 1): self`

A date archive.

### static `search(string $term, int $paged = 1): self`

A search.

### static `notFound(): self`

A 404.

### static `redirect(string $location, int $status = 301): self`

A redirect.

### `id(): int`

The record's id, post or term.


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

Used by: `Minn\Engine`, `Minn\Front\Canonical`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\Renderer`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Terms $terms, Minn\Front\Permalinks $permalinks, Closure $canReadUnpublished, int $perPage)
```
- `@param Closure(array $post): bool $canReadUnpublished`


### static `fromDb(Minn\Db $db, Closure $canReadUnpublished): self`

A resolver over the site's own settings.

### `permalinks(): Minn\Front\Permalinks`

The link builder.

### `perPage(): int`

Posts per page.

### `resolve(Minn\Http\Request $request): Minn\Front\Resolution`

$canonical mirrors the reference's redirect_canonical rule: only GET
and HEAD get trailing-slash, pretty-URL, and 404-guess redirects;
every other method renders what the query alone finds, as typed.

### static `dateRange(int $year, ?int $month, ?int $day): ?array`

The site-local bounds of a date archive, or null when the date is invalid.

- `@return array{0: string, 1: string}|null`

Internals: `fromRuleVars()` (private, line 118), `resolvePath()` (private, line 143), `resolveQueryVars()` (private, line 211), `dateRedirect()` (private, line 294), `home()` (private, line 311), `pluginRoute()` (private, line 333), `segmentsOf()` (private, line 368), `taxonomyArchive()` (private, line 377), `termArchive()` (private, line 391), `termResolution()` (private, line 404), `authorArchive()` (private, line 413), `dateArchive()` (private, line 430), `resolveContent()` (private, line 477), `resolveSingle()` (private, line 518), `elsewhere()` (private, line 556), `singleOrRedirect()` (private, line 561), `readable()` (private, line 570), `pages()` (private, line 587)


## SingleAddresses

`final readonly class Minn\Front\SingleAddresses` · `public/minn/src/Minn/Front/SingleAddresses.php`

The addresses a single answers to besides its own, as the reference
treats them: a slug the post used to have (alone, with a page number, or
with an embed or trackback suffix), and its comment-page-N addresses.

Used by: `Minn\Front\Resolver`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks)
```


### `formerSlug(string $slug, int $paged = 1): ?Minn\Front\Resolution`

A slug a post used to have redirects to where the post lives now,
keeping the page number and dropping the query string; the lookup
ignores status because the reference does (unreadable posts land on
their `?p=` form and answer 404 there).

### `commentPage(?Minn\Front\Resolution $single, Minn\Front\Redirects $redirects): Minn\Front\Resolution`

A single's comment-page-N address: served as the single when the site
pages its comments, sent to the single's own address when it does
not, as the reference does. (The engine does not page the comment
list itself yet; contracts/front.)

### `formerSuffix(Minn\Front\Resolution $redirect, string $suffix): Minn\Front\Resolution`

A former slug asked for with a suffix: embed follows the post to its
new embed address, trackback goes to the post itself. The front
page's redirect to the root keeps its own handling.


## SitemapController

`final readonly class Minn\Front\SitemapController` · `public/minn/src/Minn/Front/SitemapController.php`

The sitemap index, its pages, and the two stylesheets.

Used by: `Minn\Engine`

```php
__construct(Minn\Front\Sitemaps $sitemaps, Closure $notFound)
```


### `sitemapIndex(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xml (public)`

The sitemap index.

### `sitemap(Minn\Http\Request $request, string $type, string $rest): Minn\Http\Response`

Route: `GET /wp-sitemap-{type:posts|taxonomies|users}-{rest:[a-z_0-9-]+}.xml (public)`

One sitemap page.

### `sitemapStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xsl (public)`

The sitemap stylesheet.

### `sitemapIndexStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap-index.xsl (public)`

The sitemap index stylesheet.

Internals: `xml()` (private, line 59)


## SitemapXml

`final class Minn\Front\SitemapXml` · `public/minn/src/Minn/Front/SitemapXml.php`

The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer.

Used by: `Minn\Front\Sitemaps`

### static `index(array $entries, ?string $stylesheet): string`

A sitemap index document.

- `@param list<array<string, string|null>> $entries each a map of element name => text; null values are skipped`

### static `urlset(array $entries, ?string $stylesheet): string`

A sitemap urlset document.

- `@param list<array<string, string|null>> $entries`

Internals: `elements()` (private, line 32), `document()` (private, line 47)


## Sitemaps

`final readonly class Minn\Front\Sitemaps` · `public/minn/src/Minn/Front/Sitemaps.php`

The sitemap index and its providers (posts, pages, categories, tags,
authors), in the reference's shape: one file per provider and page, 2000
URLs a page, lastmod on content only.

- const `PER_PAGE` = `2000`

Used by: `Minn\Engine`, `Minn\Front\SitemapController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `index(): string`

The sitemap index's XML.

### `page(string $type, string $subtype, int $page): ?string`

One provider page, or null when the name or page does not exist.

### static `stylesheet(): string`

The engine's own stylesheet for browsers that open a sitemap.

### static `indexStylesheet(): string`

The stylesheet the sitemap index links: one column, the sitemaps.

Internals: `providers()` (private, line 57), `contentUrls()` (private, line 83), `termUrls()` (private, line 106), `userUrls()` (private, line 121), `authors()` (private, line 128), `xsl()` (private, line 149), `iso()` (private, line 160)


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

The category list the reference prints.

- `@param list<array<string, mixed>> $terms rows with term_id, name, slug, count, parent`
- `@param array<string, mixed> $args wp_list_categories arguments`
- `@param Closure(array): string $link`

### static `categoryWrapper(array $args): array`

The list's title item and its closer.

- `@return list<string> the categories block wrapper, before and after the items`

### static `tagCloud(array $tags, array $args): ?string`

The tag cloud the reference prints, or null for none.

- `@param list<array<string, mixed>> $tags rows with term_id, name, count, plus "link"`
- `@param array<string, mixed> $args wp_generate_tag_cloud arguments`

### static `dropdownOptions(array $terms, array $args): string`

The option elements of a category dropdown, nested by depth when the
caller asked for a hierarchy.

- `@param list<array<string, mixed>> $terms`

Internals: `options()` (private, line 127), `sorted()` (private, line 146), `orphans()` (private, line 159), `items()` (private, line 166)

