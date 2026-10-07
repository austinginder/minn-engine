# Front end: the probe surface

Everything a monitor, crawler, feed reader, or hosting check requests that is
not a page. Suite: `tests/probes.test.php` (status, content type, and for
feeds, sitemaps, robots, xmlrpc, and cron the body, after host, generator
version, container suffix, and per-request ids are neutralised). Pinned copies
in `contracts/fixtures/probes/`; re-capture with `--capture`. Code:
`public/minn/src/Minn/Front/{ProbeController,FeedController,FeedTemplates,SitemapController}.php`, `public/minn/src/Minn/Rest/IndexController.php`.

## Feeds (`FeedController`, `FeedTemplates`, `wp-api/feed.php`)

Byte for byte with the reference, tabs and blank lines included; every feed
runs WordPress's feed lifecycle (the main query, then `do_feed`), so the
shapes are in the feed templates. Facts worth knowing:

- `/feed/` and `/feed/rss2/` are RSS 2.0; `/feed/atom/` and `/feed/rdf/` exist;
  `?feed=rss2` works on any resolvable path and the self link keeps the query.
  A feed path without its trailing slash redirects.
- Items run in date order, `posts_per_rss` of them; sticky posts get no special
  place. `lastBuildDate`/`updated` is the newest `post_modified_gmt` among the
  items; dates are `post_date_gmt`.
- `<comments>` is present when comments are open or exist, pointing at
  `#comments` when there are any and `#respond` otherwise. Categories come
  before tags; the first `<category>` line is indented deeper than the rest.
- `<description>`/`<summary>` is the plain excerpt WITHOUT stopping at the
  more tag; `<content:encoded>` is the whole rendered content with the more
  tag as `<span id="more-{id}"></span>`, rendered with the page image rules
  (the first image is `fetchpriority="high"`) and against the feed's own
  queried object, so a category feed's categories block marks that category.
- `<guid>` is the stored guid column, so it keeps whatever host the post was
  written under. `<generator>` carries the site's recorded core version.
- Atom entries carry `<uri>` only when the author has a URL; an authorless
  post has an empty `<name>`. Archive feed titles are `{name} &#8211; {site}`.
- Comment feeds (`/comments/feed/`, `/{post}/feed/`) have their own channel
  shape, no `<language>`, titles `Comments for {site}` / `Comments on: {title}`,
  items `Comment on {title} by {author}` / `By: {author}`, the description as
  the escaped raw comment and the content as its paragraphs.

## Sitemaps (`Sitemaps`)

`/wp-sitemap.xml` lists one file per provider and page (2000 URLs a page):
`posts-post`, `posts-page`, `taxonomies-category`, `taxonomies-post_tag`,
`users`. Content entries carry `lastmod` (`post_modified_gmt` with `+00:00`) in
ascending date order; the pages provider starts with the front page, dated by
the newest post, when the front shows posts; terms and authors have no
`lastmod`; authors are users with published posts, by id. A page beyond the
last is the 404 page. The XSL files are the engine's own.

## The rest

- `robots.txt`: the reference's four lines with the sitemap URL, or `Disallow: /`
  when the site is not public.
- `xmlrpc.php`: GET answers 405 with `Allow: POST` and the reference's one-line
  body; POST is refused with 403 (the engine serves no XML-RPC), a divergence.
- `wp-cron.php`: 200 with an empty body (milestone 25 makes it do work).
- `/wp-admin` and anything under it: 302 to `/minn-admin/` (engine-defined; the
  reference sends the login form).
- `/favicon.ico`: 302 to the site icon when one is set, else 404 (the reference
  redirects to its own logo).
- HTML pages carry `Link: <{home}/wp-json/>; rel="https://api.w.org/"`.
- `/wp-json/`: the index the reference's shape (`name`, `description`, `url`,
  `home`, `gmt_offset`, `timezone_string`, `page_*`, `show_on_front`,
  `namespaces`, `authentication`, `routes`, `site_logo`, `site_icon`,
  `site_icon_url`, `_links.help`). Routes are described from the engine's own
  router, alternations expanded to concrete routes, without argument schemas:
  a much shorter document than the reference's 230 KB, recorded divergence.
  The declared-type routes (one `{base}` catch-all in the router) are listed
  per declared type under its `rest_base`, as the reference lists a registered
  type, and not at all when no type is declared; every literal route the index
  advertises answers (the probes suite walks them). A runtime plugin's routes
  merge in from the runtime's server; the engine spells a shared pattern the
  way the plugin does so the merged index lists it once.

## Known gaps

- Atom and RDF exist only for the site feed and archives, not for comments.
- `readme.html` and `license.txt` at the root are milestone 26 (file layout).
