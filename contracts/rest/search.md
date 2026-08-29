# wp/v2/search

Captured from the reference and matched by the engine (suite
`admin-surfaces`, section 3; the app's ⌘K palette and the front-end bar's
search both ride it).

- `GET /wp/v2/search?search=…` lists published content across every
  registered type except the internal ones (attachment, nav_menu_item, the
  `wp_*` types): `{id, title, url, type: "post", subtype: <post_type>,
  _links}`. `title` is the stored title texturized, entities intact
  (`it&#8217;s`).
- Params: `per_page` (default 10, max 100), `page`, `type` (only `post`),
  `subtype` (`any` or a comma list of registered types; unknown → 400
  `rest_invalid_param`), `_fields`. `X-WP-Total`/`X-WP-TotalPages` headers.
- Order is the reference's search relevance: a title holding every term,
  then a title holding any term, then an excerpt match, then a content
  match; newest first within a rank. Terms split on whitespace, quoted
  phrases stay whole, single characters are dropped unless they are the
  whole query, at most nine terms.
- `_links.self` carries `embeddable: true` and `targetHints.allow`:
  `[GET]` for a caller who cannot edit the item, all five methods for one
  who can. `about` points at the type, `collection` at `/wp/v2/search`.
