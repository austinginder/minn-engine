# Minn Admin on Minn Engine

Status: Minn Admin boots and runs on the engine. The app shell, boot payload,
asset serving, and the content-management surface all work; a real browser
signs in, loads the SPA, and lists/filters content read through the engine's
`wp/v2`. Suite: `tests/browser/boot.test.js` (7 checks, Playwright).

This is the milestone the project points at: the admin interface running on
the from-scratch engine instead of WordPress. Minn Admin is Austin's
MIT-licensed app, so the engine reads its shell shape and serves its assets
directly (no WordPress source is involved either way).

## How it is wired

- The Minn Admin dev plugin is symlinked into the engine at `public/minn/admin`
  (gitignored; it is its own repo, developed in parallel exactly like the
  shop.localhost symlink). Editing the app updates both sites live.
- `GET /minn-admin` and `/minn-admin/*` render the shell. The route requires a
  logged-in user who can `edit_posts`; otherwise it 302-redirects to
  `/wp-login.php?redirect_to=…`.
- `GET /minn/admin/assets/<path>` serves the app's CSS/JS/fonts from the
  bundled dir (path-traversal guarded, read-only). That URL is the file on
  disk, so hosts that 404 missing `.js`/`.css` still serve them. The PHP
  route stays as a fallback.
- `GET /wp-admin/admin-ajax.php?action=rest-nonce` returns a fresh `wp_rest`
  nonce for the signed-in user — the one admin-ajax action app.js needs, for
  its nonce-refresh-and-retry path.

## The boot payload

`minn_admin_boot_payload()` assembles `window.MINN` from the engine's own
options and capability engine: `restUrl`, a real `wp_rest` `nonce`, `user`
(id, login, name, role, gravatar), `site`, `gmtOffset`, the i18n slice, and a
`caps` map computed through `minn_user_can`. It also carries
`engine: "Minn Engine/x"` so it is self-identifying.

When Minn Admin runs as code on the runtime, the engine also asks the plugin's
own `Minn_Admin::boot_payload()` and adopts every key it does not compute
itself (connectors, discussion defaults, roles, post formats, the adapter
flags such as `cache`, `visibility`, `spamUsers`); engine-owned keys win. One
key is withheld on purpose: `notices`, the wp-admin capture the app would
otherwise trigger against a page the engine does not serve (so `menuRemoved`,
which comes from that capture, stays empty here). Before asking, the engine
stands the home query the way the reference serves the shell from, so the
newest post is the global post while the payload is built: the plugin's
`comments` detection reads `comments_open` for post 0 through that global, and
a site that closes comments on old posts reports the feature off when its
newest post is old. That is why the Comments entry is absent from the sidebar
on both stacks for the dogfood site.

**The load-bearing detail: `restUrl` is the pretty `/wp-json/` form, not the
plain `?rest_route=/` form.** app.js builds request URLs as
`restUrl + "wp/v2/posts?context=edit&status=…"`. With the plain form the
first `?` folds the query into the `rest_route` value and every list 404s;
with `/wp-json/` the path carries the route and the query params stay real.
The engine routes `/wp-json/*` natively, so this just works.

## What the list surface gained for the app

The content view requests `wp/v2/posts?context=edit&status=publish,future,
draft,pending,private`, so the list controller now honors:

- **`status`** (comma-separated). Public callers get only `publish`; any other
  status (or `context=edit`) requires an authenticated user with
  `edit_posts`/`edit_pages`, else `401`/`403 rest_forbidden_context`. Ordered
  by date descending across statuses.
- **`context=edit`** returns the edit-context objects (raw+rendered, cap-gated
  action links), so drafts and their editability travel to the client.
- **`author`** scopes the list (the client uses it for own-only types).

## What works in the browser

Sign in → the SPA boots (nav, workspace, all chrome) → Content lists every
post and page with correct statuses, filter tabs (All / Posts / Pages /
Trash), sortable columns, and row actions, all read live through the engine.
No fatal console or page errors.

## The engine is the only admin

The boot payload's `engine: "Minn Engine/x"` key is how the app knows there
is no WordPress behind it. On that flag Minn Admin (since 0.36.0) hides the
sidebar's WordPress button, the "Classic wp-admin" palette command, and the
three profile switches that only exist beside wp-admin, and labels the
engine's post types "Managed by Minn Engine". The engine reports
`pluginAjax: null` so plugin toggles ride `PUT wp/v2/plugins/{plugin}`, and
`site.blockTheme` from the active theme.

## Minn Admin is a plugin, and the engine honours it

