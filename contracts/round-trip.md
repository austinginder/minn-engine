# The round trip

The second definition of done (`docs/vision.md` §3) is that any WordPress
site can resume on Minn: nothing lost, nothing locked in, nothing stops.
`tests/round-trip.test.php` is the test for it. It takes a copy of a real
site, gives WordPress and Minn the same day of work, compares what each
left behind row by row, then hands Minn's result back to WordPress and
checks that WordPress can read it and keep going.

The parity suites ask whether the engine answers like WordPress. This one
asks the question a site owner asks: if I switch, work for a day, and switch
back, is my site whole?

## The site

A local copy of a production site, with Minn installed in `public/` and
WordPress parked in `wp-reference/` (`wp minn install --park`).
`MINN_ROUNDTRIP_ROOT` picks the site; there are two so far:

- `cove-minn.localhost`, a copy of cove.run: a classic PHP theme, Gravity
  Forms, CleanTalk, CoBlocks, Code Snippets, Minn Admin, a WP Freighter
  tenant with the `stacked_15_` prefix. Its oracle is on 8129, and
  `run-all.sh` runs it.
- `shop-dogfood.localhost`, a copy of a production shop: WooCommerce (orders as
  posts), Gravity Forms and Gravity SMTP, Rank Math, Smush, Stripe, Xero,
  CaptainCore and about forty more plugins, 201 tables, two gigabytes.
  perfmatters moves its sign-in page to `/hideme/`. Its oracle is on 8127;
  `MINN_ROUNDTRIP_ROOT=~/Cove/Sites/shop-dogfood.localhost php
  tests/round-trip.test.php` runs it (about two and a half minutes).

`private/round-trip.json` also takes `login_path` (where a hide-login
plugin moved the sign-in page) and `skip_tables` (big tables the day has no
business touching, left out of the baseline to spare a full disk: the
baseline records them, a restore never touches them, and a day that writes
to one fails).

How the first copy was made, so the next one can be:

1. `cove add` the local site; stream the database from the host with
   `wp db export -` over SSH (read-only: nothing is written remotely) and
   import it; copy `wp-content`; search-replace the URLs with WP-CLI on the
   parked copy.
2. Install the engine (`wp minn install --park=wp-reference`), copy
   `wp-config.php` into the parked copy, symlink its `wp-content` back to
   `public/wp-content`, and give it a `router.php`.
3. Set a local password for an administrator, write it to
   `private/round-trip.json` (`user`, `password`, `url`, `oracle`), and
   make the starting point: `php tests/round-trip.test.php --set-baseline`
   copies the site's database to `{database}_rtbase` on the same server.

Nothing from the site enters this repository: the sign-in and the report
stay in the site's `private/` folder, the baseline in its own database.

Snapshots and restores never dump the database. Which tables changed comes
from InnoDB's update times; which rows differ, MariaDB works out itself by
primary key and a hash of each row; a restore puts back only those rows and
the next ids (`RtDatabase` in `tests/tools/round-trip.php`). Update times
are kept in memory, so the baseline records when the copy was last clean,
and after a server restart every table is compared by checksum instead. A
two-gigabyte site snapshots in seconds.

Two pieces make the copy fit to test on:

- **It runs offline.** The suite writes
  `wp-content/mu-plugins/zz-round-trip-offline.php` on every run: it
  refuses outbound HTTP to anything but this machine (spam checks, license
  pings and update checks never leave), defines `DISABLE_WP_CRON` so page
  loads run no scheduled jobs, and sends every message to the local mail
  catcher (Mailpit on 127.0.0.1:1025) from `phpmailer_init`, whatever SMTP
  server or mail plugin the site is set up with: a copy of a live site
  keeps its live mail settings. Both stacks load it, so a day's footprint is
  the work and nothing else. Requests to the site's own address are
  refused too: the parked WordPress serves from 127.0.0.1 under the site's
  name, so its loopbacks (Action Scheduler's async runner, a plugin's
  background upload) reached Minn through the web server, and once Minn
  answered admin-ajax.php it ran WordPress's background jobs mid-day.
  `php -S` could never answer its own loopbacks anyway.
- **Refusing HTTP is not enough on a shop.** `pre_http_request` sees only
  what goes through WordPress's HTTP API, and some plugins bring their own
  client. On shop-dogfood, WooCommerce Xero refreshes its OAuth token
  through Guzzle before it invoices, and it invoices when an order is
  completed, which the day does; the copy still holds the live site's
  credentials. The guard therefore also switches such services off by
  their settings, on both stacks: Xero's stored connection reads empty and
  it invoices manually with no payments, Twilio's Gravity Forms account
  reads empty, and Gravity SMTP's test mode is forced on (it holds every
  message before any connector runs). Checked on the 2026-10-05 run, before
  the guard had these lines: `xero_oauth_options` did not change on either
  day (a refreshed token is written back), no order gained Xero meta, the
  day submits no forms, and all 51 messages Gravity SMTP logged say "Test
  mode is enabled, sandboxing email". The order itself is direct bank
  transfer, never paid, so no gateway is asked. A copy of another live
  site needs its own list: look for plugins whose credentials are in the
  database and whose client is not `wp_remote_*`.
