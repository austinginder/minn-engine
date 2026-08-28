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
- `GET /minn-admin-asset/<path>` serves the app's CSS/JS/fonts from the
  symlinked dir (path-traversal guarded, read-only).
- `GET /wp-admin/admin-ajax.php?action=rest-nonce` returns a fresh `wp_rest`
  nonce for the signed-in user — the one admin-ajax action app.js needs, for
  its nonce-refresh-and-retry path.

## The boot payload

`minn_admin_boot_payload()` assembles `window.MINN` from the engine's own
options and capability engine: `restUrl`, a real `wp_rest` `nonce`, `user`
(id, login, name, role, gravatar), `site`, `gmtOffset`, the i18n slice, and a
`caps` map computed through `minn_user_can`. It also carries
`engine: "Minn Engine/x"` so it is self-identifying.

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

## Honest gaps (the `minn-admin/v1` surface)

app.js also calls the plugin's own `minn-admin/v1` namespace — `overview`
(dashboard stats), `notifications`, `plugin-updates`, `plugin-meta`, `core`,
`system`, and the ~100 adapters — plus `wp/v2/plugins`. The engine does not
implement those yet, so those calls `404` and app.js degrades them gracefully
(the Overview dashboard shows an error card; Content works fully because it
rides `wp/v2`). Implementing a first slice of `minn-admin/v1` (starting with
`overview` and `notifications`) is the next admin milestone. Media, Comments,
and Users views depend on `wp/v2/media`, `wp/v2/comments`, and the write side
of `wp/v2/users`, which are not built yet either.

The point of this milestone is proven: **the admin app the whole vision hangs
on runs on the from-scratch, MIT engine.**
