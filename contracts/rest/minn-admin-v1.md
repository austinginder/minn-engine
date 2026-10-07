# Contract: minn-admin/v1 dashboard slice (overview, notifications, boot)

Status: implemented for `GET /minn-admin/v1/overview`, `GET
.../overview/activity`, `GET .../notifications`, `POST
.../notifications/read`, `GET .../core`, `POST .../core/update` and `GET
.../boot-status`.
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
6. Core upgrade row (`update_core` cap) — on the oracle only when the first
   offer in the `update_core` transient has `response == "upgrade"`; id
   `core-{version}`, time = transient `last_checked`. The engine offers Minn
   instead (see `GET /core`): id `minn-{version}`, title "Minn {version} is
   available", `update: { type: "core", version, name: "Minn" }`, time = when
   GitHub was last asked. The suite drops `core`-type rows from both sides
   before the diff.
7. Core auto-update notice — from the `auto_core_update_notified` option,
   `type == success` within 14 days. Oracle only: WordPress's auto-updates
   never run under the engine.
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

Gate `update_core` (403 rest_forbidden below it). On the oracle `{ version,
dbUpgrade, update }` describes WordPress: its version from core's
version.php, an offer from `wp_version_check()`, `update` `{ version,
locale }` when the first offer says `upgrade`. On the engine core is Minn
itself, a deliberate divergence (2026-10-07): `{ product: "minn", version,
dbUpgrade: false, checked, update }`, where `version` is
`MINN_ENGINE_VERSION`, `checked` the time GitHub was last asked, and
`update` `{ version, url, published }` when the latest published release of
`austinginder/minn-engine` is newer, else null. WordPress's own offer (the
`update_core` transient a parked copy may write) is never shown: it is not
Minn's to install. The app keys its "Update Minn" wording off the boot
payload's `engine`, not off `product`.

`Ops\Releases` asks `api.github.com/repos/austinginder/minn-engine/releases/latest`
at most once a day and keeps the answer in the `minn_release` option (JSON
`{ checked, latest }`). A request to `/core` or `/boot-status` that finds the
answer a day old asks again after its response is sent. A 404 (no published
release, or a private repository) offers nothing; any other failure keeps the
last answer and waits a day. A release counts only when it is published, not a
pre-release, tagged `v<major>.<minor>.<patch>`, and carries a `minn.zip` asset;
the asset's `digest` (`sha256:<hex>`) is the checksum the install demands.

## GET /changelog (on the engine)

Gate: the floor. `{ version, markdown }`: the bundle's version and Minn
Admin's changelog. A Minn release does not carry the bundle's changelog.md,
so `Ops\Changelog` reads it from `austinginder/minn-admin` on GitHub the
same way as the engine's (below; option `minn_admin_changelog`). The
reference answers its bundled file, Unreleased sections included; the
suite compares the engine's answer with that file less those sections.

## GET /engine-changelog

Gate: the floor (`edit_posts`). `{ version, markdown }`: `MINN_ENGINE_VERSION`
and Minn's changelog. The changelog is not in a release (nothing on a site
running Minn names its history); `Ops\Changelog` reads `changelog.md` from the
repository's default branch on raw.githubusercontent.com at most once a day,
keeps it in the `minn_changelog` option, and leaves out sections still marked
Unreleased. A 404 (a private repository) answers `''`; any other failure keeps
the last copy. Engine only: the oracle has no such route.

## POST /core/update

Gate `update_core`. Installs the release on offer and answers `{ version }`.
Nothing newer on offer: 400 `minn_current`. A failed install: 500
`minn_update_failed` with the reason. `Ops\EngineUpdate` downloads `minn.zip`
only from `https://github.com/austinginder/minn-engine/releases/download/`
(every redirect hop judged; objects.githubusercontent.com and
release-assets.githubusercontent.com are the allowed CDN hosts), refuses
an asset without a published sha256 or one that does not match it, unpacks it
through `Ops\Archive` beside the engine, checks that its bootstrap names the
offered version and `bin/minn` is there, then swaps the folder in two renames
(the running engine aside, the new one in; the first is undone when the second
fails) and removes the old copy. A legacy `.install.json` inside the engine
comes along. One update runs at a time (a lock in the temp folder keyed by
the engine's path). A development checkout (a link, or a folder under git at
any depth) is refused. The same install runs from the command line as
`php minn/bin/minn update` (`--check` only asks GitHub).

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
data lives in `public/minn/data/types-admin.json`, deliberately OUTSIDE
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

