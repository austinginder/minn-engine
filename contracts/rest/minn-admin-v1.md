# Contract: minn-admin/v1 dashboard slice (overview, notifications, boot)

Status: implemented for `GET /minn-admin/v1/overview`, `GET
.../overview/activity`, `GET .../notifications`, `POST
.../notifications/read`, `GET .../core` and `GET .../boot-status`.
Suite: `tests/minn-v1.test.php` (31 checks, live parity against the reference
running the real Minn Admin plugin on the same database, three capability
views, plus a two-directional read-marker round trip).

The oracle for this namespace is the Minn Admin plugin itself, symlinked into
`wp-reference/wp-content/plugins/minn-admin` and activated there. Every shape
below was captured from that running oracle; the engine implements the
captured behavior.

## Authentication and gating

Every route sits behind the `edit_posts` capability.

- Anonymous (no cookie, or a garbage cookie): `401 rest_forbidden`
  ("Sorry, you are not allowed to do that.") — NOT `rest_not_logged_in`.
- Valid cookie, missing or bad nonce: `403 rest_cookie_invalid_nonce`
  ("Cookie check failed"). The nonce check outranks the permission gate.
- Authenticated without `edit_posts`: `403 rest_forbidden` (standard REST
  behavior; not capturable on this database, which has no sub-author user).
- The permission gate outranks parameter validation: anonymous plus
  `days=200` yields the 401, not the 400.
- Unknown routes inside the namespace, and wrong methods on known routes
  (GET on `notifications/read`): `404 rest_no_route`.

## GET /overview — `days` parameter

Integer, default 30, bounds 7..90 inclusive.

