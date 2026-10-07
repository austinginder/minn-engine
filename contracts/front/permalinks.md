# Front end: permalinks and URL resolution

The first reader-facing surface. Every public URL shape resolves the way the
reference does: same status, same redirect target, same body-class tokens.
Fixture: `contracts/fixtures/front/permalinks.json` (93 URL cases captured
from the oracle; re-capture with `php tests/permalinks.test.php --capture`).
Suite: `tests/permalinks.test.php` (fixture mode plus a live diff when the
oracle is up). Code: `public/minn/src/Minn/Front/`.

The shared database now runs `permalink_structure = /%postname%/`. The
oracle needs `router.php` under `wp-reference/` so the built-in server routes
pretty paths through `index.php`:
`(cd wp-reference && php -S 127.0.0.1:8123 router.php)`.

## What the oracle taught

**Link building** (`Permalinks`, also used by every wp/v2 `link` field):

- Published and private posts get the pretty form. Draft, pending, future,
  and any post with an empty `post_name` get `/?p=ID`; pages `/?page_id=ID`.
- Pages carry their full ancestry: `/sample-page/docs/`.
- Categories: `/category/{path}/` with parent slugs; tags `/tag/{slug}/`;
  authors `/author/{user_nicename}/`; dates `/YYYY/`, `/YYYY/MM/`, `/YYYY/MM/DD/`.
  A post format names the format without its term slug's prefix:
  `/type/aside/`, `?post_format=aside` under plain permalinks.
