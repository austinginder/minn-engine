# `Minn\Front`

URL resolution, permalinks, feeds, sitemaps and the public page

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AdminBar`](#adminbar) | final readonly class | 318 | The Minn bar on the public site: the same server-rendered chrome the |
| [`ArchiveAddresses`](#archiveaddresses) | final readonly class | 146 | The archives an address may stand for, as the reference answers them: |
| [`Archives`](#archives) | final readonly class | 62 | The archive periods wp_get_archives lists: months, years, days and weeks |
| [`AssetsController`](#assetscontroller) | final readonly class | 32 | The engine's own static assets, served under a reserved path. |
| [`AttachmentAddresses`](#attachmentaddresses) | final readonly class | 88 | The addresses an attachment's page answers to, as the reference's rules |
| [`Calendar`](#calendar) | final readonly class | 99 | One month as the calendar widget and block draw it: a table whose caption |
| [`CalendarLabels`](#calendarlabels) | final readonly class | 44 | The words a calendar prints: weekday names Sunday first, the short form |
| [`Canonical`](#canonical) | final class | 28 | Where a URL should redirect to, by the engine's own resolution: the |
| [`CommentList`](#commentlist) | final class | 52 | The classic threaded comment walk: top-level comments in order (or |
| [`CommentPostController`](#commentpostcontroller) | final readonly class | 174 | wp-comments-post.php: the comment form's target. The reference's |
| [`CustomLogo`](#customlogo) | final class | 27 | The site logo a theme prints, as get_custom_logo builds it (probe |
| [`DocumentTitle`](#documenttitle) | final class | 47 | The document title as parts (title, tagline, page, site) in the order the |
| [`EmbedCard`](#embedcard) | final class | 95 | The parts of a post's embed card the reference's embed template prints, |
| [`Endpoints`](#endpoints) | final class | 44 | Rewrite endpoints plugins add (add_rewrite_endpoint: a shop's account |
| [`FeedController`](#feedcontroller) | final readonly class | 151 | The feeds: the site's, the comments', a post's or an archive's by the |
| [`FeedTags`](#feedtags) | final class | 140 | The template tags a feed is written with that take more than a line, as |
| [`FeedTemplates`](#feedtemplates) | final class | 295 | The feed templates do_feed_* loads, written from the reference's output |
| [`FeedWriter`](#feedwriter) | final class | 35 | A feed as it is written: text as given, and what each template tag and |
| [`Feeds`](#feeds) | final readonly class | 316 | The syndication feeds, byte for byte in the reference's shape: RSS 2.0 |
| [`FrontController`](#frontcontroller) | final readonly class | 97 | The public site. One catch-all route: resolve the URL, then either |
| [`Kind`](#kind) | enum | 17 | What a public URL resolved to. |
| [`ListSpacing`](#listspacing) | final readonly class | 30 | How a page list is spaced: the reference's "preserve" keeps newlines and |
| [`ListingLinks`](#listinglinks) | final class | 53 | The prev/next links a paged listing prints: which page sits either side of |
| [`Maintenance`](#maintenance) | final class | 28 | Maintenance mode, as hosting tools and updaters switch it on: a |
| [`PageLinks`](#pagelinks) | final class | 40 | The links between the pages of a post split with <!--nextpage-->, as |
| [`PageList`](#pagelist) | final readonly class | 96 | The page hierarchy as wp_list_pages and wp_dropdown_pages draw it: nested |
| [`Pagination`](#pagination) | final class | 62 | Numbered page links in the reference's shape: previous, the end and |
| [`Permalinks`](#permalinks) | final readonly class | 259 | Builds public URLs from the site's permalink structure. With an empty |
| [`PluginRules`](#pluginrules) | final class | 86 | Rewrite rules a plugin registered through add_rewrite_rule(): the |
| [`PostEmbed`](#postembed) | final class | 104 | A post as other sites embed it, the oEmbed provider side, as the |
| [`PostNavigation`](#postnavigation) | final class | 36 | The links to the posts either side of this one, and the nav block that |
| [`PrintedResponse`](#printedresponse) | final class | 39 | A response WordPress's handlers print themselves (a sitemap, robots.txt), |
| [`ProbeController`](#probecontroller) | final readonly class | 66 | The surface monitors, crawlers, and hosting checks hit that is not a |
| [`Redirects`](#redirects) | enum | 23 | Whether a resolution may answer with a canonical redirect. A GET or HEAD |
| [`Renderer`](#renderer) | final readonly class | 168 | The interim public theme: one clean template until the block-theme |
| [`RequestParse`](#requestparse) | final class | 214 | The query vars the reference's request parse sets for an address |
| [`Resolution`](#resolution) | final readonly class | 108 | The outcome of resolving a public URL: which kind of thing it names, |
| [`Resolver`](#resolver) | final readonly class | 426 | Turns a public URL into a Resolution, following the reference's observed |
| [`RuleRoutes`](#ruleroutes) | final readonly class | 121 | What a rewrite rule's query vars stand for, when a plugin's rule (its |
| [`SingleAddresses`](#singleaddresses) | final readonly class | 61 | The addresses a single answers to besides its own, as the reference |
| [`SingleQueries`](#singlequeries) | final readonly class | 79 | The single a query string asks for, as the reference's request parse and |
| [`SitemapController`](#sitemapcontroller) | final readonly class | 79 | The sitemap index, its pages, and the two stylesheets. With plugins |
| [`SitemapRequest`](#sitemaprequest) | final class | 70 | A sitemap request at template_redirect, as the reference's sitemaps |
| [`SitemapXml`](#sitemapxml) | final class | 43 | The two sitemap documents, index and URL set, from entry maps; one builder for the engine's routes and the facade's renderer. |
| [`Sitemaps`](#sitemaps) | final readonly class | 151 | The sitemap index and its providers (posts, pages, categories, tags, |
| [`StoredRules`](#storedrules) | final class | 79 | Addresses a plugin's change to the rewrite rules decides (suite |
| [`TermLists`](#termlists) | final class | 178 | The two term listings themes print: the nested category list and the |
| [`ToolbarMarkup`](#toolbarmarkup) | final class | 57 | WP_Admin_Bar's markup, piece by piece, as the reference prints it (probe |
| [`ToolbarMenus`](#toolbarmenus) | final class | 338 | The nodes WordPress puts on the toolbar itself, as the reference adds |
| [`ToolbarTree`](#toolbartree) | final class | 90 | WP_Admin_Bar's nodes bound into the tree they print as, as the reference |

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


## ArchiveAddresses

`final readonly class Minn\Front\ArchiveAddresses` · `public/minn/src/Minn/Front/ArchiveAddresses.php`

The archives an address may stand for, as the reference answers them:
the front, a search, a term's (a category's, a tag's, a plugin
taxonomy's), an author's and a date's, each a 404 when it is empty or
paged past its end (an author's only past a real author's end). The
resolver reads them off the path; a plugin's rule hands their vars.

Used by: `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Content\Terms $terms, Minn\Front\Permalinks $permalinks, Closure $readable)
```
- `@param Closure(PostRecord): bool $readable whether the reader may read a post`


### `search(string $term, int $paged): Minn\Front\Resolution`

A search's results page; past the last page, a 404.

### `home(int $paged): Minn\Front\Resolution`

The front: the static front page when there is one, else the posts listing; past its last page, a 404.

### `taxonomy(string $taxonomy, array $types, array $slugs, int $paged): Minn\Front\Resolution`

A plugin taxonomy's term archive by its path; a 404 when the term is
missing, empty or overpaged.

- `@param list<string> $types the post types the taxonomy attaches to`
- `@param list<string> $slugs`

### `term(string $taxonomy, array $slugs, int $paged): Minn\Front\Resolution`

A category's or tag's archive by its path; a 404 when the term is missing. @param list<string> $slugs

- `@param list<string> $slugs`

### `termResolution(string $taxonomy, Minn\Content\TermRecord $term, int $paged): Minn\Front\Resolution`

The archive a found term stands for, 404 when it is empty or overpaged.

### `author(string $name, int $paged): Minn\Front\Resolution`

An author's archive by nicename: 200 for any name, a 404 only past a real author's last page.

### `date(array $segments, int $paged): Minn\Front\Resolution`

A date archive by year, month and day; a 404 when invalid, empty or overpaged. @param list<string> $segments

- `@param list<string> $segments`

### static `dateRange(int $year, ?int $month, ?int $day): ?array`

The site-local bounds of a date archive, or null when the date is invalid.

- `@return array{0: string, 1: string}|null`

### `pages(int $total): int`

How many listing pages a total fills.


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


## AttachmentAddresses

`final readonly class Minn\Front\AttachmentAddresses` · `public/minn/src/Minn/Front/AttachmentAddresses.php`

The addresses an attachment's page answers to, as the reference's rules
find it: by id or slug in the query (?attachment_id=, ?attachment=); a
loose attachment (no parent) by its slug at the top; any attachment by
its slug under a post's or page's address, "attachment/" between or
not; and as a post (?p=, ?page_id=), which moves to its own page. With
attachment pages off (wp_attachment_pages_enabled, the default) every
other address answers as typed; with them on, one that is not its own
moves there. An attachment is readable as its parent is.

Used by: `Minn\Front\Resolver`, `Minn\Front\SingleQueries`

```php
__construct(Minn\Db $db, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Closure $readable)
```


### static `names(Minn\Front\Resolution $resolution): bool`

Whether a resolution is an attachment's page.

### `fromQuery(Minn\Http\Request $request, Minn\Front\Redirects $redirects): ?Minn\Front\Resolution`

?attachment_id= or ?attachment=: the attachment's page, or a 404; null when the request names neither.

### `asPost(Minn\Content\PostRecord $post, Minn\Http\Request $request, string $key, Minn\Front\Redirects $redirects): ?Minn\Front\Resolution`

An attachment asked for as a post (?p=, ?page_id=): it moves to its own page whatever the setting; null for any other post.

### `at(array $segments): ?Minn\Front\Resolution`

The attachment a pretty address names: a loose one by its slug alone,
any by its slug under a post's or page's address.

- `@param list<string> $segments`

### `answer(Minn\Front\Resolution $resolution, Minn\Http\Request $request, Minn\Front\Redirects $redirects): Minn\Front\Resolution`

The page as typed, or (attachment pages on, the address not its own)
a move to its own: the other query arguments along, ?attachment=
among them; an embed stays where it was asked for.

Internals: `readable()` (private, line 102)


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


## EmbedCard

`final class Minn\Front\EmbedCard` · `public/minn/src/Minn/Front/EmbedCard.php`

The parts of a post's embed card the reference's embed template prints,
as markup: the featured image and its shape (the widest of the image's
sizes; wide enough and it sits above the title, else beside it, both by
filter), the site's name and icon, the comments and sharing buttons, and
the sharing dialog (its ids the post's and a random number, as on the
reference). The facade's template tags print them.

### static `thumbnail(): ?array`

The featured image the card shows: [attachment id, size, shape], or
null. A post's thumbnail, or an image attachment itself, through
embed_thumbnail_id, embed_thumbnail_image_size and
embed_thumbnail_image_shape.

- `@return array{0: int, 1: string, 2: string}|null`

### static `featuredImage(?array $thumbnail, string $shape): string`

The featured image's block, when the card shows one of this shape.

### static `siteTitle(): string`

The site's name and icon, linking home, the whole block through embed_site_title_html.

### static `commentsButton(): string`

The comments button: the count, linking to the comments; none on a 404, or with neither comments nor comments open.

### static `sharingButton(): string`

The button that opens the sharing dialog; none on a 404.

### static `sharingDialog(): string`

The sharing dialog: the post's address and its embed code, each a tab; none on a 404.


## Endpoints

`final class Minn\Front\Endpoints` · `public/minn/src/Minn/Front/Endpoints.php`

Rewrite endpoints plugins add (add_rewrite_endpoint: a shop's account
pages, an app's /json/), matched as the reference's endpoint rules match
them: the first registered endpoint name among the path's segments, what
follows it (slashes and all) its query var's value, '' when nothing
does; the address before it resolves as usual and counts only where the
endpoint was placed (EP_PAGES, EP_PERMALINK, EP_ROOT for the site's root,
and the archives' and attachments' own masks).

- const `MASKS` = `array (   'permalink' => 1,   'attachment' => 2,   'date' => 60,   'root' => 64,   'search' => 256,   'categories' => 512,   'tags' => 1024,   'authors' => 2048,   'pages' => 4096, )` — The reference's endpoint masks, by what they let an endpoint follow.

Used by: `Minn\Front\Resolver`

### static `split(string $path): ?array`

The endpoint a path names: the address before it, its query var and
value, and where it may be placed; null for none.

- `@return array{base: string, var: string, value: string, places: int}|null`

### static `allows(int $places, Minn\Front\Resolution $resolution): bool`

Whether an endpoint placed so may follow the address a resolution stands for.


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

### `commentsFeed(Minn\Http\Request $request, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /comments/feed (public)`

Route: `GET /comments/feed/ (public)`

Route: `GET /comments/feed/{kind:rss2|rss|atom|rdf} (public)`

Route: `GET /comments/feed/{kind:rss2|rss|atom|rdf}/ (public)`

The site's comments feed in one of its kinds.

### `pathFeed(Minn\Http\Request $request, string $path, string $kind = 'rss2'): Minn\Http\Response`

Route: `GET /{path*}/feed (public)`

Route: `GET /{path*}/feed/ (public)`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf} (public)`

Route: `GET /{path*}/feed/{kind:rss2|rss|atom|rdf}/ (public)`

A post's comment feed, or an archive's feed, by resolving the path in front of /feed/.

### `queryFeed(Minn\Http\Request $request, Minn\Front\Resolution $resolution, string $kind): Minn\Http\Response`

The ?feed= query form on any resolvable path: any feed a handler answers, a plugin's own included.

Internals: `feed()` (private, line 103), `served()` (private, line 118), `engineFeed()` (private, line 142), `feedResponse()` (private, line 174)


## FeedTags

`final class Minn\Front\FeedTags` · `public/minn/src/Minn/Front/FeedTags.php`

The template tags a feed is written with that take more than a line, as
the reference answers them in a feed's loop (probe feed-tags): a post's
categories and tags in each feed's markup, its enclosures for RSS and
Atom, the link to its comments feed, the feed's build date, and the site
icon a feed carries.

### static `categories(string $type): string`

A post's category and tag names (each once) in a feed type's markup, before the_category_rss.

### static `rssEnclosures(): string`

The current post's enclosures as RSS has them: address, length, and the type the third line starts with.

### static `atomEnclosures(): string`

The current post's enclosures as Atom links: the length a line that is a number, the type a line that is a known MIME type.

### static `postCommentsFeedLink(int $postId, string $feed): string`

The address of a post's comments feed in a feed type (the default one bare), through post_comments_feed_link.

### static `buildDate(string $format): string`

When the feed last changed, in a format: the newest of its posts'
modifications (and comments, for a comments feed); once the loop has
run out, the site's last modification; failing both, now.

### static `rss2Icon(): string`

The site icon as an RSS 2.0 image (titled with the feed's title), or '' without one.

### static `atomIcon(): string`

The site icon as an Atom icon, or '' without one.

Internals: `enclosures()` (private, line 37)


## FeedTemplates

`final class Minn\Front\FeedTemplates` · `public/minn/src/Minn/Front/FeedTemplates.php`

The feed templates do_feed_* loads, written from the reference's output
(suite feed-hooks, probes feed-templates): RSS 2.0, Atom, RDF and RSS
0.92 for posts, RSS 2.0 and Atom for comments. Each is written through
the template tags, so every feed filter has its say, and fires each feed
action where the reference fires it; the whitespace between is the
reference's. A template is written once a request, as require_once
loads it, and sends its content type through the header callback first.

- const `SYNDICATION` = `'	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" '`


### static `load(string $name, Closure $send): string`

A feed template by its name (rss2, rss2-comments, atom, atom-comments,
rdf, rss), between wp_before_load_template and wp_after_load_template.

- `@param Closure(string): void $send sends a header line (the Content-Type)`

Internals: `syndication()` (private, line 58), `rss2()` (private, line 63), `rss2Item()` (private, line 82), `atom()` (private, line 108), `atomEntry()` (private, line 127), `rdf()` (private, line 154), `rdfItem()` (private, line 178), `rss()` (private, line 193), `commentsTitle()` (private, line 211), `commentTitle()` (private, line 223), `current()` (private, line 232), `rss2Comments()` (private, line 239), `rss2Comment()` (private, line 257), `atomComments()` (private, line 271), `atomComment()` (private, line 293)


## FeedWriter

`final class Minn\Front\FeedWriter` · `public/minn/src/Minn/Front/FeedWriter.php`

A feed as it is written: text as given, and what each template tag and
action prints, in the order the template asks for them.

Used by: `Minn\Front\FeedTemplates`


### `put(string ...$text): self`

Text as written.

### `tag(string $function, mixed ...$args): self`

What a function that prints (a template tag) prints, called with its arguments.

### `act(string $hook, mixed ...$args): self`

What an action's callbacks print.

### `text(): string`

Everything written so far.


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
__construct(Minn\Front\Resolver $resolver, Minn\Front\Renderer $renderer, ?Minn\Theme\PageRenderer $theme = NULL, ?Minn\Front\FeedController $feeds = NULL, ?Minn\Cron\Cron $cron = NULL, ?Minn\Theme\ClassicRenderer $classic = NULL, ?Minn\Front\SitemapController $sitemaps = NULL, ?Minn\Theme\EmbedRenderer $embeds = NULL)
```


### `notFound(): Minn\Http\Response`

The themed (or interim) 404 page.

### `themed(Minn\Front\Resolution $resolution): Minn\Http\Response`

The themed (or interim) page for a resolution, under its status.

### `show(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /{path*} (public)`

The public page for any path; when scheduled work is due, the run follows the response.

Internals: `page()` (private, line 71), `rendered()` (private, line 100)


## Kind

`enum Minn\Front\Kind` · `public/minn/src/Minn/Front/Kind.php`

What a public URL resolved to.

Cases: `Home`, `Single`, `Page`, `Category`, `Tag`, `Author`, `Date`, `Search`, `Taxonomy`, `PostTypeArchive`, `NotFound`, `Redirect`

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Front\AdminBar`, `Minn\Front\AttachmentAddresses`, `Minn\Front\Canonical`, `Minn\Front\DocumentTitle`, `Minn\Front\Endpoints`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\Renderer`, `Minn\Front\RequestParse`, `Minn\Front\Resolution`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`


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

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\RenderController`, `Minn\Admin\SiteController`, `Minn\Admin\ThemesController`, `Minn\Blocks\Dynamic\Archives`, `Minn\Blocks\Dynamic\Categories`, `Minn\Blocks\Dynamic\LatestComments`, `Minn\Blocks\Dynamic\LatestPosts`, `Minn\Blocks\Dynamic\Search`, `Minn\Blocks\Dynamic\TagCloud`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\Dynamic\Theme\Structure`, `Minn\Blocks\Renderer`, `Minn\Cli\Runtime`, `Minn\Content\Menus`, `Minn\Content\SiteIcon`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\ArchiveAddresses`, `Minn\Front\AttachmentAddresses`, `Minn\Front\CommentPostController`, `Minn\Front\FeedController`, `Minn\Front\Feeds`, `Minn\Front\ProbeController`, `Minn\Front\Renderer`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Front\SingleQueries`, `Minn\Front\Sitemaps`, `Minn\Login\LoginController`, `Minn\Media\Uploads`, `Minn\Ops\Diagnostics`, `Minn\Rest\CommentObject`, `Minn\Rest\IndexController`, `Minn\Rest\MediaObject`, `Minn\Rest\PostObject`, `Minn\Rest\RegisteredPostFields`, `Minn\Rest\RestUrl`, `Minn\Rest\SearchController`, `Minn\Rest\Services`, `Minn\Rest\TermObject`, `Minn\Rest\UserObject`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\PageRenderer`, `Minn\Theme\Theme`, `Minn\Theme\ThemeStyles`

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

Internals: `hasPrettyLink()` (private, line 249), `fill()` (private, line 254)


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
- const `MATCHED` = `'rule_matched'` — Set when a plugin's own rule matched the request (not merely an endpoint).
- const `PUBLIC_VARS` = `array (   0 => 'm',   1 => 'p',   2 => 'posts',   3 => 'w',   4 => 'cat',   5 => 'withcomments',   6 => 'withoutcomments',   7 => 's',   8 => 'search',   9 => 'exact',   10 => 'sentence',   11 => 'calendar',   12 => 'page',   13 => 'paged',   14 => 'more',   15 => 'tb',   16 => 'pb',   17 => 'author',   18 => 'order',   19 => 'orderby',   20 => 'year',   21 => 'monthnum',   22 => 'day',   23 => 'hour',   24 => 'minute',   25 => 'second',   26 => 'name',   27 => 'category_name',   28 => 'tag',   29 => 'feed',   30 => 'author_name',   31 => 'pagename',   32 => 'page_id',   33 => 'error',   34 => 'attachment',   35 => 'attachment_id',   36 => 'subpost',   37 => 'subpost_id',   38 => 'preview',   39 => 'robots',   40 => 'favicon',   41 => 'taxonomy',   42 => 'term',   43 => 'cpage',   44 => 'post_type',   45 => 'embed', )` — The reference's public query vars a rule's query string may set.

Used by: `Minn\Front\FeedController`, `Minn\Front\RequestParse`, `Minn\Front\Resolver`, `Minn\Front\StoredRules`, `Minn\Runtime\MainQuery`, `Minn\Theme\MainQueryBridge`

### static `match(string $path, bool $top): ?array`

A plugin's rewrite rule that matches the path, or null.

- `@return array<string, string>|null the query vars of the first matching rule, null with no match`

### static `varsOf(string $query, array $matches): array`

A matched rule's query vars: its query with the matches put in
(urlencoded like the reference's WP_MatchesMapRegex, so a captured
'&x=1' cannot split into extra vars), only those the reference would
recognise (the public vars and the query_vars filter's).

- `@param array<int|string, string> $matches`
- `@return array<string, string>`

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


## PrintedResponse

`final class Minn\Front\PrintedResponse` · `public/minn/src/Minn/Front/PrintedResponse.php`

A response WordPress's handlers print themselves (a sitemap, robots.txt),
as the reference serves them: the main query stood with the request's own
variables and the front-end steps around it, then $print; what was
printed, under the status and headers the handlers sent. A handler that
ends the request early (Printed, where the reference exits) ends it here
too; when none did and there is nothing to print, null, and the theme
renders the page on the query as it stands.

Used by: `Minn\Front\ProbeController`, `Minn\Front\SitemapController`, `Minn\Theme\EmbedRenderer`

### static `stand(Minn\Theme\MainQueryBridge $bridge, array $vars, ?Closure $print = NULL, ?Minn\Front\Resolution $resolution = NULL): ?Minn\Http\Response`

The request served this way, or null for the theme to render.

- `@param array<string, mixed> $vars the request's own query variables`

Internals: `discard()` (private, line 53)


## ProbeController

`final readonly class Minn\Front\ProbeController` · `public/minn/src/Minn/Front/ProbeController.php`

The surface monitors, crawlers, and hosting checks hit that is not a
page: robots.txt, the XML-RPC and cron endpoints, the admin entry point,
and the favicon. Feeds and sitemaps have controllers of their own.

Used by: `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Content\SiteIcon $icon, ?Minn\Cron\Cron $cron = NULL, ?Minn\Theme\MainQueryBridge $bridge = NULL)
```


### `robots(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /robots.txt (public)`

robots.txt: with plugins loaded, the reference's (the main query a
robots request, then do_robots, under do_robotstxt and robots_txt);
otherwise the same lines, the sitemap's only on a public site.

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

Used by: `Minn\Front\AttachmentAddresses`, `Minn\Front\FrontController`, `Minn\Front\Resolver`, `Minn\Front\SingleAddresses`, `Minn\Front\SingleQueries`

### static `forMethod(Minn\Http\Method $method): self`

The mode a request's method allows.

### static `forRequest(Minn\Http\Request $request): self`

The mode for a request: its method's, except that a feed is served where it was asked for, as the reference serves it.

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

Internals: `attachmentClasses()` (private, line 37), `pageClasses()` (private, line 115), `article()` (private, line 132), `archive()` (private, line 142)


## RequestParse

`final class Minn\Front\RequestParse` · `public/minn/src/Minn/Front/RequestParse.php`

The query vars the reference's request parse sets for an address
(WP::parse_request), which plugins read off $wp->query_vars and
get_query_var: the vars of the rewrite rule the path matches (a post's
name and page, a page's full path, an archive's slug, the paged, feed,
comment-page, embed and trackback suffixes), then each public var the
query string or form carries, all in the public vars' order (a plugin's
own after the core ones); a post type's or taxonomy's own var brings
post_type and name; a path no rule matches is error=404. The engine
knows what the path resolved to, so the rule is read off that and the
path's shape rather than matched from a rule list.

Used by: `Minn\Theme\MainQueryBridge`

### static `vars(string $path, Minn\Front\Resolution $resolution, array $publicVars, array $given): array`

The parse's vars, in the reference's order.

- `@param list<string> $publicVars the public query vars, after the query_vars filter`
- `@param array<string, mixed> $given the query string's and the form's values`
- `@return array<string, string>`

### static `queryString(array $vars): string`

The reference's query string for parse vars (WP::build_query_string): each one with a value, encoded.

Internals: `segments()` (private, line 67), `ruleVars()` (private, line 79), `kindVars()` (private, line 96), `suffixes()` (private, line 119), `homeVars()` (private, line 147), `singleVars()` (private, line 160), `unmatched()` (private, line 181), `objectVars()` (private, line 200), `categoryBase()` (private, line 216), `taxonomyVar()` (private, line 223), `queryVarOf()` (private, line 230)


## Resolution

`final readonly class Minn\Front\Resolution` · `public/minn/src/Minn/Front/Resolution.php`

The outcome of resolving a public URL: which kind of thing it names,
the record behind it, and the page number for paginated views. Redirects
carry their target instead.

Used by: `Minn\Blocks\Context`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\ArchiveAddresses`, `Minn\Front\AttachmentAddresses`, `Minn\Front\DocumentTitle`, `Minn\Front\Endpoints`, `Minn\Front\FeedController`, `Minn\Front\FrontController`, `Minn\Front\PrintedResponse`, `Minn\Front\Renderer`, `Minn\Front\RequestParse`, `Minn\Front\Resolver`, `Minn\Front\RuleRoutes`, `Minn\Front\SingleAddresses`, `Minn\Front\SingleQueries`, `Minn\Front\SitemapController`, `Minn\Runtime\MainQuery`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\BodyClasses`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\EmbedRenderer`, `Minn\Theme\HeadLinks`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

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

### `resolve(Minn\Http\Request $request, ?Minn\Front\Redirects $mode = NULL): Minn\Front\Resolution`

$canonical mirrors the reference's redirect_canonical rule: only GET
and HEAD get trailing-slash, pretty-URL, and 404-guess redirects;
every other method renders what the query alone finds, as typed.

### static `dateRange(int $year, ?int $month, ?int $day): ?array`

The site-local bounds of a date archive, or null when the date is invalid.

- `@return array{0: string, 1: string}|null`

Internals: `fromRuleVars()` (private, line 126), `resolvePath()` (private, line 136), `resolveQueryVars()` (private, line 207), `dateRedirect()` (private, line 251), `pluginRoute()` (private, line 277), `segmentsOf()` (private, line 312), `resolveContent()` (private, line 318), `resolveSingle()` (private, line 359), `endpoint()` (private, line 401), `archives()` (private, line 419), `attachments()` (private, line 435), `elsewhere()` (private, line 441), `readable()` (private, line 446)


## RuleRoutes

`final readonly class Minn\Front\RuleRoutes` · `public/minn/src/Minn/Front/RuleRoutes.php`

What a rewrite rule's query vars stand for, when a plugin's rule (its
own, or one it changed) decided the address rather than the engine's
reading of the path (suite plugin-rules): a post by id, by name (of a
type the vars name), an attachment by name, a page by path, a plugin
type's item by its own var; else a category's, tag's or plugin
taxonomy's archive, an author's, a date's, a post type's, a search; else
the front. A rule that set error is a 404.

Used by: `Minn\Front\Resolver`

```php
__construct(Minn\Content\Posts $posts, Minn\Front\ArchiveAddresses $archives, Closure $page, Closure $readable)
```
- `@param Closure(list<string>, int): ?Resolution $page a page or post by its path, as the resolver finds one`
- `@param Closure(PostRecord): bool $readable whether the reader may read a post`


### `resolve(array $vars): Minn\Front\Resolution`

The resolution the vars stand for. @param array<string, string> $vars

- `@param array<string, string> $vars`

Internals: `single()` (private, line 48), `archive()` (private, line 73), `typeArchive()` (private, line 104), `found()` (private, line 114), `registered()` (private, line 125), `segments()` (private, line 138)


## SingleAddresses

`final readonly class Minn\Front\SingleAddresses` · `public/minn/src/Minn/Front/SingleAddresses.php`

The addresses a single answers to besides its own, as the reference
treats them: a slug the post used to have (alone, with a page number, or
with an embed or trackback suffix), and its comment-page-N addresses.

Used by: `Minn\Front\Resolver`, `Minn\Front\SingleQueries`

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


## SingleQueries

`final readonly class Minn\Front\SingleQueries` · `public/minn/src/Minn/Front/SingleQueries.php`

The single a query string asks for, as the reference's request parse and
canonical redirect answer it: by id (?p=, ?page_id=; an attachment's goes
to its own page), an attachment by id or slug (?attachment_id=,
?attachment=), a post by slug (?name=), a page by path (?pagename=).
With canonical redirects on, an address that is not the single's own
moves there, the other query arguments along; without, each var is
strict about the type it finds.

Used by: `Minn\Front\Resolver`

```php
__construct(Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Front\AttachmentAddresses $attachments, Minn\Front\SingleAddresses $elsewhere, Closure $readable)
```


### `find(Minn\Http\Request $request, Minn\Front\Redirects $redirects): ?Minn\Front\Resolution`

The resolution the query's single vars amount to; null when it has none of them.

Internals: `byId()` (private, line 53), `byPath()` (private, line 76), `singleOrRedirect()` (private, line 91)


## SitemapController

`final readonly class Minn\Front\SitemapController` · `public/minn/src/Minn/Front/SitemapController.php`

The sitemap index, its pages, and the two stylesheets. With plugins
loaded they are the reference's: the request's sitemap variables stand
in the main query and the sitemaps server answers at template_redirect
(SitemapRequest), through every filter a plugin hooks, any provider it
registers among them; what it does not print, the theme renders (a 404,
or the page a stray address amounts to). Without, the engine's own.

Used by: `Minn\Engine`, `Minn\Front\FrontController`

```php
__construct(Minn\Front\Sitemaps $sitemaps, Closure $notFound, ?Minn\Theme\MainQueryBridge $bridge = NULL, ?Closure $themed = NULL)
```


### `sitemapIndex(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xml (public)`

The sitemap index.

### `sitemap(Minn\Http\Request $request, string $name, string $rest): Minn\Http\Response`

Route: `GET /wp-sitemap-{name:[a-z]+}-{rest:[a-z_0-9-]+}.xml (public)`

One sitemap page: a provider's name, its subtype when it has them, and the page.

### `sitemapStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap.xsl (public)`

The sitemap stylesheet.

### `sitemapIndexStylesheet(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /wp-sitemap-index.xsl (public)`

The sitemap index stylesheet.

### `queried(Minn\Http\Request $request): Minn\Http\Response`

The query form (?sitemap=, ?sitemap-stylesheet=) on the front page, with plugins loaded.

Internals: `served()` (private, line 87), `xml()` (private, line 99)


## SitemapRequest

`final class Minn\Front\SitemapRequest` · `public/minn/src/Minn/Front/SitemapRequest.php`

A sitemap request at template_redirect, as the reference's sitemaps
server answers it: a sitemap address asked for another way (the query
form, page 0) moves to its own; with the sitemaps off, or a subtype or
page that has nothing, the request is a 404 the theme renders; otherwise
the stylesheet, the index or the provider's page is printed and the
request ends. A provider nobody registered leaves the request alone.

Used by: `Minn\Front\SitemapController`

### static `canonical(): void`

The canonical step: a sitemap asked for by another address is sent to its own.

### static `serve(WP_Sitemaps $server): void`

What the sitemaps server does with the request (render_sitemaps).

Internals: `page()` (private, line 62), `notFound()` (private, line 82)


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
- const `CSS` = `'body{font:15px/1.5 sans-serif;margin:2em}table{border-collapse:collapse}td{padding:.35em 1em .35em 0;border-bottom:1px solid #ddd}'` — The stylesheets' own CSS (wp_sitemaps_stylesheet_css filters it when plugins are loaded).

Used by: `Minn\Engine`, `Minn\Front\SitemapController`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks)
```


### `index(): string`

The sitemap index's XML.

### `page(string $type, string $subtype, int $page): ?string`

One provider page, or null when the name or page does not exist.

### static `stylesheet(string $css = self::CSS): string`

The engine's own stylesheet for browsers that open a sitemap.

### static `indexStylesheet(string $css = self::CSS): string`

The stylesheet the sitemap index links: one column, the sitemaps.

### static `w3c(string $gmt): string`

A GMT date as a sitemap dates it (W3C, UTC).

Internals: `providers()` (private, line 60), `contentUrls()` (private, line 86), `termUrls()` (private, line 109), `userUrls()` (private, line 124), `authors()` (private, line 131), `xsl()` (private, line 152)


## StoredRules

`final class Minn\Front\StoredRules` · `public/minn/src/Minn/Front/StoredRules.php`

Addresses a plugin's change to the rewrite rules decides (suite
plugin-rules). WordPress matches a request against its stored rules, the
first that fits winning (a page's rule only when a page is at that
path). The engine reads addresses itself, which comes to the same while
the stored rules are the ones it would make. When a plugin changed them
(through a filter or action as they were made, taking rules away or
putting them in), the rule the stored list fits first is set against the
one the engine's own list would: the same rule, and the engine's reading
stands; another, and that rule's vars decide (no rule fitting at all is
a 404).

Used by: `Minn\Front\Resolver`

### static `route(string $path): ?array`

The vars the stored rules give an address, when they are not what the
engine's own reading follows; null when they are.

- `@return array<string, string>|null`

Internals: `subject()` (private, line 57), `first()` (private, line 74), `pageAt()` (private, line 95)


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


## ToolbarMarkup

`final class Minn\Front\ToolbarMarkup` · `public/minn/src/Minn/Front/ToolbarMarkup.php`

WP_Admin_Bar's markup, piece by piece, as the reference prints it (probe
admin-bar): the wrapper (a skip link until the page has opened its body,
a class for phones), a group's list (named by the menu it opens from),
and an item: a link or an empty div, its focus order, its attributes
from meta, an arrow when it opens a menu below the top level, and any
markup a plugin hangs after it.

