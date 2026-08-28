# Contract: writes (create, update, delete)

Status: implemented for posts and pages (`POST` create, `POST`/`PUT`/`PATCH`
update, `DELETE` trash and force). Suite: `tests/writes.test.php` (19
checks, engine diffed against the reference and every write confirmed
through WordPress).

This is the milestone where the engine stops being a reader. A write issued
to Minn Engine changes the shared database, is visible to WordPress
immediately, and returns the edit-context object WordPress returns. The
capability engine from `caps.md` gates every operation.

## Create — `POST /wp/v2/{posts|pages}`

- Requires a valid cookie and nonce, then the type's edit primitive
  (`edit_posts` / `edit_pages`). A missing identity is `401
  rest_cannot_create`; an authenticated user without the primitive is `403`.
- Publishing (`status` of `publish`/`future`/`private`) additionally requires
  `publish_posts` / `publish_pages`.
- Inserts the row, then sets `guid` to `?p={id}` from the new id (a second
  update, as the reference does), then assigns terms.
- **A post with no category given gets the site `default_category`**, exactly
  as the reference assigns Uncategorized on create. Pages get none.
- Responds `201 Created` with a `Location: …/wp/v2/posts/{id}` header and the
  edit-context body.

## Update — `POST|PUT|PATCH /wp/v2/{posts|pages}/{id}`

- `404 rest_post_invalid_id` for a missing id or a type mismatch.
- `edit_post` (meta cap) gates it, so an author editing another user's post
  is `403 rest_cannot_edit` while an editor succeeds.
- Only the fields present in the body are written (`title`, `content`,
  `excerpt`, `slug`, `status`, `categories`, `tags`). Moving to `publish` for
  the first time stamps `post_date`/`post_date_gmt`; every update stamps
  `post_modified`/`post_modified_gmt`.
- Changing to a published status without the publish cap is `403
  rest_cannot_publish`.

## Delete — `DELETE /wp/v2/{posts|pages}/{id}`

- `delete_post` (meta cap) gates it.
- Without `force`, trashes the post (`post_status` → `trash`, stores
  `_wp_trash_meta_status`/`_wp_trash_meta_time`) and returns the trashed
  object. Trashing an already-trashed post is `410 rest_already_trashed`.
- With `force=true`, removes the row, its term relationships, and its
  postmeta, and returns `{ "deleted": true, "previous": {…} }`.

## The edit-context object

Create and update return the edit shape, not the view shape:

- `title`, `content`, `guid` carry both `raw` and `rendered`.
- `content` adds `block_version` (1 when the body contains block comments,
  else 0).
- Adds `password`, `permalink_template`, and `generated_slug`.
- `_links` always includes `author`, plus **cap-gated `wp:action-*` links**
  computed from the capability engine:
  - `wp:action-publish` ← `publish_{type}s`
  - `wp:action-sticky`, `wp:action-assign-author` ← `edit_others_{type}s`
  - `wp:action-unfiltered-html` ← `unfiltered_html`
  - `wp:action-create-categories` ← `manage_categories`
  - `wp:action-create-tags` ← `edit_posts`
  - `wp:action-assign-categories`, `wp:action-assign-tags` ← always
  So an administrator gets all eight and an author gets four
  (assign-categories, assign-tags, create-tags, publish) — proven against
  the reference for both roles. The response literally shows the capability
  engine driving output.

## Round-trip proof

The suite creates a draft through the engine, confirms WordPress reads the
same row, publishes it through the engine, confirms WordPress sees the new
status, then trashes and force-deletes it through the engine and confirms
WordPress reports it gone. The two implementations write the same database
and neither can tell which one made the change.

## Term counts stay honest across writes

`term_taxonomy.count` is a stored column WordPress trusts on read (it does not
recompute counts per request). So every write that changes a post's
contribution to a term's published total refreshes the affected counts:
create (default-category assignment), update when `status` changes
(publish/unpublish), trash, and force-delete. A milestone-6 bug where
force-delete removed the term links without recounting left the stored count
inflated, which WordPress then reported verbatim; the fix recounts every
taxonomy the post touched, and two back-to-back write-suite runs leave the
counts unchanged.

## Known gaps

- `author`, `featured_media`, `comment_status`, `sticky`, `template`,
  `format`, `meta`, and `date` are not yet writable (only the core content
  fields and terms are).
- Scheduling (`future` with a `date`) sets the status but not the cron event.
- No revision rows are written on update.
- Slug uniqueness is a simple `-2`/`-3` suffix and does not yet honor the
  reference's handling of reserved or hierarchical page slugs.
- No `wp:action-*` link for pages' page-specific actions beyond the shared set.
- Bulk writes, `meta` updates, and media uploads are out of scope here.

## Extended write fields (milestone 15)

Suite: `tests/write-fields.test.php` (18 checks, WordPress read-back on
every write).

- **Scheduling**: an explicit `date` is site-local; `status: publish` with
  a future date stores as `future`, and on create the modified stamps
  mirror the future date.
- **Sticky**: rewrites the serialized `sticky_posts` option (reindexed
  int list). Combined with a password in one request:
  `400 rest_invalid_field` "A post can not be sticky and have a password."
- **Password**: view-context content AND excerpt render as
  `{ rendered: "", protected: true }`; edit context keeps the rendered
  body with `protected: true`. `class_list` gains
  `post-password-required` after the format class.
- **Format**: assigns the `post_format` term (`post-format-{fmt}`,
  created on demand with name = slug); `class_list` gains BOTH
  `format-{fmt}` and, with the taxonomy classes at the end,
  `post_format-post-format-{fmt}`.
- **Author**: reassignment needs `edit_others_{type}s` →
  `403 rest_cannot_edit_others`.
- **Featured media**: `_thumbnail_id` meta; the object gains the
  embeddable `wp:featuredmedia` link and the `has-post-thumbnail` class
  (after the password class, before `hentry`); `featured_media: 0`
  deletes the meta.
- **Classic content**: a body without block delimiters renders through
  the autop pipeline (`<p>…</p>\n`), not verbatim.
- **Pages**: `parent` and `menu_order` columns; page edit-context action
  links carry NO sticky or taxonomy actions (posts only).
- **Edit-context self targetHints**: GET always, write verbs with
  `edit_post`, DELETE with `delete_post` (the earlier suites stripped
  `_links` and missed this).
- `meta.footnotes`, `comment_status`/`ping_status` round-trip.
