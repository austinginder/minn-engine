# Contract: wp/v2/comments

Status: implemented (list with status tabs and pagination headers, single,
create, moderation updates, trash, force delete). Suite:
`tests/comments.test.php` (30 checks: read parity in both contexts and
every refusal rung, plus the write lifecycle proven cross-stack in both
directions).

The Comments admin view drives this surface: an edit-context list per
status tab, status flips, replies, and force deletes.

## The object

View context: `{ id, post, parent, author, author_name, author_url, date,
date_gmt, content: { rendered }, link, status, type, author_avatar_urls,
meta, _links }`. Edit context inserts `author_email`, `author_ip`,
`author_user_agent` after author_name (author_url moves with them) and adds
`content.raw`.

- `status` strings map to `comment_approved` tokens: approved↔`1`,
  hold↔`0`, spam and trash pass through. The `status` query parameter also
  accepts `approve` and `all` (`1` + `0`).
- `type` is `comment` for an empty `comment_type`; rows are always scoped
  to `comment_type IN ('', 'comment')` (WooCommerce order notes and their
  kin never surface).
- `date`/`date_gmt`: the stored strings with `T` in place of the space.
- `author_avatar_urls`: sha256 of the author email, sizes 24/48/96.
- `meta` carries the plugin-registered `_wp_note_status` (null when unset).
- `link`: `{home}/?p={post}#comment-{id}`.
- `_links`: `self` (targetHints allow GET only, or the full verb list for a
  caller with `moderate_comments`), `collection`, `author` (embeddable,
  only when `user_id` > 0 — the authorless rule again), `up` (embeddable,
  carries `post_type`), and `in-reply-to` (embeddable, when parent > 0).

## Reads

- List defaults: `status=approve`, `per_page=10`, `page=1`, newest first
  by `comment_date_gmt`. Pagination rides `X-WP-Total` /
  `X-WP-TotalPages`. `_fields` filters per item.
- `context=edit` needs `moderate_comments` → `rest_forbidden_context`
  (401 anonymous / 403 authenticated). A non-approve `status` needs
  `edit_posts` → `rest_forbidden_param` (an author CAN query the hold tab).
- Single: unknown or non-comment-type id → `404 rest_comment_invalid_id`;
  an unapproved comment below `moderate_comments` → `rest_cannot_read`.

## Writes

- Create (`POST /comments`, body `{ post, parent?, content }`): the
  signed-in caller's identity fills the author columns (display name,
  email, url, REMOTE_ADDR, User-Agent). A caller with `moderate_comments`
  self-approves; everyone else lands in the queue (core's
  previously-approved-email shortcut is a recorded gap). 201 + `Location`,
  body in edit context. Anonymous → `401 rest_comment_login_required`;
  bad post → `403 rest_comment_invalid_post_id`; empty content →
  `400 rest_comment_content_invalid`. Approved creates recount the post's
  `comment_count` (stored, trusted on read — same rule as term counts).
- Update (`POST/PUT/PATCH /comments/{id}`): `status` flips, `content` and
  `author_*` edits; gate `moderate_comments` → `rest_cannot_edit`.
- `DELETE` without force = trash: writes core's restore bookkeeping
  (`_wp_trash_meta_status`, `_wp_trash_meta_time` commentmeta), sets
  status trash, returns the object; re-trashing → `410 rest_already_trashed`.
- `DELETE ?force=true`: removes row + meta, returns
  `{ deleted: true, previous }`.
- Every status transition and delete recounts the post's `comment_count`.

## Rendering

`content.rendered` = texturize, then paragraphs split on blank lines with
`<br />\n` for single newlines, each `<p>…</p>\n`. `make_clickable`
(bare-URL autolinking) is a recorded gap.

## Known gaps

- Duplicate-comment (409) and flood checks are not implemented.
- Core's previously-approved-email auto-approval shortcut for
  non-moderators is not implemented (they always land in hold).
- `make_clickable` in the renderer.
- Comment queries do not restrict by post readability the way the
  minn-admin/v1 feeds do; this matches core's behavior on this surface.