- Not an integer: `400 rest_invalid_param`, `params.days` = "days is not of
  type integer." and `details.days` = `{ code: rest_invalid_type, message,
  data: { param: "days" } }`.
- Out of bounds: `params.days` = "days must be between 7 (inclusive) and 90
  (inclusive)" and `details.days.code` = `rest_out_of_bounds`,
  `details.days.data` = null. (No trailing period on this one; the type
  message has one.)

## GET /overview — response

`{ stats, chart, traffic, activity, store, greeting }`.

**stats** — four cards, each `{ key, label, value, delta, up }`; values are
`number_format_i18n` strings (comma thousands):

1. `posts` — published `post` count; delta "N draft(s)".
2. `pages` — published `page` count; delta "published".
3. `comments` — approved count; delta "N pending"; `up` = "warn" when any
   pending. When approved == 0 AND pending == 0 AND the caller has
   `list_users`, this card is REPLACED by `users` (total `wp_users` rows,
   delta "registered").
4. `media` — attachments with status `inherit`; delta "SIZE used" via
   `size_format(bytes, 1)`. The plugin caches the uploads walk in the
   `minn_admin_uploads_size` transient (12h); the engine honors a fresh
   cached value and otherwise walks `wp-content/uploads` without writing
   the cache back.

**chart** — activity buckets. `bucket_days` = 7 when days > 45 else 1;
`buckets = ceil(days / bucket_days)` (90 → 13). Each bucket
`{ label, value, from, to }`: label `date('M j')` site-local ("Week of M j"
for weekly), value = published posts+pages (`post_date_gmt`) plus ALL
comments (`comment_date_gmt`, any status) falling in the bucket, from/to =
GMT `(from, to]` bounds anchored at request time.

**activity** — up to 4 rows `{ text, time, color, goto }` sorted newest
first, `time` humanized "N unit(s) ago". Post rows: templates
"X published/scheduled/drafted “T”", color green/blue/accent, goto
`{ kind: editor, type: rest_base, id }`. Comment rows (3 newest; pending
ones only visible to `moderate_comments` holders): "X commented on “T”" /
"Comment from X awaiting moderation on “T”", color blue/amber, goto
`{ kind: comments, tab: approve|hold }`. Titles are texturized then
entity-decoded (plain text); empty titles become "(no title)".

**ORACLE-CAUGHT QUIRK (do not "fix"):** the recent-activity post query runs
with a multi-type `post_type` array plus `perm => editable`, and on that
combination WordPress restricts EVERY status — published included — to
`post_author = caller`, for every role including administrators. Authorless
(post_author 0) posts appear for nobody. Verified across admin, editor and
author views. The merge window is 5 by `post_modified` DESC plus 5 by
`post_date` DESC, deduplicated. A post whose GMT columns are zeroed (a
never-updated draft) takes its timestamp from site-local `post_date`.

**traffic** / **store** — `null` unless an analytics provider / WooCommerce
is present. The engine has no extension runtime, so both are always null
(honest gap; matches this database).

**greeting** — site-local hour: < 12 "Good morning", < 17 "Good afternoon",
else "Good evening".

## GET /notifications — response

`{ items: [...] }`, each `{ id, kind, icon, title, time, unread, group,
ago }`, sorted by `time` DESC. `time` here stays a UNIX integer (unlike the
overview's humanized `time`); `ago` carries the humanized form.

Sections, in capture order (rows only when their gate and data allow):

1. Pending comments (5 newest, `moderate_comments` only) — id
   `comment-{ID}`, kind `comments`, icon 💬.
2. Approved comments (3 newest, any caller) — same id scheme; a comment row
   never shows unless the caller may know the post exists
   (edit_post, else readable and not password-walled).
3. Plugin update rows (`update_plugins` cap) — GAP: needs an installed
   extension inventory; empty on this database, engine emits none.
4. Translation count row — id `translations-{n}`, sums the `translations`
   arrays across the update_plugins / update_themes / update_core
   transients.
5. Theme update rows — same gap as plugins; empty here.
6. Core upgrade row (`update_core` cap) — only when the first offer in the
   `update_core` transient has `response == "upgrade"` (this database says
   "latest"); id `core-{version}`, time = transient `last_checked`.
7. Core auto-update notice — from the `auto_core_update_notified` option,
   `type == success` within 14 days.
8. Captured admin notices (Minn_Admin_Notices) — GAP: engine has no notice
   capture store; empty on a fresh activation.
9. New users (`list_users` only): 2 newest with `user_registered` in the
   last 7 days — id `user-{ID}`, kind `system`, icon 👤.

Decoration: `unread` = time > the caller's `minn_admin_notif_read_at`
usermeta AND id not in their `minn_admin_notif_read_ids` list; `group` =
"Today" when time >= site-local midnight else "Earlier"; `ago` = humanized.

## POST /notifications/read

Body `{ id }` appends to `minn_admin_notif_read_ids` (deduplicated, capped
at the last 200, stored as a serialized PHP string list); empty body sets
`minn_admin_notif_read_at` to now and deletes the id list. Response
`{ ok: true }`. The suite proves the engine-written serialized list is read
back correctly by real WordPress and the WordPress-written `read_at` by the
engine.

## Humanized ages (behavior table, captured from the oracle)

`human_time_diff` rounds within a unit and hands off at the next boundary:
seconds below 60 (exact count), then minutes / hours / days / weeks (30-day
months) / months (365-day years) / years, each `max(1, round(diff/unit))`.
Pinned pairs: 89s → "1 minute", 90s → "2 minutes", 3599s → "60 minutes",
86399s → "24 hours", 604799s → "7 days", 2591999s → "4 weeks", 31535999s →
"12 months". `size_format(0,1)` → "0.0 B"; 1023 → "1,023.0 B"; units step
at 1024.

## GET /overview/activity

The events behind one chart bar. `from`/`to` are REQUIRED strings matching
`^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$` — missing params come back as one
aggregated `400 rest_missing_callback_param` ("Missing parameter(s): from,
to", `data.params` a LIST); malformed ones as one aggregated
`rest_invalid_param` whose `details.*.code` is `rest_invalid_pattern` with
`data: null`. Response `{ items }`, each `{ kind, id, [type,] text, time,
color, ago }` sorted newest first, capped at 100: published posts/pages in
the `(from, to]` GMT window (kind `post`, decodes the RAW stored title —
not texturized; author falls back to "Someone") and comments of type
''/'comment' (kind `comment`, decodes the TEXTURIZED title — the oracle's
asymmetry, kept; approved-only below `moderate_comments`; every row passes
the comment-row visibility gate).

## GET /core

Gate `update_core` (403 rest_forbidden below it). `{ version, dbUpgrade,
update }`. The oracle reads its version from core's version.php and phones
home via `wp_version_check()`; the engine reads `version_checked` from the
`update_core` transient blob and never phones home. `update` is
`{ version, locale }` when the first offer says `upgrade`, else null.
`dbUpgrade` is false on the engine by definition (no newer core code on
disk for the database to lag behind).

## GET /boot-status

The app's one-round-trip boot burst. Absent sections are the contract's
own fallback mechanism (the client loads them standalone), which is what
makes an honest partial implementation possible. The engine serves
`notifications` (everyone), `core` (only with `update_core`), `types`, and
`pendingComments` (count of hold comments of type ''/'comment', present
while a UI type still supports comments); it omits `plugins`,
`pluginUpdates` and `pluginMeta` (no plugin installation to describe) and
`store` (no WooCommerce).

`types` is the edit-context type list slimmed to `{ slug, rest_base, name,
viewable, labels: { singular_name }, supports, hierarchical }`. Which types
a user sees follows the per-type edit gate, pinned empirically: `post`,
`attachment` and `wp_block` ride `edit_posts`; `page` rides `edit_pages`;
the theme-object types (nav_menu_item, wp_template, wp_template_part,
wp_global_styles, wp_navigation, wp_font_family, wp_font_face) ride
`edit_theme_options` (admin sees 11, editor 4, author 3). The enrichment
data lives in `src/data/types-admin.json`, deliberately OUTSIDE
`data/types.json` so the wp/v2 types payload stays byte-faithful.

## Plugin-active oracle: wp/v2 side effects

Activating Minn Admin in the reference changed three core-surface shapes,
now matched by the engine (caught by the auth/caps/writes suites):

- View-context user objects (anonymous included): `meta` gains
  `show_admin_bar_front` ("true"/"false" strings, usermeta, default
  "true"), turning the empty list into an object.
- Edit-context user objects: the same key joins `persisted_preferences`.
- Edit-context post objects gain the plugin's registered fields:
  `minn_modified` (a live post carries an autosave revision newer than its
  save; false for callers who cannot edit the post) and `minn_lock` (the
  OTHER user holding a `_edit_lock` within core's 150s window as
  `{ user, name }`, else null).

## Known gaps (recorded, not hidden)

- Plugin/theme update rows and captured admin notices need extension
  awareness the engine does not have.
- `sanitize_text_field` on the read id is approximated (tag strip +
  whitespace collapse); ids are machine-generated slugs in practice.
- The remaining dashboard routes (`/overview/activity`,
  `/overview/traffic-day`, `/stats`, `/boot-status`, `/notices/*`) are not
  yet implemented; the app degrades per-panel.
