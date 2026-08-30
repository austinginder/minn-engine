# Contract: the wp/v2 read surface

Status: implemented (view context, GET only) for posts, pages, categories,
tags, types, and the `_fields` filter. Suites: `tests/rest-posts.test.php`
(fixtures) and `tests/rest-parity.test.php` (live diff against the reference).

## Fixture capture

The reference WordPress runs from the parked copy against the same database:

```bash
cd wp-reference && php -S 127.0.0.1:8123
curl -s 'http://127.0.0.1:8123/?rest_route=/wp/v2/posts'   | jq . > ../contracts/fixtures/rest/posts-list.json
curl -s 'http://127.0.0.1:8123/?rest_route=/wp/v2/posts/1' | jq . > ../contracts/fixtures/rest/posts-single.json
```

Fixtures are data captured from observed behavior; they carry no license
entanglement. The Cove stack rewrites URLs per request host, so suites
normalize the capture origin to the engine origin before comparing.

## Contract facts learned from the oracle

- REST URLs render in the plain-permalink form `{home}/index.php?rest_route=/…`.
  When query args ride along (`replies`, `wp:attachment`, `wp:term` links), the
  `rest_route` value itself is URL-encoded (`%2Fwp%2Fv2%2Fcomments&post=1`).
- An authorless post (`post_author` 0) carries NO `_links.author` entry at all;
  the `author` field still renders as 0.
- Rendered content: block comment delimiters are removed with surrounding
  whitespace preserved, and paragraph blocks gain a `wp-block-paragraph` class
  on their tag (WP 7 block-supports behavior).
- Generated excerpts collapse all whitespace to single spaces, cap at 55 words
  with ` [&hellip;]`, and wrap in `<p>…</p>\n`. A hand-written excerpt is
  wrapped the same way.
- JSON strings escape slashes (`\/`), PHP's default `json_encode` behavior.
- `sticky` reads the serialized `sticky_posts` option; sticky posts do NOT
  reorder the REST list (date-desc order holds).
- `class_list` order: `post-{id}`, `{type}`, `type-{type}`, `status-{status}`,
  `format-{format}`, `hentry`, then `category-{slug}`, `tag-{slug}`, and last
  `post_format-post-format-{format}` when a format term is assigned.
- List headers: `X-WP-Total`, `X-WP-TotalPages`,
  `Access-Control-Expose-Headers`, `Access-Control-Allow-Headers`,
  `X-Content-Type-Options: nosniff`, `Allow: GET`.
- Errors: `rest_post_invalid_id` (404), `rest_no_route` (404),
  `rest_post_invalid_page_number` (400).

## Facts from the milestone 2 routes (all oracle-proven)

- **`_fields`** filters per item on list responses and against the whole
  payload otherwise. Requested dot paths descend (`title.rendered`,
  `_links.self`). Field order in the output follows the object's canonical
  order, not the request order. An unmatched field yields an empty object.
  Because `wp/v2/types` is one associative payload, `_fields` strips every
  type key and the response is literally `[]` over HTTP; the engine
  reproduces this quirk by construction.
- **List filters**: `author` / `author_exclude` (id lists), `parent` /
  `parent_exclude` (id lists; `0` is top-level), `menu_order` (exact
  integer, including 0), `include` / `exclude`, `slug`, `search`.
- **Pages**: link is `/?page_id={id}`; fields add `parent` and `menu_order`
  and drop sticky, format, categories, and tags; `class_list` has no
  `format-*` entry; `_links` gains an embeddable `up` entry when the page
  has a parent and never has `wp:term`.
- **Terms**: categories link by id (`/?cat=1`), tags link by slug
  (`/?tag=engine`); tag objects have no `parent` field; `meta` is `[]`;
  `_links.wp:post_type` uses the encoded `rest_route` form with
  `categories=`/`tags=` args. Term errors: `rest_term_invalid`
  "Term does not exist." (404), including an id that exists in another
  taxonomy. Default order is name ascending, `hide_empty` false, counts come
  straight from the `term_taxonomy.count` column.
- **Types**: eleven built-ins in registration order (post, page, attachment,
  nav_menu_item, wp_block, wp_template, wp_template_part, wp_global_styles,
  wp_navigation, wp_font_family, wp_font_face), each with `_links.wp:items`
  pointing at its rest_base. Error: `rest_type_invalid` "Invalid post type."
  (404). The engine serves these from its own registry
  (`public/minn/data/types.json`). Active extensions may append extra types
  via `minn.json` `"types"`; those get a collection at their `rest_base`
  (`tests/declared-types.test.php`).
- **Texturize** (rendered content, titles, excerpts): straight quotes,
  apostrophes, `...`, `---`, ` -- `, `--`, and `'99` become numeric entities
  (`&#8220;` `&#8221;` `&#8216;` `&#8217;` `&#8230;` `&#8212;` `&#8211;`);
  `6'2"` renders as `6&#8217;2&#8243;` (only the double quote after a digit
  becomes a prime); `pre`/`code`/`kbd`/`style`/`script` contents are
  skipped. Pinned by the texturize battery post (id 7).
- **Quote blocks** gain layout-support classes at render:
  `is-layout-flow wp-block-quote-is-layout-flow` appended to the
  blockquote's class. Lists get no such classes.
- **Generated excerpts** remove disallowed blocks whole before trimming
  (a code block's text never appears); allowed set proven so far:
  paragraph, heading, list, quote (nested content included), preformatted.
  Tags are replaced by spaces (list items read as separate words), then
  whitespace collapses. Inline `code` inside a paragraph SURVIVES the
  excerpt and is texturized there, even though rendered content skips it.
  Pinned by the excerpt probe post (id 8).

## Known gaps (not yet implemented)

- Write methods (POST/PUT/DELETE) and authentication; non-GET currently answers
  `rest_no_route`, which is a placeholder, not the contract.
- `context=edit`, `_fields`, `_embed`, and the remaining collection params
  (`search`, `orderby`, `include`, `slug`, …).
- Draft/private posts should answer 401/403 shapes for anonymous requests;
  the engine currently treats non-publish as invalid id.
- `predecessor-version` link when a post has revisions.
- `Link` header pagination (`rel="next"/"prev"`) on multi-page lists.
- Byte-level output fidelity (key order matches by construction, but only
  structural equality is enforced by suites so far).
