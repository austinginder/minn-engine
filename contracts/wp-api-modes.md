# MINN_WP_API: how much of the WordPress runtime a request gets

An engine-defined switch, not a WordPress behaviour: WordPress has no such
constant. A site sets it in `wp-config.php`, beside its other constants:

```php
define('MINN_WP_API', 'auto'); // 'on' (the default) | 'auto' | 'off'
```

`true` reads as `'on'` and `false` as `'off'`. With no constant, or a value the
engine does not know (which it logs once a request), the site boots as `'on'`,
so nothing changes for a site that never sets it. The reading is
`Minn\Runtime\WpApiMode`.

## What each mode loads

A **public page** is a GET or HEAD that is not REST (`/wp-json/…` or
`?rest_route=`), not the admin (`/wp-admin`, `/minn-admin` and below) and not
one of WordPress's entry scripts at the webroot (`wp-login.php`, `wp-cron.php`,
`wp-comments-post.php`, `xmlrpc.php`, `wp-signup.php` and the rest of
`wp-*.php`). Everything else is a request behind the pages.

| | `on` | `auto` | `off` |
|---|---|---|---|
| Plugins and must-use plugins, on a public page | load | no | no |
| Plugins and must-use plugins, behind the pages | load | load | no |
| Minn Admin's plugin file, behind the pages | load | load | load |
| Minn Admin's plugin file, on a public page | load | no | no |
| The theme's `functions.php`, where a themed page is drawn | load | load | load |
| An extension route answering a public page | after the boot | without the runtime | without the runtime |

- **Without the runtime** means no facade (`wp-api/`), no plugin loading and no
  boot hooks (`plugins_loaded`, `init`, `wp_loaded`): the extension's route
  answers from the engine's own classes. Before it answers, the engine checks
  that none of its own routes ahead of the extensions (`Engine::FRONT_ROUTES`:
  assets, sign-in, the admin shell, ajax, admin actions, the probe routes,
  sitemaps, comment posting, the entry scripts) claims the method and path, read
  from their `#[Route]` attributes alone (`Router::claims`). When one does, or
  no extension route answers (a route that throws `RouteMiss`, say), the request
  boots as any other, on the extensions already registered (`FrontParts`).
- **A themed page** under `auto` or `off` still boots the facade, which the
  block renderer draws the head, the footer and the main query through; only
  the plugins stay out. The boot hooks still fire for the facade's own
  registrations. The theme's `functions.php` loads because it is the theme:
  block themes enqueue their own stylesheet there (Twenty Twenty-Five does).
- **Minn Admin's plugin file** is the admin's server half (its adapters and
  routes, and the `wp/v2` hooks the editor's page reads go through), so it loads
  behind the pages in every mode. The engine draws the Minn bar itself, so a
  public page does not need it.
- **The drop-ins** are the host's: `advanced-cache.php` runs before the engine
  reads the mode, and `object-cache.php` loads wherever the facade does.
- **WP-CLI** is not a web request. Its commands always load the plugins.

## What it costs a site

Each mode is a trade, and the site owner makes it:

- **`auto`**: a plugin that works on public pages (an SEO plugin's head tags, a
  form plugin's shortcode, a plugin's sitemap) does not run there. A POST to a
  public page counts as behind the pages, so a form that posts back to its page
  still reaches its plugin.
- **`off`**: no plugin runs anywhere, including ajax, REST and the entry
  scripts. A plugin's REST routes answer `rest_no_route`, and its ajax actions
  answer `0`. Signing in, Cove's one-time login links and Minn Admin keep
  working: the engine answers them itself.
- **Scheduled events.** A public page that loads no plugins never fires the cron
  option's due events. Their callbacks belong to plugin code that is not there,
  and firing them would drop a single event without running it. Such a page
  still runs the engine's own jobs (scheduled posts, sweeps, the daily update
  pass). The events run on the next request that loads the plugins:
  `wp-cron.php` under `auto` (point a system cron at it), or `wp minn cron`.
  Under `off` no web request loads them; `wp minn cron` still does.

## Proof

- `tests/unit/wp-api-mode.php`: which requests are public pages, what each mode
  loads, `Router::claims`.
- `tests/wp-api-modes.test.php`: the test site under `php -S`, the mode set per
  request by the router. A fixture must-use plugin
  (`tests/fixtures/mu-plugins/minn-test-wp-api.php`) shows where plugins ran:
  - A themed page is byte-identical in every mode, the plugin's own output aside,
    and so is an extension route, which answers without the facade under `auto`
    and `off`.
  - Signed in, both kinds of page carry the same Minn bar in every mode.
  - Ajax, REST and a POST run the plugin under `on` and `auto` and not under
    `off`. Minn Admin's REST answers in all three.
  - Under `auto` a due plugin event survives a public page and runs on
    `wp-cron.php`.

## Measured

Taken on 2026-10-24 on a site whose pages are mostly an extension's built HTML
files, with one must-use plugin and Minn Admin active (FrankenPHP, PHP 8.5,
warm OPcache). Each page was requested 40 times in each mode, alternating `on`
and `auto` request by request, so load drift touches both alike; the figures are
the server's share of each request (time to first byte less connection and
TLS), as medians, over two runs that agreed within a millisecond.

- An extension's built page: about 20 ms under `on`, 3 to 5 ms under `auto`.
  The time saved is plugin loading, the boot hooks (`init` alone is about 5 ms)
  and the facade (2 to 5 ms).
- A themed page, a post and the feed: 2.5 to 4.5 ms less, since only the plugins
  stay out.
- Every page was byte-identical in both modes.

Comparing whole runs, one per mode, gave contradictory figures on the same
machine, so measure by alternating.