- const `ATTRIBUTES` = `array (   0 => 'onclick',   1 => 'target',   2 => 'title',   3 => 'rel',   4 => 'lang',   5 => 'dir', )` — The meta keys an item carries onto its link or div, in the reference's order.

### static `open(): string`

The bar's wrapper up to its first group.

### static `close(): string`

The bar's wrapper after its last group.

### static `groupOpen(object $node, mixed $menuTitle): string`

A group's opening list tag, labelled by the menu it opens from when that has a menu title.

### static `itemOpen(object $node): string`

An item up to its submenu: the list item, then its link (or a div when it has none) with its title.

### static `itemClose(object $node): string`

An item after its submenu: any markup a plugin hangs on it, and the list item's end.


## ToolbarMenus

`final class Minn\Front\ToolbarMenus` · `public/minn/src/Minn/Front/ToolbarMenus.php`

The nodes WordPress puts on the toolbar itself, as the reference adds
them on the front end for whoever is signed in (probe admin-bar): the
account menu, the WordPress menu, the site menu with its appearance
group, the site editor and customizer links, updates, comments, new
content, the edit link for what the page shows, the shortlink, the
secondary groups, and the search box. Each is one admin_bar_menu
callback, so plugins can unhook any of them by name.

- const `MENUS` = `array (   0 =>    array (     0 => 'wp_admin_bar_my_account_menu',     1 => 0,   ),   1 =>    array (     0 => 'wp_admin_bar_my_account_item',     1 => 9991,   ),   2 =>    array (     0 => 'wp_admin_bar_recovery_mode_menu',     1 => 9992,   ),   3 =>    array (     0 => 'wp_admin_bar_search_menu',     1 => 9999,   ),   4 =>    array (     0 => 'wp_admin_bar_sidebar_toggle',     1 => 0,   ),   5 =>    array (     0 => 'wp_admin_bar_wp_menu',     1 => 10,   ),   6 =>    array (     0 => 'wp_admin_bar_my_sites_menu',     1 => 20,   ),   7 =>    array (     0 => 'wp_admin_bar_site_menu',     1 => 30,   ),   8 =>    array (     0 => 'wp_admin_bar_edit_site_menu',     1 => 40,   ),   9 =>    array (     0 => 'wp_admin_bar_customize_menu',     1 => 40,   ),   10 =>    array (     0 => 'wp_admin_bar_updates_menu',     1 => 50,   ),   11 =>    array (     0 => 'wp_admin_bar_command_palette_menu',     1 => 55,   ),   12 =>    array (     0 => 'wp_admin_bar_comments_menu',     1 => 60,   ),   13 =>    array (     0 => 'wp_admin_bar_new_content_menu',     1 => 70,   ),   14 =>    array (     0 => 'wp_admin_bar_edit_menu',     1 => 80,   ),   15 =>    array (     0 => 'wp_admin_bar_add_secondary_groups',     1 => 200,   ), )` — WordPress's menus as add_menus hooks them: the callback, its priority.
- const `ICON` = `'<span class="ab-icon" aria-hidden="true"></span>'`

