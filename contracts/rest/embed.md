# `_embed` and the embed context

Captured from the reference on 2026-08-29; pinned live by `tests/embed.test.php`
(engine vs oracle, request by request).

## What `_embed` does

- `?_embed` (or `_embed=1`, `_embed=true`) answers every link in `_links` that carries
  `embeddable: true` and places the results under `_embedded[rel]`, one entry per link,
  in link order. `_embed=author,wp:featuredmedia` limits the rels; an unknown rel is
  ignored.
- Each linked object is fetched **as the same caller** with `context=embed` (below), so a
  draft author's post embeds its author exactly as `users/{id}?context=embed` would
  answer that caller.
- A rel whose embeds are all empty lists or errors is left out entirely: a post with no
  comments has no `_embedded.replies`, an authorless post no `_embedded.author`, a
  featured image pointing at a deleted attachment no `_embedded.wp:featuredmedia`.
  An empty list **beside** a full one stays: `wp:term` on a post with a category and no
  tags is `[[category], []]`.
- Nothing embeddable at all means no `_embedded` key.
- The same href is answered once per response (a list of posts by one author asks for
  the author once).

## `_embed` with `_fields`

- **Single objects** embed first and filter afterwards. Asking for `_embedded` keeps
  `_links` too: `_fields=id,_embedded` returns `id`, `_links`, `_embedded`. Asking for
  neither (`_fields=id`) embeds nothing.
- **Lists** filter each item first and embed from what survives, so
  `_fields=id,_embedded` on a list returns bare ids and `_fields=id,_links,_embedded`
  is what a list needs. Minn Admin's Content view sends the second form.

## `context=embed`

The view object cut to a fixed key set, in view order:

| Kind | Keys |
|---|---|
| posts, pages, declared types | `id date slug type link title excerpt author featured_media _links` |
| media | `id date slug type link title author featured_media caption alt_text media_type mime_type media_details source_url _links` |
| users | `id name url description link slug avatar_urls _links` |
| categories, tags, declared taxonomies | `id link name slug taxonomy _links` |
| comments | `id parent author author_name author_url date content link type author_avatar_urls _links` |

Other routes answer `context=embed` as `view`. Readability rules are the view rules.

## The filters the embeds rely on (and the app's list parameters)

Pinned in the same suite because the Content view sends them:

- `posts`/`pages`: `include`, `exclude`, `slug` (comma lists), `parent`, `search`
  (every whitespace-separated word must appear in the title, excerpt, or content),
  `orderby` (`date` default, `modified`, `title`, `slug`, `id`, `author`, `menu_order`,
  `include`), `order` (`desc` default; `orderby=include` keeps the include order and
  ignores `order`).
- `categories`/`tags?post={id}`: the post's terms; an unknown post is
  `rest_post_invalid_id` 400.
- `comments?post={id}`: that post's comments; an unknown post lists nothing; a post the
  caller cannot read is `rest_cannot_read_post` (401 anonymous, 403 signed in).

Two rules the reference applies to lists that the suite caught while pinning these:

- **Statuses need the edit cap.** Any `status` beyond `publish` from a caller without
  the type's `edit_posts`/`edit_pages` is `rest_invalid_param` 400 with
  `data.params.status = "Status is forbidden."` and `data.details.status` carrying
  `rest_forbidden_status` (401 anonymous, 403 signed in), before the context check.
- **Edit context is cut after the page.** A caller without `edit_others_*` gets the
  SQL page of published-or-own rows and then loses the rows they cannot edit, while
  `X-WP-Total` still counts them all: an author asking `per_page=5` can receive one
  item and a total of ten.

Same-date rows: the reference's tie order comes from MySQL's sort and is not stable
across page sizes (the same two pages swap between `per_page=5` and `per_page=10`).
The engine breaks date ties by id descending on a plain list and ascending once
`search` or `include` narrows the query, which matches the reference for full pages;
the suite avoids the unstable case.

## Also fixed while pinning

- Edit context adds no `author` link to an authorless post (the earlier rule said it always did).
- A user's `_links.self.targetHints.allow` carries the write verbs for any caller who can
  `edit_user` them (an administrator sees them on every user), not only for the user's own record.
- Classic content that already holds `<p>` markup renders with a newline after each `</p>`.