## The Manage views (suite `admin-surfaces`, 59 checks)

Every route below sits behind the `edit_posts` floor; the finer gate is
named per route. Where the plugin answers from site data the engine
matches it at live parity; where the plugin answers from WordPress
internals the engine answers for itself in the same shape.

**Structure.** `GET term-taxonomies` (parity: show_ui taxonomies that
organise a public type and pass the caller's `manage_terms`; categories,
tags, then alphabetical; `count` counts every term, empty ones included).
`GET post-types` (manage_options; parity apart from `backends`, which is
`[]` because the engine stores no post-type definitions: core types from
`data/types-admin.json` supports, site-declared types as source `minn`,
`post` carries `post_format` the way the reference registers it, `count`
sums publish/future/draft/pending/private). `GET taxonomies`
(manage_options; parity apart from `backends`). Creating or editing types
and taxonomies has no route: that is the `minn.json` manifest's job.

**Extensions.** `GET themes` (switch_themes): every theme folder with a
style.css, active first; `block` is the presence of `templates/index.html`;
`on_wporg` false, `update` null, `auto_updates` false: there is no update
channel. `POST themes/activate {stylesheet}` writes `stylesheet`,
`template`, and `current_theme` (404 for an unknown folder or a missing
parent). `GET plugin-updates` is the empty shape with `autoAllowed: false`;
`GET plugin-meta` is `{}`; `GET translations` is `{count: 0, groups: []}`.
The plugin list itself is `wp/v2/plugins` (contracts/rest/plugins.md).

**Adding themes and extensions** (suite section 6a; `Minn\Admin\Packages`).
`GET themes/search?q=` asks wordpress.org (the popular list for an empty
query) and answers in the plugin's item shape with `installed`/`active`
from disk; `POST themes/install {slug}` downloads that theme's release zip
from downloads.wordpress.org; `POST themes/upload` (multipart `file`,
optional `overwrite`) unpacks a zip; `POST themes/delete {stylesheet}`
removes a folder that is not the active theme or its parent. Extensions:
`POST plugins/upload` and `POST plugins/install-url {url | github, asset}`
accept a folder carrying `minn.json` or a file with a Plugin Name header
(WordPress plugins load as code on the engine since Track E); an archive
with neither is refused 400 `not_plugin`, and `GET plugins/search` is
honestly empty. `DELETE
wp/v2/plugins/{plugin}` removes an inactive folder. Every archive goes
through one unpacker: https only, exactly one top-level folder, no
absolute or dotted paths, identity checked (style.css Theme Name;
minn.json) before the folder is moved in; a folder that already exists
answers 409 `folder_exists` with both versions, and `overwrite` replaces
it. Caps: `install_themes`, `delete_themes`, `install_plugins`,
`delete_plugins`; the boot payload reports them.

**Editor previews.** `POST render-blocks {blocks: [markup], post}` renders
each string through the public site's block renderer and answers
`{rendered: [...], styles: {urls, inline}}`; `GET editor-styles` is the
same `styles`: the engine's `blocks.css`, the active theme's `style.css`,
and the theme.json rules plus font faces inline, so a preview is styled
like the page.