The app is served while `active_plugins` names `minn-admin/minn-admin.php`,
the same record `wp plugin list` and the reference read. Deactivating it
(the Extensions toggle, `PUT wp/v2/plugins/minn-admin/minn-admin`, or
`wp plugin deactivate`) leaves the site, its REST API, cron and CLI running
and turns `/minn-admin/` into a page that says so and names
`wp plugin activate minn-admin`; the front bar goes with it. A site that
carries only the engine's bundle (no plugin folder in wp-content/plugins)
has no such record and keeps its admin. On the engine the app's deactivate
dialog says exactly this instead of promising wp-admin.

## The Minn bar on the public site

`Minn\Front\AdminBar` renders the app's own front-end bar on every themed
page for a signed-in reader who can `edit_posts`: the same markup the
plugin prints, the bundle's `assets/css/bar.css` and `assets/js/bar.js`
served through `/minn/admin/assets/`, and `window.MINN_BAR` with the REST
base, a `wp_rest` nonce, the app URL, the capability-filtered command list,
the searchable post types, and the status chip (only "Hidden from search"
when `blog_public` is 0, with the settings fix for `manage_options`). The
Edit button targets the singular being viewed when the reader can edit it
(`/minn-admin/editor/{rest_base}/{id}`). The body gains `minn-front-bar`;
previews and anonymous readers get no bar. The bar is on for everyone who
passes the gate: there is no per-person opt-in on the engine. Suite:
`admin-surfaces` (section 8) and `tests/browser/admin-views.test.js`.

Since Minn Admin 0.43 `bar.js` binds only to the corner whose
`data-minn-bar` repeats `MINN_BAR.key`, a 20-character key minted per
request (post content can carry the bar's ids, but cannot know the key);
without the pair it does nothing. The corner always ships ghosted, a
status chip included, and the root carries `data-minn-font`. The two
pre-paint scripts find the bar through `document.currentScript`, and a
reader who never chose light or dark starts in the site default the app
keeps in `minn_admin_site_appearance`. The status fix asks, then confirms
(`data-barfix`), with no id a label in post content could click. The
engine's copy fell behind the plugin's here once and the bar went inert
on every Minn site; the suites now check the key pair and that a menu opens.
Still owed: the per-person "site" scheme, which follows the site's default
scheme.

## Stale writes (Minn Admin 0.46)

A definition the app can edit carries a `stamp`, and a save sends it back
as `base`: a copy opened before someone else changed the definition is
refused with 409 (`minn_stale`) before anything is written. The stamp is
the definition's escaped JSON, its lists and keys sorted, as an md5 cut
to twelve characters. The engine answers the Structure lists itself
(`Admin\StructureController`), so it stamps each post type and taxonomy
the same way, from the same fields, and `admin-surfaces` compares the
values with the plugin's. Custom CSS, role defaults and the adapters'
builders (Gravity Forms, ACF field groups, ACPT, memberships) are Minn
Admin's own routes on the engine and stamp themselves; the suite checks
custom CSS and role defaults, a stale save of each included.

## The routes the engine answers in the plugin's place (2026-10-18)

The engine's own controllers answer 69 of the minn-admin/v1 routes; the
plugin's code answers the rest. A port falls behind when the plugin's
answer grows, so `admin-surfaces` section 9 compares every engine-owned
GET without captures (33, found from the `#[Route]` attributes) with the
plugin's, by keys and value types, with the known differences listed
beside their reasons (Minn's own release in `core`, the plugin
inventory left out of `boot-status`, the locked wp-config switches).

The first sweep found three ports that had fallen behind. **boot-locale** sent only the
catalog, so after a language switch the server-translated labels (role
names, surfaces, post formats, block forms, appearance slots) stayed in
the old language until a reload. It now adds the plugin's slices from
`BootPayload::fromPlugin()`. **Hiding** knew only the core views: the
engine refused a plugin surface as unregistered, and every hide or unhide
answered with empty surfaces and block forms, which the app takes as
the new nav, so the nav lost every plugin surface. While Minn Admin runs as code the four hide routes
are the plugin's own (`PreferencesController::leaveToPlugin()`), and the
boot payload's `hidden` is its list. **Search and plugin info** answered
a missing `q` or `slug` as the plugin does not (200, `no_slug`); now the
reference's missing-parameter 400.

The namespace index (`/wp-json/minn-admin/v1`) lists a route the engine
answers in part with the plugin's endpoints for the rest: the post-types
GET is the engine's, its POST the plugin's, and both are listed. The
engine's own endpoints still list no arguments.

## Honest gaps

The adapter namespaces (`minn-admin/v1/<plugin>/*`), captured admin notices,
`/stats`, and installing plugins or themes need runtimes the engine does not
have; the app degrades those panels. Everything the Manage nav shows
(Extensions, Users, Menus, Structure, System, Settings) answers from the
engine now: see `contracts/rest/minn-admin-v1.md`.

The point of this milestone is proven: **the admin app the whole vision hangs
on runs on the from-scratch, MIT engine.**