### static `myAccountItem(WP_Admin_Bar $bar): void`

"Howdy" with the user's name and small avatar, at the right of the bar.

### static `myAccountMenu(WP_Admin_Bar $bar): void`

The account menu: the user's card (larger avatar, name, login when it differs, the profile link) and Log Out.

### static `sidebarToggle(WP_Admin_Bar $bar): void`

The menu button wp-admin's narrow screens show; the front end has none.

### static `wpMenu(WP_Admin_Bar $bar): void`

The WordPress logo's menu: About and Get Involved for a reader, then the wordpress.org links beside them.

### static `siteMenu(WP_Admin_Bar $bar): void`

The site's name (with its icon when it has one), then the dashboard, appearance and plugins below it.

### static `appearanceMenu(WP_Admin_Bar $bar): void`

The appearance group under the site's name: themes, then what the theme supports for those who may edit it.

### static `editSiteMenu(WP_Admin_Bar $bar): void`

Edit Site under a block theme, opening the template this page was built from when there is one.

### static `customizeMenu(WP_Admin_Bar $bar): void`

Customize, for a theme the customizer serves (a block theme only once something registers with it).

### static `updatesMenu(WP_Admin_Bar $bar): void`

The count of updates waiting, when there are any.

### static `commentsMenu(WP_Admin_Bar $bar): void`