- **The oracle stands in for the site over HTTPS.** `php -S 127.0.0.1:8129
  router.php` serves a request as HTTPS when it carries `X-Forwarded-Proto:
  https`, and the suite sends `Host: cove-minn.localhost` with it, so
  WordPress signs in with secure cookies and builds links exactly as it
  does for the real site. Every request goes over IPv4 on both stacks, so
  plugins that key visitors by address (CleanTalk) see `127.0.0.1` on each.

## The day

1. Restore whatever changed since the copy was last clean (tables a day
   created are dropped).
2. **WordPress has the day.** A visitor browses (the front page, a post
   and one of its comment pages, the feed, a search, a missing page, the
   REST posts index, the sign-in page). Then the owner signs in through
   `wp-login.php`, changes the tagline, revises the oldest post and renames
   it, uploads a photo (made on the spot, the same bytes every run) and
   describes it, adds a category and a tag, publishes a post that uses all
   of them with the photo featured and in the content, comments and
   replies, revises a page, updates the profile through
   `/wp/v2/users/me`, adds an editor, and trashes a draft. On a shop
   (`/wp-json/wc/v3` answers) it also edits the oldest simple product's
   price and stock, adds a coupon where the shop takes them, takes an
   order for two of the product (with the coupon), notes it, and completes
   it. Comments are made only where the new post takes them. The snapshot
   after is WordPress's footprint; the files it uploaded are removed.
3. Restore, and **Minn has the same day**, request for request. Browsing
   compares each answer's status and where it redirects.