- Feed links (probe feed-links): a term's, an author's or a search's
  address with `feed/` after it, the type named unless it is the default
  (a search's always names it); a search's comments feed adds
  `?withcomments=1`. Plain: `?feed={type}&amp;cat={id}` (`tag={slug}`, a
  taxonomy's own var with the full term slug, `author={id}`, escaped `&amp;`),
  searches `?s={query}&feed={type}` and `feed=comments-{type}`. A term
  asked for by id is found in any taxonomy unless one is named; a missing
  one is false. The links pass `category_feed_link`, `tag_feed_link`,
  `taxonomy_feed_link` (with the taxonomy), `author_feed_link`, and
  `search_feed_link` (with `posts` or `comments`; the comments link is the
  posts link through the filter first). A structure changed mid-request
  (`set_permalink_structure`) makes the links built after it.
- Attachments: `/{slug}/` when unattached, `{parent permalink}{slug}/` when
  attached, `/?attachment_id=ID` under plain permalinks.
- `permalink_template` (edit context) keeps the token: `/%postname%/` for
  posts of every status, `/{parent path}/%pagename%/` for pages.
- REST self links switch form with the structure: `/wp-json/wp/v2/...` when
  pretty, `/index.php?rest_route=...` when plain. The `_links` block is
  reproduced whole under `_fields` (the reference ignores `_links.self`
  as a sub-path; recorded, not yet mirrored).
- A post published without a slug is given one from its title at publish
  time (create and status change). Drafts keep an empty `post_name`.

**Resolution** (`RuleTable`, `RuleRoutes`, `Resolver`):

- One grammar, the reference's: a path is matched against the site's
  rewrite rules (`Runtime\RewriteRules`, stored in `rewrite_rules`, a
  plugin's own rules and endpoints among them), the first rule it fits
  winning, as typed or decoded; under verbose page rules a page's rule fits
  only when a page (or an attachment) stands at the path it captured. The
  rule's query vars say what the address names (`RuleRoutes`, the main
  query's reading of them) and ride into the request parse as they are. A
  path no rule fits is a 404 with `error=404` (`/a/b/hel` under
  `/%postname%/`), and is never guessed from. The root reads its query
  string. What `Resolver` keeps are the canonical answers below.
- Query forms redirect to the pretty form when the target is public:
  `?p=`, `?page_id=` (either accepts either type), `?name=`, `?pagename=`
  (a partial page name redirects to the full path; the full path answers
  200), `?cat=`, `?tag=`, `?author=`, `?m=YYYYMM`, `?year=`.
- Trailing slash: unpaged singles, pages, term, author, and date archives
  without a slash redirect to the slashed path as typed (case kept, query
  string kept), as do embed, endpoint and feed addresses. Paged views
  (`/hello-world/page/2`), search, `/page/1`, and 404s answer without
  redirecting. `/index.php/{path}` redirects to `/{path}/`.
- Matching is case-insensitive everywhere and never canonicalises case:
  `/HELLO-WORLD/` and `/category/Uncategorized/` are 200 as typed.
- Archive addresses are strict: a category, tag, author, search or date
  rule whose vars find nothing is a 404 with no guessing
  (`/category/uncategorized/hello-world/` is `category_name` with a path no
  category has). `/category/{c}/embed/` and `/author/{a}/embed/` answer
  with the archive itself.
- The 404 for a listing is the main query's, decided once
  (`Theme\FrontLifecycle::handle404`): an empty page past the first of any
  listing (`/page/2/`, `/author/nobody/page/2/`, `?s=hello&paged=2`) and an
  empty date archive (`/2025/`, and `?m=202501`, which then does not move to
  its pretty form) are 404; an existing term, author or post type with no
  posts (`/type/aside/`, the post format archive), an empty search and the
  front are 200. A term at no path is a 404 before any query
  (`/tag/nope/`). An author archive is 200 for any name, including one that
  belongs to nobody (no `author-*` tokens then). A feed is never a 404.
- The guess, for a 404 whose rule named a single (`name`, `attachment`, or
  a `pagename` at which no page stands): a 301 to a published post of a
  viewable type whose name starts with that slug, `page` riding along
  (`/docs/2/` to `/sample-page/docs/2/`), `paged` dropped. It is one prefix
  query with no order of its own, so the database's plan picks the match:
  alphabetical by name on the test site (`/s` reaches `/sample-page/`, `/e`
  `/editor-authored-post/`), oldest first on the dogfood site's larger
  table (`/a/b/c/` reaches `/corporate/`). `/p` is a 404 because
  `privacy-policy` is a draft; `/hello-world/docs/` (`attachment=docs`)
  reaches `/sample-page/docs/`.
- A trailing number (`page`) on a real single redirects to the plain
  permalink (`/hello-world/2/`, `/hello-world/1/`). The engine does not
  page a multi-page post yet, so it redirects those too.
- `/{single}/page/N/` is 200 for any N with `paged-N` and `single-paged-N`
  (or `page-paged-N`) tokens. `/{single}/embed/` renders the single;
  `/{single}/trackback/` is a 302 to it.
- Under a structure that names the category (`/%category%/%postname%/`, the
  dogfood site), a post is found by its name whatever category it was
  asked under, and a category that is none at that path or not one of the
  post's moves to the post's own address (`/bogus/title-here-like-this/`);
  a bare `/news/` is the category's archive and never guesses, while
  `/news/title-here/` guesses from the name. `/news/feed/` is the single
  named `feed` under `news`, a 404, as the rules order it.
- `/{single}/comment-page-N/` is a 301 to the single when the site does not
  page its comments (`page_comments` off, the default) and the single
  itself when it does (the engine does not page the comment list yet). The
  reference drops the segment only when the request's host is the site's,
  so this oracle (reached as 127.0.0.1) answers 200; the round trip
  (`tests/round-trip.test.php`) checks it with the site's own host.
- A slug a post used to have (`_wp_old_slug`) is a 301 to the post: alone,
  with a trailing number, `trackback` or `comment-page-N` (all to the plain
  address), with `page/N` (kept), and with `embed` (to the new embed
  address). A post that is not published (private included) is sent to its
  `?p=` form. The engine writes the rows too (`contracts/rest/writes.md`).
  A feed address is served where it was asked for: `/{old slug}/feed/`,
  `/{missing}/feed/` and `/category/{missing}/feed/` are 200 feeds of
  nothing, as on the reference.
- Non-public posts are 404 to anonymous readers by every route. A reader
  who can edit the post sees it by slug or by `?p=`; `?p=` on a private post
  redirects to its pretty link, on a draft it renders in place.
- The root's query string is read in the grammar a path's rule vars are
  read in (`Front\RuleRoutes`), a search first: `?s=x&category_name=y` is
  the search (its classes carry both, from the main query). Its archive
  forms move under pretty permalinks (`Front\QueryMoves`): a date (`?m=`,
  `?year=`) first, then an author by id, then a term when the query names
  exactly one taxonomy (`?cat=` or `?category_name=`, `?tag=`,
  `?taxonomy=&term=`, a plugin taxonomy's own var such as `?product_cat=`;
  `?post_format=` counts as one but never moves itself). The other
  arguments go along (`?cat=1&foo=bar` to `/category/uncategorized/?foo=bar`,
  `?cat=1&year=2026` to `/2026/?cat=1`, `?cat=1&author=1` to
  `/author/admin/?cat=1`); `?tag=engine&cat=1` names two taxonomies and
  stays. Nothing moves while `?s=` is given (even empty), past the first
  page, or to something missing (`?cat=99`, `?author=99` are 404s).
  `?author_name=`, `?post_format=` and `?post_type=` never move; a type
  without an archive (`?post_type=post`, `?post_type=bogus`) is the
  front's listing. `?error=404` from the query string is ignored (home).
  `?p=`/`?page_id=` move any viewable type to its address and drop
  `post_type`; `?name=` with `post_type` finds that type's post and keeps
  the other arguments (`/hello-world/?post_type=post`).
- A term archive's path is read by its last slug: `/category/any/child/`
  is the child's archive, unmoved; `/category/child/bogus/` is a 404.
- The 404 for an empty listing: past the first page, or an empty date that
  names nothing else. A date inside an existing term's, author's or type's
  archive, or inside a search, is a 200 (`?year=2025&cat=1` moves to
  `/2025/?cat=1`, which answers 200).
- Open, host-dependent: reached under the site's own host, the reference
  also sends an attachment page to its file while attachment pages are
  off, drops `/page/1/` (`/hello-world/page/1/` to `/hello-world/`), and
  moves feed aliases to the feed form (`/rss2/` to `/feed/`,
  `/hello-world/atom/` to `/hello-world/feed/atom/`). The suites reach it
  as 127.0.0.1, where it answers all of these 200 as the engine does.

**Body-class tokens** (the contract; the surrounding markup is engine-defined),
read from the main query (`Theme\QueryClasses`), so flags combine as the query's
do: `home`, `blog`, `privacy-policy`, `archive`, `date`, `search` with
`search-results` or `search-no-results`, `paged` (a listing past its first
page), `attachment`, `error404`; then the singular's (`single single-{type}
postid-N single-format-standard`; `page page-id-N` plus `page-parent` /
`page-child parent-pageid-N`) or the archive's (`post-type-archive
post-type-archive-{type}`, `author author-{nicename} author-N`, `category
category-{slug} category-N`, `tag tag-{slug} tag-N`, `tax-{taxonomy}
term-{slug} term-N`). `/?s=x&category_name=uncategorized` is `archive search
search-results category category-uncategorized category-1`. After
`wp-embed-responsive`: `paged-N` and the view's `{prefix}-paged-N` (single,
page, category, tag, date, author, search, post-type, in that precedence; the
front and a plain taxonomy have none). A single's page counts (`/multi/2/` is
`paged-2 single-paged-2`, no bare `paged`); a 404 has no paging tokens.

## Known gaps

- Feeds (`/feed/`, `/{single}/feed/`), `/wp-sitemap.xml`, `robots.txt`,
  and `/wp-admin/` are milestone 21 and currently 404.
- Only `%postname%`-style structures are resolved; date-prefixed structures
  build links correctly but the resolver has not been oracle-tested on them.
- Reader-side access uses `edit_post` for every non-public status; the
  `read_private_posts` distinction is milestone 23.
- The rendered page is an interim template (title, content through the
  block renderer, archive lists with excerpts). Milestones 19 and 20 replace it.
- Block themes pick the template by the resolution's kind, so
  `/category/x/?s=y` (a search on the reference, which picks the search
  template) renders the category's; its classes and title are right.
- Not made yet: `?product=x&s=y` (the reference moves it to the product
  with the arguments along); paged query-form moves
  (`?post_type=product&paged=2` to `/page/2/?post_type=product`); with
  `?product_cat=` and `?taxonomy=&term=` together the reference names the
  second's term.

## Front-page settings (`tests/front-page.test.php`, live against the oracle)

The suite switches the shared database to `show_on_front=page` with the Sample
Page in front and Docs as the posts page, then restores it. What the reference does:

- **The static front page** answers at `/` only (its own path redirects to `/`), is
  titled by the site name alone (the tagline follows when there is one), and its
  later pages (`/page/2/`) keep its body classes with `paged paged-2 page-paged-2`
  and are titled `Site – Page 2`. Its page-list item carries `menu-item-home` as
  its last class. Its comments feed link prints while comments or pings are open
  on it, comments or not (every other single needs a comment first).
- **The posts page** (`page_for_posts`) renders the `home` template (never
  `front-page`) with the blog listing, body class `blog` alone (no `home`, no
  `page-id-N`), title `Docs – Site`, no JSON alternate link, and the site-title
  link without `aria-current`. `?page_id=N` and `?p=N` redirect to its path. A
  page number under it (`/sample-page/docs/page/2/`) is one of the page's own
  sub-pages, which it has none of: 404. Its `feed/` is the site's posts feed with
  channel title `Docs – Site`.
- **Doubled slashes** anywhere in a path (`//`, `/hello-world//`, `//hello-world/`)
  redirect 301 to the collapsed path. The engine reads the path from the request
  URI itself; `parse_url` would take a leading `//` for an authority.
- **Titles** are texturized text escaped without touching the entities that
  made (`Hello world! &#8211; Site`, never `&amp;#8211;`).
- Known gap: the reference also prints oEmbed discovery links on singles and the
  front page; the engine has no oEmbed endpoint and prints none (the suite skips
  them).


## Non-GET methods (POST and the rest)

Captured 2026-08-31; suite `tests/front-method.test.php`, fixture
`contracts/fixtures/front/methods.json`.

- The reference runs `redirect_canonical` only for GET and HEAD. Every other
  method renders what the query alone finds, at the URL as typed: no
  trailing-slash redirect (`POST /sample-page` is 200 with the same body),
  no pretty-URL mapping (`POST /?page_id=2`, `/?cat=1`, `/?author=1`,
  `/?m=YYYYMM`, `/?name=` all render in place), and no 404 guessing
  (`POST /hello` is 404 where GET redirects to `/hello-world/`).
- Without the canonical pass each query var is strict about type: `?p=` finds
  only posts and `?page_id=` only pages, so `POST /?p=<page id>` is 404 while
  `GET /?p=<page id>` redirects to the page.
- A bare page slug in `?pagename=` (a nested page addressed without its path)
  404s on POST; the trailing-number form (`/sample-page/2`) 404s too.
- The old-slug redirect is not canonical: it fires for every method
  (`POST /<former slug>/` still 301s to the current permalink).
- The engine maps this with `Resolver::resolve($request, bool $canonical)`,
  `$canonical = $request->method->canonicalRedirects()` (GET/HEAD only), and
  the front catch-all route accepts every method. This is what makes
  WooCommerce's classic add-to-cart POST and `/?wc-ajax=*` endpoints answer.
- Known gap: `POST /feed` renders the feed on the reference; the engine's feed
  routes stay GET and the front resolver 404s a `feed` segment.
