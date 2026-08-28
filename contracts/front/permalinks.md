# Front end: permalinks and URL resolution

The first reader-facing surface. Every public URL shape resolves the way the
reference does: same status, same redirect target, same body-class tokens.
Fixture: `contracts/fixtures/front/permalinks.json` (93 URL cases captured
from the oracle; re-capture with `php tests/permalinks.test.php --capture`).
Suite: `tests/permalinks.test.php` (fixture mode plus a live diff when the
oracle is up). Code: `src/Minn/Front/`.

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
