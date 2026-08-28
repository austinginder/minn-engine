# Contract: wp/v2 posts (read surface)

Status: implemented (view context, GET only). Suites: `tests/rest-posts.test.php`
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
  `format-{format}`, `hentry`, then `category-{slug}` and `tag-{slug}`.
- List headers: `X-WP-Total`, `X-WP-TotalPages`,
  `Access-Control-Expose-Headers`, `Access-Control-Allow-Headers`,
  `X-Content-Type-Options: nosniff`, `Allow: GET`.
- Errors: `rest_post_invalid_id` (404), `rest_no_route` (404),
  `rest_post_invalid_page_number` (400).

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