4. **WordPress takes it back**, on Minn's database: signs the owner in with
   the same password, reads the new post (title, status, category, tag,
   featured photo, the photo in the content), renders it, sends the
   renamed post's old address on to the new one (Minn does too), reads the
   photo's description and finds every size on disk, reads the revised
   post and its revisions, the comment thread, the tagline, the page, the
   profile and the trashed draft, signs Minn's new editor in as an editor,
   accepts the session Minn signed in without a second sign-in, then keeps
   working (revises Minn's post, answers the reply), and Minn reads what
   WordPress wrote. On a shop it reads Minn's order (completed, for the
   product, at the total WordPress's own day reached) and the product (the
   new price, the stock WordPress's own day left).
5. Restore, remove every uploaded file, and check the site is back at its
   baseline.

## Footprints

A snapshot is every table with the site's prefix, rows keyed by what they
are: options by name, meta by owner and key (with a count for repeats),
everything else by primary key. A footprint is what changed from one
snapshot to the next, one line per row (`added`, `removed`, or the columns
that changed; serialized arrays are compared leaf by leaf). To lay two
stacks' days side by side:

- times inside the run read `{now}` (as UTC or the site's local time), and
  Unix times a common span after it read `{now+14d}` and the like;
- password, reset and application-password hashes read `{hash}`, session
  keys `{sha256}`, UUIDs `{uuid}`, loopback addresses `{loopback}`;
- rows the day created read by kind and order (`{attachment#1}`,
  `{revision#2}`, `{user#1}`) wherever their IDs appear, so one missing row
  does not renumber the rest;
- the auto-increment IDs of meta and option rows never compare (nothing
  points at them).

Transients are left out of both footprints: they are caches. Everything
else is compared, and every difference has to be fixed or explained.

## Explained differences

Each is in `$EXPLAINED` in the suite with a test narrowing it to the part of
the row the reason is about, so anything else on that row still fails.

- **Minn's own options** (`minn_*`): the plugin symbol scan and similar
  bookkeeping, in options WordPress never reads.
- **Pingbacks.** Publishing on WordPress adds `_pingme` and `_encloseme` to
  the post and schedules `do_pings`: its to-do list for pingbacks,
  trackbacks and enclosure checks. Minn sends none of those, so it queues
  none. A site switched back finds no pings owed for posts written on Minn.
- **Image bytes.** The parked WordPress cuts sizes with Imagick, the engine
  with GD: the same files at the same dimensions, a different `filesize`.
- **CleanTalk's cron lock** is a fresh random number on every run.
- **The sign-in page** (`GET /wp-login.php`) sends a visitor to Minn
  Admin's (`/minn-admin/login`); the form's post is still taken there.

## What it found

The first runs, and what each became (each matched to the oracle):

| Finding | Where it is now |
|---|---|
| `POST /wp/v2/users/me` answered 404, so profile edits from any client but Minn Admin were lost | `contracts/rest/users.md` |
| A REST-made user was mailed a set-password link and stored a reset key; the reference does neither | `contracts/rest/users.md`, `contracts/cron-mail.md` |
| A REST-made user's display name was the login; the reference uses first and last name | `contracts/rest/users.md` |
| `user_count` never moved | `contracts/rest/users.md` |
| A new post's guid was always `?p=`; the reference stores the permalink for a live post | `contracts/rest/writes.md` |
| `categories: []` on create still gave the default category; an update never did | `contracts/rest/writes.md` |
| Trash only flipped the status: no `__trashed` slug, no desired slug, no revision, modified time unmoved | `contracts/rest/writes.md` |
| No `_wp_old_slug` / `_wp_old_date`, so a renamed post's old address was lost to WordPress | `contracts/rest/writes.md` |
| Uploads lacked the `1536x1536` / `2048x2048` sizes and ignored `intermediate_image_sizes_advanced` | `contracts/runtime.md` |
| A sign-in rewrote every stored session with four keys, dropping anything a plugin attached; an empty `ua` was stored | `contracts/runtime.md` |
| CleanTalk did not load (`wp_blacklist_check`); loaded, every page was a 500 (no-argument actions) | `contracts/runtime.md` |
| CleanTalk's settings (an `ArrayObject` inside an option) read back as a string, and the plugin saved defaults over them | `contracts/runtime.md` |
| Plugins heard nothing of a sign-in (`wp_login`, `wp_login_failed`, `wp_logout`) | `contracts/runtime.md` |
| `admin-ajax.php` did not declare `DOING_AJAX`, so a plugin took the nonce refresh for a page view | `contracts/runtime.md` |

shop-dogfood's first days added these:

| Finding | Where it is now |
|---|---|
| Every WooCommerce order made on Minn answered 500: `wp_after_insert_post` got three arguments in the wrong order | `contracts/runtime.md` |
| Order items lost their product, quantity and totals: the metadata API knew only the four core meta types | `contracts/runtime.md` |
| Order lines pointed at product 1: a multi-type schema took `array` before the type it listed first | `contracts/runtime.md` |
| A plugin's own objects (Freemius licenses, among others) were saved back as `stdClass` | `contracts/runtime.md` |
| A plugin that changed an object held in an option had the change skipped | `contracts/runtime.md` |
| New posts and attachments opened comments the site keeps closed | `contracts/rest/writes.md` |
| A floating draft kept its old date through a save or a trash | `contracts/rest/writes.md` |
| `category_children` was never rewritten, so WordPress would miss new child categories | `contracts/rest/terms.md` |
| Old addresses with a page number or trackback answered 500; comment-page addresses 404'd | `contracts/front/permalinks.md` |
| `admin-ajax.php` answered only the nonce refresh: no plugin's `wp_ajax_*` or `wp_ajax_nopriv_*` handler ran (Gravity Forms' submissions and feeds, CleanTalk's checks, WooCommerce's cart) | `contracts/runtime.md` |
| The `authenticate` chain never ran, so no plugin could refuse a sign-in or add a factor | `contracts/runtime.md` |

## Open

Found by the round trip and not done yet:

- **The engine's own REST writes tell plugins now** for posts, pages,
  comments and settings (`contracts/runtime.md`, "Writes tell plugins"):
  on shop-dogfood Minn's day sends the same 51 new-post notifications.
  Their bodies differ: the newsletter renders the post through
  `apply_filters('the_content')`, which on Minn has none of the
  reference's defaults behind it, so the mail carries raw block markup.
  Media, terms and users tell plugins too now.
- WordPress's own update checks on `admin_init` (`_maybe_update_*`) do
  not run on Minn (the engine has its own updater, and the reference
  skips them on `admin-ajax.php` anyway).
- Gravity Forms counts a form view on Minn where WordPress does not (one
  row on shop-dogfood), not yet looked into.

- Comments are not paged: with `page_comments` on, `comment-page-N`
  serves the post with every comment.
- A stored object of a class a plugin has loaded comes back a plain object
  where the reference instantiates the class, so plugin code calling its
  methods fails on Minn. Written back it now keeps its class
  (`contracts/runtime.md`).
- Scheduled jobs are off in the copy; whether the site's cron keeps
  running on Minn is a second day to write.

Running it: `php tests/round-trip.test.php` (about twenty seconds; the
oracle on 8129 must be up, `run-all.sh` starts it).
`MINN_ROUNDTRIP_KEEP=1` leaves the site as the swap back made it, for
looking around; the next run restores it.
