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

**Resolution** (`Resolver`):

- Query forms redirect to the pretty form when the target is public:
  `?p=`, `?page_id=` (either accepts either type), `?name=`, `?pagename=`
  (a partial page name redirects to the full path; the full path answers
  200), `?cat=`, `?tag=`, `?author=`, `?m=YYYYMM`, `?year=`.
- Trailing slash: unpaged singles, pages, term, author, and date archives
  without a slash redirect to the slashed path as typed (case kept, query
  string kept). Paged views (`/hello-world/page/2`), search, `/page/1`, and
  404s answer without redirecting. `/index.php/{path}` redirects to `/{path}/`.
- Matching is case-insensitive everywhere and never canonicalises case:
  `/HELLO-WORLD/` and `/category/Uncategorized/` are 200 as typed.
- Archive-shaped paths are strict. `category`, `tag`, `author`, `search`,
  or a four-digit year in the first segment means a mismatch is a 404 with
  no guessing (`/category/uncategorized/hello-world/`, `/2026/08/28/hello-world/`).
- Empty term and date archives are 404 (`/tag/nope/`, `/2025/`), as is a
  page number past the end. An author archive is 200 for any name, including
  one that belongs to nobody (no `author-*` tokens then).
- Plain-segment paths: the page hierarchy first, then the post structure
  (single segment for `%postname%`), then the guess: the closest published
  page or post whose name starts with the last segment, pages before posts,
  newest first, as a 301 (`/hello` and `/hello-wor` reach `/hello-world/`;
  `/s` reaches `/sample-page/` over the newer `scribe-published`; `/p` is a
  404 because `privacy-policy` is a draft). A wrong parent still redirects to
  the right page (`/hello-world/docs/` to `/sample-page/docs/`).
- A trailing number: on a real single it redirects to the plain permalink
  (`/hello-world/2/`); on a guessed path it rides along (`/docs/2/` to
  `/sample-page/docs/2/`). `page/N` on a guessed path is dropped.
- `/{single}/page/N/` is 200 for any N with `paged-N` and `single-paged-N`
  (or `page-paged-N`) tokens. `/{single}/embed/` renders the single;
  `/{single}/trackback/` is a 302 to it.
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
  Not matched: the reference answers `/{old slug}/feed/` (like any
  `/{missing}/feed/`) with a 200 comments feed of nothing; the engine 404s.
- Non-public posts are 404 to anonymous readers by every route. A reader
  who can edit the post sees it by slug or by `?p=`; `?p=` on a private post
  redirects to its pretty link, on a draft it renders in place.

**Body-class tokens** (the contract; the surrounding markup is engine-defined):
`home blog`; `single single-post postid-N single-format-standard`;
`page page-id-N` plus `page-parent` / `page-child parent-pageid-N`;
`archive category category-{slug} category-N`; `archive tag tag-{slug} tag-N`;
`archive author author-{nicename} author-N`; `archive date`;
`search search-results`; `error404`; `paged paged-N` with `single-paged-N`
or `page-paged-N`.

## Known gaps

- Feeds (`/feed/`, `/{single}/feed/`), `/wp-sitemap.xml`, `robots.txt`,
  and `/wp-admin/` are milestone 21 and currently 404.
- Only `%postname%`-style structures are resolved; date-prefixed structures
  build links correctly but the resolver has not been oracle-tested on them.
- Reader-side access uses `edit_post` for every non-public status; the
  `read_private_posts` distinction is milestone 23.
- The rendered page is an interim template (title, content through the
  block renderer, archive lists with excerpts). Milestones 19 and 20 replace it.

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
