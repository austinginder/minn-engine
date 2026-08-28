# Contract: the editor-support surface

Status: implemented (revisions, autosaves, the edit lock, and the small
minn-admin/v1 routes the editor and Settings views load). Suite:
`tests/editor.test.php` (25 checks, cross-stack).

## Revisions and autosaves (wp/v2/{posts,pages}/{id}/…)

Gate: `edit_post` on the parent (`rest_cannot_read` 401/403; bad parent →
`404 rest_post_invalid_parent`). Revision rows are `post_type = revision`
children; autosaves are the ones named `{parent}-autosave-v1` (one slot
per author — a second save UPDATES the row and keeps its id), plain
revisions are the rest.

The object: `{ author, date, date_gmt, id, modified, modified_gmt,
parent, slug, guid, title, content, excerpt, meta, _links.parent }` — no
status/type. LIST responses are view-context (rendered-only duals); the
autosave CREATE response (HTTP 200, not 201) is edit-context (raw+rendered)
and adds `preview_link` with a volatile `preview_nonce`.

An autosave create writes the revision row with status `inherit`,
closed comment/ping, dates = now on BOTH date and modified. Engine post
force-deletes cascade to child revisions (as core does), so no orphans.

**Gap:** engine post updates do not write plain revisions (WordPress's do;
both stacks list whatever the table holds, so parity is unaffected).

## Single posts, contexts (fixed here)

`GET /wp/v2/{posts,pages}/{id}?context=edit` returns the edit object for
callers with `edit_post` (any status), `rest_forbidden_context` "Sorry,
you are not allowed to edit this post." below it. View context serves
non-published posts only to callers who may read them
(`401/403 rest_forbidden` "Sorry, you are not allowed to do that.").
Until this milestone the engine served only published posts in view shape.

## The edit lock

`POST /minn-admin/v1/posts/{id}/lock` (gate `edit_post`) writes
`_edit_lock` = `time:uid` and answers `{ acquired: true }`. The OTHER
user's edit-context post object then carries `minn_lock =
{ user, name }` within core's 150s window (suite-proven end to end).

## The small routes

- `/minn-admin/v1/templates?type=` → `{ templates: [] }` (no theme).
- `/minn-admin/v1/site-logo` → `{ supported: false, id: 0, url: "" }`.
- `/minn-admin/v1/permalinks` (manage_options) → structure/bases from
  options, `pretty` = structure non-empty, `app_url` (`/?minn_admin=1`
  under plain permalinks).
- `/minn-admin/v1/spam` (moderate_comments) → `{ providers: [],
  queue: { spam, pending }, disallowed_keys }` from live counts.
- `/minn-admin/v1/languages` (manage_options) → `installed` +
  `available` served from captured registry data
  (`public/minn/data/languages.json`, 133 locales), `current` from WPLANG.
- `/minn-admin/v1/media/months` → distinct `Y-m` of attachments, newest
  first.
- `GET /wp/v2/blocks` → `wp_block` rows (empty on this database),
  edit context gated on `edit_posts`.

## Recorded divergences (deliberate)

- `/minn-admin/v1/editor-styles` → `{ urls: [] }`: the reference serves
  core's block CSS from wp-includes, which is GPL code the engine neither
  carries nor serves. The editor iframe falls back to base styling.
- `/minn-admin/v1/patterns` → `{ patterns: [] }`: theme patterns are GPL
  theme content.