The comments awaiting moderation, for those who edit posts.

### static `newContentMenu(WP_Admin_Bar $bar): void`

New: a post, media, a link, a page, each other type shown on the bar, and a user, as far as the user may create them.

### static `editMenu(WP_Admin_Bar $bar): void`

Edit, for the post, term or user the page shows, when the user may edit it.

### static `shortlinkMenu(WP_Admin_Bar $bar): void`

The page's shortlink, with a box to copy it from.

### static `secondaryGroups(WP_Admin_Bar $bar): void`

The groups for the bar's right side and the logo menu's outside links.

### static `recoveryModeMenu(WP_Admin_Bar $bar): void`

The way out of recovery mode, while the site is in it.

### static `searchMenu(WP_Admin_Bar $bar): void`

The search box, on the front end.

Internals: `profileUrl()` (private, line 43), `creatable()` (private, line 244), `editPost()` (private, line 291), `editTerm()` (private, line 301)


## ToolbarTree

`final class Minn\Front\ToolbarTree` · `public/minn/src/Minn/Front/ToolbarTree.php`

WP_Admin_Bar's nodes bound into the tree they print as, as the reference
binds them (probe admin-bar): each gains its children and its type; an
item's children go into its "-default" group, made when the first one
arrives; a group inside a group sits next to it in a "-container", put
where the outer group was; a node whose parent is missing is left out.
The bar's own node accessors are used throughout, so a subclass that
overrides them still sees every node it is asked for.


### static `bind(array $nodes, Closure $get, Closure $set): ?object`

The root of the bound tree, for nodes (the bar's, a root among them)
reached through the bar's accessors.

- `@param array<string, object> $nodes`
- `@param \Closure(string): ?object $get`
- `@param \Closure(array<string, mixed>): void $set`

Internals: `place()` (private, line 50), `defaultGroup()` (private, line 70), `container()` (private, line 83)