**System** (manage_options). `GET system` carries the plugin's keys
(`generated, checks, config, logs, licenses, extensions, integrations,
groups`) with the engine's own facts: checks `engine, php, https, memory,
opcache, debug, uploads, autoload, cron, mail`; groups `Minn Engine, PHP,
Database (+ tables, autoload), Server`; `config.editable` is false and
every constant is `locked` (the engine never rewrites wp-config.php, and
`POST system/config` refuses with 400 `not_editable`); `licenses` and
`integrations` are null; `extensions` lists manifests first, then the
WordPress plugin folders marked "not run". `GET system/cron` lists every
scheduled post as a one-off `minn_publish_post` event; `GET
system/autoload` mirrors the plugin's query; `GET system/logs`,
`GET/DELETE system/logs/{id}`, and `GET/DELETE system/debug-log` read the
debug log the failure handler writes and PHP's own error log when it is a
separate file inside the site (`Minn\Admin\Logs`; the last 256 KB, the
partial first line dropped; files outside the site are named, never read).

**Sessions.** `GET users/{id}/sessions` (self, or `edit_users`) lists the
live rows of `session_tokens` as `{verifier, ip, ua, login, expiration,
current}` newest first; `verifier` is the store's key, the sha256 of the
token, so `current` is computable from the caller's own token. `DELETE
users/{id}/sessions` signs the person out everywhere, keeping the caller's
own session when they act on themselves; `DELETE
users/{id}/sessions/{verifier}` removes one (404 `not_found`). The
reference reads the engine's rewritten store (round trip in the suite).

**Appearance.** `GET/POST me/appearance` and `users/{id}/appearance`
(`edit_users` for others) read and write the plugin's `minn_admin_appearance`
user meta as a serialized array (decoded by `Serialized::decode`, never
`unserialize`), so a scheme picked on either stack shows on the other.
`defaultAdmin` and `frontBar` are always reported `true` and never written:
the engine has no other admin and no other bar. The boot payload's
`user.policy` is `{signin: 'minn', toolbar: 'minn'}` for the same reason.

**Languages** (suite section 5b). A person's locale is their `locale` user
meta, else `WPLANG`, else `en_US`, as the reference resolves it; the boot
payload and `GET boot-locale` carry `locale`, `rtl`, the app's `i18n` map
and `i18nPlural` rule read from the JED catalogs under
`wp-content/languages/plugins/minn-admin-<locale>-*.json` (the bundle's
`languages/` folder is the fallback), and the shell's `<html lang dir>`
follow. `GET languages` lists what is on disk as installed (a core pack or a
Minn Admin pack), the captured registry as available, `canInstall` for
`manage_options`. `POST me/language`, `users/{id}/language` and
`site/language {locale}` write the meta or `WPLANG`; a locale with no files
yet is installed first by fetching Minn Admin's own pack from the release
the bundle's `manifest.json` names (https only, sha256 checked, only that
locale's files unpacked into `wp-content/languages/plugins/`), which is the
same set of files the reference's updater writes. Core language packs are
not fetched: the engine has no core strings to translate with them yet.

**Documents.** `GET changelog` and `GET guide` serve the bundle's own
`changelog.md` and `docs/user-guide.md` with the app version (parity).
`GET users/{id}/hidden` is `{hidden: []}`.

## Known gaps (recorded, not hidden)

- Captured admin notices (`/notices/*`), `/stats`, `/overview/traffic-day`
  and the adapter namespaces need plugin runtimes the engine does not have.
- `sanitize_text_field` on the read id is approximated (tag strip +
  whitespace collapse); ids are machine-generated slugs in practice.
- `wp/v2/users/{id}/application-passwords` lists `[]`: no passwords exist
  and none can be created yet.

## Updates (2026-08-29)

The engine asks wordpress.org itself (`api.wordpress.org/plugins/update-check/1.1/`
and `themes/update-check/1.1/`, sent the installed headers and `all=true`) and keeps
the answer in the `minn_updates` option as JSON for twelve hours; `POST check-updates`
asks again now. Offers are only listed where the installed version is older
(`real_plugin_updates` on the reference). Pinned by `tests/updates.test.php` on the
dogfood site against its own reference, both freshly checked:

- `GET plugin-updates` → `{updates: {file: version}, themes: {stylesheet: version},
  translations: 0, translationGroups: [], auto: [file…], autoAllowed: true}`. Language
  packs are wordpress.org state the engine does not carry, so translations stay 0.
- `GET plugin-meta` → `{file: {slug, icon, url}}` for every plugin the directory knows
  (offers and current alike; the svg, 2x, or 1x icon; the directory URL).
- `POST check-updates {}` → `{ok, pluginUpdates, themeUpdates, translations: 0,
  translationGroups: [], plugins: n, themes: n}`, plus on the engine `core` (the
  `GET /core` answer after asking GitHub again; `update_core` alone may call it).
- `POST plugins/update {plugin}` (with or without `.php`) downloads the offer's
  `downloads.wordpress.org` package through the one unpacker, replaces the folder,
  and answers `{updated: true, version}`; nothing offered → 400 `no_update`; an
  active plugin stays active (`active_plugins` is untouched by a folder swap).
  `POST plugins/update-all {}` → `{updated: [], failed: [], errors: []}` (`{updated: []}`
  when nothing was pending). `POST themes/update {stylesheet}` the same for themes.
- `POST auto-updates {type: plugin|theme, asset, enabled}` → `{auto: [...]}` writes
  `auto_update_plugins` / `auto_update_themes` (serialized string lists, the shape the
  app and WordPress share); an unknown asset is 404 `minn_auto_updates_unknown`.
  When auto-updates are off for the type (below) the answer is 400
  `minn_auto_updates_disabled`, "Automatic updates are turned off for this site.",
  before the asset is looked up. `Minn\Cron` applies the listed offers once a day
  (`minn_auto_updates_last`), for the types the same gate allows.
- **Auto-updates** (`Minn\Ops\AutoUpdates`, behind `wp_is_auto_update_enabled_for_type`;
  captured 2026-10-07 by running the reference with each condition): only `plugin` and
  `theme` can be on (`core`, `translation`, anything else: false). Off when file mods are
  refused (`DISALLOW_FILE_MODS` through `file_mod_allowed`, context `automatic_updater`;
  then `automatic_updater_disabled` is not even asked). Otherwise `automatic_updater_disabled`
  receives `AUTOMATIC_UPDATER_DISABLED` and a plugin may turn it back (false re-enables).
  Then `plugins_auto_update_enabled` / `themes_auto_update_enabled` receive that answer
  and have the last word. A version-control checkout does not change it. The parked
  references define `AUTOMATIC_UPDATER_DISABLED` so the oracles never update
  themselves; the updates suite puts both stacks under one gate with a temporary
  mu-plugin on `automatic_updater_disabled`.
- `GET themes` items carry `on_wporg` (the directory knows the stylesheet), `update`
  (the offered version or null) and `auto_update`; `auto_updates` is true for a caller
  with `update_themes` while auto-updates are on for themes. Theme names and authors are served as the reference serves
  headers: tags stripped, a bare `&` as `&amp;`.
- Notifications gain the reference's rows: `plugin-{file}-{version}` and
  `theme-{stylesheet}-{version}` of kind `updates` with the `update` payload the app
  renders its in-row button from, timed at the check.

### A plugin that hosts itself (2026-09-03)

The directory is not the only source of an offer. A plugin that hosts itself or
sells itself is not on wordpress.org, and publishes its offer by filtering
`site_transient_update_plugins`, which is where WordPress reads every plugin's
update. `Runtime\PluginUpdates::supplied()` asks the runtime that same question
with the transient the reference builds (`last_checked`, `checked` as file =>
installed version, empty `response` / `no_update` / `translations`), and
`Ops\Updates::check()` merges the answer: an entry a plugin supplies wins over the
directory's for the same file, and a supplied offer removes that file from
`no_update`. An empty `checked` is why this has to carry the installed versions:
an updater returns early without them, which is exactly what the reference's does.

Facts this settled, found on minn.run (2026-09-03), where Minn Admin 0.36.0 was
installed with 0.37.0 released and nothing was offered:

- The check runs where the runtime is booted. A REST request boots it, so the
  Extensions view sees supplied offers; `wp plugin list` and a cron trigger that
  loads no plugins do not, so `state['supplied']` records which files answered for
  themselves and their entries ride across a check that could not ask.
- `pluginMeta` gives a directory URL only to an entry the directory knows (its `id`
  starts `w.org/`); a self-hosted plugin that published no URL of its own gets none,
  rather than a link to a wordpress.org page that does not exist.
- **Applying a supplied offer goes through its publisher.** A `downloads.wordpress.org`
  package is downloaded by the engine as before. Anything else is asked of
  `upgrader_pre_download`, the filter the reference runs before every download, and
  installed only when the publisher hands back a file it fetched and verified
  (`Runtime\PackageDownload`). A refusal is answered with the publisher's own code and
  message at 500 `update_failed`; no answer at all is "the offer's package is not on
  wordpress.org and its publisher did not verify the download". This is stricter than
  the reference, which downloads whatever the offer names: here an archive nobody
  vouched for is never unpacked over a folder. Minn Admin's own updater is the worked
  example, checking the sha256 its release manifest publishes.

## The plugin directory (2026-08-29)

WordPress plugins run on the engine, so the wordpress.org directory answers here too,
through `api.wordpress.org/plugins/info/1.2/` (parameters bracket-encoded, a
WordPress-style User-Agent; the API answers nothing otherwise):

- `GET plugins/search?q=&page=` → `{plugins: [{slug, name, description, installs, rating,
  version, icon, installed}], page, pages, total}`, twelve per page, `installed` the plugin
  file when the folder exists.
- `GET plugins/info?slug=` → `{slug, name, author, description, installs, version, rating,
  icon, source: "wporg"}`, cached twelve hours per slug in the `minn_plugin_info` option.
- `POST wp/v2/plugins {slug, status?}` (the app's catalog install) downloads the directory's
  `download_link` through the one unpacker and answers 201 with the plugin item; `status:
  active` activates it (needs `activate_plugins`); no slug is `rest_missing_callback_param`.
- `POST plugins/upload` and `plugins/install-url` accept WordPress plugin zips as well as
  Minn extensions.

Minn Admin's app still hides the catalog on `B.engine`; lifting that gate is an app change.

## Minn Admin runs as code (2026-08-29)

The plugin folder passes the runtime's symbol gate with nothing missing, so the runtime
loads `minn-admin/minn-admin.php` like any other plugin. The engine keeps the shell, the
front bar, the sign-in flow, and maintenance: right after the include the runtime removes
`Minn_Admin::maybe_render_app`, `maybe_maintenance_mode`, `maintenance_rest`,
`login_redirect`, `enforce_toolbar_policy` and the four `Minn_Admin_Bar` hooks
(`Runtime\Plugins::MINN_ADMIN_HOOKS`). The engine's own `minn-admin/v1` and `wp/v2`
controllers answer first; every route the plugin registers that the engine has no
controller for (`surfaces`, `stream/*`, `licenses`, `connectors`, `custom-css`, `db/*`,
the adapter routes) answers through the runtime fallback. The boot payload gains the
plugin's own slices when it is loaded: `surfaces`, `editorPanels`, `designs`,
`editorCommands`, `blockForms`, `insertBlocks`, plus `caps.licenses` from
`minn_admin_licenses_can_manage()`. On the dogfood site this lights the Tools group
(Stream's Activity Log), the Licenses tab, Settings → Design (custom CSS, gated on
`edit_css`, which the engine maps to `unfiltered_html`) and Connectors. The site icon in
the sidebar is the `site_icon` attachment's file URL.

## Overview metrics (2026-08-31)

The overview payload carries the metric-card system beside `stats`:
`metrics` (the full catalog: the default layout first, then cap-gated
cards — drafts behind edit_others_posts, comments_pending behind
moderate_comments, users behind list_users; the store group needs the
WooCommerce runtime and is honestly absent), `metricKeys` (what shows),
`metricDefaults` (the site default from option
minn_admin_overview_metric_defaults), `metricCustom` (whether the caller
saved a personal pick in user meta minn_admin_overview_metrics), and
`canSetMetricDefaults` (manage_options). Stats deltas are cap-gated the
same way: the draft count needs edit_others_posts, the pending count
moderate_comments; every card carries `group` and `goto`.

Saves: POST `/overview/metrics` {keys} stores at most six cleaned
sanitize_key ids for the caller (null or [] clears); POST
`/overview/metric-defaults` (manage_options) does the same for the site
option. Both answer the STORED layout, unfiltered by the catalog. The
overlay rule: saved keys land on the fallback layout slot by slot,
unknown keys drop and the slot refills from the fallback left to right.

The site-logo route now answers the app's contract: `supported` is true
for any block theme (its Site Logo block manages one without declared
support) or a classic theme with custom-logo support; `id` from the
theme mods, `url` the medium rendition.
